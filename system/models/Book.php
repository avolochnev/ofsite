<?php

class Book {
  // db field
  var $book_id;
  var $book_name;
  var $about;
  var $description;
  var $priority;
  var $access_rights;
  var $create_discussion;
  var $pseudo_book;
  var $admin_rights;
  var $alive_term;
  var $archived; // date
  var $spec_message;
  // calculated
  var $can_add_message;
  var $can_create_discussion;
  var $is_admin;
  var $can_see_deleted;

  function __construct($ro, $user) {
    $this->book_id           = $ro->book_id;
    $this->book_name         = $ro->book_name;
    $this->about             = $ro->about;
    $this->description       = $ro->description;
    $this->priority          = $ro->priority;
    $this->access_rights     = $ro->access_rights;
    $this->create_discussion = $ro->create_discussion;
    $this->pseudo_book       = $ro->pseudo_book;
    $this->admin_rights      = $ro->admin_rights;
    $this->alive_term        = $ro->alive_term;
    $this->archived          = $ro->archived;
    $this->spec_message      = $ro->spec_message;
    if ($user) $this->setup_access($user); // no additional access if not logged
  }

  private function setup_access($user) {
    $this->can_add_message = ($user->isActive() && !$user->has_restrict('BOOK_BLOCK'));
    $this->can_create_discussion = ($this->can_add_message &&
                                    $this->pseudo_book == 'N' &&
                                    (!$this->create_discussion || $user->hasAccess($this->create_discussion)));

    if (!$this->admin_rights) $this->admin_rights = 'BOOK_ADMIN';
    $this->is_admin = ($this->can_add_message && $user->hasAccess($this->admin_rights));
    $this->can_see_deleted = ($this->is_admin || $user->hasAccess('PRIORITY'));
  }
}

?>