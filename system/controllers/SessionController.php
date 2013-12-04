<?php

class SessionController extends ApplicationController {
  var $default_action = '_new';

  public function create() {
    try {
      $nick = $_POST["nick"];
      $ro = UserUtils::findbyNick($nick);
      if (!$ro) throw new DomainException("Пользователь с ником $nick не найден.");
      Access::check_password($_POST[password], $ro->password);
      Access::delete_old_blocks($ro->user_id);
      Access::check_user_change($ro->user_id, Access::prev_user_id());
      if (!Access::create_session($ro->user_id)) throw new DomainException("Не удалось создать сессию.");
      HTTPUtils::redirect($_POST['redirect']);
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
      $this->_new();
    }
  }

  public function _new() {
    $this->render('users/enter.html', array('redirect' => $_POST['redirect']));
  }

  public function destroy() {
    Access::clear_session($this->current_user);
    HTTPUtils::redirect("/");
  }
}

?>