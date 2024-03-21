<?php

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
  case questionnaireOptions;
  case questionnaireResults;
  case questionnaireFillIn;
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
      PageType::childVictims,
      PageType::map,
    ]);
}

function pageWithMap($pageType){
  return pageWithEditMap($pageType) || in_array($pageType, [PageType::map]);
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

function getHtmlYearOptions($addEmpty=true, $amountYears=50){
  $texts = translateArray(['Always']);

  $yearNow = intval(date("Y"));
  $options = $addEmpty? "<option value=''>{$texts['Always']}</option>" : '';
  for ($year=$yearNow; $year >= $yearNow - $amountYears; $year--) $options .= "<option value={$year}>{$year}</option>";
  return $options;
}
