<?php

require_once '../initialize.php';

global $database;
global $user;

$function = $_REQUEST['function'];

if ($function === 'downloadCrashesData'){
  try{

    // Only admins can download the crashes with full text.
//    $includeAllText = $user->admin;

    // NOTE: Everyone can download all data including full texts
    $includeAllText = true;

    $filename = $includeAllText? 'roaddanger_org_data_all_text.json.gz' : 'roaddanger_org_data.json.gz';

    // Recreate backup if existing backup file older than 24 hours
    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {

      // We need more memory to parse all data. 128MB allows a little over 10,000 crashes.
      // 1028M should allow 80,000 crashes with all text.
      // We should not use more than a safe percentage of the server installed memory
      ini_set('memory_limit', '1028M');
      // 100,000 is the default
      $maxDownloadCrashes = 100000;

      // This can be much quicker using JSON_OBJECT() and JSON_ARRAYAGG()
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
  ac.countryid,
  ac.unilateral, 
  ac.pet, 
  ac.trafficjam
FROM crashes ac
ORDER BY date DESC 
LIMIT 0, $maxDownloadCrashes
SQL;

      $DBResults = $database->fetchAll($sql);
      foreach ($DBResults as $crash) {
        $crash['id'] = (int)$crash['id'];
        $crash['latitude'] = isset($crash['latitude'])? floatval($crash['latitude']) : null;
        $crash['longitude'] = isset($crash['longitude'])? floatval($crash['longitude']) : null;
        $crash['unilateral'] = (int)$crash['unilateral'];
        $crash['pet'] = (int)$crash['pet'];
        $crash['trafficjam'] = (int)$crash['trafficjam'];
        $crash['date'] = datetimeDBToISO8601($crash['date']);

        // Load persons
        $crash['persons'] = [];
        $DBPersons = $database->fetchAllPrepared($DBStatementPersons, ['crashid' => $crash['id']]);
        foreach ($DBPersons as $person) {
          $person['groupid'] = isset($person['groupid'])? (int)$person['groupid'] : null;
          $person['transportationmode'] = (int)$person['transportationmode'];
          $person['health'] = isset($person['health'])? (int)$person['health'] : null;
          $person['child'] = (int)$person['child'];
          $person['underinfluence'] = (int)$person['underinfluence'];
          $person['hitrun'] = (int)$person['hitrun'];

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

      $dataOut = [
        'crashes' => $crashes,
      ];
      $gzData = gzencode(json_encode($dataOut));
      file_put_contents($filename, $gzData);
    }

    $result = ['ok' => true, 'filename' => $filename];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
} else if ($function === 'downloadResearchData'){

  try {
    $filename = 'roaddanger_org_research_data.json.gz';

    // Recreate backup if existing backup file older than 24 hours
    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {

      ini_set('memory_limit', '1028M');

      // 100,000 is the default
      $maxDownload = 100000;

      $sql = <<<SQL
SELECT
  id, text, active, question_order, explanation
FROM questions;
SQL;

      $questions = [];
      $questions = $database->fetchAll($sql);

      $answers = [];
      $sql = <<<SQL
SELECT
  questionid, articleid, answer
FROM answers;
SQL;
      $answers = $database->fetchAll($sql);

      $dataOut = [
        'questions' => $questions,
        'answers' => $answers,
      ];

      $gzData = gzencode(json_encode($dataOut));
      file_put_contents($filename, $gzData);
    }


    $result = ['ok' => true, 'filename' => $filename];
  } catch (\Exception $e){
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }
  echo json_encode($result);
}