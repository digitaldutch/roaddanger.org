<?php
/**
 * Adds the place (latitude, longitude) to crashes that have none. The AI reads the article text, and the geocoder
 * (geocodeAiLocation in general/utils.php: HERE, with PDOK for small places in the Netherlands) finds the coordinates.
 *
 * Usage (command line only):
 *   php -d xdebug.mode=off install/backfill_locations.php --create-prompt   Adds the prompt (function article_location). Or make it in the AI prompt builder.
 *   php -d xdebug.mode=off install/backfill_locations.php --validate --limit=40
 *                                                                            Runs the same steps on crashes that have a place. Shows how far the
 *                                                                            result is from the place that is stored. Changes nothing.
 *   php -d xdebug.mode=off install/backfill_locations.php --limit=20         Dry run on crashes without a place. Shows what would be added.
 *   php -d xdebug.mode=off install/backfill_locations.php --apply --limit=500
 *                                                                            Adds the places, after you type the database name.
 *   php -d xdebug.mode=off install/backfill_locations.php --revert           Removes the places that this script added (only if not changed since).
 *   --limit=N (default 20) or --limit=all. --part=2/4 runs a quarter of the crashes, to run several at the same time.
 *   --max-accuracy=3000: a place is only added if the geocoder is exact to this many metres. A larger place (a town) and
 *   places from the coordinates of the AI are only shown in the report. --redo also runs the crashes that were seen before.
 *   --report=file.html writes the report (default backfill_report.html in the current folder).
 *   Add --config=path to use another config file than config_secret.php, for example the config of the live website.
 *   Add --database=name to use another database than the one in the config file.
 *   Add --port=3307 to connect to another port than the standard one, for example an SSH tunnel next to a local MySQL.
 * The dry run and --validate ask the database for a read-only session, so they can not change anything.
 *
 * - Only crashes without a place (latitude is empty) are changed, and only latitude, longitude, location, the accuracy of
 *   the place (locationaccuracy, if the column exists), and when empty the location description and the country. A place that somebody added meanwhile is never overwritten.
 * - Every crash that was seen gets a row in the table crashes_location_backfill (created by --apply), with the place that
 *   was added. --revert uses it. A crash in the table is skipped the next time, unless you use --redo.
 * - Every crash costs one AI request (a few hundredths of a cent) and a few geocoder requests.
 * - This is not part of the website. The install folder is not uploaded to servers.
 */

const PROMPT_FUNCTION = 'article_location';
const MAX_TEXT_LENGTH = 7000; // Characters of the article that the AI gets
const MIN_TEXT_LENGTH = 300;  // A crash needs an article with at least this many characters

if (PHP_SAPI !== 'cli') exit("This script can only be run from the command line.\n");

$options = getopt('', ['apply', 'revert', 'validate', 'create-prompt', 'redo', 'limit:', 'part:', 'report:', 'max-accuracy:', 'database:', 'config:', 'port:']);
$apply = isset($options['apply']);
$revert = isset($options['revert']);
$validate = isset($options['validate']);
$createPrompt = isset($options['create-prompt']);
$redo = isset($options['redo']);
$writes = $apply || $revert || $createPrompt;
if ($apply + $revert + $validate + $createPrompt > 1) exit("Use only one of --apply, --revert, --validate and --create-prompt.\n");

$limit = ($options['limit'] ?? '20') === 'all'? PHP_INT_MAX : max(1, (int)($options['limit'] ?? 20));
$maxAccuracy = (int)($options['max-accuracy'] ?? 3000);
$partNumber = 1;
$partCount = 1;
if (isset($options['part'])) {
  if (! preg_match('/^(\d+)\/(\d+)$/', $options['part'], $match) || $match[1] < 1 || $match[1] > $match[2]) exit("Use --part=2/4: part 2 of 4.\n");
  [$partNumber, $partCount] = [(int)$match[1], (int)$match[2]];
}
$reportFile = $options['report'] ?? (getcwd() . DIRECTORY_SEPARATOR . 'backfill_report.html');

$configFile = $options['config'] ?? (__DIR__ . '/../config_secret.php');
if (! is_file($configFile)) exit("Config file not found: $configFile\n");
require_once $configFile;
// The name and domain of the website, for the AI client. config.php loads config_secret.php too. With another config
// file (--config) that gives "already defined" warnings, which are hidden: the constants of --config stay.
@require_once __DIR__ . '/../config.php';

$databaseName = $options['database'] ?? DB_NAME;
$port = isset($options['port'])? (int)$options['port'] : null;
$dsn = 'mysql:host=' . DB_HOST . ($port? ';port=' . $port : '') . ';dbname=' . $databaseName . ';charset=utf8mb4';
try {
  $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  exit("Could not connect to " . DB_HOST . ($port? ":$port" : '') . ": " . $e->getMessage() . "\nIs the SSH tunnel open?\n");
}

// A dry run must never write. The database refuses all writes in a read-only session.
if (! $writes) $pdo->exec('SET SESSION TRANSACTION READ ONLY');

echo "Config: $configFile\n";
echo "Database: $databaseName on " . DB_HOST . ($port? ":$port" : '') . "\n";
echo 'Mode: ' . match(true) {
  $apply => "APPLY (adds places to crashes)\n",
  $revert => "REVERT (removes the places that this script added)\n",
  $createPrompt => "CREATE PROMPT\n",
  $validate => "validate (changes nothing)\n",
  default => "dry run (changes nothing)\n",
};

if ($writes) {
  echo "Type the database name to continue: ";
  $answer = trim((string)fgets(STDIN));
  if ($answer !== $databaseName) exit("Not the same name. Nothing is changed.\n");
}

$logTableExists = $pdo->query("SHOW TABLES LIKE 'crashes_location_backfill'")->fetchColumn() !== false;
// The accuracy of the place is saved when the column exists. The script also works before the column is added.
$hasAccuracyColumn = $pdo->query("SHOW COLUMNS FROM crashes LIKE 'locationaccuracy'")->fetch() !== false;
if (! $hasAccuracyColumn) echo "Note: the column crashes.locationaccuracy does not exist yet. The accuracy of the places is not saved. It is in the table crashes_location_backfill.
";

// The functions of the website: geocodeAiLocation, replaceArticleTags, distanceKilometers. They want the folder general.
chdir(__DIR__ . '/../general');
require_once __DIR__ . '/../general/utils.php';
require_once __DIR__ . '/../general/OpenRouterAIClient.php';

if ($createPrompt) {
  createPrompt($pdo);
  exit(0);
}

if ($revert) {
  revertPlaces($pdo, $logTableExists, $hasAccuracyColumn);
  exit(0);
}

$prompt = $pdo->query('SELECT model_id, system_prompt, user_prompt, response_format FROM ai_prompts WHERE `function` = ' . $pdo->quote(PROMPT_FUNCTION))->fetch();
if (! $prompt) exit("There is no prompt with the function " . PROMPT_FUNCTION . ". Run this script with --create-prompt, or make it in the AI prompt builder.\n");
echo "Prompt: {$prompt['model_id']}\n";

if ($apply) {
  $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS crashes_location_backfill (
  crashid       int           NOT NULL PRIMARY KEY,
  article_id    int           NULL,
  status        varchar(20)   NOT NULL,
  source        varchar(10)   NULL,
  result_type   varchar(30)   NULL,
  accuracy_m    int           NULL,
  latitude      decimal(9, 6) NULL,
  longitude     decimal(9, 6) NULL,
  description   varchar(500)  NULL,
  country_set   char(2)       NULL,
  geocoder_text varchar(300)  NULL,
  processed_at  timestamp     NOT NULL DEFAULT current_timestamp()
) DEFAULT CHARSET=utf8mb4
SQL);
  $logTableExists = true;
}

$crashes = findCrashes($pdo, $validate, $limit, $partNumber, $partCount, $redo && ! $validate, $logTableExists);
echo count($crashes) . ' crashes to do' . ($partCount > 1? " (part $partNumber of $partCount)" : '') . "\n\n";

$client = new OpenRouterAIClient();
$rows = [];
$cost = 0.0;
$startTime = microtime(true);
foreach ($crashes as $i => $crash) {
  $row = processCrash($pdo, $client, $prompt, $crash, $maxAccuracy);
  $cost += $row['cost'] ?? 0;

  if ($validate && isset($row['pin']) && $crash['latitude'] !== null) {
    $row['storedDistance'] = distanceKilometers($row['pin']['latitude'], $row['pin']['longitude'], (float)$crash['latitude'], (float)$crash['longitude']);
  }

  if ($apply) saveRow($pdo, $row, $hasAccuracyColumn);

  $rows[] = $row;
  printf("%4d/%d crash %-6d %-10s %-6s %-12s %s\n", $i + 1, count($crashes), $crash['id'], $row['status'], $row['source'] ?? '-',
    $row['resultType'] ?? '', isset($row['storedDistance'])? sprintf('%.2f km from the stored place', $row['storedDistance']) : substr($row['aiText'] ?? $row['reason'] ?? '', 0, 60));
}

summary($rows, $validate, $cost, microtime(true) - $startTime, $apply);
writeReport($reportFile, $rows, $validate);
echo "\nReport: $reportFile\n";

// ---------------------------------------------------------------------------------------------------------------

function findCrashes(PDO $pdo, bool $validate, int $limit, int $part, int $parts, bool $redo, bool $logTableExists): array {
  $hasText = 'EXISTS (SELECT 1 FROM articles a WHERE a.crashid = c.id AND LENGTH(a.alltext) >= ' . MIN_TEXT_LENGTH . ')';
  $where = $validate? "c.latitude IS NOT NULL AND $hasText" : "c.latitude IS NULL AND $hasText";
  if (! $validate && $logTableExists && ! $redo) $where .= ' AND NOT EXISTS (SELECT 1 FROM crashes_location_backfill b WHERE b.crashid = c.id)';
  if ($parts > 1) $where .= ' AND c.id % ' . $parts . ' = ' . ($part - 1);

  $order = $validate? 'RAND()' : 'c.id DESC';
  $sqlLimit = $limit === PHP_INT_MAX? '' : " LIMIT $limit";

  return $pdo->query("SELECT c.id, c.title, c.countryid, c.latitude, c.longitude FROM crashes c WHERE $where ORDER BY $order$sqlLimit")->fetchAll();
}

/**
 * Asks the AI for the place in the longest article of the crash and finds the coordinates.
 * The status is: add (can be added), review (found, but not exact enough), no_location (the article names no place),
 * no_pin (no coordinates found), ai_error or no_text.
 */
function processCrash(PDO $pdo, OpenRouterAIClient $client, array $prompt, array $crash, int $maxAccuracy): array {
  $row = ['crashId' => (int)$crash['id'], 'title' => $crash['title'], 'countryId' => $crash['countryid']];

  $statement = $pdo->prepare('SELECT id, title, alltext, publishedtime FROM articles WHERE crashid = :id AND LENGTH(alltext) >= ' . MIN_TEXT_LENGTH . ' ORDER BY LENGTH(alltext) DESC LIMIT 1');
  $statement->execute([':id' => $crash['id']]);
  $article = $statement->fetch();
  if (! $article) return $row + ['status' => 'no_text', 'reason' => 'no article text'];
  $row['articleId'] = (int)$article['id'];

  try {
    $userPrompt = replaceArticleTags($prompt['user_prompt'], (object)[
      'date' => substr($article['publishedtime'], 0, 10),
      'title' => $article['title'],
      'text' => mb_substr($article['alltext'], 0, MAX_TEXT_LENGTH),
    ]);
    $ai = $client->chatWithMeta($userPrompt, $prompt['system_prompt'] ?? '', $prompt['model_id'], $prompt['response_format'] ?? '');
    $row['cost'] = $ai['cost'] ?? 0;
    $answer = json_decode($ai['response']);
  } catch (Throwable $e) {
    return $row + ['status' => 'ai_error', 'reason' => $e->getMessage()];
  }

  if (! $answer || ! isset($answer->location)) return $row + ['status' => 'ai_error', 'reason' => 'invalid answer of the AI'];
  if (empty($answer->has_location)) return $row + ['status' => 'no_location', 'reason' => 'the article names no place'];

  $location = $answer->location;
  $row['aiText'] = (string)($location->geocoder_prompt ?? '');
  $row['description'] = (string)($location->description ?? '');
  $row['countryCode'] = (string)($location->country_code ?? '');

  $pin = geocodeAiLocation($location);
  if ($pin) {
    $row['pin'] = $pin;
    $row['source'] = $pin['source'];
    $row['resultType'] = $pin['resultType'];
    $row['accuracy'] = $pin['accuracyMeters'];
    $tooLarge = in_array($pin['resultType'], ['administrativeArea', 'country', 'state', 'county']) || $pin['accuracyMeters'] > $maxAccuracy;

    return $row + ($tooLarge? ['status' => 'review', 'reason' => 'not exact enough'] : ['status' => 'add', 'reason' => '']);
  }

  // The geocoder found nothing that fits. The coordinates of the AI are only shown.
  $latitude = $location->coordinates->latitude ?? null;
  $longitude = $location->coordinates->longitude ?? null;
  if (is_numeric($latitude) && is_numeric($longitude) && ($latitude != 0 || $longitude != 0)) {
    $row['pin'] = ['latitude' => (float)$latitude, 'longitude' => (float)$longitude];
    $row['source'] = 'ai';
    $row['resultType'] = 'AI coordinates';

    return $row + ['status' => 'review', 'reason' => 'only coordinates of the AI'];
  }

  return $row + ['status' => 'no_pin', 'reason' => 'no coordinates found'];
}

/**
 * Adds the place to the crash, and logs the crash. A place that was added since is not overwritten.
 */
function saveRow(PDO $pdo, array $row, bool $hasAccuracyColumn): void {
  $applied = false;
  $countrySet = null;

  if ($row['status'] === 'add') {
    $pin = $row['pin'];
    $pdo->beginTransaction();

    $update = $pdo->prepare("UPDATE crashes SET latitude = :latitude, longitude = :longitude, location = POINT(:longitude2, :latitude2),
      locationdescription = IF(locationdescription IS NULL OR locationdescription = '', :description, locationdescription)" .
      ($hasAccuracyColumn? ', locationaccuracy = :accuracy' : '') . "
      WHERE id = :id AND latitude IS NULL");
    $params = [':latitude' => $pin['latitude'], ':longitude' => $pin['longitude'], ':longitude2' => $pin['longitude'], ':latitude2' => $pin['latitude'],
      ':description' => substr($row['description'], 0, 500), ':id' => $row['crashId']];
    if ($hasAccuracyColumn) $params[':accuracy'] = $row['accuracy'] ?? null;
    $update->execute($params);
    $applied = $update->rowCount() > 0;

    // The country, when the crash has none
    if ($applied && ! $row['countryId'] && preg_match('/^[A-Z]{2}$/', $row['countryCode'])) {
      $country = $pdo->prepare('UPDATE crashes SET countryid = :country WHERE id = :id AND countryid IS NULL AND EXISTS (SELECT 1 FROM countries WHERE id = :country2)');
      $country->execute([':country' => $row['countryCode'], ':country2' => $row['countryCode'], ':id' => $row['crashId']]);
      if ($country->rowCount() > 0) $countrySet = $row['countryCode'];
    }
    $pdo->commit();
  }

  $log = $pdo->prepare("INSERT INTO crashes_location_backfill (crashid, article_id, status, source, result_type, accuracy_m, latitude, longitude, description, country_set, geocoder_text)
    VALUES (:id, :article, :status, :source, :type, :accuracy, :latitude, :longitude, :description, :country, :text)
    ON DUPLICATE KEY UPDATE article_id = VALUES(article_id), status = VALUES(status), source = VALUES(source), result_type = VALUES(result_type),
      accuracy_m = VALUES(accuracy_m), latitude = VALUES(latitude), longitude = VALUES(longitude), description = VALUES(description),
      country_set = VALUES(country_set), geocoder_text = VALUES(geocoder_text), processed_at = current_timestamp()");
  $log->execute([
    ':id' => $row['crashId'], ':article' => $row['articleId'] ?? null, ':status' => $applied? 'applied' : $row['status'],
    ':source' => $row['source'] ?? null, ':type' => $row['resultType'] ?? null, ':accuracy' => $row['accuracy'] ?? null,
    ':latitude' => $row['pin']['latitude'] ?? null, ':longitude' => $row['pin']['longitude'] ?? null,
    ':description' => isset($row['description'])? substr($row['description'], 0, 500) : null, ':country' => $countrySet,
    ':text' => isset($row['aiText'])? substr($row['aiText'], 0, 300) : null,
  ]);
}

function revertPlaces(PDO $pdo, bool $logTableExists, bool $hasAccuracyColumn): void {
  if (! $logTableExists) exit("There is no table crashes_location_backfill. Nothing to revert.\n");

  $reverted = 0;
  $skipped = 0;
  $update = $pdo->prepare("UPDATE crashes SET latitude = NULL, longitude = NULL, location = NULL," . ($hasAccuracyColumn? ' locationaccuracy = NULL,' : '') . "
    locationdescription = IF(locationdescription = :description, NULL, locationdescription),
    countryid = IF(countryid = :country, NULL, countryid)
    WHERE id = :id AND latitude = :latitude AND longitude = :longitude");
  foreach ($pdo->query("SELECT * FROM crashes_location_backfill WHERE status = 'applied' ORDER BY crashid")->fetchAll() as $log) {
    $update->execute([':description' => $log['description'], ':country' => $log['country_set'] ?? '', ':id' => $log['crashid'], ':latitude' => $log['latitude'], ':longitude' => $log['longitude']]);
    // Only restore if the place is still the one that this script added. A place that was changed since is left alone.
    if ($update->rowCount() > 0) {
      $pdo->prepare("UPDATE crashes_location_backfill SET status = 'reverted' WHERE crashid = ?")->execute([$log['crashid']]);
      $reverted++;
    } else {
      $skipped++;
    }
  }

  echo "Removed the place of $reverted crashes. Skipped $skipped (changed since).\n";
}

function createPrompt(PDO $pdo): void {
  $exists = $pdo->query('SELECT id FROM ai_prompts WHERE `function` = ' . $pdo->quote(PROMPT_FUNCTION))->fetchColumn();
  if ($exists) exit('The prompt exists already: #' . $exists . ".\n");

  $userId = $pdo->query('SELECT id FROM users WHERE permission = 1 ORDER BY id LIMIT 1')->fetchColumn();
  if (! $userId) exit("There is no administrator to own the prompt.\n");

  $format = [
    'type' => 'json_schema',
    'json_schema' => [
      'name' => 'crash_location',
      'strict' => true,
      'schema' => [
        'type' => 'object',
        'properties' => [
          'has_location' => ['type' => 'boolean', 'description' => 'True if the article names the place where the crash happened. False if it does not name a place: then leave the text fields empty and the coordinates 0.'],
          'location' => [
            'type' => 'object',
            'properties' => [
              'description' => ['type' => 'string', 'description' => 'The place of the crash as a short text, with the street or road, the town and the country.'],
              'geocoder_prompt' => ['type' => 'string', 'description' => "Address string for a geocoder. Maximum 100 characters, only the address, no explanation. Always use the most specific location the article gives, in this order: a house address ('Street 12, Town, Country'); an intersection ('Street A & Street B, Town, Country'); a road with the cross street, junction, exit or village where it happened ('B6 & Oehlischer Weg, Sohland am Rotstein, Germany'); only for a long road without any such detail, the road and the nearest town ('A4, Nossen, Germany'). Write road numbers the way locals do (A4, B7, N979, RD943), never spelled out. Write the country in English. Do not use words such as near, between, direction or outside. Do not use the name of a business, shop or landmark: use its street, or else its town. Use the spelling of the place names as on local road signs and maps. If the street is not known, use only the town and country."],
              'country_code' => ['type' => 'string', 'pattern' => '^[A-Z]{2}$', 'description' => 'Country code using ISO 3166-1 alpha-2 format (two uppercase letters)'],
              'coordinates' => [
                'type' => 'object',
                'properties' => ['latitude' => ['type' => 'number'], 'longitude' => ['type' => 'number']],
                'required' => ['latitude', 'longitude'],
                'additionalProperties' => false,
              ],
            ],
            'required' => ['description', 'geocoder_prompt', 'country_code', 'coordinates'],
            'additionalProperties' => false,
          ],
        ],
        'required' => ['has_location', 'location'],
        'additionalProperties' => false,
      ],
    ],
  ];

  $insert = $pdo->prepare('INSERT INTO ai_prompts (user_id, model_id, user_prompt, system_prompt, response_format, `function`) VALUES (:user, :model, :user_prompt, :system_prompt, :format, :function)');
  $insert->execute([
    ':user' => $userId,
    ':model' => 'google/gemini-3.5-flash-lite',
    ':user_prompt' => "Article date: [article_date]\nArticle title: [article_title]\nArticle text: [article_text]",
    ':system_prompt' => 'You are a research assistant who finds the location of traffic crashes in media articles. Find where the crash happened, according to the article. Only use places that the article names. If the article does not name the place where the crash happened, set has_location to false. Never guess a place. For the location fields write short, plain address strings that a geocoder can find, never explanations.',
    ':format' => json_encode($format, JSON_UNESCAPED_UNICODE),
    ':function' => PROMPT_FUNCTION,
  ]);

  echo 'Prompt #' . $pdo->lastInsertId() . ' added, function ' . PROMPT_FUNCTION . ". You can change it in the AI prompt builder.\n";
}

function summary(array $rows, bool $validate, float $cost, float $seconds, bool $apply): void {
  $count = fn(callable $filter) => count(array_filter($rows, $filter));
  echo "\n--- Summary of " . count($rows) . " crashes\n";
  foreach (['add' => $apply? 'added (or can not be added now)' : 'can be added', 'review' => 'found, not exact enough or from the AI (report only)', 'no_location' => 'the article names no place', 'no_pin' => 'no coordinates found', 'ai_error' => 'AI error', 'no_text' => 'no text'] as $status => $text) {
    $n = $count(fn($r) => $r['status'] === $status);
    if ($n) printf("  %-12s %5d  %s\n", $status, $n, $text);
  }
  foreach (['here', 'pdok', 'ai'] as $source) {
    $n = $count(fn($r) => ($r['source'] ?? '') === $source);
    if ($n) printf("  source %-6s %5d\n", $source, $n);
  }

  if ($validate) {
    $distances = array_values(array_filter(array_map(fn($r) => $r['storedDistance'] ?? null, $rows), fn($d) => $d !== null));
    sort($distances);
    $n = count($distances);
    if ($n) {
      echo "\n  Distance to the stored place, of $n crashes with a result:\n";
      foreach ([0.3, 1, 5, 25] as $km) printf("    within %5s km  %3.0f%%\n", $km, 100 * count(array_filter($distances, fn($d) => $d <= $km)) / $n);
      printf("    median %.2f km, more than 50 km off: %d\n", $distances[intdiv($n, 2)], count(array_filter($distances, fn($d) => $d > 50)));
    }
    echo "  Note: the stored places were mostly made with HERE, so a difference does not always mean that the new place is wrong.\n";
  }

  printf("\n  Cost %s, %.1f seconds (%.1f per crash)\n", '$' . number_format($cost, 4), $seconds, $rows? $seconds / count($rows) : 0);
}

function writeReport(string $file, array $rows, bool $validate): void {
  $e = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
  $html = '<!doctype html><meta charset="utf-8"><title>Location backfill</title><style>body{font:13px system-ui,sans-serif;margin:16px}table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:3px 7px;text-align:left;vertical-align:top}.add{background:#e4f4e0}.review{background:#fdf1d6}.no_location,.no_pin,.ai_error,.no_text{background:#f5dcd8}</style>';
  $html .= '<h1>Location backfill</h1><table><tr><th>Crash</th><th>Title</th><th>Status</th><th>AI text</th><th>Source</th><th>Type</th><th>Accuracy</th><th>Place</th>' . ($validate? '<th>From stored place</th>' : '') . '</tr>';
  foreach ($rows as $r) {
    $pin = $r['pin'] ?? null;
    $map = $pin? sprintf('<a href="https://www.openstreetmap.org/?mlat=%1$s&amp;mlon=%2$s#map=16/%1$s/%2$s" target="_blank">%3$.5f, %4$.5f</a>', $pin['latitude'], $pin['longitude'], $pin['latitude'], $pin['longitude']) : '';
    $html .= '<tr class="' . $e($r['status']) . '"><td><a href="/' . (int)$r['crashId'] . '" target="_blank">' . (int)$r['crashId'] . '</a></td><td>' . $e($r['title']) . '</td><td>' . $e($r['status'] . ($r['reason'] ? ': ' . $r['reason'] : '')) .
      '</td><td>' . $e($r['aiText'] ?? '') . '</td><td>' . $e($r['source'] ?? '') . '</td><td>' . $e($r['resultType'] ?? '') . '</td><td>' . $e(isset($r['accuracy'])? $r['accuracy'] . ' m' : '') . '</td><td>' . $map . '</td>' .
      ($validate? '<td>' . (isset($r['storedDistance'])? sprintf('%.2f km', $r['storedDistance']) : '') . '</td>' : '') . '</tr>';
  }
  file_put_contents($file, $html . '</table>');
}
