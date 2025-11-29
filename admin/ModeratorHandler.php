<?php

class ModeratorHandler {

  private Database $database;
  private User $user;
  private ?array $input = null;

  public function __construct(Database $database, User $user) {
    $this->database = $database;
    $this->user = $user;

    $data = file_get_contents('php://input');
    if (! empty($data)) $this->input = json_decode($data, true);
  }

  public function handleRequest($command): void {
    try {

      if (! $this->user->isModerator()) {
        throw new Exception('Moderators only');
      }

      // The stuff below is only for administrators
      $response = match($command) {
        'loadTranslations' => $this->loadTranslations(),
        'saveTranslations' => $this->saveTranslations(),
        default => throw new Exception('Invalid command'),
      };

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }

  private function respondWithSucces(array $response): void {
    header('Content-Type: application/json');

    $response['ok'] = true;
    echo json_encode($response);
  }

  private function respondWithError(string $error): void {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');

    echo json_encode([
      'ok' => false,
      'error' => $error
    ]);
  }

  private function loadTranslations(): array {
    $sql = "SELECT translations FROM languages WHERE id='en'";
    $translationsEnglish = json_decode($this->database->fetchSingleValue($sql), true);

    // Sort on key alphabetically
    ksort($translationsEnglish);

    $this->user->getTranslations();

    return [
      'translationsEnglish' => $translationsEnglish,
    ];
  }

  private function saveTranslations(): array {
    $sql = "SELECT translations FROM languages WHERE id=:id";
    $params = [':id' => $this->input['language']];
    $translations = json_decode($this->database->fetchSingleValue($sql, $params), true);

    foreach ($this->input['newTranslations'] as $new) {
      // Only lowercase keys are allowed. The first character becomes uppercase if the key is the first character uppercase.
      $translations[$new['id']] = $new['translation'];
    }

    $sql = "UPDATE languages SET translations =:translations WHERE id=:id";
    $params = [':translations' => json_encode($translations), ':id' => $this->input['language']];

    $this->database->execute($sql, $params);

    return [];

  }

}