<?php

/**
 * Cleans the full text of an article (articles.alltext): everything that is not part of the article is removed
 * and the text gets tidy paragraphs.
 *
 *   $text = ArticleTextCleaner::clean($text, $url);
 *
 * What it does:
 * 1. Tidies the layout: one blank line between paragraphs, no trailing spaces, no non-breaking spaces.
 *    Every line of the original is a paragraph. Lines are never joined.
 * 2. Removes lines that do not belong to the article (WhatsApp tips, newsletter adverts, bylines, photo credits,
 *    dates and comment counts). The rules are in article_text_rules.php.
 * 3. Removes the first paragraph if the text repeats it right after. That is the description of the web page that
 *    the fetch function adds in front of the text. A place name in front of the repeat ("Utrecht - ") is allowed.
 * 4. Removes a paragraph that is the same as the paragraph before it.
 *
 * Cleaning the result again changes nothing.
 */
class ArticleTextCleaner {
  private const MIN_LEAD_LENGTH = 40;

  private static ?array $rules = null;

  public static function clean(string $text, string $url = '', string $sitename = ''): string {
    $paragraphs = self::splitParagraphs($text);
    $paragraphs = self::removeUnwantedLines($paragraphs, self::getSiteRules($url, $sitename));
    $paragraphs = self::removeDuplicateLead($paragraphs);
    $paragraphs = self::removeRepeatedParagraphs($paragraphs);

    return implode("\n\n", $paragraphs);
  }

  /**
   * Every non-empty line is a paragraph. Characters that only take space are normalised.
   */
  private static function splitParagraphs(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Zero-width characters, byte order mark, soft hyphen and control characters (not the line feed and tab)
    $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]|[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

    $paragraphs = [];
    foreach (explode("\n", $text) as $line) {
      // Non-breaking space and tabs become normal spaces. Spaces inside a line are kept as they are, except double spaces.
      $line = preg_replace('/[ \t\x{00A0}\x{2007}\x{202F}]+/u', ' ', $line) ?? $line;
      $line = trim($line);
      if ($line !== '') $paragraphs[] = $line;
    }

    return $paragraphs;
  }

  private static function removeUnwantedLines(array $paragraphs, array $siteRules): array {
    $rules = self::getRules();
    $lineRules = array_merge($rules['general'], $siteRules);

    $result = [];
    $skipNameLine = false;
    foreach ($paragraphs as $paragraph) {
      if ($skipNameLine) {
        $skipNameLine = false;
        // The name after a label like "Geschreven door": short, no full stop at the end
        if ((mb_strlen($paragraph) <= 50) && (! str_ends_with($paragraph, '.')) && (count(explode(' ', $paragraph)) <= 5)) continue;
      }

      if (self::matchesAny($paragraph, $rules['labels'])) {
        $skipNameLine = true;
        continue;
      }

      if (self::matchesAny($paragraph, $lineRules)) continue;

      $result[] = $paragraph;
    }

    return $result;
  }

  private static function matchesAny(string $line, array $patterns): bool {
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $line) === 1) return true;
    }

    return false;
  }

  /**
   * The first paragraph is removed if the text after it starts with the same words.
   * Differences in spaces, quotes and dashes are ignored. A first paragraph that is cut off with "..." counts too.
   */
  private static function removeDuplicateLead(array $paragraphs): array {
    if (count($paragraphs) < 2) return $paragraphs;

    $first = self::normalizeForCompare($paragraphs[0]);
    if (mb_strlen($first) < self::MIN_LEAD_LENGTH) return $paragraphs;

    // A first paragraph that is cut off with ... is a duplicate if the text continues with the words before the ...
    $stem = preg_replace('/\s*(\.\.\.|\[\.\.\.\])$/', '', $first);
    if (mb_strlen($stem) < self::MIN_LEAD_LENGTH) return $paragraphs;

    $rest = self::normalizeForCompare(implode(' ', array_slice($paragraphs, 1)));
    // A place name and dash in front of the repeat: "Veenendaal - Het Ichthus College ..."
    $restWithoutPlace = preg_replace('/^\p{Lu}[\p{L} .\'’-]{1,40}?\s*(\([^)]{1,15}\))?\s*[-–—]\s+/u', '', $rest) ?? $rest;

    if (str_starts_with($rest, $stem) || str_starts_with($restWithoutPlace, $stem)) {
      array_shift($paragraphs);
    }

    return $paragraphs;
  }

  private static function removeRepeatedParagraphs(array $paragraphs): array {
    $result = [];
    $previous = null;
    foreach ($paragraphs as $paragraph) {
      $normalized = self::normalizeForCompare($paragraph);
      if ($normalized === $previous) continue;

      $result[] = $paragraph;
      $previous = $normalized;
    }

    return $result;
  }

  private static function normalizeForCompare(string $text): string {
    $text = strtr($text, [
      "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"',
      "\u{2018}" => "'", "\u{2019}" => "'",
      "\u{2013}" => '-', "\u{2014}" => '-',
      "\u{00A0}" => ' ', "\u{2026}" => '...',
    ]);

    return trim(preg_replace('/\s+/u', ' ', $text));
  }

  private static function getRules(): array {
    if (self::$rules === null) self::$rules = require __DIR__ . '/article_text_rules.php';

    return self::$rules;
  }

  /**
   * The rules for the website of the article. The domain comes from the url, or from the site name if that is a domain.
   */
  private static function getSiteRules(string $url, string $sitename): array {
    $hosts = [];
    $host = parse_url($url, PHP_URL_HOST);
    if (is_string($host)) $hosts[] = $host;
    if (str_contains($sitename, '.')) $hosts[] = $sitename;

    $siteRules = [];
    foreach ($hosts as $host) {
      $host = preg_replace('/^www\./', '', mb_strtolower(trim($host)));
      foreach (self::getRules()['sites'] as $domain => $patterns) {
        if (($host === $domain) || str_ends_with($host, '.' . $domain)) $siteRules = array_merge($siteRules, $patterns);
      }
    }

    return $siteRules;
  }
}
