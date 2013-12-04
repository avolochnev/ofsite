<?php

class UsersController extends ApplicationController {
  var $id_param = 'id';

  public function show() {
    $user_to_show = UserUtils::find($_GET['id'] + 0);
    if (!$user_to_show) HTTPUtils::http404();
    $context = array(
      'user' => $user_to_show,
      'birth' => DateUtils::parseFullDate($user_to_show->birth),
      'age' => DateUtils::getYear($user_to_show->birth) ? DateUtils::yearsPassedCaption($user_to_show->birth) : '',
      'block_info' => BlockUtils::find_block_for($user_to_show));

    if ($this->hasAccess(array('S_ADMIN', 'BOOK_ADMIN', 'PRIORITY', 'BOOK_RATING'))) {
      $context['statistics'] = UserUtils::messageStatistics($user_to_show->user_id);
    }

    if ($user = $this->current_user) {
      $context['my_priority'] = UserUtils::isPriority($user->user_id, $user_to_show->user_id);
      $context['my_black'] = UserUtils::isBlack($user->user_id, $user_to_show->user_id);
    }

    $this->render('users/show.html', $context);
  }

  public function edit_by_admin() {
    $this->checkAccess(array('S_ADMIN', 'BOOK_ADMIN'));
    $user_to_show = UserUtils::find($_GET['id'] + 0);
    if (!$user_to_show) HTTPUtils::http404();
    $context = array('user' => $user_to_show);

    if ($this->hasAccess('S_ADMIN')) {
      $context['realms'] = UserUtils::realmsFor($user_to_show->user_id);
    }
    $context['nick_change_history'] = UserUtils::nick_change_history($user_to_show->user_id);
    $context['blocks'] = BlockUtils::find_blocks($user_to_show->user_id);
    $context['warns']  = WarnUtils::find_warns($user_to_show->user_id);

    $this->render('users/edit.by.admin.html', $context);
  }

  public function priorityChange() {
    $user_to_show = UserUtils::find($_GET['id'] + 0);
    if (!$user_to_show) HTTPUtils::http404();
    UserUtils::update_priority($this->current_user, $user_to_show->user_id, $_POST['priority'], $_POST['black']);
    $this->show();
  }

  public function updateAccess() {
    $this->forAdmin();
    $user_to_show = UserUtils::find($_GET['id'] + 0);
    $realms = array();
    foreach ($_POST as $key => $value) {
      if (ereg("realm_(.+)", $key, $arg) && $value) {
        $realms[] = $arg[1];
      }
    }
    Access::update_access($user_to_show->user_id, $realms);
    $this->edit_by_admin();
  }

  public function index() {
    $this->forAdmin();
    $realm_id = $_GET[realm_id];
    $is_group = $_GET[type] == "group";

    $fields = "u.user_id, nick, u.realm_id";
    if ($is_group) {
      $query = "SELECT $fields FROM gf_user AS u WHERE realm_id is not null;";
    } else if ($realm_id) {
      $query = "SELECT $fields FROM gf_user AS u, gf_access AS a WHERE u.user_id = a.user_id AND a.realm_id = '$realm_id'";
    } else {
      $query = "SELECT $fields FROM gf_user AS u;";
    }

    $this->render('users/index.html', array(
      'realm_id' => $realm_id,
      'is_group' => $is_group,
      'realms' => RealmUtils::all(),
      'list' => new Paginator($query)
      ));
  }

  public function addGroupUser() {
    $nick = $_POST['nick'];
    $group = $_POST['group'];
    if (UserUtils::create_group_user($nick, $group)) {
      $this->addMessage("Пользователь $nick для группы $group добавлен");
    }
    $this->index();
  }

  public function registration($form = null) {
    if (!$form) $form = new RegistrationForm();
    $this->render('users/new.html', array('object' => $form));
  }

  public function create() {
    $form = new RegistrationForm();
    $form->setData();
    // Пароль будет закриптован. Сохраняем пароль для его восстановления при редактировании.
    $psw = $form->get(password);
    try {
      $form->save();
      $user_id = $form->get('user_id');
      Access::create_session($user_id);
      HTTPUtils::redirect("users.phtml?id=$user_id");
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
      $form->set(password, $psw);
      $this->registration($form);
    }
  }

  public function edit() {
    $user = $this->current_user;
    if (!$user) HTTPUtils::http404();
    $form = new ProfileForm($user->user_id);
    $form->title = $user->getNick();
    $this->render('users/edit.html', array("object" => $form));
  }

  public function update() {
    $user = $this->current_user;
    if (!$user) HTTPUtils::http404();
    $form = new ProfileForm($user->user_id);
    $form->setData();

    try {
      $form->save();
      $this->addMessage('Профайл обновлен');
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
    }
    $this->edit();
  }

  public function edit_password() {
    if (!$this->current_user) HTTPUtils::http404();
    $this->render('users/change.password.html');
  }

  public function update_password() {
    if (!$this->current_user) HTTPUtils::http404();

    try {
      UserUtils::update_password($this->current_user, $_POST['old'], $_POST['newPassword']);
      $this->addMessage('Пароль изменен. В следующих сеансах используйте новый пароль.');
    } catch (DomainException $e) {
      $this->addError($e->getMessage());
    }

    $this->edit_password();
  }

  public function changePassword() {
    $user_id = $_GET['id'] + 0;
    $pw = $_GET['pw'];
    $this->forAdmin();

    if (!$user_id) {
      echo('user_id is not provided');
      return;
    }

    if (!$pw) {
      echo('pw is not provided');
      return;
    }

    UserUtils::set_password($user_id, $pw);
    echo('Ok');
    exit;
  }

  public function find() {
    $nick = $_POST['nick'];
    if (!$nick) exit;
    $nicks = UserUtils::find_by_nick($nick, !$this->hasAccess('GFMSG_CREATE'));
    twig('users/search.result.js', array('nicks' => $nicks));
    exit;
  }

  public function relationships() {
    $this->checkAccess(array('BOOK_ADMIN', 'BOOK_RATING'));
    $user_id = $_GET['id'] + 0;

    $lists = array();
    $lists['Красный Список'] = DB::all("SELECT nick, u.user_id FROM gf_user AS u, gfb_priority AS p
        WHERE u.user_id = p.priority_id AND p.user_id = $user_id");
    $lists['Включен в Красный Список'] = DB::all("SELECT nick, u.user_id FROM gf_user AS u, gfb_priority AS p
        WHERE u.user_id = p.user_id AND p.priority_id = $user_id");
    $lists['Черный список'] = DB::all("SELECT nick, u.user_id FROM gf_user AS u, gfb_black_list AS p
        WHERE u.user_id = p.black_id AND p.user_id = $user_id");
    $lists['Включен в Черный список'] = DB::all("SELECT nick, u.user_id FROM gf_user AS u, gfb_black_list AS p
        WHERE u.user_id = p.user_id AND p.black_id = $user_id");

    $this->render('users/relationships.html', array('lists' => $lists, 'user_id' => $user_id));
  }

  public function update_settings() {
    $user = $this->current_user;
    if (!$user) HTTPUtils::forbidden();
    $preference = $user->preferences();
    $preference->fill_from_post();
    if ($preference->save()) {
      $this->addMessage('Настройки сохранены.');
    } else {
      $this->addError('Не удалось сохранить настройки.');
    }
    $this->settings();
  }

  public function settings() {
    $user = $this->current_user;
    if (!$user) HTTPUtils::forbidden();
    $highlights = array(1 => 'Зеленым', 2 => 'Красным', 3 => 'Инвертированием');
    $preference = new Preferences($user); // reload prefs - may be udpated.
    $books = array();
    foreach ($user->available_books() as $key => $value) $books[$key] = array('name' => $value->book_name, 'checked' => 1);
    $dont_trace = split(",", $preference->dont_trace_books);
    foreach ($dont_trace as $key => $value) if ($books[$value]) $books[$value][checked] = 0;

    $this->render('settings/form.html', array(
      'uri' => $_SERVER[REQUEST_URI],
      'preference' => $preference,
      'pages' => BookUtils::landingPages(),
      'highlights' => $highlights,
      'books' => $books,
      'black_list' => UserUtils::blackList($user->user_id),
      'red_list' => UserUtils::redList($user->user_id)));
  }

  private function forAdmin() {
    $this->checkAccess('S_ADMIN');
  }
}

?>