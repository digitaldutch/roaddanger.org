<?php

require_once '../initialize.php';

global $database;
global $user;

$function = $_REQUEST['function'];

function removeCommas($text){
  return str_replace(",", "", $text);
}

if ($function === 'downloadData'){
  try{

    // Only admins can download the crashes with full text.
//    $includeAllText = $user->admin;

    // NOTE: All text downloads are disabled as the file gets quite large
    $includeAllText = false;

    $filename = $includeAllText? 'hetongeluk_nl_crashes_all_text_latest.json.gz' : 'hetongeluk_nl_crashes_latest.json.gz';

    // Recreate backup if existing backup file older than 24 hours
    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {
      $maxRows = 10000;

      $sql = <<<SQL
SELECT
  id,
  groupid,
  transportationmode,
  health,
  child,
  underinfluence,
  hitrun
FROM crashpersons
WHERE crashid=:crashid
SQL;
      $DBStatementPersons = $database->prepare($sql);

      $allText = $includeAllText? 'alltext,' : '';
      $sql = <<<SQL
SELECT
  id,
  sitename,
  publishedtime,
  url,
  urlimage,
  title,
  $allText       
  text AS 'summary'
FROM articles
WHERE crashid=:crashid
SQL;
      $DBStatementArticles = $database->prepare($sql);

      $sql = <<<SQL
SELECT DISTINCT 
  ac.id,
  ac.title,
  ac.text,
  ac.date,
  ac.latitude,
  ac.longitude,
  ac.unilateral, 
  ac.pet, 
  ac.trafficjam 
FROM crashes ac
ORDER BY date DESC 
LIMIT 0, $maxRows
SQL;

      $DBResults = $database->fetchAll($sql);
      foreach ($DBResults as $crash) {
        $crash['id']         = (int)$crash['id'];
        $crash['latitude']   = isset($crash['latitude'])? floatval($crash['latitude']) : null;
        $crash['longitude']  = isset($crash['longitude'])? floatval($crash['longitude']) : null;
        $crash['unilateral'] = (int)$crash['unilateral'];
        $crash['pet']        = (int)$crash['pet'];
        $crash['trafficjam'] = (int)$crash['trafficjam'];
        $crash['date']       = datetimeDBToISO8601($crash['date']);

        // Load persons
        $crash['persons'] = [];
        $DBPersons = $database->fetchAllPrepared($DBStatementPersons, ['crashid' => $crash['id']]);
        foreach ($DBPersons as $person) {
          $person['groupid']            = isset($person['groupid'])? (int)$person['groupid'] : null;
          $person['transportationmode'] = (int)$person['transportationmode'];
          $person['health']             = isset($person['health'])? (int)$person['health'] : null;
          $person['child']              = (int)$person['child'];
          $person['underinfluence']     = (int)$person['underinfluence'];
          $person['hitrun']             = (int)$person['hitrun'];

          $crash['persons'][] = $person;
        }

        // Load articles
        $crash['articles'] = [];
        $DBArticles = $database->fetchAllPrepared($DBStatementArticles, ['crashid' => $crash['id']]);
        foreach ($DBArticles as $article) {
          $article['id']          = (int)$article['id'];
          $crash['publishedtime'] = datetimeDBToISO8601($crash['publishedtime']);

          $crash['articles'][] = $article;
        }

        $ids[] = $crash['id'];
        $crashes[] = $crash;
      }

      $gzData = gzencode(json_encode($crashes));
      file_put_contents($filename, $gzData);
    }

    $result = ['ok' => true, 'filename' => $filename];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}