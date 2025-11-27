<?php

class ResearchHandler {

  private Database $database;
  private User $user;

  public function __construct(Database $database, User $user) {
    $this->database = $database;
    $this->user = $user;
  }

  public function handleRequest($command): void {
    try {

      // Public functions
      $response = match ($command) {
        'loadQuestionnaireResults' => $this->loadQuestionnaireResults(),
        default => null,
      };

      // The stuff below is for moderators only
      if (! $response  && $this->user->isModerator()) {
        $response = match ($command) {
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
          'loadArticlesUnanswered' => $this->loadArticlesUnanswered(),
          default => null,
        };
      }

      // The stuff below is only for administrators
      if (! $response && $this->user->admin) {
        $response = match($command) {
          'loadQuestionnaires' => $this->loadQuestionnaires(),
          'saveQuestion' => $this->saveQuestion(),
          'deleteQuestion' => $this->deleteQuestion(),
          'saveQuestionsOrder' => $this->saveQuestionsOrder(),
          'saveQuestionnaire' => $this->saveQuestionnaire(),
          'deleteQuestionnaire' => $this->deleteQuestionnaire(),
          default => null,
        };
      }

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }

  private function respondWithSucces(string $response): void {
    header('Content-Type: application/json');
    echo $response;
  }

  private function respondWithError(string $error): void {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => $error]);
  }

  private function loadQuestionnaires():string {

    try{

      $sql = <<<SQL
SELECT 
  id,
  title, 
  type,
  country_id,
  active,
  public
FROM questionnaires;
SQL;

      $questionnaires = $this->database->fetchAll($sql);

      $sql = <<<SQL
SELECT 
  id,
  text, 
  explanation 
FROM questions 
ORDER BY question_order;
SQL;

      $questions = $this->database->fetchAll($sql);

      $result = ['ok' => true, 'questionnaires' => $questionnaires, 'questions' => $questions];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function saveQuestion(): string {
    try{
      $question = json_decode(file_get_contents('php://input'));

      $isNew = (empty($question->id));
      if ($isNew) {
        $sql = "INSERT INTO questions (text, explanation) VALUES (:text, :explanation);";

        $params = [
          ':text'        => $question->text,
          ':explanation' => $question->explanation,
        ];
        $this->database->execute($sql, $params);
        $question->id = (int)$this->database->lastInsertID();

      } else {
        $sql = "UPDATE questions SET text=:text, explanation=:explanation WHERE id=:id;";

        $params = [
          ':id'          => $question->id,
          ':text'        => $question->text,
          ':explanation' => $question->explanation,
        ];

        $this->database->execute($sql, $params);
      }

      $result = ['ok' => true, 'id' => $question->id];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function deleteQuestion(): string {

    try{
      $question = json_decode(file_get_contents('php://input'));

      $sql = "SELECT questionnaire_id FROM questionnaire_questions WHERE question_id=:question_id;";
      $params = [':question_id' => $question->id];
      $dbIds = $this->database->fetchAll($sql, $params);
      $ids = [];
      foreach ($dbIds as $dbId) $ids[] = $dbId['questionnaire_id'];

      if (count($ids) > 0) {
        $idsString = implode(", ", $ids);
        throw new \Exception('Cannot delete question. Question is still use in questionnaires: ' . $idsString);
      }

      $sql = "DELETE FROM questions WHERE id=:id;";

      $params = [':id' => $question->id];
      $this->database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function saveQuestionsOrder(): string {
    try{
      $ids = json_decode(file_get_contents('php://input'));

      $sql = "UPDATE questions SET question_order=:question_order WHERE id=:id";
      $statement = $this->database->prepare($sql);
      foreach ($ids as $order=>$id){
        $params = [':id' => $id, ':question_order' => $order];
        $this->database->executePrepared($statement,$params);
      }

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function saveQuestionnaire(): string {
    try {
      $questionnaire = json_decode(file_get_contents('php://input'));

      $isNew = (empty($questionnaire->id));
      if ($isNew) {
        $sql = "INSERT INTO questionnaires (title, type, country_id, active, public) VALUES (:title, :type, :country_id, :active, :public);";

        $params = [
          ':title'      => $questionnaire->title,
          ':type'       => $questionnaire->type,
          ':country_id' => $questionnaire->countryId,
          ':active'     => intval($questionnaire->active),
          ':public'     => intval($questionnaire->public),
        ];
        $this->database->execute($sql, $params);
        $questionnaire->id = (int)$this->database->lastInsertID();
      } else {
        $sql = "UPDATE questionnaires SET title=:title, type=:type, country_id=:country_id, active=:active, public=:public WHERE id=:id;";

        $params = [
          ':id'         => $questionnaire->id,
          ':title'      => $questionnaire->title,
          ':type'       => $questionnaire->type,
          ':country_id' => $questionnaire->countryId,
          ':active'     => intval($questionnaire->active),
          ':public'     => intval($questionnaire->public),
        ];
        $this->database->execute($sql, $params);
      }

      // Save questionnaire questions
      $sql = "DELETE FROM questionnaire_questions WHERE questionnaire_id=:questionnaire_id;";
      $params = [':questionnaire_id' => $questionnaire->id];
      $this->database->execute($sql, $params);

      $sql = "INSERT INTO questionnaire_questions (questionnaire_id, question_id, question_order) VALUES (:questionnaire_id, :question_id, :question_order);";
      $statement = $this->database->prepare($sql);
      $order = 1;
      foreach ($questionnaire->questionIds as $questionId){
        $params = [':questionnaire_id' => $questionnaire->id, ':question_id' => $questionId, ':question_order' => $order];
        $this->database->executePrepared($statement, $params);
        $order += 1;
      }

      $result = ['ok' => true, 'id' => $questionnaire->id];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function deleteQuestionnaire(): string {
    try {
      $questionnaire = json_decode(file_get_contents('php://input'));

      $sql    = "DELETE FROM questionnaires WHERE id=:id;";
      $params = [':id' => $questionnaire->id];

      $this->database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }
  private function loadQuestionnaireResults(): string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      $filter = $data['filter'];
      $filter['public'] = ! $this->user->admin;
      $articleFilter = $data['articleFilter'];
      $group = $data['group']?? '';

      require_once 'Research.php';
      $result = Research::loadQuestionnaireResults($filter, $group, $articleFilter);

    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function loadArticlesUnanswered(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $filter = $data['filter'];

      // Get active questionnaires
      $sql = <<<SQL
SELECT
id,
title,
type
FROM questionnaires
WHERE active = 1
ORDER BY id;
SQL;

      $questionnaires = $this->database->fetchAll($sql);

      // Sort on dead=3, injured=2, unknown=0, uninjured=1
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

        $SQLJoin          = '';
        $SQLWhereAnd      = ' ';
        $joinPersonsTable = false;

        addHealthWhereSql($SQLWhereAnd, $joinPersonsTable, $filter);

        if (isset($filter['persons']) && (count($filter['persons'])) > 0) $joinPersonsTable = true;

        if (isset($filter['child']) && ($filter['child'] === 1)){
          $joinPersonsTable = true;
          addSQLWhere($SQLWhereAnd, "cp.child=1 ");
        }

        if (isset($filter['noUnilateral']) && ($filter['noUnilateral'] === 1)){
          addSQLWhere($SQLWhereAnd, " c.unilateral !=1 ");
        }

        addPersonsWhereSql($SQLWhereAnd, $SQLJoin, $filter['persons']);

        $SQLWhereAnd .= $this->user->countryId === 'UN'? '' : " AND c.countryid='" . $this->user->countryId . "'";

        if ($joinPersonsTable) $SQLJoin .= ' JOIN crashpersons cp on c.id = cp.crashid ';

        /** @noinspection SqlIdentifier */
        $sql = <<<SQL
SELECT
a.id,
a.title,
a.url,
a.sitename,
c.date AS crash_date,
c.unilateral AS crash_unilateral,
c.countryid AS crash_countryid,
c.id AS crashid
FROM articles a
LEFT JOIN crashes c ON a.crashid = c.id
$SQLJoin
WHERE ((alltext IS NOT NULL) AND (alltext != ''))
$SQLWhereAnd
AND NOT EXISTS(SELECT 1 FROM answers WHERE articleid = a.id)
ORDER BY c.date DESC
LIMIT 50;
SQL;
      }

      $articles = $this->database->fetchAll($sql);

      $crashes = [];
      foreach ($articles as $article) {
        $crash = [
          'id'         => $article['crashid'],
          'date'       => $article['crash_date'],
          'countryid'  => $article['crash_countryid'],
          'unilateral' => $article['crash_unilateral'] === 1,
        ];

        // Load crash persons
        $crash['persons'] = $this->database->fetchAllPrepared($dBStatementCrashPersons, ['crashid' => $crash['id']]);

        $crashes[] = $crash;
      }

      $result = [
        'ok'      => true,
        'crashes'  => $crashes,
        'articles' => $articles,
      ];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
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

  private function aiRunPrompt(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      $model = $data['model'];
      $userPrompt = $data['userPrompt'];
      $systemPrompt = $data['systemPrompt'];
      $responseFormat = $data['responseFormat'];

      if (is_numeric($data['articleId'])) {
        $articleId = intval($data['articleId']);

        $article = $this->loadArticleFromDatabase($articleId);

        $userPrompt = replaceArticleTags($userPrompt, $article);
      }

      require_once '../general/OpenRouterAIClient.php';

      $openrouter = new OpenRouterAIClient();
      $result = $openrouter->chatWithMeta($userPrompt, $systemPrompt, $model, $responseFormat);

      $result['ok'] = true;
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
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

  private function aiSavePrompt(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      if (empty($data['articleId'])) $data['articleId'] = null;

      if (! empty ($data['id'])) {

        if (! $this->mayEditPrompt($data['id'])) throw new \Exception("You cannot save somebody else's prompt");

        $SQL = <<<SQL
UPDATE ai_prompts SET 
  model_id = :model_id,
  user_prompt = :user_prompt,
  system_prompt = :system_prompt,
  response_format = :response_format,
  article_id = :article_id
WHERE id = :id;                                                                                              ;                                                                                              
SQL;

        $params = [
          ':id' => $data['id'],
          ':model_id' => $data['modelId'],
          ':user_prompt' => $data['userPrompt'],
          ':system_prompt' => $data['systemPrompt'],
          ':response_format' => $data['responseFormat'],
          ':article_id' => $data['articleId'],
        ];

        $dbResponse = $this->database->execute($SQL, $params);

      } else {
        if (! $this->user->isModerator()) throw new \Exception("You have no permission to save a prompt");

        $SQL = <<<SQL
INSERT INTO ai_prompts (user_id, model_id, user_prompt, system_prompt, response_format, article_id) 
VALUES (:user_id, :model_id, :user_prompt, :system_prompt, :response_format, :article_id);                                                                                              ;                                                                                              
SQL;

        $params = [
          ':user_id' => $this->user->id,
          ':model_id' => $data['modelId'],
          ':user_prompt' => $data['user_prompt'],
          ':system_prompt' => $data['systemPrompt'],
          ':response_format' => $data['responseFormat'],
          ':article_id' => $data['articleId'],
        ];

        $dbResponse = $this->database->execute($SQL, $params);
        $result['id'] = $this->database->lastInsertID();
      }
      if ($dbResponse === false) throw new \Exception('Internal error: Can not update prompt');


      $result['ok'] = true;
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function aiDeletePrompt(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      if (! $this->mayEditPrompt($data['id'])) throw new \Exception("You cannot delete somebody else's prompt");

      $SQL = "DELETE FROM ai_prompts WHERE id=:id;";

      $this->database->execute($SQL, ['id' => $data['id']]);

      $result = [
        'ok' => true,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function aiGetPromptList(): false|string {
    try {
      $SQL = <<<SQL
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

      $queries = $this->database->fetchAll($SQL);
      $result = [
        'ok' => true,
        'queries' => $queries,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function loadArticle(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $articleId = $data['id'];
      $command = $data['command']?? null;

      $article = $this->loadArticleFromDatabase($articleId, $command);
      if ($article === false) throw new \Exception('Article not found');

      $result = [
        'ok' => true,
        'article' => $article,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function aiGetGenerationInfo(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $generationId = $data['id'];

      require_once '../general/OpenRouterAIClient.php';

      $openrouter = new OpenRouterAIClient();
      $generation = $openrouter->getGenerationInfo($generationId);

      $result = [
        'ok' => true,
        'generation' => $generation,
        'credits' => $openrouter->getCredits(),
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  /**
   * @throws Exception
   */
  private function getOpenRouterModels(): array {
    require_once '../general/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();

    return $openrouter->getAllModels();
  }

  private function selectAiModel(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $modelId = $data['model_id'];

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

      $result = [
        'ok' => true,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function updateModelsDatabase(): string|false {
    try {
      $models = $this->getOpenRouterModels();

      $SQL = <<<SQL
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
      $prompt = $this->database->prepare($SQL);

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

      $result = [
        'ok' => true,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function removeAiModel(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $modelId = $data['model_id'];

      $SQL = "DELETE FROM ai_models WHERE id=:id;";

      $this->database->execute($SQL, ['id' => $modelId]);

      $result = [
        'ok' => true,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function getAIModels() {
    $sql = "SELECT * FROM ai_models ORDER BY created DESC;";
    $models = $GLOBALS['database']->fetchAll($sql);

    foreach ($models as &$model) {
      $model['cost_input'] = (float)$model['cost_input'];
      $model['cost_output'] = (float)$model['cost_output'];
    }

    return $models;
  }

  private function aiInit(): string {
    try {
      require_once '../general/OpenRouterAIClient.php';

      $openrouter = new OpenRouterAIClient();
      $models = $this->getAIModels();
      $credits = $openrouter->getCredits();

      $result = [
        'ok' => true,
        'models' => $models,
        'credits' => $credits,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function aiGetAvailableModels(): string {
    try {
      require_once '../general/OpenRouterAIClient.php';

      $models = $this->getOpenRouterModels();

      $result = [
        'ok' => true,
        'models' => $models,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

}