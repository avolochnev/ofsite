<?php

class BookUtils {
  public static function last_read($discussion_id, $user_id) {
    $read = UserRead::load($user_id, $discussion_id);
    return $read->last_read;
  }

  public static function mark_discussion_as_read($user_id, $discussion_id, $time_ms) {
    $read = UserRead::load($user_id, $discussion_id);
    $read->mark($time_ms);
    $read->save();
  }

  public static function moveMessageToDiscussion($message_id, $old_discussion_id, $discussion_id) {
    DB::q("UPDATE gfb_message SET discussion_id = $discussion_id WHERE message_id = $message_id");
    self::resetLastTime($old_discussion_id);
    self::resetLastTime($discussion_id);
  }

  function resetLastTime($discussion_id) {
    $query = "SELECT max(time) as max_time FROM gfb_message WHERE discussion_id = $discussion_id AND deleted_by = 0;";
    $ro = DB::obj($query);
    if ($ro && $ro->max_time > 0) {
      $query = sprintf("UPDATE gfb_discussion SET last_time=%d WHERE discussion_id = $discussion_id", $ro->max_time);
      DB::q($query);
    }
  }

  function deleteMessage($message_id, $user) {
    DB::q("UPDATE gfb_message SET deleted_by = $user->user_id WHERE message_id = $message_id");
  }

  function deleteDiscussion($discussion_id, $user) {
    DB::q("UPDATE gfb_discussion SET deleted_by = $user->user_id WHERE discussion_id = $discussion_id");
    DB::q("UPDATE gfb_message SET deleted_by = $user->user_id WHERE discussion_id = $discussion_id AND deleted_by = 0");
  }

  public static function updates($user, $possible_user, $current_book) {
    $current_book_id = $current_book ? $current_book->book_id : 0;
    $preference = $possible_user->preferences();

    // 1. Получаем список книг.
    $books = $possible_user->available_books();
    // 1.1. Исключаем текущую книгу, а также книги, которые пользователь не отслеживает.
    $traceBooks = array();
    foreach ($books as $book) $traceBooks[$book->book_id] = $book->book_id;
    if ($current_book_id) unset($traceBooks[$current_book_id]);
    foreach (split(',', $preference->dont_trace_books) as $id) unset($traceBooks[$id]);

    reset($traceBooks);
    $blackList = $possible_user->black_list();
    $user_id = $possible_user->user_id;
    $updates = array();
    UserRead::synchronize($user_id, $traceBooks);

    if ($user) {
      $wherePart = '';
      while(list($id, $value) = each($traceBooks)) {
        if ($value) {
          $wherePart .= ($wherePart ? ' OR ' : '') . 'd.book_id = ' . $id;
        }
      }

      if ($wherePart) {
        $wherePart = "($wherePart)";

        // 3. Строим и выполняем запрос по выводу количества новых сообщений в книгах.
        $query = "SELECT b.book_id, count(1) AS new_messages
                    FROM gfb_book AS b, gfb_discussion AS d, gfb_user_read AS r, gfb_message AS m
                   WHERE r.userid = $user->user_id AND r.discussion_id = d.discussion_id AND d.book_id = b.book_id
                     AND r.discussion_id = m.discussion_id AND $wherePart AND d.deleted_by = 0
                     AND d.is_archived = 'N' AND m.deleted_by = 0 AND r.last_read < m.time $blackList
                     AND r.dont_trace = 'N' AND m.userid <> 0
                   GROUP BY d.book_id
                   ORDER BY priority DESC, b.book_id;";

        foreach(DB::i($query) as $ro) {
          $updates[] = array(
            'id' => $ro->book_id,
            'name' => $books[$ro->book_id]->book_name,
            'url' => "books.phtml?book=$ro->book_id&action=show",
            'cnt' => $ro->new_messages);
        }
      }

      if (strpos($_SERVER['REQUEST_URI'], '/private') === FALSE) {
        $newPrivateCount = PrivateUtils::new_count($user->user_id);
        if ($newPrivateCount) {
          $updates[] = array(
            'id' => 'private',
            'name' => 'Личные сообщения',
            'url' => "private.phtml",
            'cnt' => $newPrivateCount);
        }
      }
    } else {
      while(list($id, $value) = each($traceBooks)) {
        if ($value) {
          $last_show_time = $_COOKIE["board_$id"] + 0;
          $wherePart = "(d.book_id = $id AND m.time > $last_show_time)";
          // 3. Строим и выполняем запрос по выводу количества новых сообщений в книгах.
          if ($user_id) {
            // 2. Синхронизируем таблицу READ для этих книг.
            $query = sprintf("SELECT count(1) AS new_messages FROM gfb_discussion AS d, gfb_user_read AS r, gfb_message AS m
                 WHERE r.userid = %d AND r.discussion_id = d.discussion_id AND r.discussion_id = m.discussion_id
                   AND $wherePart AND d.deleted_by = 0 AND d.is_archived = 'N' AND m.deleted_by = 0 $blackList
                   AND r.dont_trace = 'N' AND m.userid <> 0;", $user_id);
          } else {
            $query = "SELECT count(1) AS new_messages FROM gfb_discussion AS d, gfb_message AS m
                 WHERE d.discussion_id = m.discussion_id AND $wherePart AND d.deleted_by = 0 AND d.is_archived = 'N' AND m.deleted_by = 0 $blackList
                   AND m.userid <> 0;";
          }
          if($ro = DB::obj($query)) {
            if ($ro->new_messages > 0) {
              $updates[] = array(
                'id' => $id,
                'name' => $books[$id]->book_name,
                'url' => "books.phtml?book=$id&action=show",
                'cnt' => $ro->new_messages);
            }
          }
        }
      }
    }
    return $updates;
  }

  public static function discussion_updates($user, $book_id, $except_discussion_id = 0) {
    if (!$user) return null;
    $preference = $user->preferences();
    if ($preferences->as_book) return null;
    return DB::all(self::getDiscussionListQuery2($user, $user, $book_id, 'only new', 5, $except_discussion_id));
  }

  public static function landingPages() {
    return array(0 => array('Список дискуссий', 'discussions.phtml', ''),
                 1 => array('Новые сообщения', 'messages.phtml', 'mode=new'),
                 2 => array('Обновленные дискуссии', 'messages.phtml', ''),
                 3 => array('Список обновлений', 'discussions.phtml', 'mode=new')
                );
  }

  // ==========================================================
  // Функция возвращает запрос к базе данных для формирования списка дискуссий.
  // $preference - объект Preference с настройкам показа книги.
  // $user - объект User или 0, если вход анонимный.
  // $onlyNew -  показывать только новые вне зависимости от настроек.
  // $limit - ограничение количества возвращаемых дискуссий. По умолчанию возвращаются все дискуссии.
  // $except_discussion_id - discussion to be expcluded from result (usage: to exclude current discussion)
  // ==========================================================
  // Требует:
  // classes/db
  public static function getDiscussionListQuery2($user, $possible_user, $book_id, $onlyNew = 0, $limit = 0, $except_discussion_id = 0) {
    $preference =& $possible_user->preferences();
    $blackList =& $possible_user->black_list();
    UserRead::synchronize($possible_user->user_id, $book_id);

    $user_id = $possible_user->user_id;

    $hide = $except_discussion_id ? " AND d.discussion_id <> $except_discussion_id" : '';

    // Считываем список дискуссий, для которых хранится время последнего прочтения, с количеством новых сообщений и временем последнего сообщения.
    if ($user) {
      // Выбираем все дискуссии, по которым у пользователя нет метки о просмотре.
      // Добавляем метки просмотра для всех этих дискуссий.
      $query = sprintf("
         SELECT d.discussion_id, caption, last_time, first_time, d.date, d.userid, u.nick, COUNT(IF(m.deleted_by = 0, 1, NULL)) as full_count,
             COUNT(IF(r.last_read < m.time AND r.dont_trace = 'N' AND (m.deleted_by = 0 OR m.deleted_by IS NULL), 1, NULL)) as new_count,
             MAX(m.time) as max_time, r.dont_trace, d.voting
             FROM gfb_discussion AS d LEFT JOIN gfb_message AS m USING (discussion_id), gf_user AS u, gfb_user_read AS r
             WHERE book_id = %d AND d.deleted_by = 0 AND d.is_archived = 'N'
                 AND d.discussion_id = r.discussion_id AND r.userid = %d AND d.userid = u.user_id $blackList $hide
             GROUP BY discussion_id", $book_id, $user->user_id);

      // 7. Если стоит настройка показыать только дискуссии с новыми сообщениями, убираем лишние сообщения.
      if ($onlyNew) {
        $query .= ' HAVING new_count > 0';
      }
    } else {
      $query = sprintf("
          SELECT d.discussion_id, caption, last_time, first_time, d.date, d.userid, d.voting, u.nick, COUNT(DISTINCT m.message_id) as full_count
              FROM gfb_discussion AS d, gfb_message AS m, gf_user AS u
              WHERE book_id = %d AND d.deleted_by = 0 AND d.is_archived = 'N' AND d.discussion_id = m.discussion_id AND m.deleted_by = 0
              AND d.userid = u.user_id $blackList $hide
              GROUP BY discussion_id", $book_id);
    }

    // 8. Сортируем сообщения согласно настройке.
    if ($preference->sort_type) { // Сортировать по дате последнего
      if (!$user) {
        $query .= ' ORDER BY last_time DESC';
      } else {
        $query .= ' ORDER BY max_time DESC, last_time DESC';
      }
    } else {
      $query .= ' ORDER BY first_time DESC';
    }

    if ($limit) {
      $query .= " LIMIT $limit";
    }

    $query .= ';';
    return $query;
  }

  public static function query_deleted($book_id, $possible_user) {
    $black = $possible_user->black_list();
    $preference = $possible_user->preferences();

    // Формируем запрос.
    // Строим запрос на выборку книги согласно установкам.
    $query = sprintf("SELECT m.message_id, m.discussion_id, m.userid, m.date, m.comment, m.text, m.time, m.ip, d.caption, d.first_time, u.nick, m.deleted_by
        FROM gfb_message AS m, gfb_discussion AS d, gf_user AS u
        WHERE book_id = %d AND m.deleted_by <> 0 AND m.discussion_id = d.discussion_id AND d.is_archived = 'N' AND m.userid = u.user_id $black",
        $book_id); //

    if ($preference->sort_type) { // Сортировать по дате последнего
      $query .= ' ORDER BY d.last_time DESC, m.time';
    } else {
      $query .= ' ORDER BY d.first_time DESC, m.time';
    }
    $query .= ' LIMIT 500;';
    return $query;
  }

  public static function query_all($book_id, $possible_user) {
    $black = $possible_user->black_list();
    $preference = $possible_user->preferences();

    // Формируем запрос.
    // Строим запрос на выборку книги согласно установкам.
    $query = "SELECT m.message_id, m.discussion_id, m.userid, m.date, m.comment, m.text, m.time, m.ip, d.caption,
                     d.first_time, u.nick, m.deleted_by
                FROM gfb_message AS m, gfb_discussion AS d, gf_user AS u
               WHERE book_id = $book_id AND d.deleted_by = 0 AND m.deleted_by = 0 AND m.discussion_id = d.discussion_id
                 AND d.is_archived = 'N' AND m.userid = u.user_id $black"; //

    if ($preference->sort_type) { // Сортировать по дате последнего
      $query .= ' ORDER BY d.last_time DESC, m.time';
    } else {
      $query .= ' ORDER BY d.first_time DESC, m.time';
    }
    $query .= ' LIMIT 500;';
    return $query;
  }
}

?>