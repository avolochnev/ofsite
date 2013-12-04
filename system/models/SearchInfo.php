<?php

// ==================
class SearchInfo {
  var $nick;
  var $from_day;
  var $from_month;
  var $from_year;
  var $to_day;
  var $to_month;
  var $to_year;
  var $message_text;
  var $discussion;
  var $book_id;
  var $current_book;

  function SearchInfo($current_book) {
    $this->nick = stripslashes($_GET["nick"]);
    $this->from_day = $_GET["from_day"] + 0;
    $this->from_month = $_GET["from_month"] + 0;
    $this->from_year = $_GET["from_year"] + 0;
    $this->to_day = $_GET["to_day"] + 0;
    $this->to_month = $_GET["to_month"] + 0;
    $this->to_year = $_GET["to_year"] + 0;
    $this->message_text = stripslashes($_GET["message_text"]);
    $this->discussion = stripslashes($_GET["discussion"]);
    $this->current_book = $current_book;
    $this->book_id = $current_book->book_id;

    if ($this->from_year) {
      if (!$this->from_month) $this->from_month = 1;
      if (!$this->from_day)   $this->from_day   = 1;
    }

    if ($this->to_year) {
      if (!$this->to_month) $this->to_month = 12;
      if (!$this->to_day)   $this->to_day = 31;
    }
  }

  function where() {
    $where = array();
    if ($this->from_year) {
      $where[] = sprintf("m.date >= '%s'", DateUtils::concatDate($this->from_year, $this->from_month, $this->from_day));
    }

    if ($this->to_year) {
      $where[] = sprintf("m.date < '%s'", DateUtils::concatDate($this->to_year, $this->to_month, $this->to_day + 1));
    }

    if ($this->nick) {
      $u = UserUtils::findByNick($this->nick);
      $user_id = $u->user_id + 0;
      $where[] = "m.userid = $user_id";
    }

    if ($this->message_text) {
      $tmpWhere = TextUtils::getSearchWhere("UCASE(m.text)", $this->message_text);
      if (count($tmpWhere)) $where = array_merge($where, $tmpWhere);
    }

    if ($this->discussion) {
      $tmpWhere = TextUtils::getSearchWhere("d.caption", $this->discussion);
      if (count($tmpWhere)) $where = array_merge($where, $tmpWhere);
    }
    return $where;
  }

  function query() {
    $where = $this->where();
    // Выполняем запрос.
    if (count($where)) {
      $query = sprintf("SELECT m.message_id, m.discussion_id
          FROM gfb_message AS m, gfb_discussion AS d
          WHERE book_id = %d AND m.discussion_id = d.discussion_id AND ",
          $this->book_id);

      $is_admin = $this->current_book->is_admin;
      if (!$is_admin) $query .= ' m.deleted_by = 0 AND d.deleted_by = 0 AND ';
      $query .= implode(' AND ', $where) . ' ORDER BY d.discussion_id DESC, m.date LIMIT 500;';
      return $query;
    } else {
      return null;
    }
  }

  function paginator() {
    $query = $this->query();
    if (!$query) return null;
    $paginator = new Paginator($query, 30);
    $rows = array();
    foreach ($paginator->data as $ro) {
      $rows[] = MessageUtils::find($ro->message_id);
    }
    $paginator->data = $rows;
    return $paginator;
  }
}

?>