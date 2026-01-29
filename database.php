<?php


enum LogLevel: int {
  case info = 1;
  case warning = 2;
  case error = 3;
}


class Database {
  private PDO $pdo;
  public  ?array $countries;
  public  int $rowCount = 0;

  /**
   * @throws Exception
   */
  public function open(): void {
    // To debug on localhost with the remote database, use port forwarding use port forwarding:
    // ssh -L 3306:localhost:3306 loginname@databaseserver.com
    try {
      $options = [
        // Forces native MySQL prepares. Required before PHP 8.1 to return native fields (integer & float instead of strings)
        // See: https://stackoverflow.com/questions/10113562/pdo-mysql-use-pdoattr-emulate-prepares-or-not
        PDO::ATTR_EMULATE_PREPARES => false,

        // Unicode support. utf8mb4 also supports emojis, which UTF8 does not.
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",

        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      ];

      $this->pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $options);

    } catch (Throwable $e) {
      throw new Exception('Database error: ' . $e->getMessage());
    }
  }

  public function close(): void {
    unset($this->pdo);
  }

  public function beginTransaction(): void {
    $this->pdo->beginTransaction();
  }

  public function rollback(): void {
    $this->pdo->rollBack();
  }

  public function commit(): void {
    $this->pdo->commit();
  }

  public function inTransaction(): void {
    $this->pdo->inTransaction();
  }

  public function fetchAll($sql, $params=null): bool|array {
    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function fetchAllValues($sql, $params=null): false|array {
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetchAllGroup($sql, $params=null): false|array {
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchAll(PDO::FETCH_GROUP);
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetchObject($sql, $params=null) {
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetchObject();
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetch($sql, $params=null) {
    try {
      $statement = $this->pdo->prepare($sql);
      $statement->execute($params);
      return $statement->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      return false;
    }
  }

  public function fetchSingleValue(string $sql, ?array $params=null): mixed{
    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
  }

  public function lastInsertID(): bool|string {
    return $this->pdo->lastInsertId();
  }

  public function execute(string $sql, array|null $params=null, bool $doRowCount=false): bool {
    $statement = $this->pdo->prepare($sql);
    $result = $statement->execute($params);

    if ($doRowCount) $this->rowCount = $statement->rowCount();

    return $result;
  }

  public function prepare($sql): PDOStatement|false {
    return $this->pdo->prepare($sql);
  }

  public function executePrepared(PDOStatement $statement, array|null $params=null, $doRowCount=false): bool {
    $result = $statement->execute($params);
    if ($doRowCount) $this->rowCount = $statement->rowCount();
    return $result;
  }

  public function fetchAllPrepared(PDOStatement $statement, mixed $params=null): array|false {
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function log(?int $userId, LogLevel $level=LogLevel::info, string $info=''): bool {
    $ip = substr(getCallerIP(), 0, 45);

    $sql = 'INSERT INTO logs (userid, level, ip, info) VALUES (:userId, :level, :ip, :info)';
    $params = [
      ':userId' => $userId,
      ':level' => $level->value,
      ':ip' => $ip,
      ':info' => substr($info, 0, 500),
      ];
    return $this->execute($sql, $params);
  }

  public function loadQuestionnaires(): array {

    $sql = "SELECT id, title, type, country_id, active, public FROM questionnaires ORDER BY id;";
    $questionnaires = $this->fetchAll($sql);

    // Get question ids for each questionnaire
    $sql = "SELECT questionnaire_id, question_id FROM questionnaire_questions ORDER BY question_order;";
    $relations = $this->fetchAll($sql);

    foreach ($questionnaires as &$questionnaire) {
      $questionnaire['question_ids'] = array_column(
        array_filter($relations, fn($r) => $r['questionnaire_id'] == $questionnaire['id']),
        'question_id'
      );
    }

    $sql = "SELECT id, text, explanation FROM questions ORDER BY question_order;";
    $questions = $this->fetchAll($sql);

    return [
      'questionnaires' => $questionnaires,
      'questions' => $questions];
  }

  public function loadCountries(): array {
    if (empty($this->countries)) {
      $sql = "SELECT id, name, defaultlanguageid FROM countries ORDER BY CASE WHEN id='UN' THEN 0 ELSE 1 END, name;";
      $dbCountries = $this->fetchAll($sql);
      $this->countries = [];
      foreach ($dbCountries as $country) {
        $flagId = strtolower($country['id']);
        $country['flagFile'] = "/images/flags/$flagId.svg";

        $this->countries[] = $country;
      }
    }

    return $this->countries;
  }

  public function getCountryLanguage(string $countryId): ?string {
    $country = $this->getCountryFromId($countryId);
    return $country? $country['defaultlanguageid'] : DEFAULT_LANGUAGE;
  }

  public function getCountryFromId(string $countryId): ?array {
    $countryId = mb_strtoupper($countryId);
    
    $countries = $this->loadCountries();
    foreach ($countries as $country) {
      if ($country['id'] === $countryId) {
        return $country;
      }
    }
    return null;
  }


  public function getQuestionnaires($publicOnly=false): bool|array {
    $where = $publicOnly? ' WHERE public=1 ' : '';
    $sql = "SELECT id, type, title, country_id, public, active FROM questionnaires $where ORDER BY title;";
    return $this->fetchAll($sql);
  }

  public function getQuestionnaireCountries(): false|array {
    return $this->fetchAllValues("SELECT DISTINCT country_id FROM questionnaires WHERE active=1 ORDER BY country_id;");
  }

  public function saveAnswer($articleId, $questionId, $answer, $answerJustification=null): void {
    $params = [
      ':articleid' => $articleId,
      ':questionid' => $questionId,
      ':answer' => $answer,
      ':answer2' => $answer,
    ];
    $sql = <<<SQL
INSERT INTO answers (articleid, questionid, answer) 
VALUES(:articleid, :questionid, :answer) 
ON DUPLICATE KEY UPDATE 
  answer=:answer2;
SQL;

    $this->execute($sql, $params);

    if ($answerJustification !== null) {
      $params = [
        ':articleid' => $articleId,
        ':questionid' => $questionId,
        ':justification' => substr($answerJustification ?? '', 0, 200),
      ];

      $sql = "UPDATE answers SET explanation=:justification WHERE articleid=:articleid AND questionid=:questionid;";
      $this->execute($sql, $params);
    }
  }

}
