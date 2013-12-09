<?php

class MessageUtils {
  public static function verify_and_insert($discussion_id, $text, $comment, $time, $user) {
    $comment = TextUtils::removeTagsAndSpaces($comment);
    $text    = TextUtils::removeTagsAndSpaces($text);
    if (!TextUtils::lettersAndDigits($text)) throw new DomainException("Не заполнен текст сообщения.");

    $antimat = Antimat::instance();

    if ($antimat->isUncensorship($comment) || $antimat->isUncensorship($text)) {
      throw new DomainException('Сообщение содержит слова, запрещенные на данном сайте.');
    }

    $text = MessageUtils::parse($text);

    if (!BlockUtils::check_ip_block($user->user_id)) exit();
    self::insert($discussion_id, $text, $comment, $time, $user);
  }

  private static function insert($discussion_id, $text, $comment, $time_ms, $user) {
    $ip = $_SERVER["REMOTE_ADDR"];
    $query = sprintf("INSERT INTO gfb_message (discussion_id, userid, date, comment, text, time, ip)
        VALUES ($discussion_id, %d, '%s', '%s', '%s', %d, '%s');", $user->user_id, date("Y-m-d H:i:s"),
        DB::safe($comment), DB::safe($text), $time_ms, $ip);
    DB::q($query);
    $query = sprintf("UPDATE gfb_discussion SET last_time=%d WHERE discussion_id = $discussion_id", $time_ms);
    DB::q($query);
  }

  public static function update($message_id, $comment, $text) {
    $comment = DB::safe($comment);
    $text = DB::safe($text);
    DB::q("UPDATE gfb_message SET text = '$text', comment = '$comment' WHERE message_id = $message_id;");
  }

  /**
   * Process tables and huanize links
   *
   * Throws DomainException if message cannot be processed or contains errors.
   **/
  public static function parse($text) {
    $m = new BookMessage($text);
    return $m->getText();
  }

  /*
   * Prepare source message to display on the book
   * - process links
   * - process tables
   * - replaces \n with br
   */
  public static function parseMessage($src) {
    try {
      $src = self::parse($src);
    } catch (DomainException $e) {
      $er = $e->getMessage();
      $src .= "\n<b>Ошибка обработки сообщения: $er</b>";
    }
    return ereg_replace("\n", "\n<BR>", $src);
  }

  public static function find($message_id) {
    $query = "SELECT m.message_id, m.discussion_id, m.userid, m.userid as user_id, u.nick, m.date, m.comment, m.text,
                     m.time, m.ip, m.deleted_by, d.caption, d.book_id, IF(d.first_time = m.time, 0, 1) AS is_answer,
                     d.first_time
                FROM gfb_message AS m, gf_user AS u, gfb_discussion AS d
               WHERE m.discussion_id = d.discussion_id AND m.message_id = $message_id AND m.userid = u.user_id;";
    return DB::obj($query);
  }

  /**
   * Array of messages added into the book for given period
   **/
  public static function for_period($discussion_id, $possible_user, $from, $to, $mode = 'new') {
    $black = $possible_user->black_list();
    $query = "SELECT m.message_id, m.userid, m.date, m.comment, m.text, m.time, m.ip,
      u.nick, IF(m.time > $from, 1, 0) AS is_new, d.first_time
      FROM gfb_message AS m, gf_user AS u, gfb_discussion AS d
      WHERE m.deleted_by = 0 AND m.discussion_id = $discussion_id
        AND d.discussion_id = $discussion_id
        AND m.userid = u.user_id $black ";
    if ($mode == 'new') $query .= " AND m.time > $from";
    $query .= " AND m.time <= $to ";
    $query .= ' ORDER BY m.message_id';
    $query .= ';';
    return DB::all($query);
  }

  public static function prev_messages($discussion_id, $possible_user, $last) {
    $black = $possible_user->black_list();
    $query = "SELECT m.message_id, m.userid, m.date, m.comment, m.text, m.time, m.ip,
      u.nick, 0 AS is_new, d.first_time
      FROM gfb_message AS m, gf_user AS u, gfb_discussion AS d
      WHERE m.deleted_by = 0 AND m.discussion_id = $discussion_id
        AND d.discussion_id = $discussion_id
        AND m.userid = u.user_id $black
        AND m.time < $last
      ORDER BY message_id DESC
      LIMIT 5;";
    return array_reverse(DB::all($query));
  }


  public static function toggle($user, $message_id, $delete) {
    $query = sprintf("UPDATE gfb_message SET deleted_by = %d WHERE message_id = %d", $delete ? $user->user_id : 0, $message_id);
    DB::q($query);
    $discussion_id = DB::field('gfb_message', 'discussion_id', "message_id = $message_id");
    DiscussionUtils::reset_last_time($discussion_id);
  }

  public static function move_to_discussion($message_id, $old_discussion_id, $discussion_id) {
    DB::q("UPDATE gfb_message SET discussion_id = $discussion_id WHERE message_id = $message_id");
    DiscussionUtils::reset_last_time($old_discussion_id);
    DiscussionUtils::reset_last_time($discussion_id);
  }

  public static function destroy($user, $message_id) {
    DB::q("UPDATE gfb_message SET deleted_by = $user->user_id WHERE message_id = $message_id");
  }
}

define('TABLE_BEGIN', '[TABLE]');
define('TABLE_END', '[/TABLE]');

// ========================================
class BookMessageItem {
  function getText() {}
}

// ========================================
class BookTextItem extends BookMessageItem {
  var $text;
  function  BookTextItem($text) {
    if (ereg("([^[:space:]]{80})", $text)) {
      throw new DomainException("Данное сообщение не может быть отправлено в связи с тем, что текст сообщения содержит слишком длинные слова и будет неправильно показываться на книге.");
    }
    $this->text = $text;
  }

  function getText() {
    return $this->text;
  }
}

// ========================================
class BookLinkItem extends BookMessageItem {
  var $text;
  function  BookLinkItem($url) {
    $visible = $url;
    $len = strlen($visible);
    if ($len > 80) {
      $visible = substr($visible, 0, 37) . '...' . substr($visible, $len - 37);
    }
    $this->text .= '<A HREF="' . $url . '" target="_blank">' . $visible . '</A>';
  }
  function getText() {
    return $this->text;
  }
}

// ========================================
class CompositeBookItem extends BookMessageItem {
   var $items = array();

   function addItem(&$item) {
     $this->items[] = & $item;
   }

   function addItemCopy(&$item) {
     $this->items[] = $item;
   }

  function getText() {
    $result = '';
    reset($this->items);
    while(list($key, $item) = each($this->items)) {
      $result .= $item->getText();
    }
    return $result;
  }

}

// ========================================
class CompositeBookTextItem extends CompositeBookItem {
  function CompositeBookTextItem($text) {
    $out = array();
    if (preg_match_all ("/(https?:\/\/\S+)/", $text, $out)) {
      reset($out[0]);
      while(list($key, $value) = each($out[0])) {

        $pos = strpos($text, $value);
        if (is_integer($pos)) {
          $len = strlen($value);
          if ($pos > 0) {
            $this->addItem(new BookTextItem(substr($text, 0, $pos)));
          }
          $text = substr($text, $pos + $len);
          $this->addItem(new BookLinkItem($value));
        }
      }
    }
    if ($text) {
      $this->addItem(new BookTextItem($text));
    }
  }

}

// ========================================
class BookTableItem extends CompositeBookItem {
  var $columns = 0;
  function BookTableItem($text) {
    $rows = split("\n", $text);
    $table = array();
    $columns = 0;
    while(list($key, $row) = each($rows)) {
      if ($row) {
        $rowItem = new BookTableRowItem($row);
        if ($rowItem->columns > 0) {
          if ($rowItem->columns > $this->columns) {
            $this->columns = $rowItem->columns;
          }
          $this->addItemCopy($rowItem);
        }
      }
    }
  }

  function getText() {
    if (!$this->columns) {
      return '';
    }
    reset($this->items);
    while(list($key, $row) = each($this->items)) {
      $result .= $row->getRow($this->columns);
    }
    if ($result) {
      $result = "<table>$result</table>";
    }
    return $result;
  }
}

// ========================================
class BookTableRowItem extends CompositeBookItem {
  var $columns = 0; // последняя непустая колонка.
  function BookTableRowItem($row) {
    $r = split(";", $row);
    $cnt = count($r);
    if ($cnt > 10) {
      throw new DomainException("Данное сообщение не может быть отправлено, так как содержит слишком широкую таблицу. Таблица должна содержать не более 10 столбцов");
    }
    // Вычисляем последнюю непустую колонку.
    $i = 0;
    while(list($key, $cell) = each($r)) {
      $i++;
      if ($cell == "0") {
        $cell = "0&nbsp;";
      }
      if (trim($cell)) {
        $this->columns = $i;
      }
      $this->addItem(new CompositeBookTextItem($cell));
    }
  }

  function getRow($count) {
    $result = '<tr>';
    for($i = 0; $i < $count; $i++) {
      $item = $this->items[$i];
      if ($item) {
        $text = $item->getText();
      } else {
        $text = '';
      }
      $result .= '<TD>' . $text . '</TD>';
    }
    $result .= '</tr>';
    return $result;
  }
}

// ========================================
class BookMessage extends CompositeBookItem {
  function BookMessage($text) {
    while($text) {
      $pos = strpos($text, TABLE_BEGIN);
      if (!is_integer($pos)) {
        $this->addItem(new CompositeBookTextItem($text));
        $text = '';
      } else {
        if ($pos > 0) {
          $this->addItem(new CompositeBookTextItem(substr($text, 0, $pos)));
        }
        $text = substr($text, $pos + strlen(TABLE_BEGIN));
        $pos = strpos($text, TABLE_END);
        if (!is_integer($pos)) {
          $this->addItem(new BookTableItem($text));
          $text = '';
        } else {
          $this->addItem(new BookTableItem(substr($text, 0, $pos)));
          $text = substr($text, $pos + strlen(TABLE_END));
        }
      }
    }
  }
}


?>