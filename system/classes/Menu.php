<?php

class Menu {
  var $title;
  var $url;
  var $id;
  var $items = array();
  var $newWindow;
  var $selected;

  function Menu($title = '', $url = '', $id = '', $newWindow = FALSE, $selected = FALSE) {
    $this->title = $title;
    $this->url = $url;
    $this->id = $id;
    $this->newWindow = $newWindow;
    $this->selected = $selected;
  }
}

?>