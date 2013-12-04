<?php

class RulesController extends ApplicationController {
  var $default_object_action = 'edit';
  var $id_param = 'rule_id';

  public function index() {
    $is_admin = $this->hasAccess('BOOK_RULES');
    $this->render('rules/index.html', array(
      'list' => new Paginator(RuleUtils::query($is_admin)),
      'is_admin'=> $is_admin));
  }

  public function edit() {
    $this->checkAccess('BOOK_RULES');
    $rule_id = $_GET[rule_id] + 0;
    $object = new RuleObject($rule_id);
    if ($object->processPost()) {
      $this->addMessage('Правило сохранено.');
      $rule_id = $object->get(rule_id);
    }
    $this->render('rules/edit.html', array(
      'rule_id' => $rule_id,
      'title' => $object->get('name'),
      'object' => $object));
  }
}

?>