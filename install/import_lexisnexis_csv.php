<?php

/**
 * Imports the articles from the Lexis Nexis csv of the students. Convert the csv first:
 *   python install/lexisnexis_csv_to_jsonl.py analysis_corpus_2024.csv articles.jsonl
 *   php -d xdebug.mode=off install/import_lexisnexis_csv.php --user=2 --dry-run articles.jsonl
 *
 * --user=ID       user that owns the imported crashes and articles (required)
 * --dry-run       only tell what would happen, save nothing
 * --autoapprove   publish without moderation (default: crashes and articles wait for moderation)
 * --uncertain=create   make a new crash if a crash that may be the same is found (default: do nothing, status review)
 * --skip=N, --limit=N  start after N articles, import N articles
 *
 * Articles of the same crash event (field event) are added to the crash of the first article of the event.
 * Run it again after a stop: articles that were imported are skipped.
 */

if (php_sapi_name() !== 'cli') die('Script may only run from command line');

chdir(__DIR__);
require_once '../config.php';
require_once '../database.php';
require_once '../general/ImportService.php';

date_default_timezone_set('Europe/Amsterdam');
mb_internal_encoding('UTF-8');

$options = getopt('', ['user:', 'dry-run', 'autoapprove', 'uncertain:', 'skip:', 'limit:'], $restIndex);
$file = $argv[$restIndex] ?? null;
if (! $file || empty($options['user']) || ! is_readable($file)) die("Usage: php import_lexisnexis_csv.php --user=ID [--dry-run] [--autoapprove] [--uncertain=create] [--skip=N] [--limit=N] articles.jsonl\n");

$database = new Database();
$database->open();
$database->loadCountries();

$dryRun = isset($options['dry-run']);
$service = new ImportService($database, ['id' => null, 'userid' => (int)$options['user'], 'autoapprove' => isset($options['autoapprove']), 'maxaiperday' => 0]);

$skip = (int)($options['skip'] ?? 0);
$limit = (int)($options['limit'] ?? PHP_INT_MAX);
$counts = [];
$crashOfEvent = [];
$line = 0;
$done = 0;

foreach (new SplFileObject($file) as $json) {
  if (trim($json) === '') continue;
  if (++$line <= $skip) continue;
  if ($done >= $limit) break;
  $done++;

  $article = json_decode($json, true);
  $event = $article['event'] ?? null;
  unset($article['event']);

  if ($event !== null && isset($crashOfEvent[$event])) $article['crashid'] = $crashOfEvent[$event];
  if (isset($options['uncertain'])) $article['uncertain'] = $options['uncertain'];

  $result = $service->importArticle($article, $dryRun);
  if ($event !== null && $result['crashId'] && in_array($result['status'], ['created', 'attached', 'duplicate'], true)) $crashOfEvent[$event] = $result['crashId'];

  $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
  if ($result['status'] === 'error') echo "Article {$article['externalid']}: {$result['message']}\n";
  if ($done % 200 === 0) echo "$done articles: " . json_encode($counts) . "\n";
}

echo ($dryRun ? 'Dry run, nothing saved. ' : '') . "$done articles: " . json_encode($counts) . "\n";
