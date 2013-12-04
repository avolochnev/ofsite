<?php

class Vote {
  const COUNT = 20;
  var $options = array();
  var $discussion_id;

  /**
   * Creates vote from POST request.
   * Returns FALSE if no vote provided.
   */
  public static function fromPost() {
    if ($_POST['vote'] != 'true') return FALSE;
    $vote = new Vote();
    for($i = 1; $i <= self::COUNT; $i++) {
      $opt = self::sanitarize($_POST["option$i"]);
      if ($opt) {
        $vote->options[$i] = $opt;
      }
    }
    return $vote;
  }

  /**
   * Loads vote from db by given discussion_id
   */
  public static function load($discussion_id) {
    $vote = new Vote();
    $vote->discussion_id = $discussion_id;
    $vote->reloadOptions();
    return $vote;
  }

  public function isValid() {
    if (!count($this->options)) return FALSE;
    $antimat = Antimat::instance();
    foreach ($this->options as $opt) {
      if ($antimat->isUncensorship($opt)) return FALSE;
    }
    return TRUE;
  }

  public function save($discussion_id, $user) {
    foreach ($this->options as $opt) {
      $this->insertOption($discussion_id, $opt, $user);
    }
    DB::q("UPDATE gfb_discussion SET voting = 'true' WHERE discussion_id = $discussion_id");
  }

  public function userVote($user, $id) {
    DB::q("DELETE FROM gfb_vote WHERE user_id = $user->user_id AND discussion_id = $this->discussion_id");
    DB::q("INSERT INTO gfb_vote (user_id, discussion_id, option_id) VALUES ($user->user_id, $this->discussion_id, $id)");
  }

  public function isEditor($user) {
    if (!$user) return FALSE;
    $created_by = DB::field('gfb_discussion', 'userid', "discussion_id = $this->discussion_id") + 0;
    return $user->user_id == $created_by || $user->hasAccess('BOOK_ADMIN');
  }

  public function addOption($opt, $user) {
    $opt = self::sanitarize($opt);
    if (!$opt) return FALSE;
    $antimat = Antimat::instance();
    if ($antimat->isUncensorship($opt)) return FALSE;
    $this->insertOption($this->discussion_id, $opt, $user);
    $this->reloadOptions();
    return TRUE;
  }

  public function deleteOption($option_id) {
    $cnt = DB::count('gfb_vote', "discussion_id = $this->discussion_id AND option_id = $option_id");
    if ($cnt) return FALSE;
    DB::q("DELETE FROM gfb_vote_options WHERE discussion_id = $this->discussion_id AND id = $option_id");
    $this->reloadOptions();
    return TRUE;
  }

  private function insertOption($discussion_id, $option, $user) {
    $option = DB::safe($option);
    DB::q("INSERT INTO gfb_vote_options (discussion_id, title, created_by) VALUES ($discussion_id, '$option', $user->user_id)");
  }

  private function reloadOptions() {
    $query = "SELECT id, title
                FROM gfb_vote_options
               WHERE discussion_id = $this->discussion_id";
    $this->options = DB::map($query);
  }

  private static function sanitarize($text) {
    return TextUtils::removeSpaces(TextUtils::removeTags($text));
  }

  public function selectedOption($user) {
    if (!$user) return 0;
    return DB::field('gfb_vote', 'option_id', "discussion_id = $this->discussion_id AND user_id = $user->user_id");
  }

  public function twigContext($user) {
    return array(
      'vote' => $this,
      'editor' => $this->isEditor($user),
      'selected_option' => $this->selectedOption($user),
      'result' => new VoteResult($this));
  }
}

class VoteResult {
  var $sums = array();
  var $percents = array();
  var $totalVotes = 0;
  var $order = array();
  var $maxVotes = 0;
  var $stars = array();

  function __construct($vote) {
    $this->loadVotes($vote->discussion_id);
    $this->calculateStat($vote);
  }

  private function loadVotes($discussion_id) {
    $query = "SELECT option_id, count(user_id) as cnt
                FROM gfb_vote
               WHERE discussion_id = $discussion_id GROUP BY option_id";
    foreach (DB::i($query) as $ro) {
      $this->totalVotes += $ro->cnt;
      $this->sums[$ro->option_id] = $ro->cnt;
    }
  }

  private function calculateStat($vote) {
    if ($this->totalVotes > 0) {
      arsort($this->sums);
      foreach ($this->sums as $option_id => $count) {
        $this->percents[$option_id] = round($count * 100 / $this->totalVotes);
        if ($this->percents[$option_id] == 0) $this->percents[$option_id] = 1;
        $this->order[] = $option_id;
        if (!$this->maxVotes) $this->maxVotes = $count;
        $this->stars[$option_id] = ceil($count * 5 / $this->maxVotes);
      }
    }
    $this->fillOrderForMissedOptions($vote);
  }

  private function fillOrderForMissedOptions($vote) {
    foreach ($vote->options as $option_id => $title) {
      if (!$this->sums[$option_id]) $this->order[] = $option_id;
    }
  }
}

?>