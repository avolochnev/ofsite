<?php

class HTTPUtils {
	public static function http404() {
  	header("HTTP/1.1 404 Not Found");
  	echo('Not found');
  	exit();
  }

  public static function notFound() {
    self::http404();
  }

  public static function noAccess() {
    header("HTTP/1.1 403 Forbidden");
    echo('Forbidden');
    exit();
  }

  public static function forbidden() {
    self::noAccess();
  }

  public static function redirect($location = '/') {
    if (!$location) $location = '/';
    header("Location: $location");
    exit();
  }
}

?>