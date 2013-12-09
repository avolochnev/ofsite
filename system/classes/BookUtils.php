<?php

class BookUtils {
  public static function updates($user, $possible_user, $current_book) {
    $updates = self::book_updates($possible_user, $current_book);

    if ($user && strpos($_SERVER['REQUEST_URI'], '/private') === FALSE) {
      if ($newPrivateCount = PrivateUtils::new_count($user->user_id)) {
        $updates[] = self::private_update_line($newPrivateCount);
      }
    }

    return $updates;
  }

  private static function book_updates($possible_user, $current_book) {
    $traceBooks = $possible_user->traceable_books();
    if ($current_book) unset($traceBooks[$current_book->book_id]);
    if (empty($traceBooks)) return array();

    $updates = array();
    if ($possible_user->user_id) UserRead::synchronize($possible_user->user_id, $traceBooks);
    $blackList = $possible_user->black_list();
    $common_where = "d.deleted_by = 0 AND d.is_archived = 'N' AND d.discussion_id = m.discussion_id
                 AND m.deleted_by = 0 AND m.userid <> 0 $blackList";
    $read_where = "r.userid = $possible_user->user_id AND r.discussion_id = d.discussion_id AND r.dont_trace = 'N'";

    if ($possible_user->is_logged) {
      $book_ids = implode(', ', $traceBooks);
      $query = "SELECT b.book_id, b.book_name, count(1) AS new_messages
                  FROM gfb_book AS b, gfb_discussion AS d, gfb_user_read AS r, gfb_message AS m
                 WHERE d.book_id = b.book_id AND b.book_id IN ($book_ids) AND $common_where AND $read_where
                   AND r.last_read < m.time
                 GROUP BY d.book_id
                 ORDER BY priority DESC, b.book_id;";
      foreach(DB::i($query) as $ro) $updates[] = self::update_line($ro->book_id, $ro->book_name, $ro->new_messages);
    } else {
      $books = $possible_user->available_books();
      foreach($traceBooks as $id => $value) {
        $last_show_time = $_COOKIE["board_$id"] + 0;
        if ($possible_user->user_id) {
          $query = "SELECT count(1) AS new_messages FROM gfb_discussion AS d, gfb_user_read AS r, gfb_message AS m
                     WHERE d.book_id = $id AND m.time > $last_show_time AND $common_where AND $read_where";
        } else {
          $query = "SELECT count(1) AS new_messages FROM gfb_discussion AS d, gfb_message AS m
                     WHERE d.book_id = $id AND m.time > $last_show_time AND $common_where";
        }
        $ro = DB::obj($query);
        if($ro->new_messages > 0) {
          $updates[] = self::update_line($id, $books[$id]->book_name, $ro->new_messages);
        }
      }
    }
    return $updates;
  }

  private static function update_line($book_id, $book_name, $new_messages) {
    return array('id' => $book_id,
                 'name' => $book_name,
                 'url' => "books.phtml?book=$book_id&action=show",
                 'cnt' => $new_messages);
  }

  private static function private_update_line($new_messages) {
    return array('id' => 'private',
                 'name' => 'Личные сообщения',
                 'url' => 'private.phtml',
                 'cnt' => $new_messages);
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