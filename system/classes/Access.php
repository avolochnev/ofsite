<?php

define("CRYPT_KEY", 'ur');
define("SESSION_COOKIE_NAME", "gf2_session");
define("USERID_COOKIE_NAME", "gf2_user_id_");
define("SESSION_TIME", 60 * 60 * 4); // Время истечения сессии. В данный момент - 4 часа.

class Access {
  public static function current_user() {
    $session = $_COOKIE[SESSION_COOKIE_NAME];

    if (!$session) {
      return null;
    } else { // Идентификатор сессии передан через cookie
      list ($userID, $sessionID) = explode(".", $session);
      return self::check_session($sessionID, $userID);
    }
  }

  // Функция проверки сессии
  // Автоматически удаляет все истекшие сессии.
  // Устанавливает запрашиваемой сессии, если она существует, последнее время обращения в текущее.
  // Возвращает 0, если сессия не найдена, или 1, если найдена.
  // $sessionID - идентификатор сессии.
  // $userID - идентификатор пользователя
  private static function check_session($sessionID, $userID) {
    $ro = DB::one('gf_user', "user_id = $userID", "session, last_enter");

    $time = time();
    if ($sessionID && $ro->session == $sessionID) {
      if ($ro->last_enter < $time - SESSION_TIME) {
        DB::q("UPDATE gf_user set session=NULL WHERE user_id = $userID;");
        return 0;
      } else {
        DB::q("UPDATE gf_user set last_enter=$time WHERE user_id = $userID;");
        return new User($userID, TRUE);
      }
    } else {
      return 0;
    }
  }

  // Проверка пароля, введенного пользователем.
  public static function check_password($candidate, $master) {
    $c = Access::crypt_password($candidate);
    if ($master != $c) throw new DomainException('Неправильный пароль');
  }

  // Шифрует пароль.
  public static function crypt_password($password) {
    // Convert encoding to cp1251 due to backward compatility.
    $pw1251 = mb_convert_encoding(TextUtils::normalizeStr($password), 'Windows-1251');
    $result = crypt($pw1251, CRYPT_KEY);
    $result = ereg_replace("\/", "_", $result);
    return $result;
  }

  public static function clear_session($user) {
    setcookie(SESSION_COOKIE_NAME, '', 0, "/");
    if ($user) DB::q("UPDATE gf_user SET session = NULL WHERE user_id = $user->user_id");
  }

  // Создает сессию, записывает ее в базу данных.
  // Устанавливает cookie с идентификатором сессии.
  // В случае проблем возвращает 0, в случае отсутствия проблем: 1.
  // $userID - идентификатор пользователя, для которого создается сессия.
  public static function create_session($userID) {
    $current = time();
    $sessionID = md5($userID . $current);
    DB::q("UPDATE gf_user SET session='$sessionID', last_enter = $current WHERE user_id = $userID;");
    setcookie(SESSION_COOKIE_NAME, sprintf("%d.%s", $userID, $sessionID), 0, "/");
    setcookie(USERID_COOKIE_NAME . SITE_COOKIE_MARKER, $userID, mktime(0, 0, 0, 1, 1, 2030), "/");
    return 1;
  }

  public static function prev_user_id() {
    return $_COOKIE[USERID_COOKIE_NAME . SITE_COOKIE_MARKER] + 0;
  }

  public static function check_user_change($id, $prevUserID) {
    if (!$prevUserID) return;
    if ($id == $prevUserID) return;

    $hiddenUsers = array();
    $query = "SELECT user_id FROM gf_access WHERE realm_id = 'BOOK_HIDDEN'";
    foreach (DB::i($query) as $ro) $hiddenUsers[$ro->user_id] = 1;

    if ($hiddenUsers[$id] || $hiddenUsers[$prevUserID]) {
      return;
    }

    $query = sprintf("INSERT INTO gf_nick_change (prev, next, date) VALUES ($prevUserID, $id, '%s');", date("Y-m-d H:i:s"));
    DB::q($query);

    // Более того. Если предыдущий пользователь был заблокирован, то блокируем и этого пользователя.
    if (DB::field('gf_user', 'active', "user_id = $prevUserID") == 'N') {
      DB::q("UPDATE gf_user SET active = 'N' WHERE user_id = $id");
    }

    // Также переносим все общие блокировки.
    // Считываем все старые блокировки
    // Считываем все новые блокировки
    // Если среди старых есть такие, которых нет среди новых, то добавляем их.
    $r = DB::i("SELECT a.realm_id, till FROM gf_access AS a, gf_realm AS r WHERE a.user_id = $prevUserID AND a.realm_id = r.realm_id AND r.is_restrict = 'Y'");
    $oldBlocks = array();
    $newBlocksTill = array();
    foreach($r as $ro) {
      $oldBlocks[$ro->realm_id] = 1;
      $newBlocksTill[$ro->realm_id] = $ro->till;
    }
    $r = DB::i("SELECT a.realm_id, till FROM gf_access AS a, gf_realm AS r WHERE a.user_id = $id AND a.realm_id = r.realm_id AND r.is_restrict = 'Y'");
    $newBlocks = array();
    foreach ($r as $ro) {
      $newBlocks[$ro->realm_id] = 1;
    }
    foreach($oldBlocks as $key => $value) {
      if ($newBlocks[$key] != 1) {
        DB::q("INSERT INTO gf_access (realm_id, user_id) VALUES ('$key', $id)");
        if ($newBlocksTill[$key]) {
          $t = $newBlocksTill[$key];
          DB::q("UPDATE gf_access SET till = '$t' WHERE realm_id = '$key' AND user_id = $id");
        }
      }
    }
  }

  // Снимаем блокировки, если настал срок...
  public static function delete_old_blocks($user_id) {
    DB::q("DELETE FROM gf_access WHERE user_id = $user_id AND till < NOW()");
  }

  public static function update_access($user_id, $realms) {
    $till = array();
    $r = DB::i("SELECT realm_id, till FROM gf_access WHERE user_id = $user_id AND till IS NOT NULL");
    foreach($r as $ro) $till[$ro->realm_id] = $ro->till;
    DB::q("DELETE FROM gf_access WHERE user_id=$user_id");

    foreach ($realms as $realm) {
      $query = "INSERT INTO gf_access (user_id, realm_id, till) VALUES ($user_id, '$realm', " . ($till[$realm] ? "'" . $till[$realm] . "'" : 'NULL') . ");";
      DB::q($query);
    }
  }
}

?>