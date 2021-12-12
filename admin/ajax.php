<?php

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

require_once '../initialize.php';

global $database;
global $user;

// Only admins allowed
if (! $user->admin) {
  $result = ['ok' => false, 'error' => 'Mens is geen beheerder', 'user' => $user->info()];
  die(json_encode($result));
}

$function = $_REQUEST['function'];

if ($function === 'loadUsers') {
  try {
    $offset = (int)getRequest('offset',0);
    $count  = (int)getRequest('count', 100);

    $sql = <<<SQL
SELECT
  id,
  CONCAT(firstname, ' ', lastname) AS name,
  firstname,
  lastname,
  lastactive,
  email,
  permission
FROM users
ORDER BY lastactive DESC
LIMIT $offset, $count
SQL;

    $users = [];
    $users = $database->fetchAll($sql);
    foreach ($users as &$dbUser) {
      $dbUser['id']         = (int)$dbUser['id'];
      $dbUser['permission'] = (int)$dbUser['permission'];
      $dbUser['lastactive'] = datetimeDBToISO8601($dbUser['lastactive']);
    }

    $result = ['ok' => true, 'users' => $users];
  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function === 'saveUser') {
  try{
    $user = json_decode(file_get_contents('php://input'), true);

    $sql = <<<SQL
    UPDATE users SET
      email       = :email,
      firstname   = :firstname,
      lastname    = :lastname,
      permission  = :permission                    
    WHERE id=:id;
SQL;
    $params = [
      ':email'       => $user['email'],
      ':firstname'   => $user['firstname'],
      ':lastname'    => $user['lastname'],
      ':permission'  => $user['permission'],
      ':id'          => $user['id'],
    ];
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'deleteUser') {
  try{
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql = "DELETE FROM users WHERE id=:id;";
      $params = [':id' => $id];

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Kan mens niet verwijderen.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'saveNewTranslation') {
  try{
    $data = json_decode(file_get_contents('php://input'), true);

    $sql          = "SELECT translations FROM languages WHERE id='en'";
    $translations = json_decode($database->fetchSingleValue($sql), true);

    $translations[strtolower($data['id'])] = strtolower(trim($data['english']));

    $sql    = "UPDATE languages SET translations =:translations WHERE id='en'";
    $params = [':translations' => json_encode($translations)];
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'deleteTranslation') {
  try{
    $data = json_decode(file_get_contents('php://input'), true);

    $sql          = "SELECT translations FROM languages WHERE id='en'";
    $translations = json_decode($database->fetchSingleValue($sql), true);

    $id = $data['id'];
    unset($translations[$id]);

    $sql    = "UPDATE languages SET translations =:translations WHERE id='en'";
    $params = [':translations' => json_encode($translations)];
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'loadLongText') {
  try{
    $data = json_decode(file_get_contents('php://input'));

    $sql = <<<SQL
SELECT 
       id, 
       language_id, 
       content 
FROM longtexts 
WHERE id=:longtext_id
AND ((language_id=:language_id) OR (language_id='en'))
SQL;

    $params  = [
      ':longtext_id' => $data->longtextId,
      ':language_id' => $data->languageId,
    ];

    $texts = $database->fetchAll($sql, $params);

    $result = ['ok' => true, 'texts' => $texts];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'saveLongText') {
  try{
    $data = json_decode(file_get_contents('php://input'));

    $sql = <<<SQL
INSERT INTO longtexts
    (id, language_id, content)
    VALUES (:longtext_id, :language_id, :content)
ON DUPLICATE KEY UPDATE 
  id          = :longtext_id2,
  language_id = :language_id2,
  content     = :content2
SQL;

    $params  = [
      'longtext_id'  => $data->longtextId,
      'language_id'  => $data->languageId,
      'content'      => $data->content,
      'longtext_id2' => $data->longtextId,
      'language_id2' => $data->languageId,
      'content2'     => $data->content,
    ];

    $database->execute($sql, $params, true);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'loadQuestionaires') {
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

    $questionaires = $database->fetchAll($sql);

    $sql = <<<SQL
SELECT 
  id,
  text, 
  explanation 
FROM questions 
ORDER BY question_order;
SQL;

    $questions = $database->fetchAll($sql);

    $result = ['ok' => true, 'questionaires' => $questionaires, 'questions' => $questions];
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
else if ($function === 'deleteQuestionaire') {
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
else echo json_encode(['ok' => false, 'error' => 'Function not found']);

