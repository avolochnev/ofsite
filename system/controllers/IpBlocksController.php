<?php

class IpBlocksController extends ApplicationController {
  var $id_param = 'ip_block_id';
  var $default_object_action = 'edit';

  protected function before() {
    $this->checkAccess(array('BOOK_ADMIN', 'S_ADMIN'));
  }

  public function index() {
    $paginator = new Paginator(
      "SELECT ip_block_id, ip, r.name
         FROM gfb_ip_block AS b, gfb_rule AS r
        WHERE b.rule_id = r.rule_id
        ORDER BY ip_block_id");
    $this->render('ip_blocks/index.html', array('list' => $paginator));
  }

  public function edit() {
    $ip_block_id = $_GET[ip_block_id] + 0;
    $object = new IPBlockObject($ip_block_id);
    if ($object->processPost()) {
      $this->addMessage('Изменения сохранены.');
      $ip_block_id = $object->get(book_id);
    }
    $this->render('ip_blocks/edit.html', array('object' => $object, 'ip_block_id' => $ip_block_id));
  }

  public function destroy() {
    $id = $_POST['id'];
    DB::q("DELETE FROM gfb_ip_block WHERE ip_block_id = $id;");
    $this->addMessage('Надеюсь, блокировка была удалена.');
    $this->index();
  }
}

?>