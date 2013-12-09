<?php

class User {
  var $user_id;
  var $realms = -1;
  var $data = -1;
  var $till = array();
  var $priority = -1;
  var $preferences = -1;
  var $black_list = -1;
  var $available_books = -1;
  var $is_logged;

  public function User($user_id = 0, $is_logged = FALSE) {
    $this->user_id = $user_id;
    $this->is_logged = $is_logged;
  }

  public function hasAccess($realm) {
    if (!$this->user_id || !$this->is_logged) return false;
    $this->getRealms();
    return $this->realms[$realm] == 1;
  }

  public function has_restrict($realm) {
    if (!$this->user_id || !$this->is_logged) return false;
    $this->getRealms();
    return $this->realms[$realm] == 1;
  }

  public function &getRealms() {
    if ($this->realms == -1) {
      $this->realms = array();
      if ($this->user_id) {
        $result = DB::i("SELECT realm_id, till FROM gf_access WHERE user_id = " . $this->user_id);
        foreach ($result as $ro) {
          $this->realms[$ro->realm_id] = 1;
          if ($ro->till) {
            $this->till[$ro->realm_id] = $ro->till;
          }
        }
      }
    }
    return $this->realms;
  }

  public function isActive() {
    if (!$this->user_id) {
      return FALSE;
    } else {
      $this->checkData();
      return $this->data->active == 'Y';
    }
  }

  public function checkData() {
    if ($this->user_id && $this->data == -1) {
      $r = DB::q("SELECT active, nick FROM gf_user WHERE user_id = " . $this->user_id);
      $this->data = mysql_fetch_object($r);
    }
  }

  public function getNick() {
    if (!$this->user_id) return '';
    $this->checkData();
    return $this->data->nick;
  }

  public function is_priority($user_id) {
    if ($this->priority == -1) {
      $this->priority = array();
      if ($this->user_id) {
        $query = sprintf("SELECT priority_id FROM gfb_priority WHERE user_id = %d;", $this->user_id);
        foreach (DB::i($query) as $ro) {
          $this->priority[$ro->priority_id] = 1;
        }
      }
    }
    return $this->priority[$user_id];
  }

  public function preferences() {
    if ($this->preferences == -1) {
      $this->preferences = new Preferences($this);
    }
    return $this->preferences;
  }

  public function link_to_user($nick, $id, $blank = FALSE) {
    if ($this->is_priority($id)) {
      $preferences = $this->preferences();
      $nickStyle = 'nick' . $preferences->highlight_nick;
    } else {
      $nickStyle = 'message';
    }
    return '<A class="' . $nickStyle . '" href="users.phtml?id=' . $id . '" ' . ($blank ? 'target="_blank"' : '') . '><b>' . $nick . '</b></A>';
  }

  public function black_list() {
    if ($this->black_list != -1) return $this->black_list;
    $this->black_list = '';
    if ($this->user_id) {
      $query = "SELECT black_id FROM gfb_black_list WHERE user_id = $this->user_id;";
      foreach (DB::i($query) as $ro) {
        $this->black_list .= ' AND m.userid <> ' . $ro->black_id . ' ';
      }
    }
    return $this->black_list;
  }

  public function available_books() {
    if ($this->available_books == -1) $this->load_available_books();
    return $this->available_books;
  }

  private function load_available_books() {
    $this->available_books = array();
    $query = "SELECT * FROM gfb_book ORDER BY priority DESC, book_id;";
    foreach (DB::i($query) as $ro) {
      if (!$ro->access_rights || $this->hasAccess($ro->access_rights)) {
        $this->available_books[$ro->book_id] = new Book($ro, $this);
      }
    }
  }

  public function traceable_books() {
    $preference = $this->preferences();
    $books = $this->available_books();
    $traceBooks = array();
    foreach ($this->available_books() as $book) $traceBooks[$book->book_id] = $book->book_id;
    foreach (split(',', $preference->dont_trace_books) as $id) unset($traceBooks[$id]);
    return $traceBooks;
  }
}

?>