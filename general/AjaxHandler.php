<?php

abstract class AjaxHandler {

  protected Database $database;
  protected User $user;
  protected ?array $input = null;
  protected ?string $command;

  public function __construct(Database $database, User $user) {
    $this->database = $database;
    $this->user = $user;

    $this->rejectCrossSiteRequests();

    $this->command = $_REQUEST['function'] ?? null;

    if (empty($this->command)) dieWithJSONErrorMessage('No function specified');

    $data = file_get_contents('php://input');

    if (! empty($data)) $this->input = json_decode($data, true);
  }

  /**
   * Only POST requests with a JSON body are accepted, and no requests that another website made in the browser of a
   * visitor. A link or a form on another website cannot send such a request: a browser only sends a JSON body to
   * another website after a CORS check, which this website never allows. Without this, a link could make a logged-in
   * visitor's browser run an action (cross-site request forgery).
   */
  private function rejectCrossSiteRequests(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      header('Allow: POST');
      http_response_code(405);
      dieWithJSONErrorMessage('Only POST requests are allowed');
    }

    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (! str_starts_with($contentType, 'application/json')) {
      http_response_code(415);
      dieWithJSONErrorMessage('The content type must be application/json');
    }

    // Browsers tell where a request comes from. "same-site" is allowed: the country websites are subdomains.
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
      http_response_code(403);
      dieWithJSONErrorMessage('Cross-site requests are not allowed');
    }
  }

  abstract protected function handleRequest();

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