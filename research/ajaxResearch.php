<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
require_once '../general/utils.php';
require_once '../general/OpenRouterAIClient.php';
require_once './ResearchHandler.php';

$function = $_REQUEST['function'];

global $database;
global $user;

$handler = new ResearchHandler($database, $user);
$handler->handleRequest($function);

