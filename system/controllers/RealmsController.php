<?php

class RealmsController extends ApplicationController {
  public function create() {
    $this->checkAccess('S_ADMIN');
    RealmUtils::create($_POST['realm_id'], $_POST['description'], $_POST['is_restrict']);
    $this->addMessage('Область доступа добавлена');
    $this->index();
  }

  public function index() {
    $this->checkAccess('S_ADMIN');
    $this->render('realms/index.html', array('realms' => RealmUtils::all()));
  }
}

?>