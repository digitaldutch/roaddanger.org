<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

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
    $DBResults = $database->fetchAll($sql);
    foreach ($DBResults as $dbUser) {
      $dbUser['id']         = (int)$dbUser['id'];
      $dbUser['permission'] = (int)$dbUser['permission'];
      $dbUser['lastactive'] = datetimeDBToISO8601($dbUser['lastactive']);

      $users[] = $dbUser;
    }

    $result = ['ok' => true, 'users' => $users];
    if ($offset === 0) {
      $user->getTranslations();
      $result['user'] = $user->info();
    }

  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function === 'saveOptions') {
  try{
    $options = json_decode(file_get_contents('php://input'), true);

    $sql         = "INSERT INTO options (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value=:value2";
    $DBStatement = $database->prepare($sql);

    foreach ($options as $key => $value){
      $params = [':name' => $key, ':value' => $value, ':value2' => $value];
      $database->executePrepared($params, $DBStatement);
    }

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
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

    $translations[strtolower($data['id'])] = strtolower($data['english']);

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
