<?php

require_once __DIR__ . '/utils.php';

/**
 * Finds crashes that may be the same crash as one that is being added: the same time, the same country and a place
 * that fits. A crash that is far away is never the same. A crash without a place is not found.
 *
 * The title is not compared: an article that comes out months later is often about the trial, not about the crash.
 * The score is 0 to 5 points and is also the number of stars of the match.
 */
class SimilarCrashes {

  const MAX_RESULTS = 5;
  const MIN_SCORE = 3;
  const MAX_SCORE = 5;
  // A place without a known accuracy is assumed to be exact to this many metres
  const DEFAULT_ACCURACY_METERS = 1000;
  const MIN_RADIUS_KM = 1;
  const MAX_RADIUS_KM = 30;

  /**
   * @param array $crash date (Y-m-d), countryId, latitude, longitude, accuracy (metres), modes (transportation modes of the people)
   * @return array crashes, best match first. Each has id, title, date, distanceKm, locationMatch, exactPlace, sameModes, sameDay, score and stars (1 to 5)
   */
  public static function find(Database $database, array $crash, ?int $excludeCrashId = null, ?int $userId = null, int $minScore = self::MIN_SCORE, int $maxResults = self::MAX_RESULTS): array {
    $date = $crash['date'] ?? null;
    if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return [];

    $countryId = $crash['countryId'] ?? null;
    $latitude = isset($crash['latitude']) && $crash['latitude'] !== '' ? (float)$crash['latitude'] : null;
    $longitude = isset($crash['longitude']) && $crash['longitude'] !== '' ? (float)$crash['longitude'] : null;
    $hasPlace = $latitude !== null && $longitude !== null;
    $accuracy = (int)($crash['accuracy'] ?? 0) ?: self::DEFAULT_ACCURACY_METERS;
    // Unknown (0) says nothing
    $modes = array_values(array_unique(array_filter(array_map('intval', $crash['modes'] ?? []))));
    sort($modes);

    // Candidates: one day before and after. Crashes that are not moderated yet are only seen by the person who added them.
    $sql = "
SELECT c.id, c.title, c.date, c.latitude, c.longitude, c.locationaccuracy
FROM crashes c
WHERE c.date BETWEEN DATE_SUB(:date1, INTERVAL 1 DAY) AND DATE_ADD(:date2, INTERVAL 1 DAY)
  AND (c.countryid = :country OR :country2 IS NULL OR c.countryid IS NULL)
  AND (c.awaitingmoderation = 0 OR c.userid <=> :userid)
  AND c.id <> :exclude
ORDER BY c.date, c.id
LIMIT 500";
    $candidates = $database->fetchAll($sql, [
      ':date1' => $date, ':date2' => $date,
      ':country' => $countryId, ':country2' => $countryId,
      ':userid' => $userId, ':exclude' => $excludeCrashId ?? 0,
    ]);
    if (! $candidates) return [];

    $candidateModes = [];
    $ids = array_map(fn($row) => (int)$row['id'], $candidates);
    $rows = $database->fetchAll(
      'SELECT crashid, transportationmode FROM crashpersons WHERE crashid IN (' . implode(',', $ids) . ')');
    foreach ($rows as $row) {
      if ((int)$row['transportationmode'] > 0) $candidateModes[(int)$row['crashid']][(int)$row['transportationmode']] = true;
    }

    $results = [];
    foreach ($candidates as $candidate) {
      $score = 0;

      $distanceKm = null;
      $locationMatch = null;
      $exactPlace = false;
      if ($hasPlace && $candidate['latitude'] !== null && $candidate['longitude'] !== null) {
        $distanceKm = distanceKilometers($latitude, $longitude, (float)$candidate['latitude'], (float)$candidate['longitude']);
        $candidateAccuracy = (int)$candidate['locationaccuracy'] ?: self::DEFAULT_ACCURACY_METERS;
        $radiusKm = min(self::MAX_RADIUS_KM, max(self::MIN_RADIUS_KM, ($accuracy + $candidateAccuracy) / 1000));

        // Too far apart for the accuracy of both places: another crash
        if ($distanceKm > $radiusKm) continue;

        // Only two exact places say a lot. Two towns that are close to each other say little.
        $locationMatch = true;
        $exactPlace = $accuracy + $candidateAccuracy <= 1000;
        $score += $exactPlace ? 2 : 1;
      }

      $sameModes = null;
      $ownModes = array_keys($candidateModes[(int)$candidate['id']] ?? []);
      sort($ownModes);
      if ($modes && $ownModes) {
        $sameModes = $modes === $ownModes;
        if ($sameModes) $score += 1;
        // Nobody in common: another crash, unless the AI read the article wrong
        elseif (! array_intersect($modes, $ownModes)) $score -= 2;
      }

      // A crash at night or a late report is often one day off
      if ($candidate['date'] === $date) $score += 2;
      else $score += 1;

      if ($score < $minScore) continue;

      $results[] = [
        'id' => (int)$candidate['id'],
        'title' => $candidate['title'],
        'date' => $candidate['date'],
        'distanceKm' => $distanceKm === null ? null : round($distanceKm, 1),
        'locationMatch' => $locationMatch,
        'exactPlace' => $exactPlace,
        'sameModes' => $sameModes,
        'sameDay' => $candidate['date'] === $date,
        'score' => $score,
        'stars' => max(1, min(self::MAX_SCORE, $score)),
      ];
    }

    usort($results, fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['id'] <=> $b['id']);

    return array_slice($results, 0, $maxResults);
  }
}
