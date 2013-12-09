<?php

class DiscussionUtils {
  public static function create($user, $book_id, $comment, $caption, $text, $time) {
    $textUtils = new TextUtils();

    $comment = $textUtils->removeTags($comment);
    $caption = $textUtils->removeTags($caption);
    $text    = $textUtils->removeTags($text);

    $vote = Vote::fromPost();
    if ($vote && !$vote->isValid()) throw new DomainException('Ошибка добавления голосования.');

    // Убираем лишние пробелы и переводы строки.
    $text = $textUtils->removeSpaces($text);

    $antimat = Antimat::instance();

    if ($antimat->isUncensorship($comment) || $antimat->isUncensorship($caption) || $antimat->isUncensorship($text)) {
      throw new DomainException('Данное сообщение не может быть отправлено в связи с тем, что оно содержит слова, запрещенные на данном сайте');
    }

    if (!$vote && !TextUtils::lettersAndDigits($text)) {
      throw new DomainException('Ваше сообщение не может быть отправлено в связи с тем, что не заполнен текст сообщения.');
    }

    if (!TextUtils::lettersAndDigits($caption)) {
      throw new DomainException('Ваше сообщение не может быть отправлено в связи с тем, что не заполнен заголовок сообщения.');
    }

    if (ereg("[^[:space:]]{30}", $caption)) {
      throw new DomainException('Ваше сообщение не может быть отправлено в связи с тем, что заголовок сообщения содержит слишком длинные слова и будет неправильно показываться на книге.');
    }

    $text = MessageUtils::parse($text);

    $ip = $_SERVER["REMOTE_ADDR"];
    if (!BlockUtils::check_ip_block($user->user_id)) return 0;

    $query = sprintf("INSERT INTO gfb_discussion (userid, caption, date, last_time, first_time, book_id)
                           VALUES (%d, '%s', '%s', %d, %d, %d)",
                           $user->user_id, DB::safe($caption), date("Y-m-d H:i:s"), $time, $time, $book_id);

    DB::q($query);

    // Достаем идентификатор дискуссии:
    $discussion_id = DB::last_id();

    if (!$discussion_id) throw new DomainException('Ошибка добавления дискуссии.');

    if ($text) {
      // Формируем команду для добавления сообщения в дискуссию.
      $query = sprintf("INSERT INTO gfb_message (discussion_id, userid, date, comment, text, time, ip)
          VALUES ($discussion_id, %d, '%s', '%s', '%s', %d, '%s');",
          $user->user_id, date("Y-m-d H:i:s"), DB::safe($comment), DB::safe($text), $time, $ip);
      DB::q($query);
    }

    if ($vote) $vote->save($discussion_id, $user);
    return $discussion_id;
  }

  public static function update($d_id, $caption, $dont_archive) {
    $query = sprintf("UPDATE gfb_discussion
                         SET caption = '%s', dont_archive = '%s'
                       WHERE discussion_id = $d_id", DB::safe($caption), DB::bool2yn($dont_archive));
    DB::q($query);
  }

  public static function destroy($user, $d_id) {
    $user_id = $user->user_id;
    DB::q("UPDATE gfb_discussion SET deleted_by = $user_id WHERE discussion_id = $d_id");
    DB::q("UPDATE gfb_message SET deleted_by = $user_id WHERE discussion_id = $d_id AND deleted_by = 0;");
    UserRead::clear($d_id);
  }

  public static function restore($d_id) {
    DB::q("UPDATE gfb_discussion SET deleted_by = 0 WHERE discussion_id = $d_id");
    DB::q("UPDATE gfb_message SET deleted_by = 0 WHERE discussion_id = $d_id;");
  }

  public static function findById($discussion_id) {
    $query = "SELECT d.caption, d.dont_archive, d.first_time, d.is_archived, d.date, d.deleted_by, d.book_id, d.voting,
                    u.nick, u.user_id, b.book_name
               FROM gfb_discussion AS d,
                    gf_user AS u,
                    gfb_book AS b
              WHERE d.userid = u.user_id
                AND d.discussion_id = $discussion_id
                AND d.book_id = b.book_id";
    return DB::obj($query);
  }

  public static function archive($d_id) {
    DB::q("UPDATE gfb_discussion SET is_archived = 'Y' WHERE discussion_id = $d_id;");
    UserRead::clear($d_id);
  }

  public static function move($user, $d_id, $target_book) {
    global $twig;
    DB::q("UPDATE gfb_discussion SET book_id = $target_book WHERE discussion_id = $d_id;");
    // Если включены сообщения и есть групповой пользователь группы модераторов,
    // то отправляем сообщение от этого пользователя.
    $moderator_id = DB::field('gf_user', 'user_id', "realm_id = 'BOOK_MAIL'") + 0;
    if ($moderator_id) {
      $d_info = self::findById($d_id);
      PrivateUtils::send($user, $moderator_id, $d_info->user_id, 'Перенос дискуссии',
        $twig->render('discussions/move.notify.txt', array('info' => $d_info)));
    }
  }

  public static function messages_query($discussion_id, $include_deleted, $possible_user, $prev_read) {
    $blackList = $possible_user->black_list();
    $whereDeleted = ($include_deleted ? '' : " AND m.deleted_by < 1 ");
    return "SELECT m.message_id, m.discussion_id, m.userid, u.nick, m.date, m.comment, m.text, m.time, m.ip,
                   IF(m.time > $prev_read, 1, NULL) as is_new, m.deleted_by
              FROM gfb_message AS m, gf_user AS u
             WHERE m.discussion_id = $discussion_id AND m.userid = u.user_id $blackList $whereDeleted ORDER BY m.time;";
  }

  public static function discussion_options($book_id, $discussion_id) {
    $_ops = DB::all("SELECT discussion_id, caption
                       FROM gfb_discussion
                      WHERE (is_archived = 'N' AND deleted_by = 0 AND book_id = $book_id)
                         OR discussion_id = $discussion_id
                      ORDER BY first_time DESC;");
    $result = array();
    foreach ($_ops as $row) {
      $result[$row->discussion_id] = $row->caption;
    }
    return $result;
  }

  public static function updated_discussions($user, $possible_user, $book_id, $last_show_time) {
    $preference =& $possible_user->preferences();
    UserRead::synchronize($possible_user->user_id, $book_id);

    // Строим запрос на выборку книги согласно установкам.
    if ($user) {
        // Черный список не применяется.
      $query = "SELECT d.discussion_id, d.first_time, d.caption, d.voting, r.last_read
          FROM gfb_discussion AS d, gfb_user_read AS r
          WHERE book_id = $book_id AND d.deleted_by = 0
            AND d.is_archived = 'N'
            AND r.userid = $user->user_id AND r.discussion_id = d.discussion_id
            AND r.dont_trace = 'N'
            AND (d.last_time > r.last_read OR d.voting IS NOT NULL)";
    } else if ($possible_user->user_id) {
      $query = "SELECT d.discussion_id, d.first_time, d.caption, d.voting, $last_show_time AS last_read
          FROM gfb_discussion AS d, gfb_user_read AS r
          WHERE book_id = $book_id AND d.deleted_by = 0
            AND d.is_archived = 'N'
            AND r.userid = $possible_user->user_id AND r.discussion_id = d.discussion_id
            AND r.dont_trace = 'N'
            AND (d.last_time > $last_show_time OR d.voting IS NOT NULL)";
    } else {
      $query = "SELECT d.discussion_id, d.first_time, d.caption, d.voting, $last_show_time AS last_read
          FROM gfb_discussion AS d
          WHERE book_id = $book_id AND d.deleted_by = 0
            AND d.is_archived = 'N'
            AND (d.last_time > $last_show_time OR d.voting IS NOT NULL)";
    }
    // }
    if ($preference->sort_type) { // Сортировать по дате последнего
      $query .= ' ORDER BY d.last_time DESC, d.discussion_id';
    } else {
      $query .= ' ORDER BY d.first_time DESC, d.discussion_id';
    }
    $query .= ';';

    return DB::all($query);
  }

  public static function reset_last_time($discussion_id) {
    $query = "SELECT max(time) as max_time FROM gfb_message WHERE discussion_id = $discussion_id AND deleted_by = 0;";
    $ro = DB::obj($query);
    if ($ro && $ro->max_time > 0) {
      $query = sprintf("UPDATE gfb_discussion SET last_time=%d WHERE discussion_id = $discussion_id", $ro->max_time);
      DB::q($query);
    }
  }
}

?>