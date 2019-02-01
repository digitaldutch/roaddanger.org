<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

global $user;

$function = $_REQUEST['function'];


if ($function === 'downloadData'){
  try{

    $filename = 'hetongeluk_nl_crashes_latest.json.gz';

    // Recreate backup if existing backup file older than 24 hours
    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {
      $count = 10000;

      $sql = <<<SQL
SELECT
  id,
  groupid,
  transportationmode,
  health,
  child,
  underinfluence,
  hitrun
FROM accidentpersons
WHERE accidentid=:accidentid
SQL;
      $DBStatementPersons = $database->prepare($sql);

      $sql = <<<SQL
SELECT
  id,
  sitename,
  publishedtime,
  url,
  urlimage,
  title,
  text,
  alltext       
FROM articles
WHERE accidentid=:accidentid
SQL;
      $DBStatementArticles = $database->prepare($sql);

      $sql = <<<SQL
SELECT DISTINCT 
  ac.id,
  ac.title,
  ac.text,
  ac.date,
  ac.pet, 
  ac.trafficjam 
FROM accidents ac
ORDER BY date DESC 
LIMIT 0, $count
SQL;

      $DBResults = $database->fetchAll($sql);
      foreach ($DBResults as $crash) {
        $crash['id']         = (int)$crash['id'];
        $crash['pet']        = (int)$crash['pet'];
        $crash['trafficjam'] = (int)$crash['trafficjam'];
        $crash['date']       = datetimeDBToISO8601($crash['date']);

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

        // Load articles
        $crash['articles'] = [];
        $DBArticles = $database->fetchAllPrepared($DBStatementArticles, ['accidentid' => $crash['id']]);
        foreach ($DBArticles as $article) {
          $article['id']          = (int)$article['id'];
          $crash['publishedtime'] = datetimeDBToISO8601($crash['publishedtime']);

          $crash['articles'][] = $article;
        }

        $ids[] = $crash['id'];
        $crashes[] = $crash;
      }

      $gzData   = gzencode(json_encode($crashes));
      file_put_contents($filename, $gzData);
    }

    $result = ['ok' => true, 'filename' => $filename];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}