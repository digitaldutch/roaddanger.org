<?php

/**
 * Stable public url for the crash data: /api/crashes.json.gz (see the rewrite rule in .htaccess).
 * Sends the daily export as a gzip JSON file. The export is made when the file is older than 24 hours.
 * No session and no login: this is open data and automatic downloads should not get cookies.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../general/utils.php';
require_once __DIR__ . '/../general/ExportFiles.php';

date_default_timezone_set('Europe/Amsterdam');
mb_internal_encoding('UTF-8');

function dieWithPlainError(int $status, string $message, int $retryAfterSeconds = 0): never {
  http_response_code($status);
  if ($retryAfterSeconds > 0) header('Retry-After: ' . $retryAfterSeconds);
  header('Content-Type: text/plain; charset=utf-8');
  die($message);
}

if (! in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
  header('Allow: GET, HEAD');
  dieWithPlainError(405, 'Only GET requests are allowed');
}

try {
  $database = new Database();
  $database->open();

  $export = (new ExportFiles($database))->crashes();
} catch (\Throwable $e) {
  error_log('api/crashes.php: ' . $e->getMessage());
  dieWithPlainError(503, 'The data is being prepared. Try again in a few minutes.', 300);
}

$filename = $export['filename'];
$path = realpath($_SERVER['DOCUMENT_ROOT']) . '/cache/' . $filename;
$modified = filemtime($path);
$etag = '"' . $modified . '-' . filesize($path) . '"';

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . str_replace('.json.gz', '_' . $export['date'] . '.json.gz', $filename) . '"');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
header('ETag: ' . $etag);
// The file changes once a day. Clients can ask If-Modified-Since / If-None-Match to skip the download.
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
if ($ifNoneMatch === $etag || ($ifNoneMatch === '' && $ifModifiedSince !== false && $ifModifiedSince >= $modified)) {
  http_response_code(304);
  exit;
}

header('Content-Length: ' . filesize($path));
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($path);
