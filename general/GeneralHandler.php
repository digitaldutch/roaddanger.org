<?php

class GeneralHandler {

  private Database $database;
  private User $user;
   
  public function __construct(Database $database, User $user) {
    $this->database = $database;
    $this->user = $user;
  }
  
  public function handleRequest($command): void {
    try {

      $response = match ($command) {
        'login' => $this->login(),
        'logout' => $this->logout(),
        'register' => $this->register(),
        'loadUserData' => $this->loadUserData(),
        'sendPasswordResetInstructions' => $this->sendPasswordResetInstructions(),
        'saveNewPassword' => $this->saveNewPassword(),
        'saveAccount' => $this->saveAccount(),
        'extractDataFromArticle' => $this->extractDataFromArticle(),
        'loadCountryMapOptions' => $this->loadCountryMapOptions(),
        'loadCrashes' => $this->loadCrashes(),
        'getArticleText' => $this->getArticleText(),
        'saveArticleCrash' => $this->saveArticleCrash(),
        'deleteArticle' => $this->deleteArticle(),
        'deleteCrash' => $this->deleteCrash(),
        'crashToStreamTop' => $this->crashToStreamTop(),
        'crashModerateOK' => $this->crashModerateOK(),
        'articleModerateOK' => $this->articleModerateOK(),
        'getArticleWebpageMetaData' => $this->getArticleWebpageMetaData(),
        'mergeCrashes' => $this->mergeCrashes(),
        'saveLanguage' => $this->saveLanguage(),
        'saveAnswer' => $this->saveAnswer(),
        'saveExplanation' => $this->saveExplanation(),
        'getArticleQuestionnairesAndText' => $this->getArticleQuestionnairesAndText(),
        'getQuestions' => $this->getQuestions(),
        'getStatistics' => $this->getStatistics(),
        'getMediaHumanizationData' => $this->getMediaHumanizationData(),
        default => throw new Exception('Invalid command'),
      };

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }
  
  private function respondWithSucces(string $response): void {
    header('Content-Type: application/json');
    echo $response;
  }
  
  private function respondWithError(string $error): void {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => $error]);
  }
  
  private function login(): string {
    if (is_null($_REQUEST['email']) || is_null($_REQUEST['password'])) dieWithJSONErrorMessage('Invalid AJAX login call.');

    $email = $_REQUEST['email'];
    $password = $_REQUEST['password'];
    $stayLoggedIn = (int)getRequest('stayLoggedIn', 0) === 1;

    global $user;
    $user->login($email, $password, $stayLoggedIn);

    return json_encode($user->info());
  }

  private function logout(): string {
    global $user;
    $user->logout();

    return json_encode($user->info());
  }

  private function sendPasswordResetInstructions(): string {
    try {
      $result = [];
      if (! isset($_REQUEST['email'])) throw new \Exception('No email adres');
      $email = trim($_REQUEST['email']);

      global $user;
      $recoveryID = $user->resetPasswordRequest($email);
      if (! $recoveryID) throw new \Exception('Interne fout: Kan geen recoveryID aanmaken');

      $domain = $_SERVER['SERVER_NAME'];
      $subject = $domain . ' wachtwoord resetten';
      $server = $_SERVER['SERVER_NAME'];
      $emailEncoded = urlencode($email);
      $recoveryIDEncoded = urlencode($recoveryID);
      $body = <<<HTML
<p>Hi,</p>

<p>We received a request to reset the password for $email. To reset your password, click the link below:</p>

<p><a href="https://$server/account/resetpassword?email=$emailEncoded&recoveryid=$recoveryIDEncoded">Wachtwoord resetten</a></p>

<p>Greetings,<br>
$domain</p>
HTML;

      if (sendEmail($email, $subject, $body, [])) $result['ok'] = true;
      else throw new \Exception('Interne server fout: Kan email niet verzenden.');
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }
  private function saveNewPassword(): string {
    try {
      if (! isset($_REQUEST['password'])) throw new \Exception('Geen password opgegeven');
      if (! isset($_REQUEST['recoveryid'])) throw new \Exception('Geen recoveryid opgegeven');
      if (! isset($_REQUEST['email'])) throw new \Exception('Geen email opgegeven');

      $password = $_REQUEST['password'];
      $recoveryId = $_REQUEST['recoveryid'];
      $email = $_REQUEST['email'];

      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      $sql = <<<SQL
UPDATE users SET 
  passwordhash=:passwordhash,
  passwordrecoveryid = null
WHERE email=:email 
  AND passwordrecoveryid=:passwordrecoveryid;
SQL;

      $params = [
        ':passwordhash'       => $passwordHash,
        ':email'              => $email,
        ':passwordrecoveryid' => $recoveryId,
      ];

      global $database;
      if (($database->execute($sql, $params, true)) && ($database->rowCount ===1)) {
        $result = ['ok' => true];
      } else $result = ['ok' => false, 'error' => 'Wachtwoord link is verlopen of email is onbekend'];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function saveAccount(): string {
    try {
      $newUser = json_decode(file_get_contents('php://input'));

      global $user;
      $user->saveAccount($newUser);

      $result = ['ok' => true];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function loadUserData(): string {
    try {
      $data = json_decode(file_get_contents('php://input'));

      global $user;
      global $database;

      $user->getTranslations();
      $result = [
        'ok' => true,
        'user' => $user->info(),
        'countries' => $database->loadCountries(),
      ];

      if (isset($data->getQuestionnaireCountries) && $data->getQuestionnaireCountries === true) {
        $result['questionnaireCountries'] = $database->getQuestionnaireCountries();
      }
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function register(): string {
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      global $user;
      $user->register($data['firstname'], $data['lastname'], $data['email'], $data['password']);

      $result = ['ok' => true];
    } catch (\Exception $e) {
      $result = ['error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function extractDataFromArticle():false|string {
    $article = json_decode(file_get_contents('php://input'));

    global $database;
    try {
      $prompt = $database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='article_analist';");

      require_once '../general/OpenRouterAIClient.php';

      $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, $article);

      $openrouter = new OpenRouterAIClient();
      $AIResults = $openrouter->chatWithMeta($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

      $AIResults['response'] = json_decode($AIResults['response']);

      // Get coordinates also from geocoder as AI is not very good at it yet
      $geocoder_prompt = $AIResults['response']->location->geocoder_prompt;
      $AIResults['response']->location->geocoder_coordinates = geocodeLocation($geocoder_prompt);

      $result = ['ok' => true, 'data' => $AIResults['response']];
    } catch (\Throwable $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function loadCountryMapOptions(): false|string {
    global $database;
    global $user;

    try {
      $data = json_decode(file_get_contents('php://input'), true);

      $sql = 'SELECT options from countries WHERE id=:id;';
      $params = [':id' => $data['countryId']];
      $optionsJson = $database->fetchSingleValue($sql, $params);

      if (! isset($optionsJson)) throw new \Exception('No country options found for ' . $user->countryId);
      $options     = json_decode($optionsJson);

      $result = [
        'ok' => true,
        'options' => $options,
      ];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function saveArticleCrash(): string {
    global $database;
    global $user;

    try {
      $data = json_decode(file_get_contents('php://input'), true);
      $article = $data['article']?? null;
      $crash = $data['crash'];
      $isNewCrash = (! isset($crash['id'])) || ($crash['id'] <= 0);
      $moderationRequired = ! $user->isModerator();
      $crashIsAwaitingModeration = $moderationRequired && $isNewCrash;
      $articleIsAwaitingModeration = $moderationRequired && (! $crashIsAwaitingModeration);

      $database->beginTransaction();

      // Check if new article url already in the database
      if ($article['id'] < 1) {
        $exists = $this->urlExists($database, $article['url']);
        if ($exists) throw new \Exception("<a href='/{$exists['crashId']}}' style='text-decoration: underline;'>There is already a crash with this link</a>", 1);
      }

      $streamToTopType = StreamTopType::new;

      if (! $isNewCrash) {
        // Update existing crash
        $streamToTopType = StreamTopType::edited;

        // We don't set awaiting moderation for updates because it is unfriendly for helpers. We may need to come back on this policy if it is misused.
        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
        $sql = <<<SQL
  UPDATE crashes SET
    streamdatetime      = current_timestamp,
    streamtoptype       = 1, 
    streamtopuserid     = :userid,
    date                = :date,
    countryid           = :countryid,
    latitude            = :latitude,
    longitude           = :longitude,
    location            = POINT(:longitude2, :latitude2), 
    locationdescription = :locationdescription,
    unilateral          = :unilateral,
    pet                 = :pet,
    trafficjam          = :trafficjam
  WHERE id=:id 
  $sqlANDOwnOnly
SQL;
        $params = [
          ':id' => $crash['id'],
          ':userid' => $user->id,
          ':date' => $crash['date'],
          ':countryid' => $crash['countryid'],
          ':latitude' => empty($crash['latitude'])?  null : $crash['latitude'],
          ':longitude' => empty($crash['longitude'])? null : $crash['longitude'],
          ':latitude2' => empty($crash['latitude'])?  null : $crash['latitude'],
          ':longitude2' => empty($crash['longitude'])? null : $crash['longitude'],
          ':locationdescription' => $crash['locationdescription'],
          ':unilateral' => intval($crash['unilateral']),
          ':pet' => intval($crash['pet']),
          ':trafficjam' => intval($crash['trafficjam']),
        ];

        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new \Exception('Helpers can only edit their own crashes');

      } else {
        // New crash
        $sql = <<<SQL
  INSERT INTO crashes (userid, awaitingmoderation, title, date, countryid, location, latitude, longitude, locationdescription, unilateral, pet, trafficjam)
  VALUES (:userid, :awaitingmoderation, :title, :date, :countryId, POINT(:longitude2, :latitude2), :latitude, :longitude, :locationdescription, :unilateral, :pet, :trafficjam);
SQL;

        $params = [
          ':userid' => $user->id,
          ':awaitingmoderation' => intval($moderationRequired),
          ':date' => $crash['date'],
          ':title' => $article['title'],
          ':countryId' => $crash['countryid'],
          ':latitude' => $crash['latitude'],
          ':longitude' => $crash['longitude'],
          ':latitude2' => $crash['latitude'],
          ':longitude2' => $crash['longitude'],
          ':locationdescription' => $crash['locationdescription'],
          ':unilateral' => intval($crash['unilateral']),
          ':pet' => intval($crash['pet']),
          ':trafficjam' => intval($crash['trafficjam']),
        ];

        $database->execute($sql, $params);
        $crash['id'] = (int)$database->lastInsertID();
      }

      // Save crash persons
      $sql = "DELETE FROM crashpersons WHERE crashid=:crashId;";
      $params = ['crashId' => $crash['id']];
      $database->execute($sql, $params);

      $sql = <<<SQL
INSERT INTO crashpersons (crashid, groupid, transportationmode, health, child, underinfluence, hitrun) 
VALUES (:crashid, :groupid, :transportationmode, :health, :child, :underinfluence, :hitrun);
SQL;
      $dbStatement = $database->prepare($sql);
      foreach ($crash['persons'] AS $person){
        $params = [
          ':crashid'            => $crash['id'],
          ':groupid'            => $person['groupid']?? null,
          ':transportationmode' => $person['transportationmode'],
          ':health'             => $person['health'],
          ':child'              => intval($person['child']),
          ':underinfluence'     => intval($person['underinfluence']),
          ':hitrun'             => intval($person['hitrun']),
        ];
        $dbStatement->execute($params);
      }

      if ($article['id'] > 0){
        // Update article

        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';

        $sql = <<<SQL
  UPDATE articles SET
    crashid       = :crashId,
    url           = :url,
    title         = :title,
    text          = :text,
    alltext       = :alltext,
    publishedtime = :date,
    sitename      = :sitename,
    urlimage      = :urlimage
    WHERE id=:id $sqlANDOwnOnly
SQL;
        $params = [
          ':crashId'  => $crash['id'],
          ':url'      => $article['url'],
          ':title'    => substr($article['title'], 0 , 500),
          ':text'     => substr($article['text'], 0 , 500),
          ':alltext'  => substr($article['alltext'], 0 , 10000),
          ':sitename' => substr($article['sitename'], 0 , 200),
          ':date'     => $article['date'],
          ':urlimage' => $article['urlimage'],
          ':id'       => $article['id'],
        ];

        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;
        $database->execute($sql, $params, true);

        if (! $isNewCrash) $streamToTopType = StreamTopType::edited;

      } else {
        // New article
        $sql = <<<SQL
  INSERT INTO articles (userid, awaitingmoderation, crashid, url, title, text, alltext, publishedtime, sitename, urlimage)
  VALUES (:userid, :awaitingmoderation, :crashid, :url, :title, :text, :alltext, :publishedtime, :sitename, :urlimage);
SQL;
        // Article moderation is only required if the crash is not awaiting moderation
        $article['userid'] = $user->id;
        $article['crashid'] = $crash['id'];
        $article['awaitingmoderation'] = $articleIsAwaitingModeration;
        $params = [
          ':userid'             => $article['userid'],
          ':awaitingmoderation' => intval($article['awaitingmoderation']),
          ':crashid'            => $article['crashid'],
          ':url'                => $article['url'],
          ':title'              => substr($article['title'], 0, 500),
          ':text'               => substr($article['text'], 0, 500),
          ':alltext'            => substr($article['alltext'], 0, 10000),
          ':sitename'           => substr($article['sitename'], 0, 200),
          ':publishedtime'      => $article['date'],
          ':urlimage'           => $article['urlimage'],
        ];

        $database->execute($sql, $params);
        $article['id'] = (int)$database->lastInsertID();

        if (! $isNewCrash) $streamToTopType =  StreamTopType::articleAdded;
      }

      $this->setCrashStreamTop($database, $crash['id'], $user->id, $streamToTopType);

      $database->commit();
      $result = [
        'ok' => true,
        'crashId' => $crash['id'],
      ];

      $sqlArticleSelect = $this->getArticleSelect();
      $sqlArticle = "$sqlArticleSelect WHERE ar.ID=:id";

      $DBArticle = $database->fetch($sqlArticle, ['id' => $article['id']]);
      $DBArticle = $this->cleanArticleDBRow($DBArticle);

      $result['article'] = $DBArticle;

    } catch (\Throwable $e){
      $database->rollback();
      $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
    }

    return json_encode($result);
  }

  private function deleteArticle(): string {
    try{
      $crashId = (int)$_REQUEST['id'];
      if ($crashId > 0) {
        global $user;
        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
        $sql    = "DELETE FROM articles WHERE id=:id $sqlANDOwnOnly ;";
        $params = [':id' => $crashId];
        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        global $database;
        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new \Exception('Internal error: Cannot delete article.');
      }
      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function deleteCrash(): string {
    try{
      $crashId = (int)$_REQUEST['id'];
      if ($crashId > 0) {
        global $user;
        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
        $sql = "DELETE FROM crashes WHERE id=:id $sqlANDOwnOnly ;";
        $params = [':id' => $crashId];
        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        global $database;
        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new \Exception('Only moderators can delete crashes.');
      }

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function crashToStreamTop(): string {
    try{
      global $user;
      if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to put crashes to top of stream.');

      $crashId = (int)$_REQUEST['id'];
      global $database;
      if ($crashId > 0) $this->setCrashStreamTop($database, $crashId, $user->id, StreamTopType::placedOnTop);
      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  private function crashModerateOK(): string {
    try{
      global $user;
      if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to moderate crashes.');

      $crashId = (int)$_REQUEST['id'];
      if ($crashId > 0){
        $sql    = "UPDATE crashes SET awaitingmoderation=0 WHERE id=:id;";
        $params = [':id' => $crashId];
        global $database;
        $database->execute($sql, $params);
      }
      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function articleModerateOK(): string {
    try {
      global $user;
      if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to moderate crashes.');

      $crashId = (int)$_REQUEST['id'];
      if ($crashId > 0){
        $sql = "UPDATE articles SET awaitingmoderation=0 WHERE id=:id;";
        $params = [':id' => $crashId];
        global $database;
        $database->execute($sql, $params);
      }
      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function getArticleWebpageMetaData(): string {
    try{
      $data = json_decode(file_get_contents('php://input'), true);
      $url = $data['url'];
      $newArticle = $data['newArticle'];

      require_once 'meta_parser_utils.php';
      $resultParser = parseMetaDataFromUrl($url);

      // Check if new article url already in the database.
      global $database;
      if ($newArticle) $urlExists = $this->urlExists($database, $url);
      else $urlExists = false;

      $result = [
        'ok' => true,
        'media' => $resultParser['media'],
        'tagcount' => $resultParser['tagCount'],
        'urlExists' => $urlExists,
      ];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function mergeCrashes(): string {
    try {
      global $user;
      if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to merge crashes.');

      $idFrom = (int)$_REQUEST['idFrom'];
      $idTo = (int)$_REQUEST['idTo'];

      // Move articles to the other crash
      $sql = "UPDATE articles set crashid=:idTo WHERE crashid=:idFrom;";
      $params = [':idFrom' => $idFrom, ':idTo' => $idTo];
      global $database;
      $database->execute($sql, $params);

      $sql = "DELETE FROM crashes WHERE id=:idFrom;";
      $params = [':idFrom' => $idFrom];
      $database->execute($sql, $params);

      $sql = "UPDATE crashes SET streamdatetime=current_timestamp, streamtoptype=1, streamtopuserid=:userId WHERE id=:id";
      $params = [':id' => $idTo, ':userId' => $user->id];
      $database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }
  private function saveLanguage(): string {
    global $user;

    try {
      $languageId = getRequest('id');

      $user->saveLanguage($languageId);

      $result = ['ok' => true];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function saveAnswer(): string {
    try {
      global $user, $database;

      if (! $user->isModerator())  throw new \Exception('Only moderators can save answers');

      $data = json_decode(file_get_contents('php://input'));

      $params = [
        'articleid'  => $data->articleId,
        'questionid' => $data->questionId,
        'answer'     => $data->answer,
        'answer2'    => $data->answer,
      ];
      $sql = "INSERT INTO answers (articleid, questionid, answer) VALUES(:articleid, :questionid, :answer) ON DUPLICATE KEY UPDATE answer=:answer2;";

      $database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function saveExplanation(): string {
    try {
      global $user, $database;
      if (! $user->isModerator())  throw new \Exception('Only moderators can save explanations');

      $data = json_decode(file_get_contents('php://input'));

      $params = [
        'articleid' => $data->articleId,
        'questionid' => $data->questionId,
        'explanation' => $data->explanation,
      ];
      $sql = "UPDATE answers SET explanation= :explanation WHERE articleid=:articleid AND questionid=:questionid;";
      $database->execute($sql, $params);

      $result = ['ok' => true];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function getArticleQuestionnairesAndText(): string {
    try {
      global $user, $database;
      if (! $user->isModerator())  throw new \Exception('Only moderators can edit article questions');

      $data = json_decode(file_get_contents('php://input'), true);

      if (! isset($data['crashCountryId'])) throw new \Exception('No crashCountryId found');
      if ($data['articleId'] <= 0) throw new \Exception('No article id found');

      if ($data['crashCountryId'] === 'UN') $whereCountry = " ";
      else $whereCountry = " AND country_id IN ('UN', '" . $data['crashCountryId'] . "') ";

      $sql = <<<SQL
SELECT
  id,
  title,
  country_id,
  type
FROM questionnaires
WHERE active = 1
  $whereCountry
ORDER BY id;
SQL;

      $questionnaires = $database->fetchAll($sql);

      $sql = <<<SQL
SELECT
q.id,
q.text,
q.explanation,
a.answer,
a.explanation AS answerExplanation
FROM questionnaire_questions qq
  LEFT JOIN questions q ON q.id = qq.question_id
  LEFT JOIN answers a ON q.id = a.questionid AND articleid=:articleId
WHERE qq.questionnaire_id = :questionnaire_id
ORDER BY qq.question_order;
SQL;

      $statementQuestions = $database->prepare($sql);
      $questionnaire['questions'] = [];
      foreach ($questionnaires as &$questionnaire) {
        $params = [':articleId' => $data['articleId'], 'questionnaire_id' => $questionnaire['id']];

        $questionnaire['questions'] = $database->fetchAllPrepared($statementQuestions, $params);
      }

      $sql = "SELECT alltext FROM articles WHERE id=:id;";
      $params = [':id' => $data['articleId']];
      $articleText = $database->fetchSingleValue($sql, $params);

      $result = ['ok' => true, 'text' => $articleText, 'questionnaires' => $questionnaires];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function getQuestions(): string {
    try {
      global $user, $database;
      if (! $user->admin) throw new \Exception('Admins only');

      $questionnaireId = (int)$_REQUEST['questionnaireId'];

      $sql = <<<SQL
SELECT
  q.id,
  q.text
FROM questionnaire_questions qq
LEFT JOIN questions q ON qq.question_id = q.id
WHERE qq.questionnaire_id = :questionnaireId
ORDER BY qq.question_order;
SQL;

      $params = [':questionnaireId' => $questionnaireId];
      $questions = $database->fetchAll($sql, $params);

      $result = ['ok' => true, 'questions' => $questions];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function getStatistics(): string {
    try {
      global $user, $database;
      $data = json_decode(file_get_contents('php://input'), true);

      $type   = $data['type'] ?? '';
      $filter = $data['filter'] ?? '';

      if ($type === 'general') $stats = $this->getStatsDatabase($database);
      else if ($type === 'crashPartners') $stats = $this->getStatsCrashPartners($database, $filter);
      else if ($type === 'media_humanization') $stats = $this->getStatsMediaHumanization();
      else $stats = $this->getStatsTransportation($database, $filter);

      $user->getTranslations();
      $result = [
        'ok' => true,
        'statistics' => $stats,
        'user' => $user->info(),
      ];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function getMediaHumanizationData(): string {
    try {
      $stats = $this->getStatsMediaHumanization();

      $result = ['ok' => true,
        'statistics' => $stats,
      ];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  private function getArticleText(): string {
    try{
      $articleId = (int)$_REQUEST['id'];

      if ($articleId > 0){
        $params = [':id' => $articleId];
        $sql  = "SELECT alltext FROM articles WHERE id=:id;";

        global $database;
        $text = $database->fetchSingleValue($sql, $params);
      }

      $result = ['ok' => true, 'text' => $text];
    } catch (\Exception $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  private function loadCrashes(): string {
    global $database;
    global $user;

    try {
      $data = json_decode(file_get_contents('php://input'), true);

      $offset = $data['offset']?? 0;
      $count = $data['count']?? 20;
      $crashId = $data['id']?? null;
      $moderations = $data['moderations']?? 0;
      $sort = $data['sort']?? '';
      $filter = $data['filter'];

      if ($count > 1000) throw new \Exception('Internal error: Count to high.');
      if ($moderations && (! $user->isModerator())) throw new \Exception('Moderaties zijn alleen zichtbaar voor moderators.');

      $crashes = [];
      $articles = [];
      $params = [];
      $sqlModerated = '';
      if ($moderations) {
        $sqlModerated = ' (c.awaitingmoderation=1) OR (c.id IN (SELECT crashid FROM articles WHERE awaitingmoderation=1)) ';
      } else if ($crashId === null) {
        // Individual pages are always shown and *not* moderated.
        $sqlModerated = $user->isModerator()? '':  ' ((c.awaitingmoderation=0) || (c.userid=:useridModeration)) ';
        if ($sqlModerated) $params[':useridModeration'] = $user->id;
      }

      // Sort on dead=3, injured=2, unknown=0, uninjured=1
      $sql = <<<SQL
SELECT 
  groupid,
  transportationmode,
  health,
  child,
  underinfluence,
  hitrun
FROM crashpersons
WHERE crashid=:crashid
ORDER BY health IS NULL, FIELD(health, 3, 2, 0, 1);
SQL;

      $DbStatementCrashPersons = $database->prepare($sql);

      $sql = <<<SQL
SELECT DISTINCT 
  c.id,
  c.userid,
  c.createtime,
  c.streamdatetime,
  c.streamtopuserid,     
  c.streamtoptype,
  c.awaitingmoderation,
  c.date,
  c.countryid,
  ST_X(c.location) AS longitude,
  ST_Y(c.location) AS latitude,
  c.locationdescription,
  c.unilateral,
  c.pet, 
  c.trafficjam, 
  CONCAT(u.firstname, ' ', u.lastname) AS user, 
  CONCAT(tu.firstname, ' ', tu.lastname) AS streamtopuser 
FROM crashes c
LEFT JOIN users u  on u.id  = c.userid 
LEFT JOIN users tu on tu.id = c.streamtopuserid
SQL;

      $SQLWhere = '';
      if ($crashId !== null) {
        // Single crash
        $params = [':id' => $crashId];
        $SQLWhere = " WHERE c.id=:id ";
      } else {

        $joinArticlesTable = false;
        $joinPersonsTable  = false;
        $SQLJoin = '';

        // Only do full-text search if the text has 3 characters or more
        if (isset($filter['text']) && strlen($filter['text']) > 2){
          addSQLWhere($SQLWhere, "(MATCH(c.title, c.text) AGAINST (:search IN BOOLEAN MODE) OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))");
          $joinArticlesTable = true;
          $params[':search']  = $filter['text'];
          $params[':search2'] = $filter['text'];
        }

        $this->addPeriodWhereSql($SQLWhere, $params, $filter);

        if (! empty($filter['country'])){
          if ($filter['country'] !== 'UN') {
            addSQLWhere($SQLWhere, "c.countryid=:country");
            $params[':country'] = $filter['country'];
          }
        }

        if (! empty($filter['siteName'])){
          $joinArticlesTable = true;
          addSQLWhere($SQLWhere, " LOWER(ar.sitename) LIKE :sitename ");
          $params[':sitename'] = "%{$filter['siteName']}%";
        }

        addHealthWhereSql($SQLWhere, $joinPersonsTable, $filter);

        if (isset($filter['child']) && ($filter['child'] === 1)){
          $joinPersonsTable = true;
          addSQLWhere($SQLWhere, " cp.child=1 ");
        }

        if (isset($filter['area'])) {
          $sqlArea = "latitude BETWEEN :latMin AND :latMax AND longitude BETWEEN :lonMin AND :lonMax";

          addSQLWhere($SQLWhere, $sqlArea);
          $params[':latMin'] = $filter['area']['latMin'];
          $params[':latMax'] = $filter['area']['latMax'];
          $params[':lonMin'] = $filter['area']['lonMin'];
          $params[':lonMax'] = $filter['area']['lonMax'];
        }

        if (isset($filter['persons']) && (count($filter['persons'])) > 0) {
          $joinPersonsTable = true;
          addPersonsWhereSql($SQLWhere, $SQLJoin, $filter['persons']);
        }

        if ($sqlModerated) addSQLWhere($SQLWhere, $sqlModerated);

        if ($joinArticlesTable) $SQLJoin .= ' JOIN articles ar ON c.id = ar.crashid ';
        if ($joinPersonsTable) $SQLJoin .= ' JOIN crashpersons cp on c.id = cp.crashid ';


        $orderField = match ($sort) {
          'crashDate'   => 'c.date DESC, c.streamdatetime DESC',
          'lastChanged' => 'c.streamdatetime DESC',
          default       => 'c.date DESC, c.streamdatetime DESC',
        };

        $SQLWhere = <<<SQL
   $SQLJoin      
   $SQLWhere
  ORDER BY $orderField 
  LIMIT $offset, $count
SQL;
      }

      $sql .= $SQLWhere;
      $ids = [];
      $articles = $database->fetchAll($sql, $params);
      foreach ($articles as $crash) {
        $crash['createtime'] = datetimeDBToISO8601($crash['createtime']);
        $crash['streamdatetime'] = datetimeDBToISO8601($crash['streamdatetime']);
        $crash['awaitingmoderation'] = $crash['awaitingmoderation'] == 1;

        $crash['unilateral'] = $crash['unilateral'] == 1;
        $crash['pet'] = $crash['pet'] == 1;
        $crash['trafficjam'] = $crash['trafficjam'] == 1;

        $crash['persons'] = $database->fetchAllPrepared($DbStatementCrashPersons, ['crashid' => $crash['id']]);

        $ids[] = $crash['id'];
        $crashes[] = $crash;
      }

      if (count($crashes) > 0){
        $params = [];
        $sqlModerated = '';
        // In the moderation and for individual crash pages, all crashes are shown
        if (! $moderations && ($crashId === null)) {
          $sqlModerated = $user->isModerator()? '':  ' AND ((ar.awaitingmoderation=0) || (ar.userid=:useridModeration)) ';
          if ($sqlModerated) $params[':useridModeration'] = $user->id;
        }

        $commaArrays = implode (", ", $ids);
        $sqlArticleSelect = $this->getArticleSelect();
        $sqlArticles = <<<SQL
$sqlArticleSelect
WHERE ar.crashid IN ($commaArrays)
 $sqlModerated
ORDER BY ar.streamdatetime DESC
SQL;

        $articles = $database->fetchAll($sqlArticles, $params);
        foreach ($articles as &$article) {
          $article = $this->cleanArticleDBRow($article);
        }
      }

      $result = ['ok' => true, 'crashes' => $crashes, 'articles' => $articles];

    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

  // ***** Private functions *****
  private function cleanArticleDBRow($article): array {
    $article['awaitingmoderation'] = $article['awaitingmoderation'] == 1;
    $article['hasalltext'] = ($article['hasalltext'] ?? 0) == 1;
    $article['createtime'] = datetimeDBToISO8601($article['createtime']);
    $article['publishedtime'] = isset($article['publishedtime']) ? datetimeDBToISO8601($article['publishedtime']) : null;
    $article['streamdatetime'] = datetimeDBToISO8601($article['streamdatetime']);
    // NOTE: Do not sanitize strings. We handle escaping in JavaScript

    return $article;
  }

  private function getArticleSelect(): string {
    return <<<SQL
SELECT
  ar.id,
  ar.userid,
  ar.awaitingmoderation,
  ar.crashid,
  ar.title,
  ar.text,
  IF(ar.alltext > '', 1, 0) AS hasalltext,
  ar.createtime,
  ar.publishedtime,
  ar.streamdatetime,
  ar.sitename,
  ar.url,
  ar.urlimage,
  CONCAT(u.firstname, ' ', u.lastname) AS user 
FROM articles ar
JOIN users u on u.id = ar.userid
SQL;
  }

  private function addPeriodWhereSql(&$sqlWhere, &$params, $filter): void {
    if ((! isset($filter['period'])) || ($filter['period'] === '')) return;

    switch ($filter['period']) {
      case 'today':
        addSQLWhere($sqlWhere, ' DATE(c.date) = CURDATE() ');
        break;
      case 'yesterday':
        addSQLWhere($sqlWhere, ' DATE(c.date) = SUBDATE(CURDATE(), 1) ');
        break;
      case '7days':
        addSQLWhere($sqlWhere, ' DATE(c.date) > SUBDATE(CURDATE(), 7) ');
        break;
      case '30days':
        addSQLWhere($sqlWhere, ' DATE(c.date) > SUBDATE(CURDATE(), 30) ');
        break;
      case '365days':
        addSQLWhere($sqlWhere, ' DATE(c.date) > SUBDATE(CURDATE(), 365) ');
        break;
      case 'decorrespondent':
        addSQLWhere($sqlWhere, " DATE(c.date) >= '2019-01-14' AND DATE (c.date) <= '2019-01-20' ");
        break;
      case 'custom': {
        if ($filter['dateFrom'] !== '') {
          addSQLWhere($sqlWhere, " DATE(c.date) >= :searchDateFrom ");
          $params[':searchDateFrom'] = date($filter['dateFrom']);
        }

        if ($filter['dateTo'] !== '') {
          addSQLWhere($sqlWhere, " DATE(c.date) <= :searchDateTo ");
          $params[':searchDateTo'] = date($filter['dateTo']);
        }

        break;
      }
    }
  }

  private function getStatsCrashPartners(Database $database, array $filter): array{
    $SQLWhere = '';
    $SQLJoin = '';
    $params = [];
    $joinArticlesTable = false;
    $joinPersonsTable = true;

    // Only do full-text search if the text has 3 characters or more
    if (isset($filter['text']) && strlen($filter['text']) > 2){
      addSQLWhere($SQLWhere, "(MATCH(c.title, c.text) AGAINST (:search IN BOOLEAN MODE) OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))");
      $joinArticlesTable = true;
      $params[':search']  = $filter['text'];
      $params[':search2'] = $filter['text'];
    }

    addHealthWhereSql($SQLWhere, $joinPersonsTable, $filter);
    $this->addPeriodWhereSql($SQLWhere, $params, $filter);

    if ($filter['child'] === 1) addSQLWhere($SQLWhere, " cp.child=1 ");

    if ($filter['country'] !== 'UN'){
      addSQLWhere($SQLWhere, "c.countryid=:country");
      $params[':country'] = $filter['country'];
    }

    if (! empty($filter['siteName'])){
      $joinArticlesTable = true;
      addSQLWhere($SQLWhere, " LOWER(ar.sitename) LIKE :sitename ");
      $params[':sitename'] = "%{$filter['siteName']}%";
    }

    if ($joinArticlesTable) $SQLJoin .= ' JOIN articles ar ON c.id = ar.crashid ';

    $sqlCrashesWithDeath = <<<SQL
  SELECT
    c.id
  FROM crashpersons cp
  JOIN crashes c ON cp.crashid = c.id
  $SQLJoin
  $SQLWhere
SQL;

    // Get all persons from crashes with dead
    $sql = <<<SQL
SELECT
  c.id AS crashid,
  c.unilateral,
  cp.id,
  transportationmode,
  health
FROM crashpersons cp
JOIN crashes c ON cp.crashid = c.id
WHERE
  c.id IN ($sqlCrashesWithDeath);
SQL;

    $crashVictims = [];
    $crashes = $database->fetchAllGroup($sql, $params);
    foreach ($crashes as $crashPersons) {
      $crashInjured             = [];
      $crashTransportationModes = [];
      $unilateralCrash          = false;

      // get crash dead persons
      foreach ($crashPersons as $person){
        $person['unilateral'] = $person['unilateral'] === 1;

        if ($person['unilateral'] === true) $unilateralCrash = true;

        if ($person['health'] === 3) $crashInjured[] = $person;
        else if (($filter['healthInjured'] === 1) && ($person['health'] === 2)) $crashInjured[] = $person;
        if (! in_array($person['transportationmode'], $crashTransportationModes)) $crashTransportationModes[] = $person['transportationmode'];
      }

      foreach ($crashInjured as $personInjured){
        if (! isset($crashVictims[$personInjured['transportationmode']])) $crashVictims[$personInjured['transportationmode']] = [];

        // Add crash partner
        if ($unilateralCrash){
          // Unilateral transportationMode = -1
          if (! isset($crashVictims[$personInjured['transportationmode']][-1])) $crashVictims[$personInjured['transportationmode']][-1] = 0;
          $crashVictims[$personInjured['transportationmode']][-1] += 1;
        } else if (count($crashTransportationModes) === 1){
          if (! isset($crashVictims[$personInjured['transportationmode']][$personInjured['transportationmode']])) $crashVictims[$personInjured['transportationmode']][$personInjured['transportationmode']] = 0;
          $crashVictims[$personInjured['transportationmode']][$personInjured['transportationmode']] += 1;
        } else {
          foreach ($crashTransportationModes as $transportationMode){
            if ($transportationMode !== $personInjured['transportationmode']) {
              if (! isset($crashVictims[$personInjured['transportationmode']][$transportationMode])) $crashVictims[$personInjured['transportationmode']][$transportationMode] = 0;
              $crashVictims[$personInjured['transportationmode']][$transportationMode] += 1;
            }
          }
        }
      }
    }

    $crashVictims2Out = [];
    foreach ($crashVictims as $victimTransportationMode => $partners){
      arsort($partners);
      $partnersOut = [];
      foreach ($partners as $partnerTransportationMode => $victimCount){
        $partnersOut[] = ['transportationMode' => $partnerTransportationMode, 'victimCount' => $victimCount];
      }

      $crashVictims2Out[] = ['transportationMode' => $victimTransportationMode, 'crashPartners' => $partnersOut];
    }

    return ['crashVictims' => $crashVictims2Out];
  }

  private function getStatsTransportation(Database $database, array $filter): array{
    $stats = [];
    $params = [];
    $SQLJoin = '';
    $SQLWhere = '';
    $joinArticlesTable = false;

    // Only do full-text search if text has 3 characters or more
    if (isset($filter['text']) && strlen($filter['text']) > 2){
      addSQLWhere($SQLWhere, "(MATCH(c.title, c.text) AGAINST (:search IN BOOLEAN MODE) OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))");
      $joinArticlesTable = true;
      $params[':search']  = $filter['text'];
      $params[':search2'] = $filter['text'];
    }

    $this->addPeriodWhereSql($SQLWhere, $params, $filter);

    if ($filter['child'] === 1){
      addSQLWhere($SQLWhere, " cp.child=1 ");
    }

    if ($filter['country'] !== 'UN'){
      addSQLWhere($SQLWhere, "c.countryid=:country");
      $params[':country'] = $filter['country'];
    }

    if ($joinArticlesTable) $SQLJoin .= ' JOIN articles ar ON c.id = ar.crashid ';

    $sql = <<<SQL
SELECT
  transportationmode,
  sum(cp.underinfluence=1) AS underinfluence,
  sum(cp.hitrun=1)         AS hitrun,
  sum(cp.child=1)          AS child,
  sum(cp.health=3)         AS dead,
  sum(cp.health=2)         AS injured,
  sum(cp.health=1)         AS uninjured,
  sum(cp.health=0)         AS healthunknown,
  COUNT(*) AS total
FROM crashpersons cp
JOIN crashes c ON cp.crashid = c.id
  $SQLJoin
  $SQLWhere
GROUP BY transportationmode
ORDER BY dead DESC, injured DESC
SQL;

    $stats['total'] = $database->fetchAll($sql, $params);

    return $stats;
  }

  /**
   * @throws Exception
   */
  private function getStatsMediaHumanization(): array {

    $filter = [
      "questionnaireId" => 7,
      "healthDead" => 0,
      "child" => 0,
      "noUnilateral" =>  1,
      "year" => "",
      "timeSpan" => "from2022",
      "country" => "NL",
      "persons" => [],
      "minArticles" => 5,
      "public" => 1,
    ];

    $group = 'month';

    require_once '../general/Cache.php';
    $cacheResponse = Cache::get('getStatsMediaHumanization', 600);

    if ($cacheResponse === null) {
      require_once '../research/Research.php';
      $response = Research::loadQuestionnaireResults($filter, $group, []);

      Cache::set('getStatsMediaHumanization', json_encode($response));
    } else {
      $response = json_decode($cacheResponse, true);
    }

    return $response;
  }

  private function getStatsDatabase(Database $database): array {
    $stats = [];

    $stats['total'] = [];
    $sql = "SELECT COUNT(*) AS count FROM crashes";
    $stats['total']['crashes'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles";
    $stats['total']['articles'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE a.health=3";

    $stats['total']['dead'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE a.health=2";
    $stats['total']['injured'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM users";
    $stats['total']['users'] = $database->fetchSingleValue($sql);


    $stats['today'] = [];
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`date`) = CURDATE()";
    $stats['today']['crashes'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`publishedtime`) = CURDATE()";
    $stats['today']['articles'] = $database->fetchSingleValue($sql);
    $stats['today']['users'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) = CURDATE() AND a.health=3";
    $stats['today']['dead'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) = CURDATE() AND a.health=2";
    $stats['today']['injured'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`createtime`) = CURDATE()";
    $stats['today']['crashesAdded'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`createtime`) = CURDATE()";
    $stats['today']['articlesAdded'] = $database->fetchSingleValue($sql);

    $stats['sevenDays'] = [];
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`date`) >= SUBDATE(CURDATE(), 7)";
    $stats['sevenDays']['crashes'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`publishedtime`) >= SUBDATE(CURDATE(), 7)";
    $stats['sevenDays']['articles'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) >= SUBDATE(CURDATE(), 7) AND a.health=3";
    $stats['sevenDays']['dead'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) >= SUBDATE(CURDATE(), 7) AND a.health=2";
    $stats['sevenDays']['injured'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`createtime`) >= SUBDATE(CURDATE(), 7)";
    $stats['sevenDays']['crashesAdded'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`createtime`) >= SUBDATE(CURDATE(), 7)";
    $stats['sevenDays']['articlesAdded'] = $database->fetchSingleValue($sql);

    $stats['deCorrespondent'] = [];
    $sql = "SELECT COUNT(*) FROM crashes WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20'";
    $stats['deCorrespondent']['crashes'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM articles WHERE DATE (`publishedtime`) >= '2019-01-14' AND DATE (`publishedtime`) <= '2019-01-20'";
    $stats['deCorrespondent']['articles'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes WHERE DATE (`createtime`) >= '2019-01-14' AND DATE (`createtime`) <= '2019-01-20'";
    $stats['deCorrespondent']['crashesAdded'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM articles WHERE DATE (`createtime`) >= '2019-01-14' AND DATE (`createtime`) <= '2019-01-20'";
    $stats['deCorrespondent']['articlesAdded'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20' AND a.health=3";
    $stats['deCorrespondent']['dead'] = $database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20' AND a.health=2";
    $stats['deCorrespondent']['injured'] = $database->fetchSingleValue($sql);

    return $stats;
  }

  private function urlExists(Database $database, string $url): array | false {
    $sql = "SELECT id, crashid FROM articles WHERE url=:url LIMIT 1;";
    $params = [':url' => $url];
    $dbResults = $database->fetchAll($sql, $params);
    foreach ($dbResults as $found) {
      return [
        'articleId' => $found['id'],
        'crashId'   => $found['crashid'],
      ];
    }
    return false;
  }

  private function setCrashStreamTop(Database $database, int $crashId, int $userId, StreamTopType $streamTopType): void {
    $sql = <<<SQL
  UPDATE crashes SET
    streamdatetime  = current_timestamp,
    streamtoptype   = :streamTopType, 
    streamtopuserid = :userid
  WHERE id=:id;
SQL;
    $params = [
      ':id' => $crashId,
      ':streamTopType' => $streamTopType->value,
      ':userid' => $userId,
    ];
    $database->execute($sql, $params);
  }

}