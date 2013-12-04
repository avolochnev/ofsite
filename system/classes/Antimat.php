<?php

class Antimat {
  protected static $_instance;
  var $words;

  function Antimat($fileName) {
    $this->words = array();
    $f = file($fileName);
    if (!$f) {
      return;
    }
    foreach($f as $line) {
      $word = trim(TextUtils::rusUpperCase($line));
      if ($word != '') {
        $pos = strpos($word, '#');
        if ($pos === false || $pos > 0) {
          $this->words[] = $word;
        }
      }
    }

  }

  // Возвращает 1, если переданный текст содержит нецензурные выражения.
  function isUncensorship($text) {

    $text = TextUtils::rusUpperCase($text);
    $text = ereg_replace("[\"'!-<>&;\(\)\.\,\n\*\?]", " ", $text);
    $text = ereg_replace("  ", " ", $text);
    $textarray = split (" ", $text);

    foreach($this->words as $value) {
      reset($textarray);
      while(list($key1, $value1) = each($textarray)) {
        if ($value == $value1) {
          return 1;
        }
      }
    }
    return 0;
  }

  public static function instance() {
    if (null === self::$_instance) {
      self::$_instance = new self('./config/antimat.txt');
    }
    // возвращаем созданный или существующий экземпляр
    return self::$_instance;
  }
}

?>