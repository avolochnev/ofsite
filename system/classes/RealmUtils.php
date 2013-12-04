<?php

class RealmUtils {
  public static function create($realm_id, $description, $is_restrict) {
    DB::q(sprintf("INSERT INTO gf_realm (realm_id, description, is_restrict)
                        VALUES ('%s', '%s', '%s') ",
                        DB::safe($realm_id), DB::safe($description), $is_restrict ? 'Y' : 'N'));

  }

  public static function all() {
    return DB::all("SELECT * FROM gf_realm;");
  }
}

?>