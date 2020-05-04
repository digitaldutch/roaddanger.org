<?php

abstract class TUserPermission {
  const newuser   = 0;
  const admin     = 1;
  const moderator = 2;
}

class TUser{
  /** @var  TDatabase */
  private $database;

  public $id;
  public $loggedin = false;
  public $firstname;
  public $lastname;
  public $email;
  public $emailexists = false;
  public $admin = false;
  public $permission;

  public function __construct($database){
    $this->database = $database;
    $this->clearData();

    // Load user data
    // Plan A: Check if user is logged in (user_id set).
    if (isset($_SESSION['user_id'])) {
      $this->loadUserFromDBByID($_SESSION['user_id']);
    }

    // Plan B: Check if a remember me login token cookie is available
    if ( (! $this->loggedin) && isset($_COOKIE['user_id']) && isset($_COOKIE['login_token']) && isset($_COOKIE['login_id'])) {
      $this->loadUserFromDBByToken($_COOKIE['user_id'], $_COOKIE['login_id'], $_COOKIE['login_token']);
    }

    if (!$this->loggedin) {
      // Delete hanging sessions and cookies if not logged in. Sometimes a session is killed.
      $this->logout();
    }
  }

  function isModerator(){
    return ($this->permission === TUserPermission::admin) || ($this->permission === TUserPermission::moderator);
  }

  private function clearData(){
    $this->id          = -1;
    $this->loggedin    = false;
    $this->firstname   = '';
    $this->lastname    = '';
    $this->email       = '';
    $this->emailexists = false;
    $this->admin       = false;
    $this->permission  = TUserPermission::newuser;
  }

  private function loadUserFromDBIntern($id='', $email='', $password='') {
    $this->clearData();

    if ($id !== ''){
      $sql = "SELECT u.id, firstname, lastname, email, passwordhash, permission FROM users u WHERE id=:id;";
      $params = array(':id' => $id);
    } else {
      $sql = "SELECT u.id, firstname, lastname, email, passwordhash, permission FROM users u WHERE email=:email;";
      $params = array(':email' => $email);
    }

    $user = $this->database->fetch($sql, $params);
    if ($user) {
      $this->emailexists = true;
      if (($password === '') || (password_verify($password, $user['passwordhash']))) {
        $this->id         = (int)$user['id'];
        $this->email      = $user['email'];
        $this->firstname  = $user['firstname'];
        $this->lastname   = $user['lastname'];
        $this->permission = (int)$user['permission'];
        $this->admin      = $this->permission == 1;
        $this->loggedin   = true;
      }
    }

    if ($this->loggedin) {
      $sql = 'UPDATE users SET lastactive=CURRENT_TIMESTAMP WHERE id=:id;';
      $params = array(':id' => $this->id);
      $this->database->execute($sql, $params);

      $_SESSION['user_id'] = $this->id;
    }
  }

  private function loadUserFromDBByID($ID) {
    $this->loadUserFromDBIntern($ID);
  }

  private function loadUserFromDBByToken($userID, $loginID, $loginToken) {
    $sql    = "SELECT tokenhash FROM logins WHERE userid=:userid AND id=:loginid";
    $params = array(':userid' => $userID, ':loginid' => $loginID);
    $row = $this->database->fetch($sql, $params);

    // Kill the token even if login fails. One time use only to block tokens from stolen cookies.
    $sql = "DELETE FROM logins WHERE userid=:userid AND id=:loginid";
    $this->database->execute($sql, $params);

    if ($row && password_verify($loginToken, $row['tokenhash'])){
      $this->loadUserFromDBIntern($userID);

      // Create a new token now that we successfully logged in
      $this->setStayLoggedInToken();
    }
  }

  private function deleteLoginTokenAndCookies() {
    if (isset($_COOKIE['login_token'])) {
      $token = $_COOKIE['login_token'];

      $tokenHash = password_hash($token, PASSWORD_DEFAULT);
      $sql = "DELETE FROM logins WHERE tokenhash=?;";
      $params = ['tokenhash' => $tokenHash];
      $this->database->execute($sql, $params);

      // Clear remember me cookies
      setcookie('login_token', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['user_id']))  setcookie('user_id',  '', time() - 3600, '/');
    if (isset($_COOKIE['login_id'])) setcookie('login_id', '', time() - 3600, '/');
  }


  private function loadUserFromDB($email, $password) {
    $this->loadUserFromDBIntern('', $email, $password);
  }

  public function logout(){
    if (isset($_SESSION['user_id'])) unset($_SESSION['user_id']);
    $this->deleteLoginTokenAndCookies();
    $this->clearData();
  }

  public function resetPasswordRequest($email) {
    try{
      $sql = "SELECT 1 FROM users WHERE email=:email;";
      $user = $this->database->fetch($sql, [':email' => $email]);

      if ($user === false) throw new Exception('Email adres onbekend') ;

      $passwordRecoveryID = getRandomString(16);
      $sql    = "UPDATE users SET passwordrecoveryid=:passwordrecoveryid, passwordrecoverytime=current_timestamp WHERE email=:email;";
      $params = array(':passwordrecoveryid' => $passwordRecoveryID, ':email' => $email);
      $this->database->execute($sql, $params);
      return $passwordRecoveryID;
    } catch (Exception $e){
      return false;
    }
  }

  private function setStayLoggedInToken(){
    $token     = bin2hex(openssl_random_pseudo_bytes(64)); // PHP 7: Use bin2hex(random_bytes(64));
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

    $NowPlus10Years = time() + 60*60*24*3650; // 3650 dagen cookie expiration time
    // Use path bug to set samesite. PHP 7.3: Samesite is an extra parameter
    setcookie('user_id', $this->id,  ['expires' => $NowPlus10Years, 'path' => '/', 'secure' => true, 'samesite' => 'Lax']);
    setcookie('login_id', $id,       ['expires' => $NowPlus10Years, 'path' => '/', 'secure' => true, 'samesite' => 'Lax']);
    setcookie('login_token', $token, ['expires' => $NowPlus10Years, 'path' => '/', 'secure' => true, 'samesite' => 'Lax']);
  }

  public function login($email, $password, $stayLoggedIn=false){
    $this->logout();
    $this->loadUserFromDB($email, $password);

    if ($stayLoggedIn) $this->setStayLoggedInToken();
  }

  /**
   * @param $firstname
   * @param $lastname
   * @param $email
   * @param $password
   * @return string
   * @throws Exception
   */
  public function register($firstname, $lastname, $email, $password){
    if (empty($password))                            throw new Exception('Geen paswoord') ;
    if (empty($firstname))                           throw new Exception('Geen voornaam') ;
    if (empty($lastname))                            throw new Exception('Geen achternaam') ;
    if (empty($email))                               throw new Exception('Geen email') ;
    if (empty($password) or (strlen($password) < 6)) throw new Exception('Wachtwoord is te kort: Minder dan 6 karakters.') ;

    $sql = "SELECT COUNT(*) AS count FROM users WHERE email=:email;";
    $params = array(':email' => $email);
    $rows = $this->database->fetchAll($sql, $params);

    if ((count($rows) > 0) && ($rows[0]['count'] > 0)) {
      throw new Exception('Email adres wordt al gebruikt door iemand anders.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $sql = 'INSERT INTO users (firstname, lastname, email, passwordhash) VALUES(:firstname, :lastname, :email, :password)';
    $params = array(':firstname' => $firstname, ':lastname' => $lastname, ':email' => $email, ':password' => $passwordHash);
    $this->database->execute($sql, $params);
    $userId = $this->database->lastInsertID();

    $this->database->log($userId, TLogLevel::info, "Nieuw mens registratie.");

    return $userId;
  }

  /**
   * @param $firstname
   * @param $lastname
   * @param string $password
   * @return bool
   * @throws Exception
   */
  public function saveProfile($firstname, $lastname, $password=''){
    if (strlen($firstname) <= 0) throw new Exception('Geen voornaam gevonden');
    if (strlen($lastname)  <= 0) throw new Exception('Geen achternaam gevonden');

    if (strlen($password) > 0) {
      if (strlen($password) < 6) throw new Exception('Wachtwoord te kort. Minder dan 6 karakters.');

      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      $sql    = 'UPDATE users SET firstname=:firstname, lastname=:lastname, passwordhash=:passwordHash WHERE id=:id;';
      $params = array(':firstname' => $firstname, ':lastname' => $lastname, ':passwordHash' => $passwordHash,':id' => $this->id);
    } else {
      $sql    = 'UPDATE  users SET firstname=:firstname, lastname=:lastname WHERE id=:id;';
      $params = array(':firstname' => $firstname, ':lastname' => $lastname, ':id' => $this->id);
    }

    $this->database->execute($sql, $params);

    return true;
  }

  public function fullName(){
    return trim($this->firstname . ' ' . $this->lastname);
  }

  public function info() {
    if ($this->loggedin)
      $r = [
        'loggedin'    => $this->loggedin,
        'id'          => $this->id,
        'firstname'   => $this->firstname,
        'lastname'    => $this->lastname,
        'email'       => $this->email,
        'emailexists' => $this->emailexists,
        'admin'       => $this->admin,
        'moderator'   => $this->isModerator(),
        'permission'  => $this->permission,
      ];
    else $r = [
      'loggedin'    => false,
      'emailexists' => $this->emailexists,
    ];
    return $r;
  }

}
