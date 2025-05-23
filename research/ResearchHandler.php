<?php

class ResearchHandler {

  static public function loadQuestionnaires():string {
    global $database;
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

      $questionnaires = $database->fetchAll($sql);

      $sql = <<<SQL
SELECT 
  id,
  text, 
  explanation 
FROM questions 
ORDER BY question_order;
SQL;

      $questions = $database->fetchAll($sql);

      $result = ['ok' => true, 'questionnaires' => $questionnaires, 'questions' => $questions];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  static public function saveQuestion(): string {
    global $database;

    try{
      $question = json_decode(file_get_contents('php://input'));

      $isNew = (empty($question->id));
      if ($isNew) {
        $sql = "INSERT INTO questions (text, explanation) VALUES (:text, :explanation);";

        $params = [
          ':text'        => $question->text,
          ':explanation' => $question->explanation,
        ];
        $dbResult = $database->execute($sql, $params);
        $question->id = (int)$database->lastInsertID();
      } else {
        $sql = "UPDATE questions SET text=:text, explanation=:explanation WHERE id=:id;";

        $params = [
          ':id'          => $question->id,
          ':text'        => $question->text,
          ':explanation' => $question->explanation,
        ];
        $dbResult = $database->execute($sql, $params);
      }

      $result = ['ok' => true, 'id' => $question->id];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  static public function deleteQuestion(): string {
    global $database;

    try{
      $question = json_decode(file_get_contents('php://input'));

      $sql = "SELECT questionnaire_id FROM questionnaire_questions WHERE question_id=:question_id;";
      $params = [':question_id' => $question->id];
      $dbIds = $database->fetchAll($sql, $params);
      $ids = [];
      foreach ($dbIds as $dbId) $ids[] = $dbId['questionnaire_id'];

      if (count($ids) > 0) {
        $idsString = implode(", ", $ids);
        throw new Exception('Cannot delete question. Question is still use in questionnaires: ' . $idsString);
      }

      $sql = "DELETE FROM questions WHERE id=:id;";

      $params = [':id' => $question->id];
      $dbResult = $database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  static public function saveQuestionsOrder(): string {
    global $database;
    try{
      $ids = json_decode(file_get_contents('php://input'));

      $sql = "UPDATE questions SET question_order=:question_order WHERE id=:id";
      $statement = $database->prepare($sql);
      foreach ($ids as $order=>$id){
        $params = [':id' => $id, ':question_order' => $order];
        $database->executePrepared($statement,$params);
      }

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  static public function saveQuestionnaire(): string {
    global $database;
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
        $dbResult = $database->execute($sql, $params);
        $questionnaire->id = (int)$database->lastInsertID();
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
        $dbResult = $database->execute($sql, $params);
      }

      // Save questionnaire questions
      $sql = "DELETE FROM questionnaire_questions WHERE questionnaire_id=:questionnaire_id;";
      $params = [':questionnaire_id' => $questionnaire->id];
      $database->execute($sql, $params);

      $sql = "INSERT INTO questionnaire_questions (questionnaire_id, question_id, question_order) VALUES (:questionnaire_id, :question_id, :question_order);";
      $statement = $database->prepare($sql);
      $order = 1;
      foreach ($questionnaire->questionIds as $questionId){
        $params = [':questionnaire_id' => $questionnaire->id, ':question_id' => $questionId, ':question_order' => $order];
        $database->executePrepared($statement, $params);
        $order += 1;
      }

      $result = ['ok' => true, 'id' => $questionnaire->id];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  static public function deleteQuestionnaire(): string {
    global $database;
    try {
      $questionnaire = json_decode(file_get_contents('php://input'));

      $sql    = "DELETE FROM questionnaires WHERE id=:id;";
      $params = [':id' => $questionnaire->id];
      $dbResult = $database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }
  static public function loadQuestionnaireResults(): string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      global $user;
      $filter = $data['filter'];
      $filter['public'] = ! $user->admin;
      $articleFilter = $data['articleFilter'];
      $group = $data['group']?? '';

      require_once 'Research.php';
      $result = Research::loadQuestionnaireResults($filter, $group, $articleFilter);

    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  static public function loadArticlesUnanswered() {
    global $database;
    global $user;
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

      $questionnaires = $database->fetchAll($sql);

      // Sort on dead=3, injured=2, unknown=0, unharmed=1
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

      $dBStatementCrashPersons = $database->prepare($sql);

      $articles = [];
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

        $SQLWhereAnd .= $user->countryId === 'UN'? '' : " AND c.countryid='" . $user->countryId . "'";

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

      $articles = $database->fetchAll($sql);

      $crashes = [];
      foreach ($articles as $article) {
        $crash = [
          'id'         => $article['crashid'],
          'date'       => $article['crash_date'],
          'countryid'  => $article['crash_countryid'],
          'unilateral' => $article['crash_unilateral'] === 1,
        ];

        // Load crash persons
        $crash['persons'] = [];
        $dBPersons = $database->fetchAllPrepared($dBStatementCrashPersons, ['crashid' => $crash['id']]);
        foreach ($dBPersons as $person) {
          $person['groupid']            = isset($person['groupid'])? (int)$person['groupid'] : null;
          $person['transportationmode'] = (int)$person['transportationmode'];
          $person['health']             = isset($person['health'])? (int)$person['health'] : null;
          $person['child']              = (int)$person['child'];
          $person['underinfluence']     = (int)$person['underinfluence'];
          $person['hitrun']             = (int)$person['hitrun'];

          $crash['persons'][] = $person;
        }

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

  public static function aiRunQuery(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $model = $data['model'];
      $query = $data['query'];
      $systemInstructions = $data['systemInstructions'];
      $responseFormat = $data['responseFormat'];

      require_once '../general/OpenRouterAIClient.php';

      $openrouter = new OpenRouterAIClient();
      $result = $openrouter->chatWithMeta($query, $systemInstructions, $model, $responseFormat);

      $result['ok'] = true;
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private static function mayEditQuery($queryId): bool {
    global $user;
    global $database;

    // Only admin can edit other users' queries
    if ($user->admin) return true;

    $SQL = "SELECT 1 FROM ai_queries WHERE id=:id AND user_id=:user_id;";
    $params = [
      'id' => $queryId,
      'user_id' => $user->id,
    ];
    $dbResult = $database->fetchSingleValue($SQL, $params);

    return $dbResult !== false;
  }

  public static function aiSaveQuery(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      global $user;
      global $database;

      if (! empty ($data['id'])) {

        if (! self::mayEditQuery($data['id'])) throw new Exception("You cannot save somebody else's query");

        $SQL = <<<SQL
UPDATE ai_queries SET 
  model_id = :model_id,
  query = :query,
  system_instructions = :system_instructions,
  response_format = :response_format
WHERE id = :id;                                                                                              ;                                                                                              
SQL;

        $params = [
          ':id' => $data['id'],
          ':model_id' => $data['modelId'],
          ':query' => $data['query'],
          ':system_instructions' => $data['systemInstructions'],
          ':response_format' => $data['responseFormat'],
        ];

        $dbResponse = $database->execute($SQL, $params);

      } else {
        if (! $user->isModerator()) throw new Exception("You have no permission to save a query");

        $SQL = <<<SQL
INSERT INTO ai_queries (user_id, model_id, query, system_instructions, response_format) 
VALUES (:user_id, :model_id, :query, :system_instructions, :response_format);                                                                                              
SQL;

        $params = [
          ':user_id' => $user->id,
          ':model_id' => $data['modelId'],
          ':query' => $data['query'],
          ':system_instructions' => $data['systemInstructions'],
          ':response_format' => $data['responseFormat'],
        ];

        $dbResponse = $database->execute($SQL, $params);
        $result['id'] = $database->lastInsertID();
      }
      if ($dbResponse === false) throw new Exception('Internal error: Can not update query');


      $result['ok'] = true;
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  public static function aiDeleteQuery(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      if (! self::mayEditQuery($data['id'])) throw new Exception("You cannot delete somebody else's query");

      $SQL = "DELETE FROM ai_queries WHERE id=:id;";

      global $database;
      $database->execute($SQL, ['id' => $data['id']]);;

      $result = [
        'ok' => true,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  public static function aiGetQueryList(): false|string {
    try {
      $SQL = <<<SQL
SELECT 
  q.id, 
  q.model_id, 
  q.query, 
  q.system_instructions, 
  q.response_format,
  CONCAT(u.firstname, ' ', u.lastname) AS user
FROM ai_queries q
LEFT JOIN users u ON u.id = q.user_id;
SQL;

      global $database;
      $queries = $database->fetchAll($SQL);
      $result = [
        'ok' => true,
        'queries' => $queries,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  public static function aiGetGenerationInfo(): false|string {
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
  private static function getOpenRouterModels(): array {
    require_once '../general/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();

    return $openrouter->getAllModels();
  }

  public static function selectAiModel(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $modelId = $data['model_id'];

      $modelsAvailable = self::getOpenRouterModels();

      $models = array_filter($modelsAvailable, fn($m) => $m['id'] === $modelId);

      if (count($models) === 0) throw new Exception('Model ID not found: ' . $modelId);

      $model = reset($models);

      $SQL = <<<SQL
INSERT INTO ai_models (id, name, description, context_length, created, cost_input, cost_output, structured_outputs) 
VALUES (:id, :name, :description, :context_length, :created, :cost_input, :cost_output, :structured_outputs);
SQL;

      $params = [
        ':id' => $model['id'],
        ':name' => $model['name'],
        ':description' => $model['description'],
        ':context_length' => $model['context_length'],
        ':created' => $model['created'],
        ':cost_input' => $model['cost_input'],
        ':cost_output' => $model['cost_output'],
        ':structured_outputs' => $model['structured_outputs'],
      ];

      global $database;
      $database->execute($SQL, $params);

      $result = [
        'ok' => true,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  public static function updateModelsDatabase(): string|false {

    global $database;

    try {
      $models = self::getOpenRouterModels();

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
      $query = $database->prepare($SQL);

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

        $database->executePrepared($query, $params);
      }

      $result = [
        'ok' => true,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  public static function removeAiModel(): false|string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $modelId = $data['model_id'];

      $SQL = "DELETE FROM ai_models WHERE id=:id;";

      global $database;
      $database->execute($SQL, ['id' => $modelId]);

      $result = [
        'ok' => true,
      ];

    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private static function getAIModels() {
    $sql = "SELECT * FROM ai_models ORDER BY created DESC;";
    $models = $GLOBALS['database']->fetchAll($sql);

    foreach ($models as &$model) {
      $model['cost_input'] = (float)$model['cost_input'];
      $model['cost_output'] = (float)$model['cost_output'];
    }

    return $models;
  }

  public static function aiInit(): string {
    try {
      require_once '../general/OpenRouterAIClient.php';

      $openrouter = new OpenRouterAIClient();
      $models = self::getAIModels();
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

  public static function aiGetAvailableModels(): string {
    try {
      require_once '../general/OpenRouterAIClient.php';

      $models = self::getOpenRouterModels();

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