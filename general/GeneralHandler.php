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
        'answerQuestionnairesForArticle' => $this->answerQuestionnairesForArticle(),
        'loadCountryMapOptions' => $this->loadCountryMapOptions(),
        'loadCrashes' => $this->loadCrashes(),
        'loadMapCrashes' => $this->loadMapCrashes(),
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
        'saveJustification' => $this->saveJustification(),
        'getArticleQuestionnairesAndText' => $this->getArticleQuestionnairesAndText(),
        'getStatistics' => $this->getStatistics(),
        'getResearch_UVA_2026' => $this->getResearch_UVA_2026(),
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

    $prompt = $this->database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='article_analyst';");

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
  private function answerQuestionnairesForArticle(): array {
    $articleId = $this->input['articleId'];

    $sql = "SELECT title, text, publishedtime FROM articles WHERE id = :id";
    $article = $this->database->fetchObject($sql, ['id' => $articleId]);

    $prompt = $this->database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='questionnaire_agent';");

    require_once '../general/OpenRouterAIClient.php';

    $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, (object)$article);
    $questionnaires = $this->database->loadQuestionnaires();
    $prompt->user_prompt = replaceAI_QuestionnaireTags($prompt->user_prompt, $questionnaires);

    $openrouter = new OpenRouterAIClient();
    $AIResults = $openrouter->chatWithMeta($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

    $AIResults['response'] = json_decode($AIResults['response']);
    $questionnaires = $AIResults['response']->questionnaires;

    foreach ($questionnaires AS &$questionnaire) {
      foreach ($questionnaire->questions AS &$question) {
        $question->answer_id = aiAnswerToAnswerId($question->answer);
        $question->justification = 'AI: ' . $question->justification;

        // Save answer to the database
        $this->database->saveAnswer($articleId, $question->id, $question->answer_id, $question->justification);
      }
    }

    return ['questionnaires' => $questionnaires];
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

  /**
   * @throws Exception
   */
  private function saveAnswer(): array {
    if (! $this->user->isModerator())  throw new \Exception('Only moderators can save answers');

    $this->database->saveAnswer(
      $this->input['articleId'],
      $this->input['questionId'],
      $this->input['answer'],
      $this->input['justification'],
    );

    return [];
  }
  private function saveJustification(): array {
    if (! $this->user->isModerator())  throw new \Exception('Only moderators can save justifications');

    $params = [
      'articleid' => $this->input['articleId'],
      'questionid' => $this->input['questionId'],
      'justification' => $this->input['justification'],
    ];
    $sql = "UPDATE answers SET explanation= :justification WHERE articleid=:articleid AND questionid=:questionid;";
    $this->database->execute($sql, $params);

    return [];
  }

  /**
   * @throws Exception
   */
  private function getArticleQuestionnairesAndText(): array {
    if (! $this->user->isModerator()) throw new \Exception('Only moderators can edit article questions');

    if ($this->input['articleId'] <= 0) throw new \Exception('No article id found');

    $sql = <<<SQL
SELECT
  id,
  title,
  country_id,
  type
FROM questionnaires
WHERE active = 1
ORDER BY id;
SQL;

    $questionnaires = $this->database->fetchAll($sql);

    $sql = <<<SQL
SELECT
  q.id,
  q.text,
  q.explanation,
  a.answer,
  a.explanation AS answerJustification
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
  private function getStatistics(): array {
    $type = $this->input['type'] ?? '';
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

  private function getResearch_UVA_2026(): array {
    $stats = [];

    $sql = "SELECT COUNT(*) FROM crashes WHERE EXTRACT(YEAR FROM date) = 2025;";
    $stats['crashes'] = $this->database->fetchSingleValue($sql);

    $sql = "SELECT COUNT(*) FROM articles a LEFT JOIN crashes c ON a.crashid = c.id WHERE EXTRACT(YEAR FROM c.date) = 2025;";
    $stats['articles'] = $this->database->fetchSingleValue($sql);

    return [
      'stats' => $stats,
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
  private function loadCrashes($crashIds = []): array {
    $offset = $this->input['offset']?? 0;
    $count = $this->input['count']?? 20;
    $crashId = $this->input['id']?? null;
    $moderations = $this->input['moderations']?? 0;
    $sort = $this->input['sort']?? '';
    $filter = $this->input['filter'];

    if ($count > 1000) throw new \Exception('Internal error: Count to high.');
    if ($moderations && (! $this->user->isModerator())) throw new \Exception('Moderaties zijn alleen zichtbaar voor moderators.');

    $crashes = [];

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
SELECT
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

    $params = [];
    $SQLWhere = '';
    if (count($crashIds) > 0) {
      // Search on crash IDs
      $IdsString = implode(', ', $crashIds);
      $SQLWhere = " WHERE c.id IN ($IdsString) ";
    } else if ($crashId !== null) {
      // Single crash
      $params = [':id' => $crashId];
      $SQLWhere = " WHERE c.id=:id ";
    } else {

      [$SQLWhere, $params] = $this->getCrashesWhere($filter, $SQLWhere, $params);

      $sqlModerated = '';
      if ($moderations) {
        $sqlModerated = ' (c.awaitingmoderation=1) OR (c.id IN (SELECT crashid FROM articles WHERE awaitingmoderation=1)) ';
      } else if ($crashId === null) {
        // Individual pages are always shown and *not* moderated.
        $sqlModerated = $this->user->isModerator()? '':  ' ((c.awaitingmoderation=0) || (c.userid=:useridModeration)) ';
        if ($sqlModerated) $params[':useridModeration'] = $this->user->id;
      }

      if ($sqlModerated) addSQLWhere($SQLWhere, $sqlModerated);

      if (! isset($filter['area'])) {
        $orderField = match ($sort) {
          'crashDate' => 'c.date DESC, c.streamdatetime DESC',
          'lastChanged' => 'c.streamdatetime DESC',
          default => 'c.date DESC, c.streamdatetime DESC',
        };
        $SQLWhere .= " ORDER BY $orderField ";

        $params[':offset'] = $offset;
        $params[':count'] = $count;
        $SQLWhere .= " LIMIT :offset, :count ";
      }
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

  public function loadMapCrashes(): array {
    // Using hysteresis to avoid jumping quickly between points and bins.
    $toBinsThreshold = 3000;
    $toPointsThreshold = 2500;

    $filter = $this->input['filter'];

    // Get the number of crashes in the bounding box
    [$SQLWhere, $params] = $this->getCrashesWhere($filter);
    $sql = <<<SQL
SELECT COUNT(*) AS count 
FROM crashes c
$SQLWhere;
SQL;

    $count = $this->database->fetchSingleValue($sql, $params);

    $mode = $filter['map_mode'] ?? 'points';

    if ($mode === 'points') {
      $useBins = ($count > $toBinsThreshold);
    } else { // mode === 'bins'
      $useBins = ($count > $toPointsThreshold);
    }

    if ($useBins) {
      // Show aggregate bins if too many crashes
      $binResults = $this->fetchCrashMapAggregateBins($filter);
      $crashIds = array_column($binResults['crashes'], 'id');
      $crashesResults = $this->loadCrashes($crashIds);
      return [
        'crash_count' => $count,
        'crashes' => $crashesResults['crashes'],
        'articles' => $crashesResults['articles'],
        'bins' => $binResults['bins'],
      ];
    } else {
      $binResults = $this->loadCrashes();
      $binResults['crash_count'] = count($binResults['crashes']);
      return $binResults;
    }
  }

  // ***** Private functions *****
  private function getBinCellSizeFromBbox(
    float $lonMin, float $lonMax,
    float $latMin, float $latMax,
    int $cols = 25, int $rows = 15
  ): float {
    $w = max(1e-9, $lonMax - $lonMin);
    $h = max(1e-9, $latMax - $latMin);
    return max($w / $cols, $h / $rows);
  }


  private function makeBinsLatLonCount(array $crashes, float $cell, int $targetBins = 300, int $minBinCount = 3): array {
    while (true) {
      $bins = [];

      foreach ($crashes as $crash) {
        if ($crash['longitude'] === null || $crash['latitude'] === null) {
          continue;
        }

        $id  = (int)$crash['id'];
        $lon = (float)$crash['longitude'];
        $lat = (float)$crash['latitude'];

        $ix  = (int)floor($lon / $cell);
        $iy  = (int)floor($lat / $cell);
        $key = $ix . ':' . $iy;

        if (!isset($bins[$key])) {
          $bins[$key] = [
            'count'  => 0,
            'sumLon' => 0.0,
            'sumLat' => 0.0,
            'points' => [], // store up to ($minBinCount - 1) points for small bins
          ];
        }

        $bins[$key]['count']++;
        $bins[$key]['sumLon'] += $lon;
        $bins[$key]['sumLat'] += $lat;

        // Keep a few raw points so we can return them as individual crashes
        // when the bin is too small to be meaningful (e.g. count 1-2).
        $keep = max(0, $minBinCount - 1);
        if ($keep > 0 && count($bins[$key]['points']) < $keep) {
          $bins[$key]['points'][] = ['id' => $id, 'longitude' => $lon, 'latitude' => $lat];
        }
      }

      // Too many bins? Make cells larger and retry.
      if (count($bins) > $targetBins) {
        $cell *= 1.6;
        continue;
      }

      $outBins = [];
      $outCrashes = [];

      foreach ($bins as $bin) {
        $count = $bin['count'];

        // For small bins, return the original crash points (exact coords)
        if ($count > 0 && $count < $minBinCount) {
          foreach ($bin['points'] as $p) {
            $outCrashes[] = $p; // {id, longitude, latitude}
          }
          continue;
        }

        // Otherwise return an aggregate bin (centroid + count)
        if ($count > 0) {
          $outBins[] = [
            'latitude'  => $bin['sumLat'] / $count,
            'longitude' => $bin['sumLon'] / $count,
            'count'     => $count,
          ];
        }
      }

      return [
        'bins'    => $outBins,
        'crashes' => $outCrashes,
        'cell'    => $cell, // optional debug
      ];
    }
  }



  private function fetchCrashMapAggregateBins($filter): array {

    [$SQLWhere, $params] = $this->getCrashesWhere($filter);

    $sql = <<<SQL
SELECT 
  c.id,
  ST_X(c.location) AS longitude, 
  ST_Y(c.location) AS latitude
FROM crashes c
$SQLWhere
SQL;

    $rows = $this->database->fetchAll($sql, $params);

    $latMin = (float)$filter['area']['latMin'];
    $latMax = (float)$filter['area']['latMax'];
    $lonMin = (float)$filter['area']['lonMin'];
    $lonMax = (float)$filter['area']['lonMax'];

    $cell = $this->getBinCellSizeFromBbox($lonMin, $lonMax, $latMin, $latMax);

    $result = $this->makeBinsLatLonCount($rows, $cell, 200, 20);

    return [
      'bins' => $result['bins'],
      'crashes' => $result['crashes'], // singleton crash markers (with id)
    ];
  }

  private function getCrashesWhere($filter, $SQLWhere='', $params=[]): array {

    // Only do full-text search if the text has 3 characters or more
    if (isset($filter['text']) && strlen($filter['text']) > 2) {
      $sqlLocal = <<<SQL
(MATCH(c.title, c.text) AGAINST (:search IN BOOLEAN MODE) 
    OR EXISTS (
      SELECT 1
      FROM articles ar
      WHERE ar.crashid = c.id
        AND MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))
)
SQL;

      addSQLWhere($SQLWhere, $sqlLocal);
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

    if (! empty($filter['siteName'])) {
      $sqlLocal = <<<SQL
EXISTS (
  SELECT 1
  FROM articles ar
  WHERE ar.crashid = c.id
    AND (LOWER(ar.sitename) LIKE :sitename))
SQL;
      addSQLWhere($SQLWhere, $sqlLocal);
      $params[':sitename'] = "%{$filter['siteName']}%";
    }

    if (! empty($filter['userId'])) {
      $sqlLocal = <<<SQL
((c.userid = :userId) 
OR
  EXISTS (
    SELECT 1
    FROM articles ar
    WHERE ar.crashid = c.id
      AND (ar.userid = :userId2)))
SQL;
      addSQLWhere($SQLWhere, $sqlLocal);
      $params[':userId'] = $filter['userId'];
      $params[':userId2'] = $filter['userId'];
    }

    addPersonsWhereSql($SQLWhere, $filter);

    if (isset($filter['area'])) {
      $sqlArea = "MBRContains(ST_GeomFromText(:bboxWkt), c.location)";

      addSQLWhere($SQLWhere, $sqlArea);

      $latMin = (float)$filter['area']['latMin'];
      $latMax = (float)$filter['area']['latMax'];
      $lonMin = (float)$filter['area']['lonMin'];
      $lonMax = (float)$filter['area']['lonMax'];

      $params[':bboxWkt'] = sprintf(
        'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
        $lonMin, $latMin,
        $lonMax, $latMin,
        $lonMax, $latMax,
        $lonMin, $latMax,
        $lonMin, $latMin
      );
    }

    return [$SQLWhere, $params];
  }

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
    [$SQLWhere, $params] = $this->getCrashesWhere($filter);

    $sqlCrashesWithDeath = <<<SQL
  SELECT
    c.id
  FROM crashpersons cp
  JOIN crashes c ON cp.crashid = c.id
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
      $crashInjured = [];
      $crashTransportationModes = [];
      $unilateralCrash = false;

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

        // Add the crash partner
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

    // Only do full-text search if the text has 3 characters or more
    if (isset($filter['text']) && strlen($filter['text']) > 2) {
      $sqlLocal = <<<SQL
EXISTS (SELECT 1 FROM articles ar 
  WHERE ar.crashid = cp.crashid
  AND MATCH(ar.title, ar.text) AGAINST (:search IN BOOLEAN MODE)
)
SQL;

      addSQLWhere($SQLWhere, $sqlLocal);
      $params[':search']  = $filter['text'];
    }

    if (! empty($filter['siteName'])) {
      $sqlLocal = <<<SQL
EXISTS (
  SELECT 1
  FROM articles ar
  WHERE ar.crashid = cp.crashid
    AND (LOWER(ar.sitename) LIKE :sitename))
SQL;
      addSQLWhere($SQLWhere, $sqlLocal);
      $params[':sitename'] = "%{$filter['siteName']}%";
    }

    $this->addPeriodWhereSql($SQLWhere, $params, $filter);

    if ($filter['child'] === 1){
      addSQLWhere($SQLWhere, " cp.child=1 ");
    }

    if ($filter['country'] !== 'UN') {
      $sqlLocal = "EXISTS(SELECT 1 FROM crashes c WHERE c.id=cp.crashid AND c.countryid=:country)";
      addSQLWhere($SQLWhere, $sqlLocal);
      $params[':country'] = $filter['country'];
    }

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
LEFT JOIN crashes c ON cp.crashid = c.id
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