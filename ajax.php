<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'initialize.php';

$function = $_REQUEST['function'];

function addPeriodWhereSql(&$sqlWhere, &$params, $searchPeriod, $searchDateFrom, $searchDateTo){
  if ($searchPeriod === '') return;

  switch ($searchPeriod) {
    case 'today':
      addSQLWhere($sqlWhere, ' DATE(ac.date) = CURDATE() ');
      break;
    case 'yesterday':
      addSQLWhere($sqlWhere, ' DATE(ac.date) = SUBDATE(CURDATE(), 1) ');
      break;
    case '7days':
      addSQLWhere($sqlWhere, ' DATE(ac.date) > SUBDATE(CURDATE(), 7) ');
      break;
    case 'decorrespondent':
      addSQLWhere($sqlWhere, " DATE(ac.date) >= '2019-01-14' AND DATE (ac.date) <= '2019-01-20' ");
      break;
    case '30days':
      addSQLWhere($sqlWhere, ' DATE(ac.date) > SUBDATE(CURDATE(), 30) ');
      break;
    case '2019':
      addSQLWhere($sqlWhere, ' YEAR(ac.date) = 2019 ');
      break;
    case '2020':
      addSQLWhere($sqlWhere, ' YEAR(ac.date) = 2020 ');
      break;
    case 'custom': {
      if ($searchDateFrom !== '') {
        addSQLWhere($sqlWhere, " DATE(ac.date) >= :searchDateFrom ");
        $params[':searchDateFrom'] = date($searchDateFrom);
      }

      if ($searchDateTo !== '') {
        addSQLWhere($sqlWhere, " DATE(ac.date) <= :searchDateTo ");
        $params[':searchDateTo'] = date($searchDateTo);
      }

      break;
    }
  }
}


/**
 * @param TDatabase $database
 * @return array
 */
function getStatsTransportation($database, $searchPeriod='all', $searchDateFrom=null, $searchDateTo=null){
  $stats    = [];
  $params   = [];
  $SQLWhere = '';
  addPeriodWhereSql($SQLWhere, $params, $searchPeriod, $searchDateFrom, $searchDateTo);

  $sql = <<<SQL
SELECT
  transportationmode,
  sum(ap.underinfluence=1) AS underinfluence,
  sum(ap.hitrun=1)         AS hitrun,
  sum(ap.child=1)          AS child,
  sum(ap.health=3)         AS dead,
  sum(ap.health=2)         AS injured,
  sum(ap.health=1)         AS unharmed,
  sum(ap.health=0)         AS healthunknown,
  COUNT(*) AS total
FROM accidentpersons ap
JOIN accidents ac ON ap.accidentid = ac.id
  $SQLWhere
GROUP BY transportationmode
ORDER BY dead DESC, injured DESC
SQL;

  $stats['total'] = $database->fetchAll($sql, $params);
  foreach ($stats['total'] as &$stat) {
    $stat['transportationmode'] = (int)$stat['transportationmode'];
    $stat['underinfluence']     = (int)$stat['underinfluence'];
    $stat['hitrun']             = (int)$stat['hitrun'];
    $stat['child']              = (int)$stat['child'];
    $stat['dead']               = (int)$stat['dead'];
    $stat['injured']            = (int)$stat['injured'];
    $stat['healthunknown']      = (int)$stat['healthunknown'];
    $stat['total']              = (int)$stat['total'];
  }

  return $stats;
}

/**
 * @param TDatabase $database
 * @return array
 */
function getStatsCrashPartners($database, $searchPeriod='all', $searchDateFrom=null, $searchDateTo=null){

  $SQLWhere = ' WHERE ap.health=3 ';
  $params   = [];
  addPeriodWhereSql($SQLWhere, $params, $searchPeriod, $searchDateFrom, $searchDateTo);

  $sqlCrashesWithDeath = <<<SQL
  SELECT
    ac.id
  FROM accidentpersons ap
  JOIN accidents ac ON ap.accidentid = ac.id
  $SQLWhere
SQL;

  // Get all persons from crashes with dead
  $sql = <<<SQL
select
  a.id AS accidentid,
  a.unilateral,
  ap.id,
  transportationmode,
  health
from accidentpersons ap
JOIN accidents a ON ap.accidentid = a.id
where
  a.id IN ($sqlCrashesWithDeath);
SQL;


  $crashVictims = [];
  $crashes = $database->fetchAllGroup($sql, $params);
  foreach ($crashes as $crashPersons) {
    $crashDeaths              = [];
    $crashTransportationModes = [];
    $unilateralCrash          = false;

    // get crash dead persons
    foreach ($crashPersons as $person){
      $person['id']                 = (int)$person['id'];
      $person['transportationmode'] = (int)$person['transportationmode'];
      $person['health']             = (int)$person['health'];
      $person['unilateral']         = (int)$person['unilateral'] === 1;
      if ($person['unilateral'] === true) $unilateralCrash = true;

      if ($person['health'] === 3) $crashDeaths[] = $person;
      if (! in_array($person['transportationmode'], $crashTransportationModes)) $crashTransportationModes[] = $person['transportationmode'];
    }

    foreach ($crashDeaths as $personDead){
      if (! isset($crashVictims[$personDead['transportationmode']])) $crashVictims[$personDead['transportationmode']] = [];

      // Add crash partner
      if ($unilateralCrash){
        // Unilateral transportationMode = -1
        if (! isset($crashVictims[$personDead['transportationmode']][-1])) $crashVictims[$personDead['transportationmode']][-1] = 0;
        $crashVictims[$personDead['transportationmode']][-1] += 1;
      } else if (count($crashTransportationModes) === 1){
        if (! isset($crashVictims[$personDead['transportationmode']][$personDead['transportationmode']])) $crashVictims[$personDead['transportationmode']][$personDead['transportationmode']] = 0;
        $crashVictims[$personDead['transportationmode']][$personDead['transportationmode']] += 1;
      } else {
        foreach ($crashTransportationModes as $transportationMode){
          if ($transportationMode !== $personDead['transportationmode']) {
            if (! isset($crashVictims[$personDead['transportationmode']][$transportationMode])) $crashVictims[$personDead['transportationmode']][$transportationMode] = 0;
            $crashVictims[$personDead['transportationmode']][$transportationMode] += 1;
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
 * @param TDatabase $database
 * @return array
 */
function getStatsDatabase($database){
  $stats = [];

  $stats['total'] = [];
  $sql = "SELECT COUNT(*) AS count FROM accidents";
  $stats['total']['crashes'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM articles";
  $stats['total']['articles'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE a.health=3";
  $stats['total']['dead'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE a.health=2";
  $stats['total']['injured'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM users";
  $stats['total']['users'] = $database->fetchSingleValue($sql);


  $stats['today'] = [];
  $sql = "SELECT COUNT(*) AS count FROM accidents WHERE DATE(`date`) = CURDATE()";
  $stats['today']['crashes'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`publishedtime`) = CURDATE()";
  $stats['today']['articles'] = $database->fetchSingleValue($sql);
  $stats['today']['users'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE DATE(`date`) = CURDATE() AND a.health=3";
  $stats['today']['dead'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE DATE(`date`) = CURDATE() AND a.health=2";
  $stats['today']['injured'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM accidents WHERE DATE(`createtime`) = CURDATE()";
  $stats['today']['crashesAdded'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`createtime`) = CURDATE()";
  $stats['today']['articlesAdded'] = $database->fetchSingleValue($sql);

  $stats['sevenDays'] = [];
  $sql = "SELECT COUNT(*) AS count FROM accidents WHERE DATE(`date`) >= SUBDATE(CURDATE(), 7)";
  $stats['sevenDays']['crashes'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`publishedtime`) >= SUBDATE(CURDATE(), 7)";
  $stats['sevenDays']['articles'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE DATE(`date`) >= SUBDATE(CURDATE(), 7) AND a.health=3";
  $stats['sevenDays']['dead'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE DATE(`date`) >= SUBDATE(CURDATE(), 7) AND a.health=2";
  $stats['sevenDays']['injured'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM accidents WHERE DATE(`createtime`) >= SUBDATE(CURDATE(), 7)";
  $stats['sevenDays']['crashesAdded'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM articles WHERE DATE(`createtime`) >= SUBDATE(CURDATE(), 7)";
  $stats['sevenDays']['articlesAdded'] = $database->fetchSingleValue($sql);

  $stats['deCorrespondent'] = [];
  $sql = "SELECT COUNT(*) FROM accidents WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20'";
  $stats['deCorrespondent']['crashes'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM articles WHERE DATE (`publishedtime`) >= '2019-01-14' AND DATE (`publishedtime`) <= '2019-01-20'";
  $stats['deCorrespondent']['articles'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents WHERE DATE (`createtime`) >= '2019-01-14' AND DATE (`createtime`) <= '2019-01-20'";
  $stats['deCorrespondent']['crashesAdded'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM articles WHERE DATE (`createtime`) >= '2019-01-14' AND DATE (`createtime`) <= '2019-01-20'";
  $stats['deCorrespondent']['articlesAdded'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM users WHERE DATE (`registrationtime`) >= '2019-01-14' AND DATE (`registrationtime`) <= '2019-01-20'";
  $stats['deCorrespondent']['users'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20' AND a.health=3";
  $stats['deCorrespondent']['dead'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM accidents JOIN accidentpersons a on accidents.id = a.accidentid WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20' AND a.health=2";
  $stats['deCorrespondent']['injured'] = $database->fetchSingleValue($sql);

  return $stats;
}

/**
 * @param TDatabase $database
 * @param string $url
 * @throws Exception
 * @return array | false
 */
function urlExists($database, $url){
  $sql = "SELECT id, accidentid FROM articles WHERE url=:url LIMIT 1;";
  $params = [':url' => $url];
  $DBResults = $database->fetchAll($sql, $params);
  foreach ($DBResults as $found) {
    return [
      'articleId' => (int)$found['id'],
      'crashId'   => (int)$found['accidentid'],
      ];
  }
  return false;
}

/**
 * @param TDatabase $database
 * @param integer $crashId
 * @param integer $userId
 * @param integer $streamTopType unknown: 0, edited: 1, articleAdded: 2, placedOnTop: 3
 */
function setCrashStreamTop($database, $crashId, $userId, $streamTopType){
  $sql = <<<SQL
  UPDATE accidents SET
    streamdatetime  = current_timestamp,
    streamtoptype   = $streamTopType, 
    streamtopuserid = :userid
  WHERE id=:id;
SQL;
  $params = array(':id' => $crashId, ':userid' => $userId);
  $database->execute($sql, $params);
}

function getArticleSelect(){
  return <<<SQL
SELECT
  ar.id,
  ar.userid,
  ar.awaitingmoderation,
  ar.accidentid,
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
  $article['hasalltext']         = $article['hasalltext'] == 1;
  $article['accidentid']         = (int)$article['accidentid'];
  $article['createtime']         = datetimeDBToISO8601($article['createtime']);
  $article['publishedtime']      = datetimeDBToISO8601($article['publishedtime']);
  $article['streamdatetime']     = datetimeDBToISO8601($article['streamdatetime']);
  // JD NOTE: Do not sanitize strings. We handle escaping in JavaScript

  return $article;
}


// ***** Main loop *****
if ($function == 'login') {
  if (is_null($_REQUEST['email']) || is_null($_REQUEST['password'])) dieWithJSONErrorMessage('Invalid AJAX login call.');

  $email        = $_REQUEST['email'];
  $password     = $_REQUEST['password'];
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
    $result = array('ok' => true);
  } catch (Exception $e) {
    $result = array('error' => $e->getMessage());
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
    if (! isset($_REQUEST['email'])) throw new Exception('No email adres');
    $email  = trim($_REQUEST['email']);

    $recoveryID = $user->resetPasswordRequest($email);
    if (! $recoveryID) throw new Exception('Interne fout: Kan geen recoveryID aanmaken');

    $domain            = DOMAIN_NAME;
    $subject           = $domain . ' wachtwoord resetten';
    $server            = SERVER_DOMAIN;
    $emailEncoded      = urlencode($email);
    $recoveryIDEncoded = urlencode($recoveryID);
    $body    = <<<HTML
<p>Hallo,</p>

<p>We hebben een verzoek ontvangen om het wachtwoord verbonden aan je emailadres ($email) te resetten. Om je wachtwoord te resetten, klik op de onderstaande link:</p>

<p><a href="$server/account/resetpassword.php?email=$emailEncoded&recoveryid=$recoveryIDEncoded">Wachtwoord resetten</a></p>

<p>Vriendelijke groeten,<br>
$domain</p>
HTML;

    if (sendEmail($email, $subject, $body, [])) $result['ok'] = true;
    else throw new Exception('Interne server fout: Kan email niet verzenden.');
  } catch (Exception $e){
    $result = array('ok' => false, 'error' => $e->getMessage());
  }
  echo json_encode($result);
} // ====================
else if ($function == 'saveNewPassword') {
  try {
    if (! isset($_REQUEST['password']))   throw new Exception('Geen password opgegeven');
    if (! isset($_REQUEST['recoveryid'])) throw new Exception('Geen recoveryid opgegeven');
    if (! isset($_REQUEST['email']))      throw new Exception('Geen email opgegeven');
    $password   = $_REQUEST['password'];
    $recoveryid = $_REQUEST['recoveryid'];
    $email      = $_REQUEST['email'];

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $sql = <<<SQL
UPDATE users SET 
  passwordhash=:passwordhash,
  passwordrecoveryid = null
WHERE email=:email 
  AND passwordrecoveryid=:passwordrecoveryid;
SQL;

    $params = array(':passwordhash' => $passwordHash, ':email' => $email, ':passwordrecoveryid' => $recoveryid);
    if (($database->execute($sql, $params, true)) && ($database->rowCount ===1)) {
      $result = ['ok' => true];
    } else $result = ['ok' => false, 'error' => 'Wachtwoord link is verlopen of email is onbekend'];
  } catch (Exception $e) {
    $result = array('ok' => false, 'error' => $e->getMessage());
  }

  echo json_encode($result);
} // ====================
else if ($function === 'loadCrashes') {
  try {
    $data = json_decode(file_get_contents('php://input'), true);

    $offset            = (int)$data['offset']?? 0;
    $count             = (int)$data['count']?? 20;
    $crashId           = $data['id']?? null;
    $searchText        = $data['search']?? '';
    $searchDateFrom    = $data['searchDateFrom']?? '';
    $searchDateTo      = $data['searchDateTo']?? '';
    $searchPeriod      = $data['searchPeriod']?? '';
    $searchDateFrom    = $data['searchDateFrom']?? '';
    $searchDateTo      = $data['searchDateTo']?? '';
    $searchPersons     = $data['searchPersons']?? [];
    $searchSiteName    = $data['sitename']?? '';
    $searchHealthDead  = (int)$data['healthdead']?? 0;
    $searchChild       = (int)$data['child']?? 0;
    $moderations       = (int)$data['moderations']?? 0;
    $sort              = $data['sort']?? '';
    $export            = (int)$data['export']?? 0;
    $imageUrlsOnly     = ($data['imageUrlsOnly'] === 1)? (int)$data['imageUrlsOnly'] : 0;

    if ($count > 1000) throw new Exception('Internal error: Count to high.');
    if ($moderations && (! $user->isModerator())) throw new Exception('Moderaties zijn alleen zichtbaar voor moderators.');

    $crashes    = [];
    $articles     = [];
    $params       = [];
    $sqlModerated = '';
    if ($moderations) {
      $sqlModerated = ' (ac.awaitingmoderation=1) OR (ac.id IN (SELECT accidentid FROM articles WHERE awaitingmoderation=1)) ';
    } else if ($crashId === null) {
      // Individual pages are always shown and *not* moderated.
      $sqlModerated = $user->isModerator()? '':  ' ((ac.awaitingmoderation=0) || (ac.userid=:useridModeration)) ';
      if ($sqlModerated) $params[':useridModeration'] = $user->id;
    }

    // Sort on dead=3, injured=2, unknown=0, unharmed=1
    $sql = <<<SQL
SELECT 
  groupid,
  transportationmode,
  health,
  child,
  underinfluence,
  hitrun
FROM accidentpersons
WHERE accidentid=:accidentid
ORDER BY health IS NULL, FIELD(health, 3, 2, 0, 1);
SQL;
    $DBStatementPersons = $database->prepare($sql);

    $sql = <<<SQL
SELECT DISTINCT 
  ac.id,
  ac.userid,
  ac.createtime,
  ac.streamdatetime,
  ac.streamtopuserid,     
  ac.streamtoptype,
  ac.awaitingmoderation,
  ac.title,
  ac.text,
  ac.date,
  ac.latitude,
  ac.longitude,
  ac.unilateral,
  ac.pet, 
  ac.trafficjam, 
  ac.tree,
  CONCAT(u.firstname, ' ', u.lastname) AS user, 
  CONCAT(tu.firstname, ' ', tu.lastname) AS streamtopuser 
FROM accidents ac
LEFT JOIN users u  on u.id  = ac.userid 
LEFT JOIN users tu on tu.id = ac.streamtopuserid
SQL;

    $SQLWhere = '';
    if ($crashId !== null) {
      // Single accident
      $params = [':id' => $crashId];
      $SQLWhere = " WHERE ac.id=:id ";
    } else {

      $joinArticlesTable = false;
      $joinPersonsTable  = false;
      $SQLJoin = '';
      // Only do full text search if text has 3 characters or more
      if (strlen($searchText) > 2){
        addSQLWhere($SQLWhere, "(MATCH(ac.title, ac.text) AGAINST (:search IN BOOLEAN MODE) OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE))");
        $joinArticlesTable = true;
        $params[':search']  = $searchText;
        $params[':search2'] = $searchText;
      }

      addPeriodWhereSql($SQLWhere, $params, $searchPeriod, $searchDateFrom, $searchDateTo);

      if ($searchSiteName !== ''){
        $joinArticlesTable = true;
        addSQLWhere($SQLWhere, " LOWER(ar.sitename) LIKE :sitename ");
        $params[':sitename'] = "%$searchSiteName%";
      }

      if ($searchHealthDead === 1){
        $joinPersonsTable = true;
        addSQLWhere($SQLWhere, " ap.health=3 ");
      }

      if ($searchChild === 1){
        $joinPersonsTable = true;
        addSQLWhere($SQLWhere, " ap.child=1 ");
      }

      if (count($searchPersons) > 0) $joinPersonsTable = true;

      if ($sqlModerated) addSQLWhere($SQLWhere, $sqlModerated);

      if ($joinArticlesTable) $SQLJoin .= ' JOIN articles ar ON ac.id = ar.accidentid ';
      if ($joinPersonsTable)  $SQLJoin .= ' JOIN accidentpersons ap on ac.id = ap.accidentid ';

      if (count($searchPersons) > 0){
        foreach ($searchPersons as $person){
          $tableName          = 'p' . $person;
          $transportationMode = (int)$person;
          $personDead         = containsText($person, 'd');
          $personInjured      = containsText($person, 'i');
          $restricted         = containsText($person, 'r');
          $unilateral         = containsText($person, 'u');
          $SQLJoin .= " JOIN accidentpersons $tableName ON ac.id = $tableName.accidentid AND $tableName.transportationmode=$transportationMode ";
          if ($personDead || $personInjured ) {
            $healthValues = [];
            if ($personDead)    $healthValues[] = 3;
            if ($personInjured) $healthValues[] = 2;
            $healthValues = implode(',', $healthValues);
            $SQLJoin .= " AND $tableName.health IN ($healthValues) ";
          }
          if ($restricted) addSQLWhere($SQLWhere, "(ac.unilateral is null OR ac.unilateral != 1) AND (ac.id not in (select au.id from accidents au LEFT JOIN accidentpersons apu ON au.id = apu.accidentid WHERE apu.transportationmode != $transportationMode))");
          if ($unilateral) addSQLWhere($SQLWhere, "ac.unilateral = 1");
        }
      }

      $orderField = ($sort === 'crashDate')? 'ac.date DESC, ac.streamdatetime DESC' : 'ac.streamdatetime DESC';

      $SQLWhere = <<<SQL
   $SQLJoin      
   $SQLWhere
  ORDER BY $orderField 
  LIMIT $offset, $count
SQL;
    }

    $sql .= $SQLWhere;
    $ids = [];
    $DBResults = $database->fetchAll($sql, $params);
    foreach ($DBResults as $crash) {
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
      $crash['tree']                  = $crash['tree'] == 1;

      // Load persons
      $crash['persons'] = [];
      $DBPersons = $database->fetchAllPrepared($DBStatementPersons, ['accidentid' => $crash['id']]);
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
WHERE ar.accidentid IN ($commaArrays)
 $sqlModerated
ORDER BY ar.streamdatetime DESC
SQL;

      $DBResults = $database->fetchAll($sqlArticles, $params);
      foreach ($DBResults as $article) {
        $article = cleanArticleDBRow($article);
        $articles[] = $article;
      }
    }

    $result = ['ok' => true, 'crashes' => $crashes, 'articles' => $articles];
    if ($offset === 0) {
      $result['user'] = $user->info();
    }
  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'getuser') {
  try {
    $result = ['ok' => true, 'user' => $user->info()];
  } catch (Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  $json = json_encode($result);
  if ($json) echo $json;
  else echo json_encode(['ok' => false, 'error' => json_last_error()]);
} //==========
else if ($function === 'getPageMetaData'){
  try{
    $data       = json_decode(file_get_contents('php://input'), true);
    $url        = $data['url'];
    $newArticle = $data['newArticle'];

    function getFirstAvailableTag($tags){
      $result = '';
      foreach ($tags as $tag){
        if (isset($tag) && (! empty($tag)) && (strlen($tag) > strlen($result))) $result = $tag;
      }
      return $result;
    }

    $metaData     = getPageMediaMetaData($url);
    $ogTags       = $metaData['og'];
    $twitterTags  = $metaData['twitter'];
    $articleTags  = $metaData['article'];
    $itemPropTags = $metaData['itemprop'];
    $tagCount     = [
      'og'       => count($metaData['og']),
      'twitter'  => count($metaData['twitter']),
      'article'  => count($metaData['article']),
      'itemprop' => count($metaData['itemprop']),
      'other'    => count($metaData['other']),
      ];

    // Decode HTML entities to normal text
    $media = [
      'url'            => getFirstAvailableTag([$ogTags['og:url'], $url]),
      'urlimage'       => getFirstAvailableTag([$ogTags['og:image']]),
      'title'          => html_entity_decode(htmlspecialchars_decode(strip_tags(getFirstAvailableTag([$ogTags['og:title'], $twitterTags['twitter:title']]))),ENT_QUOTES),
      'description'    => html_entity_decode(strip_tags(htmlspecialchars_decode(getFirstAvailableTag([$ogTags['og:description'], $twitterTags['twitter:description'], $metaData['other']['description']]))),ENT_QUOTES),
      'sitename'       => html_entity_decode(htmlspecialchars_decode(getFirstAvailableTag([$ogTags['og:site_name'], $metaData['other']['domain']])),ENT_QUOTES),
      'published_time' => getFirstAvailableTag([$ogTags['og:article:published_time'], $articleTags['article:published_time'], $itemPropTags['datePublished'], $articleTags['article:modified_time']]),
    ];

    // Replace http with https on image tags. Hart van Nederland sends unsecure links
    $media['urlimage'] = str_replace('http://', 'https://', $media['urlimage']);
    if (substr($media['urlimage'], 0, 1) === '/') {
      $parse = parse_url($media['url']);
      $media['urlimage'] = 'https://' . $parse['host'] . $media['urlimage'];
    }

    // Plan C if no other info available: Use H1 for title. Description for description
    if (($media['title']          === '') && (isset($metaData['other']['h1'])))          $media['title']          = $metaData['other']['h1'];
    if (($media['description']    === '') && (isset($metaData['other']['description']))) $media['description']    = $metaData['other']['description'];
    if (($media['published_time'] === '') && (isset($metaData['other']['time'])))        $media['published_time'] = $metaData['other']['time'];

    // Check if new article url already in database.
    if ($newArticle) $urlExists = urlExists($database, $media['url']);
    else $urlExists = false;

    $result = ['ok' => true, 'media' => $media, 'tagcount' => $tagCount, 'urlExists' => $urlExists];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'saveArticleCrash'){
  try {
    $data                         = json_decode(file_get_contents('php://input'), true);
    $article                      = $data['article'];
    $crash                        = $data['crash'];
    $saveArticle                  = $data['saveArticle'];
    $saveCrash                    = $data['saveCrash'];
    $isNewCrash                   = (! isset($crash['id'])) || ($crash['id'] <= 0);
    $moderationRequired           = ! $user->isModerator();
    $crashIsAwaitingModeration    = $moderationRequired && $isNewCrash;
    $articleIsAwaitingModeration  = $moderationRequired && (! $crashIsAwaitingModeration);

    // Check if new article url already in database.
    if ($saveArticle && ($article['id'] < 1)){
      $exists = urlExists($database, $article['url']);
      if ($exists) throw new Exception("<a href='/{$exists['crashId']}}' style='text-decoration: underline;'>Er is al een ongeluk met deze link</a>", 1);
    }

    if ($saveCrash){
      if (! $isNewCrash){
        // Update existing crash

        // We don't set awaitingmoderation for updates because it is unfriendly for helpers. We may need to come back on this policy if it is misused.
        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
        $sql = <<<SQL
    UPDATE accidents SET
      streamdatetime  = current_timestamp,
      streamtoptype   = 1, 
      streamtopuserid = :userid,
      title           = :title,
      text            = :text,
      date            = :date,
      latitude        = :latitude,
      longitude       = :longitude,
      unilateral      = :unilateral,
      pet             = :pet,
      trafficjam      = :trafficjam,
      tree            = :tree
    WHERE id=:id $sqlANDOwnOnly
SQL;
        $params = array(
          ':id'                    => $crash['id'],
          ':userid'                => $user->id,
          ':title'                 => $crash['title'],
          ':text'                  => $crash['text'],
          ':date'                  => $crash['date'],
          ':latitude'              => empty($crash['latitude'])?  null : $crash['latitude'],
          ':longitude'             => empty($crash['longitude'])? null : $crash['longitude'],
          ':unilateral'            => $crash['unilateral'],
          ':pet'                   => $crash['pet'],
          ':trafficjam'            => $crash['trafficjam'],
          ':tree'                  => $crash['tree'],
        );
        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new Exception('Helpers kunnen alleen hun eigen ongelukken updaten.');
      } else {
        // New crash

        $sql = <<<SQL
    INSERT INTO accidents (userid, awaitingmoderation, title, text, date, latitude, longitude, unilateral, pet, trafficjam, tree)
    VALUES (:userid, :awaitingmoderation, :title, :text, :date, :latitude, :longitude, :unilateral, :pet, :trafficjam, :tree);
SQL;

        $params = array(
          ':userid'                => $user->id,
          ':awaitingmoderation'    => $moderationRequired,
          ':title'                 => $crash['title'],
          ':text'                  => $crash['text'],
          ':date'                  => $crash['date'],
          ':latitude'              => $crash['latitude'],
          ':longitude'             => $crash['longitude'],
          ':unilateral'            => $crash['unilateral'],
          ':pet'                   => $crash['pet'],
          ':trafficjam'            => $crash['trafficjam'],
          ':tree'                  => $crash['tree'],
        );
        $dbresult = $database->execute($sql, $params);
        $crash['id'] = (int)$database->lastInsertID();
      }

      // Save crash persons
      $sql    = "DELETE FROM accidentpersons WHERE accidentid=:crashId;";
      $params = ['crashId' => $crash['id']];
      $database->execute($sql, $params);

    $sql         = <<<SQL
INSERT INTO accidentpersons (accidentid, groupid, transportationmode, health, child, underinfluence, hitrun) 
VALUES (:crashId, :groupid, :transportationmode, :health, :child, :underinfluence, :hitrun);
SQL;
      $dbStatement = $database->prepare($sql);
      foreach ($crash['persons'] AS $person){
        $params = [
          ':crashId'            => $crash['id'],
          ':groupid'            => $person['groupid'],
          ':transportationmode' => $person['transportationmode'],
          ':health'             => $person['health'],
          ':child'              => $person['child'],
          ':underinfluence'     => $person['underinfluence'],
          ':hitrun'             => $person['hitrun'],
        ];
        $dbStatement->execute($params);
      }

      $params = ['crashId' => $crash['id']];
    }

    if ($saveArticle){
      if ($article['id'] > 0){
        // Update article

        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';

        $sql = <<<SQL
    UPDATE articles SET
      accidentid    = :crashId,
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
          ':crashId'     => $crash['id'],
          ':url'         => $article['url'],
          ':title'       => $article['title'],
          ':text'        => $article['text'],
          ':alltext'     => $article['alltext'],
          ':date'        => $article['date'],
          ':sitename'    => $article['sitename'],
          ':urlimage'    => $article['urlimage'],
          ':id'          => $article['id'],
        ];

        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;
        $database->execute($sql, $params, true);

        if (! $saveCrash) setCrashStreamTop($database, $crash['id'], $user->id, 1);

      } else {
        // New article
        $sql = <<<SQL
    INSERT INTO articles (userid, awaitingmoderation, accidentid, url, title, text, alltext, publishedtime, sitename, urlimage)
    VALUES (:userid, :awaitingmoderation, :accidentid, :url, :title, :text, :alltext, :publishedtime, :sitename, :urlimage);
SQL;
        // Article moderation is only required if the crash is not awaiting moderation
        $article['userid']             = $user->id;
        $article['accidentid']         = $crash['id'];
        $article['awaitingmoderation'] = $articleIsAwaitingModeration;
        $params = array(
          ':userid'             => $article['userid'],
          ':awaitingmoderation' => $article['awaitingmoderation'],
          ':accidentid'         => $article['accidentid'],
          ':url'                => $article['url'],
          ':title'              => $article['title'],
          ':text'               => $article['text'],
          ':alltext'            => $article['alltext'],
          ':sitename'           => $article['sitename'],
          ':publishedtime'      => $article['date'],
          ':urlimage'           => $article['urlimage']);

        $database->execute($sql, $params);
        $article['id'] = (int)$database->lastInsertID();

        if (! $saveCrash) setCrashStreamTop($database, $crash['id'], $user->id, 2);
      }
    }

    $result = ['ok' => true, 'crashId' => $crash['id']];
    if ($saveArticle) {
      $sqlArticleSelect = getArticleSelect();
      $sqlArticle = "$sqlArticleSelect WHERE ar.ID=:id";

      $DBArticle = $database->fetch($sqlArticle, ['id' => $article['id']]);
      $DBArticle = cleanArticleDBRow($DBArticle);

      $result['article'] = $DBArticle;
    }
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'mergeCrashes'){
  try {
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken samenvoegen.');

    $idFrom = (int)$_REQUEST['idFrom'];
    $idTo   = (int)$_REQUEST['idTo'];

    // Move articles to other crash
    $sql    = "UPDATE articles set accidentid=:idTo WHERE accidentid=:idFrom;";
    $params = [':idFrom' => $idFrom, ':idTo' => $idTo];
    $database->execute($sql, $params);

    $sql    = "DELETE FROM accidents WHERE id=:idFrom;";
    $params = [':idFrom' => $idFrom];
    $database->execute($sql, $params);

    $sql = "UPDATE accidents SET streamdatetime=current_timestamp, streamtoptype=1, streamtopuserid=:userId WHERE id=:id";
    $params = array(':id' => $idTo, ':userId' => $user->id);
    $database->execute($sql, $params);

    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteArticle'){
  try{
    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM articles WHERE id=:id $sqlANDOwnOnly ;";
      $params = array(':id' => $crashId);
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Kan artikel niet verwijderen.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteCrash'){
  try{
    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM accidents WHERE id=:id $sqlANDOwnOnly ;";
      $params = array(':id' => $crashId);
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Alleen moderatoren mogen ongelukken verwijderen.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'crashToStreamTop'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken omhoog plaatsen.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0) setCrashStreamTop($database, $crashId, $user->id, 3);
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'crashModerateOK'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken modereren.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sql    = "UPDATE accidents SET awaitingmoderation=0 WHERE id=:id;";
      $params = array(':id' => $crashId);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'articleModerateOK'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen artikelen modereren.');

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $sql    = "UPDATE articles SET awaitingmoderation=0 WHERE id=:id;";
      $params = array(':id' => $crashId);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getArticleText'){
  try{

    $crashId = (int)$_REQUEST['id'];
    if ($crashId > 0){
      $params = array(':id' => $crashId);

      $sqlANDOwnOnly = '';
      if (! $user->isModerator()) {
        $params[':useridwhere'] = $user->id;
        $sqlANDOwnOnly = ' AND userid=:useridwhere ';
      }

      $sql  = "SELECT alltext FROM articles WHERE id=:id $sqlANDOwnOnly;";
      $text = $database->fetchSingleValue($sql, $params);
    }
    $result = ['ok' => true, 'text' => $text];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getStatistics'){
  try{
    $data = json_decode(file_get_contents('php://input'), true);

    $type              = $data['type']?? '';
    $searchPeriod      = $data['searchPeriod']?? '';
    $searchDateFrom    = $data['searchDateFrom']?? '';
    $searchDateTo      = $data['searchDateTo']?? '';

    if      ($type === 'general')       $stats = getStatsDatabase($database);
    else if ($type === 'crashPartners') $stats = getStatsCrashPartners($database, $searchPeriod, $searchDateFrom, $searchDateTo);
    else                                $stats = getStatsTransportation($database, $searchPeriod, $searchDateFrom, $searchDateTo);

    $result = ['ok' => true,
      'statistics' => $stats,
      'user'       => $user->info()
    ];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}