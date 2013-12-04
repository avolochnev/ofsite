<?php

class ProfileForm extends DBObject {

  function setFields() {
    $this->addField("user_id",       FT_INT,      "Идентификатор", "PK", "Y");
    $this->addField("last_name",     FT_TEXT,     "Фамилия",  0, 0,   '', 50);
    $this->addField("first_name",    FT_TEXT,     "Имя",      0, 0,   '', 50);
    $this->addField("middle_name",   FT_TEXT,     "Отчество", 0, 0,   '', 50);
    $this->addField("birth",         FT_DATE_OR_NULL, "Дата Рождения", 0, 0,   '', 0);
    $this->addField("email",         FT_TEXT,     "e-mail",   0, "Y", '', 250);
    $this->addField("hide_email",    FT_YN,       "Скрыть e-mail",  0, 0,   'Y');
    $this->addField("note",          FT_TEXTAREA, "О себе");
    $this->addField("picture_url",   FT_TEXT,     "URL фотографии",   0, 0, '', 250);
    $this->addField("open_mailbox", FT_YN, "Принимать личные сообщения от всех посетителей");
    $this->tableName = gf_user;
    $this->editAction = 'update';
  }

  function ProfileForm($aID=0) {
    $this->DBObject(array("user_id" => $aID));
  }

  function findTags($field) {
    if (ereg("[<|>]", $this->get($field))) {
      throw new DomainException('В поле "' . $this->field[$field][FLD_DESCRIPTION] . '" нельзя использовать знаки "больше" и "меньше".');
    }
    return 0;
  }

  function validation($is_update) {
    $textFields = array(last_name, first_name, middle_name, email, note);
    foreach ($textFields as $key => $field) $this->findTags($field);
    $template = "^[a-zA-Z0-9_\\.\\-]+@[a-zA-Z0-9_\\.\\-]+\\.[a-zA-Z]+$";
    if (!ereg($template, $this->get(email))) {
      throw new DomainException("Адрес электронной почты указан неверно");
    }
    return TRUE;
  }
}

?>