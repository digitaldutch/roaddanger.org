<?php

// Uncomment for debug mode
ini_set('display_errors', 1);
error_reporting(E_ALL);
//error_reporting(0);

// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
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

