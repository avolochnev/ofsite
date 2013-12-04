<?php

class FileUtils {
  public static function randomPix($localDir) {
    $pixList = array();
    // Читаем список файлов из галереи.
    $dir = dir($localDir);
    $count = 0;
    while ($fileName = $dir->read()) {
      if ($fileName != '.' && $fileName != '..') {
        $pixList[$count++] = $localDir . $fileName;
      }
    }
    return $pixList[rand(0, $count - 1)];
  }
}

?>