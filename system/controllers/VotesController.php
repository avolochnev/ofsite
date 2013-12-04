<?php

class VotesController extends ApplicationController {
  var $default_action = 'vote';
  var $js_actions = array('vote', 'addOption', 'destroy');
  private $vote;

  function __construct() {
    $discussion_id = $_POST['discussion_id'] + 0;
    $this->vote = Vote::load($discussion_id);
  }

  public function destroy() {
    $this->rejectNotAuthorized();
    $this->mustBeEditor('Нет прав на удаление варианта голосования');
    $id = $_POST[option_id] + 0;
    if (!$this->vote->deleteOption($id)) throw new DomainException('Невозможно удалить вариант');
    $this->renderVote();
  }

  public function addOption() {
    $this->rejectNotAuthorized();
    $this->mustBeEditor();
    $option = $_POST['option'];
    if (!$this->vote->addOption($option, $this->current_user)) throw new DomainException('Ошибка добавления варианта.');
    $discussion_id = $this->vote->discussion_id;
    echo("$('#add_option_$discussion_id').val('');");
    $this->renderVote();
  }

  public function vote() {
    $this->rejectNotAuthorized();
    $this->vote->userVote($this->current_user, $_POST[option_id] + 0);
    $this->renderVote();
  }

  /**
   * Updates the vote on browswer
   *
   * Draw js to update vote elment on client;
   */
  private function renderVote() {
    twig('vote/update.js', array('vote' => $this->vote, 'vote_context' => $this->vote->twigContext($this->current_user)));
  }

  private function rejectNotAuthorized() {
    if (!$this->current_user) throw new DomainException('Пожалуйста, войдите на сайт.');
  }

  private function mustBeEditor($msg = 'Нет прав доступа') {
    if (!$this->vote->isEditor($this->current_user)) throw new DomainException($msg);
  }
}

?>