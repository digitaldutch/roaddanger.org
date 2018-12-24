<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
$userInfo = $user->info();

// Only admins allowed
if (! $user->admin) {
  $result = ['ok' => false, 'error' => 'Gebruiker is geen beheerder', 'user' => $userInfo];
  die(json_encode($result));
}

$function = $_REQUEST['function'];

if ($function === 'loadusers') {
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
    foreach ($DBResults as $user) {
      $user['id']             = (int)$user['id'];
      $user['permission']     = (int)$user['permission'];
      $user['lastactive']     = datetimeDBToISO8601($user['lastactive']);

      $users[] = $user;
    }

    $result = ['ok' => true, 'users' => $users];
    if ($offset === 0) {
      $result['user'] = $userInfo;
    }
  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function === 'saveuser') {
  try{
    $data    = json_decode(file_get_contents('php://input'), true);
    $user = $data['user'];

    $sql = <<<SQL
    UPDATE users SET
      email       = :email,
      firstname   = :firstname,
      lastname    = :lastname,
      permission  = :permission                    
    WHERE id=:id;
SQL;
    $params = array(
      ':email'       => $user['email'],
      ':firstname'   => $user['firstname'],
      ':lastname'    => $user['lastname'],
      ':permission'  => $user['permission'],
      ':id'          => $user['id'],
    );
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
  }
  echo json_encode($result);
} // ====================
else if ($function === 'deleteuser') {
  try{
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql = "DELETE FROM users WHERE id=:id;";
      $params = array(':id' => $id);

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Kan gebruiker niet verwijderen.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} // ====================
