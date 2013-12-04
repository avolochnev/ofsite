<?php

class Notification {
  var $header;
  var $text;
  var $menu;

  function Notification() {
    $this->menu = new Menu();
  }
}

?>