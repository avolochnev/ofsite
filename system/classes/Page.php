<?php

class Page {
  var $errors = array();
  var $messages = array();
  var $page_id = 0; // Идентификатор страницы.

  function addError($error) {
    $this->errors[] = $error;
  }

  function addMessage($message) {
    $this->messages[]  = $message;
  }

}

?>