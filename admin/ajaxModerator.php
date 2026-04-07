<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

global $user, $database;

require_once 'ModeratorHandler.php';

$handler = new ModeratorHandler($database, $user);
$handler->handleRequest();