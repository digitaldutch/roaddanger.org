<?php
/**
 * Adds made-up test data to a new website, to try it out: 400 crashes with people and articles, and a questionnaire.
 *
 * Usage (command line only):  php install/seed_test_data.php
 *
 * Run install/init_database.php first. It creates the tables and adds the languages, countries and info texts.
 *
 * - The crashes and articles belong to the first administrator. If there is no administrator yet,
 *   admin@example.test is created with a random password, which is shown once.
 * - Refuses to run when the database already contains crashes, when questionnaire 7 exists,
 *   or when init_database.php was not run. It never adds data to a website that is in use.
 * - Everything is inserted in one transaction. Nothing is changed when an error occurs.
 * - All data is made up. The news articles do not exist and the urls point to example.test.
 */

const CRASH_COUNT = 400;
const RANDOM_SEED = 747; // Same seed = same data each run

if (PHP_SAPI !== 'cli') exit("This script can only be run from the command line.\n");

require_once __DIR__ . '/../config_secret.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if ($pdo->query('SELECT COUNT(*) FROM crashes')->fetchColumn() > 0) {
  exit("Refusing to run: the database already contains crashes.\n");
}
if ($pdo->query('SELECT COUNT(*) FROM questionnaires WHERE id = 7')->fetchColumn() > 0) {
  exit("Refusing to run: questionnaire 7 already exists.\n");
}
foreach (['languages', 'countries', 'longtexts'] as $table) {
  if ($pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() == 0) {
    exit("Table '$table' is empty. Run install/init_database.php first.\n");
  }
}

mt_srand(RANDOM_SEED);

function insertRow(PDO $pdo, string $table, array $row): int {
  $columns = implode(',', array_keys($row));
  $marks   = implode(',', array_map(fn($c) => ':' . $c, array_keys($row)));
  $pdo->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute($row);
  return (int)$pdo->lastInsertId();
}

function pick(array $items) { return $items[mt_rand(0, count($items) - 1)]; }

function pickWeighted(array $weights) {
  $roll = mt_rand(1, array_sum($weights));
  foreach ($weights as $value => $weight) {
    $roll -= $weight;
    if ($roll <= 0) return $value;
  }
  return array_key_first($weights);
}

// Fictional crash locations: [city, latitude, longitude]
$places = [
  'NL' => [['Amsterdam', 52.37, 4.90], ['Utrecht', 52.09, 5.12], ['Rotterdam', 51.92, 4.48], ['Groningen', 53.22, 6.57]],
  'BE' => [['Brussels', 50.85, 4.35], ['Antwerp', 51.22, 4.40], ['Ghent', 51.05, 3.72]],
  'DE' => [['Berlin', 52.52, 13.40], ['Hamburg', 53.55, 9.99], ['Munich', 48.14, 11.58]],
  'FR' => [['Paris', 48.86, 2.35], ['Lyon', 45.76, 4.84], ['Lille', 50.63, 3.06]],
  'GB' => [['London', 51.51, -0.13], ['Manchester', 53.48, -2.24], ['Leeds', 53.80, -1.55]],
  'US' => [['New York', 40.71, -74.01], ['Chicago', 41.88, -87.63], ['Portland', 45.52, -122.68]],
];
$countryWeights = ['NL' => 40, 'BE' => 10, 'DE' => 12, 'FR' => 10, 'GB' => 14, 'US' => 14];

// Transportation modes as used in crashpersons.transportationmode
$modeNames = [1 => 'pedestrian', 2 => 'cyclist', 3 => 'scooter rider', 4 => 'motorcyclist', 5 => 'car driver',
  7 => 'ambulance', 8 => 'delivery van', 10 => 'bus', 11 => 'tram', 12 => 'truck', 14 => 'wheelchair user'];
$victimModeWeights = [1 => 30, 2 => 30, 3 => 5, 4 => 8, 5 => 17, 14 => 2, 11 => 1, 10 => 1];
$otherModeWeights  = [5 => 55, 12 => 15, 8 => 10, 10 => 8, 4 => 5, 11 => 4, 7 => 3];
$healthWeights     = [3 => 25, 2 => 55, 1 => 15, 0 => 5]; // dead, injured, uninjured, unknown

$siteNames  = ['Daily Test News', 'The Example Times', 'Local Sample Gazette', 'Fictional Post', 'Test Street Journal'];
$verbs      = ['was hit by', 'collided with', 'was struck by', 'died after a crash with', 'was seriously injured by'];
$locations  = ['at a junction', 'on a main road', 'near a school', 'on a roundabout', 'at a pedestrian crossing', 'on a cycle path'];

try {
  $pdo->beginTransaction();

  // The owner of the test data: the first administrator (permission 1). Create one if there is none.
  $owner = $pdo->query('SELECT id, email FROM users WHERE permission = 1 ORDER BY id LIMIT 1')->fetch();
  $newPassword = null;
  if ($owner === false) {
    $newPassword = bin2hex(random_bytes(6));
    $owner = ['email' => 'admin@example.test'];
    $owner['id'] = insertRow($pdo, 'users', [
      'email' => $owner['email'], 'firstname' => 'Admin', 'lastname' => 'Test', 'language' => 'en', 'countryid' => 'UN',
      'passwordhash' => password_hash($newPassword, PASSWORD_DEFAULT), 'permission' => 1,
    ]);
  }
  $ownerId = (int)$owner['id'];

  // Questionnaire with three questions.
  // The code uses questionnaire 7 (Bechdel type, public, Netherlands) for the media humanization test on the home page.
  $questionnaireId = insertRow($pdo, 'questionnaires', [
    'id' => 7, 'active' => 1, 'type' => 1, 'country_id' => 'NL', 'title' => 'Media humanization test', 'public' => 1,
  ]);
  $questionIds = [];
  foreach (['Is the driver mentioned as the subject of the headline?', 'Is the vehicle mentioned instead of the person?', 'Is the victim blamed?'] as $order => $text) {
    $questionId = insertRow($pdo, 'questions', ['text' => $text, 'active' => 1, 'question_order' => $order + 1]);
    insertRow($pdo, 'questionnaire_questions', ['questionnaire_id' => $questionnaireId, 'question_id' => $questionId, 'question_order' => $order + 1]);
    $questionIds[] = $questionId;
  }

  // Crashes with people and articles, spread over the last three years
  $start = strtotime('2023-10-10');
  $end   = strtotime('2026-10-08');
  $articleCount = 0;

  for ($i = 0; $i < CRASH_COUNT; $i++) {
    $countryId = pickWeighted($countryWeights);
    [$city, $lat, $lng] = pick($places[$countryId]);
    $lat += (mt_rand(-300, 300) / 10000);
    $lng += (mt_rand(-300, 300) / 10000);

    $timestamp = mt_rand($start, $end);
    $date      = date('Y-m-d', $timestamp);
    $dateTime  = date('Y-m-d H:i:s', $timestamp);

    // People: the first is always a victim (dead or injured)
    $people = [];
    $victimHealth = pickWeighted([3 => 30, 2 => 70]);
    $victimMode   = pickWeighted($victimModeWeights);
    $people[] = [
      'mode' => $victimMode, 'health' => $victimHealth,
      'child' => (in_array($victimMode, [1, 2, 3]) && mt_rand(1, 100) <= 12) ? 1 : 0,
    ];
    $otherCount = pickWeighted([1 => 70, 0 => 15, 2 => 15]);
    for ($p = 0; $p < $otherCount; $p++) {
      $people[] = ['mode' => pickWeighted($otherModeWeights), 'health' => pickWeighted($healthWeights), 'child' => 0];
    }

    $hitRun     = (count($people) > 1 && mt_rand(1, 100) <= 6) ? 1 : 0;
    $unilateral = (count($people) === 1) ? 1 : 0;
    $victimName = $modeNames[$victimMode];
    $otherName  = count($people) > 1 ? $modeNames[$people[1]['mode']] : null;
    $location   = pick($locations);

    $title = $otherName === null
      ? ucfirst("$victimName crashed $location in $city")
      : ucfirst("$victimName " . pick($verbs) . " $otherName $location in $city");

    $crashId = insertRow($pdo, 'crashes', [
      'userid' => $ownerId, 'awaitingmoderation' => 0,
      'createtime' => $dateTime, 'updatetime' => $dateTime, 'streamdatetime' => $dateTime,
      'date' => $date, 'streamtopuserid' => $ownerId, 'streamtoptype' => 1,
      'title' => $title, 'text' => "Test crash $location in $city. This crash is made up.",
      'countryid' => $countryId, 'latitude' => round($lat, 6), 'longitude' => round($lng, 6),
      'trafficjam' => mt_rand(1, 100) <= 5 ? 1 : 0, 'unilateral' => $unilateral, 'hitrun' => $hitRun, 'pet' => 0,
    ]);
    // POINT(longitude latitude). The export code reads x as longitude and y as latitude.
    $pdo->prepare('UPDATE crashes SET location = ST_GeomFromText(:point) WHERE id = :id')
      ->execute([':point' => sprintf('POINT(%F %F)', $lng, $lat), ':id' => $crashId]);

    foreach ($people as $person) {
      insertRow($pdo, 'crashpersons', [
        'crashid' => $crashId, 'transportationmode' => $person['mode'], 'health' => $person['health'],
        'child' => $person['child'], 'underinfluence' => mt_rand(1, 100) <= 4 ? 1 : 0, 'hitrun' => $hitRun,
      ]);
    }

    // One to three articles per crash
    for ($a = 0, $n = pickWeighted([1 => 60, 2 => 30, 3 => 10]); $a < $n; $a++) {
      $articleCount++;
      $published = date('Y-m-d H:i:s', $timestamp + mt_rand(1800, 86400));
      $articleId = insertRow($pdo, 'articles', [
        'crashid' => $crashId, 'userid' => $ownerId, 'awaitingmoderation' => 0,
        'createtime' => $published, 'streamdatetime' => $published, 'publishedtime' => $published,
        'title' => $title, 'text' => "Made-up news report about a crash $location in $city.",
        'alltext' => "Made-up news report about a crash $location in $city. This text does not describe a real event.",
        'url' => "https://example.test/news/$articleCount", 'urlimage' => '', 'sitename' => pick($siteNames),
        'ai_questionnaire_status' => null,
      ]);

      // Answer the questionnaire for most Dutch articles, and for about a third of the others.
      // The home page graph only shows months with at least 5 answered Dutch articles.
      if (mt_rand(1, 100) <= (($countryId === 'NL') ? 80 : 33)) {
        foreach ($questionIds as $questionId) {
          insertRow($pdo, 'answers', [
            'questionid' => $questionId, 'articleid' => $articleId,
            'answer' => mt_rand(0, 1), 'answered_by_type' => 1,
          ]);
        }
      }
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  exit('Error, nothing was changed: ' . $e->getMessage() . "\n");
}

echo 'Done. ' . CRASH_COUNT . " crashes and $articleCount articles added to " . DB_NAME . ".\n";
if ($newPassword !== null) {
  echo "Created administrator {$owner['email']} with password $newPassword (shown only once).\n";
} else {
  echo "The test data belongs to administrator {$owner['email']}.\n";
}
