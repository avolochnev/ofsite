<?php

class UserRead {
  var $last_read = 0;
  var $prev_read = 0;
  var $dont_trace;
  var $user_id;
  var $discussion_id;
  var $loaded = false;

  public function save() {
    if ($this->loaded) {
      $query = "UPDATE gfb_user_read SET last_read = $this->last_read, prev_read = $this->prev_read WHERE userid = $this->user_id AND discussion_id = $this->discussion_id;";
    } else {
      $query = "INSERT INTO gfb_user_read (userid, discussion_id, last_read, prev_read) VALUES ($this->user_id, $this->discussion_id, $this->last_read, $this->prev_read);";
    }
    DB::q($query);
  }

  public function mark($last_read) {
    $this->prev_read = $this->last_read;
    $this->last_read = $last_read;
    return $this;
  }

  public static function load($user_id, $discussion_id) {
    $r = new UserRead();
    $r->user_id       = $user_id;
    $r->discussion_id = $discussion_id;
    $query = "SELECT * FROM gfb_user_read WHERE userid = $user_id AND discussion_id = $discussion_id";
    if ($ro = DB::obj($query)) {
      $r->last_read     = $ro->last_read;
      $r->prev_read     = $ro->prev_read;
      $r->dont_trace    = $ro->dont_trace;
      $r->loaded        = true;
    }
    return $r;
  }

  public static function clear($discussion_id) {
    DB::q("DELETE FROM gfb_user_read WHERE discussion_id = $discussion_id;");
  }

  public static function init($user_id, $discussion_id) {
    $r = new UserRead();
    $r->user_id       = $user_id;
    $r->discussion_id = $discussion_id;
    $r->save();
  }

  public static function dont_trace($user, $discussion_id, $dont_trace) {
    if (!$user) return;
    $query = sprintf("UPDATE gfb_user_read SET dont_trace = '$dont_trace' WHERE userid = %d AND discussion_id = $discussion_id;",
         $user->user_id);
    DB::q($query);
  }

  public static function synchronize($user_id, $book_ids){
    if (!$user_id) return;
    if (!is_array($book_ids)) $book_ids = array($book_ids);
    if (empty($book_ids)) return;

    $books_where = array();
    foreach ($book_ids as $id) $books_where[] = "d.book_id = $id";
    $where = implode(" OR ", $books_where);
    // Для этого сначала достаем полный список дискуссий
    $query = "SELECT discussion_id FROM gfb_discussion AS d WHERE ($where) AND deleted_by = 0 AND is_archived = 'N';";
    $arr = array();
    foreach (DB::i($query) as $ro) $arr[$ro->discussion_id] = 1;

    // Теперь достаем список всех просмотренных дискуссий, и сверяем с имеющимся.
    $query = "SELECT r.discussion_id FROM gfb_user_read AS r, gfb_discussion AS d WHERE r.userid = $user_id AND d.discussion_id = r.discussion_id AND ($where);";
    foreach (DB::i($query) as $ro) $arr[$ro->discussion_id] = 0;

    // В результате в массиве единицы остались только у тех дискуссий, для которых у нас нет метки чтения.
    // Создаем эти метки.
    reset($arr);
    while (list ($key, $value) = each($arr)) {
      if ($value) UserRead::init($user_id, $key);
    }

  }

  public static function last_read($user_id, $discussion_id) {
    $read = self::load($user_id, $discussion_id);
    return $read->last_read;
  }

  public static function mark_discussion_as_read($user_id, $discussion_id, $time_ms) {
    $read = self::load($user_id, $discussion_id);
    $read->mark($time_ms);
    $read->save();
  }
}

?>