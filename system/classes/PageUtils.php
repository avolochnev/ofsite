<?php

class PageUtils {
  public static function top_pages() {
    return DB::all("SELECT * FROM gfcms_page WHERE page_parent is NULL ORDER BY is_active, priority DESC, page_id;");
  }

  public static function sub_pages($page_id) {
    return DB::all("SELECT * FROM gfcms_page WHERE page_parent = $page_id ORDER BY is_active, priority DESC, page_id;");
  }

  public static function findById($page_id) {
    return DB::obj("SELECT * FROM gfcms_page WHERE page_id = $page_id");
  }
}

?>