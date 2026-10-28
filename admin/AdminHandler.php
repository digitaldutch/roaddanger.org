<?php

require_once '../general/AjaxHandler.php';

class AdminHandler extends AjaxHandler {

  public function handleRequest(): void {
    try {

      if (! $this->user->admin) {
        throw new \Exception('Admins only');
      }

      // The stuff below is only for administrators
      $response = match($this->command) {
        'loadUsers' => $this->loadUsers(),
        'saveUser' => $this->saveUser(),
        'loadApiTokens' => $this->loadApiTokens(),
        'createApiToken' => $this->createApiToken(),
        'setApiTokenActive' => $this->setApiTokenActive(),
        'deleteApiToken' => $this->deleteApiToken(),
        'deleteUser' => $this->deleteUser(),
        'saveNewTranslation' => $this->saveNewTranslation(),
        'deleteTranslation' => $this->deleteTranslation(),
        'loadLongText' => $this->loadLongText(),
        'saveLongText' => $this->saveLongText(),
        default => throw new \Exception('Invalid command'),
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
  (SELECT COUNT(*) FROM articles WHERE articles.userid = users.id) AS article_count
FROM users
ORDER BY lastactive DESC
LIMIT :offset, :count
SQL;

    $params = [
      ':offset' => $offset,
      ':count' => $count,
    ];

    $users = $this->database->fetchAll($sql, $params);
    foreach ($users as &$dbUser) {
      $dbUser['lastactive'] = datetimeDBToISO8601($dbUser['lastactive']);
      $dbUser['registrationtime'] = datetimeDBToISO8601($dbUser['registrationtime']);
    }

    return [
      'users' => $users,
    ];
  }

  /**
   * The tokens of the import API (api/import.php) and the moderators and admins that can own one.
   */
  private function loadApiTokens(): array {
    $sql = <<<SQL
SELECT
  t.id, t.name, t.active, t.autoapprove, t.maxaiperday, t.createtime, t.lastused,
  u.id AS userid, CONCAT(u.firstname, ' ', u.lastname) AS username, u.email,
  (SELECT COUNT(*) FROM import_articles ia WHERE ia.tokenid = t.id) AS imported
FROM api_tokens t
JOIN users u ON u.id = t.userid
ORDER BY t.id DESC
SQL;
    $tokens = $this->database->fetchAll($sql);
    foreach ($tokens as &$token) {
      $token['createtime'] = datetimeDBToISO8601($token['createtime']);
      $token['lastused'] = $token['lastused'] === null ? null : datetimeDBToISO8601($token['lastused']);
    }

    // permission: 1 admin, 2 moderator
    $owners = $this->database->fetchAll("SELECT id, CONCAT(firstname, ' ', lastname) AS name, email FROM users WHERE permission IN (1, 2) ORDER BY firstname, lastname");

    return ['tokens' => $tokens, 'owners' => $owners];
  }

  /**
   * Makes a token. It is returned once: only a hash is saved.
   * @throws Exception
   */
  private function createApiToken(): array {
    $userId = (int)($this->input['userid'] ?? 0);
    $name = trim((string)($this->input['name'] ?? ''));
    if ($name === '') throw new \Exception('Name is required');
    if (mb_strlen($name) > 100) throw new \Exception('Name is too long');

    $owner = $this->database->fetch('SELECT id FROM users WHERE id=:id AND permission IN (1, 2)', [':id' => $userId]);
    if (! $owner) throw new \Exception('The owner must be a moderator or an admin');

    $token = bin2hex(random_bytes(32));
    $this->database->execute(
      'INSERT INTO api_tokens (userid, name, tokenhash, autoapprove, maxaiperday) VALUES (:userid, :name, :hash, :autoapprove, :maxai)',
      [':userid' => $userId, ':name' => $name, ':hash' => hash('sha256', $token),
       ':autoapprove' => empty($this->input['autoapprove']) ? 0 : 1,
       ':maxai' => max(0, min(10000, (int)($this->input['maxaiperday'] ?? 200)))]);

    return ['token' => $token, 'name' => $name];
  }

  private function setApiTokenActive(): array {
    $this->database->execute('UPDATE api_tokens SET active=:active WHERE id=:id',
      [':active' => empty($this->input['active']) ? 0 : 1, ':id' => (int)($this->input['id'] ?? 0)]);

    return [];
  }

  private function deleteApiToken(): array {
    $this->database->execute('DELETE FROM api_tokens WHERE id=:id', [':id' => (int)($this->input['id'] ?? 0)]);

    return [];
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