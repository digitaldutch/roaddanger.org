<?php

require_once '../initialize.php';

global $database;
global $user;

$function = $_REQUEST['function'];

require_once 'ExportHandler.php';

$handler = new ExportHandler($database, $user);
$handler->handleRequest($function);
