<?php
/**
 * Makes the summary (articles.text) of every article from its full text (articles.alltext), with the same function that
 * makes it when an article is saved: ArticleTextCleaner::makeSummary(). The summary is the start of the full text: the first
 * paragraph, or more if the first is short, up to about 300 characters, cut at the end of a sentence.
 *
 * Articles without a full text keep their summary. It is all there is to show.
 *
 * Usage (command line only):
 *   php -d xdebug.mode=off install/regenerate_summaries.php                  Dry run. Shows what would change. Changes nothing.
 *   php -d xdebug.mode=off install/regenerate_summaries.php --apply          Changes the summaries, after you type the database name.
 *   php -d xdebug.mode=off install/regenerate_summaries.php --revert         Puts the old summaries back (only if not changed since).
 *   Add --config=path to use another config file than config_secret.php, for example the config of another website.
 *   Add --database=name to use another database than the one in the config file.
 *   Add --port=3307 to connect to another port than the standard one, for example an SSH tunnel next to a local MySQL.
 * The dry run asks the database for a read-only session, so it can not change anything.
 *
 * - Run install/clean_article_texts.php --apply first, so the summaries are made from cleaned texts.
 * - The old summary of every changed article is saved in the table articles_summary_backup. The table is created by --apply.
 * - Running it again is safe. Articles that are up to date are not touched, and the first backup is never overwritten.
 * - Only articles.text is changed. Each batch is one transaction. The search index on title and summary updates by itself.
 * - This is not part of the website. The install folder is not uploaded to servers.
 */

const BATCH_SIZE = 200;

if (PHP_SAPI !== 'cli') exit("This script can only be run from the command line.\n");

require_once __DIR__ . '/../general/ArticleTextCleaner.php';

function shortText(string $text, int $length = 120): string {
  return mb_substr(str_replace(["\r", "\n"], ' ', $text), 0, $length);
}

function makeNewSummary(array $article): string {
  return ArticleTextCleaner::makeSummary(ArticleTextCleaner::clean($article['alltext'], $article['url'], $article['sitename']));
}

/**
 * Lower case, one kind of quotes and dashes, single spaces, and no place name ("UTRECHT - ") at the start.
 */
function normalizeForCompare(string $text): string {
  $text = strtr($text, ["\u{201C}" => '"', "\u{201D}" => '"', "\u{2018}" => "'", "\u{2019}" => "'", "\u{2013}" => '-', "\u{2014}" => '-', "\u{00A0}" => ' ', "\u{2026}" => '...']);
  $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $text);
  $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));

  return preg_replace('/^[\p{L} .\'-]{1,40}?\s*[-]\s+/u', '', $text);
}

/**
 * Is the old summary the start of the text (apart from length, capitals, place name and quotes)?
 */
function summaryIsStartOfText(string $oldSummary, string $cleanText): bool {
  $stem = preg_replace('/\s*(\.\.\.|\[\.\.\.\])$/', '', normalizeForCompare($oldSummary));
  if (mb_strlen($stem) < 30) return false;

  return str_contains(mb_substr(normalizeForCompare($cleanText), 0, 600), $stem);
}

$options = getopt('', ['apply', 'revert', 'database:', 'config:', 'port:']);
$apply = isset($options['apply']);
$revert = isset($options['revert']);
if ($apply && $revert) exit("Use --apply or --revert, not both.\n");

$configFile = $options['config'] ?? (__DIR__ . '/../config_secret.php');
if (! is_file($configFile)) exit("Config file not found: $configFile\n");
require_once $configFile;

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
if (! $apply && ! $revert) $pdo->exec('SET SESSION TRANSACTION READ ONLY');

echo "Config: $configFile\n";
echo "Database: $databaseName on " . DB_HOST . ($port? ":$port" : '') . "\n";
echo $apply? "Mode: APPLY (changes the summaries)\n" : ($revert? "Mode: REVERT (restores the old summaries)\n" : "Mode: dry run (changes nothing)\n");

if ($apply || $revert) {
  echo "Type the database name to continue: ";
  $answer = trim((string)fgets(STDIN));
  if ($answer !== $databaseName) exit("Not the same name. Nothing is changed.\n");
}

$backupTableExists = $pdo->query("SHOW TABLES LIKE 'articles_summary_backup'")->fetchColumn() !== false;

if ($revert) {
  if (! $backupTableExists) exit("There is no table articles_summary_backup. Nothing to revert.\n");

  $restored = 0;
  $skipped = 0;
  $info = $pdo->prepare('SELECT url, sitename, alltext FROM articles WHERE id=:id');
  $update = $pdo->prepare('UPDATE articles SET text=:old WHERE id=:id AND text=:current');
  foreach ($pdo->query('SELECT id, text_old FROM articles_summary_backup ORDER BY id')->fetchAll() as $backup) {
    $info->execute([':id' => $backup['id']]);
    $article = $info->fetch();
    if (! $article) { $skipped++; continue; }

    // Only restore if the summary is still the one this script made. A summary that was changed since is left alone.
    $update->execute([':old' => $backup['text_old'], ':id' => $backup['id'], ':current' => makeNewSummary($article)]);
    if ($update->rowCount() > 0) $restored++; else $skipped++;
  }
  echo "Restored $restored summaries. Skipped $skipped (already the old summary, or changed since).\n";
  exit(0);
}

if ($apply) {
  $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS articles_summary_backup (
  id            int          NOT NULL PRIMARY KEY,
  text_old      varchar(500) NOT NULL,
  changed_time  timestamp    NOT NULL DEFAULT current_timestamp()
) DEFAULT CHARSET=utf8mb4
SQL);
}

$select = $pdo->prepare("SELECT id, url, sitename, text, alltext FROM articles WHERE id > :lastid AND alltext <> '' ORDER BY id LIMIT " . BATCH_SIZE);
$backup = $pdo->prepare('INSERT IGNORE INTO articles_summary_backup (id, text_old) VALUES (:id, :old)');
$update = $pdo->prepare('UPDATE articles SET text=:new WHERE id=:id AND text=:old');

$lastId = 0;
$checked = 0;
$unchanged = 0;
$changedCount = 0;
$empty = 0;
$wasEllipsis = 0;
$sameStart = 0;
$samples = [];
$written = 0;

while (true) {
  $select->execute([':lastid' => $lastId]);
  $rows = $select->fetchAll();
  if (! $rows) break;

  if ($apply) $pdo->beginTransaction();
  foreach ($rows as $row) {
    $lastId = (int)$row['id'];
    $checked++;

    $cleanText = ArticleTextCleaner::clean($row['alltext'], $row['url'], $row['sitename']);
    $new = ArticleTextCleaner::makeSummary($cleanText);
    if ($new === '') { $empty++; continue; }
    if ($new === $row['text']) { $unchanged++; continue; }

    $changedCount++;
    if (preg_match('/(\.\.\.|…)\s*$/u', $row['text'])) $wasEllipsis++;
    if (summaryIsStartOfText($row['text'], $cleanText)) $sameStart++;
    if (count($samples) < 8) $samples[] = [$row['id'], $row['text'], $new];

    if ($apply) {
      $backup->execute([':id' => $row['id'], ':old' => $row['text']]);
      $update->execute([':new' => $new, ':id' => $row['id'], ':old' => $row['text']]);
      $written += $update->rowCount();
    }
  }
  if ($apply) $pdo->commit();
}

$would = $apply? 'were' : 'would be';
echo "\nArticles with a full text: $checked\n";
echo "  summary already up to date: $unchanged\n";
echo "  summary $would changed: $changedCount\n";
echo "      the old summary was already the start of the text (only length, capitals or place name differ): $sameStart\n";
echo "      the old summary says something else than the start of the text: " . ($changedCount - $sameStart) . "\n";
echo "      the old summary ended with ...: $wasEllipsis\n";
if ($empty) echo "  not changed because the cleaned text gives no summary: $empty\n";

echo "\nExamples (old summary, new summary):\n";
foreach ($samples as [$id, $old, $new]) {
  echo "  article $id\n    old: " . shortText($old) . "\n    new: " . shortText($new) . "\n";
}

if ($apply) {
  echo "\nChanged $written summaries. The old summaries are in articles_summary_backup.\n";
} else {
  echo "\nDry run: nothing was changed. Run with --apply to change the summaries.\n";
}
