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
//    if (isset($_SESSION['user_id'])) {
//      $this->loadUserFromDBByID($_SESSION['user_id']);
//    }

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
    // Country is extracted from the subdomain
    if (isset($_SERVER['SERVER_NAME'])) {
      $countryId = 'UN'; // Default

      $host = parse_url('http://' . $_SERVER['SERVER_NAME'], PHP_URL_HOST);
      $parts = explode('.', $host);
      if (count($parts) > 2) {
        $subdomain = $parts[0];
        $countryId = $subdomain;
      }

      $country = $this->database->getCountryFromId($countryId);
      if (isset($country)) {
        $this->countryId = $country['id'];
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

  private function loadUserFromDBIntern($id='', $email='', $password=''): void {
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
      if (($password === '') || (password_verify($password, $user['passwordhash']))) {
        $this->id = (int)$user['id'];
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

      writeSessionAndClose('user_id', $this->id);
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
        $this->loadUserFromDBIntern($userId);

        $this->updateStayLoggedInToken();
      } else {
        $this->deleteStayLoggedInToken($tokenHash);
      }
    }
  }

  private function deleteLoginTokenAndCookies(): void {
    if (isset($_COOKIE['login_token'])) {
      $token = $_COOKIE['login_token'];

      $tokenHash = password_hash($token, PASSWORD_DEFAULT);
      $sql = "DELETE FROM logins WHERE tokenhash=:tokenhash;";
      $params = ['tokenhash' => $tokenHash];
      $this->database->execute($sql, $params);

      // Delete the "remember me" cookie
      delete_site_cookie('login_token');
    }
    if (isset($_COOKIE['user_id'])) delete_site_cookie('user_id');
    if (isset($_COOKIE['login_id'])) delete_site_cookie('login_id');
  }


  private function loadUserFromDB($email, $password): void {
    $this->loadUserFromDBIntern('', $email, $password);
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

  public function resetPasswordRequest($email): bool|string {
    try{
      $sql = "SELECT 1 FROM users WHERE email=:email;";
      $user = $this->database->fetch($sql, [':email' => $email]);

      if ($user === false) throw new \Exception('Email adres onbekend') ;

      $passwordRecoveryID = getRandomString(16);

      $sql = "UPDATE users SET passwordrecoveryid=:passwordrecoveryid, passwordrecoverytime=current_timestamp WHERE email=:email;";
      $params = [
        ':passwordrecoveryid' => $passwordRecoveryID,
        ':email' => $email,
      ];
      $this->database->execute($sql, $params);

      return $passwordRecoveryID;
    } catch (\Exception $e){
      return false;
    }
  }

  private function deleteStayLoggedInToken($tokenHash): void {
    $sql = "DELETE FROM logins WHERE tokenhash=:tokenhash";
    $this->database->execute($sql, [':tokenhash' => $tokenHash]);
  }

  private function updateStayLoggedInToken(): void {

    $token = bin2hex(random_bytes(64));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
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
    if (empty($password)) throw new \Exception('Geen paswoord') ;
    if (empty($firstName)) throw new \Exception('Geen voornaam') ;
    if (empty($lastName)) throw new \Exception('Geen achternaam') ;
    if (empty($email)) throw new \Exception('Geen email') ;
    if (strlen($password) < 6) throw new \Exception('Wachtwoord is te kort: Minder dan 6 karakters.') ;

    $sql = "SELECT COUNT(*) AS count FROM users WHERE email=:email;";
    $params = [':email' => $email];
    $rows = $this->database->fetchAll($sql, $params);

    if ((count($rows) > 0) && ($rows[0]['count'] > 0)) {
      throw new \Exception('Email adres is al in gebruik. Gebruik de wachtwoord vergeten functie.');
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
  public function saveAccount($newUser): void {
    // Users can only change their own account
    if ($newUser->id !== $this->id) throw new \Exception('Internal error: User id is not of logged in user');

    if (empty($newUser->firstName))        throw new \Exception('Geen voornaam ingevuld');
    if (empty($newUser->lastName))         throw new \Exception('Geen achternaam ingevuld');
    if (strlen($newUser->firstName) > 100) throw new \Exception('Voornaam te lang (> 100)');
    if (strlen($newUser->lastName)  > 100) throw new \Exception('Achternaam te lang (> 100)');
    if (strlen($newUser->email)     > 250) throw new \Exception('Email adres is te lang (> 250)');
    if (!filter_var($newUser->email, FILTER_VALIDATE_EMAIL)) throw new \Exception('Ongeldig email adres');

    $sql = <<<SQL
UPDATE users SET
  firstname = :firstName,                 
  lastname  = :lastName,                 
  email     = :email,               
  language  = :language                 
WHERE id = :id
SQL;

    $params = [
      ':firstName' => $newUser->firstName,
      ':lastName'  => $newUser->lastName,
      ':email'     => $newUser->email,
      ':language'  => $newUser->language,
      ':id'        => $this->id,
    ];
    $this->database->execute($sql, $params);

    if (strlen($newUser->password) > 0){
      if (strlen($newUser->password) < 6) throw new \Exception('Wachtwoord moet minimaal 6 karakters lang zijn');
      if ($newUser->password !== $newUser->passwordConfirm) throw new \Exception('Wachtwoord bevestigen is niet hetzelfde als het wachtwoord');

      $passwordHash = password_hash($newUser->password, PASSWORD_DEFAULT);

      $sql = "UPDATE users SET passwordhash=:passwordhash, passwordrecoveryid = null WHERE id=:id;";
      $params = [
        ':passwordhash' => $passwordHash,
        ':id' => $this->id,
      ];

      $this->database->execute($sql, $params);
    }

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
