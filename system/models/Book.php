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
  var $current_user;
  var $possible_user;

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
    if ($user->is_logged) {
      $this->current_user = $user;
      $this->setup_access($user); // no additional access if not logged
    }
    $this->possible_user = $user;
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

  public function discussion_updates($except_discussion_id = 0) {
    $user = $this->current_user;
    if (!$user) return null;
    $preference = $user->preferences();
    if ($preferences->as_book) return null;
    return DB::all($this->query_discussions('only new', 5, $except_discussion_id));
  }

  // ==========================================================
  // Функция возвращает запрос к базе данных для формирования списка дискуссий.
  // $onlyNew -  показывать только новые вне зависимости от настроек.
  // $limit - ограничение количества возвращаемых дискуссий. По умолчанию возвращаются все дискуссии.
  // $except_discussion_id - discussion to be expcluded from result (usage: to exclude current discussion)
  // ==========================================================
  public function query_discussions($onlyNew = FALSE, $limit = 0, $except_discussion_id = 0) {
    $user = $this->current_user;
    $possible_user = $this->possible_user;
    $book_id = $this->book_id;
    $preference =& $possible_user->preferences();
    $blackList =& $possible_user->black_list();
    UserRead::synchronize($possible_user->user_id, $book_id);

    $user_id = $possible_user->user_id;

    $hide = $except_discussion_id ? " AND d.discussion_id <> $except_discussion_id" : '';

    // Считываем список дискуссий, для которых хранится время последнего прочтения, с количеством новых сообщений и временем последнего сообщения.
    if ($user) {
      // Выбираем все дискуссии, по которым у пользователя нет метки о просмотре.
      // Добавляем метки просмотра для всех этих дискуссий.
      $query = sprintf("
         SELECT d.discussion_id, caption, last_time, first_time, d.date, d.userid, u.nick, COUNT(IF(m.deleted_by = 0, 1, NULL)) as full_count,
             COUNT(IF(r.last_read < m.time AND r.dont_trace = 'N' AND (m.deleted_by = 0 OR m.deleted_by IS NULL), 1, NULL)) as new_count,
             MAX(m.time) as max_time, r.dont_trace, d.voting
             FROM gfb_discussion AS d LEFT JOIN gfb_message AS m USING (discussion_id), gf_user AS u, gfb_user_read AS r
             WHERE book_id = %d AND d.deleted_by = 0 AND d.is_archived = 'N'
                 AND d.discussion_id = r.discussion_id AND r.userid = %d AND d.userid = u.user_id $blackList $hide
             GROUP BY discussion_id", $book_id, $user->user_id);

      // 7. Если стоит настройка показыать только дискуссии с новыми сообщениями, убираем лишние сообщения.
      if ($onlyNew) {
        $query .= ' HAVING new_count > 0';
      }
    } else {
      $query = sprintf("
          SELECT d.discussion_id, caption, last_time, first_time, d.date, d.userid, d.voting, u.nick, COUNT(DISTINCT m.message_id) as full_count
              FROM gfb_discussion AS d, gfb_message AS m, gf_user AS u
              WHERE book_id = %d AND d.deleted_by = 0 AND d.is_archived = 'N' AND d.discussion_id = m.discussion_id AND m.deleted_by = 0
              AND d.userid = u.user_id $blackList $hide
              GROUP BY discussion_id", $book_id);
    }

    // 8. Сортируем сообщения согласно настройке.
    if ($preference->sort_type) { // Сортировать по дате последнего
      if (!$user) {
        $query .= ' ORDER BY last_time DESC';
      } else {
        $query .= ' ORDER BY max_time DESC, last_time DESC';
      }
    } else {
      $query .= ' ORDER BY first_time DESC';
    }

    if ($limit) {
      $query .= " LIMIT $limit";
    }

    $query .= ';';
    return $query;
  }

  public function query_deleted() {
    $book_id = $this->book_id;
    $possible_user = $this->possible_user;
    $black = $possible_user->black_list();
    $preference = $possible_user->preferences();

    // Формируем запрос.
    // Строим запрос на выборку книги согласно установкам.
    $query = sprintf("SELECT m.message_id, m.discussion_id, m.userid, m.date, m.comment, m.text, m.time, m.ip, d.caption, d.first_time, u.nick, m.deleted_by
        FROM gfb_message AS m, gfb_discussion AS d, gf_user AS u
        WHERE book_id = %d AND m.deleted_by <> 0 AND m.discussion_id = d.discussion_id AND d.is_archived = 'N' AND m.userid = u.user_id $black",
        $book_id); //

    if ($preference->sort_type) { // Сортировать по дате последнего
      $query .= ' ORDER BY d.last_time DESC, m.time';
    } else {
      $query .= ' ORDER BY d.first_time DESC, m.time';
    }
    $query .= ' LIMIT 500;';
    return $query;
  }

  public function query_all() {
    $book_id = $this->book_id;
    $possible_user = $this->possible_user;
    $black = $possible_user->black_list();
    $preference = $possible_user->preferences();

    // Формируем запрос.
    // Строим запрос на выборку книги согласно установкам.
    $query = "SELECT m.message_id, m.discussion_id, m.userid, m.date, m.comment, m.text, m.time, m.ip, d.caption,
                     d.first_time, u.nick, m.deleted_by
                FROM gfb_message AS m, gfb_discussion AS d, gf_user AS u
               WHERE book_id = $book_id AND d.deleted_by = 0 AND m.deleted_by = 0 AND m.discussion_id = d.discussion_id
                 AND d.is_archived = 'N' AND m.userid = u.user_id $black"; //

    if ($preference->sort_type) { // Сортировать по дате последнего
      $query .= ' ORDER BY d.last_time DESC, m.time';
    } else {
      $query .= ' ORDER BY d.first_time DESC, m.time';
    }
    $query .= ' LIMIT 500;';
    return $query;
  }
}

?>