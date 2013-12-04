<?php

class DateUtils {
  public static $monthListIP;
  static $monthListRP;
  static $monthList;
  static $weekDayList;

  // Выделяет день из даты в формате YYYY-MM-DD
  public static function getDay($date) {
    list ($year, $month, $day) = explode("-", $date);
    return $day + 0;
  }

  // Выделяет месяц из даты в формате YYYY-MM-DD
  public static function getMonth($date) {
    list ($year, $month, $day) = explode("-", $date);
    return $month + 0;
  }

  // Выделяет год из даты в формате YYYY-MM-DD
  public static function getYear($date) {
    list ($year, $month, $day) = explode("-", $date);
    return $year + 0;
  }

  // Собирает дату из отдельных кусков.
  public static function concatDate($year, $month, $day) {
    return sprintf("%04d-%02d-%02d", $year, $month, $day);
  }

  public static function getMonthName($month) {
    return self::$monthListIP[$month];
  }

  // Переводит дату из формата YYYY-MM-DD в формат DD.MM.YYYY
  public static function parseFullDate($date) {
    list ($year, $month, $day) = explode("-", $date);
    $year += 0;
    $month += 0;
    $day += 0;
    if ($year) {
      if ($month > 0) {
        if ($day) {
          return sprintf("%d %s %04d года", $day, self::$monthListRP[$month - 1], $year);
        } else {
          return sprintf("%s %04d года", self::$monthListIP[$month], $year);
        }
      } else {
        return "$year год";
      }
    } else {
      if ($month > 0) {
        if ($day) {
          return sprintf("%d %s", $day, self::$monthListRP[$month - 1]);
        } else {
          return self::$monthListIP[$month];
        }
      } else {
        return "";
      }
    }
  }

  public static function parseDate($date) {
    if (ereg("^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}:[0-9]{2}:[0-9]{2})$", $date, $arr)) {
      return sprintf("%s, %d %s %s %s", self::$weekDayList[self::dayOfWeek($date)], $arr[3], self::$monthList[$arr[2] - 1], $arr[1], $arr[4]);
    } else {
      return '';
    }
  }

  // Возвращает количество лет, прошедших с указанной даты (ГГГГ-ММ-ДД)
  public static function yearsPassed($begin) {
    list ($beginYear, $beginMonth, $beginDay) = explode("-", $begin);
    list ($endYear, $endMonth, $endDay) = explode("-", date("Y-m-d"));

    // сначала считаем количество лет, которые прошли с 1 января начального года, до 1 января конечного года.
    $result = $endYear - $beginYear;

    // Если в начальном году прошло месяцев меньше, чем в конечном, то тогда прошло на один год меньше.
    // Если в прошло одинаково месяцев, то сравниваем дни по тому же принципу.
    if ($beginMonth > $endMonth) {
      $result--;
    } else if (($beginMonth == $endMonth) && ($beginDay > $endDay)) {
      $result--;
    }
    return $result;
  }

  // Возвращает количество лет, прошедших с указанной даты, с добавлением подписи (20 ЛЕТ, 21 ГОД и т.д.)
  public static function yearsPassedCaption($begin) {
    $age = self::yearsPassed($begin);
    if ((($age % 100) > 10) && (($age % 100) < 20)) {
      return $age . ' лет';
    } else if (($age % 10) == 1) {
      return $age . ' год';
    } else if ((($age % 10) == 2) || (($age % 10) == 3) || (($age % 10) == 4)) {
      return $age . ' года';
    }
    return $age . ' лет';
  }

  public static function dayOfWeek($date) {
    // Отрубаем время, если оно есть.
    list ($date) = explode(" ", $date);
    list ($year, $month, $day) = explode("-", $date);
    if($month > 2) {
      $month -= 2;
    } else  {
        $month += 10;
        $year--;
    }

    $day = ( floor((13 * $month - 1) / 5) +
            $day + ($year % 100) +
            floor(($year % 100) / 4) +
            floor(($year / 100) / 4) - 2 *
            floor($year / 100) + 77);

    $weekday_number = (($day - 7 * floor($day / 7)));

    return $weekday_number;
  }

  public static function isNewYear() {
    $date = date("m-d");
    return $date > "12-19" || $date < "01-14";
  }
}

DateUtils::$monthListIP = array(
  1 => "Январь",
  2 => "Февраль",
  3 => "Март",
  4 => "Апрель",
  5 => "Май",
  6 => "Июнь",
  7 => "Июль",
  8 => "Август",
  9 => "Сентябрь",
  10 => "Октябрь",
  11 => "Ноябрь",
  12 => "Декабрь");
DateUtils::$monthListRP = array("Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря");
DateUtils::$monthList = array("января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря");
DateUtils::$weekDayList = array('Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота');


?>