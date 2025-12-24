<?php

require_once 'AjaxHandler.php';

class GeneralHandler extends AjaxHandler {

  public function handleRequest($command): void {
    try {

      $response = match ($command) {
        'login' => $this->login(),
        'logout' => $this->logout(),
        'register' => $this->register(),
        'loadUserData' => $this->loadUserData(),
        'sendPasswordResetInstructions' => $this->sendPasswordResetInstructions(),
        'saveNewPassword' => $this->saveNewPassword(),
        'saveUser' => $this->saveUser(),
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

  private function login(): array {
    if (is_null($_REQUEST['email']) || is_null($_REQUEST['password'])) dieWithJSONErrorMessage('Invalid AJAX login call.');

    $email = $_REQUEST['email'];
    $password = $_REQUEST['password'];
    $stayLoggedIn = (int)getRequest('stayLoggedIn', 0) === 1;

    $this->user->login($email, $password, $stayLoggedIn);

    return $this->user->info();
  }

  private function logout(): array {
    $this->user->logout();

    return $this->user->info();
  }

  /**
   * @throws \PHPMailer\PHPMailer\Exception
   * @throws Exception
   */
  private function sendPasswordResetInstructions(): array {
    if (! isset($_REQUEST['email'])) throw new \Exception('No email adres');
    $email = trim($_REQUEST['email']);

    $recoveryID = $this->user->resetPasswordRequest($email);
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

    if (! sendEmail($email, $subject, $body)) {
      throw new \Exception('Interne server fout: Kan email niet verzenden.');
    }

    return [];
  }

  /**
   * @throws Exception
   */
  private function saveNewPassword(): array {
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

    if (! $this->database->execute($sql, $params, true) || ($this->database->rowCount !== 1)) {
      throw new Exception('Wachtwoord link is verlopen of email is onbekend');
    }

    return [];
  }

  /**
   * @throws Exception
   */
  private function saveUser(): array {
    $newUser = $this->input;

    $this->user->save($newUser);

    return [];
  }
  private function loadUserData(): array {
    $this->user->getTranslations();

    $result = [
      'user' => $this->user->info(),
      'countries' => $this->database->loadCountries(),
    ];

    if (isset($this->input['getQuestionnaireCountries']) && $this->input['getQuestionnaireCountries'] === true) {
      $result['questionnaireCountries'] = $this->database->getQuestionnaireCountries();
    }

    return $result;
  }
  private function register(): array {
    $this->user->register($this->input['firstname'], $this->input['lastname'],
      $this->input['email'], $this->input['password']);

    return [];
  }

  /**
   * @throws Exception
   */
  private function extractDataFromArticle(): array {
    $article = $this->input;

    $prompt = $this->database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='article_analist';");

    require_once '../general/OpenRouterAIClient.php';

    $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, (object)$article);

    $openrouter = new OpenRouterAIClient();
    $AIResults = $openrouter->chatWithMeta($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

    $AIResults['response'] = json_decode($AIResults['response']);

    // Get coordinates also from geocoder as AI is not very good at it yet
    $geocoder_prompt = $AIResults['response']->location->geocoder_prompt;
    $AIResults['response']->location->geocoder_coordinates = geocodeLocation($geocoder_prompt);

    return ['data' => $AIResults['response']];
  }

  /**
   * @throws Exception
   */
  private function loadCountryMapOptions(): array {
    $sql = 'SELECT options from countries WHERE id=:id;';
    $params = [':id' => $this->input['countryId']];
    $optionsJson = $this->database->fetchSingleValue($sql, $params);

    if (! isset($optionsJson)) throw new \Exception('No country options found for ' . $this->user->countryId);
    $options = json_decode($optionsJson);

    return [
      'options' => $options,
    ];
  }

  /**
   * @throws Throwable
   */
  private function saveArticleCrash(): array {
    try {
      $article = $this->input['article']?? null;
      $crash = $this->input['crash'];
      $isNewCrash = (! isset($crash['id'])) || ($crash['id'] <= 0);
      $moderationRequired = ! $this->user->isModerator();
      $crashIsAwaitingModeration = $moderationRequired && $isNewCrash;
      $articleIsAwaitingModeration = $moderationRequired && (! $crashIsAwaitingModeration);

      $this->database->beginTransaction();

      // Check if new article url already in the database
      if ($article['id'] < 1) {
        $exists = $this->urlExists($article['url']);
        if ($exists) throw new \Exception("<a href='/{$exists['crashId']}}' style='text-decoration: underline;'>There is already a crash with this link</a>", 1);
      }

      $streamToTopType = StreamTopType::new;

      if (! $isNewCrash) {
        // Update existing crash
        $streamToTopType = StreamTopType::edited;

        // We don't set awaiting moderation for updates because it is unfriendly for helpers. We may need to come back on this policy if it is misused.
        $sqlANDOwnOnly = (! $this->user->isModerator())? ' AND userid=:useridwhere ' : '';
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
          ':userid' => $this->user->id,
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

        if (! $this->user->isModerator()) $params[':useridwhere'] = $this->user->id;

        $this->database->execute($sql, $params, true);
        if ($this->database->rowCount === 0) throw new \Exception('Helpers can only edit their own crashes');

      } else {
        // New crash
        $sql = <<<SQL
  INSERT INTO crashes (userid, awaitingmoderation, title, date, countryid, location, latitude, longitude, locationdescription, unilateral, pet, trafficjam)
  VALUES (:userid, :awaitingmoderation, :title, :date, :countryId, POINT(:longitude2, :latitude2), :latitude, :longitude, :locationdescription, :unilateral, :pet, :trafficjam);
SQL;

        $params = [
          ':userid' => $this->user->id,
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

        $this->database->execute($sql, $params);
        $crash['id'] = (int)$this->database->lastInsertID();
      }

      // Save crash persons
      $sql = "DELETE FROM crashpersons WHERE crashid=:crashId;";
      $params = ['crashId' => $crash['id']];
      $this->database->execute($sql, $params);

      $sql = <<<SQL
INSERT INTO crashpersons (crashid, groupid, transportationmode, health, child, underinfluence, hitrun) 
VALUES (:crashid, :groupid, :transportationmode, :health, :child, :underinfluence, :hitrun);
SQL;
      $dbStatement = $this->database->prepare($sql);
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

        $sqlANDOwnOnly = (! $this->user->isModerator())? ' AND userid=:useridwhere ' : '';

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

        if (! $this->user->isModerator()) $params[':useridwhere'] = $this->user->id;
        $this->database->execute($sql, $params, true);

        if (! $isNewCrash) $streamToTopType = StreamTopType::edited;

      } else {
        // New article
        $sql = <<<SQL
  INSERT INTO articles (userid, awaitingmoderation, crashid, url, title, text, alltext, publishedtime, sitename, urlimage)
  VALUES (:userid, :awaitingmoderation, :crashid, :url, :title, :text, :alltext, :publishedtime, :sitename, :urlimage);
SQL;
        // Article moderation is only required if the crash is not awaiting moderation
        $article['userid'] = $this->user->id;
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

        $this->database->execute($sql, $params);
        $article['id'] = (int)$this->database->lastInsertID();

        if (! $isNewCrash) $streamToTopType =  StreamTopType::articleAdded;
      }

      $this->setCrashStreamTop($crash['id'], $this->user->id, $streamToTopType);

      $this->database->commit();
      $result = [
        'ok' => true,
        'crashId' => $crash['id'],
      ];

      $sqlArticleSelect = $this->getArticleSelect();
      $sqlArticle = "$sqlArticleSelect WHERE ar.ID=:id";

      $DBArticle = $this->database->fetch($sqlArticle, ['id' => $article['id']]);
      $DBArticle = $this->cleanArticleDBRow($DBArticle);

      $result['article'] = $DBArticle;

    } catch (\Throwable $e){
      $this->database->rollback();
      throw $e;
    }

    return $result;
  }

  private function deleteArticle(): array {
    $crashId = (int)$_REQUEST['id'];

    if ($crashId > 0) {
      $sqlANDOwnOnly = (! $this->user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql    = "DELETE FROM articles WHERE id=:id $sqlANDOwnOnly ;";
      $params = [':id' => $crashId];
      if (! $this->user->isModerator()) $params[':useridwhere'] = $this->user->id;

      $this->database->execute($sql, $params, true);
      if ($this->database->rowCount === 0) throw new \Exception('Internal error: Cannot delete article.');
    }

    return [];
  }
  private function deleteCrash(): array {
    $crashId = (int)$_REQUEST['id'];

    if ($crashId > 0) {
      $sqlANDOwnOnly = (! $this->user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM crashes WHERE id=:id $sqlANDOwnOnly ;";
      $params = [':id' => $crashId];
      if (! $this->user->isModerator()) $params[':useridwhere'] = $this->user->id;

      $this->database->execute($sql, $params, true);
      if ($this->database->rowCount === 0) throw new \Exception('Only moderators can delete crashes.');
    }

    return [];
  }

  /**
   * @throws Exception
   */
  private function crashToStreamTop(): array {
    if (! $this->user->isModerator()) throw new \Exception('Only moderators are allowed to put crashes to top of stream.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0) $this->setCrashStreamTop($crashId, $this->user->id, StreamTopType::placedOnTop);

    return [];
  }

  private function crashModerateOK(): array {
    if (! $this->user->isModerator()) throw new \Exception('Only moderators are allowed to moderate crashes.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sql    = "UPDATE crashes SET awaitingmoderation=0 WHERE id=:id;";
      $params = [':id' => $crashId];
      $this->database->execute($sql, $params);
    }

    return [];
  }
  private function articleModerateOK(): array {
    if (! $this->user->isModerator()) throw new \Exception('Only moderators are allowed to moderate crashes.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sql = "UPDATE articles SET awaitingmoderation=0 WHERE id=:id;";
      $params = [':id' => $crashId];
      $this->database->execute($sql, $params);
    }

    return [];
  }

  private function getArticleWebpageMetaData(): array {

    $url = $this->input['url'];
    $newArticle = $this->input['newArticle'];

    require_once 'meta_parser_utils.php';
    $resultParser = parseMetaDataFromUrl($url);

    // Check if new article url already in the database.
    if ($newArticle) $urlExists = $this->urlExists($url);
    else $urlExists = false;

    return [
      'media' => $resultParser['media'],
      'tagcount' => $resultParser['tagCount'],
      'urlExists' => $urlExists,
    ];
  }

  private function mergeCrashes(): array {
    if (! $this->user->isModerator()) throw new \Exception('Only moderators are allowed to merge crashes.');

    $idFrom = (int)$_REQUEST['idFrom'];
    $idTo = (int)$_REQUEST['idTo'];

    // Move articles to the other crash
    $sql = "UPDATE articles set crashid=:idTo WHERE crashid=:idFrom;";
    $params = [':idFrom' => $idFrom, ':idTo' => $idTo];
    $this->database->execute($sql, $params);

    $sql = "DELETE FROM crashes WHERE id=:idFrom;";
    $params = [':idFrom' => $idFrom];
    $this->database->execute($sql, $params);

    $sql = "UPDATE crashes SET streamdatetime=current_timestamp, streamtoptype=1, streamtopuserid=:userId WHERE id=:id";
    $params = [':id' => $idTo, ':userId' => $this->user->id];
    $this->database->execute($sql, $params);

    return [];
  }

  /**
   * @throws Exception
   */
  private function saveLanguage(): array {
    $languageId = getRequest('id');

    $this->user->saveLanguage($languageId);

    return [];
  }
  private function saveAnswer(): array {
    if (! $this->user->isModerator())  throw new \Exception('Only moderators can save answers');

    $params = [
      'articleid' => $this->input['articleId'],
      'questionid' => $this->input['questionId'],
      'answer' => $this->input['answer'],
      'answer2' => $this->input['answer'],
    ];
    $sql = "INSERT INTO answers (articleid, questionid, answer) VALUES(:articleid, :questionid, :answer) ON DUPLICATE KEY UPDATE answer=:answer2;";

    $this->database->execute($sql, $params);

    return [];
  }
  private function saveExplanation(): array {
    if (! $this->user->isModerator())  throw new \Exception('Only moderators can save explanations');

    $params = [
      'articleid' => $this->input['articleId'],
      'questionid' => $this->input['questionId'],
      'explanation' => $this->input['explanation'],
    ];
    $sql = "UPDATE answers SET explanation= :explanation WHERE articleid=:articleid AND questionid=:questionid;";
    $this->database->execute($sql, $params);

    return [];
  }

  /**
   * @throws Exception
   */
  private function getArticleQuestionnairesAndText(): array {
    if (! $this->user->isModerator()) throw new \Exception('Only moderators can edit article questions');

    if (! isset($this->input['crashCountryId'])) throw new \Exception('No crashCountryId found');
    if ($this->input['articleId'] <= 0) throw new \Exception('No article id found');

    if ($this->input['crashCountryId'] === 'UN') $whereCountry = " ";
    else $whereCountry = " AND country_id IN ('UN', '" . $this->input['crashCountryId'] . "') ";

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

    $questionnaires = $this->database->fetchAll($sql);

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

    $statementQuestions = $this->database->prepare($sql);
    $questionnaire['questions'] = [];
    foreach ($questionnaires as &$questionnaire) {
      $params = [':articleId' => $this->input['articleId'], 'questionnaire_id' => $questionnaire['id']];

      $questionnaire['questions'] = $this->database->fetchAllPrepared($statementQuestions, $params);
    }

    $sql = "SELECT alltext FROM articles WHERE id=:id;";
    $params = [':id' => $this->input['articleId']];
    $articleText = $this->database->fetchSingleValue($sql, $params);

    return [
      'text' => $articleText,
      'questionnaires' => $questionnaires
    ];
  }

  /**
   * @throws Exception
   */
  private function getQuestions(): array {
    if (! $this->user->admin) throw new \Exception('Admins only');

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
    $questions = $this->database->fetchAll($sql, $params);

    return ['questions' => $questions];
  }

  /**
   * @throws Exception
   */
  private function getStatistics(): array {
    $type   = $this->input['type'] ?? '';
    $filter = $this->input['filter'] ?? '';

    if ($type === 'general') $stats = $this->getStatsDatabase();
    else if ($type === 'crashPartners') $stats = $this->getStatsCrashPartners( $filter);
    else if ($type === 'media_humanization') $stats = $this->getStatsMediaHumanization();
    else $stats = $this->getStatsTransportation($filter);

    $this->user->getTranslations();

    return [
      'statistics' => $stats,
      'user' => $this->user->info(),
    ];
  }

  private function getMediaHumanizationData(): array {
    $stats = $this->getStatsMediaHumanization();

    return [
      'statistics' => $stats,
    ];
  }

  /**
   * @throws Exception
   */
  private function getArticleText(): array {
    $articleId = (int)$_REQUEST['id'];
    if ($articleId <= 0) throw new \Exception('No article id found');

    $params = [':id' => $articleId];
    $sql  = "SELECT alltext FROM articles WHERE id=:id;";

    $text = $this->database->fetchSingleValue($sql, $params);

    return ['text' => $text];
  }
  private function loadCrashes(): array {

    $offset = $this->input['offset']?? 0;
    $count = $this->input['count']?? 20;
    $crashId = $this->input['id']?? null;
    $moderations = $this->input['moderations']?? 0;
    $sort = $this->input['sort']?? '';
    $filter = $this->input['filter'];

    if ($count > 1000) throw new \Exception('Internal error: Count to high.');
    if ($moderations && (! $this->user->isModerator())) throw new \Exception('Moderaties zijn alleen zichtbaar voor moderators.');

    $crashes = [];
    $params = [];
    $sqlModerated = '';
    if ($moderations) {
      $sqlModerated = ' (c.awaitingmoderation=1) OR (c.id IN (SELECT crashid FROM articles WHERE awaitingmoderation=1)) ';
    } else if ($crashId === null) {
      // Individual pages are always shown and *not* moderated.
      $sqlModerated = $this->user->isModerator()? '':  ' ((c.awaitingmoderation=0) || (c.userid=:useridModeration)) ';
      if ($sqlModerated) $params[':useridModeration'] = $this->user->id;
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

    $DbStatementCrashPersons = $this->database->prepare($sql);

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

      if (! empty($filter['userId'])) {
        $joinArticlesTable = true;
        addSQLWhere($SQLWhere, "ar.userid = :userId");
        $params[':userId'] = $filter['userId'];
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
    $articles = $this->database->fetchAll($sql, $params);
    foreach ($articles as $crash) {
      $crash['createtime'] = datetimeDBToISO8601($crash['createtime']);
      $crash['streamdatetime'] = datetimeDBToISO8601($crash['streamdatetime']);
      $crash['awaitingmoderation'] = $crash['awaitingmoderation'] == 1;

      $crash['unilateral'] = $crash['unilateral'] == 1;
      $crash['pet'] = $crash['pet'] == 1;
      $crash['trafficjam'] = $crash['trafficjam'] == 1;

      $crash['persons'] = $this->database->fetchAllPrepared($DbStatementCrashPersons, ['crashid' => $crash['id']]);

      $ids[] = $crash['id'];
      $crashes[] = $crash;
    }

    if (count($crashes) > 0){
      $params = [];
      $sqlModerated = '';
      // In the moderation and for individual crash pages, all crashes are shown
      if (! $moderations && ($crashId === null)) {
        $sqlModerated = $this->user->isModerator()? '':  ' AND ((ar.awaitingmoderation=0) || (ar.userid=:useridModeration)) ';
        if ($sqlModerated) $params[':useridModeration'] = $this->user->id;
      }

      $commaArrays = implode (", ", $ids);
      $sqlArticleSelect = $this->getArticleSelect();
      $sqlArticles = <<<SQL
$sqlArticleSelect
WHERE ar.crashid IN ($commaArrays)
$sqlModerated
ORDER BY ar.streamdatetime DESC
SQL;

      $articles = $this->database->fetchAll($sqlArticles, $params);
      foreach ($articles as &$article) {
        $article = $this->cleanArticleDBRow($article);
      }
    }

    return [
      'crashes' => $crashes,
      'articles' => $articles
    ];
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

  private function getStatsCrashPartners(array $filter): array{
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
    $crashes = $this->database->fetchAllGroup($sql, $params);
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

  private function getStatsTransportation(array $filter): array{
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

    $stats['total'] = $this->database->fetchAll($sql, $params);

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

  private function getStatsDatabase(): array {
    $stats = [];

    $stats['total'] = [];
    $sql = "SELECT COUNT(*) AS count FROM crashes";
    $stats['total']['crashes'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles";
    $stats['total']['articles'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE a.health=3";

    $stats['total']['dead'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE a.health=2";
    $stats['total']['injured'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM users";
    $stats['total']['users'] = $this->database->fetchSingleValue($sql);


    $stats['today'] = [];
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`date`) = CURDATE()";
    $stats['today']['crashes'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`publishedtime`) = CURDATE()";
    $stats['today']['articles'] = $this->database->fetchSingleValue($sql);
    $stats['today']['users'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) = CURDATE() AND a.health=3";
    $stats['today']['dead'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) = CURDATE() AND a.health=2";
    $stats['today']['injured'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`createtime`) = CURDATE()";
    $stats['today']['crashesAdded'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`createtime`) = CURDATE()";
    $stats['today']['articlesAdded'] = $this->database->fetchSingleValue($sql);

    $stats['thirtyDays'] = [];
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`date`) >= SUBDATE(CURDATE(), 30)";
    $stats['thirtyDays']['crashes'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`publishedtime`) >= SUBDATE(CURDATE(), 30)";
    $stats['thirtyDays']['articles'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) >= SUBDATE(CURDATE(), 30) AND a.health=3";
    $stats['thirtyDays']['dead'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) FROM crashes JOIN crashpersons a on crashes.id = a.crashid WHERE DATE(`date`) >= SUBDATE(CURDATE(), 30) AND a.health=2";
    $stats['thirtyDays']['injured'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM crashes WHERE DATE(`createtime`) >= SUBDATE(CURDATE(), 30)";
    $stats['thirtyDays']['crashesAdded'] = $this->database->fetchSingleValue($sql);
    $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`createtime`) >= SUBDATE(CURDATE(), 30)";
    $stats['thirtyDays']['articlesAdded'] = $this->database->fetchSingleValue($sql);

    return $stats;
  }

  private function urlExists(string $url): array | false {
    $sql = "SELECT id, crashid FROM articles WHERE url=:url LIMIT 1;";
    $params = [':url' => $url];
    $dbResults = $this->database->fetchAll($sql, $params);
    foreach ($dbResults as $found) {
      return [
        'articleId' => $found['id'],
        'crashId'   => $found['crashid'],
      ];
    }
    return false;
  }

  private function setCrashStreamTop(int $crashId, int $userId, StreamTopType $streamTopType): void {
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
    $this->database->execute($sql, $params);
  }

}