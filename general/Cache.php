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
 *
 * The cache is cleaned automatically when calling set(), but only once an hour
 */
class Cache {
  private const string lastCleanUpFileName = '.lastCleanup';


  static private function getCacheFolder():string {
    $DOCUMENT_ROOT = realpath($_SERVER["DOCUMENT_ROOT"]);

    return $DOCUMENT_ROOT . '/cache/';
  }
  static private function fileNameFromKey(string $key):string {
    return self::getCacheFolder() . $key;
  }

  /**
   * True if the cache has a file for this key that is not older than the maximum age.
   * Use this instead of get() for large files that are only downloaded and do not need to be loaded into memory.
   */
  static public function exists(string $key, int $maxAgeSeconds = 60): bool {
    $cache_filename = self::fileNameFromKey($key);

    return file_exists($cache_filename) && (time() - filemtime($cache_filename) <= $maxAgeSeconds);
  }

  /**
   * Time (unix timestamp) the cache file for this key was saved. Null if there is no file.
   */
  static public function modifiedTime(string $key): int | null {
    $cache_filename = self::fileNameFromKey($key);

    return file_exists($cache_filename)? filemtime($cache_filename) : null;
  }

  /**
   * Takes an exclusive lock for this key, without waiting. Returns a lock handle, or null if somebody else has the lock.
   * Release it with unlock(). The operating system also releases it when the script ends, so a crash can not leave a lock behind.
   * Use it so that only one request at a time creates something that is expensive to make.
   */
  static public function tryLock(string $key) {
    $cacheFolder = self::getCacheFolder();
    if (!file_exists($cacheFolder)) mkdir($cacheFolder, 0777);

    $lockFilename = self::fileNameFromKey($key) . '.lock';
    $handle = fopen($lockFilename, 'c');
    if ($handle === false) return null;

    if (! flock($handle, LOCK_EX | LOCK_NB)) {
      fclose($handle);
      return null;
    }

    // Keeps the lock file young, so cleanCache() does not remove it while somebody uses it
    touch($lockFilename);

    return $handle;
  }

  static public function unlock($handle): void {
    flock($handle, LOCK_UN);
    fclose($handle);
  }

  static public function get(string $key, int $maxAgeSeconds = 60): string | null {
    $cache_filename = self::fileNameFromKey($key);

    if (! file_exists($cache_filename)) {
      return null;
    } elseif (time() - filemtime($cache_filename) > $maxAgeSeconds) {
      // Delete the cache file if too old
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
    // Clean cache before saving new data
    self::cleanCache();

    $cacheFolder = self::getCacheFolder();
    if (!file_exists($cacheFolder)) {
        mkdir($cacheFolder, 0777);
    }

    $cacheFilename = self::fileNameFromKey($key);

    // Writing to a temp file and renaming allows longer access for readers, and the rename is atomic on Linux
    $tempFilename = tempnam($cacheFolder, $key);
    file_put_contents($tempFilename, $data, LOCK_EX);
    // tempnam() creates a file only the owner can read. The web server must be able to send the file to a browser.
    chmod($tempFilename, 0644);
    rename($tempFilename, $cacheFilename);
}

  static private function getLastCleanupFile(): string {
    return self::getCacheFolder() . self::lastCleanUpFileName;
  }

  static private function getLastCleanupTime(): int {
    $file = self::getLastCleanupFile();
    return file_exists($file) ? (int)file_get_contents($file) : 0;
  }

  static private function saveLastCleanupTime(): void {
    file_put_contents(self::getLastCleanupFile(), time());
  }


  /**
   * Cleans old cache files.
   * Is called from set() function.
   * We should call it from a crontab if the cache grows too large as it then may slow down the set() function call.
   */
  static public function cleanCache(int $maxAgeHours = 24): void {
    $lastCleanup = self::getLastCleanupTime();

    // Only run cleanup once per hour
    if (time() - $lastCleanup < 3600) return;

    $cacheFolder = self::getCacheFolder();
    if (!file_exists($cacheFolder)) return;

    $maxAgeSeconds = $maxAgeHours * 3600;
    $cutoffTime = time() - $maxAgeSeconds;

    foreach (new DirectoryIterator($cacheFolder) as $fileInfo) {
      if ($fileInfo->isDot()) continue;
      if ($fileInfo->getFilename() === self::lastCleanUpFileName) continue;

      if ($fileInfo->getMTime() < $cutoffTime) {
        unlink($fileInfo->getPathname());
      }
    }

    self::saveLastCleanupTime();
  }

}