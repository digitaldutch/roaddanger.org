<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');

header('Content-Type: application/json; charset=utf-8');

// TODO: remove external excess when done

require_once '../initialize.php';
require_once './ReframeHandler.php';

$function = $_REQUEST['function'];

// Public functions
if ($function === 'reframeArticle') {
  echo ReframeHandler::reframeArticle();
  return;
}
