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

sendSecurityHeaders();

/**
 * Headers that make browsers protect visitors better. Sent with every page and ajax response.
 * No script-src in the Content-Security-Policy yet: the pages use inline scripts and event handlers.
 */
function sendSecurityHeaders(): void {
  if (PHP_SAPI === 'cli' || headers_sent()) return;

  // Browsers must not guess the type of a file. Stops an uploaded or fetched file from being run as a script.
  header('X-Content-Type-Options: nosniff');

  // Other websites cannot show this website in a frame (clickjacking). X-Frame-Options is for older browsers.
  header('X-Frame-Options: SAMEORIGIN');

  // Other websites only get the domain of the page a visitor comes from, not the full url. Never over http.
  header('Referrer-Policy: strict-origin-when-cross-origin');

  // This website needs no camera, microphone or payment. Location is used by the map.
  header('Permissions-Policy: camera=(), microphone=(), payment=(), geolocation=(self)');

  // frame-ancestors: same as X-Frame-Options. base-uri and object-src: block injected <base> and plugin tricks.
  // form-action: forms can only send data to this website.
  header("Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'");

  // After a visit over https, browsers use https for this domain for a year. Subdomains are not included.
  if (($_SERVER['HTTPS'] ?? '') === 'on') {
    header('Strict-Transport-Security: max-age=31536000');
  }
}

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

