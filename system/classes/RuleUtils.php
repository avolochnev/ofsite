<?php

class RuleUtils {
	public static function where($where) {
    return DB::map("SELECT rule_id, name FROM gfb_rule WHERE $where ORDER BY priority DESC, rule_id");
	}

  public static function name_for($rule_id) {
    return $rule_id ? DB::field('gfb_rule', 'name', "rule_id = " . $rule_id) : '???';
  }

  public static function query($is_admin) {
    $query = "SELECT * FROM gfb_rule";
    if (!$is_admin) $query .= " WHERE hidden = 'N'";
    $query .= " ORDER BY priority DESC, rule_id;";
    return $query;
  }
}

?>