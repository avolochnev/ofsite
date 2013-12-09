<?php

class MessagesController extends BookAwareController {
  var $js_actions = array('update', 'loadPrev', 'addMessage', 'edit', 'toggle');

  public function create() {
    if (!$this->current_user) HTTPUtils::forbidden();
    $discussion_id = $_GET['id'] + 0;
    $discussion = $this->load_discussion_as_writer($discussion_id);
    try {
      MessageUtils::verify_and_insert($discussion_id, $_POST['message'], $_POST['comment'], $this->time, $this->current_user);
      HTTPUtils::redirect("discussions.phtml?id=$discussion_id&showPage=-1");
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
      $this->render('message/error.html');
    }
  }

  public function addMessage() {
    $user = $this->current_user;
    if (!$user) return;
    $discussion_id = $_POST['discussion_id'] + 0;
    $discussion = $this->load_discussion_as_writer($discussion_id);
    $text = $_POST['text'];
    MessageUtils::verify_and_insert($discussion_id, $text, '', $this->time, $user);
    $prev_read = UserRead::last_read($user->user_id, $discussion_id);
    $addedMessages = MessageUtils::for_period($discussion_id, $this->possible_user, $prev_read, $this->time);
    $this->render('message/insert.js', array(
      'discussion_id' => $discussion_id,
      'messages' => $addedMessages,
      'profile_in_new_page' => true));
    UserRead::mark_discussion_as_read($user->user_id, $discussion_id, $this->time);
  }

  public function loadPrev() {
    $discussion_id = $_POST['discussion_id'] + 0;
    $discussion = $this->load_discussion($discussion_id);
    $addedMessages = MessageUtils::prev_messages($discussion_id, $this->possible_user, $_POST['last_time'] + 0);
    $first_time = 0;
    if (count($addedMessages)) {
      $first_message = $addedMessages[0];
      $first_time = $first_message->time;
    }
    $this->render('message/prev.js', array(
      'discussion_id' => $discussion_id,
      'messages' => $addedMessages,
      'first_time' => $first_time,
      'profile_in_new_page' => true));
  }

  public function edit() {
    $msg = MessageUtils::find($_POST['message_id'] + 0);
    $this->load_book_as_admin($msg->book_id);
    twig('message/edit.form.html', array(
      'msg' => $msg,
      'comment' => TextUtils::quote($msg->comment),
      'discussions' => DiscussionUtils::discussion_options($msg->book_id, $msg->discussion_id)));
  }

  public function update() {
    $message_id = $_POST['message_id'] + 0;
    $msg = MessageUtils::find($message_id);
    $this->load_book_as_admin($msg->book_id);
    MessageUtils::update($message_id, $_POST['comment'], $_POST['text']);
    $move_to_discussion_id = $_POST['move_to_discussion_id'] + 0;
    if ($msg->discussion_id != $move_to_discussion_id) {
      MessageUtils::move_to_discussion($message_id, $msg->discussion_id, $move_to_discussion_id);
      echo("$('#message_$message_id').replaceWith('<div class=\"notify\">Сообщение перенесено</div>');");
    } else {
      $msg = MessageUtils::find($message_id); // reload.
      $this->render('message/update.js', array(
        'message_id' => $message_id,
        'msg' => $msg,
        'profile_in_new_page' => true));
    }
    echo("$('#admin_message_placeholder_$message_id').empty().html('<div class=\"notify\">Сообщение изменено</div>');");
  }

  public function show() {
    $message_id = $_GET['id'] + 0;
    $ro = MessageUtils::find($message_id);
    if (!$ro) HTTPUtils::notFound();
    $this->load_book($ro->book_id);
    if ($ro->deleted_by && !$this->current_book->is_admin) {
      $user = $this->current_user;
      if (!$user || $user->user_id != $ro->userid) HTTPUtils::forbidden();
    }
    $this->render('message/show.html', array('msg' => $ro));
  }

  // delete/undelete message
  public function toggle() {
    $id = $_POST[id] + 0;
    $msg = MessageUtils::find($id);
    $this->load_book_as_admin($msg->book_id);
    MessageUtils::toggle($this->current_user, $id, $_POST['deletion'] == 'delete');
    twig('message/toggle.deleted.js', array('message_id' => $id));
  }

  public function index() {
    $user = $this->current_user;
    $this->load_book();
    $mode = $_GET['mode'];
    if ($mode == 'messages') $mode = 'new';
    $last_show_time = $_COOKIE["board_$this->book_id"] + 0;
    $discussions = DiscussionUtils::updated_discussions($user, $this->possible_user, $this->book_id, $last_show_time);

    setcookie("board_$this->book_id", $this->time, mktime(0, 0, 0, 1, 1, 2020), "/");

    $new_count = 0;
    $discussions_id = array();
    // Для каждой дискуссии загружаем сообщения, которые нужно показать.
    // Параллельно считаем количество новых сообщений.
    foreach($discussions AS $index => $d) {
      $last_read = $user ? $d->last_read : $last_show_time;
      $messages = MessageUtils::for_period($d->discussion_id, $this->possible_user, $last_read, $this->time, $mode);
      $discussions[$index]->messages = array();
      $has_unread = false;
      foreach($messages AS $ro) {
        if ($ro->is_new) {
          ++$new_count;
          $has_unread = true;
        }
        $discussions[$index]->messages[] = $ro;
      }
      if ($has_unread && $user) $discussions_id[] = $d->discussion_id;
      if ($d->voting == 'true') {
        $vote = Vote::load($d->discussion_id);
        $d->vote = $vote->twigContext($user);
      }
      if (count($messages) > 0 && $mode == 'new') {
        $firstMessage = $messages[0];
        if ($firstMessage->time > $d->first_time) {
          $d->first_displayed_time = $firstMessage->time;
        }
      }
    }

    $this->render('message/index.html', array(
      'only_new' => ($mode == 'new'),
      'discussions' => $discussions,
      'new_count' => $new_count,
      'time' => $this->time,
      'profile_in_new_page' => true,
      'discussions_id' => $discussions_id
      ));
  }

  private function load_discussion_as_writer($discussion_id) {
    $discussion = $this->load_discussion($discussion_id);
    if (!$this->current_book->can_add_message) HTTPUtils::forbidden();
    return $discussion;
  }

  private function load_discussion($discussion_id) {
    $discussion = DiscussionUtils::findById($discussion_id);
    $this->load_book($discussion->book_id);
    return $discussion;
  }
}

?>