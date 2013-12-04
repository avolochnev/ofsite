<?php

abstract class BookAwareController extends ApplicationController {
  var $current_book;
  var $book_id;

  protected function setup_twig_globals() {
    parent::setup_twig_globals();
    if ($this->current_book) {
      $this->addGlobal('book', $this->current_book);
    }
    if ($this->respond_with != 'js') {
      $this->content->book_updates = BookUtils::updates($this->current_user, $this->possible_user, $this->current_book);
    }
  }

  protected function load_book_as_admin($book_id = 0) {
    $this->load_book($book_id);
    if (!$this->current_book->is_admin) HTTPUtils::forbidden();
  }

  protected function load_book($book_id = 0) {
    if (!$book_id) $book_id = $_GET['book'] + 0;
    if (!$book_id) $book_id = $_POST['book'] + 0;
    $books = $this->possible_user->available_books();
    if (!$books[$book_id]) HTTPUtils::forbidden();
    $this->book_id = $book_id;
    $this->current_book =& $books[$book_id];
    if (!$this->current_book->archived || $this->current_book->archived < date("Y-m-d")) {
      ArchiveUtils::archive($book_id, $this->current_book->alive_term);
    }
  }
}

?>