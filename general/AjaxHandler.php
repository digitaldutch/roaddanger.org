<?php

abstract class AjaxHandler {

  protected Database $database;
  protected User $user;
  protected ?array $input = null;

  public function __construct(Database $database, User $user) {
    $this->database = $database;
    $this->user = $user;

    $data = file_get_contents('php://input');
    if (! empty($data)) $this->input = json_decode($data, true);
  }

  abstract protected  function handleRequest($command);

  protected  function respondWithSucces(array $response): void {
    header('Content-Type: application/json');

    $response['ok'] = true;
    echo json_encode($response);
  }

  protected  function respondWithError(string $error): void {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');

    echo json_encode([
      'ok' => false,
      'error' => $error
    ]);
  }

}