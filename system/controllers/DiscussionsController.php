<?php

class DiscussionsController extends BookAwareController {
  var $id_param = 'id';
  var $js_actions = array('markAsRead', 'trace');

  public function create() {
    $this->load_book_for_creator();
    try {
      $discussion_id = DiscussionUtils::create($this->current_user, $this->current_book->book_id, $_POST['comment'], $_POST['caption'], $_POST['message'], $this->time);
      if ($discussion_id) HTTPUtils::redirect("discussions.phtml?id=$discussion_id");
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
      $this->newForm();
    }
  }

  public function archive() {
    $this->load_book();
    $archiveSettings = ArchiveUtils::settings($this->book_id, $this->possible_user, $_GET['month'] + 0, $_GET['year'] + 0);
    $this->render('books/archive.html', array(
      'state' => $archiveSettings,
      'monthes' => DateUtils::$monthListIP,
      'list' => new Paginator($archiveSettings['query'], 100)));
  }

  public function markAsRead() {
    if (!$this->current_user) return;
    $discussions_id = $_POST['discussions_id'];
    $time = $_POST['time'] + 0;
    foreach ($discussions_id as $discussion_id) {
      BookUtils::mark_discussion_as_read($this->current_user->user_id, $discussion_id, $time);
    }
  }

  public function trace() {
    $discussion_id = $_POST['discussion'] + 0;
    $this->params_for_discussion($discussion_id);
    UserRead::dont_trace($this->current_user, $discussion_id, $_POST['dont_trace'] == 'Y' ? 'Y' : 'N');
    echo "$('#trace_toggle_$discussion_id, .trace_toggle').toggle();";
  }

  public function newForm() {
    $this->load_book_for_creator();
    $this->render('discussions/new.html', array(
      'vote' => $_GET['vote'],
      'caption' => $_POST['caption'],
      'message' => $_POST['message']));
  }

  public function deleted() {
    $this->load_book();
    if (!$this->current_book->can_see_deleted) HTTPUtils::forbidden();
    $this->render('books/deleted.html', array(
      'list' => new Paginator(BookUtils::query_deleted($this->book_id, $this->possible_user), 30)));
  }

  public function all() {
    $this->load_book();
    $this->render('books/all.html', array(
      'list' => new Paginator(BookUtils::query_all($this->book_id, $this->possible_user), 30)));
  }

  public function search() {
    $this->load_book();
    $info = new SearchInfo($this->current_book);
    $this->render('search/index.html', array(
      'params' => $info,
      'current_year' => date("Y") + 0,
      'list' => $info->paginator()));
  }

  public function index() {
    $this->load_book();
    $only_new = $_GET[mode] == 'new';
    $query = BookUtils::getDiscussionListQuery2($this->current_user, $this->possible_user, $this->book_id, $only_new, 0);
    if (!$only_new) {
      $this->content->discussion_updates = BookUtils::discussion_updates($this->current_user, $this->book_id);
    }
    $this->render('forum/index.html', array(
      'only_new' => $only_new,
      'list' => new Paginator($query, 20)));
  }

  public function edit() {
    $discussion_id = $this->postOrGet('id') + 0;
    $ro = DiscussionUtils::findById($discussion_id);
    if (!$ro) HTTPUtils::notFound();
    $this->load_book_as_admin($ro->book_id);
    $this->render('discussions/edit.html', array(
      'discussion_id' => $discussion_id,
      'current_book' => $this->book_id,
      'discussion' => array(
        'discussion_id' => $discussion_id,
        'caption' => $ro->caption,
        'dont_archive' => DB::yn2bool($ro->dont_archive),
        'is_archived' => DB::yn2bool($ro->is_archived),
        'is_deleted' => $ro->deleted_by,
        )));
  }

  public function update() {
    $d_id = $_POST[id] + 0;
    $this->params_for_discussion($d_id);
    if (!$this->current_book->is_admin) HTTPUtils::noAccess();
    DiscussionUtils::update($d_id, $_POST['caption'], $_POST['dont_archive']);
    $this->addMessage('Изменения сохранены');
    $this->edit();
  }

  public function destroy() {
    $d_id = $_POST[id] + 0;
    $this->params_for_discussion($d_id);
    if (!$this->current_book->is_admin) HTTPUtils::noAccess();
    DiscussionUtils::destroy($d_id, $this->current_user);
    $this->addMessage("Дискуссия удалена");
    $this->edit();
  }

  public function restore() {
    $d_id = $_POST[id] + 0;
    $this->params_for_discussion($d_id);
    if (!$this->current_book->is_admin) HTTPUtils::noAccess();
    DiscussionUtils::restore($d_id);
    $this->addMessage("Дискуссия восстановлена");
    $this->edit();
  }

  public function stop() {
    $d_id = $_POST[id] + 0;
    $this->params_for_discussion($d_id);
    if (!$this->current_book->is_admin) HTTPUtils::noAccess();
    DiscussionUtils::archive($d_id);
    $this->addMessage("Дискуссия отправлена в архив");
    $this->edit();
  }

  public function move() {
    $d_id = $_POST[id] + 0;
    $target_book = $_POST[target_book] + 0;
    $this->params_for_discussion($d_id); // check original access.
    $this->load_book_as_admin($target_book);
    DiscussionUtils::move($this->current_user, $d_id, $target_book);
    HTTPUtils::redirect("discussions.phtml?id=$d_id");
  }

  public function show() {
    $user = $this->current_user;
    $discussion_id = $_GET['id'] + 0;
    $ro = DiscussionUtils::findById($discussion_id);
    if (!$ro) HTTPUtils::notFound();
    $this->load_book($ro->book_id);
    $is_archived = DB::yn2bool($ro->is_archived);
    $is_deleted = $ro->deleted_by;
    if ($is_deleted) $this->addError('Дискуссия была удалена.');
    $preference = $this->possible_user->preferences();

    $context = array(
      'discussion_id' => $discussion_id,
      'discussion' => $ro,
      'is_archived' => $is_archived,
      'answer_allowed' => !$is_archived && $this->current_book->can_add_message,
      'pref' => $preference,
      'time' => $this->time
    );

    if ($ro->voting == 'true') {
      $vote = Vote::load($discussion_id);
      $context['vote_context'] = $vote->twigContext($user);
      if ($vote->isEditor($user)) $context['vote_edit'] = $vote;
    }

    if ($user) {
      $read = UserRead::load($user->user_id, $discussion_id);
      if ($read->loaded) $context['dont_trace'] = ($read->dont_trace == 'Y');
    }

    // Выводим последние несколько непрочитанных дискуссий.
    $this->content->discussion_updates = BookUtils::discussion_updates($user, $this->book_id, $discussion_id);

    $prev_read = $this->time;
    if ($user && !$is_archived && !$is_deleted) {
      if ($preference->last_discussion != $discussion_id) {
        $prev_read = $read->loaded ? $read->last_read : 0;
        $preference->last_discussion = $discussion_id;
        $preference->save();
      } elseif ($read->loaded) { // we are on the same discussion. show messages form previous read.
        $prev_read = $read->prev_read;
      }
    }

    if (!$is_deleted || $this->current_book->is_admin) {
      $query = DiscussionUtils::messages_query($discussion_id, $this->current_book->can_see_deleted,
                                               $this->possible_user, $prev_read);
      $context['list'] = new Paginator($query, 30);
    }
    $this->render('discussions/show.html', $context);
  }

  private function params_for_discussion($d_id) {
    $book_id = DB::field('gfb_discussion', 'book_id', "discussion_id = $d_id");
    $this->load_book($book_id);
  }

  private function load_book_for_creator($book_id = 0) {
    $this->load_book($book_id);
    if (!$this->current_book->can_create_discussion) HTTPUtils::forbidden();
  }
}

?>