<?php

require_once '../general/AjaxHandler.php';
class ExportHandler extends AjaxHandler {

  public function handleRequest($command): void {
    try {
      $response = match ($command) {
        'downloadCrashesData' => $this->downloadCrashesData(),
        'downloadResearchData' => $this->downloadResearchData(),

        default => throw new Exception('Invalid command'),
      };

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }

  private function downloadCrashesData(): array {
    // Only admins can download the crashes with full text.
    // $includeAllText = $user->admin;

    // NOTE: Everyone can download all data including full texts
    $includeAllText = true;

    $filename = $includeAllText? WEBSITE_NAME . '_data_all_text.json.gz' : WEBSITE_NAME . '_data.json.gz';

    // Recreate backup if existing backup file older than 24 hours
    if ((!file_exists($filename)) || (time()-filemtime($filename) > 24 * 3600)) {

      // We need more memory to parse all data. 128MB allows a little over 10,000 crashes.
      // 1028M should allow 80,000 crashes with all text.
      // We should not use more than a safe percentage of the server-installed memory
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
      $DBStatementPersons = $this->database->prepare($sql);

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
      $DBStatementArticles = $this->database->prepare($sql);

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

      $DBResults = $this->database->fetchAll($sql);
      foreach ($DBResults as $crash) {
        $crash['latitude'] = isset($crash['latitude'])? (float) $crash['latitude'] : null;
        $crash['longitude'] = isset($crash['longitude'])? (float) $crash['longitude'] : null;
        $crash['date'] = datetimeDBToISO8601($crash['date']);

        // Load persons
        $crash['persons'] = $this->database->fetchAllPrepared($DBStatementPersons, ['crashid' => $crash['id']]);

        // Load articles
        $crash['articles'] = [];
        $DBArticles = $this->database->fetchAllPrepared($DBStatementArticles, ['crashid' => $crash['id']]);
        foreach ($DBArticles as $article) {
          $crash['publishedtime'] = datetimeDBToISO8601($crash['publishedtime'] ?? '');

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

    return [
      'filename' => $filename,
    ];
  }

  private function downloadResearchData(): array {
    $filename = WEBSITE_NAME . '_research_data.json.gz';

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

      $questions = $this->database->fetchAll($sql);

      $sql = <<<SQL
SELECT
  questionid, articleid, answer
FROM answers;
SQL;
      $answers = $this->database->fetchAll($sql);

      $dataOut = [
        'questions' => $questions,
        'answers' => $answers,
      ];

      $gzData = gzencode(json_encode($dataOut));
      file_put_contents($filename, $gzData);
    }

    return ['filename' => $filename];
  }
}