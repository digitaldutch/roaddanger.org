<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
require_once 'GeneralHandler.php';

global $database;
global $user;

$function = $_REQUEST['function'];

$handler = new GeneralHandler($database, $user);
$handler->handleRequest($function);