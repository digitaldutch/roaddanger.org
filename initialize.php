<?php

// Debug mode
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// Release mode: Suppress messages
error_reporting(0);

// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 1);

// Same site cookie: PHP 7.3+
ini_set('session.cookie_samesite', 'Lax');

// Make sure cookie works also on subdomains (e.g. www.roaddanger.org & nl.roaddanger.org)
$domain = substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'],"."),100);
ini_set('session.cookie_domain', $domain);

session_start();

date_default_timezone_set('Europe/Amsterdam');
setlocale(LC_MONETARY, 'nl_NL');

mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';
require_once 'templates.php';
require_once 'database.php';
require_once 'users.php';
require_once 'utils.php';

try {

  $database = new Database();

  try {
    $database->open();
  } catch (Exception $e){
    die('Internal error: Database connection failed');
  }

  $database->loadCountries();

  $user = new User($database);

} catch (Exception $e){
  die('Internal error: Initialization failed');
}

