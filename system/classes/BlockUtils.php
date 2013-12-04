<?php

class BlockUtils {
  /**
   * TRUE if not blocked
   */
  public static function check_ip_block($user_id) {
    $ip = $_SERVER["REMOTE_ADDR"];
    $rule_id = DB::field('gfb_ip_block', 'rule_id', "ip = '$ip'");
    if ($rule_id) {
      DB::q("INSERT INTO gfb_block (user_id, message_id, rule_id, created, till, created_by, comment)
                          VALUES ($user_id, 0, $rule_id, NOW(), NULL, 0, '')");
      self::reset_block($user_id);
      return FALSE;
    }
    return TRUE;

  }

  private static function has_block($msg) {
    return (DB::field('gfb_block', 'count(1)', "user_id = $msg->user_id AND message_id = $msg->message_id") + 0 > 0);
  }

  public static function validate_and_create($msg, $comment, $user) {
    $till_day = $_POST[till_day] + 0;
    $till_month = $_POST[till_month] + 0;
    $till_year = $_POST[till_year] + 0;
    $rule_id = $_POST[rule_id] + 0;
    if (!$rule_id) throw new DomainException('Не указано правило, за которое блокируется пользователь.');

    if ($till_year > 0) {
      if ($till_day == 0) throw new DomainException('Не указан день окончания блокировки');
      if ($till_month == 0) throw new DomainException('Не указан месяц окончания блокировки');
    }
    if (self::has_block($msg)) throw new DomainException('Блокировка данного пользователя за данное сообщение уже произведена.');

    if ($msg->is_answer) {
      BookUtils::deleteMessage($msg->message_id, $user);
    } else {
      BookUtils::deleteDiscussion($msg->discussion_id, $user);
    }
    self::create($msg, $rule_id, $till_year, $till_month, $till_day, $comment, $user);
  }

  public static function create($msg, $rule_id, $till_year, $till_month, $till_day, $comment, $user) {
    global $twig;
    $comment = DB::safe($comment);
    $till = $till_year > 0 ? sprintf("'%s %s'", DateUtils::concatDate($till_year, $till_month, $till_day), date("H:i:s")) : 'NULL';
    $query = "INSERT INTO gfb_block (user_id, message_id, rule_id, created, till, created_by, comment)
                        VALUES ($msg->user_id, $msg->message_id, $rule_id, NOW(), $till, $user->user_id, '$comment')";
    DB::q($query);
    // Если включены сообщения и есть групповой пользователь группы модераторов, то отправляем сообщение от этого пользователя.
    $moderator_id = DB::field('gf_user', 'user_id', "realm_id = 'BOOK_MAIL'") + 0;
    if ($moderator_id) {
      $msg_text = $twig->render('block/notify.txt', array(
        'data' => $msg,
        'rule_name' => RuleUtils::name_for($rule_id),
        'comment' => $comment,
        'till' => ($till == 'NULL' ? 'не установлено' : sprintf("%02d.%02d.%04d", $till_day, $till_month, $till_year)),
        'host' => $_SERVER['HTTP_HOST']));
      PrivateUtils::send($user, $moderator_id, $msg->user_id, 'Блокировка', $msg_text);
    }
    self::reset_block($msg->user_id);
  }

  public static function find_block_for($user) {
    if (!$user) return FALSE;
    return DB::one('gf_access', "user_id = $user->user_id AND realm_id = 'BOOK_BLOCK'");
  }

  public static function find_blocks($user_id) {
    $query = "SELECT block_id, b.user_id, b.message_id, b.rule_id, b.created, b.till, b.created_by, b.comment, IF(b.till < NOW(), 0, 1) as current FROM gfb_block AS b WHERE user_id = $user_id ORDER BY b.block_id DESC;";
    return DB::all($query);
  }

  /*
   * Проверка метки блокировки.
   *
   * Для указанного пользователя на основании информации о блокировках переустанавливается общая метка BOOK_BLOCK
   *
   */
  public static function reset_block($user_id) {
    // 1. Выясняем текущий статус блокировки, исходя из списка блокировок.
    $block = DB::count('gfb_block', "user_id = $user_id AND (till IS NULL OR till > NOW())");
    // 1.1 Если есть блокировка, то выясняем максимальную дату блокировки...
    if ($block) {
      $permanent = DB::count('gfb_block', "user_id = $user_id AND till IS NULL");
      if ($permanent) {
        $till = "NULL";
      } else {
        $till = DB::field('gfb_block', "max(till)", "user_id = $user_id AND till IS NOT NULL");
        $till = "'$till'";
      }
    }
    // 2. Удаляем текущую блокировку если она есть.
    DB::q("DELETE from gf_access WHERE user_id = $user_id AND realm_id = 'BOOK_BLOCK'");
    // 3. Если пользователь заблокирован, то сохраняем текущую блокировку.
    if ($block) {
      DB::q("INSERT INTO gf_access (user_id, realm_id, till) VALUES ($user_id, 'BOOK_BLOCK', $till);");
    }
  }

  public static function find($block_id) {
    $query = "SELECT b.*, nick, IF(till IS NULL, 0, IF(till < NOW(), 1, 0)) as finished
                FROM gfb_block AS b, gf_user AS u
               WHERE block_id = $block_id AND b.user_id = u.user_id;";
    return DB::obj($query);
  }

  public static function cancel($user, $block_id, $comment = '') {
    global $twig;
    $block = self::find($block_id);
    if (!$block) throw new DomainException('Блокировка не найдена или уже закончилась.');
    DB::q("UPDATE gfb_block SET till = NOW() WHERE block_id = $block_id");

    // 3. Если есть комментарий модератора, то обновляем его.
    if ($comment) {
      $comment = DB::safe($comment);
      DB::q("UPDATE gfb_block SET comment = '$comment' WHERE block_id = $block_id");
    }
    self::reset_block($block->user_id);

    // 4. Формируем и посылаем личное сообщение счастливому пользователю.
    // Если включены сообщения и есть групповой пользователь группы модераторов, то отправляем сообщение от этого пользователя.
    $moderator_id = DB::field('gf_user', 'user_id', "realm_id = 'BOOK_MAIL'") + 0;
    if ($moderator_id) {
      $msg_text = $twig->render('block/cancel.notify.txt', array(
        'block' => $block,
        'comment' => $comment));
      PrivateUtils::send($user, $moderator_id, $block->user_id, 'Блокировка', $msg_text);
    }
  }

  public static function update($user, $block_id, $till_year, $till_month, $till_day, $comment) {
    global $twig;
    $block = DB::one('gfb_block', "block_id = $block_id AND (till IS NULL OR till > NOW())");
    if (!$block) throw new DomainException('Блокировка не найдена или уже закончилась.');

    $date = DateUtils::concatDate($till_year, $till_month, $till_day);

    if ($till_year + 0 == 0) {
      DB::q("UPDATE gfb_block SET till = NULL WHERE block_id = $block_id");
      $date = 0;
    } else {
      DB::q("UPDATE gfb_block SET till = '$date' WHERE block_id = $block_id");
    }

    // 3. Если есть комментарий модератора, то обновляем его.
    if ($comment) {
      $comment = DB::safe($comment);
      DB::q("UPDATE gfb_block SET comment = '$comment' WHERE block_id = $block_id");
    }
    BlockUtils::reset_block($block->user_id);

    // Формируем и посылаем личное сообщение счастливому пользователю.
    // Если включены сообщения и есть групповой пользователь группы модераторов, то отправляем сообщение от этого пользователя.
    $moderator_id = DB::field('gf_user', 'user_id', "realm_id = 'BOOK_MAIL'") + 0;
    if ($moderator_id) {
      $msg_text = $twig->render('block/update.notify.txt', array(
        'block' => $block,
        'till' => ($date ? DateUtils::parseFullDate($date) : 'не установлен.'),
        'comment' => $comment));
      PrivateUtils::send($user, $moderator_id, $block->user_id, 'Блокировка', $msg_text);
    }
  }

  public static function query($mode) {
    $query = "SELECT block_id, b.user_id, b.message_id, b.rule_id, b.created, b.till, b.created_by,
                     b.comment, IF(b.till < NOW(), 0, 1) as current
                FROM gfb_block AS b ";
    if ($mode == "active") {
        $query .= " WHERE b.till >= NOW() OR b.till is null ";
    } else if ($mode == "permanent") {
        $query .= " WHERE b.till is null ";
    }

    $query .= "ORDER BY b.block_id DESC;";
    return $query;
  }

}

?>