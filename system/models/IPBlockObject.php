<?php

function getRuleSelect($name, $rule_id) {
  $ret = '<SELECT NAME="' . $name . '" value="' . $rule_id . '">';
  $result = DB::all("SELECT rule_id, name FROM gfb_rule WHERE need_block = 'Y' AND hidden = 'N' ORDER BY priority DESC, rule_id;");
  foreach ($result as $ro) {
    $ret .=  '<OPTION VALUE="' . $ro->rule_id . '"';
    if ($ro->rule_id == $rule_id) {
      $ret .=  ' selected';
    }
    $ret .=  '>';
    $ret .= $ro->name;
  }
  $ret .=  '</SELECT>';
  return $ret;
}

class IPBlockObject extends DBObject {
  function setFields() {
    $this->addField("ip_block_id",       FT_INT,      "Идентификатор", "PK", "Y");
    $this->addField("ip",     FT_TEXT,     "IP",      0,    "Y", '', 30);
    $this->addField("rule_id",       FT_INT_FK,      "Правило",        0,    "Y", '', 0, "getRuleSelect");
    $this->addField("comment",        FT_TEXTAREA, "Комментарий",   0,    0, '', 0);
    $this->tableName = "gfb_ip_block";
    $this->editAction = "edit";
  }

  function IPBlockObject($aID=0) {
    $this->DBObject(array("ip_block_id" => $aID));
    $this->title = 'Блокировка по IP';
  }
}

?>