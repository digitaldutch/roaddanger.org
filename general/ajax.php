<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
require_once 'GeneralHandler.php';

global $database;
global $user;

$function = $_REQUEST['function'];

// All functions must be handled here instead of the loop below
if ($function === 'extractDataFromArticle') {
  echo GeneralHandler::extractDataFromArticle();
  return;
} else if ($function === 'loadCountryMapOptions') {
  echo GeneralHandler::loadCountryMapOptions();
  return;
} else if ($function === 'loadCountryDomain') {
  echo GeneralHandler::loadCountryDomain();
  return;
} else if ($function === 'loadCrashes') {
  echo GeneralHandler::loadCrashes();
  return;
} else if ($function === 'saveArticleCrash') {
  echo GeneralHandler::saveArticleCrash();
  return;
}


function addPeriodWhereSql(&$sqlWhere, &$params, $filter): void {
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
    case '2024':
      addSQLWhere($sqlWhere, ' YEAR(c.date) = 2024 ');
      break;
    case '2023':
      addSQLWhere($sqlWhere, ' YEAR(c.date) = 2023 ');
      break;
    case '2022':
      addSQLWhere($sqlWhere, ' YEAR(c.date) = 2022 ');
      break;
    case '2021':
      addSQLWhere($sqlWhere, ' YEAR(c.date) = 2021 ');
      break;
    case '2020':
      addSQLWhere($sqlWhere, ' YEAR(c.date) = 2020 ');
      break;
    case '2019':
      addSQLWhere($sqlWhere, ' YEAR(c.date) = 2019 ');
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

/**
 * @param Database $database
 * @return array
 */
function getStatsTransportation($database, $filter){
  $stats             = [];
  $params            = [];
  $SQLJoin           = '';
  $SQLWhere          = '';
  $joinArticlesTable = false;

  // Only do full text search if text has 3 characters or more
  if (isset($filter['text']) && strlen($filter['text']) > 2){
    addSQLWhere($SQLWhere, "(MATCH(c.title, c.text) AGAINST (:search IN BOOLEAN MODE) OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))");
    $joinArticlesTable = true;
    $params[':search']  = $filter['text'];
    $params[':search2'] = $filter['text'];
  }

  addPeriodWhereSql($SQLWhere, $params, $filter);

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

function getStatsCrashPartners(Database $database, array $filter): array{
  $SQLWhere = '';
  $SQLJoin = '';
  $params = [];
  $joinArticlesTable = false;
  $joinPersonsTable = true;

  // Only do full text search if text has 3 characters or more
  if (isset($filter['text']) && strlen($filter['text']) > 2){
    addSQLWhere($SQLWhere, "(MATCH(c.title, c.text) AGAINST (:search IN BOOLEAN MODE) OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))");
    $joinArticlesTable = true;
    $params[':search']  = $filter['text'];
    $params[':search2'] = $filter['text'];
  }

  addHealthWhereSql($SQLWhere, $joinPersonsTable, $filter);
  addPeriodWhereSql($SQLWhere, $params, $filter);

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
      $person['id']                 = (int)$person['id'];
      $person['transportationmode'] = (int)$person['transportationmode'];
      $person['health']             = (int)$person['health'];
      $person['unilateral']         = (int)$person['unilateral'] === 1;
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

/**
 * @throws Exception
 */
function getStatsMediaHumanization(): array {

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


/**
 * @param Database $database
 * @return array
 */
function getStatsDatabase($database){
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

function urlExists(Database $database, string $url): array | false {
  $sql = "SELECT id, crashid FROM articles WHERE url=:url LIMIT 1;";
  $params = [':url' => $url];
  $dbResults = $database->fetchAll($sql, $params);
  foreach ($dbResults as $found) {
    return [
      'articleId' => (int)$found['id'],
      'crashId'   => (int)$found['crashid'],
      ];
  }
  return false;
}

function setCrashStreamTop(Database $database, int $crashId, int $userId, StreamTopType $streamTopType): void {
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

function getArticleSelect(){
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

function cleanArticleDBRow($article){
  $article['id']                 = (int)$article['id'];
  $article['userid']             = (int)$article['userid'];
  $article['awaitingmoderation'] = $article['awaitingmoderation'] == 1;
  $article['hasalltext']         = ($article['hasalltext']?? 0) == 1;
  $article['crashid']            = isset($article['crashid'])? (int)$article['crashid'] : null;
  $article['createtime']         = datetimeDBToISO8601($article['createtime']);
  $article['publishedtime']      = isset($article['publishedtime'])? datetimeDBToISO8601($article['publishedtime']) : null;
  $article['streamdatetime']     = datetimeDBToISO8601($article['streamdatetime']);
  // JD NOTE: Do not sanitize strings. We handle escaping in JavaScript

  return $article;
}


// ***** Main loop *****
// TO DO: Move these over to the GeneralHandler above
if ($function == 'login') {

  if (is_null($_REQUEST['email']) || is_null($_REQUEST['password'])) dieWithJSONErrorMessage('Invalid AJAX login call.');

  $email = $_REQUEST['email'];
  $password = $_REQUEST['password'];
  $stayLoggedIn = (int)getRequest('stayLoggedIn', 0) === 1;

  global $user;

  $user->login($email, $password, $stayLoggedIn);

  echo json_encode($user->info());
} // ====================
else if ($function == 'register') {
  try {
    $data = json_decode(file_get_contents('php://input'), true);

    global $user;

    $user->register($data['firstname'], $data['lastname'], $data['email'], $data['password']);
    $result = ['ok' => true];
  } catch (\Exception $e) {
    $result = ['error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function == 'logout') {
  global $user;

  $user->logout();
  echo json_encode($user->info());
} // ====================
else if ($function == 'sendPasswordResetInstructions') {
  try {
    $result = [];
    if (! isset($_REQUEST['email'])) throw new \Exception('No email adres');
    $email = trim($_REQUEST['email']);

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
  echo json_encode($result);
} // ====================
else if ($function == 'saveNewPassword') {
  try {
    if (! isset($_REQUEST['password']))   throw new \Exception('Geen password opgegeven');
    if (! isset($_REQUEST['recoveryid'])) throw new \Exception('Geen recoveryid opgegeven');
    if (! isset($_REQUEST['email']))      throw new \Exception('Geen email opgegeven');

    $password   = $_REQUEST['password'];
    $recoveryId = $_REQUEST['recoveryid'];
    $email      = $_REQUEST['email'];

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

    if (($database->execute($sql, $params, true)) && ($database->rowCount ===1)) {
      $result = ['ok' => true];
    } else $result = ['ok' => false, 'error' => 'Wachtwoord link is verlopen of email is onbekend'];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function == 'saveAccount') {
  try {
    $newUser = json_decode(file_get_contents('php://input'));

    $user->saveAccount($newUser);

    $result = ['ok' => true];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function == 'setLanguage') {
  try {
    $languageId = getRequest('id');

    $user->saveLanguage($languageId);

    $result = ['ok' => true];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'loadMapCrashes') {

  $data = json_decode(file_get_contents('php://input'), true);

  try {
    $result = [];

    $sql = <<<SQL
SELECT 
  id, 
  ST_X(location) as longitude, 
  ST_Y(location) as latitude
FROM crashes
WHERE 
  latitude BETWEEN :latMin AND :latMax
AND
  longitude BETWEEN :lonMin AND :lonMax
ORDER BY RAND()
LIMIT 300; 
SQL;

    $params = [
      ':latMin' => $data['filter']['area']['latMin'],
      ':latMax' => $data['filter']['area']['latMax'],
      ':lonMin' => $data['filter']['area']['lonMin'],
      ':lonMax' => $data['filter']['area']['lonMax'],
    ];

    $result['crashes'] = [];
    $DBResults = $database->fetchAll($sql, $params);
    foreach ($DBResults as $crash) {
      $crash['latitude']  = floatval($crash['latitude']);
      $crash['longitude'] = floatval($crash['longitude']);

      $result['crashes'][] = $crash;
    }

    $result['ok'] = true;
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'loadUserData') {
  try {
    $data = json_decode(file_get_contents('php://input'));

    $user->getTranslations();
    $result = [
      'ok' => true,
      'user' => $user->info(),
      'extraData',
    ];

    if (isset($data->getQuestionnaireCountries) && $data->getQuestionnaireCountries === true) {
      $result['extraData']['questionnaireCountries'] = $database->getQuestionnaireCountries();
    }
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'getPageMetaData') {
  try{
    $data = json_decode(file_get_contents('php://input'), true);
    $url = $data['url'];
    $newArticle = $data['newArticle'];

    require_once 'meta_parser_utils.php';
    $resultParser = parseMetaDataFromUrl($url);

    // Check if new article url already in database.
    if ($newArticle) $urlExists = urlExists($database, $url);
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

  echo json_encode($result);
} //==========
else if ($function === 'mergeCrashes'){
  try {
    if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to merge crashes.');

    $idFrom = (int)$_REQUEST['idFrom'];
    $idTo   = (int)$_REQUEST['idTo'];

    // Move articles to other crash
    $sql    = "UPDATE articles set crashid=:idTo WHERE crashid=:idFrom;";
    $params = [':idFrom' => $idFrom, ':idTo' => $idTo];
    $database->execute($sql, $params);

    $sql    = "DELETE FROM crashes WHERE id=:idFrom;";
    $params = [':idFrom' => $idFrom];
    $database->execute($sql, $params);

    $sql = "UPDATE crashes SET streamdatetime=current_timestamp, streamtoptype=1, streamtopuserid=:userId WHERE id=:id";
    $params = [':id' => $idTo, ':userId' => $user->id];
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteArticle'){
  try{
    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql    = "DELETE FROM articles WHERE id=:id $sqlANDOwnOnly ;";
      $params = [':id' => $crashId];
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new \Exception('Internal error: Cannot delete article.');
    }
    $result = ['ok' => true];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteCrash'){
  try{
    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM crashes WHERE id=:id $sqlANDOwnOnly ;";
      $params = [':id' => $crashId];
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new \Exception('Only moderators can delete crashes.');
    }
    $result = ['ok' => true];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'crashToStreamTop'){
  try{
    if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to put crashes to top of stream.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0) setCrashStreamTop($database, $crashId, $user->id, StreamTopType::placedOnTop);
    $result = ['ok' => true];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'crashModerateOK'){
  try{
    if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to moderate crashes.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sql    = "UPDATE crashes SET awaitingmoderation=0 WHERE id=:id;";
      $params = [':id' => $crashId];
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'articleModerateOK'){
  try{
    if (! $user->isModerator()) throw new \Exception('Only moderators are allowed to moderate crashes.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sql    = "UPDATE articles SET awaitingmoderation=0 WHERE id=:id;";
      $params = [':id' => $crashId];
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getArticleText'){
  try{

    $articleId = (int)$_REQUEST['id'];
    if ($articleId > 0){
      $params = [':id' => $articleId];
      $sql  = "SELECT alltext FROM articles WHERE id=:id;";

      $text = $database->fetchSingleValue($sql, $params);
    }
    $result = ['ok' => true, 'text' => $text];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'saveAnswer') {
  try{
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
  echo json_encode($result);
} //==========
else if ($function === 'saveExplanation') {
  try{
    if (! $user->isModerator())  throw new \Exception('Only moderators can save explanations');

    $data = json_decode(file_get_contents('php://input'));

    $params = [
      'articleid'   => $data->articleId,
      'questionid'  => $data->questionId,
      'explanation' => $data->explanation,
      ];
    $sql = "UPDATE answers SET explanation= :explanation WHERE articleid=:articleid AND questionid=:questionid;";
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getArticleQuestionnairesAndText'){
  try{
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
  echo json_encode($result);
} //==========
else if ($function === 'getQuestions'){
  try {
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
  echo json_encode($result);
} //==========
else if ($function === 'getStatistics') {
  try {
    $data = json_decode(file_get_contents('php://input'), true);

    $type   = $data['type'] ?? '';
    $filter = $data['filter'] ?? '';

    if ($type === 'general') $stats = getStatsDatabase($database);
    else if ($type === 'crashPartners') $stats = getStatsCrashPartners($database, $filter);
    else if ($type === 'media_humanization') $stats = getStatsMediaHumanization();
    else $stats = getStatsTransportation($database, $filter);

    $user->getTranslations();
    $result = [
      'ok' => true,
      'statistics' => $stats,
      'user' => $user->info(),
    ];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getMediaHumanizationData') {
  try {
    $data = json_decode(file_get_contents('php://input'), true);

    $stats = getStatsMediaHumanization();

    $result = ['ok' => true,
      'statistics' => $stats,
    ];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
