<?php

class WarnUtils {
  public static function has_warn($msg) {
    return DB::count('gfb_warning', "user_id = $msg->user_id AND message_id = $msg->message_id");
  }

  public static function create($msg, $rule_id, $comment, $user) {
    global $twig;
    $comment = DB::safe($comment);
    $query = "INSERT INTO gfb_warning (user_id, message_id, rule_id, created, created_by, comment)
                   VALUES ($msg->user_id, $msg->message_id, $rule_id, NOW(), $user->user_id, '$comment')";
    if (DB::q($query)) {
      // Если включены сообщения и есть групповой пользователь группы модераторов, то отправляем сообщение от этого пользователя.
      $moderator_id = DB::field('gf_user', 'user_id', "realm_id = 'BOOK_MAIL'") + 0;
      if ($moderator_id) {
        $msg_text = $twig->render('warn/notify.txt', array(
          'msg' => $msg,
          'rule_name' => RuleUtils::name_for($rule_id),
          'host' => $_SERVER['HTTP_HOST'],
          'comment' => $comment));
        PrivateUtils::send($user, $moderator_id, $msg->user_id, 'Предупреждение', $msg_text);
      }
    } else {
      throw new DomainException('cannot create warn');
    }
  }

  public static function find_warns($user_id) {
    $query = "SELECT warning_id, b.user_id, b.message_id, b.rule_id, b.created, b.created_by, b.comment
                FROM gfb_warning AS b WHERE user_id = $user_id ORDER BY b.warning_id DESC;";
    return DB::all($query);
  }

  public static function find($warn_id) {
    $query = "SELECT b.*, nick FROM gfb_warning AS b, gf_user AS u WHERE warning_id = $warn_id AND b.user_id = u.user_id;";
    return DB::obj($query);
  }
}

?>