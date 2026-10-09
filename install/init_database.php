<?php
/**
 * Creates the database of a new roaddanger website and fills it with the initial data.
 *
 * Usage (command line only):  php install/init_database.php
 *                               php install/init_database.php --no-user   (do not create an administrator)
 *
 * 1. Creates the database DB_NAME from config_secret.php if it does not exist (needs the CREATE privilege).
 * 2. Creates all tables from createdatabase.sql.
 * 3. Adds the initial data from init_data.sql. The website does not work without it:
 *    - languages: the user interface texts
 *    - countries
 *    - longtexts: the texts of the info pages
 *    - ai_models, ai_prompts: the settings for the AI features
 *    This is public data. There are no users, crashes or articles.
 * 4. Asks for the details of the first user and creates an administrator (see create_admin_user.php).
 *
 * Safety:
 * - Refuses to run when the database already contains tables.
 * - The initial data is loaded in one transaction. Nothing is added when an error occurs.
 *
 * ai_prompts.user_id points to user 1. That is the administrator created at the end of this script,
 * so the foreign key is not checked while loading the data.
 */

if (PHP_SAPI !== 'cli') exit("This script can only be run from the command line.\n");

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/create_admin_user.php';

if (! preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) {
  exit("DB_NAME '" . DB_NAME . "' may only contain letters, digits and underscores.\n");
}

/**
 * Splits SQL into separate statements at each ; outside quotes and comments.
 * Enough for createdatabase.sql and init_data.sql. Not a general SQL parser.
 */
function splitSqlStatements(string $sql): array {
  $statements = [];
  $current    = '';
  $quote      = null;
  $length     = strlen($sql);

  for ($i = 0; $i < $length; $i++) {
    $char = $sql[$i];

    if ($quote !== null) {
      $current .= $char;
      if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
        $current .= $sql[++$i];
      } elseif ($char === $quote) {
        if ($i + 1 < $length && $sql[$i + 1] === $quote) $current .= $sql[++$i]; // Doubled quote = literal quote
        else $quote = null;
      }
      continue;
    }

    if ($char === "'" || $char === '"' || $char === '`') {
      $quote = $char;
      $current .= $char;
    } elseif ($char === '#' || ($char === '-' && ($sql[$i + 1] ?? '') === '-')) {
      $end = strpos($sql, "\n", $i);
      $i = ($end === false) ? $length : $end;
    } elseif ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
      $end = strpos($sql, '*/', $i + 2);
      $i = ($end === false) ? $length : $end + 1;
    } elseif ($char === ';') {
      if (trim($current) !== '') $statements[] = trim($current);
      $current = '';
    } else {
      $current .= $char;
    }
  }
  if (trim($current) !== '') $statements[] = trim($current);

  return $statements;
}

function runSqlFile(PDO $pdo, string $path): int {
  $statements = splitSqlStatements(file_get_contents($path));
  foreach ($statements as $statement) $pdo->exec($statement);
  return count($statements);
}

try {
  $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);

  // Use the MariaDB default sql_mode, also on MySQL. See database.php.
  $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

  try {
    $pdo->exec('USE `' . DB_NAME . '`');
  } catch (PDOException) {
    $pdo->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');
    echo "Created database " . DB_NAME . ".\n";
  }

  if ($pdo->query('SHOW TABLES')->rowCount() > 0) {
    exit("Refusing to run: database " . DB_NAME . " already contains tables.\n");
  }

  runSqlFile($pdo, __DIR__ . '/createdatabase.sql');
  echo 'Created ' . $pdo->query('SHOW TABLES')->rowCount() . " tables.\n";

  $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
  $pdo->beginTransaction();
  try {
    runSqlFile($pdo, __DIR__ . '/init_data.sql');
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  } finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
  }
} catch (Throwable $e) {
  exit('Error: ' . $e->getMessage() . "\n");
}

$counts = [];
foreach (['languages', 'countries', 'longtexts', 'ai_models', 'ai_prompts'] as $table) {
  $counts[] = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() . " $table";
}
echo 'Added initial data: ' . implode(', ', $counts) . ".\n";
// The first user. Use --no-user to skip, e.g. when you add test users yourself.
$adminEmail = null;
if (in_array('--no-user', $argv, true)) {
  echo "No administrator created (--no-user). Create one later with: php install/create_admin_user.php\n";
} else {
  try {
    $details = askAdminDetails();
    if ($details === null) {
      echo "No terminal to ask questions on, so no administrator was created. Create one with: php install/create_admin_user.php\n";
    } else {
      $userId = createAdminUser($pdo, $details);
      $adminEmail = $details['email'];
      echo "Created administrator $adminEmail (user id $userId).\n";
    }
  } catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\nThe database is ready. Create an administrator with: php install/create_admin_user.php\n";
  }
}
if ($adminEmail !== null) echo "Done. You can open the website now and log in with $adminEmail.\n";
else echo "Done. Create an administrator with: php install/create_admin_user.php. Then you can open the website.\n";
