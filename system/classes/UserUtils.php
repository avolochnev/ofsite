<?php

class UserUtils {
  public static function find($user_id) {
    return DB::one('gf_user', "user_id = $user_id");
  }

  public static function nick_change_history($user_id) {
    $query = "SELECT c.date, c.prev, c.next, pu.nick AS prev_nick, nu.nick AS next_nick
                FROM gf_nick_change AS c, gf_user AS pu, gf_user AS nu
               WHERE c.prev = pu.user_id AND c.next = nu.user_id AND (c.prev = $user_id OR c.next = $user_id);";
    return DB::all($query);
  }

  public static function messageStatistics($user_id) {
    $query = "select count(1) as total, count(IF(deleted_by = 0 OR deleted_by IS NULL, NULL, 1)) as deleted FROM gfb_message WHERE userid = $user_id";
    $msg_stat = DB::obj($query);
    $stat = array();
    $stat['total'] = $msg_stat->total;
    $stat['deleted'] = $msg_stat->deleted;
    $stat['black_count'] = DB::count('gfb_black_list', 'black_id = ' . $user_id);
    $stat['red_count'] = DB::count('gfb_priority', 'priority_id = ' . $user_id);
    return $stat;
  }

  public static function isPriority($current_user_id, $user_id) {
    return DB::obj("SELECT * FROM gfb_priority WHERE user_id = $current_user_id AND priority_id = $user_id;");
  }

  public static function isBlack($current_user_id, $user_id) {
    return DB::obj("SELECT * FROM gfb_black_list WHERE user_id = $current_user_id AND black_id = $user_id;");
  }

  public static function realmsFor($user_id) {
    $realms = array();
    $result = DB::i("SELECT * FROM gf_realm;");
    foreach ($result as $ro) $realms[$ro->realm_id] = 0;
    $result = DB::i("SELECT realm_id FROM gf_access WHERE user_id = $user_id;");
    foreach ($result as $ro) $realms[$ro->realm_id] = 1;
    return $realms;
  }

  public static function findByNick($nick) {
    return DB::obj(sprintf("SELECT user_id, password, nick FROM gf_user WHERE nickid='%s';", self::getNickID($nick)));
  }

  public static function hasAccess($user, $realm) {
    if (!$user) return FALSE;
    if (is_array($realm)) {
      foreach ($realm as $r) {
        if ($user->hasAccess($r)) return TRUE;
      }
      return FALSE;
    } else {
      return $user->hasAccess($realm);
    }
  }

  public static function redList($user_id) {
    return self::relationships('red', $user_id);
  }

  public static function blackList($user_id) {
    return self::relationships('black', $user_id);
  }

  public static function relationships($list = 'red', $user_id) {
    if (!$user_id) return array();
    if ($list == 'red') {
      $table = 'gfb_priority';
      $key = 'priority_id';
    } else { // black
      $table = 'gfb_black_list';
      $key = 'black_id';
    }
    $query = "SELECT u.user_id, u.nick, u.active FROM gf_user AS u, $table AS b WHERE b.user_id = $user_id AND b.$key = u.user_id";
    return DB::all($query);
  }

  public static function userLink($to, $blank = FALSE) {
    global $controller;
    $nick = $to->nick;
    $user_id = $to->user_id;
    if (!$user_id) $user_id = $to->userid;
    return $controller->possible_user->link_to_user($nick, $user_id, $blank);
  }

  public static function userLinkById($user_id, $blank = FALSE) {
    $u = self::find($user_id);
    return self::userLink($u, $blank);
  }

  public static function nick_by_id($user_id) {
    return $user_id ? DB::field('gf_user', 'nick', "user_id = $user_id") : '';
  }

  // Из имени пользователя генерирует его хэш-код.
  public static function getNickID($nick_source) {
    $letter_map = array("Б" => 193, "Г" => 195, "Ё" => 168, "Ж" => 198, "И" => 200, "Й" => 201, "Л" => 203, "П" => 207, "У" => 211, "Ф" => 212, "Ц" => 214, "Ш" => 216, "Щ" => 217, "Ъ" => 218, "Ы" => 219, "Ь" => 220, "Э" => 221, "Ю" => 222, "Я" => 223);


    # Убираем все символы, которые на 100% левые
    $nick = TextUtils::normalizeStr($nick_source);

    $result = '';

    for ($i = 0; $i < mb_strlen($nick); $i++) {
      $letter = mb_substr($nick, $i, 1);
      if (ereg("[A-Z1234567890]", $letter)) {
        $result .= $letter;
      // Заменяем русские буквы на похожие латинские
      } else if (ereg("А", $letter)) {
        $result .= 'A';
      } else if (ereg("В", $letter)) {
        $result .= 'B';
      } else if (ereg("Д", $letter)) {
        $result .= 'D';
      } else if (ereg("Е", $letter)) {
        $result .= 'E';
      } else if (ereg("З", $letter)) {
        $result .= '3'; // number 3
      } else if (ereg("К", $letter)) {
        $result .= 'K';
      } else if (ereg("М", $letter)) {
        $result .= 'M';
      } else if (ereg("Н", $letter)) {
        $result .= 'H';
      } else if (ereg("О", $letter)) {
        $result .= 'O';
      } else if (ereg("Р", $letter)) {
        $result .= 'P';
      } else if (ereg("С", $letter)) {
        $result .= 'C';
      } else if (ereg("Т", $letter)) {
        $result .= 'T';
      } else if (ereg("Х", $letter)) {
        $result .= 'X';
      } else if (ereg("Ч", $letter)) {
        $result .= '4';
      } else if (ereg("[БГЁЖИЙЛПУФЦШЩЪЫЬЭЮЯ]", $letter)) {
        $result .= sprintf("_%X", $letter_map[$letter]);
      }
    }

    return $result;
  }

  public static function update_priority($user, $user_id, $priority, $black) {
    if ($user) {
      DB::q("DELETE FROM gfb_priority WHERE user_id = $user->user_id AND priority_id = $user_id");
      if ($priority) {
        DB::q("INSERT INTO gfb_priority (user_id, priority_id) VALUES ($user->user_id, $user_id)");
      }
      DB::q("DELETE FROM gfb_black_list WHERE user_id = $user->user_id AND black_id = $user_id");
      if ($black) {
        DB::q("INSERT INTO gfb_black_list (user_id, black_id) VALUES ($user->user_id, $user_id)");
      }
    }
  }

  public static function create_group_usser($nick, $group) {
    $nick_id = UserUtils::getNickID($nick);
    return DB::q("INSERT INTO gf_user (nick,    nickid,     password, email,    secret,   realm_id)
                               VALUES ('$nick', '$nick_id', 'hidden', 'hidden', 'hidden', '$group')");
  }

  public static function update_password($user, $old, $password) {
    if (!$old) throw new DomainException('Ошибка: не указан старый пароль.');
    $ro = DB::obj("SELECT password FROM gf_user WHERE user_id = $user->user_id;");
    if (!$ro) throw new DomainException("Невозможно изменить пароль.");
    if (Access::crypt_password($old) != $ro->password) throw new DomainException('Старый пароль указан неверно.');
    self::set_password($user->user_id, $password);
  }

  public static function set_password($user_id, $password) {
    $password = Access::crypt_password($password);
    $query = sprintf("UPDATE gf_user SET password='$password' WHERE user_id=%d;", $user->user_id);
    DB::q($query);
  }

  public static function find_by_nick($nick, $only_open_mailbox = false) {
    $query = "SELECT user_id, nick FROM gf_user";
    $where = sprintf("nickid like '%%%s%%'", addslashes(self::getNickID($nick)));
    if ($only_open_mailbox) {
      if ($where) {
        $where .= ' AND ';
      }
      $where .= "open_mailbox = 'Y'";
    }
    if ($where) {
      $query .= " WHERE $where";
    }
    $query .= " ORDER BY user_id LIMIT 10";
    return DB::all($query);
  }
}

?>