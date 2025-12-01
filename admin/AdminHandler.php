<?php

require_once '../general/AjaxHandler.php';

class AdminHandler extends AjaxHandler {

  public function handleRequest($command): void {
    try {

      if (! $this->user->admin) {
        throw new Exception('Admins only');
      }

      // The stuff below is only for administrators
      $response = match($command) {
        'loadUsers' => $this->loadUsers(),
        'saveUser' => $this->saveUser(),
        'deleteUser' => $this->deleteUser(),
        'saveNewTranslation' => $this->saveNewTranslation(),
        'deleteTranslation' => $this->deleteTranslation(),
        'loadLongText' => $this->loadLongText(),
        'saveLongText' => $this->saveLongText(),
        default => throw new Exception('Invalid command'),
      };

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }

  private function loadUsers(): array {
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
  permission,
  registrationtime,
  (select count(*) from articles where articles.userid = users.id) AS article_count
FROM users
ORDER BY lastactive DESC
LIMIT $offset, $count
SQL;

    $users = $this->database->fetchAll($sql);
    foreach ($users as &$dbUser) {
      $dbUser['lastactive'] = datetimeDBToISO8601($dbUser['lastactive']);
      $dbUser['registrationtime'] = datetimeDBToISO8601($dbUser['registrationtime']);
    }

    return [
      'users' => $users,
    ];
  }

  private function saveUser(): array {
    $user = $this->input;

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

    $this->database->execute($sql, $params);

    return [];
  }

  private function deleteUser(): array {
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql = "DELETE FROM users WHERE id=:id;";
      $params = [':id' => $id];

      $this->database->execute($sql, $params, true);
      if ($this->database->rowCount === 0) throw new \Exception('Unable to delete user.');
    }

    return [];
  }

  private function saveNewTranslation(): array {
    $sql = "SELECT translations FROM languages WHERE id='en'";
    $translations = json_decode($this->database->fetchSingleValue($sql), true);

    $translations[strtolower($this->input['id'])] = strtolower(trim($this->input['english']));

    $sql = "UPDATE languages SET translations =:translations WHERE id='en'";
    $params = [':translations' => json_encode($translations)];
    $this->database->execute($sql, $params);

    return [];
  }

  private function deleteTranslation(): array {
    $sql = "SELECT translations FROM languages WHERE id='en'";
    $translations = json_decode($this->database->fetchSingleValue($sql), true);

    unset($translations[strtolower($this->input['id'])]);

    $sql = "UPDATE languages SET translations =:translations WHERE id='en'";
    $params = [':translations' => json_encode($translations)];
    $this->database->execute($sql, $params);

    return [];
  }

  private function loadLongText(): array {
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
      ':longtext_id' => $this->input['longtextId'],
      ':language_id' => $this->input['languageId'],
    ];

    $texts = $this->database->fetchAll($sql, $params);

    return ['texts' => $texts];
  }

  private function saveLongText(): array {
    $sql = <<<SQL
INSERT INTO longtexts
(id, language_id, content)
VALUES (:longtext_id, :language_id, :content)
ON DUPLICATE KEY UPDATE
id = :longtext_id2,
language_id = :language_id2,
content = :content2
SQL;

    $params  = [
      'longtext_id'  => $this->input['longtextId'],
      'language_id'  => $this->input['languageId'],
      'content'      => $this->input['content'],
      'longtext_id2' => $this->input['longtextId'],
      'language_id2' => $this->input['languageId'],
      'content2'     => $this->input['content'],
    ];

    $this->database->execute($sql, $params);

    return [];
  }

}