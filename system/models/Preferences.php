<?php

// Настройки пользователя для просмотра гостевой книги.
class Preferences {
  var $user;
  var $user_id = 0;         // Идентификатор пользователя.
  var $form_in_bottom = 0;  // Размещать форму внизу дискуссии
  var $dont_trace_books = ''; // Список книг, которые пользователь не отслеживает (идентификаторы через запятую)
  var $default_page = 1;    // Код страницы по умолчанию в гостевой книге.
  var $highlight_nick = 1;  // Вариант выделения ника.
  var $as_book = 0;         // Работа в режиме книги. Не ставить метку прочтения при открытии дискуссии.
  var $last_discussion = 0; // Последняя просмотренная пользователем дискуссия.
  var $sort_type = 0; // Сортировать дискуссии в обратном порядке.

  function Preferences($user) {
    $this->user = $user;
    $this->user_id = $user->user_id;
    if ($this->user_id) {
      $this->load();
    }
  }

  // Загрузка данных.
  // Отдельного вызова не требует, так как вызывается.
  function load() {
    if (!$this->user_id) {
      return;
    }

    $query = "select * from gfb_preferences where user_id = " . $this->user_id;
    if ($ro = DB::obj($query)) {
      $this->last_discussion = $ro->last_discussion;
      $this->form_in_bottom = DB::yn2bool($ro->form_in_bottom);
      $this->dont_trace_books = $ro->dont_trace_books;
      $this->default_page = $ro->default_page;
      $this->highlight_nick = $ro->highlight_nick;
      $this->as_book = DB::yn2bool($ro->as_book);
      $this->sort_type = DB::yn2bool($ro->sort_type);
    } else {
      DB::q("INSERT INTO gfb_preferences (user_id) VALUES (" . $this->user_id . ")");
    }
  }

  function fill_from_post() {
    $this->as_book         =  $_POST[reg_as_book] + 0;
    $this->sort_type       =  $_POST[reg_sort_type] + 0;
    $this->form_in_bottom  =  $_POST[reg_form_in_bottom] + 0;
    $this->default_page    =  $_POST[default_page] + 0;
    $this->highlight_nick  =  $_POST[highlight_nick] + 0;

    $dont_trace = '';
    $availableBooks =& $this->user->available_books();
    reset($availableBooks);
    while(list($key, $value) = each($availableBooks)) {
      if (!$_POST['trace_book_' . $key]) {
        $dont_trace .= ($dont_trace ? ',' : '') . $key;
      }
    }
    $this->dont_trace_books = $dont_trace;
  }

  function save() {
    if (!$this->user_id) {
      return 0;
    }

    $query = sprintf("UPDATE gfb_preferences
                         SET last_discussion = %d,
                             form_in_bottom = '%s',
                             dont_trace_books = '%s',
                             default_page = %d,
                             highlight_nick = %d,
                             as_book = '%s',
                             sort_type = '%s'
                       WHERE user_id = %d;",
                     $this->last_discussion,
                     DB::bool2yn($this->form_in_bottom),
                     $this->dont_trace_books,
                     $this->default_page,
                     $this->highlight_nick,
                     DB::bool2yn($this->as_book),
                     DB::bool2yn($this->sort_type),
                     $this->user_id);
    DB::q($query);
    return 1;
  }
}

?>