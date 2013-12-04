<?php

class PrivateUtils {
  public static function messages($viewer_id, $user_id, $as_id = 0) {
    if (!$as_id) $as_id = $viewer_id;
    $query = "SELECT m.msg_id, m.from_nick, m.to_nick, m.date, r.read_date, m.subject, m.text
                FROM gfmsg_message AS m, gfmsg_read AS r
               WHERE m.msg_id = r.msg_id AND r.user_id = $viewer_id
                 AND (m.from_id = $user_id AND m.to_id = $as_id OR m.from_id = $as_id AND m.to_id = $user_id)
               ORDER BY m.msg_id;";
    return DB::all($query);
  }

  public static function mark_as_read($messages, $viewer_id) {
    $new_mesages = array();
    foreach ($messages as $msg) {
      if (!$msg->read_date) $new_messages[] = $msg->msg_id;
    }
    if (!count($new_messages)) return;
    $ids = join(',', $new_messages);
    DB::q("UPDATE gfmsg_read set read_date = NOW() where msg_id IN ($ids) AND user_id = $viewer_id");
  }

  public static function has_messages($one_id, $two_id) {
    return DB::count('gfmsg_message', "from_id = $one_id AND to_id = $two_id OR from_id = $two_id AND to_id = $one_id");
  }

  public static function discussions($viewer_id) {
    $query = "SELECT m.from_id, m.from_nick, m.to_id, m.to_nick, IF(r.read_date IS NULL, 'Y', 'N') as is_new
                FROM gfmsg_message AS m, gfmsg_read AS r
               WHERE m.msg_id = r.msg_id AND r.user_id = $viewer_id
               ORDER BY m.msg_id DESC";
    $msgs = DB::all($query);
    $aliases = self::aliases($viewer_id);
    $result = array();
    foreach ($msgs as $msg) {
      $uid = self::msgUID($msg, $aliases, $viewer_id);
      if ($result[$uid['key']]) {
        $result[$uid['key']]['total'] += 1;
        if ($uid['new']) {
          $result[$uid['key']]['new'] += 1;
        }
      } else {
        $result[$uid['key']] = $uid;
      }
    }
    return $result;
  }

  public static function msgUID($msg, $aliases, $viewer_id) {
    $from_first = $msg->from_id < $msg->to_id;
    $result = array(
      'total' => 1,
      'new' => $msg->is_new == 'Y' ? 1 : 0,
      'key' => $from_first ? "$msg->from_id.$msg->to_id" : "$msg->to_id.$msg->from_id");
    if ($msg->from_id == $viewer_id) {
      $result['adressee_id'] = $msg->to_id;
      $result['adressee_nick'] = $msg->to_nick;
    } else if ($msg->to_id == $viewer_id) {
      $result['adressee_id'] = $msg->from_id;
      $result['adressee_nick'] = $msg->from_nick;
    } else if ($aliases[$msg->from_id]) {
      $result['as_id'] = $msg->from_id;
      $result['as_nick'] = $msg->from_nick;
      $result['adressee_id'] = $msg->to_id;
      $result['adressee_nick'] = $msg->to_nick;
    } else if ($aliases[$msg->to_id]) {
      $result['as_id'] = $msg->to_id;
      $result['as_nick'] = $msg->to_nick;
      $result['adressee_id'] = $msg->from_id;
      $result['adressee_nick'] = $msg->from_nick;
    }
    return $result;
  }

  public static function aliases($viewer_id) {
    $r = DB::i("SELECT u.user_id, nick FROM gf_user u, gf_access a WHERE a.user_id = $viewer_id AND a.realm_id = u.realm_id");
    $result = array();
    foreach ($r as $ro) $result[$ro->user_id] = $ro->nick;
    return $result;
  }

  public static function send($user, $from_id, $to_id, $subject, $message) {
    $from_nick = DB::field('gf_user', 'nick', "user_id = $from_id");
    $to_nick = DB::field('gf_user', 'nick', "user_id = " . $to_id);
    $is_from_group = DB::field('gf_user', 'realm_id', "user_id = $from_id");;

    DB::q(sprintf("INSERT INTO gfmsg_message (from_id, from_nick, to_id, to_nick, date, subject, text)
                   VALUES ($from_id, '$from_nick', $to_id, '$to_nick', NOW(), '%s', '%s');",
                   DB::safe($subject), DB::safe($message)));

    $msg_id = DB::last_id();

    if (!$msg_id) return 0;

    // Вставляем нотификацию отправителя.
    // Если отправитель обычный, то нотифицируем только его.
    // Если отправитель групповой - то нотифицируем всех, кото входит в группу.
    if ($is_from_group) {
      $realm_id = DB::field('gf_user', 'realm_id', "user_id = $from_id");
      DB::q("INSERT INTO gfmsg_read (msg_id, user_id, is_from, read_date) SELECT $msg_id as msg_id, user_id, 'Y' as is_from, NOW() as read_date FROM gf_access  WHERE realm_id = '$realm_id' AND user_id <> $to_id;");
    } else {
      DB::q("INSERT INTO gfmsg_read (msg_id, user_id, is_from, read_date) VALUES ($msg_id, $from_id, 'Y', NOW());");
    }

    // Вставляем нотификацию получателя.
    // Если получатель обычный - то просто уведомляем его.
    // Если получатель групповой - то уведомляем всех участников группы.
    $realm_id = DB::field('gf_user', 'realm_id', "user_id = $to_id");
    if ($realm_id) {
      DB::q("INSERT INTO gfmsg_read (msg_id, user_id, is_from) SELECT $msg_id AS msg_id, user_id, 'N' as is_from FROM gf_access WHERE realm_id = '$realm_id' AND user_id <> $user->user_id;");
    } else {
      DB::q("INSERT INTO gfmsg_read (msg_id, user_id, is_from) VALUES ($msg_id, $to_id, 'N');");
    }

    return $msg_id;
  }

  public static function cleanup() {
    $ids = array();
    $r = DB::i("SELECT msg_id FROM gfmsg_message WHERE DATE_ADD(date, INTERVAL 1 MONTH) < NOW()");
    foreach ($r as $ro) {
      $ids[] = $ro->msg_id;
    }
    foreach($ids as $id) {
      DB::q("DELETE FROM gfmsg_message WHERE msg_id = $id");
      DB::q("DELETE FROM gfmsg_read WHERE msg_id = $id");
    }
  }

  public static function new_count($viewer_id) {
    return DB::count('gfmsg_read', "user_id = $viewer_id AND read_date IS NULL");
  }

  public static function parseAndVerifyMessage($text) {
    $message = TextUtils::removeTagsAndSpaces($text);
    $textUtils = new TextUtils();

    $antimat = Antimat::instance();

    if ($antimat->isUncensorship($message)) {
      throw new DomainException('Cообщение содержит слова, запрещенные на даннм сайте');
    }

    if (!$textUtils->hasText($message)) {
      throw new DomainException('Не заполнен текст сообщения.');
    }

    // Verify message parsing, throw DomainException if not valid
    MessageUtils::parse($message);

    if (!$message) throw new DomainException('Текст сообщения не заполнен');
    return $message;
  }

  public static function detect_sender($user, $user_id, $as_id = 0) {
    if (!$user) HTTPUtils::redirect();
    // Определяем отправителя.
    // Проверяем возможность отправки сообщения от этого отправителя.
    if ($as_id) {
      if ($as_id != $user->user_id) {
        $aliases = PrivateUtils::aliases($user->user_id);
        if (!$aliases[$as_id]) HTTPUtils::redirect();
      }
      $sender_id = $as_id;
    } else {
      $sender_id = $user->user_id;
    }

    if (!$user->hasAccess('GFMSG_CREATE')) {
      $is_open = (DB::field('gf_user', 'open_mailbox', "user_id = $user_id") == 'Y');
      if (!$is_open) {
        if (!self::has_messages($user_id, $sender_id))
         throw new DomainException('Вы не можете послать сообщение указанному получателю.');
      }
    }
    return $sender_id;
  }
}

?>