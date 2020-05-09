<?php

abstract class PageType {
  const stream                        = 0;
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
}

function getCallerIP(){
  return (isset($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '';
}

function datetimeDBToISO8601($datetimeDB){
  $datetime = new DateTime($datetimeDB);
  return $datetime->format('c'); // ISO 8601
}

/**
 * @param string $key
 * @return string
 */
function translate($key) {
  $lowerKey = strtolower($key);

  if      (isset($GLOBALS['language'][$lowerKey]))        $text = $GLOBALS['language'][$lowerKey];
  else if (isset($GLOBALS['defaultLanguage'][$lowerKey])) $text = $GLOBALS['defaultLanguage'][$lowerKey] . '¹'; // Untranslated text gets an superscript 1 to make them stand out;
  else $text = '[Undefined text: ' . $key . ']';
  return $lowerKey === $key? $text : ucfirst($text);
}

function translateArray($keys){
  $texts = [];
  foreach ($keys as $key){
    $texts[$key] = translate($key);
  }
  return $texts;
}

function pageWithEditMap($pageType){
  return in_array($pageType, [PageType::recent, PageType::stream, PageType::deCorrespondent, PageType::crash, PageType::moderations, PageType::childDeaths]);
}

function pageWithMap($pageType){
  return pageWithEditMap($pageType) || in_array($pageType, [PageType::map]);
}

function showIntroMessagePage($pageType){
  return in_array($pageType, [PageType::recent, PageType::stream, PageType::deCorrespondent]);
}

function headerContainsGZIP($headersRaw){
  function parseHeaders($headers) {
    $headerArray = array();
    foreach($headers as $header) {
      $headerParts = explode(':', $header,2);
      if(isset($headerParts[1])) $headerArray[trim($headerParts[0])] = trim($headerParts[1]);
      else {
        $headerArray[] = $header;
        if(preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$header, $out))
          $headerArray['reponse_code'] = intval($out[1]);
      }
    }
    return $headerArray;
  }
  $headers = parseHeaders($headersRaw);

  return isset($headers['Content-Encoding']) && $headers['Content-Encoding'] == 'gzip';
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

/**
 * @param string $url
 * @return array
 * @throws Exception
 */
function getPageMediaMetaData($url){
  $arrContextOptions = array(
    'ssl' => array(
      'verify_peer'      => false,
      'verify_peer_name' => false,
    ),
    'http' => array(
      'follow_location'  => true,
      'header'=>"User-agent: Googlebot-News this is not\r\n" .
        "Accept-Charset: UTF-8, *;q=0\r\n" .
        "Accept-Encoding: gzip\r\n"
    ),
  );

  $meta = ['og' => [], 'twitter' => [], 'article' => [], 'itemprop' => [], 'other' => []];

  // If mobiele website: Use desktop website instead
  if (strpos($url, '//m.') !== false){
    $url = str_replace('//m.', '//www.', $url);
    $arrContextOptions['http']['follow_location'] = true;
  }

  $url = str_replace('//m.', '//www.', $url);
  $html = @file_get_contents($url, false, stream_context_create($arrContextOptions));
  if ($html === false) throw new Exception("Kan link '$url' niet openen");

  // Convert GZIP content if needed
  if (headerContainsGZIP($http_response_header)) $html = gzdecode($html);

  // Handle UTF 8 properly
  $html = mb_convert_encoding($html, 'UTF-8',  mb_detect_encoding($html, 'UTF-8, ISO-8859-1', true));

  // See Google structured data guidelines https://developers.google.com/search/docs/guides/intro-structured-data#structured-data-guidelines

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
    } catch (Exception $e) {
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
  return json_encode(array('error' => $message));
}

function dieWithJSONErrorMessage($message) {
  die(jsonErrorMessage($message));
}

function getRandomString($length=16){
  return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, $length);
}

/**
 * @param string $emailTo
 * @param string $subject
 * @param string $body
 * @param array $ccList
 * @return bool
 * @throws Exception
 */
function sendEmail($emailTo, $subject, $body, $ccList=[]) {
  // DOCUMENT_ROOT does not work in cli. For cli this file is included in the start file (eg meterchecker.php).
  $root = realpath($_SERVER["DOCUMENT_ROOT"]);
  require_once $root . '/scripts/PHPMailerAutoload.php';

  $from     = DOMAIN_EMAIL;
  $fromName = DOMAIN_NAME;

  $mail = new PHPMailer;
  $mail->isSendmail();
  $mail->CharSet = 'UTF-8';
  $mail->setFrom($from, $fromName);
  $mail->addReplyTo($from);
  $mail->Subject = $subject;

  $mail->msgHTML($body);
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

function containsText($haystack, $needle){
  // https://stackoverflow.com/a/4366748/63849
  return strpos($haystack, $needle) !== false;
}

function formatMessage($text){
  require_once "./scripts/lib_autolink.php";

  $text = htmlspecialchars($text); // Escape all html special characters for protection
  $text = nl2br($text);            // Replace line endings with html equivalent
  $text = autolink($text);         // Linkify all links

  // PHPBB link style: [url=https://www.hetongeluk.nl]Het Ongeluk[/url]
  $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/', '<a href="$1">$2</a>', $text);

  return $text;
}