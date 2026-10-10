<?php

require_once 'scripts/Parsedown.php';
$parsedown = new Parsedown();

enum UserPermission: int {
  case newuser = 0;
  case admin = 1;
  case moderator = 2;
}

class User {
  private Database $database;
  public int $id;
  public bool $loggedIn = false;
  public string $firstName;
  public string $lastName;
  public string $email;
  public bool $emailExists = false;
  public ?string $languageId;
  public ?string $countryId;
  public array $translations;
  public bool $admin = false;
  public UserPermission $permission;

  public function __construct($database) {
    $this->database = $database;
    $this->clearData();

    // Load user data
    // Plan A: Check if the user is logged in (user_id set).
    if (isset($_SESSION['user_id'])) {
      $this->loadUserFromDBByID($_SESSION['user_id']);
    }

    // Plan B: Check if a remember me login token cookie is available
    if ( (! $this->loggedIn) && isset($_COOKIE['user_id']) && isset($_COOKIE['login_token']) && isset($_COOKIE['login_id'])) {
      $this->loadUserFromDBByToken($_COOKIE['user_id'], $_COOKIE['login_id'], $_COOKIE['login_token']);
    }

    if (!$this->loggedIn) {
      // Delete hanging sessions and cookies if not logged in. Sometimes a session is killed.
      $this->logout();
    }

    $this->loadCountryFromUrl();

    $this->loadDefaultLanguageIfNoneSet();
  }

  function isModerator(): bool {
    return in_array($this->permission, [UserPermission::admin, UserPermission::moderator]);
  }

  private function clearData(): void {
    $this->id = -1;
    $this->loggedIn = false;
    $this->firstName = '';
    $this->lastName = '';
    $this->email = '';
    $this->countryId = null;
    $this->languageId = null;
    $this->translations = [];
    $this->emailExists = false;
    $this->admin = false;
    $this->permission = UserPermission::newuser;
  }

  private function loadCountryFromUrl(): void {
    $this->countryId = DEFAULT_COUNTRY_ID; // Default

    if (isset($_SERVER['SERVER_NAME'])) {

      // Country is extracted from the subdomain
      $host = parse_url('http://' . $_SERVER['SERVER_NAME'], PHP_URL_HOST);
      $parts = explode('.', $host);
      if (count($parts) > 2) {
        $subdomain = $parts[0];
        $countryId = $subdomain;

        $country = $this->database->getCountryFromId($countryId);
        if (isset($country)) {
          $this->countryId = $country['id'];
        }
      }

    }
  }

  private function loadDefaultLanguageIfNoneSet(): void {
    if (! isset($this->languageId)) {

      // Load language from cookie if set
      if (isset($_COOKIE['language_id'])) {
        $this->languageId = $_COOKIE['language_id'];
      } else {
        $this->languageId = $this->database->getCountryLanguage($this->countryId);

        // Save the default language as we do not want to change automatically when selecting another country
        set_site_cookie('language_id', $this->languageId, 10 * 365);
      }
    }
  }

  /**
   * @param bool $newSession True when the visitor logs in now (password or login token). The session id is then
   *                         replaced, so a session id that someone planted before the login cannot be used afterwards.
   */
  private function loadUserFromDBIntern($id='', $email='', $password='', bool $newSession=false): void {
    $this->clearData();

    if ($id !== '') {
      $sql = <<<SQL
SELECT 
  u.id, 
  u.firstname, 
  u.lastname, 
  u.email, 
  u.passwordhash, 
  u.permission, 
  u.language 
FROM users u 
WHERE u.id=:id;
SQL;

      $params = [':id' => $id];
    } else {
      $sql = <<<SQL
SELECT 
  u.id, 
  u.firstname, 
  u.lastname, 
  u.email, 
  u.passwordhash, 
  u.permission, 
  u.language 
FROM users u 
WHERE u.email=:email;
SQL;

      $params = [':email' => $email];
    }

    $user = $this->database->fetch($sql, $params);
    if ($user) {
      $this->emailExists = true;

      // A user loaded by id is already authenticated (session or login token). Only internal code does that.
      // A login by email always needs a password. An empty password is never accepted.
      $authenticated = ($id !== '') ||
        (($password !== '') && is_string($user['passwordhash']) && password_verify($password, $user['passwordhash']));

      if ($authenticated) {
        $this->id = $user['id'];
        $this->firstName = $user['firstname'];
        $this->lastName = $user['lastname'];
        $this->email = $user['email'];
        $this->languageId = $user['language']?? $this->languageId;
        $this->permission = UserPermission::from($user['permission']);
        $this->admin = $this->permission === UserPermission::admin;
        $this->loggedIn = true;
      }
    }

    if ($this->loggedIn) {
      $sql = 'UPDATE users SET lastactive=CURRENT_TIMESTAMP WHERE id=:id;';
      $params = [':id' => $this->id];
      $this->database->execute($sql, $params);

      writeSessionAndClose('user_id', $this->id, $newSession);
    }
  }

  private function loadUserFromDBByID($Id): void {
    $this->loadUserFromDBIntern($Id);
  }

  private function loadUserFromDBByToken($userId, $loginId, $loginToken): void {
    $sql = "SELECT tokenhash FROM logins WHERE userid=:userid AND id=:loginid";
    $params = [
      ':userid' => $userId,
      ':loginid' => $loginId,
    ];

    $tokenHash = $this->database->fetchSingleValue($sql, $params);

    if ($tokenHash) {
      if (password_verify($loginToken, $tokenHash)){
        $this->loadUserFromDBIntern($userId, newSession: true);

        $this->updateStayLoggedInToken();
      } else {
        $this->deleteStayLoggedInToken($tokenHash);
      }
    }
  }

  private function deleteLoginTokenAndCookies(): void {
    if (isset($_COOKIE['login_token']) && isset($_COOKIE['login_id']) && isset($_COOKIE['user_id'])) {
      // Delete the token from the database, but only when the three cookies belong together
      $sql = "SELECT tokenhash FROM logins WHERE id=:id AND userid=:userid;";
      $params = [':id' => $_COOKIE['login_id'], ':userid' => $_COOKIE['user_id']];
      $tokenHash = $this->database->fetchSingleValue($sql, $params);

      if ($tokenHash && password_verify($_COOKIE['login_token'], $tokenHash)) {
        $this->database->execute("DELETE FROM logins WHERE id=:id;", [':id' => $_COOKIE['login_id']]);
      }
    }

    // Delete the "remember me" cookies
    if (isset($_COOKIE['login_token'])) delete_site_cookie('login_token');
    if (isset($_COOKIE['user_id'])) delete_site_cookie('user_id');
    if (isset($_COOKIE['login_id'])) delete_site_cookie('login_id');
  }


  private function loadUserFromDB($email, $password): void {
    $this->loadUserFromDBIntern('', $email, $password, true);
  }

  private function loadTranslations(): void {
    // First, get English translations
    $sql = "SELECT translations FROM languages WHERE id='en';";
    $translations_json = $this->database->fetchSingleValue($sql);
    $this->translations = json_decode($translations_json, true);

    // Get user language
    if ($this->languageId !== 'en') {
      $sql = 'SELECT translations FROM languages WHERE id=:id';
      $params = [':id' => $this->languageId];
      $translations_json = $this->database->fetchSingleValue($sql, $params);
      $translationsLanguage = json_decode($translations_json, true);

      foreach ($this->translations as $key => $english) {
        $textLanguage = trim($translationsLanguage[$key]?? '');

        // Translations that do not exist yet are replaced with the English text and
        // marked with an * to indicate that a translation is needed.
        if (! empty($textLanguage)) $this->translations[$key] = $textLanguage;
        else $this->translations[$key] .= '*';
      }
    }

    // Sort on key alphabetically
    ksort($this->translations);
  }

  public function translateLongText($textId): string {
    $text = $this->getLongText($textId, $this->languageId);

    // Fall back to English if the original does not exist.
    if (($text === false) && ($this->languageId !== 'en')){
      $text = $this->getLongText($textId, 'en');
    }

    global $parsedown;
    return $parsedown->text($text);
  }

  private function getLongText($textId, $languageId): string | false {
    $sql = "SELECT content FROM longtexts WHERE id=:id AND language_id = :language_id;";
    $params = [
      ':id' => $textId,
      ':language_id' => $languageId
    ];

    return $this->database->fetchSingleValue($sql, $params);
  }

  public function getTranslations(): void {
    if (empty($this->translations)) $this->loadTranslations();
  }

  public function logout(): void {
    deleteSessionIdAndClose('user_id');
    $this->deleteLoginTokenAndCookies();
    $this->clearData();
  }

  /**
   * Starts a password reset. Returns the token for the reset link, or false when there is no user with this
   * email address, or when a reset was requested less than a minute ago for this user.
   * The token is random and only a hash of it is stored in the database.
   */
  public function resetPasswordRequest($email): bool|string {
    // One request per minute per user. This also limits the number of emails that someone can trigger.
    $sql = <<<SQL
SELECT 1 FROM users
WHERE email=:email AND (passwordrecoverytime IS NULL OR passwordrecoverytime < (NOW() - INTERVAL 60 SECOND));
SQL;
    $user = $this->database->fetch($sql, [':email' => $email]);
    if ($user === false) return false;

    $token = bin2hex(random_bytes(32));

    $sql = "UPDATE users SET passwordrecoveryid=:tokenhash, passwordrecoverytime=NOW() WHERE email=:email;";
    $this->database->execute($sql, [':tokenhash' => hash('sha256', $token), ':email' => $email]);

    return $token;
  }

  private function deleteStayLoggedInToken($tokenHash): void {
    $sql = "DELETE FROM logins WHERE tokenhash=:tokenhash";
    $this->database->execute($sql, [':tokenhash' => $tokenHash]);
  }

  private function updateStayLoggedInToken(): void {

    $token = bin2hex(random_bytes(64));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);

    // The cookies last a year. Remove older tokens
    $this->database->execute("DELETE FROM logins WHERE lastlogin < (NOW() - INTERVAL 365 DAY);");

    $sql = <<<SQL
INSERT INTO logins 
  (userid, tokenhash, lastlogin) 
  VALUES (:userid, :tokenhash, current_timestamp)
  ON DUPLICATE KEY UPDATE lastlogin=current_timestamp;
SQL;
    $params = ['userid' => $this->id, 'tokenhash' => $tokenHash];
    $this->database->execute($sql, $params);
    $id = $this->database->lastInsertID();

    set_site_cookie('login_token', $token,365);
    set_site_cookie('user_id', $this->id, 365);
    set_site_cookie('login_id', $id, 365);
  }

  public function login(string $email, string $password, bool $stayLoggedIn=false): void {
    $this->logout();
    $this->loadUserFromDB($email, $password);
    $this->loadTranslations();

    if ($stayLoggedIn) $this->updateStayLoggedInToken();
  }

  /**
   * @throws Exception
   */
  public function register(string $firstName, string $lastName, string $email, string $password): void {
    if (empty($password)) throw new \Exception('No password') ;
    if (empty($firstName)) throw new \Exception('No first name') ;
    if (empty($lastName)) throw new \Exception('No last name') ;
    if (empty($email)) throw new \Exception('No email address') ;
    if (strlen($password) < 6) throw new \Exception('Password is too short: less than 6 characters.') ;

    $sql = "SELECT COUNT(*) AS count FROM users WHERE email=:email;";
    $params = [':email' => $email];
    $rows = $this->database->fetchAll($sql, $params);

    if ((count($rows) > 0) && ($rows[0]['count'] > 0)) {
      throw new \Exception('This email address is already in use. Use the forgot password function.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $sql = 'INSERT INTO users (firstname, lastname, email, passwordhash) VALUES(:firstname, :lastname, :email, :password)';
    $params = [':firstname' => $firstName, ':lastname' => $lastName, ':email' => $email, ':password' => $passwordHash];
    $this->database->execute($sql, $params);

    $userId = $this->database->lastInsertID();

    $this->database->log($userId, LogLevel::info, "New registration");
  }

  /**
   * @throws Exception
   */
  public function save($newUser): void {
    // Users can only change their own account
    if (($newUser['id'] ?? null) !== $this->id) throw new \Exception('Internal error: User id is not of logged in user');

    $firstName = (string)($newUser['firstName'] ?? '');
    $lastName  = (string)($newUser['lastName'] ?? '');
    $email     = (string)($newUser['email'] ?? '');
    $language  = (string)($newUser['language'] ?? '');
    $password  = (string)($newUser['password'] ?? '');

    // Check everything first. Nothing is saved when something is wrong, so a rejected password cannot leave a half saved profile.
    if ($firstName === '')              throw new \Exception('First name is not filled in');
    if ($lastName === '')               throw new \Exception('Last name is not filled in');
    if (strlen($firstName) > 100)       throw new \Exception('First name is too long (> 100)');
    if (strlen($lastName)  > 100)       throw new \Exception('Last name is too long (> 100)');
    if (strlen($email)     > 250)       throw new \Exception('Email address is too long (> 250)');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \Exception('Invalid email address');

    $sql = 'SELECT COUNT(*) FROM users WHERE email=:email AND id<>:id';
    if ($this->database->fetchSingleValue($sql, [':email' => $email, ':id' => $this->id]) > 0) {
      throw new \Exception('This email address is already in use');
    }

    if ($language !== '') {
      $sql = 'SELECT COUNT(*) FROM languages WHERE id=:id';
      if ($this->database->fetchSingleValue($sql, [':id' => $language]) == 0) throw new \Exception('Invalid language');
    }

    $passwordHash = null;
    if ($password !== '') {
      if (strlen($password) < 6) throw new \Exception('Password must be at least 6 characters long');
      if ($password !== ($newUser['passwordConfirm'] ?? null)) throw new \Exception('Password confirmation is not the same as the password');

      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    // One statement: the profile and the password are saved together or not at all
    $sql = 'UPDATE users SET firstname=:firstName, lastname=:lastName, email=:email, language=:language';
    $params = [
      ':firstName' => $firstName,
      ':lastName'  => $lastName,
      ':email'     => $email,
      ':language'  => ($language === '') ? null : $language,
      ':id'        => $this->id,
    ];

    if ($passwordHash !== null) {
      $sql .= ', passwordhash=:passwordhash, passwordrecoveryid=null';
      $params[':passwordhash'] = $passwordHash;
    }

    $this->database->execute($sql . ' WHERE id=:id', $params);

    if ($passwordHash !== null) $this->deleteOtherStayLoggedInTokens();
  }

  /**
   * After a password change: ends the "stay logged in" logins on all other devices, for example of someone who knew
   * the old password. The login of this browser stays, so the user is not logged out here.
   */
  private function deleteOtherStayLoggedInTokens(): void {
    $keepLoginId = 0;

    if (isset($_COOKIE['login_token']) && isset($_COOKIE['login_id']) && isset($_COOKIE['user_id'])) {
      $sql = 'SELECT tokenhash FROM logins WHERE id=:id AND userid=:userid';
      $tokenHash = $this->database->fetchSingleValue($sql, [':id' => $_COOKIE['login_id'], ':userid' => $this->id]);

      if ($tokenHash && password_verify($_COOKIE['login_token'], $tokenHash)) $keepLoginId = (int)$_COOKIE['login_id'];
    }

    $this->database->execute('DELETE FROM logins WHERE userid=:userid AND id<>:keep', [':userid' => $this->id, ':keep' => $keepLoginId]);
  }

  /**
   * @throws Exception
   */
  public function saveLanguage($languageId): bool {
    if ($this->loggedIn) {
      $sql = "UPDATE users SET users.language = :language WHERE id = :id;";

      $params = [
        ':language' => $languageId,
        ':id' => $this->id,
      ];
      $this->database->execute($sql, $params);
    }

    // Save language id in cookie in case the user is not logged in or logs out
    set_site_cookie('language_id',$languageId, 10 * 365);

    return true;
  }

  public function info(): array {
    if ($this->loggedIn)
      $info = [
        'loggedin'     => $this->loggedIn,
        'id'           => $this->id,
        'firstname'    => $this->firstName,
        'lastname'     => $this->lastName,
        'email'        => $this->email,
        'countryid'    => $this->countryId,
        'language'     => $this->languageId,
        'translations' => $this->translations,
        'emailexists'  => $this->emailExists,
        'admin'        => $this->admin,
        'moderator'    => $this->isModerator(),
        'permission'   => $this->permission->value,
      ];
    else $info = [
      'loggedin'     => false,
      'countryid'    => $this->countryId,
      'language'     => $this->languageId,
      'translations' => $this->translations,
      'emailexists'  => $this->emailExists,
    ];
    return $info;
  }

  private function firstCharacterUpper($text): string {
    // Unicode-aware ucfirst; prefers grapheme clusters if intl is installed
    if ($text === '') return $text;

    if (function_exists('grapheme_substr')) {
      $first = grapheme_substr($text, 0, 1);
      $rest = grapheme_substr($text, 1);
    } else {
      $first = mb_substr($text, 0, 1, 'UTF-8');
      $rest = mb_substr($text, 1, null, 'UTF-8');
    }

    $first = mb_convert_case($first, MB_CASE_TITLE, 'UTF-8');
    return $first . $rest;
  }

  public function translate(string $key): string {
    $lowerKey = strtolower($key);

    $this ->getTranslations();

    // Unknown keys are marked with double **. Indicating that the developers should add them to the translations table.
    $text = $this->translations[$lowerKey]?? $key . '**';

    return $lowerKey === $key? $text : $this->firstCharacterUpper($text);
  }

}
