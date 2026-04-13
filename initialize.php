<?php

// Prevents JavaScript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Use a secure HTTPS connection
ini_set('session.cookie_secure', 1);

// Same site cookie
ini_set('session.cookie_samesite', 'Lax');

// Make sure cookie works also on subdomains (e.g. www.roaddanger.org & nl.roaddanger.org)
$serverName = $_SERVER['SERVER_NAME'];
// Extract domain parts
$parts = explode('.', $serverName);
// If there are only 2 parts (like roaddanger.org), use the whole name with a leading dot
// Otherwise, remove the first part (the subdomain)
$domain = (count($parts) <= 2) ? '.' . $serverName : substr($serverName, strpos($serverName, '.'));
ini_set('session.cookie_domain', $domain);

session_start();

date_default_timezone_set('Europe/Amsterdam');
setlocale(LC_MONETARY, 'nl_NL');

mb_internal_encoding('UTF-8');

// Environment constants
define("IS_DEVELOPMENT_ENVIRONMENT", $_SERVER['SERVER_NAME'] === 'localhost' || str_ends_with($_SERVER['SERVER_NAME'], '.test'));
const IS_PRODUCTION_ENVIRONMENT = ! IS_DEVELOPMENT_ENVIRONMENT;

// Show debug info only when developing or testing, not in production:
// - localhost
// - test domains ending in *.test
if (IS_DEVELOPMENT_ENVIRONMENT) {
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', 0);
  error_reporting(0);
}

require_once __DIR__ . '/config.php';
require_once 'database.php';
require_once 'users.php';
require_once 'general/utils.php';

// Send all unhandled Exceptions and Errors to the main developer
//set_error_handler('globalErrorHandler');
//set_exception_handler('globalExceptionHandler');
//register_shutdown_function('globalShutdownHandler');

try {
  $database = new Database();

  try {
    $database->open();
  } catch (\Exception $e){
    die('Internal error: Database connection failed');
  }

  $database->loadCountries();

  $user = new User($database);

} catch (\Exception $e) {
  $message = 'Internal error: Initialization failed: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString();
  die($message);
}

