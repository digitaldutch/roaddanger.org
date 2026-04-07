<?php

require_once '../initialize.php';

global $database;
global $user;


require_once 'ExportHandler.php';

$handler = new ExportHandler($database, $user);
$handler->handleRequest();
