<?php

require_once '../general/AjaxHandler.php';
class ResearchHandler extends AjaxHandler {

  public function handleRequest(): void {
    try {

      // Public functions
      $response = match ($this->command) {
        'loadQuestionnaireResults' => $this->loadQuestionnaireResults(),
        default => null,
      };

      // The stuff below is for moderators only
      if (($response === null) && $this->user->isModerator()) {
        $response = match ($this->command) {
          'getResearch_UVA_2026' => $this->getResearch_UVA_2026(),
          default => null,
        };
      }

      // The stuff below is only for administrators
      if (($response === null) && $this->user->admin) {
        $response = match($this->command) {
          'aiRunPrompt' => $this->aiRunPrompt(),
          'aiInit' => $this->aiInit(),
          'aiGetAvailableModels' => $this->aiGetAvailableModels(),
          'aiGetGenerationInfo' => $this->aiGetGenerationInfo(),
          'loadArticle' => $this->loadArticle(),
          'selectAiModel' => $this->selectAiModel(),
          'removeAiModel' => $this->removeAiModel(),
          'updateModelsDatabase' => $this->updateModelsDatabase(),
          'aiSavePrompt' => $this->aiSavePrompt(),
          'aiGetPromptList' => $this->aiGetPromptList(),
          'aiDeletePrompt' => $this->aiDeletePrompt(),
          'loadArticlesToAnswer' => $this->loadArticlesToAnswer(),
          'loadAITasks' => $this->loadAITasks(),
          'findArticlesForAITasks' => $this->findArticlesForAITasks(),
          'addAITasks' => $this->addAITasks(),
          'deleteTask' => $this->deleteTask(),
          'getTaskWorkerStatus' => $this->getTaskWorkerStatus(),
          'loadQuestionnairesData' => $this->database->loadQuestionnairesData(),
          'saveQuestion' => $this->saveQuestion(),
          'deleteQuestion' => $this->deleteQuestion(),
          'saveQuestionsOrder' => $this->saveQuestionsOrder(),
          'saveQuestionnaire' => $this->saveQuestionnaire(),
          'deleteQuestionnaire' => $this->deleteQuestionnaire(),
          'queueArticleForAIAnswering' => $this->queueArticleForAIAnswering(),
          'startAITaskWorker' => $this->startAITaskWorker(),
          default => null,
        };
      }

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }

  private function saveQuestion(): array {
    $question = $this->input;

    $isNew = (empty($question['id']));
    if ($isNew) {
      $sql = "INSERT INTO questions (text, explanation) VALUES (:text, :explanation);";

      $params = [
        ':text' => $question['text'],
        ':explanation' => $question['explanation'],
      ];
      $this->database->execute($sql, $params);
      $question['id'] = (int)$this->database->lastInsertID();

    } else {
      $sql = "UPDATE questions SET text=:text, explanation=:explanation WHERE id=:id;";
      $params = [
        ':id' => $question['id'],
        ':text' => $question['text'],
        ':explanation' => $question['explanation'],
      ];

      $this->database->execute($sql, $params);
    }

    return ['id' => $question['id']];
  }

  private function deleteQuestion(): array {
    $question = $this->input;

    $sql = "SELECT questionnaire_id FROM questionnaire_questions WHERE question_id=:question_id;";
    $params = [':question_id' => $question['id']];
    $dbIds = $this->database->fetchAll($sql, $params);
    $ids = [];
    foreach ($dbIds as $dbId) $ids[] = $dbId['questionnaire_id'];

    if (count($ids) > 0) {
      $idsString = implode(", ", $ids);
      throw new \Exception('Cannot delete question. Question is still use in questionnaires: ' . $idsString);
    }

    $sql = "DELETE FROM questions WHERE id=:id;";

    $params = [':id' => $question['id']];
    $this->database->execute($sql, $params);

    return [];
  }

  private function saveQuestionsOrder(): array {
    $ids = $this->input;

    $sql = "UPDATE questions SET question_order=:question_order WHERE id=:id";
    $statement = $this->database->prepare($sql);
    foreach ($ids as $order=>$id){
      $params = [':id' => $id, ':question_order' => $order];
      $this->database->executePrepared($statement,$params);
    }

    return [];
  }

  private function saveQuestionnaire(): array {
    $questionnaire = $this->input;

    $isNew = (empty($questionnaire['id']));
    if ($isNew) {
      $sql = "INSERT INTO questionnaires (title, type, country_id, active, public, exclude_unilateral) VALUES (:title, :type, :country_id, :active, :public, :exclude_unilateral);";

      $params = [
        ':title' => $questionnaire['title'],
        ':type' => $questionnaire['type'],
        ':country_id' => $questionnaire['countryId'],
        ':active' => intval($questionnaire['active']),
        ':public' => intval($questionnaire['public']),
        ':exclude_unilateral' => intval($questionnaire['exclude_unilateral']),
      ];
      $this->database->execute($sql, $params);
      $questionnaire->id = (int)$this->database->lastInsertID();
    } else {
      $sql = "UPDATE questionnaires SET title=:title, type=:type, country_id=:country_id, active=:active, public=:public, exclude_unilateral=:exclude_unilateral WHERE id=:id;";

      $params = [
        ':id' => $questionnaire['id'],
        ':title' => $questionnaire['title'],
        ':type' => $questionnaire['type'],
        ':country_id' => $questionnaire['countryId'],
        ':active' => intval($questionnaire['active']),
        ':public' => intval($questionnaire['public']),
        ':exclude_unilateral' => intval($questionnaire['exclude_unilateral']),
      ];
      $this->database->execute($sql, $params);
    }

    // Save questionnaire questions
    $sql = "DELETE FROM questionnaire_questions WHERE questionnaire_id=:questionnaire_id;";
    $params = [':questionnaire_id' => $questionnaire['id']];
    $this->database->execute($sql, $params);

    $sql = "INSERT INTO questionnaire_questions (questionnaire_id, question_id, question_order) VALUES (:questionnaire_id, :question_id, :question_order);";
    $statement = $this->database->prepare($sql);
    $order = 1;
    foreach ($questionnaire['questionIds'] as $questionId){
      $params = [':questionnaire_id' => $questionnaire['id'], ':question_id' => $questionId, ':question_order' => $order];
      $this->database->executePrepared($statement, $params);
      $order += 1;
    }

    return ['id' => $questionnaire['id']];
  }

  private function deleteQuestionnaire(): array {
    $questionnaire = $this->input;

    $sql = "DELETE FROM questionnaires WHERE id=:id;";
    $params = [':id' => $questionnaire['id']];

    $this->database->execute($sql, $params);

    return [];
  }

  /*
   * queue article to let AI answer the active questionnaire's
   */
  private function queueArticleForAIAnswering(): array {

    $articleId = $this->input['articleId'];
    $remove = $this->input['remove'];

    if ($remove) {
      // Remove all tasks for this article
      $sql = "DELETE FROM ai_tasks WHERE article_id = :articleId;";
      $params = [':articleId' => $articleId];

      $this->database->execute($sql, $params);
    } else {

      // Get current active questionnaire ID's
      $sql = "SELECT id FROM questionnaires WHERE active=1;";
      $questionnairesIds = $this->database->fetchAllValues($sql);

      foreach ($questionnairesIds as $questionnaireId) {
        $sql = <<<SQL
INSERT INTO ai_tasks (article_id, task_status, questionnaire_id)   
VALUES (:articleId, 1, :questionnaireId);                                                                                                                                                                                          
SQL;
        $params = [
          ':articleId' => $articleId,
          ':questionnaireId' => $questionnaireId,
          ];

        $this->database->execute($sql, $params);
      }

    }

    return [
      'articleId' => $articleId,
      ];
  }

  /**
   * @throws Exception
   */
  private function startAITaskWorker(): array {
    $taskWorkerStartFile = __DIR__ . '/../workers/start_AI_task_worker.php';

    startPHPFromCommandLine($taskWorkerStartFile);

    return [];
  }

  /**
   * @throws Exception
   */
  private function loadQuestionnaireResults(): array {
    $filter = $this->input['filter'];
    $filter['public'] = ! $this->user->admin;
    $articleFilter = $this->input['articleFilter'];
    $group = $this->input['group']?? '';

    require_once 'Research.php';
    return Research::loadQuestionnaireResults($filter, $group, $articleFilter);
  }

  private function loadArticlesToAnswer(): array {

    $filter = $this->input['filter'];
    $sort = $this->input['sort'];

    // Get active questionnaires
    $sql = <<<SQL
SELECT
  id,
  title,
  type,
  exclude_unilateral
FROM questionnaires
WHERE active = 1
ORDER BY id;
SQL;

    $questionnaires = $this->database->fetchAll($sql);

    // Sort crash persons on dead=3, injured=2, unknown=0, uninjured=1
    $sql = <<<SQL
SELECT 
  groupid,
  transportationmode,
  health,
  child,
  underinfluence,
  hitrun
FROM crashpersons
WHERE crashid=:crashid
ORDER BY health IS NULL, FIELD(health, 3, 2, 0, 1);
SQL;

    $dBStatementCrashPersons = $this->database->prepare($sql);

    if (count($questionnaires) > 0) {
      // Leave space ' ' to prevent parameter starting with WHERE
      $SQLWhereAnd = ' ';
      $SQLSort = '';

      $offset = $this->input['offset'];
      $count = $this->input['count'];

      $params = [
        ':offset' => $offset,
        ':count'  => $count,
      ];

      addPersonsWhereSql($SQLWhereAnd, $filter);

      if (isset($filter['noUnilateral']) && ($filter['noUnilateral'] === 1)){
        $SQLWhereAnd .= " AND c.unilateral !=1 ";
      }

      $SQLWhereAnd .= $this->user->countryId === 'UN'? '' : " AND c.countryid='" . $this->user->countryId . "'";

      if ($filter['answered_by_type'] === 'unanswered') {
        $SQLWhereAnd .= " AND NOT EXISTS(SELECT 1 FROM answers WHERE articleid = a.id) ";
      } else if ($filter['answered_by_type'] === 'human') {
        $SQLWhereAnd .= " AND ans.answered_by_type = 1 ";
      } else if ($filter['answered_by_type'] === 'ai') {
        $SQLWhereAnd .= " AND ans.answered_by_type = 2 ";
      }

      if (($sort === 'answered_at') && ($filter['answered_by_type'] !== 'unanswered')) {
        $SQLSort = "ORDER BY ans.answered_at DESC";
      } else if ($sort === 'added_at') {
        $SQLSort = "ORDER BY a.id DESC";
      } else {
        $SQLSort = "ORDER BY a.publishedtime DESC";
      }


      // Jan Derk note:
      // This is a dirty hack join to get the AI LLM and answered_at as there are multiple answers per article.
      // We just look at the first one.
      /** @noinspection SqlIdentifier */
      $sql = <<<SQL
SELECT
a.id,
a.title,
a.url,
a.sitename,
a.publishedtime,
ans.answered_by_type,
ans.answered_at,
ans.ai_info,
c.date AS crash_date,
c.unilateral AS crash_unilateral,
c.countryid AS crash_countryid,
c.id AS crashid
FROM articles a
LEFT JOIN crashes c ON a.crashid = c.id
LEFT JOIN answers ans ON ans.articleid = a.id 
  AND ans.questionid = (SELECT MIN(a2.questionid) FROM answers a2  WHERE a2.articleid = a.id)
WHERE ((alltext IS NOT NULL) AND (alltext != ''))
$SQLWhereAnd
$SQLSort
LIMIT :offset, :count;
SQL;
    }

    $articles = $this->database->fetchAll($sql, $params);

    $crashes = [];
    foreach ($articles as $article) {
      $article['publishedtime'] = datetimeDBToISO8601($article['publishedtime'] ?? '');

      $crash = [
        'id' => $article['crashid'],
        'date' => $article['crash_date'],
        'countryid' => $article['crash_countryid'],
        'unilateral' => $article['crash_unilateral'] === 1,
      ];

      // Load crash persons
      $crash['persons'] = $this->database->fetchAllPrepared($dBStatementCrashPersons, ['crashid' => $crash['id']]);

      $crashes[] = $crash;
    }

    return [
      'crashes'  => $crashes,
      'articles' => $articles,
    ];
  }

  /**
   * @throws DateMalformedStringException
   */
  private function loadAITasks(): array {
    $offset = $this->input['offset'];
    $count = $this->input['count'];
    $filter = $this->input['filter'];

    $params = [
      ':offset' => $offset,
      ':count'  => $count,
    ];

    $SQLWhere = '';

    if (! empty($filter['status'])){
      $params[':status'] = $filter['status'];
      addSQLWhere($SQLWhere, 't.task_status = :status');
    }

    if (! empty($filter['questionnaire_id'])){
      $params[':questionnaire_id'] = $filter['questionnaire_id'];
      addSQLWhere($SQLWhere, 't.questionnaire_id = :questionnaire_id');
    }

    $sql = <<<SQL
SELECT
  t.id,
  t.task_status,
  COALESCE(t.ai_model, '') AS ai_model,
  t.created_at,
  t.processed_at,
  t.questionnaire_id,
  q.title AS questionnaire_title,
  t.article_id,
  a.title AS article_title,
  COALESCE(info, '') AS info
FROM ai_tasks t
LEFT JOIN questionnaires q ON t.questionnaire_id = q.id
LEFT JOIN articles a ON t.article_id = a.id
$SQLWhere
ORDER BY t.id DESC
LIMIT :offset, :count
SQL;

    $tasks = $this->database->fetchAll($sql, $params);
    foreach ($tasks as &$task) {
      $task['created_at'] = datetimeDBToISO8601($task['created_at'] ?? '');
      $task['processed_at'] = datetimeDBToISO8601($task['processed_at'] ?? '');
    }

    return [
      'tasks' => $tasks,
    ];
  }

  private function getFirstQuestionOfQuestionnaire($questionnaire_id): ?int {
    $params = [
      'questionnaire_id' => $questionnaire_id,
    ];

    // Get first question of questionnaire
    $sql = <<<SQL
SELECT
  question_id
from questionnaire_questions
WHERE questionnaire_id = :questionnaire_id
order by question_order
LIMIT 1;
SQL;

    return $this->database->fetchSingleValue($sql, $params);
  }

  /**
   * @throws Exception
   */
  private function findArticlesForAITasks(): array {
    $questionnaire_id = $this->input['questionnaire_id'];
    $filter = $this->input['filter'];

    $exclude_unilateral = $this->database->fetchSingleValue("SELECT exclude_unilateral FROM questionnaires WHERE id = :questionnaire_id", [':questionnaire_id' => $questionnaire_id]);

    $first_question_id = $this->getFirstQuestionOfQuestionnaire( $questionnaire_id);

    if ($first_question_id === false) {
      throw new \Exception("No questions found for questionnaire $questionnaire_id");
    }

    $sqlWhereAnd = ' '; // Keeps space as we want to start with AND not WHERE
    $params = [];
    if ($exclude_unilateral === 1) {
      $sqlWhereAnd .= " AND c.unilateral !=1 ";
    }

    [$sqlWhereAnd, $params] = getCrashesWhere($filter, $sqlWhereAnd, $params);

    $sql = <<<SQL
SELECT
  count(*)
FROM articles a
LEFT JOIN crashes c ON a.crashid = c.id
WHERE ((a.alltext IS NOT NULL) AND (a.alltext != ''))
$sqlWhereAnd
SQL;

    $total = $this->database->fetchSingleValue($sql, $params);

    // Get number of articles for which the first question has not been answered
    // Only for articles with full text available
    $sql = <<<SQL
SELECT
  count(*)
FROM articles a
LEFT JOIN crashes c ON a.crashid = c.id
WHERE ((a.alltext IS NOT NULL) AND (a.alltext != ''))
   $sqlWhereAnd
AND EXISTS(SELECT 1 FROM answers WHERE articleid = a.id AND questionid = :question_id);
SQL;

    $params[':question_id'] = $first_question_id;
    $answered = $this->database->fetchSingleValue($sql, $params);

    return [
      'total' => $total,
      'answered' => $answered,
    ];
  }

  /**
   * @throws Exception
   */
  private function addAITasks(): array {
    $questionnaire_id = (int)$this->input['questionnaire_id'];
    $tasks_count = (int)$this->input['tasks_count'];
    $filter = $this->input['filter'];

    if (is_nan($questionnaire_id)) throw new \Exception("Invalid questionnaire_id");
    if (is_nan($tasks_count)) throw new \Exception("Invalid count");

    $first_question_id = $this->getFirstQuestionOfQuestionnaire( $questionnaire_id);
    if ($first_question_id === false) throw new \Exception("No questions found for questionnaire $questionnaire_id");

    $exclude_unilateral = $this->database->fetchSingleValue("SELECT exclude_unilateral FROM questionnaires WHERE id = :questionnaire_id", [':questionnaire_id' => $questionnaire_id]);

    $sqlWhereAnd = ' '; // Keeps space as we want to start with AND not WHERE
    $params = [];
    if ($exclude_unilateral === 1) {
      $sqlWhereAnd .= " AND c.unilateral !=1 ";
    }

    [$sqlWhereAnd, $params] = getCrashesWhere($filter, $sqlWhereAnd, $params);

    $sql = <<<SQL
INSERT INTO ai_tasks (article_id, questionnaire_id, task_status)
SELECT
  a.ID,
  :questionnaire_id,
  1
FROM articles a
LEFT JOIN crashes c ON a.crashid = c.id
WHERE a.alltext IS NOT NULL AND a.alltext != ''
  $sqlWhereAnd
  AND NOT EXISTS (SELECT 1 FROM answers WHERE articleid = a.id AND questionid = :question_id
)
ORDER BY ID DESC
LIMIT :tasks_count;
SQL;

    $params[':question_id'] = $first_question_id;
    $params[':questionnaire_id'] = $questionnaire_id;
    $params[':tasks_count'] = $tasks_count;

    $this->database->execute($sql, $params);

    return [];
  }

  /**
   * @throws Exception
   */
  private function deleteTask(): array {
    $task_id = (int)$this->input['task_id'];
    if (! is_int($task_id)) {
      throw new \Exception("Invalid task_id");
    }

    $sql = "DELETE FROM ai_tasks WHERE id = :id";
    $params = [
      'id' => $task_id,
    ];
    $this->database->execute($sql, $params);

    return [];
  }

  /**
   * @throws Exception
   */
  private function getResearch_UVA_2026(): array {
    $filter = $this->input['filter'];

    $stats = [];

    $year = $filter['period'];

    $sqlWhere = '';
    $params = [];

    addPeriodWhereSql($sqlWhere, $params, $filter);

    $sql = "SELECT COUNT(*) FROM crashes c $sqlWhere;";
    $stats['crashes'] = $this->database->fetchSingleValue($sql, $params);

    $sql = "SELECT COUNT(*) FROM articles a LEFT JOIN crashes c ON a.crashid = c.id $sqlWhere;";
    $stats['articles'] = $this->database->fetchSingleValue($sql, $params);

    // Load questionnaire results
    $filter['public'] = ! $this->user->admin;

    require_once 'Research.php';
    $questionnaire = Research::loadQuestionnaireResults($filter);

    return [
      'stats' => $stats,
      'user' => $this->user->info(),
      'questionnaire' => $questionnaire,
    ];
  }

  private function getTaskWorkerStatus(): array {
    $taskWorkerFile = __DIR__ . '/../workers/task_worker.php';
    $statusFile = __DIR__ . '/../workers/task_worker_status.json';

    require_once $taskWorkerFile;

    $response = [
      'running' => TaskWorker::isRunning(),
    ];

    if (file_exists($statusFile)) {
      $fileContent = @file_get_contents($statusFile);
      if ($fileContent !== false) {
        $statusResult = json_decode($fileContent, true);
        if (is_array($statusResult)) {
          $response = array_merge($response, $statusResult);
        }
      }
    }

    $task_ids = $this->input['task_ids']?? [];
    $response['tasks'] = [];
    if (count($task_ids) > 0) {
      $placeholders = implode(',', array_fill(0, count($task_ids), '?'));

      $sql = <<<SQL
SELECT 
  id, 
  task_status, 
  processed_at, 
  COALESCE(ai_model, '') AS ai_model, 
  COALESCE(info, '') AS info 
FROM ai_tasks 
WHERE id IN ($placeholders);
SQL;
      $tasks = $this->database->fetchAll($sql, array_values($task_ids));

      foreach ($tasks as &$task) {
        $task['processed_at'] = datetimeDBToISO8601($task['processed_at'] ?? '');
      }

      $response['tasks'] = $tasks;
    }

    return $response;
  }


  private function loadArticleFromDatabase($articleId, $command=''): false|stdClass {

    $params = [];
    if ($command === 'latest') {
      $sql = "SELECT id, crashid, title, alltext AS text, DATE(publishedtime) AS date FROM articles WHERE alltext IS NOT NULL AND TRIM(alltext) != '' ORDER BY id DESC LIMIT 1";
    } else if ($command === 'next') {
      $sql = "SELECT id, crashid, title, alltext AS text, DATE(publishedtime) AS date FROM articles WHERE alltext IS NOT NULL AND TRIM(alltext) != '' AND id < :id ORDER BY id DESC LIMIT 1";
      $params[':id'] = $articleId;
    } else if ($command === 'back') {
      $sql = "SELECT id, crashid, title, alltext AS text, DATE(publishedtime) AS date FROM articles WHERE alltext IS NOT NULL AND TRIM(alltext) != '' AND id > :id ORDER BY id LIMIT 1";
      $params[':id'] = $articleId;
    } else {
      $sql = "SELECT id, crashid, title, alltext AS text, DATE(publishedtime) AS date FROM articles WHERE id=:id";
      $params[':id'] = $articleId;
    }

    return $this->database->fetchObject($sql, $params);
  }

  /**
   * @throws Exception
   */
  private function aiRunPrompt(): array {

    $model = $this->input['model'];
    $userPrompt = $this->input['userPrompt'];
    $systemPrompt = $this->input['systemPrompt'];
    $responseFormat = $this->input['responseFormat'];

    if (is_numeric($this->input['articleId'])) {
      $articleId = intval($this->input['articleId']);

      $article = $this->loadArticleFromDatabase($articleId);
      $userPrompt = replaceArticleTags($userPrompt, $article);

      // Load questionnaires if needed
      if (str_contains($userPrompt, '[questionnaires]')) {
        $questionnaires = $this->database->loadQuestionnairesData(true);
        $userPrompt = replaceAI_QuestionnaireTags($userPrompt, $questionnaires);
      }
    }

    require_once '../general/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();
    return $openrouter->chatWithMeta($userPrompt, $systemPrompt, $model, $responseFormat);
  }

  private function mayEditPrompt($promptId): bool {
    // Only admin can edit other users' queries
    if ($this->user->admin) return true;

    $SQL = "SELECT 1 FROM ai_prompts WHERE id=:id AND user_id=:user_id;";
    $params = [
      'id' => $promptId,
      'user_id' => $this->user->id,
    ];
    $dbResult = $this->database->fetchSingleValue($SQL, $params);

    return $dbResult !== false;
  }

  /**
   * @throws Exception
   */
  private function aiSavePrompt(): array {

    $result = [];

    if (empty($this->input['articleId'])) $this->input['articleId'] = null;

    if (! empty ($this->input['id'])) {

      if (! $this->mayEditPrompt($this->input['id'])) throw new \Exception("You cannot save somebody else's prompt");

      $SQL = <<<SQL
UPDATE ai_prompts SET 
model_id = :model_id,
user_prompt = :user_prompt,
system_prompt = :system_prompt,
response_format = :response_format,
function = :function,
article_id = :article_id
WHERE id = :id;                                                                                              ;                                                                                              
SQL;

      $params = [
        ':id' => $this->input['id'],
        ':model_id' => $this->input['modelId'],
        ':user_prompt' => $this->input['userPrompt'],
        ':system_prompt' => $this->input['systemPrompt'],
        ':response_format' => $this->input['responseFormat'],
        ':function' => $this->input['function'],
        ':article_id' => $this->input['articleId'],
      ];

      $dbResponse = $this->database->execute($SQL, $params);

    } else {
      if (! $this->user->isModerator()) throw new \Exception("You have no permission to save a prompt");

      $SQL = <<<SQL
INSERT INTO ai_prompts (user_id, model_id, user_prompt, system_prompt, response_format, function, article_id) 
VALUES (:user_id, :model_id, :user_prompt, :system_prompt, :response_format, :function, :article_id);                                                                                              ;                                                                                              
SQL;

      $params = [
        ':user_id' => $this->user->id,
        ':model_id' => $this->input['modelId'],
        ':user_prompt' => $this->input['userPrompt'],
        ':system_prompt' => $this->input['systemPrompt'],
        ':response_format' => $this->input['responseFormat'],
        ':function' => $this->input['function'],
        ':article_id' => $this->input['articleId'],
      ];

      $dbResponse = $this->database->execute($SQL, $params);
      $result['id'] = $this->database->lastInsertID();
    }

    if ($dbResponse === false) throw new \Exception('Internal error: Can not update prompt');

    return $result;
  }

  /**
   * @throws Exception
   */
  private function aiDeletePrompt(): array {

    // AI prompt with a function cannot be deleted
    $sql = "SELECT function FROM ai_prompts WHERE id=:id;";
    $params = [':id' => $this->input['id']];
    $function = $this->database->fetchSingleValue($sql, $params);
    if (! empty($function)) throw new \Exception("Cannot delete an AI prompt with a function. Remove the function first.");

    if (! $this->mayEditPrompt($this->input['id'])) throw new \Exception("You cannot delete somebody else's prompt");

    $SQL = "DELETE FROM ai_prompts WHERE id=:id;";

    $this->database->execute($SQL, ['id' => $this->input['id']]);

    return [];
  }

  private function aiGetPromptList(): array {
    $sql = <<<SQL
SELECT 
q.id, 
q.model_id, 
q.user_prompt, 
COALESCE(q.function, '') AS function,  
q.system_prompt, 
q.article_id, 
q.response_format,
CONCAT(u.firstname, ' ', u.lastname) AS user
FROM ai_prompts q
LEFT JOIN users u ON u.id = q.user_id;
SQL;

    $queries = $this->database->fetchAll($sql);

    return [
      'queries' => $queries,
    ];
  }

  private function loadArticle(): array {
    $articleId = $this->input['id'];
    $command = $this->input['command']?? null;

    $article = $this->loadArticleFromDatabase($articleId, $command);
    if ($article === false) throw new \Exception('Article not found');

    return [
      'article' => $article,
    ];
  }

  /**
   * @throws Exception
   */
  private function aiGetGenerationInfo(): array {
    $generationId = $this->input['id'];

    require_once '../general/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();
    $generation = $openrouter->getGenerationInfo($generationId);

    return [
      'generation' => $generation,
      'credits' => $openrouter->getCredits(),
    ];
  }

  /**
   * @throws Exception
   */
  private function getOpenRouterModels(): array {
    require_once '../general/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();

    return $openrouter->getAllModels();
  }

  private function aiGetAvailableModels(): array {;
    return ['models' => $this->getOpenRouterModels()];
  }

  private function selectAiModel(): array {
    $modelId = $this->input['model_id'];

    $modelsAvailable = $this->getOpenRouterModels();

    $models = array_filter($modelsAvailable, fn($m) => $m['id'] === $modelId);

    if (count($models) === 0) throw new \Exception('Model ID not found: ' . $modelId);

    $model = reset($models);

    $SQL = <<<SQL
INSERT INTO ai_models (id, name, description, context_length, created, cost_input, cost_output, structured_outputs) 
VALUES (:id, :name, :description, :context_length, :created, :cost_input, :cost_output, :structured_outputs);
SQL;

    $params = [
      ':id' => $model['id'],
      ':name' => substr($model['name'], 0, 100),
      ':description' => substr($model['description'], 0, 1000),
      ':context_length' => $model['context_length'],
      ':created' => $model['created'],
      ':cost_input' => $model['cost_input'],
      ':cost_output' => $model['cost_output'],
      ':structured_outputs' => $model['structured_outputs'] === true? 1 : 0,
    ];

    $this->database->execute($SQL, $params);

    return [];
  }

  private function updateModelsDatabase(): array {
    $models = $this->getOpenRouterModels();

    $sql = <<<SQL
UPDATE ai_models SET 
name = :name,
description = :description,
context_length = :context_length,
created = :created,
cost_input = :cost_input,
cost_output = :cost_output,
structured_outputs = :structured_outputs
WHERE id = :id;
SQL;
    $prompt = $this->database->prepare($sql);

    foreach ($models as $model) {
      $params = [
        ':id' => $model['id'],
        ':name' => $model['name'],
        ':description' => $model['description'],
        ':context_length' => $model['context_length'],
        ':created' => $model['created'],
        ':cost_input' => $model['cost_input'],
        ':cost_output' => $model['cost_output'],
        ':structured_outputs' => (int)$model['structured_outputs'],
      ];

      $this->database->executePrepared($prompt, $params);
    }

    return [];
  }

  /**
   * @throws Exception
   */
  private function removeAiModel(): array {
    // Check if the model is still used in prompts
    $modelId = $this->input['model_id'];

    $sql = "SELECT COUNT(*) FROM ai_prompts WHERE model_id=:model_id;";
    $count = $this->database->fetchSingleValue($sql, [':model_id' => $modelId]);

    if ($count > 0) throw new \Exception("Model is still used in $count prompts. Remove it from existing prompts first.");

    $SQL = "DELETE FROM ai_models WHERE id=:model_id;";
    $this->database->execute($SQL, ['model_id' => $modelId]);

    return [];
  }

  private function getAIModels(): array {
    $sql = "SELECT * FROM ai_models ORDER BY created DESC;";
    $models = $GLOBALS['database']->fetchAll($sql);

    foreach ($models as &$model) {
      $model['cost_input'] = (float)$model['cost_input'];
      $model['cost_output'] = (float)$model['cost_output'];
    }

    return $models;
  }

  /**
   * @throws Exception
   */
  private function aiInit(): array {
    require_once '../general/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();
    $models = $this->getAIModels();
    $credits = $openrouter->getCredits();

    return [
      'models' => $models,
      'credits' => $credits,
    ];
  }

}