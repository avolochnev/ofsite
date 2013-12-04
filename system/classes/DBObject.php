<?php
// ATTENTION. Spagetti-code. To be refactored.

// Константы для обращения к полям массива field.
define("FLD_TYPE",        0);
define("FLD_DESCRIPTION", 1);
define("FLD_PK",          2);
define("FLD_MANDATORY",   3);
define("FLD_VALUE",       4);
define("FLD_MAX_LENGTH",  5);
define("FLD_VALUES_LIST", 6);
define("FLD_VISIBLE",     7);

// Константы для типов полей.
define("FT_TEXT",         1);
define("FT_INT",          2);
define("FT_DATE",         3);
define("FT_YN",           4);
define("FT_ENUM",         5);
define("FT_ENUM_FK",     10);
define("FT_ENUM_INT",    11);
define("FT_INT_OR_NULL",  6);
define("FT_INT_FK",       7);
define("FT_DATE_OR_NULL", 8);
define("FT_TEXTAREA",     9);
define("FT_PASSWORD",    12);

// Класс DBObject реализует реализует объект, который умеет читать и писать себя в базу данных.
class DBObject {
  // двумерный массив, хранящий имена полей, типы и значения.
  var $field = array();
  // Имя таблицы.
  var $tableName;

  // Поле для хранения сообщения об ошибке.
  var $error_text = "";

  // Заголовок окна редактирования.
  var $title = '';

  // Additional &action=<> param for update query
  var $editAction = '';

  // Устанавливает имя базы данных, имя таблицы, список полей и адрес страницы для редактирования.
  // Обязана быть переопределена в дочерних классах
  function setFields() {
    return;
  }

  // Возвращает значение поля по имени
  function get($key) {
    return $this->field[$key][FLD_VALUE];
  }

  function set($key, $value) {
    $this->field[$key][FLD_VALUE] = $value;
  }

  // Устанавливает ключевые значения полей
  function setKeyValues($keyValues) {
    if ($keyValues) {
      reset($keyValues);
      while (list($key, $value) = each($keyValues)) {
        $this->field[$key][FLD_VALUE] = $value;

      }
    }
  }

  // Возвращает часть WHERE команды SELECT, которая определяет запись по первичному ключу.
  // В случае проблем возвращает 0. (Например, не у всех полей первичного ключа установлены значения).
  function getPKWhere() {
    reset($this->field);
    $all = 1;
    $where = '';
    while (list($key, $value) = each($this->field)) {
      if ($this->field[$key][FLD_PK]) {
        if ($this->get($key)) {
          if ($where) {
            $where .= " AND ";
          }
          $where .= $this->getSetStatement($key);
        } else {
          $all = 0;
        }
      }
    }
    if ($all && $where) {
      return $where;
    }
    return 0;
  }

  // Загружает данные объекта из базы данных.
  // Возвращает 1, если загрузка прошла нормально, либо если загружать нечего
  function load() {
    // Проверяем, у всех ли ключевых полей есть значения.
    if ($where = $this->getPKWhere()) {
      // Если есть, то формируем запрос к базе.
      $query = sprintf("SELECT * FROM %s WHERE %s;", $this->tableName, $where);
      // Выполняем запрос и заполняем значения полей.

      $result = DB::q($query);
      if (!mysql_num_rows($result)) {
        mysql_free_result($result);
        $this->error_text = "Объект не найден";
        return 0;
      }
      $resultArray = mysql_fetch_array($result);
      reset($this->field);
      while (list($key) = each($this->field)) {
        $type = $this->field[$key][FLD_TYPE];
        if ($type == FT_YN) {
          $this->field[$key][FLD_VALUE] = DB::yn2bool($resultArray[$key]);
        } else {
          $this->field[$key][FLD_VALUE] = $resultArray[$key];
        }
      }
      mysql_free_result($result);
    }

    return 1;
  }

  function DBObject($keyValues) {
    $this->setFields();

    $this->setKeyValues($keyValues);

    if (!$this->load()) {
      exit;
    }
  }

  // добавляет поле
  function addField($name, $type, $description, $isPK = 0, $isMandatory = 0, $default = 0, $width = 0, $valuesList = 0, $visible = 1) {
    $this->field[$name] = array($type, $description, $isPK, $isMandatory, ($default ? $default : ''), $width, $valuesList, $visible);
  }

  // Загружает данные из переменных HTTP-запроса.
  // Используется при добавлении/обновлении страны.
  function setData() {
    reset($this->field);
    while (list($key) = each($this->field)) {
      $type = $this->field[$key][FLD_TYPE];
      $isPK = $this->field[$key][FLD_PK];
      if (!$isPK) {
        if ($type == FT_DATE || $type == FT_DATE_OR_NULL) {
          $this->field[$key][FLD_VALUE] = DateUtils::concatDate($_POST[$key . "_year"], $_POST[$key . "_month"], $_POST[$key . "_day"]);
        } else {
          $this->field[$key][FLD_VALUE] = stripslashes($_POST[$key]);
        }
      }
    }
  }

  // Возвращает список имен полей для формирования INSERT
  function getFieldNamesList() {
    $result = '';
    reset($this->field);
    while (list($key) = each($this->field)) {
      $isPK = $this->field[$key][FLD_PK];
      if (!$isPK) {
        if ($result) {
          $result .= ', ';
        }
        $result .= $key;
      }
    }
    return $result;
  }

  // Возвращает список значений полей для формирования INSERT
  function getValuesList() {
    $result = '';
    reset($this->field);
    while (list($key) = each($this->field)) {
      $isPK = $this->field[$key][FLD_PK];
      if (!$isPK) {
        if ($result) {
          $result .= ', ';
        }
        $result .= $this->getTextValue($key);
      }
    }
    return $result;
  }

  // Вытаскивает значение первичного ключа из базы данных
  // сразу после добавления.
  // Очень даже может переопределяться в потомках,
  // так как этот вариант рассчитан на первичный ключ
  // из одного столбца, который генерируется посредством
  // поля типа AUTO_INCREMENT.
  // Возвращает 1, если все получилось, и 0 в противном случае.
  function setPKAfterInsert() {
    $result = DB::q("SELECT LAST_INSERT_ID() AS id;");
      $resultArray = mysql_fetch_array($result);
      if ($resultArray["id"] == 0) {
        mysql_free_result($result);
        return 0;
      } else {
        reset($this->field);
        while (list($key) = each($this->field)) {

          $isPK = $this->field[$key][FLD_PK];
          if ($isPK) {
            $this->field[$key][FLD_VALUE] = $resultArray["id"];

          }
        }
        mysql_free_result($result);
        return 1;
      }
  }

  // Формирует список пар field=value для UPDATE .. SET
  function getFullSetStatement() {
    $result = '';
    reset($this->field);
    while (list($key) = each($this->field)) {
      $isPK = $this->field[$key][FLD_PK];
      if (!$isPK) {
        if ($result) {
          $result .= ', ';
        }
        $result .= $this->getSetStatement($key);
      }
    }
    return $result;
  }

  // Сохраняет данные объекта в базе данных.
  // Если объекта нет в базе, то он туда добавляется.
  // Возвращает 0, в случае ошибки, и 1, если сохранение прошло
  // успешно.
  function save() {
    $where = $this->getPKWhere();

    if (!$this->validation($where ? 1 : 0)) {
      return 0;
    }

    if (!$where) {
      $fieldsList = $this->getFieldNamesList();
      $valuesList = $this->getValuesList();
      $query = sprintf("INSERT INTO %s (%s) VALUES (%s);", $this->tableName, $fieldsList, $valuesList);
      DB::q($query);
      if (!$this->setPKAfterInsert()) throw new DomainException("Не удалось установить первичный ключ.");
    } else {
      // У объекта есть id - обновляем данные.
      $query = sprintf("UPDATE %s SET %s WHERE %s;", $this->tableName, $this->getFullSetStatement(), $where);
      DB::q($query);
    }
    return 1;
  }

  // Проверка информации перед добавлением/обновлением.
  // Возвращает 1, если проверка прошла, и 0, если были найдены ошибки.
  // Найденные ошибки помещает на страницу.
  // Реализация по умолчанию возвращает 1.
  // Может быть переопределена в дочерних классах для реализации проверки объекта.
  // $is_update : 1 - обновление, 0 - добавление записи.
  function validation($is_update) {
    return 1;
  }

  // Возвращает адрес страницы, осуществляющей редактирование объекта
  function getEditAddress() {
    $result = '';
    reset($this->field);
    while (list($key) = each($this->field)) {
      if ($this->field[$key][FLD_PK]) {
        if ($result) {
          $result .= '&';
        }
        $result .= sprintf("%s=%s", $key, $this->get($key));
      }
    }
    if ($_GET[t]) {
        if ($result) {
          $result .= '&';
        }
        $result .= sprintf("t=%s", $_GET[t]);
    }
    return sprintf("%s?%s", strtok($_SERVER['REQUEST_URI'], '?'), $result);
  }

  // ==========================================================
  // Печатает форму для добавления редактирования объекта.
  function printEditForm($title = '') {
?>

<SCRIPT language="javascript">
function checkEditForm() {
<?php

    reset($this->field);
    while (list($key) = each($this->field)) {
      if ($this->field[$key][FLD_MANDATORY] && $this->field[$key][FLD_TYPE] != FT_DATE && $this->field[$key][FLD_TYPE] != FT_DATE_OR_NULL && !$this->field[$key][FLD_PK] && $this->field[$key][FLD_TYPE] != FT_INT_FK) {
?>
  if (!document.sendForm.<?php print $key; ?>.value) {
    alert ('Необходимо заполнить <?php print addslashes($this->field[$key][FLD_DESCRIPTION]); ?>!');
    return false;
  }
<?php
      }
    }

?>

  return true;
}
</SCRIPT>

<FORM ACTION="<?php print $this->getEditAddress(); ?>" METHOD="POST" NAME="sendForm">
<INPUT TYPE=HIDDEN NAME="post" VALUE="POST"></INPUT>
<?php if ($this->editAction) { ?>
<INPUT TYPE=HIDDEN NAME="action" VALUE="<?php print $this->editAction; ?>"></INPUT>
<?php } ?>
<table align="center" class="form">
<?php
    if ($title) {
?>
<TR class=header><TD colspan=3><?php echo($title); ?></TD></TR>
<?php
    }

    reset($this->field);


    while (list($key) = each($this->field)) {

      if (!$this->field[$key][FLD_PK]) {

        print $this->getFormField($key);
      }
    }

?>
<TR><TD class="buttons" colspan=3><INPUT TYPE=SUBMIT VALUE="Отправить" onClick="return checkEditForm();"></INPUT></TD></TR>
</TABLE>
</FORM>

<?php
  }

  // Возвращает кусок html-кода, формирующий часть формы редактированя объекта,
  // соответствующий полю $key.
  // Может быть переопределена у потомков.
  function getFormField($key) {
    return $this->getFormFieldInt2($key,
        $this->get($key),
        $this->field[$key][FLD_DESCRIPTION],
        $this->field[$key]);
  }

  // Возвращает кусок html-кода, формирующий часть формы редактированя объекта,
  // соответствующий полю $key.
  function getFormFieldInt2($key, $value, $displayName, &$data) {
    global $twig;
    $visible = $data[FLD_VISIBLE];
    $type = $data[FLD_TYPE];
    $mandatory = $data[FLD_MANDATORY];
    if ($visible) {
      $result = '<TR>';
      if ($type == FT_DATE || $type == FT_DATE_OR_NULL) {
        $result .= sprintf("<TD colspan=3>%s:</TD></TR><TR><TD colspan=3>День: %s Месяц: %s Год: %s</TD>",
            $displayName,
            $twig->render('components/dropdown.day.html', array('name' => $key . "_day", 'value' => DateUtils::getDay($value))),
            $twig->render('components/dropdown.month.html', array('name' => $key . "_month", 'value' => DateUtils::getMonth($value))),
            $twig->render('components/dropdown.year.html', array(
              'name' => $key . "_year", 'value' => DateUtils::getYear($value), 'start' => 1901, 'finish' => 2050))
            );
      } else if ($type == FT_TEXTAREA) {
        $result .= sprintf("<TD colspan=3>%s:<BR><TEXTAREA NAME=\"%s\" rows=8 cols=40 wrap=\"virtual\">", $displayName, $key);
        $result .= $value . "</TEXTAREA></TD>";
      } else if ($type == FT_YN) {
          $result .= sprintf("<TR><TD colspan=3><INPUT TYPE=\"checkbox\" NAME=\"%s\" value=\"1\"%s>%s</INPUT></TD></TR>", $key, ($value ? " checked" : ''), $displayName);
      } else {
        $result .= '</TD><TD>';
        if ($mandatory) {
          $result .= "* ";
        }
        $result .= sprintf("%s:</TD><TD>", $displayName);
        if ($type == FT_ENUM || $type == FT_ENUM_INT) {
          $result .= $twig->render('components/dropdown.html', array(
            'name' => $key, 'value' => $value, 'options' => $data[FLD_VALUES_LIST], 'needEmpty' => !$data[FLD_MANDATORY]));
        } else if ($type == FT_INT_FK || $type == FT_ENUM_FK) {
          $function = $data[FLD_VALUES_LIST];
          $result .= $function($key, $value);
        } else {
          if ($type == FT_PASSWORD) {
            $inputType = "PASSWORD";
          } else {
            $inputType = "TEXT";
          }
          $result .= sprintf("<INPUT TYPE=$inputType NAME=\"%s\" value=\"%s\"", $key, TextUtils::quote($value));
          if ($max = $data[FLD_MAX_LENGTH]) {
            $result .= sprintf(" MAXLENGTH=%d", $max);
          }
          $result .= "></INPUT>";

        }

        $result .= "</TD><TD>";
        $result .= "</TD></TR>";

      }
      return $result;
    } else {
      return sprintf('<INPUT TYPE="HIDDEN" NAME="%s" value="%s"></INPUT>', $key, TextUtils::quote($value));
    }
  }

  function out() {
    $this->printEditForm($this->title);
  }

  function getTextValueInt($value, $type) {
    if ($type == FT_INT || $type == FT_INT_FK || $type == FT_ENUM_INT) {
      return sprintf("%d", $value);
    } else if ($type == FT_INT_OR_NULL) {
      return sprintf("%s", ($value ? $value : 'NULL'));
    } else if ($type == FT_DATE_OR_NULL) {
      if (DateUtils::getYear($value) + 0) {
        return sprintf("'%s'", $value);
      } else {
        return "NULL";
      }
    } else if ($type == FT_YN) {
      return sprintf("'%s'", DB::bool2yn($value));
    } else if ($type == FT_TEXT || $type == FT_TEXTAREA || $type == FT_PASSWORD) {
      if ($value) {
        return sprintf("'%s'", addslashes($value));
      } else {
        return "NULL";
      }
    } else {
      return sprintf("'%s'", addslashes($value));
    }
  }

  // Возвращает строку вида "value" из строки вида "field=value"
  // для включения в условие WHERE или в UPDATE .. SET SQL-скрипта.
  // $key - имя поля.
  function getTextValue($key) {
    $type = $this->field[$key][FLD_TYPE];
    $value = $this->get($key);
    return $this->getTextValueInt($value, $type);

  }

  // Возвращает строку вида "field=value" для включения в условие
  // $key - имя поля.
  function getSetStatement($key) {
    return sprintf("%s=%s", $key, $this->getTextValue($key));
  }

  /**
   * Уникальный идентификатор объекта.
   *
   * Возвращает первичный ключ объекта. Если объект не имеет первичного ключа, еще не сохранен в базе,
   * или первичный ключ состоит из нескольких полей, функция возвращает <CODE>0</CODE>.
   */
  function getID() {
      foreach ($this->field AS $key => $value) {
          if ($value[FLD_PK]) {
              return $value[FLD_VALUE];
          }
      }
      return 0;
  }

  function processPost() {
    if ($_POST["post"]) {
      $this->setData();
      return $this->save();
    } else {
      return false;
    }
  }

  public static function render($obj, $title = '') {
    ob_start();
    $obj->printEditForm($title);
    $form = ob_get_contents();
    ob_end_clean();
    return $form;
  }
}
?>