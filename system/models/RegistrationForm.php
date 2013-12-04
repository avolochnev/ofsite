<?php

class RegistrationForm extends DBObject {
  function setFields() {
    $this->addField("user_id",       FT_INT,      "Идентификатор", "PK", "Y");
    $this->addField("nick",          FT_TEXT,     "Ник",      0, "Y", '', 50);
    $this->addField("nickid",        FT_TEXT,     "Код ника", 0, 0, '', 50, 0, 0, 0, 0); // Invisible
    $this->addField("password",      FT_PASSWORD, "Пароль",   0, "Y", '', 20);
    $this->addField("last_name",     FT_TEXT,     "Фамилия",  0, 0,   '', 50);
    $this->addField("first_name",    FT_TEXT,     "Имя",      0, 0,   '', 50);
    $this->addField("middle_name",   FT_TEXT,     "Отчество", 0, 0,   '', 50);
    $this->addField("birth",         FT_DATE_OR_NULL, "Дата Рождения", 0, 0,   '', 0);
    $this->addField("secret",        FT_TEXT,     "Кодовое слово",   0, "Y", '', 50);
    $this->addField("email",         FT_TEXT,     "e-mail",   0, "Y", '', 250);
    $this->addField("hide_email",    FT_YN,       "Скрыть e-mail",  0, 0, 'Y');
    $this->addField("note",          FT_TEXTAREA, "О себе");
    $this->addField("reg_date",      FT_TEXT,     "Дата регистрации", 0, 0, '', 50, 0, 0, 0, 0); // Invisible
    $this->addField("picture_url",   FT_TEXT,     "URL фотографии",   0, 0, '', 250);
    $this->tableName = gf_user;
    $this->editAction = 'create';
  }

  function RegistrationForm($aID=0) {
    $this->DBObject(array("user_id" => $aID));
    $this->title = 'Регистрация';
  }

  function findTags($field) {
    if (ereg("[<|>]", $this->get($field))) {
      throw new DomainException('В поле "' . $this->field[$field][FLD_DESCRIPTION] . '" нельзя использовать знаки "больше" и "меньше".');
    }
    return 0;
  }

  function validation($is_update) {
    $this->set(nickid, UserUtils::getNickID($this->get(nick)));
    $this->set(reg_date, date("Y-m-d"));
    $this->set(password, Access::crypt_password($this->get(password)));

    $result = 1;

    $textFields = array(nick, last_name, first_name, middle_name, secret, email, icq, note);
    foreach ($textFields as $key => $field) {
      if ($this->findTags($field)) $result = 0;
    }
    // Вставить проверку на то, что имя пользователя содержит буквы.
    if (!ereg("[A-Z_3].*[A-Z_3]", $this->get(nickid))) {
      throw new DomainException('Ник должен содержать хотя бы две буквы.');
    }

    // Проверяем корректность e-mail
    $template = "^[a-zA-Z0-9_\\.\\-]+@[a-zA-Z0-9_\\.\\-]+\\.[a-zA-Z]+$";
    if (!ereg($template, $this->get(email))) {
      throw new DomainException('Адрес электронной почты указан неверно');
      $result = 0;
    }

    // Блокировка автомата, который в фамилию, имя и отчество вставляет ник.
    if ($this->get('nick') == $this->get('last_name')
        && $this->get('nick') == $this->get('first_name')
        && $this->get('nick') == $this->get('middle_name'))  {
      throw new DomainException('Ошибка регистрации');
      $result = 0;
    }

    if (!$is_update) {
      // Проверяем, нет ли одноименного пользователя.
      $r = DB::q("SELECT user_id FROM gf_user WHERE nickid='" . $this->get(nickid) . "';");
      if ($resultArray = mysql_fetch_array($r)) {
        if ($resultArray["user_id"] > 0) {
          // Нашелся пользователь с таким же ID - добавление невозможно.
          throw new DomainException("Пользователь с ником " . $this->get(nick) . " уже зарегистрирован.");
          $result = 0;
        }
      }

      // Проверяем, нет ли в нике символов, запрещенных к использованию в нике.
      $template = "^[ a-zA-Z0-9_\\.\\-АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ]+$";
      // print($template . '<BR>');
      if (!ereg($template, TextUtils::rusUpperCase($this->get(nick)))) {
        throw new DomainException('Ваш ник должен содержать только русские или английские буквы, пробел, точку, тире, символ подчеркивания.');
        $result = 0;
      }
    }

    return $result;
  }


}
?>