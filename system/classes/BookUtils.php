<?php

class BookUtils {
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

  public static function landingPages() {
    return array(0 => array('Список дискуссий', 'discussions.phtml', ''),
                 1 => array('Новые сообщения', 'messages.phtml', 'mode=new'),
                 2 => array('Обновленные дискуссии', 'messages.phtml', ''),
                 3 => array('Список обновлений', 'discussions.phtml', 'mode=new')
                );
  }
}

?>