<?php

class PrivateController extends ApplicationController {
  var $id_param = 'user_id';
  var $default_object_action = 'by_user';
  var $default_action = 'discussions';

  public function by_user() {
    $user = $this->current_user;
    $user_id = $this->postOrGet('user_id') + 0;
    $as_id = $this->postOrGet('as_id') + 0;
    $user_to_show = UserUtils::find($user_id);
    if (!$user || !$user_to_show) HTTPUtils::http404();
    $aliases = PrivateUtils::aliases($user->user_id);
    if ($as_id && !$aliases[$as_id]) $as_id = 0;
    $messages = PrivateUtils::messages($user->user_id, $user_to_show->user_id, $as_id);
    $context = array(
      'user' => $user_to_show,
      'messages' => $messages,
      'as_id' => $as_id,
      'as_nick' => ($as_id ? $aliases[$as_id] : null),
      'aliases' => $aliases);
    PrivateUtils::mark_as_read($messages, $user->user_id);
    $this->render('private/by_user.html', $context);
  }

  public function discussions() {
    $user = $this->current_user;
    if (!$user) HTTPUtils::redirect();
    $context = array(
      'discussions' => PrivateUtils::discussions($user->user_id));

    if (defined('MODERATOR_ID') && !$this->hasAccess('GFMSG_BLOCK')) {
      $context['moderator_id'] = MODERATOR_ID;
    }

    $this->render('private/list.html', $context);
  }

  public function create() {
    $user_id = $this->postOrGet('user_id') + 0;
    $as_id = $this->postOrGet('as_id') + 0;
    $text = $_POST['text'];

    if (!$user_id) HTTPUtils::redirect('private.phtml');
    try {
      $message = PrivateUtils::parseAndVerifyMessage($text);
      $from_id = PrivateUtils::detect_sender($this->current_user, $user_id, $as_id);
      $msg_id = PrivateUtils::send($this->current_user, $from_id, $user_id, '', $message);
      PrivateUtils::cleanup(); // delete old messages

      if (!$msg_id) throw new DomainException('Не удалось отправить сообщение.');

      HTTPUtils::redirect("private.phtml?user_id=$user_id" . ($as_id ? "&as_id=$as_id" : '' ));
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
      $this->by_user();
    }
  }
}

?>