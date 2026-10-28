<?php

require_once __DIR__ . '/Cache.php';

/**
 * The gzip JSON exports of the crash data and the research data. Used by the export page (admin/ExportHandler.php)
 * and by the public url /api/crashes.json.gz (api/crashes.php).
 */
class ExportFiles {
  private Database $database;

  public function __construct(Database $database) {
    $this->database = $database;
  }

  /**
   * Returns the name of an export file in the cache folder. The file is renewed when it is older than 24 hours.
   * Only one request at a time creates the file, because it uses a lot of memory and time.
   * Other requests get the old file, or a message if there is no old file yet.
   * After an attempt (also a failed one) there are no new attempts for 10 minutes.
   */
  private function getExport(string $filename, callable $createContent): array {
    if (Cache::exists($filename, 24 * 3600)) return $this->getExportInfo($filename);

    $oldFileExists = Cache::exists($filename, PHP_INT_MAX);

    $lock = Cache::tryLock($filename);
    if ($lock === null) {
      if ($oldFileExists) return $this->getExportInfo($filename);
      throw new \Exception('The export is being prepared. Try again in a minute.');
    }

    try {
      // Another request may have finished the export while we were taking the lock
      if (Cache::exists($filename, 24 * 3600)) return $this->getExportInfo($filename);

      $attemptKey = $filename . '.attempt';
      if (Cache::exists($attemptKey, 10 * 60)) {
        if ($oldFileExists) return $this->getExportInfo($filename);
        throw new \Exception('The export could not be created. Try again in 10 minutes.');
      }
      // Saved before we start: a crash (out of memory) does not run any code afterwards
      Cache::set($attemptKey, (string)time());

      Cache::set($filename, $createContent());
    } finally {
      Cache::unlock($lock);
    }

    return $this->getExportInfo($filename);
  }

  /**
   * The browser saves the file with the date the export was made in the name, so downloads can be told apart.
   */
  private function getExportInfo(string $filename): array {
    return [
      'filename' => $filename,
      'date' => date('Y-m-d', Cache::modifiedTime($filename)),
    ];
  }

  public function crashes(): array {
    // NOTE: Everyone can download all data including full texts
    $includeAllText = true;

    $filename = $includeAllText? WEBSITE_NAME . '_data_all_text.json.gz' : WEBSITE_NAME . '_data.json.gz';

    return $this->getExport($filename, fn() => $this->createCrashesExport($includeAllText));
  }

  private function createCrashesExport(bool $includeAllText): string {

    // We need more memory to parse all data. 128MB allows a little over 10,000 crashes.
    // 1028M should allow 80,000 crashes with all text.
    // We should not use more than a safe percentage of the server-installed memory
    ini_set('memory_limit', '1028M');
    // 100,000 is the default
    $maxDownloadCrashes = 100000;

    // Persons and articles are loaded with one query each and added to their crash in PHP.
    // A query for every crash is about three times slower (21,000 crashes: 4 instead of 1.2 seconds on a local database).
    $sql = <<<SQL
SELECT
cp.crashid,
cp.id,
cp.groupid,
cp.transportationmode,
cp.health,
cp.child,
cp.underinfluence,
cp.hitrun
FROM crashpersons cp
  JOIN crashes ac ON ac.id = cp.crashid
WHERE ac.awaitingmoderation=0
ORDER BY cp.id
SQL;
    $personsByCrash = [];
    foreach ($this->database->fetchAll($sql) as $person) {
      $crashId = $person['crashid'];
      unset($person['crashid']);
      $personsByCrash[$crashId][] = $person;
    }

    $allText = $includeAllText? 'ar.alltext,' : '';
    $sql = <<<SQL
SELECT
ar.crashid,
ar.id,
ar.sitename,
ar.publishedtime,
ar.url,
ar.urlimage,
ar.title,
$allText
ar.text AS 'summary'
FROM articles ar
  JOIN crashes ac ON ac.id = ar.crashid
WHERE ac.awaitingmoderation=0 AND ar.awaitingmoderation=0
ORDER BY ar.id
SQL;
    $articlesByCrash = [];
    foreach ($this->database->fetchAll($sql) as $article) {
      $crashId = $article['crashid'];
      unset($article['crashid']);
      $article['publishedtime'] = datetimeDBToISO8601($article['publishedtime'] ?? '');
      $articlesByCrash[$crashId][] = $article;
    }

    $sql = <<<SQL
SELECT
ac.id,
ac.title,
ac.text,
ac.date,
st_x(ac.location) AS longitude,
st_y(ac.location) AS latitude,
ac.countryid,
ac.unilateral,
ac.pet,
ac.trafficjam
FROM crashes ac
WHERE ac.awaitingmoderation=0
ORDER BY date DESC
LIMIT 0, $maxDownloadCrashes
SQL;

    $crashes = [];
    foreach ($this->database->fetchAll($sql) as $crash) {
      $crash['date'] = datetimeDBToISO8601($crash['date']);
      $crash['persons'] = $personsByCrash[$crash['id']] ?? [];
      $crash['articles'] = $articlesByCrash[$crash['id']] ?? [];

      $crashes[] = $crash;
    }

    $dataOut = [
      'crashes' => $crashes,
    ];
    return gzencode(json_encode($dataOut));
  }

  public function research(): array {
    $filename = WEBSITE_NAME . '_research_data.json.gz';

    return $this->getExport($filename, fn() => $this->createResearchExport());
  }

  private function createResearchExport(): string {

    ini_set('memory_limit', '1028M');

    $sql = <<<SQL
SELECT
id, text, active, question_order, explanation
FROM questions;
SQL;

    $questions = $this->database->fetchAll($sql);

    // Only answers about articles that are public. Not about crashes or articles that wait for moderation.
    $sql = <<<SQL
SELECT
an.questionid, an.articleid, an.answer, an.explanation, an.answered_by_type, an.ai_info, an.answered_at
FROM answers an
JOIN articles ar ON ar.id = an.articleid
JOIN crashes c ON c.id = ar.crashid
WHERE ar.awaitingmoderation=0 AND c.awaitingmoderation=0;
SQL;
    $answers = $this->database->fetchAll($sql);

    $dataOut = [
      'questions' => $questions,
      'answers' => $answers,
    ];

    return gzencode(json_encode($dataOut));
  }
}
