<?php

require_once '../initialize.php';

// Background script.
// It may only be run from the command line in production.
if (IS_PRODUCTION_ENVIRONMENT && (php_sapi_name() !== 'cli')) {
  die('Script may only run from command line');
}

try {
  // If started from the command line, change directory to the script's directory
  if (php_sapi_name() === 'cli') chdir(dirname(__FILE__));

  // No task should take longer than the time limit
  // Every task resets the limit
  $time_limit = 2 * 60;
  set_time_limit($time_limit);

  require_once '../database.php';
  require_once 'task_worker.php';

  $database = new Database();
  $database->open();

  $task_worker = new TaskWorker($database);
  $task_worker->start();

  print json_encode($task_worker->status);

} catch (\Throwable $e) {
  $errorText = 'Task worker error: ' . $e->getMessage();

  dieWithJSONErrorMessage($errorText);
} finally {
  $database->close();
}
