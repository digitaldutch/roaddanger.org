<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

global $user, $database;

$function = $_REQUEST['function'];

require_once 'ModeratorHandler.php';

$handler = new ModeratorHandler($database, $user);
$handler->handleRequest($function);