<?php

class PagesController extends ApplicationController {
  var $id_param = 'id';

  /**
   * Admin enter form
   */
  public function index() {
    $this->forAdmin();
    $this->render('pages/index.html', array('pages' => PageUtils::top_pages()));
  }

  public function edit() {
    $this->forAdmin();
    $page_id = $_GET[page_id] + 0;
    $parent = $_GET[page_parent] + 0;
    $object = new PageEditObject($page_id);
    if ($parent) $object->set('page_parent', $parent);
    if ($object->processPost()) $this->addMessage('Данные сохранены.');
    $page_id = $object->get(page_id);
    $this->render('pages/edit.html', array(
      'page_id' => $object->get('page_id'),
      'parent_id' => $object->get('page_parent'),
      'object' => $object,
      'pages' => PageUtils::sub_pages($page_id)
      ));
  }

  public function show() {
    $page_data = PageUtils::findById($_GET[id] + 0);
    if (!$page_data) HTTPUtils::notFound();
    if ($page_data->page_url)  HTTPUtils::redirect($page_data->page_url);
    $this->content->page_id = $page_data->page_id;
    $this->render('pages/show.html', array('page_data' => $page_data));
  }

  private function forAdmin() {
    $this->checkAccess('CMS_ADMIN');
  }
}

?>