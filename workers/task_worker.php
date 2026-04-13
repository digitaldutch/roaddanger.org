<?php

require_once '../general/utils.php';
require_once '../general/OpenRouterAIClient.php';

define ('TASK_WORKER_LOCK_FILE', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'task_worker.lock');
define ('TASK_WORKER_STATUS_FILE', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'task_worker_status.json');

abstract class TaskStatus {
  const int pending = 1;
  const int completed = 2;
  const int error = 3;
}

class TaskWorker
{
  private const int TASK_TIMEOUT_SECONDS = 120;

  private Database $database;
  private string $startTime = '';
  private string $endTime = '';
  public array $status = [];

  public function __construct(Database $database) {
    $this->database = $database;
  }

  public static function isRunning(): bool {
    $handle = fopen(TASK_WORKER_LOCK_FILE, 'w') or dieWithJSONErrorMessage('Cannot open or create task worker lock file');

    if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
      // Lock obtained -> worker not running -> release it immediately.
      flock($handle, LOCK_UN);
      fclose($handle);
      return false;
    }

    fclose($handle);

    // Another process holds the lock.
    return $wouldBlock;
  }

  /**
   * @throws Exception
   */
  public function start(): void {
    $lockHandle = $this->acquireLock();

    if ($lockHandle === null) {
      // Task worker is already running.
      return;
    }

    $this->startTime = $this->getCurrentTimestamp();
    $this->saveStatusFile('Preparing tasks to be executed');

    $tasks = $this->getTasksQueue();
    $totalTasks = count($tasks);

    if ($totalTasks === 0) {
      $this->stop('No tasks found');
      return;
    }

    $i = 0;
    foreach ($tasks as $task) {
      $i++;

      $this->saveStatusFile("Executing task $i of $totalTasks (task id $task->id)");

      $this->executeTask($task);
    }

    $this->stop('ready');
  }

  private function acquireLock(): mixed {
    $handle = fopen(TASK_WORKER_LOCK_FILE, 'w') or dieWithJSONErrorMessage('Cannot open or create task worker lock file');

    if (flock($handle, LOCK_EX | LOCK_NB)) {
      fwrite($handle, '1');
      return $handle;
    }

    fclose($handle);
    return null;
  }

  private function executeTask(object $task): void {
    // Every task gets 2 minutes to complete. After 2 minutes, the PHP script will be killed.
    set_time_limit(self::TASK_TIMEOUT_SECONDS);

    try {
      $openrouter = new OpenRouterAIClient();

      $chat_response = $openrouter->chatAnswerArticleQuestionnaires($task->article_id, $task->questionnaire_id);
      $task->ai_model = $chat_response['model_id'];

      $this->markTaskCompleted($task);

    } catch (\Throwable $e) {
      $this->saveStatusFile("Task $task->id failed: " . $e->getMessage());
      $this->markTaskError($task, $e->getMessage());
    }
  }

  private function stop(string $statusMessage = ''): void {
    $this->endTime = $this->getCurrentTimestamp();
    $this->saveStatusFile($statusMessage);
  }

  private function getTasksQueue(): bool|array {
    $params = ['status' => TaskStatus::pending];
    $sql = "SELECT id, article_id, questionnaire_id FROM ai_tasks WHERE task_status = :status ORDER BY id;";
    return $this->database->fetchAllObjects($sql, $params);
  }

  private function markTaskCompleted(object $task): void {
    $this->updateTaskStatus($task, TaskStatus::completed);
  }

  private function markTaskError(object $task, string $error): void {
    $this->updateTaskStatus($task, TaskStatus::error, 'Error: ' . $error);
  }

  private function updateTaskStatus(object $task, int $status, string $info=''): void {
    $params = [
      ':status' => $status,
      ':info' => $info,
      ':ai_model' => $task->ai_model,
      ':id' => $task->id,
    ];

    $sql = "UPDATE ai_tasks SET task_status = :status, ai_model = :ai_model, info = :info WHERE id = :id;";

    $this->database->execute($sql, $params);
  }

  private function getCurrentTimestamp(): string {
    return gmdate('c'); // c = ISO 8601 format. e.g.: 2026-02-12T15:19:21+00:00
  }

  private function saveStatusFile(string $statusMessage): void {
    $this->status = [
      'updated' => $this->getCurrentTimestamp(),
      'start_time' => $this->startTime,
      'end_time' => $this->endTime,
      'info' => $statusMessage,
    ];
    file_put_contents(TASK_WORKER_STATUS_FILE, json_encode($this->status));
  }
}

