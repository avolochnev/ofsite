<?php

class CMSMenu {
  public static function main($id = 0) {
    while ($id) {
      $parent = $id;
      $id = DB::field('gfcms_page', 'page_parent', "page_id = $id");
    };
    return self::fill(null, $parent);
  }

  public static function sub($id) {
    $menu = new Menu();
    while ($id) {
      $parent = $id;
      $id = DB::field('gfcms_page', 'page_parent', "page_id = $id");
    };
    if ($parent) {
      return self::fill($parent);
    } else {
      return new Menu();
    }
  }

  public static function local($id) {
    $menu = new Menu();
    $chain = array();
    while ($id) {
      $chain[] = $id;
      $id = DB::field('gfcms_page', 'page_parent', "page_id = $id");
    };
    $chain = array_reverse($chain);
    if ($chain[1]) {
      return self::fill($chain[1]);
    } else {
      return new Menu();
    }
  }

  private static function fill($parent_id = null, $top_id = null) {
    $where = $parent_id ? "page_parent = $parent_id"
                        : '(page_parent IS NULL OR page_parent = 0)';
    $r = DB::i("SELECT page_id, page_name, page_url, new_window FROM gfcms_page WHERE $where AND is_active = 'Y' ORDER BY priority DESC, page_id;");
    $menu = new Menu();
    foreach($r as $ro) {
      $menu->items[] = self::menu($ro, $top_id);
    }
    return $menu;
  }

  private static function menu($ro, $top_id = null) {
    $url = ($ro->page_url ? $ro->page_url : "pages.phtml?id=$ro->page_id");
    return new Menu($ro->page_name, $url, '', $ro->new_window == 'Y', $ro->page_id == $top_id);
  }
}

?>