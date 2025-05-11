<?php

/**
 * Simple key value cache utility storing strings as in local files.
 * A local cache folder is created in the root if it does not exist.
 * This folder is public. Never store private data in the cache.
 *
 * Load a string. Returns string if maximum 600 seconds old. Null if not existing or too old.
 * $content = Cache::get('key', 600);
 *
 * Save a string:
 * Cache::set('key', $content);
 */
class Cache {

  static private function fileNameFromKey(string $key):string {
    $DOCUMENT_ROOT = realpath($_SERVER["DOCUMENT_ROOT"]);

    return $DOCUMENT_ROOT . '/cache/' . $key;
  }

  static public function get(string $key, int $maxAgeSeconds = 60): string | null {
    $cache_filename = self::fileNameFromKey($key);

    if (! file_exists($cache_filename)) {
      return null;
    } elseif (time() - filemtime($cache_filename) > $maxAgeSeconds) {
      // Delete cache file if too old
      unlink($cache_filename);
      return null;
    } else {

      $fileLock = fopen($cache_filename, 'r');
      flock($fileLock, LOCK_SH);

      $content = file_get_contents($cache_filename);

      flock($fileLock, LOCK_UN);
      fclose($fileLock);

      if ($content !== false) return $content;
    }
    return null;
  }
  static public function set(string $key, string $data): void {
    $cacheFilename = self::fileNameFromKey($key);

    $cacheFolder = dirname($cacheFilename);
    if (! file_exists($cacheFolder)) {
      mkdir($cacheFolder, 0777);
    }

    // Writing to a temp file and renaming allows longer access for readers and the rename is atomic on Linux
    // https://softwareengineering.stackexchange.com/a/332544/185807
    $tempFilename = tempnam($cacheFolder, $key);
    file_put_contents($tempFilename, $data, LOCK_EX);
    rename($tempFilename, $cacheFilename);
  }

}