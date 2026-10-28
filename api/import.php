<?php

/**
 * Import API: /api/import (see the rewrite rule in .htaccess). POST with a JSON body:
 * {"token": "...", "dryRun": false, "articles": [{"source": "...", "externalid": "...", "title": "...", "alltext": "...", "date": "...", ...}]}
 * The token is only read from the body, never from the url. See general/ImportService.php for what happens to an article.
 * No session and no cookie.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../general/ImportService.php';

date_default_timezone_set('Europe/Amsterdam');
mb_internal_encoding('UTF-8');

function respondJson(int $status, array $body): never {
  http_response_code($status);
  header('Content-Type: application/json');
  header('X-Content-Type-Options: nosniff');
  die(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  respondJson(405, ['ok' => false, 'error' => 'Only POST requests are allowed']);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (! str_starts_with($contentType, 'application/json')) respondJson(415, ['ok' => false, 'error' => 'The content type must be application/json']);

$input = json_decode(file_get_contents('php://input'), true);
if (! is_array($input)) respondJson(400, ['ok' => false, 'error' => 'The body must be JSON']);

// The articles come to a few hundred kilobytes, and the AI can take a while for each
set_time_limit(300);

try {
  $database = new Database();
  $database->open();
  $database->loadCountries();

  $token = ImportService::findToken($database, (string)($input['token'] ?? ''));
  if (! $token) respondJson(401, ['ok' => false, 'error' => 'Invalid token']);

  $articles = $input['articles'] ?? (isset($input['article']) ? [$input['article']] : null);
  if (! is_array($articles) || ! $articles || ! array_is_list($articles)) respondJson(400, ['ok' => false, 'error' => 'articles must be a list of articles']);
  if (count($articles) > ImportService::MAX_ARTICLES_PER_CALL) {
    respondJson(400, ['ok' => false, 'error' => 'At most ' . ImportService::MAX_ARTICLES_PER_CALL . ' articles per request']);
  }

  $service = new ImportService($database, $token);
  $dryRun = ! empty($input['dryRun']);

  $results = [];
  foreach ($articles as $article) {
    $results[] = is_array($article) ? $service->importArticle($article, $dryRun) : ['status' => 'error', 'message' => 'An article must be an object'];
  }

  respondJson(200, ['ok' => true, 'dryRun' => $dryRun, 'results' => $results]);

} catch (\Throwable $e) {
  error_log('api/import.php: ' . $e->getMessage());
  respondJson(500, ['ok' => false, 'error' => 'Internal error']);
}
