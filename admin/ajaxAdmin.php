<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

global $database;
global $user;

$function = $_REQUEST['function'];

require_once 'AdminHandler.php';
$handler = new AdminHandler($database, $user);
$handler->handleRequest($function);

