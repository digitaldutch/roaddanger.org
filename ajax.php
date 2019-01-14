<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'initialize.php';

global $user;

$function = $_REQUEST['function'];

/**
 * @param TDatabase $database
 * @return array
 */
function getStatsTransportation($database, $period='all'){
  $stats = [];

  switch ($period) {
    case '24hours': $SQLWhere = ' WHERE DATE (`date`) > subdate(NOW(), INTERVAL 24 HOUR) '; break;
    case '7days':   $SQLWhere = ' WHERE DATE (`date`) > subdate(CURDATE(), 7) '; break;
    case '30days':  $SQLWhere = ' WHERE DATE (`date`) > subdate(CURDATE(), 30) '; break;
    default:        $SQLWhere = '';
  }

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
JOIN accidents a ON ap.accidentid = a.id
  $SQLWhere
GROUP BY transportationmode
ORDER BY dead DESC, injured DESC
SQL;

//  AND DATE (a.date) > SUBDATE(CURDATE(), 300)

  $stats['total'] = $database->fetchAll($sql);
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
function getStatsDatabase($database){
  $stats = [];

  $stats['total'] = [];
  $sql = "SELECT COUNT(*) AS count FROM accidents";
  $stats['total']['accidents'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM articles";
  $stats['total']['articles'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) AS count FROM users";
  $stats['total']['users'] = $database->fetchSingleValue($sql);

  $stats['live'] = [];
  $sql = "SELECT COUNT(*) FROM accidents WHERE DATE (`createtime`) >= '2019-01-14'";
  $stats['live']['accidents'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM articles WHERE DATE (`createtime`) >= '2019-01-14'";
  $stats['live']['articles'] = $database->fetchSingleValue($sql);
  $sql = "SELECT COUNT(*) FROM users WHERE DATE (`registrationtime`) >= '2019-01-14'";
  $stats['live']['users'] = $database->fetchSingleValue($sql);

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
      'articleid'  => (int)$found['id'],
      'accidentid' => (int)$found['accidentid'],
      ];
  }
  return false;
}

if ($function == 'login') {
  if (is_null($_REQUEST['email']) || is_null($_REQUEST['password'])) dieWithJSONErrorMessage('Invalid AJAX login call.');

  $email        = $_REQUEST['email'];
  $password     = $_REQUEST['password'];
  $stayLoggedIn = (int)getRequest('stayLoggedIn', 0) === 1;

  $user->login($email, $password, $stayLoggedIn);
  echo json_encode($user->info());
} // ====================
else if ($function == 'register') {
  try {
    $data = json_decode(file_get_contents('php://input'), true);

    $user->register($data['firstname'], $data['lastname'], $data['email'], $data['password']);
    $result = array('ok' => true);
  } catch (Exception $e) {
    $result = array('error' => $e->getMessage());
  }

  echo json_encode($result);
} // ====================
else if ($function == 'logout') {
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
else if ($function === 'loadaccidents') {
  try {
    $offset      = (int)getRequest('offset',0);
    $count       = (int)getRequest('count', 100);
    $id          = isset($_REQUEST['id'])? (int)$_REQUEST['id'] : null;
    $search      = isset($_REQUEST['search'])? $_REQUEST['search'] : '';
    $moderations = (int)getRequest('moderations', 0);
    $sort        = getRequest('sort');
    $export      = (int)getRequest('export', 0);

    if ($count > 1000) throw new Exception('Internal error: Count to high.');
    if ($moderations && (! $user->isModerator())) throw new Exception('Moderaties zijn alleen zichtbaar voor moderators.');

    $accidents    = [];
    $articles     = [];
    $params       = [];
    $sqlModerated = '';
    if ($moderations) {
      $sqlModerated = ' (ac.awaitingmoderation=1) OR (ac.id IN (SELECT accidentid FROM articles WHERE awaitingmoderation=1)) ';
    } else if ($id === null) {
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
  ac.pet, 
  ac.trafficjam, 
  ac.tree, 
  CONCAT(u.firstname, ' ', u.lastname) AS user, 
  CONCAT(tu.firstname, ' ', tu.lastname) AS streamtopuser 
FROM accidents ac
LEFT JOIN users u  on u.id  = ac.userid 
LEFT JOIN users tu on tu.id = ac.streamtopuserid
SQL;

    if ($id !== null) {
      // Single accident
      $params = ['id' => $id];
      $SQLWhere = " WHERE ac.id=:id ";
    } else if ($search !== '') {
      // Text search
      $params['search']  = $search;
      $params['search2'] = $search;

      if ($sqlModerated) $sqlModerated = ' AND ' . $sqlModerated;

      $SQLWhere = <<<SQL
 LEFT JOIN articles ar on ac.id = ar.accidentid      
 WHERE MATCH(ac.title, ac.text) AGAINST (:search  IN BOOLEAN MODE)
    OR MATCH(ar.title, ar.text) AGAINST (:search2 IN BOOLEAN MODE)
    $sqlModerated
ORDER BY streamdatetime DESC
LIMIT $offset, $count
SQL;
    } else {
      // accidents stream
      if ($sqlModerated) $sqlModerated = ' WHERE ' . $sqlModerated;
      $orderField = ($sort === 'accidentdate')? 'ac.date DESC, ac.streamdatetime DESC' : 'ac.streamdatetime DESC';
      $SQLWhere = " $sqlModerated ORDER BY $orderField LIMIT $offset, $count ";
    }

    $sql .= $SQLWhere;
    $ids = [];
    $DBResults = $database->fetchAll($sql, $params);
    foreach ($DBResults as $accident) {
      $accident['id']                    = (int)$accident['id'];
      $accident['userid']                = (int)$accident['userid'];
      $accident['streamtopuserid']       = (int)$accident['streamtopuserid'];
      $accident['streamtoptype']         = (int)$accident['streamtoptype'];
      $accident['createtime']            = datetimeDBToISO8601($accident['createtime']);
      $accident['streamdatetime']        = datetimeDBToISO8601($accident['streamdatetime']);
      $accident['awaitingmoderation']    = $accident['awaitingmoderation'] == 1;

      $accident['pet']                   = $accident['pet'] == 1;
      $accident['trafficjam']            = $accident['trafficjam'] == 1;
      $accident['tree']                  = $accident['tree'] == 1;

      // Load persons
      $accident['persons'] = [];
      $DBPersons = $database->fetchAllPrepared($DBStatementPersons, ['accidentid' => $accident['id']]);
      foreach ($DBPersons as $person) {
        $person['groupid']            = isset($person['groupid'])? (int)$person['groupid'] : null;
        $person['transportationmode'] = (int)$person['transportationmode'];
        $person['health']             = isset($person['health'])? (int)$person['health'] : null;
        $person['child']              = (int)$person['child'];
        $person['underinfluence']     = (int)$person['underinfluence'];
        $person['hitrun']             = (int)$person['hitrun'];

        $accident['persons'][] = $person;
      }

      $ids[] = $accident['id'];
      $accidents[] = $accident;
    }

    if (count($accidents) > 0){
      $params = [];
      $sqlModerated = '';
      if ($moderations) {
        // In the moderation queue all articles are shown
      } else if ($id === null) { // Individual pages are always shown and *not* moderated. Needed
        $sqlModerated = $user->isModerator()? '':  ' AND ((ar.awaitingmoderation=0) || (ar.userid=:useridModeration)) ';
        if ($sqlModerated) $params[':useridModeration'] = $user->id;
      }

      $commaArrays = implode (", ", $ids);
      $sqlArticles = <<<SQL
SELECT
  ar.id,
  ar.userid,
  ar.awaitingmoderation,
  ar.accidentid,
  ar.title,
  ar.text,
  ar.createtime,
  ar.publishedtime,
  ar.streamdatetime,
  ar.sitename,
  ar.url,
  ar.urlimage,
  CONCAT(u.firstname, ' ', u.lastname) AS user 
FROM articles ar
JOIN users u on u.id = ar.userid
WHERE ar.accidentid IN ($commaArrays)
 $sqlModerated
ORDER BY ar.streamdatetime DESC
SQL;

      $DBResults = $database->fetchAll($sqlArticles, $params);
      foreach ($DBResults as $article) {
        $article['id']                 = (int)$article['id'];
        $article['userid']             = (int)$article['userid'];
        $article['awaitingmoderation'] = $article['awaitingmoderation'] == 1;
        $article['accidentid']         = (int)$article['accidentid'];
        $accident['createtime']        = datetimeDBToISO8601($accident['createtime']);
        $accident['publishedtime']     = datetimeDBToISO8601($accident['publishedtime']);
        $accident['streamdatetime']    = datetimeDBToISO8601($accident['streamdatetime']);
        // JD NOTE: Do not sanitize strings. We handle escaping in JavaScript

        $articles[] = $article;
      }
    }

    $result = ['ok' => true, 'accidents' => $accidents, 'articles' => $articles];
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
      'title'          => html_entity_decode(strip_tags(getFirstAvailableTag([$ogTags['og:title'], $twitterTags['twitter:title']])),ENT_QUOTES),
      'description'    => html_entity_decode(strip_tags(getFirstAvailableTag([$ogTags['og:description'], $twitterTags['twitter:description'], $metaData['other']['description']])),ENT_QUOTES),
      'sitename'       => html_entity_decode(getFirstAvailableTag([$ogTags['og:site_name'], $metaData['other']['domain']]),ENT_QUOTES),
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

    $result = ['ok' => true, 'media' => $media, 'tagcount' => $tagCount, 'urlexists' => $urlExists];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} //==========
else if ($function === 'saveArticleAccident'){
  try {
    $data                         = json_decode(file_get_contents('php://input'), true);
    $article                      = $data['article'];
    $accident                     = $data['accident'];
    $saveArticle                  = $data['savearticle'];
    $saveAccident                 = $data['saveaccident'];
    $isNewAccident                = (! isset($accident['id'])) || ($accident['id'] <= 0);
    $moderationRequired           = ! $user->isModerator();
    $accidentIsAwaitingModeration = $moderationRequired && $isNewAccident;
    $articleIsAwaitingModeration  = $moderationRequired && (! $accidentIsAwaitingModeration);

    // Check if new article url already in database.
    if ($saveArticle && ($article['id'] < 1)){
      $exists = urlExists($database, $article['url']);
      if ($exists) throw new Exception("<a href='/{$exists['accidentid']}}' style='text-decoration: underline;'>Er is al een ongeluk met deze link</a>", 1);
    }

    if ($saveAccident){
      if (! $isNewAccident){
        // Update existing accident

        // We don't set awaitingmoderation for updates because it is unfriendly for helpers. We may need to come back on this policy if it is misused.
        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
        $sql = <<<SQL
    UPDATE accidents SET
      updatetime            = current_timestamp,
      streamdatetime        = current_timestamp,
      streamtoptype         = 1, 
      streamtopuserid       = :userid,
      title                 = :title,
      text                  = :text,
      date                  = :date,
      pet                   = :pet,
      trafficjam            = :trafficjam,
      tree                  = :tree
    WHERE id=:id $sqlANDOwnOnly
SQL;
        $params = array(
          ':id'                    => $accident['id'],
          ':userid'                => $user->id,
          ':title'                 => $accident['title'],
          ':text'                  => $accident['text'],
          ':date'                  => $accident['date'],
          ':pet'                   => $accident['pet'],
          ':trafficjam'            => $accident['trafficjam'],
          ':tree'                  => $accident['tree'],
        );
        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        $database->execute($sql, $params, true);
        if ($database->rowCount === 0) throw new Exception('Helpers kunnen alleen hun eigen ongelukken updaten. Sorry.');
      } else {
        // New accident

        $sql = <<<SQL
    INSERT INTO accidents (userid, awaitingmoderation, title, text, date, pet, trafficjam, tree)
    VALUES (:userid, :awaitingmoderation, :title, :text, :date, :pet, :trafficjam, :tree);
SQL;

        $params = array(
          ':userid'                => $user->id,
          ':awaitingmoderation'    => $moderationRequired,
          ':title'                 => $accident['title'],
          ':text'                  => $accident['text'],
          ':date'                  => $accident['date'],
          ':pet'                   => $accident['pet'],
          ':trafficjam'            => $accident['trafficjam'],
          ':tree'                  => $accident['tree'],
        );
        $dbresult = $database->execute($sql, $params);
        $accident['id'] = (int)$database->lastInsertID();
      }

      // Save accident persons
      $sql    = "DELETE FROM accidentpersons WHERE accidentid=:accidentid;";
      $params = ['accidentid' => $accident['id']];
      $database->execute($sql, $params);

    $sql         = <<<SQL
INSERT INTO accidentpersons (accidentid, groupid, transportationmode, health, child, underinfluence, hitrun) 
VALUES (:accidentid, :groupid, :transportationmode, :health, :child, :underinfluence, :hitrun);
SQL;
      $dbStatement = $database->prepare($sql);
      foreach ($accident['persons']  AS $person){
        $params = [
          ':accidentid'         => $accident['id'],
          ':groupid'            => $person['groupid'],
          ':transportationmode' => $person['transportationmode'],
          ':health'             => $person['health'],
          ':child'              => $person['child'],
          ':underinfluence'     => $person['underinfluence'],
          ':hitrun'             => $person['hitrun'],
        ];
        $dbStatement->execute($params);
      }

      $params = ['accidentid' => $accident['id']];

    }

    if ($saveArticle){
      if ($article['id'] > 0){
        // Update article

        $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';

        $sql = <<<SQL
    UPDATE articles SET
      accidentid  = :accidentid,
      url         = :url,
      title       = :title,
      text        = :text,
      publishedtime = :date,
      sitename    = :sitename,
      urlimage    = :urlimage
      WHERE id=:id $sqlANDOwnOnly
SQL;
        $params = [
          ':accidentid'  => $accident['id'],
          ':url'         => $article['url'],
          ':title'       => $article['title'],
          ':text'        => $article['text'],
          ':date'        => $article['date'],
          ':sitename'    => $article['sitename'],
          ':urlimage'    => $article['urlimage'],
          ':id'          => $article['id'],
        ];

        if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

        $database->execute($sql, $params, true);
      } else {
        // New article

        $sql = <<<SQL
    INSERT INTO articles (userid, awaitingmoderation, accidentid, url, title, text, publishedtime, sitename, urlimage)
    VALUES (:userid, :awaitingmoderation, :accidentid, :url, :title, :text, :date, :sitename, :urlimage);
SQL;
        // Article moderation is only required if the accident is not awaiting moderation
        $params = array(
          ':userid'             => $user->id,
          ':awaitingmoderation' => $articleIsAwaitingModeration,
          ':accidentid'         => $accident['id'],
          ':url'                => $article['url'],
          ':title'              => $article['title'],
          ':text'               => $article['text'],
          ':sitename'           => $article['sitename'],
          ':date'               => $article['date'],
          ':urlimage'           => $article['urlimage']);

        $database->execute($sql, $params);
        $article['id'] = $database->lastInsertID();

        if (! $saveAccident){
          // New artikel
          // Update accident streamtype
          $sql = <<<SQL
    UPDATE accidents SET
      updatetime      = current_timestamp,
      streamdatetime  = current_timestamp,
      streamtoptype   = 2, 
      streamtopuserid = :userid
    WHERE id=:id;
SQL;
          $params = array(
            ':id'             => $accident['id'],
            ':userid'         => $user->id,
          );
          $database->execute($sql, $params);
        }
      }
    }

    $result = ['ok' => true, 'accidentid' => $accident['id']];
    if ($saveArticle) $result['articleid']  = (int)$article['id'];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage(), 'errorcode' => $e->getCode()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'deleteArticle'){
  try{
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM articles WHERE id=:id $sqlANDOwnOnly ;";
      $params = array(':id' => $id);
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
else if ($function === 'deleteAccident'){
  try{
    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sqlANDOwnOnly = (! $user->isModerator())? ' AND userid=:useridwhere ' : '';
      $sql = "DELETE FROM accidents WHERE id=:id $sqlANDOwnOnly ;";
      $params = array(':id' => $id);
      if (! $user->isModerator()) $params[':useridwhere'] = $user->id;

      $database->execute($sql, $params, true);
      if ($database->rowCount === 0) throw new Exception('Alleen moderatoren mogen ongelukken verwijderen. Sorry.');
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'accidentToTopStream'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken omhoog plaatsen. Sorry.');

    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql    = "UPDATE accidents SET streamdatetime=current_timestamp, streamtoptype=3, streamtopuserid=:userid WHERE id=:id;";
      $params = array(':id' => $id, ':userid' => $user->id);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'accidentModerateOK'){
  try{
    if (! $user->isModerator()) throw new Exception('Alleen moderatoren mogen ongelukken modereren.');

    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql    = "UPDATE accidents SET awaitingmoderation=0 WHERE id=:id;";
      $params = array(':id' => $id);
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

    $id = (int)$_REQUEST['id'];
    if ($id > 0){
      $sql    = "UPDATE articles SET awaitingmoderation=0 WHERE id=:id;";
      $params = array(':id' => $id);
      $database->execute($sql, $params);
    }
    $result = ['ok' => true];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} //==========
else if ($function === 'getstats'){
  try{

    $period = getRequest('period','all');
    $type   = getRequest('type','');

    if ($type === 'general') $stats = getStatsDatabase($database);
    else $stats = getStatsTransportation($database, $period);

    $result = ['ok' => true,
      'statistics' => $stats,
      'user'       => $user->info()
    ];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}