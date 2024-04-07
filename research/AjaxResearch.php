<?php

class AjaxResearch {

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

}