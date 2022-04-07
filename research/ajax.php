<?php

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

require_once '../initialize.php';
require_once '../utils.php';

global $database;
global $user;

if (! $user->isModerator()) {
  $result = ['ok' => false, 'error' => 'Only admins and moderators allowed', 'user' => $user->info()];
  die(json_encode($result));
}

$function = $_REQUEST['function'];

function getBechdelResult($answers) {

  $bechdelResult = Answer::yes;

  $totalQuestionsPassed = 0;
  foreach ($answers as $answer) {
    if      ($answer === Answer::no)              {$bechdelResult = Answer::no; break;}
    else if ($answer === Answer::notDeterminable) {$bechdelResult = Answer::notDeterminable; break;}
    else if ($answer === null)                    {$bechdelResult = null; break;}
    else if (($answer === Answer::yes))           {$totalQuestionsPassed += 1;}
  }

  return ['result' => $bechdelResult, 'total_questions_passed' => $totalQuestionsPassed];
}

function passesArticleFilter($article, $articleFilter) {

  // TODO: If not questions are anwered the result should be null
//  if (! isset($article['bechdelResult']['result'])) return false;

  if ($articleFilter['questionsPassed'] === 'nd') {
    if ($article['bechdelResult']['result'] != Answer::notDeterminable) return false;
  } else {
    if ($article['bechdelResult']['result'] === Answer::notDeterminable) return false;

    // Note: != compare as filter is a string
    if ($article['bechdelResult']['total_questions_passed'] !== (int)$articleFilter['questionsPassed']) return false;
  }

  return true;
}

if ($function === 'loadQuestionnaires') {
  try{

    $sql = <<<SQL
SELECT 
  id,
  title, 
  type,
  country_id,
  active
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
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'saveQuestion') {
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
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'deleteQuestion') {
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
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'saveQuestionsOrder') {
  try{
    $ids = json_decode(file_get_contents('php://input'));

    $sql = "UPDATE questions SET question_order=:question_order WHERE id=:id";
    $statement = $database->prepare($sql);
    foreach ($ids as $order=>$id){
      $params = [':id' => $id, ':question_order' => $order];
      $database->executePrepared($params, $statement);
    }

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'savequestionnaire') {
  try{
    $questionnaire = json_decode(file_get_contents('php://input'));

    $isNew = (empty($questionnaire->id));
    if ($isNew) {
      $sql = "INSERT INTO questionnaires (title, type, country_id, active) VALUES (:title, :type, :country_id, :active);";

      $params = [
        ':title'      => $questionnaire->title,
        ':type'       => $questionnaire->type,
        ':country_id' => $questionnaire->countryId,
        ':active'     => $questionnaire->active,
      ];
      $dbResult = $database->execute($sql, $params);
      $questionnaire->id = (int)$database->lastInsertID();
    } else {
      $sql = "UPDATE questionnaires SET title=:title, type=:type, country_id=:country_id, active=:active WHERE id=:id;";

      $params = [
        ':id'         => $questionnaire->id,
        ':title'      => $questionnaire->title,
        ':type'       => $questionnaire->type,
        ':country_id' => $questionnaire->countryId,
        ':active'     => $questionnaire->active,
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
      $database->executePrepared($params, $statement);
      $order += 1;
    }

    $result = ['ok' => true, 'id' => $questionnaire->id];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'deleteQuestionnaire') {
  try{
    $questionnaire = json_decode(file_get_contents('php://input'));

    $sql    = "DELETE FROM questionnaires WHERE id=:id;";
    $params = [':id' => $questionnaire->id];
    $dbResult = $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'loadQuestionnaireResults') {
  try{
    $data = json_decode(file_get_contents('php://input'), true);

    $filter         = $data['filter'];
    $articleFilter  = $data['articleFilter'];
    $group          = $data['group']?? '';
    $bechdelResults = null;

    $result = ['ok' => true];

    // Get questionnaire info
    $sql = <<<SQL
SELECT
  q.title,
  q.country_id,
  c.name AS country,
  q.type
FROM questionnaires q
LEFT JOIN countries c ON q.country_id = c.id
WHERE q.id=:questionnaire_id
SQL;

    $params = [':questionnaire_id' => $filter['questionnaireId']];
    $questionnaire = $database->fetch($sql, $params);

    $result['questionnaire'] = $questionnaire;

    $SQLJoin          = '';
    $SQLWhereAnd      = ' ';
    $joinPersonsTable = false;

    addHealthWhereSql($SQLWhereAnd, $joinPersonsTable, $filter);

    if (isset($filter['persons']) && (count($filter['persons'])) > 0) $joinPersonsTable = true;

    if (! empty($filter['year'])){
      addSQLWhere($SQLWhereAnd, 'YEAR(c.date)=' . intval($filter['year']));
    }

    if (isset($filter['child']) && ($filter['child'] === 1)){
      $joinPersonsTable = true;
      addSQLWhere($SQLWhereAnd, "cp.child=1 ");
    }

    if (isset($filter['noUnilateral']) && ($filter['noUnilateral'] === 1)){
      addSQLWhere($SQLWhereAnd, " c.unilateral !=1 ");
    }

    if ($joinPersonsTable) $SQLJoin .= ' JOIN crashpersons cp on c.id = cp.crashid ';

    addPersonsWhereSql($SQLWhereAnd, $SQLJoin, $filter['persons']);

    // Get questionnaire answers
    if ($questionnaire['type'] === QuestionnaireType::standard) {
      $sql = <<<SQL
SELECT
  a.questionid AS id,
  q.text,
  a.answer,
  count(a.answer) AS aantal
FROM answers a
  LEFT JOIN articles ar                ON ar.id = a.articleid
  LEFT JOIN crashes c                  ON ar.crashid = c.id
  LEFT JOIN questionnaire_questions qq ON qq.question_id = a.questionid
  LEFT JOIN questions q                ON a.questionid = q.id
  $SQLJoin
WHERE qq.questionnaire_id=:questionnaire_id
  $SQLWhereAnd
GROUP BY qq.question_order, answer
ORDER BY qq.question_order
SQL;

      $params = [':questionnaire_id' => $data['filter']['questionnaireId']];
      $dbQuestions = $database->fetchAllGroup($sql, $params);

      $questions = [];
      foreach ($dbQuestions as $questionId => $dbQuestion) {
        $questions[] = [
          'question_id'      => $questionId,
          'question'         => $dbQuestion[0]['text'],
          'no'               => $dbQuestion[0]['aantal'] ?? 0,
          'yes'              => $dbQuestion[1]['aantal'] ?? 0,
          'not_determinable' => $dbQuestion[2]['aantal'] ?? 0,
        ];
      }
      $result['questions'] = $questions;

    } else {
      // Bechdel type

      // Get questionnaire questions
      $sql = <<<SQL
SELECT
  q.id,
  q.text
FROM questionnaire_questions qq
LEFT JOIN questions q ON q.id = qq.question_id
WHERE qq.questionnaire_id=:questionnaire_id
ORDER BY qq.question_order
SQL;
      $questionnaire['questions'] = $database->fetchAll($sql, $params);

      $sql = <<<SQL
SELECT
  ar.crashid,
  a.articleid,
  ar.sitename AS source,
  YEAR(c.date) AS crash_year,
  ar.sitename AS source,
  GROUP_CONCAT(a.questionid ORDER BY qq.question_order) AS question_ids,
  GROUP_CONCAT(a.answer     ORDER BY qq.question_order) AS answers
FROM answers a
  LEFT JOIN articles ar                ON ar.id = a.articleid
  LEFT JOIN crashes c                  ON ar.crashid = c.id
  LEFT JOIN questionnaire_questions qq ON qq.question_id = a.questionid
  $SQLJoin
WHERE a.questionid in (SELECT question_id FROM questionnaire_questions WHERE questionnaire_id=:questionnaire_id)
  AND c.countryid = (SELECT country_id FROM questionnaires WHERE id=1)
  $SQLWhereAnd
GROUP BY a.articleid
ORDER BY a.articleid;
SQL;

      function getInitBechdelResults($questionCount) {
        $results = [
          'yes'                    => 0,
          'no'                     => 0,
          'not_determinable'       => 0,
          'total_articles'         => 0,
          'total_questions_passed' => [],
        ];

        for ($i=0; $i<=count($questionCount); $i++) {$results['total_questions_passed'][$i] = 0;};

        return $results;
      }

      $articles = [];

      $statement = $database->prepare($sql);
      $statement->execute([':questionnaire_id' => $data['filter']['questionnaireId']]);
      while ($article = $statement->fetch(PDO::FETCH_ASSOC)) {

        // Format and clean up article questions and answers data
        $articleQuestionIds = explode(',', $article['question_ids']);
        $articleAnswers     = explode(',', $article['answers']);

        $article['questions'] = [];
        foreach ($questionnaire['questions'] as $question) {
          $index  = array_search($question['id'], $articleQuestionIds);
          $answer = $index === false? null : (int)$articleAnswers[$index];
          $article['questions'][$question['id']] = $answer;
        }

        unset($article['question_ids']);
        unset($article['answers']);

        $articleBechdel = getBechdelResult($article['questions']);

        switch ($group) {

          case 'year': {
            $bechdelResultsGroup = &$bechdelResults[$article['crash_year']];

            if (! isset($bechdelResultsGroup)) $bechdelResultsGroup = getInitBechdelResults($questionnaire['questions']);

            break;
          }

          case 'source': {
            $bechdelResultsGroup = &$bechdelResults[$article['source']];

            if (! isset($bechdelResultsGroup)) $bechdelResultsGroup = getInitBechdelResults($questionnaire['questions']);

            break;
          }

          default: {
            $bechdelResultsGroup = &$bechdelResults;

            if (! isset($bechdelResultsGroup)) $bechdelResultsGroup = getInitBechdelResults($questionnaire['questions']);
          }
        }

        if ($articleBechdel['result'] !== null) {
          switch ($articleBechdel['result']) {

            case Answer::no: {
              $bechdelResultsGroup['no'] += 1;
              $bechdelResultsGroup['total_articles'] += 1;
              $bechdelResultsGroup['total_questions_passed'][$articleBechdel['total_questions_passed']] += 1;
              break;
            }

            case Answer::yes: {
              $bechdelResultsGroup['yes'] += 1;
              $bechdelResultsGroup['total_articles'] += 1;
              $bechdelResultsGroup['total_questions_passed'][$articleBechdel['total_questions_passed']] += 1;
              break;
            }

            case Answer::notDeterminable: {
              $bechdelResultsGroup['not_determinable'] += 1;
              break;
            }

            default: throw new Exception('Internal error: Unknown Bechdel result');
          }

          if ($articleFilter['getArticles']) {
            $article['bechdelResult'] = $articleBechdel;

            if (passesArticleFilter($article, $articleFilter)) $articles[] = $article;
          }
        }

      }

      if ($group === 'year') {
        $resultsArray = [];
        foreach ($bechdelResults as $year => $bechdelResult) {
          $bechdelResult['year'] = $year;
          $resultsArray[] = $bechdelResult;
        }
        $result['bechdelResults'] = $resultsArray;
      } else if ($group === 'source') {
        $resultsArray = [];
        foreach ($bechdelResults as $source => $bechdelResult) {
          $bechdelResult['source'] = $source;
          $resultsArray[] = $bechdelResult;
        }
        $result['bechdelResults'] = $resultsArray;
      } else $result['bechdelResults'] = $bechdelResults;
    }

    if ($articleFilter['getArticles']) {
      $result = [
        'ok'       => true,
        'articles' => array_slice($articles, $articleFilter['offset'], 1000),
      ];
    } else $result['questionnaire'] = $questionnaire;

  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function === 'loadArticlesUnanswered') {

  try {
    $data = json_decode(file_get_contents('php://input'), true);
    $filter = $data['filter'];

    // Get active questionnaires
    $SQLWhereCountry = $user->countryId === 'UN'? '' : " AND country_id='" . $user->countryId . "'";
    $sql = <<<SQL
SELECT
  id,
  title,
  type
FROM questionnaires
WHERE active = 1
$SQLWhereCountry
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

    $DbStatementCrashPersons = $database->prepare($sql);

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

      /** @noinspection SqlResolve */
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
      $DBPersons = $database->fetchAllPrepared($DbStatementCrashPersons, ['crashid' => $crash['id']]);
      foreach ($DBPersons as $person) {
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
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else echo json_encode(['ok' => false, 'error' => 'Function not found']);

