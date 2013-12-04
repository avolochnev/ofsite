<?php

class TextUtils {
  function removeSpaces($text) {
    // В сообщении все тройные переводы строки меняем на двойные - блокируем большие свободные пространства.
    $text = ereg_replace("\n[[:space:]]+\n", "\n\n", trim($text));
    $text = ereg_replace("[[:space:]]+$", "", $text);

    // !!! Изменено. В базе данных сообщение будет сохраняться с переводами строки, а <BR> будут вставляться при выводе сообщений.
    // В сообщении все переводы строки меняем на BR
    // $text = ereg_replace("\n", "\n<BR>", $text);

    $text = ereg_replace("\n[[:space:]]+\[TABLE\]", "\n[TABLE]", $text);
    $text = ereg_replace("\[\/TABLE\][[:space:]]+\n", "[/TABLE]\n", $text);

    return $text;
  }

  function removeTags($source) {
    $result = $source;
    // $result = ereg_replace("<[^>]+>", "", $source);
    $result = ereg_replace("<", "&lt;", $result);
    $result = ereg_replace(">", "&gt;", $result);
    return $result;
  }

  function hasText($text) {
      $testStr = ereg_replace("[[:space:] !#'<>&;~%#:\"\(\)\.\,\n\*\?\\\+\^\$\{\}\[\]\|\-]", '', $text);
      if ($testStr) {
        return 1;
      } else {
        return false;
      }
  }

  function isWide($text, $max) {
    if (ereg("[^[:space:]]{" . $max . "}", $text)) {
      return TRUE;
    }
    return FALSE;

  }

  // Переводит переданную строку в верхний регистр (только русские буквы)
  public static function rusUpperCase($str) {
    $str = strtoupper($str);
    $str = ereg_replace("а", "А", $str);
    $str = ereg_replace("б", "Б", $str);
    $str = ereg_replace("в", "В", $str);
    $str = ereg_replace("г", "Г", $str);
    $str = ereg_replace("д", "Д", $str);
    $str = ereg_replace("е", "Е", $str);
    $str = ereg_replace("ё", "Ё", $str);
    $str = ereg_replace("ж", "Ж", $str);
    $str = ereg_replace("з", "З", $str);
    $str = ereg_replace("и", "И", $str);
    $str = ereg_replace("й", "Й", $str);
    $str = ereg_replace("к", "К", $str);
    $str = ereg_replace("л", "Л", $str);
    $str = ereg_replace("м", "М", $str);
    $str = ereg_replace("н", "Н", $str);
    $str = ereg_replace("о", "О", $str);
    $str = ereg_replace("п", "П", $str);
    $str = ereg_replace("р", "Р", $str);
    $str = ereg_replace("с", "С", $str);
    $str = ereg_replace("т", "Т", $str);
    $str = ereg_replace("у", "У", $str);
    $str = ereg_replace("ф", "Ф", $str);
    $str = ereg_replace("х", "Х", $str);
    $str = ereg_replace("ц", "Ц", $str);
    $str = ereg_replace("ч", "Ч", $str);
    $str = ereg_replace("ш", "Ш", $str);
    $str = ereg_replace("щ", "Щ", $str);
    $str = ereg_replace("ъ", "Ъ", $str);
    $str = ereg_replace("ы", "Ы", $str);
    $str = ereg_replace("ь", "Ь", $str);
    $str = ereg_replace("э", "Э", $str);
    $str = ereg_replace("ю", "Ю", $str);
    $str = ereg_replace("я", "Я", $str);
    return $str;
  }

  // Проверяет, входит ли все слова из переданной строки в текст.
  public static function isInclude($text, $str) {
    $text = TextUtils::rusUpperCase($text);
    $str = TextUtils::rusUpperCase($str);
    $wordArray = explode(' ', $str);
    $needPrint = 1;

    reset($wordArray);
    while(list($key, $word) = each($wordArray)) {
      if ($word && !ereg($word, $text)) {
        $needPrint = 0;
      }
    }
    return $needPrint;
  }

  public static function lettersAndDigits($str) {
    return preg_replace("/[ !#'<>&;~%#:\"\(\)\.\,\n\*\?\\\+\^\$\{\}\[\]\|\-]/i", '', $str);
  }

  public static function removeTagsAndSpaces($str) {
    $instance = new TextUtils();
    return $instance->removeTags($instance->removeSpaces($str));
  }

  // Заменят кавычку на &quot;
  public static function quote($str) {
    return ereg_replace("\"", "&quot;", $str);
  }

  public static function replaceCR($in) {
    return ereg_replace("\n", "\n<BR>", $in);
  }

  // Возвращает кусок условия where для поиска по слову
  // $fieldName - имя поля
  // $value - искомое значение
  public static function getSearchWhere($fieldName, $value) {
    $where = array();
    // $maxLength = 0;
    $wordArray = explode(' ', $value);
    while(list($key, $text) = each($wordArray)) {
      if ($text) {
        // $maxLength = max($maxLength, strlen($text));
        $text = ereg_replace('%', '%%', $text);
        $text = "%$text%";
        $where[] = sprintf("%s LIKE '%s'", $fieldName, TextUtils::rusUpperCase(addslashes($text)));
      }
    }
    return $where;
  }

  public static function normalizeStr($str) {
    $str = mb_strtoupper($str);
    // $str = self::rusUpperCase($str);
    $str = self::lettersAndDigits($str);
    return $str;
  }
}

?>