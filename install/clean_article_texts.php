<?php
/**
 * Cleans the full text (articles.alltext) of the articles that are already in the database, with the same function
 * that cleans the text when an article is added: ArticleTextCleaner (rules in general/article_text_rules.php).
 *
 * It removes lines that are not part of the article (WhatsApp tips, newsletter adverts, bylines, photo credits),
 * removes the first paragraph if the text repeats it, and makes tidy paragraphs. See general/ArticleTextCleaner.php.
 *
 * Usage (command line only):
 *   php -d xdebug.mode=off install/clean_article_texts.php                       Dry run. Shows what would change. Changes nothing.
 *   php -d xdebug.mode=off install/clean_article_texts.php --apply               Changes the articles, after you type the database name.
 *   php -d xdebug.mode=off install/clean_article_texts.php --apply --skip-layout-only
 *                                                                                Only articles where text is removed. Not the ones where only
 *                                                                                spaces and blank lines change.
 *   php -d xdebug.mode=off install/clean_article_texts.php --revert              Puts the old texts back (only if the text was not edited since).
 *   Add --config=path to use another config file than config_secret.php, for example the config of another website.
 *   Add --database=name to use another database than the one in the config file.
 * The dry run asks the database for a read-only session, so it can not change anything.
 *
 * - The old text of every changed article is saved in the table articles_alltext_backup. The table is created by --apply.
 * - Running it again is safe. Clean articles are not touched, and the first backup is never overwritten.
 * - An article that would lose more than 70% of its text is not changed. It is shown in the report.
 * - An article that would get longer than the column (10,000 characters, because of the blank lines between paragraphs)
 *   is not changed either. It is shown in the report.
 * - Only articles.alltext is changed. Each batch is one transaction.
 * - Exports are cached for 24 hours (cache/ folder). Old exports still contain the old texts until they are renewed.
 * - This is not part of the website. The install folder is not uploaded to servers.
 */

const BATCH_SIZE = 200;
const MAX_REMOVED_FRACTION = 0.7;
const MAX_TEXT_LENGTH = 10000; // Size of the column alltext

if (PHP_SAPI !== 'cli') exit("This script can only be run from the command line.\n");

require_once __DIR__ . '/../general/ArticleTextCleaner.php';

function shortText(string $text, int $length = 70): string {
  return mb_substr(str_replace(["\r", "\n"], ' ', $text), 0, $length);
}

function normalizeSpaces(string $text): string {
  return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
}

/**
 * The paragraphs of the old text that are not in the new text. Layout is ignored.
 */
function removedParagraphs(string $old, string $new): array {
  $newCount = array_count_values(array_map('normalizeSpaces', explode("\n\n", $new)));
  $removed = [];
  foreach (explode("\n", $old) as $line) {
    $line = normalizeSpaces($line);
    if ($line === '') continue;
    if (! empty($newCount[$line])) $newCount[$line]--; else $removed[] = $line;
  }

  return $removed;
}

$options = getopt('', ['apply', 'revert', 'skip-layout-only', 'database:', 'config:']);
$apply = isset($options['apply']);
$revert = isset($options['revert']);
$skipLayoutOnly = isset($options['skip-layout-only']);
if ($apply && $revert) exit("Use --apply or --revert, not both.\n");

$configFile = $options['config'] ?? (__DIR__ . '/../config_secret.php');
if (! is_file($configFile)) exit("Config file not found: $configFile\n");
require_once $configFile;

$databaseName = $options['database'] ?? DB_NAME;
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $databaseName . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// A dry run must never write. The database refuses all writes in a read-only session.
if (! $apply && ! $revert) $pdo->exec('SET SESSION TRANSACTION READ ONLY');

echo "Config: $configFile\n";
echo "Database: $databaseName on " . DB_HOST . "\n";
echo $apply? "Mode: APPLY (changes the articles)\n" : ($revert? "Mode: REVERT (restores the old texts)\n" : "Mode: dry run (changes nothing)\n");

if ($apply || $revert) {
  echo "Type the database name to continue: ";
  $answer = trim((string)fgets(STDIN));
  if ($answer !== $databaseName) exit("Not the same name. Nothing is changed.\n");
}

$backupTableExists = $pdo->query("SHOW TABLES LIKE 'articles_alltext_backup'")->fetchColumn() !== false;

if ($revert) {
  if (! $backupTableExists) exit("There is no table articles_alltext_backup. Nothing to revert.\n");

  $restored = 0;
  $skipped = 0;
  $info = $pdo->prepare('SELECT url, sitename FROM articles WHERE id=:id');
  $update = $pdo->prepare('UPDATE articles SET alltext=:old WHERE id=:id AND alltext=:current');
  foreach ($pdo->query('SELECT id, alltext_old FROM articles_alltext_backup ORDER BY id')->fetchAll() as $backup) {
    $info->execute([':id' => $backup['id']]);
    $article = $info->fetch();
    if (! $article) { $skipped++; continue; }

    // Only restore if the text is still the one this script made. A text that was edited since is left alone.
    $current = ArticleTextCleaner::clean($backup['alltext_old'], $article['url'], $article['sitename']);
    $update->execute([':old' => $backup['alltext_old'], ':id' => $backup['id'], ':current' => $current]);
    if ($update->rowCount() > 0) $restored++; else $skipped++;
  }
  echo "Restored $restored articles. Skipped $skipped (already the old text, or edited since).\n";
  exit(0);
}

if ($apply) {
  $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS articles_alltext_backup (
  id            int          NOT NULL PRIMARY KEY,
  alltext_old   varchar(10000) NOT NULL,
  cleaned_time  timestamp    NOT NULL DEFAULT current_timestamp()
) DEFAULT CHARSET=utf8mb4
SQL);
}

$select = $pdo->prepare("SELECT id, YEAR(publishedtime) AS year, url, sitename, alltext FROM articles WHERE id > :lastid AND alltext <> '' ORDER BY id LIMIT " . BATCH_SIZE);
$backup = $pdo->prepare('INSERT IGNORE INTO articles_alltext_backup (id, alltext_old) VALUES (:id, :old)');
$update = $pdo->prepare('UPDATE articles SET alltext=:new WHERE id=:id AND alltext=:old');

$lastId = 0;
$checked = 0;
$unchanged = 0;
$layoutOnly = 0;
$withRemoved = 0;
$tooMuch = [];
$tooLong = [];
$changed = 0;
$charactersRemoved = 0;
$perYear = [];
$removedLines = [];
$samples = [];

while (true) {
  $select->execute([':lastid' => $lastId]);
  $rows = $select->fetchAll();
  if (! $rows) break;

  if ($apply) $pdo->beginTransaction();
  foreach ($rows as $row) {
    $lastId = (int)$row['id'];
    $checked++;

    $clean = ArticleTextCleaner::clean($row['alltext'], $row['url'], $row['sitename']);
    if ($clean === $row['alltext']) { $unchanged++; continue; }

    $removed = removedParagraphs($row['alltext'], $clean);
    $isLayoutOnly = ! $removed;
    if ($isLayoutOnly) $layoutOnly++; else $withRemoved++;

    // Never change an article that would lose most of its text
    if (mb_strlen($clean) < (1 - MAX_REMOVED_FRACTION) * mb_strlen($row['alltext'])) {
      $tooMuch[] = $row['id'];
      continue;
    }

    if (mb_strlen($clean) > MAX_TEXT_LENGTH) {
      $tooLong[] = $row['id'];
      continue;
    }

    if ($isLayoutOnly && $skipLayoutOnly) continue;

    $charactersRemoved += mb_strlen($row['alltext']) - mb_strlen($clean);
    $perYear[$row['year']] = ($perYear[$row['year']] ?? 0) + 1;
    foreach ($removed as $line) {
      $key = mb_substr($line, 0, 90);
      $removedLines[$key] = ($removedLines[$key] ?? 0) + 1;
    }
    if ((! $isLayoutOnly) && (count($samples) < 6)) $samples[] = [$row['id'], $removed];

    if ($apply) {
      $backup->execute([':id' => $row['id'], ':old' => $row['alltext']]);
      $update->execute([':new' => $clean, ':id' => $row['id'], ':old' => $row['alltext']]);
      $changed += $update->rowCount();
    }
  }
  if ($apply) $pdo->commit();
}

ksort($perYear);
arsort($removedLines);
$would = $apply? 'were' : 'would be';
echo "\nArticles with a full text: $checked\n";
echo "  unchanged: $unchanged\n";
echo "  only spaces and blank lines " . ($skipLayoutOnly? '(skipped)' : $would . ' changed') . ": $layoutOnly\n";
echo "  lines or text removed: $withRemoved\n";
if ($tooMuch) echo "  not changed because more than " . (MAX_REMOVED_FRACTION * 100) . "% of the text would go: " . count($tooMuch) . " (ids " . implode(', ', array_slice($tooMuch, 0, 15)) . (count($tooMuch) > 15? ', ...' : '') . ")\n";
if ($tooLong) echo "  not changed because the text would get longer than " . MAX_TEXT_LENGTH . " characters: " . count($tooLong) . " (ids " . implode(', ', array_slice($tooLong, 0, 15)) . (count($tooLong) > 15? ', ...' : '') . ")
";
echo "Characters that $would removed: $charactersRemoved\n";
echo "Per year of publication: " . json_encode($perYear) . "\n";

echo "\nMost removed lines (check that these do not belong to an article):\n";
foreach (array_slice($removedLines, 0, 25, true) as $line => $count) echo sprintf("  %5dx  %s\n", $count, $line);

echo "\nExamples of removed lines:\n";
foreach ($samples as [$id, $removed]) {
  echo "  article $id:\n";
  foreach (array_slice($removed, 0, 3) as $line) echo "    - " . shortText($line, 90) . "\n";
}

if ($apply) {
  echo "\nChanged $changed articles. The old texts are in articles_alltext_backup.\n";
} else {
  echo "\nDry run: nothing was changed. Run with --apply to change the articles.\n";
}
