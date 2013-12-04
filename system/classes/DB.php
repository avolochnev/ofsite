<?php

class DB {
  var $connection;
  var $db_name;
  static $instance = null;

  function __construct($db_name, $user, $password, $host = 'localhost') {
    $this->connection = mysql_connect($host, $user, $password);
    $this->db_name = $db_name;
  }

  private static function load() {
    if (!self::$instance) {
      self::$instance = new DB(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST);
      mysql_set_charset('utf8');
    }
    return self::$instance;
  }

  public static function q($query) {
    $db = self::load();
    if (!$result = mysql_db_query($db->db_name, $query, $db->connection)) {
      throw new DomainException(mysql_error() . ' in ' . $query);
    }
    return $result;
  }

  public static function safe($str) {
    $db = self::load();
    return mysql_real_escape_string(stripcslashes($str), $db->connection);
  }

  public static function one($table, $where, $columns = '*') {
    $result = self::q("SELECT $columns FROM $table WHERE $where LIMIT 1;");
    return mysql_fetch_object($result);
  }

  public static function all($query) {
    $r = self::q($query);
    $result = array();
    while ($ro = mysql_fetch_object($r)) $result[] = $ro;
    return $result;
  }

  public static function obj($query) {
    return mysql_fetch_object(self::q($query));
  }

  public static function last_id() {
    $ro = self::obj("SELECT LAST_INSERT_ID() AS id;");
    if ($ro) return $ro->id;
    return FALSE;
  }

  public static function field($table, $field, $where) {
    $query = "SELECT $field as f FROM $table WHERE $where;";
    $ro = DB::obj($query);
    if (!$ro) {
      return FALSE;
    } else {
      return $ro->f;
    }
  }

  public static function count($table, $where) {
    return self::field($table, 'count(1)', $where);
  }

  public static function i($query) {
    return new ResultIterator($query);
  }

  public static function map($query) {
    $r = DB::q($query);
    $map = array();
    while($ro = mysql_fetch_array($r)) $map[$ro[0]] = $ro[1];
    return $map;
  }

  // Меняет Y/N на 1/0
  public static function yn2bool($value) {
    return ($value == 'Y' ? 1 : 0);
  }

  // Меняет 1/0 на Y/N
  public static function bool2yn($value) {
    return ($value ? 'Y' : 'N');
  }
}

class ResultIterator implements Iterator {
  var $result;
  var $current;
  var $index = 0;
  var $query;

  function __construct($query) {
    $this->query = $query;
    $this->result = DB::q($this->query);
  }

  function rewind() {
    $this->index = -1;
    $this->next();
  }

  function current() {
    return $this->current;
  }

  function key() {
    return $this->index;
  }

  function next() {
    ++$this->index;
    $this->current = mysql_fetch_object($this->result);
  }

  function valid() {
    return $this->current;
  }

  function size() {
    return mysql_num_rows($this->result);
  }
}

?>