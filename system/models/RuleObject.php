<?php

class RuleObject extends DBObject {
  function setFields() {
    $this->addField("rule_id",       FT_INT,      "Идентификатор", "PK", "Y");
    $this->addField("name",          FT_TEXT,     "Заголовок",      0, "Y", '', 255);
    $this->addField("description",   FT_TEXTAREA, "Описание");
    $this->addField("priority",      FT_INT,      "Приоритет",     0,    "Y", 50, 3);
    $this->addField("need_warn",     FT_YN,       "Выносить предупреждение в случае нарушения");
    $this->addField("need_block",    FT_YN,       "Блокировать пользователя в случае нарушения");
    $this->addField("hidden",        FT_YN,       "Скрыть правило");
    $this->tableName = 'gfb_rule';
    $this->editAction = "edit";
  }

  function RuleObject($aID=0) {
    $this->DBObject(array("rule_id" => $aID));
    $this->title = 'Правило';
  }
}

?>