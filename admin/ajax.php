<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

global $database;
global $user;

// Only admins allowed
if (! $user->admin) {
  $result = [
    'ok' => false,
    'error' => 'No permission. You need to be an administrator.',
    'user' => $user->info(),
  ];
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
  } catch (\Exception $e) {
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
  } catch (\Exception $e){
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
  } catch (\Exception $e){
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
  } catch (\Exception $e){
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
  } catch (\Exception $e){
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
  } catch (\Exception $e){
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
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
else echo json_encode(['ok' => false, 'error' => 'Function not found']);

