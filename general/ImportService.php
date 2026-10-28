<?php

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/SimilarCrashes.php';
require_once __DIR__ . '/ArticleTextCleaner.php';

/**
 * Imports articles from other sources (api/import.php, install/import_lexisnexis_csv.php).
 *
 * For each article:
 * 1. Is it already known? The same source and id, the same url, or the same title on (about) the same day: nothing is saved.
 * 2. The crash data comes with the article, or the AI reads the article (limited per day for each token).
 * 3. Is there a crash that fits? Then the article is added to it. Crashes that may be the same go to review,
 *    and everything else becomes a new crash. New crashes and articles wait for moderation, unless the token says otherwise.
 *
 * Every article is saved in the table import_articles with what happened.
 */
class ImportService {

  const MAX_ARTICLES_PER_CALL = 50;
  // A crash with at least this score gets the article. SimilarCrashes gives 4 for the same day and a place that fits
  // with similar words in the title, 6 or more if the place is exact too.
  const ATTACH_SCORE = 5;
  // A crash with a lower score, but not far off, is a reason to look before a new crash is made
  const REVIEW_SCORE = 3;

  const TRANSPORTATION_MODES = [
    'unknown' => 0, 'pedestrian' => 1, 'bicycle' => 2, 'motorScooter' => 3, 'motorcycle' => 4, 'car' => 5, 'taxi' => 6,
    'emergencyVehicle' => 7, 'deliveryVan' => 8, 'tractor' => 9, 'bus' => 10, 'tram' => 11, 'truck' => 12, 'train' => 13,
    'wheelchair' => 14, 'mopedCar' => 15, 'scooter' => 16,
  ];
  const HEALTH = ['unknown' => 0, 'uninjured' => 1, 'injured' => 2, 'dead' => 3];

  private Database $database;
  private int $userId;
  private ?int $tokenId;
  private bool $autoApprove;
  private int $maxAiPerDay;
  private bool $inTransaction = false;

  /**
   * @param array $token row of api_tokens: id (or null), userid, autoapprove, maxaiperday
   */
  public function __construct(Database $database, array $token) {
    $this->database = $database;
    $this->userId = (int)$token['userid'];
    $this->tokenId = isset($token['id']) ? (int)$token['id'] : null;
    $this->autoApprove = ! empty($token['autoapprove']);
    $this->maxAiPerDay = (int)($token['maxaiperday'] ?? 200);
  }

  /**
   * The row of an active token, or null. The token is only stored as a hash.
   */
  public static function findToken(Database $database, string $token): ?array {
    if (! preg_match('/^[a-f0-9]{64}$/', $token)) return null;

    $row = $database->fetch(
      'SELECT t.* FROM api_tokens t JOIN users u ON u.id = t.userid WHERE t.tokenhash=:hash AND t.active=1 AND u.permission IN (1, 2)',
      [':hash' => hash('sha256', $token)]);
    if (! $row) return null;

    $database->execute('UPDATE api_tokens SET lastused=current_timestamp WHERE id=:id', [':id' => $row['id']]);

    return $row;
  }

  /**
   * @param array $input source, externalid, title, alltext, date, sitename, url, urlimage, crash (optional), crashid (optional: add the article to this crash), uncertain (optional: 'create')
   * @return array status (created, attached, duplicate, review or error), message, crashId, articleId, matches
   */
  public function importArticle(array $input, bool $dryRun = false): array {
    $article = null;
    try {
      $article = $this->cleanArticle($input);

      $known = $this->findKnown($article);
      if ($known) return $this->finish($article, $input, $known, $dryRun);

      // The caller knows the crash: only add the article
      if (! empty($input['crashid'])) {
        $crashId = (int)$input['crashid'];
        if (! $this->database->fetchSingleValue('SELECT id FROM crashes WHERE id=:id', [':id' => $crashId])) throw new \Exception("Crash $crashId does not exist");

        $result = ['status' => 'attached', 'crashId' => $crashId];
        if (! $dryRun) $result = $this->save($article, [], $result);

        return $this->finish($article, $input, $result, $dryRun);
      }

      $usedAi = false;
      $crash = isset($input['crash']) && is_array($input['crash']) ? $input['crash'] : null;
      if ($crash === null) {
        // The AI costs money: a dry run only says that it would be used
        if ($dryRun) return ['status' => 'created', 'message' => 'The AI would read the article', 'dryRun' => true];

        $crash = $this->extractCrashWithAi($article);
        $usedAi = true;
      }
      $crash = $this->cleanCrash($crash, $article);

      $matches = SimilarCrashes::find($this->database, [
        'date' => $crash['date'], 'countryId' => $crash['countryid'], 'latitude' => $crash['latitude'],
        'longitude' => $crash['longitude'], 'accuracy' => $crash['locationaccuracy'], 'title' => $article['title'],
        'modes' => array_column($crash['persons'], 'transportationmode'),
      ], null, $this->userId, self::REVIEW_SCORE);

      $best = $matches[0] ?? null;
      if ($best && $best['score'] >= self::ATTACH_SCORE) {
        $result = ['status' => 'attached', 'crashId' => $best['id'], 'matches' => $matches];
      } else if ($best && ($input['uncertain'] ?? '') !== 'create') {
        $result = ['status' => 'review', 'message' => 'A crash that may be the same exists, but it is not sure', 'matches' => $matches];
      } else {
        $result = ['status' => 'created', 'matches' => $matches];
      }
      $result['usedAi'] = $usedAi;
      $result['crash'] = $crash;

      if (! $dryRun && in_array($result['status'], ['attached', 'created'], true)) $result = $this->save($article, $crash, $result);

      return $this->finish($article, $input, $result, $dryRun);

    } catch (\Throwable $e) {
      if ($this->inTransaction) {
        $this->database->rollback();
        $this->inTransaction = false;
      }

      return $this->finish($article ?? $this->minimalArticle($input), $input, ['status' => 'error', 'message' => $e->getMessage()], $dryRun);
    }
  }

  private function minimalArticle(array $input): array {
    return ['source' => mb_substr((string)($input['source'] ?? ''), 0, 50), 'externalid' => isset($input['externalid']) ? mb_substr((string)$input['externalid'], 0, 100) : null];
  }

  /**
   * @throws Exception
   */
  private function cleanArticle(array $input): array {
    $source = trim((string)($input['source'] ?? ''));
    if (! preg_match('/^[a-z0-9_\-]{1,50}$/i', $source)) throw new \Exception('source is required: letters, numbers, - and _, at most 50');

    $externalId = isset($input['externalid']) && $input['externalid'] !== '' ? trim((string)$input['externalid']) : null;
    if ($externalId !== null && mb_strlen($externalId) > 100) throw new \Exception('externalid is too long (maximum 100)');

    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') throw new \Exception('title is required');

    $url = trim((string)($input['url'] ?? ''));
    $urlImage = trim((string)($input['urlimage'] ?? ''));
    assertHttpUrl($url, 'url');
    assertHttpUrl($urlImage, 'urlimage');
    if (mb_strlen($url) > 1000 || mb_strlen($urlImage) > 1000) throw new \Exception('url or urlimage is too long (maximum 1000)');

    $sitename = trim((string)($input['sitename'] ?? ''));
    $text = ArticleTextCleaner::clean((string)($input['alltext'] ?? ''), $url, $sitename);
    if (trim($text) === '') throw new \Exception('alltext (the text of the article) is required');

    $time = strtotime((string)($input['date'] ?? ''));
    if ($time === false) throw new \Exception('date (when the article was published, for example 2026-10-26T14:30:00+01:00) is required');

    return [
      'source' => $source,
      'externalid' => $externalId,
      'title' => mb_substr($title, 0, 500),
      'alltext' => mb_substr($text, 0, 10000),
      'text' => ArticleTextCleaner::makeSummary($text),
      'sitename' => mb_substr($sitename, 0, 200),
      'url' => $url,
      'urlimage' => $urlImage,
      'published' => date('Y-m-d H:i:s', $time),
    ];
  }

  /**
   * The result for an article that is already known, or null.
   */
  private function findKnown(array $article): ?array {
    if ($article['externalid'] !== null) {
      $row = $this->database->fetch(
        "SELECT crashid, articleid FROM import_articles WHERE source=:source AND externalid=:id AND status IN ('created', 'attached', 'duplicate')",
        [':source' => $article['source'], ':id' => $article['externalid']]);
      if ($row) return ['status' => 'duplicate', 'message' => 'This article was imported before', 'crashId' => $row['crashid'], 'articleId' => $row['articleid']];
    }

    if ($article['url'] !== '') {
      $row = $this->database->fetch('SELECT id, crashid FROM articles WHERE url IN (:url1, :url2) LIMIT 1',
        [':url1' => $article['url'], ':url2' => self::normalizeUrl($article['url'])]);
      if ($row) return ['status' => 'duplicate', 'message' => 'An article with this url exists', 'crashId' => $row['crashid'], 'articleId' => $row['id']];
    }

    // The same title, published on the same day or a day earlier or later (the same news in other newspapers has other titles)
    $row = $this->database->fetch(
      'SELECT id, crashid FROM articles WHERE title=:title AND publishedtime BETWEEN DATE_SUB(:published1, INTERVAL 1 DAY) AND DATE_ADD(:published2, INTERVAL 1 DAY) LIMIT 1',
      [':title' => $article['title'], ':published1' => $article['published'], ':published2' => $article['published']]);
    if ($row) return ['status' => 'duplicate', 'message' => 'An article with this title exists', 'crashId' => $row['crashid'], 'articleId' => $row['id']];

    return null;
  }

  /**
   * The url without tracking parameters, fragment and a slash at the end.
   */
  public static function normalizeUrl(string $url): string {
    $parts = parse_url($url);
    if (! $parts || empty($parts['host'])) return $url;

    $query = '';
    if (! empty($parts['query'])) {
      parse_str($parts['query'], $parameters);
      $parameters = array_filter($parameters, fn($key) => ! preg_match('/^(utm_|fbclid$|gclid$|ref$)/i', $key), ARRAY_FILTER_USE_KEY);
      if ($parameters) $query = '?' . http_build_query($parameters);
    }

    return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($parts['path'] ?? '', '/') . $query;
  }

  /**
   * The AI reads the article, as in the add article form.
   * @throws Exception
   */
  private function extractCrashWithAi(array $article): array {
    $used = (int)$this->database->fetchSingleValue(
      'SELECT COUNT(*) FROM import_articles WHERE tokenid <=> :tokenid AND usedai=1 AND createtime > DATE_SUB(current_timestamp, INTERVAL 1 DAY)',
      [':tokenid' => $this->tokenId]);
    if ($used >= $this->maxAiPerDay) throw new \Exception('The limit of articles that the AI reads per day is reached. Try again later.');

    require_once __DIR__ . '/OpenRouterAIClient.php';

    $openrouter = new OpenRouterAIClient();
    $response = json_decode($openrouter->chatAboutArticle('article_analyst', (object)[
      'title' => $article['title'],
      'text' => mb_substr($article['alltext'], 0, 9000),
      'date' => substr($article['published'], 0, 10),
    ]));
    if (! $response || empty($response->location)) throw new \Exception('The AI gave no usable answer');

    $geocoded = geocodeAiLocation($response->location);
    if ($geocoded) {
      $latitude = $geocoded['latitude'];
      $longitude = $geocoded['longitude'];
      $accuracy = $geocoded['accuracyMeters'] ?? null;
    } else {
      // The place of the AI itself is not exact
      $latitude = $response->location->coordinates->latitude ?? null;
      $longitude = $response->location->coordinates->longitude ?? null;
      $accuracy = null;
    }

    $persons = [];
    foreach ($response->transportation_modes ?? [] as $mode) {
      foreach ($mode->humans ?? [] as $human) {
        $persons[] = [
          'transportationmode' => $mode->transportation_mode,
          'health' => $human->health ?? 'unknown',
          'child' => $human->child ?? false,
          'underinfluence' => $human->intoxicated ?? false,
          'hitrun' => $human->fled_scene ?? false,
        ];
      }
    }

    return [
      'date' => $response->crash_date ?? null,
      'countryid' => $response->location->country_code ?? null,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'locationaccuracy' => $accuracy,
      'locationdescription' => $response->location->description ?? null,
      'unilateral' => $response->single_party_incident ?? false,
      'pet' => $response->animals_mentioned ?? false,
      'trafficjam' => $response->traffic_congestion ?? false,
      'persons' => $persons,
    ];
  }

  /**
   * @throws Exception
   */
  private function cleanCrash(array $crash, array $article): array {
    $date = (string)($crash['date'] ?? '');
    if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) throw new \Exception('No date of the crash found: it is needed for the crash');
    $date = substr($date, 0, 10);

    $countryId = isset($crash['countryid']) ? strtoupper(trim((string)$crash['countryid'])) : null;
    if ($countryId !== null && ! $this->database->getCountryFromId($countryId)) $countryId = null;

    $latitude = isset($crash['latitude']) && is_numeric($crash['latitude']) ? round((float)$crash['latitude'], 6) : null;
    $longitude = isset($crash['longitude']) && is_numeric($crash['longitude']) ? round((float)$crash['longitude'], 6) : null;
    if ($latitude === null || $longitude === null || abs($latitude) > 90 || abs($longitude) > 180) $latitude = $longitude = null;

    $accuracy = ($latitude !== null && isset($crash['locationaccuracy']) && is_numeric($crash['locationaccuracy']))
      ? max(0, min(1000000, (int)$crash['locationaccuracy'])) : null;

    $persons = [];
    foreach ($crash['persons'] ?? [] as $person) {
      $mode = $person['transportationmode'] ?? 0;
      $health = $person['health'] ?? 0;
      $persons[] = [
        'groupid' => isset($person['groupid']) ? (int)$person['groupid'] : null,
        'transportationmode' => is_numeric($mode) ? max(0, min(16, (int)$mode)) : (self::TRANSPORTATION_MODES[$mode] ?? 0),
        'health' => is_numeric($health) ? max(0, min(3, (int)$health)) : (self::HEALTH[$health] ?? 0),
        'child' => (int)(bool)($person['child'] ?? false),
        'underinfluence' => (int)(bool)($person['underinfluence'] ?? false),
        'hitrun' => (int)(bool)($person['hitrun'] ?? false),
      ];
    }

    return [
      'date' => $date,
      'countryid' => $countryId,
      'latitude' => $latitude,
      'longitude' => $longitude,
      'locationaccuracy' => $accuracy,
      'locationdescription' => isset($crash['locationdescription']) ? mb_substr((string)$crash['locationdescription'], 0, 2000) : null,
      'unilateral' => (int)(bool)($crash['unilateral'] ?? false),
      'pet' => (int)(bool)($crash['pet'] ?? false),
      'trafficjam' => (int)(bool)($crash['trafficjam'] ?? false),
      'persons' => $persons,
    ];
  }

  /**
   * Saves the article, and the crash if it is new.
   */
  private function save(array $article, array $crash, array $result): array {
    $this->database->beginTransaction();
    $this->inTransaction = true;

    // Unlike crashes entered by hand, an import does not move a crash to the top of the stream: the stream times are the
    // publication time of the article, and an article added to a crash leaves the crash where it is
    $origin = ['origin' => 'api', 'originname' => $article['source']];
    $awaitingModeration = $this->autoApprove ? 0 : 1;

    if ($result['status'] === 'created') {
      $sql = <<<SQL
INSERT INTO crashes (userid, awaitingmoderation, title, date, countryid, location, latitude, longitude, locationdescription, locationaccuracy, unilateral, pet, trafficjam, origin, originname, streamdatetime)
VALUES (:userid, :awaitingmoderation, :title, :date, :countryid, POINT(:longitude2, :latitude2), :latitude, :longitude, :locationdescription, :locationaccuracy, :unilateral, :pet, :trafficjam, :origin, :originname, :streamdatetime);
SQL;
      $this->database->execute($sql, [
        ':userid' => $this->userId, ':awaitingmoderation' => $awaitingModeration, ':title' => $article['title'],
        ':streamdatetime' => $article['published'],
        ':date' => $crash['date'], ':countryid' => $crash['countryid'],
        ':latitude' => $crash['latitude'], ':longitude' => $crash['longitude'],
        ':latitude2' => $crash['latitude'], ':longitude2' => $crash['longitude'],
        ':locationdescription' => $crash['locationdescription'], ':locationaccuracy' => $crash['locationaccuracy'],
        ':unilateral' => $crash['unilateral'], ':pet' => $crash['pet'], ':trafficjam' => $crash['trafficjam'],
        ':origin' => $origin['origin'], ':originname' => $origin['originname'],
      ]);
      $result['crashId'] = (int)$this->database->lastInsertID();

      $statement = $this->database->prepare(
        'INSERT INTO crashpersons (crashid, groupid, transportationmode, health, child, underinfluence, hitrun) VALUES (:crashid, :groupid, :transportationmode, :health, :child, :underinfluence, :hitrun)');
      foreach ($crash['persons'] as $person) {
        $statement->execute([
          ':crashid' => $result['crashId'], ':groupid' => $person['groupid'], ':transportationmode' => $person['transportationmode'],
          ':health' => $person['health'], ':child' => $person['child'], ':underinfluence' => $person['underinfluence'], ':hitrun' => $person['hitrun'],
        ]);
      }

      // The article of a new crash is published with the crash
      $articleAwaitingModeration = 0;
    } else {
      $articleAwaitingModeration = $awaitingModeration;
    }

    $sql = <<<SQL
INSERT INTO articles (userid, awaitingmoderation, crashid, url, title, text, alltext, publishedtime, sitename, urlimage, origin, originname, streamdatetime)
VALUES (:userid, :awaitingmoderation, :crashid, :url, :title, :text, :alltext, :publishedtime, :sitename, :urlimage, :origin, :originname, :publishedtime2);
SQL;
    $this->database->execute($sql, [
      ':userid' => $this->userId, ':awaitingmoderation' => $articleAwaitingModeration, ':crashid' => $result['crashId'],
      ':url' => $article['url'], ':title' => $article['title'], ':text' => $article['text'], ':alltext' => $article['alltext'],
      ':publishedtime' => $article['published'], ':sitename' => $article['sitename'], ':urlimage' => $article['urlimage'],
      ':origin' => $origin['origin'], ':originname' => $origin['originname'], ':publishedtime2' => $article['published'],
    ]);
    $result['articleId'] = (int)$this->database->lastInsertID();

    $this->database->commit();
    $this->inTransaction = false;

    return $result;
  }

  /**
   * Remembers what happened in import_articles (not for a dry run) and returns the answer for the caller.
   */
  private function finish(array $article, array $input, array $result, bool $dryRun): array {
    $answer = [
      'externalid' => $article['externalid'] ?? null,
      'status' => $result['status'],
      'message' => $result['message'] ?? null,
      'crashId' => isset($result['crashId']) ? (int)$result['crashId'] : null,
      'articleId' => isset($result['articleId']) ? (int)$result['articleId'] : null,
    ];
    if (! empty($result['matches'])) $answer['matches'] = $result['matches'];
    if ($dryRun) $answer['dryRun'] = true;

    if (! $dryRun && $article['source'] !== '') {
      // A duplicate keeps the first row of the article: only a new row is made when there is none
      $sql = <<<SQL
INSERT INTO import_articles (tokenid, source, externalid, status, message, crashid, articleid, usedai, payload, processedtime)
VALUES (:tokenid, :source, :externalid, :status, :message, :crashid, :articleid, :usedai, :payload, current_timestamp)
ON DUPLICATE KEY UPDATE tokenid=VALUES(tokenid), status=IF(status IN ('created', 'attached'), status, VALUES(status)),
  message=VALUES(message), crashid=VALUES(crashid), articleid=VALUES(articleid), usedai=usedai OR VALUES(usedai),
  payload=VALUES(payload), processedtime=current_timestamp
SQL;
      $this->database->execute($sql, [
        ':tokenid' => $this->tokenId, ':source' => $article['source'], ':externalid' => $article['externalid'] ?? null,
        ':status' => $answer['status'], ':message' => $answer['message'] === null ? null : mb_substr($answer['message'], 0, 500),
        ':crashid' => $answer['crashId'], ':articleid' => $answer['articleId'], ':usedai' => (int)! empty($result['usedAi']),
        ':payload' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
      ]);
    }

    return $answer;
  }
}
