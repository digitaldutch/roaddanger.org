<?php

require_once '../general/AjaxHandler.php';
require_once '../general/ExportFiles.php';
class ExportHandler extends AjaxHandler {

  public function handleRequest(): void {
    try {
      $exports = new ExportFiles($this->database);

      $response = match ($this->command) {
        'downloadCrashesData' => $exports->crashes($this->user->isModerator() && ! empty($this->input['includeAllText'])),
        'downloadResearchData' => $exports->research(),

        default => throw new \Exception('Invalid command'),
      };

      $this->respondWithSucces($response);

    } catch (Exception $e) {
      $this->respondWithError($e->getMessage());
    }
  }
}
