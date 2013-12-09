<?php

abstract class ApplicationController {
  var $content;
  var $current_user;
  var $possible_user; // current user of user logged last time if no active session.
  var $default_action = 'index';
  var $default_object_action = 'show';
  var $id_param = null;
  var $js_actions = null;
  var $rename_action = null;
  var $action;
  var $respond_with;
  var $time;
  var $layout;

  public function dispatch() {
    $this->layout = $this->detect_layout();
    $this->action = $this->detect_action();
    $this->respond_with = $this->detect_response($this->action);
    try {
      $this->content = new Page();
      $this->setup_user();
      if ($respond_with == 'js') {
        $this->js_header();
      } else {
        $this->http_header();
      }
      $this->time = time();
      $this->before();
      $action = $this->action;
      $this->$action();
    } catch (DomainException $e) {
      if ($this->respond_with == 'js') {
        $this->ajax_error($e->getMessage());
      } else { // html
        echo($e->getMessage());
      }
    }
  }

  /**
   * Called before any action.
   * may be overrided in controllers for commpon processing.
   */
  protected function before() {}

  protected function detect_action() {
    $action = $this->postOrGet('action');
    if (!$action) {
      if ($this->id_param && $this->postOrGet($this->id_param)) {
        $action = $this->default_object_action;
      }
    }
    if (!$action) $action = $this->default_action;
    if ($this->rename_action && isset($this->rename_action[$action])) {
      $action = $this->rename_action[$action];
    }
    return $action;
  }

  protected function detect_response($action) {
    if ($this->js_actions && in_array($action, $this->js_actions)) return 'js';
    return 'html';
  }

  protected function detect_layout() {
    $l = $_GET['layout'];
    if ($l) {
      unset($_GET['layout']);
    } else {
      $l = 'default';
    }
    return $l;
  }

  protected function getId() {
    return $this->postOrGet('id') + 0;
  }

  protected function postOrGet($field) {
    $value = $_POST[$field];
    if (!$value) $value = $_GET[$field];
    return $value;
  }

  protected function addMessage($msg) {
    $this->content->addMessage($msg);
  }

  protected function addError($msg) {
    $this->content->addError($msg);
  }

  protected function render($template, $context = null) {
    $this->setup_twig_globals();
    if (!$context) $context = array();
    twig($template, $context);
  }

  private function layout() {
    $l = $_GET['layout'];
    if (!$l) $l = 'default';
    return "layout/$l.html";
  }

  private function fill_menu() {
    $page = $this->content;
    if (!$page->page_id && defined('DEFAULT_PAGE_ID')) $page->page_id = DEFAULT_PAGE_ID;
    $menus = array();
    $menus['main'] = CMSMenu::main($page->page_id);
    if ($page->page_id) {
      $menus['sub'] = CMSMenu::sub($page->page_id);
      $menus['local'] = CMSMenu::local($page->page_id);
    }
    return $menus;
  }

  private function http_header() {
    header("Pragma: no-cache");
    header("Cache-Control: no-cache, must-revalidate");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Content-Type: text/html; charset=utf-8");
  }

  private function js_header() {
    header("Content-Type: text/javascript; charset=utf-8");
  }

  protected function addGlobal($name, $value) {
    global $twig;
    $twig->addGlobal($name, $value);
  }

  protected function setup_twig_globals() {
    $this->addGlobal('possible_user', $this->possible_user);
    $this->addGlobal('time', $this->time);
    if ($this->respond_with != 'js') {
      $this->content->layout = "layout/$this->layout.html";
      $this->addGlobal('menus', $this->fill_menu());
      $this->addGlobal('content', $this->content);
      $this->addGlobal('new_year', DateUtils::isNewYear());
      $this->addGlobal('content_timestamp', CSS_MARKER);
      $this->addGlobal('available_books', $this->possible_user->available_books());
    }
    if ($this->current_user) {
      $this->current_user->checkData();
      $this->addGlobal('current_user', $this->current_user);
    } else {
      $this->addGlobal('request_uri', $_SERVER['REQUEST_URI']);
    }
  }

  protected function hasAccess($realms) {
    $user = $this->current_user;
    if (!$user) return false;
    if (!is_array($realms)) $realms = array($realms);
    foreach ($realms as $realm) if ($user->hasAccess($realm)) return true;
    return false;
  }

  protected function checkAccess($realms) {
    if (!$this->hasAccess($realms)) HTTPUtils::noAccess();
  }

  protected function ajax_error($msg) {
    $msg = json_encode($msg);
    echo "alert($msg);";
    exit();
  }

  private function setup_user() {
    $this->current_user = Access::current_user();
    if ($this->current_user) {
      $this->possible_user = $this->current_user;
    } else {
      if ($prev_user_id = Access::prev_user_id()) {
        $this->possible_user = new User($prev_user_id);
      } else {
        $this->possible_user = new User(); // no user
      }
    }
  }

  public static function load() {
    $cname = $_GET['controller'];
    unset($_GET['controller']);

    // redirect old-fashitoned requests.
    if ($cname == 'new') $cname = 'messages';
    if ($cname == 'Discussion') $cname = 'discussions';

    // ip_blocks => IpBlocks
    $cname = explode('_', $cname);
    $cname = array_map(ucwords, $cname);
    $cname = implode($cname);
    if (!$cname) $cname = 'Books';
    $cname .= 'Controller';

    if (!class_exists($cname)) $cname = 'BooksController';
    return new $cname;
  }
}

?>