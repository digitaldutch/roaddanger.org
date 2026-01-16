<?php

use JetBrains\PhpStorm\NoReturn;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

enum PageType {
  case lastChanged;
  case crash;
  case moderations;
  case statisticsTransportationModes;
  case statisticsGeneral;
  case statisticsHumanizationTest;
  case statisticsCrashPartners;
  case recent;
  case deCorrespondent;
  case mosaic;
  case export;
  case map;
  case childVictims;
  case translations;
  case longTexts;
  case humans;
  case questionnaireSettings;
  case questionnaireResults;
  case questionnaireFillIn;
  case ai_prompt_builder;
}

enum QuestionnaireType: int {
  case standard = 0;
  case bechdel = 1;
}

enum Answer: int {
  case no = 0;
  case yes = 1;
  case notDeterminable = 2;
}

enum StreamTopType: int {
  case new = 0;
  case edited = 1;
  case articleAdded = 2;
  case placedOnTop = 3;
}

function translate($key): string {
  global $user;
  return $user->translate($key);
}

function translateLongText($key): string {
  global $user;
  return $user->translateLongText($key);
}

function translateArray($keys): array {
  $texts = [];

  foreach ($keys as $key) $texts[$key] = translate($key);

  return $texts;
}

function getCallerIP(){
  return (isset($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '';
}

/**
 * @throws DateMalformedStringException
 */
function datetimeDBToISO8601($datetimeDB): string {
  if (empty($datetimeDB)) return '';

  $datetime = new DateTime($datetimeDB);
  return $datetime->format('c'); // ISO 8601
}

function pageWithEditMap($pageType): bool {
  return in_array($pageType,
    [PageType::recent,
      PageType::lastChanged,
      PageType::deCorrespondent,
      PageType::mosaic,
      PageType::crash,
      PageType::moderations,
      PageType::childVictims,
      PageType::map,
    ]);
}

function pageWithMap($pageType): bool {
  return pageWithEditMap($pageType) || in_array($pageType, [PageType::map]);
}


function jsonErrorMessage($message): false|string {
  return json_encode(['error' => $message]);
}

#[NoReturn]
function dieWithJSONErrorMessage($message): void {
  die(jsonErrorMessage($message));
}

function getRandomString($length): string {
  return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, $length);
}

/**
 * @throws Exception
 */
function sendEmail(string $emailTo, string $subject, string $body, array $ccList=[]): bool {
  $root = realpath($_SERVER["DOCUMENT_ROOT"]);
  require_once $root . '/scripts/PHPMailer/PHPMailer.php';
  require_once $root . '/scripts/PHPMailer/SMTP.php';
  require_once $root . '/scripts/PHPMailer/Exception.php';

  $from = 'noreply@' . WEBSITE_DOMAIN;
  $fromName = $_SERVER['SERVER_NAME'];

  $mail = new PHPMailer;

  $mail->isSendmail();

  $mail->CharSet = 'UTF-8';
  $mail->Subject = $subject;
  $mail->msgHTML($body);
  $mail->setFrom($from, $fromName);
  $mail->addReplyTo($from);
  $mail->addAddress($emailTo);

  foreach ($ccList as $cc){
    $mail->addCC($cc);
  }

  if (! $mail->send()) throw new \Exception($mail->ErrorInfo);
  return true;
}

function getRequest($name, $default=null): mixed {
  return $_REQUEST[$name] ?? $default;
}

function addSQLWhere(&$whereSql, $wherePart): void {
  if ($wherePart === '') return;
  $whereSql .= ($whereSql === '')? ' WHERE ' : ' AND ';
  $whereSql .= ' ' . $wherePart . ' ';
}

function addPersonsWhereSql(&$sqlWhere, $filter): void {
  $dead = isset($filter['healthDead']) && ($filter['healthDead'] === 1);
  $injured = isset($filter['healthInjured']) && ($filter['healthInjured'] === 1);
  $child = isset($filter['child']) && ($filter['child'] === 1);
  $persons = isset($filter['persons']) && (count($filter['persons']) > 0);

  if ($dead || $injured || $child) {
    $values = [];
    if ($dead) $values[] = 3;
    if ($injured) $values[] = 2;
    $valuesText = implode(", ", $values);

    $wherePersons = 'c.id = crashid';
    if (! empty($valuesText)) addSQLWhere($wherePersons, "cp.health IN ($valuesText)");

    if ($child) addSQLWhere($wherePersons, "cp.child = 1");

    $where = "EXISTS(SELECT 1 FROM crashpersons cp WHERE $wherePersons)";

    addSQLWhere($sqlWhere, $where);
  }

  if ($persons) {
    foreach ($filter['persons'] as $person){
      $transportationMode = (int)$person;
      $personDead = str_contains($person, 'd');
      $personInjured = str_contains($person, 'i');
      $restricted = str_contains($person, 'r');
      $unilateral = str_contains($person, 'u');

      if ($restricted) addSQLWhere($sqlWhere, "(c.unilateral is null OR c.unilateral != 1) AND (c.id not in (select au.id from crashes au LEFT JOIN crashpersons apu ON au.id = apu.crashid WHERE apu.transportationmode != $transportationMode))");
      if ($unilateral) addSQLWhere($sqlWhere, "c.unilateral = 1");

      $wherePersons = "cp.crashid = c.id AND cp.transportationmode=$transportationMode";

      $healthValues = [];
      if ($personDead) $healthValues[] = 3;
      if ($personInjured) $healthValues[] = 2;
      $healthValues = implode(',', $healthValues);

      if (! empty($healthValues)) addSQLWhere($wherePersons, "cp.health IN ($healthValues)");


      $where = "EXISTS(SELECT 1 FROM crashpersons cp WHERE $wherePersons)";
      addSQLWhere($sqlWhere, $where);
    }
  }
}

/**
 * @throws Exception
 */
function sendErrorEmail($subject, $message): void {
  sendEmail(EMAIL_FOR_ERRORS, WEBSITE_NAME . ' ' . $subject, $message);
}


/**
 * @throws Exception
 */
function globalErrorHandler($severity, $message, $file, $line): void {
  $backtrace = debug_backtrace();
  $formattedBacktrace = "Call Stack:\n";
  foreach ($backtrace as $key => $trace) {
    $file = $trace['file'] ?? '[internal]';
    $line = $trace['line'] ?? 'N/A';
    $function = $trace['function'] ?? 'Unknown';
    $formattedBacktrace .= "#{$key} {$file}:{$line} - {$function}()\n";
  }

  $errorDetails = "Error Occurred:\n";
  $errorDetails .= "Message: {$message}\n";
  $errorDetails .= "File: {$file}\n";
  $errorDetails .= "Line: {$line}\n";
  $errorDetails .= "Severity: {$severity}\n\n{$formattedBacktrace}";

  sendErrorEmail("PHP Error", $errorDetails);
}

/**
 * @throws Exception
 */
function globalExceptionHandler(Throwable $e): void {
  $trace = $e->getTrace();
  $formattedTrace = "Call Stack:\n";
  foreach ($trace as $key => $traceItem) {
    $file = $traceItem['file'] ?? '[internal]';
    $line = $traceItem['line'] ?? 'N/A';
    $function = $traceItem['function'] ?? 'Unknown';
    $formattedTrace .= "#{$key} {$file}:{$line} - {$function}()\n";
  }

  $exceptionDetails = "Uncaught Exception:\n";
  $exceptionDetails .= "Message: {$e->getMessage()}\n";
  $exceptionDetails .= "File: {$e->getFile()}\n";
  $exceptionDetails .= "Line: {$e->getLine()}\n\n{$formattedTrace}";

  try {
    sendErrorEmail("PHP Uncaught Exception", $exceptionDetails);
  } catch (Exception $e) {

  }
}

/**
 * @throws Exception
 */
function globalShutdownHandler (): void {
  $error = error_get_last();
  if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
    $errorDetails = "Fatal Error:\n";
    $errorDetails .= "Message: {$error['message']}\n";
    $errorDetails .= "File: {$error['file']}\n";
    $errorDetails .= "Line: {$error['line']}\n";

    if (function_exists('debug_backtrace')) {
      $backtrace = debug_backtrace();
      $formattedBacktrace = "\nCall Stack:\n";
      foreach ($backtrace as $key => $trace) {
        $file = $trace['file'] ?? '[internal]';
        $line = $trace['line'] ?? 'N/A';
        $function = $trace['function'] ?? 'Unknown';
        $formattedBacktrace .= "#{$key} {$file}:{$line} - {$function}()\n";
      }
      $errorDetails .= $formattedBacktrace;
    }

    sendErrorEmail("PHP Fatal Error", $errorDetails);
  }
}

function writeSessionAndClose(string $id, $value): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION[$id] = $value;
  session_write_close();
}

function deleteSessionIdAndClose(string $id): void {
  if (isset($_SESSION[$id])) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    unset($_SESSION[$id]);
    session_write_close();
  }
}

/**
 * Return a registrable base domain for cookies (example.com, example.co.uk, etc.)
 * Returns '' for localhost or IPs so the Domain attribute is omitted (required for those).
 */
function cookie_base_domain(string $host): string {
  $hostLower = strtolower($host);

  // Localhost or IP (IPv4/IPv6)
  if ($hostLower === 'localhost' || filter_var($hostLower, FILTER_VALIDATE_IP)) {
    return '';
  }

  // Handle some common multipart public suffixes
  $multiPartTLDs = [
    'co.uk','org.uk','ac.uk','gov.uk',
    'com.au','net.au','org.au',
    'co.nz','co.jp'
  ];
  foreach ($multiPartTLDs as $tld) {
    if (str_ends_with($hostLower, '.' . $tld)) {
      $parts = explode('.', $hostLower);
      $keep = substr_count($tld, '.') + 2; // e.g., example + co + uk
      return implode('.', array_slice($parts, -$keep));
    }
  }

  // Default: last two labels (example.com)
  $parts = explode('.', $hostLower);
  return count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : '';
}

/**
 * Set a site-wide cookie for apex + subdomains.
 */
function set_site_cookie(string $name, string $value, int $expiresDays, array $opts = []): bool {
  $host = $opts['host'] ?? ($_SERVER['HTTP_HOST'] ?? '');
  $domain = $opts['domain'] ?? cookie_base_domain($host);

  $expires = time() + $expiresDays * 24 * 60 * 60;
  $options = [
    'expires'  => $expires,
    'path'     => $opts['path'] ?? '/',
    // only include domain if non-empty (omit for localhost/IP)
    'domain'   => $domain ?: null,
    'secure'   => $opts['secure'] ?? true,
    'httponly' => $opts['httponly'] ?? true,
    'samesite' => $opts['samesite'] ?? 'Lax', // use 'None' if you need cross-site
  ];

  // Remove null to avoid sending an empty Domain attribute
  if ($options['domain'] === null) unset($options['domain']);

  return setcookie($name, $value, $options);
}

/**
 * Delete a cookie set by set_site_cookie. Must match path/domain.
 *
 * @param string $name
 * @param array $opts Optional overrides: path, secure, httponly, samesite, domain, host (for detection)
 * @return bool
 */
function delete_site_cookie(string $name, array $opts = []): bool {
  $host = $opts['host'] ?? ($_SERVER['HTTP_HOST'] ?? '');
  $domain = $opts['domain'] ?? cookie_base_domain($host);

  $options = [
    'expires' => time() - 3600,
    'path' => $opts['path'] ?? '/',
    'domain' => $domain ?: null,
    'secure' => $opts['secure'] ?? true,
    'httponly' => $opts['httponly'] ?? true,
    'samesite' => $opts['samesite'] ?? 'Lax',
  ];

  if ($options['domain'] === null) unset($options['domain']);

  return setcookie($name, '', $options);
}


function replaceArticleTags(string $text, object $article): string {
  if (isset($article->date)) $text = str_replace('[article_date]', $article->date, $text);
  $text = str_replace('[article_text]', $article->text, $text);
  return str_replace('[article_title]', $article->title, $text);
}

function replaceAI_QuestionnaireTags(string $text, $questionnaires): string {
  if (str_contains('[questionnaires]', $text)) {
//    $text = str_replace('[questionnaires]', $article->questionnaires, $text);
  }

  return $text;
}

function geocodeLocation($locationPrompt): ?array {
  if (! defined('HERE_API_KEY')) return null;

  try {
    // Using HERE map service. Preferred over OpenStreetMap-based services as it is better in places and does intersections
    $url = "https://geocode.search.hereapi.com/v1/geocode?q=" . urlencode($locationPrompt) . "&apiKey=" . HERE_API_KEY;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      throw new \Exception('cURL error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode !== 200) {
      throw new \Exception("Geocoder error: Received response code $httpCode");
    }

    $data = json_decode($response, true);

    // Check if the response contains results
    if (isset($data['items']) && count($data['items']) > 0) {
      $position = $data['items'][0]['position'];
      return [
        'latitude' => $position['lat'],
        'longitude' => $position['lng']
      ];
    } else {
      throw new \Exception("Geocoder error: No results found for the given address: $locationPrompt");
    }

  } catch (Throwable $e) {
    return null;
  }
}
