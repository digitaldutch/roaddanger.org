<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';

global $user, $database;

// Only moderators allowed
if (! $user->isModerator()) {
  $result = ['ok' => false, 'error' => 'Mens is geen moderator', 'user' => $user->info()];
  die(json_encode($result));
}

$function = $_REQUEST['function'];

if ($function === 'loadTranslations') {
  try {
    $sql = "SELECT translations FROM languages WHERE id='en'";
    $translationsEnglish = json_decode($database->fetchSingleValue($sql), true);

    // Sort on key alphabetically
    ksort($translationsEnglish);

    $user->getTranslations();

    $result = ['ok' => true, 'translationsEnglish' => $translationsEnglish];
  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
else if ($function === 'saveTranslations') {
  try {

    $data = json_decode(file_get_contents('php://input'), true);

    $sql          = "SELECT translations FROM languages WHERE id=:id";
    $params       = [':id' => $data['language']];
    $translations = json_decode($database->fetchSingleValue($sql, $params), true);

    foreach ($data['newTranslations'] as $new) {
      // Only lowercase is allowed. First character becomes uppercase if key is first character uppercase.
      $translations[$new['id']] = $new['translation'];
    }

    $sql    = "UPDATE languages SET translations =:translations WHERE id=:id";
    $params = [':translations' => json_encode($translations), ':id' => $data['language']];
    $database->execute($sql, $params);

    $result = ['ok' => true];
    $result['user'] = $user->info();

  } catch (\Exception $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
  }

  echo json_encode($result);
} // ====================
