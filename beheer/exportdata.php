<?php

require_once '../initialize.php';

global $user;

$function = $_REQUEST['function'];

function removeCommas($text){
  return str_replace(",", "", $text);
}

if ($function === 'downloadData'){
  try{

    // Only admins can download the crashes with full text.
    $includeAllText = $user->admin;

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
FROM accidentpersons
WHERE accidentid=:accidentid
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
  text AS summary
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
  ac.latitude,
  ac.longitude,
  ac.unilateral, 
  ac.pet, 
  ac.trafficjam 
FROM accidents ac
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

      $gzData = gzencode(json_encode($crashes));
      file_put_contents($filename, $gzData);
    }

    $result = ['ok' => true, 'filename' => $filename];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} else if ($function === 'downloadCorrespondentWeekData'){
  try{

    // Only admins can download the crashes with full text.
    $includeAllText = $user->admin;

    $filename = 'hetongeluk_nl_crashes_correspondent_week.csv';

    // Recreate backup if existing backup file older than 24 hours
    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {
      $maxRows = 10000;

      $sql = <<<SQL
SELECT
  id,
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
SELECT DISTINCT 
  ac.id,
  ac.title,
  ac.date,
  ac.unilateral, 
  ac.pet, 
  ac.trafficjam 
FROM accidents ac
WHERE DATE (`date`) >= '2019-01-14' AND DATE (`date`) <= '2019-01-20'
ORDER BY date DESC 
LIMIT 0, $maxRows
SQL;

      $crashes = [];
      $DBResults = $database->fetchAll($sql);
      foreach ($DBResults as $crash) {
        $crash['id']         = (int)$crash['id'];
        $crash['unilateral'] = (int)$crash['unilateral'];
        $crash['pet']        = (int)$crash['pet'];
        $crash['trafficjam'] = (int)$crash['trafficjam'];
        $crash['date']       = datetimeDBToISO8601($crash['date']);

        // Load persons
        $crash['persons'] = [];
        $crash['dead']    = 0;
        $crash['injured'] = 0;

        $crash['unknownTransport'] = 0;
        $crash['pedestrian']       = 0;
        $crash['bicycle']          = 0;
        $crash['scooter']          = 0;
        $crash['motorcycle']       = 0;
        $crash['car']              = 0;
        $crash['taxi']             = 0;
        $crash['emergencyVehicle'] = 0;
        $crash['deliveryVan']      = 0;
        $crash['tractor']          = 0;
        $crash['bus']              = 0;
        $crash['tram']             = 0;
        $crash['truck']            = 0;
        $crash['train']            = 0;
        $crash['wheelchair']       = 0;
        $crash['mopedCar']         = 0;

        $DBPersons = $database->fetchAllPrepared($DBStatementPersons, ['accidentid' => $crash['id']]);
        foreach ($DBPersons as $person) {
          $person['groupid']            = isset($person['groupid'])? (int)$person['groupid'] : null;
          $person['transportationmode'] = (int)$person['transportationmode'];
          $person['health']             = isset($person['health'])? (int)$person['health'] : null;
          $person['child']              = (int)$person['child'];
          $person['underinfluence']     = (int)$person['underinfluence'];
          $person['hitrun']             = (int)$person['hitrun'];

          if ($person['health'] === 3) $crash['dead']    += 1;
          if ($person['health'] === 2) $crash['injured'] += 1;

          if ($person['transportationmode'] === 0) $crash['unknownTransport'] += 1;
          if ($person['transportationmode'] === 1) $crash['pedestrian'] += 1;
          if ($person['transportationmode'] === 2) $crash['bicycle'] += 1;
          if ($person['transportationmode'] === 3) $crash['scooter'] += 1;
          if ($person['transportationmode'] === 4) $crash['motorcycle'] += 1;
          if ($person['transportationmode'] === 5) $crash['car'] += 1;
          if ($person['transportationmode'] === 6) $crash['taxi'] += 1;
          if ($person['transportationmode'] === 7) $crash['emergencyVehicle'] += 1;
          if ($person['transportationmode'] === 8) $crash['deliveryVan'] += 1;
          if ($person['transportationmode'] === 9) $crash['tractor'] += 1;
          if ($person['transportationmode'] === 10) $crash['bus'] += 1;
          if ($person['transportationmode'] === 11) $crash['tram'] += 1;
          if ($person['transportationmode'] === 12) $crash['truck'] += 1;
          if ($person['transportationmode'] === 13) $crash['train'] += 1;
          if ($person['transportationmode'] === 14) $crash['wheelchair'] += 1;
          if ($person['transportationmode'] === 15) $crash['mopedCar'] += 1;

          $crash['persons'][] = $person;
        }

        $ids[] = $crash['id'];
        $crashes[] = $crash;
      }

      $csv = 'id,title,date,dead,injured,unilateral,trafficjam,pet' .
        ',unknownTransport,pedestrian,bicycle,scooter,motorcycle,car,taxi,emergencyVehicle,deliveryVan,tractor,bus,tram,truck,train,wheelchair,mopedCar' .
        "\r\n";

      foreach ($crashes as $crash){
         $csv .=
           $crash['id']                        . ',' .
           '"' . removeCommas($crash['title']) . '",' .
           $crash['date']                      . ',' .
           $crash['dead']                      . ',' .
           $crash['injured']                   . ',' .
           $crash['unilateral']                . ',' .
           $crash['trafficjam']                . ',' .
           $crash['pet']                       . ',' .
           $crash['unknownTransport']          . ',' .
           $crash['pedestrian']                . ',' .
           $crash['bicycle']                   . ',' .
           $crash['scooter']                   . ',' .
           $crash['motorcycle']                . ',' .
           $crash['car']                       . ',' .
           $crash['taxi']                      . ',' .
           $crash['emergencyVehicle']          . ',' .
           $crash['deliveryVan']               . ',' .
           $crash['tractor']                   . ',' .
           $crash['bus']                       . ',' .
           $crash['tram']                      . ',' .
           $crash['truck']                     . ',' .
           $crash['train']                     . ',' .
           $crash['wheelchair']                . ',' .
           $crash['mopedCar']                  . ',' .
           "\r\n";
      }
      file_put_contents($filename, $csv);
    }

    $result = ['ok' => true, 'filename' => $filename];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} else if ($function === 'downloadCorrespondentWeekArticles'){
  try{

    $filename = 'hetongeluk_nl_articles_correspondent_week.csv';

    // Recreate backup if existing backup file older than 24 hours
    if (true) {
//    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {
      $maxRows = 10000;

      $sql = <<<SQL
SELECT DISTINCT 
  a.id,
  a.accidentid,
  a.publishedtime,
  a.title,
  a.sitename, 
  a.url, 
  a.urlimage 
FROM articles a
WHERE DATE (`publishedtime`) >= '2019-01-14' AND DATE (`publishedtime`) <= '2019-01-20'
ORDER BY publishedtime DESC 
LIMIT 0, $maxRows
SQL;

      $articles = [];
      $DBResults = $database->fetchAll($sql);
      foreach ($DBResults as $article) {
        $article['id']         = (int)$article['id'];
        $article['accidentid'] = (int)$article['accidentid'];
        $article['publishedtime'] = datetimeDBToISO8601($article['publishedtime']);

        $articles[] = $article;
      }

      $csv = 'id,accidentid,publishedtime,title,sitename,url,urlimage' . "\r\n";
      foreach ($articles as $article){
        $csv .=
          $article['id']                        . ',' .
          $article['accidentid']                . ',' .
          $article['publishedtime']             . ',' .
          '"' . removeCommas($article['title']) . '",' .
          removeCommas($article['sitename'])    . ',' .
          removeCommas($article['url'])         . ',' .
          removeCommas($article['urlimage'])    .
          "\r\n";
      }
      file_put_contents($filename, $csv);
    }

    $result = ['ok' => true, 'filename' => $filename];
  } catch (Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}