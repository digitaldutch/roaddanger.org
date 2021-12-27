<?php

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

require_once '../initialize.php';

global $database;
global $user;

if (! $user->isModerator()) {
  $result = ['ok' => false, 'error' => 'Only admins and moderators allowed', 'user' => $user->info()];
  die(json_encode($result));
}

$function = $_REQUEST['function'];

function getBechdelResult($article) {
  foreach ($article['questions'] as $questionAnswer) {
    if      ($questionAnswer === 0)    return 0;
    else if ($questionAnswer === 2)    return 2;
    else if ($questionAnswer === null) return null;
  }
  return 1;
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
    $data = json_decode(file_get_contents('php://input'));

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

    $params = [':questionnaire_id' => $data->filters->questionnaireId];
    $questionnaire = $database->fetch($sql, $params);

    $result['questionnaire'] = $questionnaire;

    // Get questionnaire answers
    if ($questionnaire['type'] === QuestionnaireType::standard) {
      $sql = <<<SQL
SELECT
  a.questionid AS id,
  q.text,
  a.answer,
  count(a.answer) AS aantal
FROM answers a
LEFT JOIN questionnaire_questions qq ON qq.question_id = a.questionid
LEFT JOIN questions q ON a.questionid = q.id
WHERE qq.questionnaire_id=:questionnaire_id
GROUP BY qq.question_order, answer
ORDER BY qq.question_order
SQL;

      $params = [':questionnaire_id' => $data->filters->questionnaireId];
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
//    $sql = <<<SQL
//SELECT
//  qq.question_id,
//  q.text
//FROM questionnaire_questions qq
//LEFT JOIN questions q ON q.id = qq.question_id
//WHERE qq.questionnaire_id=:questionnaire_id
//ORDER BY qq.question_order
//SQL;
//    $questions = $database->fetchAll($sql, $params);
//    $questionnaire['questions'] = $questions;

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
  GROUP_CONCAT(a.questionid ORDER BY qq.question_order) AS question_ids,
  GROUP_CONCAT(a.answer ORDER BY qq.question_order) AS answers
FROM answers a
  LEFT JOIN articles ar ON ar.id = a.articleid
  LEFT JOIN crashes c ON ar.crashid = c.id
  LEFT JOIN questionnaire_questions qq ON  qq.question_id = a.questionid
WHERE a.questionid in (select question_id from questionnaire_questions where questionnaire_id=:questionnaire_id)
AND c.countryid = (select country_id from questionnaires where id=1)
GROUP BY a.articleid
ORDER BY a.articleid;
SQL;

      $articles = [];
      $bechdelResults = ['yes' => 0, 'no' => 0, 'not_determinable' => 0,];
      $statement = $database->prepare($sql);
      $statement->execute([':questionnaire_id' => $data->filters->questionnaireId]);
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

        $articleResult = getBechdelResult($article);

        switch ($articleResult) {
          case 0: {$bechdelResults['no'] += 1; break;}
          case 1: {$bechdelResults['yes'] += 1; break;}
          case 2: {$bechdelResults['not_determinable'] += 1; break;}
        }

        $article['bechdelResult'] = $articleResult;

        $articles[] = $article;
      }
      $result['bechdelResults'] = $bechdelResults;
    }

    $result['questionnaire'] = $questionnaire;
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else echo json_encode(['ok' => false, 'error' => 'Function not found']);

