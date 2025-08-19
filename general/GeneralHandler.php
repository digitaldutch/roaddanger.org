<?php

class GeneralHandler {

  static public function extractDataFromArticle():false|string {
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

  static public function loadCountryMapOptions(): false|string {
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

  static public function saveArticleCrash(): string {
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
        $exists = urlExists($database, $article['url']);
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
        $dbResult = $database->execute($sql, $params);
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

      setCrashStreamTop($database, $crash['id'], $user->id, $streamToTopType);

      $database->commit();
      $result = [
        'ok' => true,
        'crashId' => $crash['id'],
      ];

      $sqlArticleSelect = getArticleSelect();
      $sqlArticle = "$sqlArticleSelect WHERE ar.ID=:id";

      $DBArticle = $database->fetch($sqlArticle, ['id' => $article['id']]);
      $DBArticle = cleanArticleDBRow($DBArticle);

      $result['article'] = $DBArticle;

    } catch (\Throwable $e){
      $database->rollback();
      $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
    }

    return json_encode($result);
  }

  static public function saveLanguage(): string {
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
  static public function saveCountry(): string {
    global $user;

    try {
      $countryId = getRequest('id');

      $user->saveCountry($countryId);

      $result = ['ok' => true];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
  static public function loadCrashes(): string {
    global $database;
    global $user;

    try {
      $data = json_decode(file_get_contents('php://input'), true);

      $offset = isset($data['offset'])? (int)$data['offset'] : 0;
      $count = isset($data['count'])? (int)$data['count'] : 20;
      $crashId = $data['id']?? null;
      $moderations = isset($data['moderations'])? (int)$data['moderations'] : 0;
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

        addPeriodWhereSql($SQLWhere, $params, $filter);

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
        $crash['id']                    = (int)$crash['id'];
        $crash['userid']                = (int)$crash['userid'];
        $crash['streamtopuserid']       = (int)$crash['streamtopuserid'];
        $crash['streamtoptype']         = (int)$crash['streamtoptype'];
        $crash['createtime']            = datetimeDBToISO8601($crash['createtime']);
        $crash['streamdatetime']        = datetimeDBToISO8601($crash['streamdatetime']);
        $crash['awaitingmoderation']    = $crash['awaitingmoderation'] == 1;

        $crash['unilateral']            = $crash['unilateral'] == 1;
        $crash['pet']                   = $crash['pet'] == 1;
        $crash['trafficjam']            = $crash['trafficjam'] == 1;

        // Load crash persons
        $crash['persons'] = [];
        $DBPersons = $database->fetchAllPrepared($DbStatementCrashPersons, ['crashid' => $crash['id']]);
        foreach ($DBPersons as $person) {
          $person['groupid']            = isset($person['groupid'])? (int)$person['groupid'] : null;
          $person['transportationmode'] = (int)$person['transportationmode'];
          $person['health']             = isset($person['health'])? (int)$person['health'] : null;
          $person['child']              = (int)$person['child'];
          $person['underinfluence']     = (int)$person['underinfluence'];
          $person['hitrun']             = (int)$person['hitrun'];

          $crash['persons'][] = $person;
        }

        $ids[] = $crash['id'];
        $crashes[] = $crash;
      }

      if (count($crashes) > 0){
        $params = [];
        $sqlModerated = '';
        if ($moderations) {
          // In the moderation queue all articles are shown
        } else if ($crashId === null) { // Individual pages are always shown and *not* moderated. Needed
          $sqlModerated = $user->isModerator()? '':  ' AND ((ar.awaitingmoderation=0) || (ar.userid=:useridModeration)) ';
          if ($sqlModerated) $params[':useridModeration'] = $user->id;
        }

        $commaArrays      = implode (", ", $ids);
        $sqlArticleSelect = getArticleSelect();
        $sqlArticles = <<<SQL
$sqlArticleSelect
WHERE ar.crashid IN ($commaArrays)
 $sqlModerated
ORDER BY ar.streamdatetime DESC
SQL;

        $articles = $database->fetchAll($sqlArticles, $params);
        foreach ($articles as &$article) {
          $article = cleanArticleDBRow($article);
        }
      }

      $result = ['ok' => true, 'crashes' => $crashes, 'articles' => $articles];

    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }
}
