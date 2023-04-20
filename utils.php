<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

abstract class PageType {
  const lastChanged                        = 0;
  const crash                         = 1;
  const moderations                   = 2;
  const statisticsTransportationModes = 3;
  const statisticsGeneral             = 4;
  const statisticsCrashPartners       = 5;
  const recent                        = 6;
  const deCorrespondent               = 7;
  const mosaic                        = 8;
  const export                        = 9;
  const map                           = 10;
  const childDeaths                   = 11;
  const translations                  = 12;
  const longTexts                     = 13;
  const humans                        = 14;
  const questionnaireOptions          = 15;
  const questionnaireResults          = 16;
  const questionnaireFillIn           = 17;
}

abstract class QuestionnaireType {
  const standard = 0;
  const bechdel  = 1;
}

abstract class Answer {
  const no               = 0;
  const yes              = 1;
  const notDeterminable  = 2;
}

function translate($key){
  global $user;
  return $user->translate($key);
}

function translateLongText($key){
  global $user;
  return $user->translateLongText($key);
}

function translateArray($keys){
  $texts = [];

  foreach ($keys as $key) $texts[$key] = translate($key);

  return $texts;
}

function getCallerIP(){
  return (isset($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '';
}

function datetimeDBToISO8601($datetimeDB){
  $datetime = new DateTime($datetimeDB);
  return $datetime->format('c'); // ISO 8601
}

function pageWithEditMap($pageType){
  return in_array($pageType,
    [PageType::recent,
      PageType::lastChanged,
      PageType::deCorrespondent,
      PageType::mosaic,
      PageType::crash,
      PageType::moderations,
      PageType::childDeaths,
      PageType::map,
    ]);
}

function pageWithMap($pageType){
  return pageWithEditMap($pageType) || in_array($pageType, [PageType::map]);
}

function parse_url_all($url){
  $url = substr($url,0,4)=='http'? $url: 'http://'.$url;
  $d = parse_url($url);
  $tmp = explode('.',$d['host']);
  $n = count($tmp);
  if ($n>=2){
    if ($n==4 || ($n==3 && strlen($tmp[($n-2)])<=3)){
      $d['domain'] = $tmp[($n-3)].".".$tmp[($n-2)].".".$tmp[($n-1)];
      $d['domainX'] = $tmp[($n-3)];
    } else {
      $d['domain'] = $tmp[($n-2)].".".$tmp[($n-1)];
      $d['domainX'] = $tmp[($n-2)];
    }
  }
  return $d;
}

function getWebsiteUserAgent($url) {
  // Some websites only use with certain User agents. Set a custom user agent for websites that do not work with the default one.

  // Chrome 108 agent
  $agentChrome = "User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36";

  if (str_contains($url, 'www.jn.pt')) return $agentChrome;
  return null;
}

function curlDownload($url) {

  $headers = [
    "Accept-Encoding:gzip,deflate"
  ];

  // Note: Using own user-agent gets us blocked on several websites apparently using white listing.
  //  "User-Agent:roaddanger.org | Scientific research on crashes",
  // Note: We no longer fake Googlebot-News headers as most media websites now allow default user-agent.
  // Some websites block the server ip if we fake the user agent.
  // Note: Some website do not work if no agent is set.
  $userAgent = getWebsiteUserAgent($url);
  if ($userAgent !== null) {
    $headers[] = $userAgent;
  }

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_ENCODING,"gzip");
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION,true);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,false); // Don't verify authenticity of peer

  $data = curl_exec($curl);

  curl_close($curl);
  return $data;
}

function parseLdObject(&$result, $data) {
  if (isset($data->headline))      $result['headline']      = $data->headline;
  if (isset($data->articleBody))   $result['articleBody']   = $data->articleBody;
  if (isset($data->description))   $result['description'] = $data->description;
  if (isset($data->datePublished)) $result['datePublished'] = $data->datePublished;
  if (isset($data->image)) {
    if (is_string($data->image)) $result['image'] = $data->image;
    else if (is_object($data->image) && isset($data->image->url)) $result['image'] = $data->image->url;
  }
  if (isset($data->publisher) && isset($data->publisher->name)) $result['publisher'] = $data->publisher->name;
}

function parse_ld_json($json, &$result) {
  $ldJson = trim($json);
  if (! isset($ldJson)) return;

  if (! isset($result)) $result = [];
  $ld = json_decode($ldJson);
  if (! isset($ld)) return;

  if (is_array($ld)) foreach ($ld as $entry) parseLdObject($result, $entry);
  else parseLdObject($result, $ld);
}

/**
 * @param string $url
 * @return array
 * @throws Exception
 */
function getPageMediaMetaData($url){
  $meta = [
    'json-ld'  => [],
    'og'       => [],
    'twitter'  => [],
    'article'  => [],
    'itemprop' => [],
    'other'    => []
  ];

  // If mobiele website: Use desktop website instead
  if (str_contains($url, '//m.')){
    $url = str_replace('//m.', '//www.', $url);
    $arrContextOptions['http']['follow_location'] = true;
  }

  $url = str_replace('//m.', '//www.', $url);

  $html = curlDownload($url);

  if ($html === false) throw new Exception(translate('Unable_to_load_url') . '<br>' . $url);

  // Handle UTF 8 properly
  $html = mb_convert_encoding($html, 'UTF-8',  mb_detect_encoding($html, 'UTF-8, ISO-8859-1', true));

  // Some website html encode their tag names
  $html = html_entity_decode($html);

  // See Google structured data guidelines https://developers.google.com/search/docs/guides/intro-structured-data#structured-data-guidelines
  // json-ld tags. Used by Google.

  $matches = null;
  // Check for both property and name attributes. nu.nl uses incorrectly name
  preg_match_all('~<\s*script\s+[^<>]*ld\+json[^<>]*>(.*)<\/script>~iUs', $html, $matches);
  for ($i=0; $i<count($matches[1]); $i++) {
    $ldJson = trim($matches[1][$i]);
    parse_ld_json($ldJson, $meta['json-ld']);
  }

  // Open Graph tags
  $matches = null;
  // Check for both property and name attributes. nu.nl uses incorrectly name
  preg_match_all('~<\s*meta\s+[^<>]*[property|name]=[\'"](og:[^"]+)[\'"]\s+[^<>]*content="([^"]*)~i', $html,$matches);
  for ($i=0; $i<count($matches[1]); $i++) $meta['og'][$matches[1][$i]] = $matches[2][$i];

  // Twitter tags
  $matches = null;
  // Check for both property and name attributes. nu.nl uses incorrectly name
  preg_match_all('~<\s*meta\s+[^<>]*[property|name]="(twitter:[^"]+)"\s+[^<>]*content="([^"]*)~i', $html,$matches);
  for ($i=0; $i<count($matches[1]); $i++) $meta['twitter'][$matches[1][$i]] = $matches[2][$i];

  // Article tags
  $matches = null;
  preg_match_all('~<\s*meta\s+[^<>]*[property|name]="(article:[^"]+)"\s+[^<>]*content="([^"]*)~i', $html,$matches);
  for ($i=0; $i<count($matches[1]); $i++) $meta['article'][$matches[1][$i]] = $matches[2][$i];

  // Itemprop content general tags
  $matches = null;
  // content must not be empty. Thus + instead of *
  preg_match_all('~<\s*[^<>]*itemprop="(datePublished)"\s+[^<>]*content="([^"]+)~i', $html,$matches);
  for ($i=0; $i<count($matches[1]); $i++) $meta['itemprop'][$matches[1][$i]] = $matches[2][$i];

  // h1 tag
  $matches = null;
  preg_match_all('~<h1.*>(.*)<\/h1>~i', $html,$matches);
  if (count($matches[1]) > 0) $meta['other']['h1'] = $matches[1][0];

  // Description meta tag
  $matches = null;
  preg_match_all('~<\s*meta\s+[^<>]*[property|name]=[\'"](description)[\'"]\s+[^<>]*content=[\'"]([^"\']*)~i', $html,$matches);
  if (count($matches[1]) > 0) $meta['other']['description'] = $matches[2][0];

  // Time tag
  preg_match_all('~<\s*time\s+[^<>]*[datetime]=[\'"]([^"\']*)[\'"]~i', $html,$matches);
  if (count($matches[1]) > 0) {
    $dateText     = $matches[1][0];
    try {
      $date         = new DateTime($dateText);
      $current_date = new DateTime();
      if ($date < $current_date) $meta['other']['time'] = $date->format('Y-m-d');
    } catch (\Exception $e) {
      // Silent exception
    }
  }

  $meta['other']['domain'] = parse_url_all($url)['domain'];

  return $meta;
}

function emptyIfNull($parameter){
  return isset($parameter)? $parameter : '';
}

function jsonErrorMessage($message) {
  return json_encode(['error' => $message]);
}

function dieWithJSONErrorMessage($message) {
  die(jsonErrorMessage($message));
}

function getRandomString($length=16){
  return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, $length);
}

function sendEmail(string $emailTo, string $subject, string $body, array $ccList=[]): bool {
  $root = realpath($_SERVER["DOCUMENT_ROOT"]);
  require_once $root . '/scripts/PHPMailer/PHPMailer.php';
  require_once $root . '/scripts/PHPMailer/SMTP.php';
  require_once $root . '/scripts/PHPMailer/Exception.php';

  $from     = 'noreply@roaddanger.org';
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

  if (! $mail->send()) throw new Exception($mail->ErrorInfo);
  return true;
}

function getRequest($name, $default=null) {
  return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}

function cookiesApproved(){
  return isset($_COOKIE['cookiesAccepted']) && (isset($_COOKIE['cookiesAccepted']) == 1);
}

function addSQLWhere(&$whereSql, $wherePart){
  if ($wherePart === '') return;
  $whereSql .= ($whereSql === '')? ' WHERE ' : ' AND ';
  $whereSql .= ' ' . $wherePart . ' ';
}

function addHealthWhereSql(&$sqlWhere, &$joinPersonsTable, $filter){
  if ((isset($filter['healthDead'])    && ($filter['healthDead'] === 1)) ||
    (isset($filter['healthInjured']) && ($filter['healthInjured'] === 1))) {
    $joinPersonsTable = true;
    $values = [];
    if ($filter['healthDead']    === 1) $values[] = 3;
    if ($filter['healthInjured'] === 1) $values[] = 2;
    $valuesText = implode(", ", $values);
    addSQLWhere($sqlWhere, " cp.health IN ($valuesText) ");
  }
}

function addPersonsWhereSql(&$sqlWhere, &$sqlJoin, $filterPersons) {
  if (isset($filterPersons) && (count($filterPersons) > 0)) {
    foreach ($filterPersons as $person){
      $tableName          = 'p' . $person;
      $transportationMode = (int)$person;
      $personDead         = str_contains($person, 'd');
      $personInjured      = str_contains($person, 'i');
      $restricted         = str_contains($person, 'r');
      $unilateral         = str_contains($person, 'u');
      $sqlJoin .= " JOIN crashpersons $tableName ON c.id = $tableName.crashid AND $tableName.transportationmode=$transportationMode ";
      if ($personDead || $personInjured ) {
        $healthValues = [];
        if ($personDead)    $healthValues[] = 3;
        if ($personInjured) $healthValues[] = 2;
        $healthValues = implode(',', $healthValues);
        $sqlJoin .= " AND $tableName.health IN ($healthValues) ";
      }
      if ($restricted) addSQLWhere($sqlWhere, "(c.unilateral is null OR c.unilateral != 1) AND (c.id not in (select au.id from crashes au LEFT JOIN crashpersons apu ON au.id = apu.crashid WHERE apu.transportationmode != $transportationMode))");
      if ($unilateral) addSQLWhere($sqlWhere, "c.unilateral = 1");
    }
  }
}

function formatMessage($text){
  require_once "./scripts/lib_autolink.php";

  $text = htmlspecialchars($text); // Escape all html special characters for protection
  $text = nl2br($text);            // Replace line endings with html equivalent
  $text = autolink($text);         // Linkify all links

  // PHPBB link style: [url=https://www.roaddanger.org]Roaddanger[/url]
  $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/', '<a href="$1">$2</a>', $text);

  return $text;
}

function getHtmlYearOptions($addEmpty=true, $amountYears=50){
  $yearNow = intval(date("Y"));
  $options = $addEmpty? '<option value="">Always</option>' : '';
  for ($year=$yearNow; $year >= $yearNow - $amountYears; $year--) $options .= "<option value={$year}>{$year}</option>";
  return $options;
}
