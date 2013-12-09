<?php

class BooksController extends BookAwareController {
  public function edit() {
    $this->checkAccess('S_ADMIN');
    $book_id = $_GET[book_id] + 0;
    $object = new BookEditObject($book_id);
    if ($object->processPost()) {
      $this->addMessage('Изменения сохранены.');
      $book_id = $object->get(book_id);
    }
    if ($book_id) $this->load_book_as_admin($book_id);
    $this->render('books/edit.html', array('object' => $object));
  }

  public function about() {
    $this->load_book();
    $this->content->discussion_updates = $this->current_book->discussion_updates();
    $this->render('books/info.html');
  }

  public function show() {
    $this->load_book();
    $preferences = $this->possible_user->preferences();
    $default_page = $preferences->default_page + 0;
    $landingPages = BookUtils::landingPages();
    $page = $landingPages[$default_page];
    if (!$page) $page = $landingPages[0];
    $url = $page[1] . '?' . ($page[2] ? $page[2] . '&' : '') . 'book=' . $this->current_book->book_id;
    HTTPUtils::redirect($url);
  }

  public function index() {
    $this->render('books/list.html', array(
      'book_admin' => $this->hasAccess('S_ADMIN')));
  }
}

?>