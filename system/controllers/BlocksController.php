<?php

class BlocksController extends BookAwareController {
  var $js_actions = array('create', 'loadForm');
  var $id_param = 'id';
  var $rename_action = array('new' => 'loadForm');

  public function loadForm() {
    $msg = $this->load_message();
    twig('block/form.html', array(
      'message_id' => $msg->message_id,
      'rules' => RuleUtils::where("need_block = 'Y'"),
      'day' => $_POST['till_day'],
      'month' => $_POST['till_month'],
      'year' => $_POST['till_year'],
      'msg' => $msg->text
      ));
    exit; // stop processing without more rendering
  }

  public function create() {
    $msg = $this->load_message();
    $comment = $_POST[comment];
    BlockUtils::validate_and_create($msg, $comment, $this->current_user);
    echo("$('#admin_message_placeholder_$msg->message_id').empty().html('<div class=\"notify\">Блокировка создана</div>');");
    twig('message/toggle.deleted.js', array('message_id' => $msg->message_id));
  }

  public function index() {
    $mode = $_GET['mode'];
    $this->onlyForBlocker();
    $query = BlockUtils::query($mode);
    $this->render('block/list.html', array('list' => new Paginator($query, 10), 'mode' => $mode));
  }

  public function show() {
    $this->onlyForBlocker();
    $ro = BlockUtils::find($this->getId());
    if (!$ro) HTTPUtils::notFound();
    $this->render('block/info.html', array(
      'block' => $ro,
      'rule'  => RuleUtils::name_for($ro->rule_id),
      'msg'   => MessageUtils::find($ro->message_id),
      'year'  => DateUtils::getYear($ro->till),
      'month' => DateUtils::getMonth($ro->till),
      'day'   => DateUtils::getDay($ro->till)
      ));
  }

  public function update() {
    $this->onlyForBlocker();
    BlockUtils::update($this->current_user, $this->getId(), $_POST['till_year'], $_POST['till_month'], $_POST['till_day'], $_POST['comment']);
    $this->addMessage('Блокировка изменена.');
    $this->show();
  }

  public function cancel() {
    $this->onlyForBlocker();
    BlockUtils::cancel($this->current_user, $this->getId(), $_POST['comment']);
    $this->addMessage('Блокировка отменена.');
    $this->show();
  }

  private function load_message() {
    $message_id = $_POST['message_id'] + 0;
    $msg = MessageUtils::find($message_id);
    $this->load_book_as_admin($msg->book_id);
    return $msg;
  }

  private function onlyForBlocker() {
    $this->checkAccess(array('BOOK_ADMIN', 'S_ADMIN'));
  }
}

?>