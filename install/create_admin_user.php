<?php
/**
 * Creates an administrator user. Run it after init_database.php to create the first user of a new website.
 *
 * Usage (command line only):  php install/create_admin_user.php
 *
 * The script asks for the email address, name and password.
 * To run it without questions, set the environment variables
 * ROADDANGER_ADMIN_EMAIL, ROADDANGER_ADMIN_FIRSTNAME, ROADDANGER_ADMIN_LASTNAME and ROADDANGER_ADMIN_PASSWORD.
 *
 * Why a script and not a web page: there is no page that can create an administrator, so nobody can claim
 * a fresh website before you do. Creating an administrator needs access to the server.
 */

/**
 * Reads a line from the terminal without showing what is typed.
 */
function askHidden(string $prompt): string {
  echo $prompt;

  if (PHP_OS_FAMILY === 'Windows') {
    // PHP cannot hide input on Windows by itself. PowerShell can.
    $command = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; ' .
      '[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
    $value = shell_exec($command);
  } else {
    shell_exec('stty -echo');
    $value = fgets(STDIN);
    shell_exec('stty echo');
    echo "\n";
  }

  return rtrim((string)$value, "\r\n");
}

function askLine(string $prompt): string {
  echo $prompt;
  return trim((string)fgets(STDIN));
}

/**
 * Asks for the user details. Returns null when there is no terminal to ask them on.
 * @return array{email: string, firstname: string, lastname: string, password: string}|null
 */
function askAdminDetails(): ?array {
  $fromEnvironment = [
    'email'     => getenv('ROADDANGER_ADMIN_EMAIL'),
    'firstname' => getenv('ROADDANGER_ADMIN_FIRSTNAME'),
    'lastname'  => getenv('ROADDANGER_ADMIN_LASTNAME'),
    'password'  => getenv('ROADDANGER_ADMIN_PASSWORD'),
  ];
  if (! in_array(false, $fromEnvironment, true)) return array_map('trim', $fromEnvironment);

  if (! stream_isatty(STDIN)) return null;

  echo "Create the administrator user.\n";
  $details = [
    'email'     => askLine('Email: '),
    'firstname' => askLine('First name: '),
    'lastname'  => askLine('Last name: '),
  ];

  while (true) {
    $password = askHidden('Password (at least 6 characters): ');
    if (strlen($password) < 6) {
      echo "The password is too short.\n";
      continue;
    }
    if ($password !== askHidden('Repeat password: ')) {
      echo "The passwords are not the same.\n";
      continue;
    }
    break;
  }
  $details['password'] = $password;

  return $details;
}

/**
 * Adds an administrator (permission 1) to the users table.
 * @throws Exception when the details are not valid or the email address is already in use.
 * @return int The id of the new user.
 */
function createAdminUser(PDO $pdo, array $details): int {
  if (! filter_var($details['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('The email address is not valid.');
  if (strlen($details['email']) > 250) throw new Exception('The email address is too long.');
  if ($details['firstname'] === '' || strlen($details['firstname']) > 100) throw new Exception('The first name must have 1 to 100 characters.');
  if ($details['lastname']  === '' || strlen($details['lastname'])  > 100) throw new Exception('The last name must have 1 to 100 characters.');
  if (strlen($details['password']) < 6) throw new Exception('The password must have at least 6 characters.');

  $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
  $statement->execute([':email' => $details['email']]);
  if ($statement->fetchColumn() > 0) throw new Exception('A user with this email address already exists.');

  // 0=helper; 1=admin; 2=moderator
  $pdo->prepare('INSERT INTO users (email, firstname, lastname, language, countryid, passwordhash, permission)
                 VALUES (:email, :firstname, :lastname, :language, :countryid, :passwordhash, 1)')
    ->execute([
      ':email'        => $details['email'],
      ':firstname'    => $details['firstname'],
      ':lastname'     => $details['lastname'],
      ':language'     => DEFAULT_LANGUAGE,
      ':countryid'    => DEFAULT_COUNTRY_ID,
      ':passwordhash' => password_hash($details['password'], PASSWORD_DEFAULT),
    ]);

  return (int)$pdo->lastInsertId();
}

// Run only when this file is started directly, not when init_database.php includes it.
if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
  require_once __DIR__ . '/../config.php';

  try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $details = askAdminDetails();
    if ($details === null) exit("No terminal to ask questions on. Run this script from a terminal.\n");

    $userId = createAdminUser($pdo, $details);
    echo "Created administrator {$details['email']} (user id $userId).\n";
  } catch (Throwable $e) {
    exit('Error: ' . $e->getMessage() . "\n");
  }
}
