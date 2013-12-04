<?php

class PageEditObject extends DBObject {
  function setFields() {
    $this->addField("page_id",         FT_INT,         "Идентификатор", "PK", "Y");
    $this->addField("page_name",       FT_TEXT,        "Заголовок",      0,   "Y", '', 250);
    $this->addField("page_url",        FT_TEXT,        "Редирект",      0,    0, '', 250);
    $this->addField("page_content",    FT_TEXTAREA,    "Контент",   0,    0, '', 0);
    $this->addField("page_parent",     FT_INT_OR_NULL, "Родительская страница",      0,    0, 0, 0, 0, 0, 0, 0); // hidden
    $this->addField("priority",        FT_INT,         "Приоритет",      0,    "Y", 50, 3);
    $this->addField("realm_id",        FT_TEXT,        "Ограничение доступа",      0,   0,  '', 250);
    $this->addField("is_active",       FT_YN,          "Активна",      0,   0,  1, 0);
    $this->addField("new_window",      FT_YN,          "Открывать в новом окне",      0,   0,  0, 0);
    $this->tableName = 'gfcms_page';
    $this->editAction = "edit";
  }

  function PageEditObject($aID=0) {
    $this->DBObject(array("page_id" => $aID));
  }
}

?>