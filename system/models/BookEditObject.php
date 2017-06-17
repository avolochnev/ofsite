<?php

class BookEditObject extends DBObject {
  function setFields() {
    $this->addField("book_id",           FT_INT,      "Идентификатор", "PK", "Y");
    $this->addField("book_name",         FT_TEXT,     "Название",      0,    "Y", '', 100);
    $this->addField("about",             FT_TEXTAREA, "Краткое описание",   0,    0, '', 0);
    $this->addField("description",       FT_TEXTAREA, "Подробное описание", 0,    0, '', 0);
    $this->addField("priority",          FT_INT,      "Приоритет",      0,    "Y", 50, 3);
    $this->addField("access_rights",     FT_TEXT,     "Ограничение доступа",      0,   0,  '', 250);
    $this->addField("admin_rights",      FT_TEXT,     "Группа администраторов",      0,   0,  '', 250);
    $this->addField("create_discussion", FT_TEXT,     "Могут создавать дискуссии",      0,   0,  '', 250);
    $this->addField("create_vote",       FT_TEXT,     "Могут создавать голосования",    0,   0,  'NOBODY', 250);
    $this->addField("pseudo_book",       FT_YN,       "Псевдо-книга",      0,   0,  0, 0);
    $this->addField("alive_term" ,       FT_INT,      "Автоархивация (дней)", 0, 0, 7);
    $this->addField("spec_message",      FT_TEXTAREA, "Специальное сообщение (HTML)", 0,    0, '', 0);
    $this->tableName = 'gfb_book';
    $this->editAction = "edit";
  }

  function BookEditObject($aID=0) {
    $this->DBObject(array("book_id" => $aID));
    $this->title = 'Книга';
  }
}

?>