<?php


abstract class TLogLevel {
  const info    = 1;
  const warning = 2;
  const error   = 3;
}


class TDatabase {
  /** @var  PDO */
  private $pdo;
  public  $rowCount;

  public function databaseHandle(){
    return $this->pdo;
  }

  /**
   * @throws Exception
   */
  public function open(){
    // To debug on localhost with remote database use port forwarding:
    // ssh -L 3306:localhost:3306 loginname@databaseserver.com
    try {
      $options = [
        PDO::ATTR_EMULATE_PREPARES   => false,              // Forces native MySQL prepares. Required to return native fields (integer & float instead of strings) See: https://stackoverflow.com/questions/10113562/pdo-mysql-use-pdoattr-emulate-prepares-or-not
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'", // Unicode support
      ];
      $this->pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $options);
    } catch (Exception $e) {
      throw new Exception('Database error: ' . $e->getMessage());
    }
  }

  public function close(){
    unset($this->pdo);
  }

  public function beginTransaction(){
    $this->pdo->beginTransaction();
  }

  public function rollback(){
    $this->pdo->rollBack();
  }

  public function commit(){
    $this->pdo->commit();
  }

  public function inTransaction(){
    $this->pdo->inTransaction();
  }

  public function fetchAll($sql, $params=null){
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetchAllGroup($sql, $params=null){
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchAll(PDO::FETCH_GROUP);
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetchObject($sql, $params=null){
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchObject();
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetch($sql, $params=null){
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetchSingleValue($sql, $params=null){
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchColumn();
    } catch (Exception $e) {
      return false;
    }
  }

  public function lastInsertID(){
    return $this->pdo->lastInsertId();
  }

  /**
   * @param $sql
   * @param null $params
   * @return bool
   */
  public function execute($sql, $params=null, $doRowCount=false){
    try {
      $statement = $this->pdo->prepare($sql);
      $result    = $statement->execute($params);

      if ($doRowCount) $this->rowCount = $statement->rowCount();

      return $result;
    } catch (Exception $e) {
      return false;
    }
  }

  public function prepare($sql){
    try {
      return $this->pdo->prepare($sql);
    } catch (Exception $e) {
      return false;
    }
  }

  /**
   * @param array $params
   * @param PDOStatement $statement
   * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
   */
  public function executePrepared($params, $statement){
    try {
      return $statement->execute($params);
    } catch (Exception $e) {
      return false;
    }
  }

  /**
   * @param PDOStatement $statement
   * @param null $params
   * @return array | false
   */
  public function fetchAllPrepared($statement, $params=null){
    try {
      $statement->execute($params);
      return $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      return false;
    }
  }


  public function getUserIDFromEmail($email){
    $sql = 'SELECT id FROM users WHERE email=:email';
    $params = [':email' => $email];
    return $this->fetchSingleValue($sql, $params);
  }

  public function log($userId, $level=TLogLevel::info, $info=''){
    $ip = substr(getCallerIP(), 0, 45);
    $sql = 'INSERT INTO logs (userid, level, ip, info) VALUES (:userid, :level, :ip, :info)';
    $params = [':userid' => $userId, ':level' => $level, ':ip' => $ip, ':info' => substr($info, 0, 500)];
    return $this->execute($sql, $params);
  }

  public function logError($info=''){
    $this->log(null,TLogLevel::error, $info);
  }

  public function logText($text=''){
    $this->log(null,TLogLevel::info, $text);
  }

}
