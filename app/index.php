<?php

require('config/config.php');
require('../system/autoload.phtml');

$controller = ApplicationController::load();
$controller->dispatch();

?>