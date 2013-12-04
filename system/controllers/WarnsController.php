<?php

class WarnsController extends BookAwareController {
  var $js_actions = array('create', 'loadForm');
  var $id_param = 'id';
  var $rename_action = array('new' => 'loadForm');

  public function loadForm() {
    $message_id = $_POST['message_id'] + 0;
    $msg = MessageUtils::find($message_id);
    $this->load_book_as_admin($msg->book_id);
    twig('warn/form.html', array(
      'msg' => $msg,
      'rules' => RuleUtils::where("need_warn = 'Y'")
      ));
    exit;
  }

  public function create() {
    $message_id = $_POST['message_id'] + 0;
    $msg = MessageUtils::find($message_id);
    $user = $this->current_user;
    $del_message = $_POST['del_message'] + 0;
    $del_discussion = $_POST['del_discussion'] + 0;
    $this->load_book_as_admin($msg->book_id);
    $rule_id = $_POST[rule_id] + 0;
    if (!$rule_id) throw new DomainException('Не указано правило.');

    if (WarnUtils::has_warn($msg)) throw new DomainException('Пользователь уже предупрежден за данное сообщение.');

    if ($del_discussion) {
      BookUtils::deleteDiscussion($msg->discussion_id, $user);
      $notify = 'Дискуссия удалена; ';
    } else if ($del_message) {
      BookUtils::deleteMessage($msg->message_id, $user);
      $notify = 'Сообщение удалено; ';
    }

    WarnUtils::create($msg, $rule_id, $_POST['comment'], $user);
    $notify .= 'Предупреждение отправлено.';
    echo("$('#admin_message_placeholder_$message_id').empty().html('<div class=\"notify\">$notify</div>');");
    if ($del_message) {
      twig('message/toggle.deleted.js', array('message_id' => $message_id));
    }
  }

  public function index() {
    $this->only_for_admin();
    $query = "SELECT warning_id, b.user_id, b.message_id, b.rule_id, b.created, b.created_by, b.comment
                FROM gfb_warning AS b ORDER BY b.warning_id DESC;";
    $this->render('warn/list.html', array('list' => new Paginator($query)));
  }

  public function show() {
    $this->only_for_admin();

    // Загружаем данные блокировки.
    $ro =  WarnUtils::find($this->getId());
    if (!$ro) HTTPUtils::notFound();
    $message = MessageUtils::find($ro->message_id);
    $this->render('warn/show.html', array(
      'msg' => $ro,
      'rule' => RuleUtils::name_for($ro->rule_id),
      'text' => $message->text));
  }

  private function only_for_admin() {
    $this->checkAccess(array('BOOK_ADMIN', 'S_ADMIN'));
  }
}

?>