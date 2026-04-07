<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
require_once '../general/utils.php';
require_once '../general/OpenRouterAIClient.php';
require_once './ResearchHandler.php';

global $database;
global $user;

$handler = new ResearchHandler($database, $user);
$handler->handleRequest();

