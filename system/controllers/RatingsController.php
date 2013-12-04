<?php

class RatingsController extends BookAwareController {
  private $limit;

  protected function before() {
    $this->checkAccess(array('BOOK_ADMIN', 'BOOK_RATING'));
    $this->load_book();
    $this->limit = $_GET[limit] + 0;
    if ($this->limit <= 0) $this->limit = 100;
  }

  public function red() {
    $query = "SELECT priority_id AS userid, nick, count(1) as cnt
                FROM gfb_priority AS b, gf_user AS u
               WHERE u.user_id = b.priority_id
               GROUP BY b.priority_id
               ORDER BY cnt DESC
               LIMIT $this->limit";
    $this->render('rating/list.html', array(
      'list' => new Paginator($query, 100),
      'title' => 'Красный сисок'));
  }

  public function black() {
    $query = "SELECT black_id AS userid, nick, count(1) as cnt
                FROM gfb_black_list AS b, gf_user AS u
               WHERE u.user_id = b.black_id
               GROUP BY b.black_id
               ORDER BY cnt DESC
               LIMIT $this->limit";
    $this->render('rating/list.html', array(
      'list' => new Paginator($query, 100),
      'title' => 'Черный сисок'));
  }

  public function messages() {
    $query = "SELECT m.userid, nick, count(1) as cnt
                FROM gfb_message AS m, gf_user AS u, gfb_discussion AS d
               WHERE u.user_id = m.userid AND m.discussion_id = d.discussion_id AND d.book_id = $this->book_id
               GROUP BY m.userid
               ORDER BY cnt DESC
               LIMIT $this->limit";
    $this->render('rating/list.html', array(
      'list' => new Paginator($query, 100),
      'title' => 'Количество сообщений'));
  }

  public function discussions() {
    $query = "SELECT userid, nick, count(1) as cnt
                FROM gfb_discussion AS d, gf_user AS u
               WHERE u.user_id = d.userid AND d.book_id = $this->book_id
               GROUP BY userid
               ORDER BY cnt DESC
               LIMIT $this->limit";
    $this->render('rating/list.html', array(
      'list' => new Paginator($query, 100),
      'title' => 'Количество дискуссий'));
  }
}

?>