<?php

class Paginator {
  var $query;
  var $baseAddress;
  var $data;
  var $linkList;
  var $recordsOnPage = 10;
  var $count; // Количество записей, которые были получены.

  function Paginator($aQuery, $recordsOnPage = 100) {
    $this->query = $aQuery;
    $this->recordsOnPage = $recordsOnPage;
    // $this->baseAddress = $_SERVER[SCRIPT_NAME];
    $this->baseAddress = strtok($_SERVER['REQUEST_URI'], '?');
    $add = '';
    reset($_GET);
    $knownParams = array('showPage' => 1, 'showRecord' => 1);
    while(list($param, $value) = each($_GET)) {
      if (!$knownParams[$param]) {
        $add .= ($add ? '&' : '?');
        $add .= $param .  '=' . $value;
      }

    }
    $this->baseAddress .= $add;
    $this->execute();
  }

  // Возвращает TRUE, если запись с переданным номером находится на странице с переданным номером.
  function isOnPage($record, $showPage) {
    if ($this->recordsOnPage < 1) {
      return TRUE;
    }
    return ($record > ($showPage - 1) * $this->recordsOnPage && $record <= $showPage * $this->recordsOnPage);
  }

  function execute() {
    $showPage = $_GET["showPage"] + 0;
    $showRecord = $_GET["showRecord"] + 0;

    // showPage == -1 означает, что мы хотим показать последнюю страницу.
    if ($showPage == -1) {
      $showPage = 100000;
    }

    // Если передан параметр showRecord, то нужно показыавть страницу, на которой находится сообщение с переданным порядковым номером.
    if ($showRecord) {
      $showPage = floor(($showRecord - 1) / $this->recordsOnPage) + 1;
    }

    if (!$showPage) {
      $showPage = 1;
    }
    $this->data = array();
    $this->linkList = '';
    $result = DB::i($this->query);
    $rowCount = $result->size();

    if ($this->recordsOnPage < 1) {
      $this->recordsOnPage = $rowCount;
    }

    // Если идет попытка показать страницу, которой нет,
    // то будем показывать последнюю страницу.
    $lastPageNumber = floor(($rowCount - 1) / $this->recordsOnPage) + 1;
    if ($lastPageNumber < $showPage) {
      $showPage = $lastPageNumber;
    }

    $count = 0;
    $dataCount = 0;
    foreach ($result as $ra) {
      $count++;
      if ($this->isOnPage($count, $showPage)) {
        $this->data[$dataCount++] = $ra;
      }
    }


    // Заполняем список ссылок на страницы.
    if ($count > $this->recordsOnPage) {
      $linksPerPage = 10;
      $linksCount = floor(($count - 1) / $this->recordsOnPage + 1);
      $linkPagesCount = floor(($linksCount - 1) / $linksPerPage + 1);
      $linkPage = floor(($showPage - 1) / $linksPerPage + 1);
      // print("Links - $linksCount<BR/>LinkPages = $linkPagesCount<BR/>Link Page - $linkPage<BR/>");

      if ($linkPagesCount > 1) {
        if ($linkPage > 1) {
          // Ссылка на первую страницу
          $address = $this->getPageAdderss(1);
          $this->linkList .= sprintf("<A HREF=\"%s\" class=\"link\">|&lt;&lt;</A>&nbsp;&nbsp;", $address);
        }
        if ($linkPage > 2) {
          $address = $this->getPageAdderss(($linkPage - 1) * $linksPerPage);
          $this->linkList .= sprintf("<A HREF=\"%s\" class=\"link\">&lt;&lt;</A>&nbsp;&nbsp;", $address);
        }
      }
      $firstLink = ($linkPage - 1) * $linksPerPage + 1;
      $lastLink = min($firstLink + $linksPerPage - 1, $linksCount);
      $divider = '';
      // print("First: $firstLink, Last: $lastLink");
      for ($i = $firstLink; $i <= $lastLink; $i++) {
        $first = ($i - 1) * $this->recordsOnPage + 1;
        $last = $i * $this->recordsOnPage;
        $last = min($last, $count);
        if ($divider) {
          $this->linkList .= $divider;
        } else {
          $divider = ' | ';
        }
        if ($i == $showPage) {
          $this->linkList .= sprintf("<B>%d-%d</B>", $first, $last);
        } else {
          $address = $this->baseAddress;
          if (ereg("\?", $address)) {
            $address .= '&';
          } else {
            $address .= '?';
          }
          $address .= "showPage=$i";
          $this->linkList .= sprintf("<A HREF=\"%s\" class=\"link\">%d-%d</A>", $address, $first, $last);
        }
      }
      if ($linkPagesCount > 1) {
        if ($linkPage < $linkPagesCount - 1) {
          $address = $this->getPageAdderss($linkPage * $linksPerPage + 1);
          $this->linkList .= sprintf("&nbsp;&nbsp;<A HREF=\"%s\" class=\"link\">&gt;&gt;</A>", $address);
        }
        if ($linkPage < $linkPagesCount) {
          // Ссылка на первую страницу
          $address = $this->getPageAdderss($linksCount);
          $this->linkList .= sprintf("&nbsp;<A HREF=\"%s\" class=\"link\">&gt;&gt;|</A>", $address);
        }
      }

    }
    $this->count = $count;
  }

  function getPageAdderss($n) {
    $address = $this->baseAddress;
    if (ereg("\?", $address)) {
      $address .= '&';
    } else {
      $address .= '?';
    }
    $address .= "showPage=$n";
    return $address;
  }

  public static function pagination($paginator) {
    if ($paginator->linkList) {
      return '<div class="gf_link_list">' . $paginator->linkList . "</div>";
    } else {
      return '';
    }
  }

}

?>