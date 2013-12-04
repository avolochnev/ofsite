<?php

class ArchiveUtils {
  public static function last_date($book_id) {
    $where = sprintf("is_archived = 'Y' AND deleted_by = 0 AND book_id = %d ORDER BY date DESC LIMIT 1;", $book_id);
    return DB::field('gfb_discussion', 'date', $where);
  }

  public static function settings($book_id, $possible_user, $month, $year) {
    $lastDate = self::last_date($book_id);

    if (!$lastDate) $lastDate = date('Y-m-d');

    $curMonth = DateUtils::getMonth($lastDate);
    $curYear = DateUtils::getYear($lastDate);

    if (!$month) {
      $month = DateUtils::getMonth($lastDate);
      $year = DateUtils::getYear($lastDate);
    }

    $archiveSettings = array(
      'curYear' => $curYear,
      'curMonth' => $curMonth,
      'year' => $year,
      'month' => $month,
      'book_id' => $book_id,
      'startYear' => 2003);

    // Поучаем первый день нужного месяца и первый день следующего:
    $currentFirst = DateUtils::concatDate($year, $month, 1);
    $nextFirst = DateUtils::concatDate(($month == 12 ? $year + 1 : $year), $month % 12 + 1, 1);

    // Формируем запрос для выборки списка дискуссий.
    $archiveSettings['query'] = sprintf("
        SELECT d.discussion_id, caption, last_time, first_time, d.date, d.userid, d.voting, u.nick,
            COUNT(DISTINCT m.message_id) as full_count
            FROM gfb_discussion AS d, gfb_message AS m, gf_user AS u
            WHERE book_id = %d AND d.deleted_by = 0 AND d.is_archived = 'Y' AND d.discussion_id = m.discussion_id AND m.deleted_by = 0
              AND d.date >= '$currentFirst' AND d.date < '$nextFirst' AND d.userid = u.user_id %s
            GROUP BY discussion_id ORDER BY first_time;", $book_id, $possible_user->black_list());
    return $archiveSettings;
  }

  function archive($book_id, $period) {
    $period = $period + 0;
    if (!$period) {
      $period = 7;
    }
    $archive_time = time() - $period  * 24 * 60 * 60; // $period дней по 24 часа по 60 минут по 60 секунд.

    // Получаем все дискуссии, которые нам нужны.
    $query = "SELECT discussion_id FROM gfb_discussion
        WHERE deleted_by = 0 AND is_archived = 'N' AND last_time < $archive_time AND dont_archive = 'N' AND book_id = $book_id;";
    $result = DB::i($query);

    // А затем архивируем и сами дискуссию.
    $query = "UPDATE gfb_discussion SET is_archived = 'Y'
    WHERE deleted_by = 0 AND is_archived = 'N' AND last_time < $archive_time
    AND dont_archive = 'N' AND book_id = $book_id;";
    DB::q($query);

    // Физически удаляем ссылки пользователей на зархивированные дискуссии.
    foreach ($result as $ro) {
      UserRead::clear($ro->discussion_id);
    }

    // Ставим метку об архивации книги.
    DB::q("UPDATE gfb_book SET archived = '" . date("Y-m-d") . "' WHERE book_id = $book_id;");
  }
}

?>