<?php

require_once __DIR__ . '/ArticleTextCleaner.php';
require_once __DIR__ . '/OpenRouterAIClient.php';

/**
 * Cleans the full text and the title of an article with AI, for what the rules in ArticleTextCleaner miss: a byline or
 * date in a layout that no rule knows, slider and embed texts, links to other articles, a page with more than one
 * article, and a title with the website name, a label or a second headline in it.
 *
 *   $result = ArticleTextAICleaner::clean($text, $title, $url, $sitename);   // ['text' => ..., 'title' => ..., 'removed' => 3]
 *
 * The rules run first. Then the AI gets the paragraphs with a number and says which numbers are not part of the article.
 * It also gives the title without what does not belong in it.
 * The AI never writes text: the result is only paragraphs of the original and a part of the original title (this is
 * checked), so nothing can be reworded or invented.
 * The prompt is in the table ai_prompts, function 'article_text_cleaner'.
 */
class ArticleTextAICleaner {
  // The user prompt of OpenRouterAIClient is limited to 10000 characters, the title and the instructions take some
  private const MAX_LIST_LENGTH = 8500;

  // A paragraph is cut to this length in the list for the AI. The start of a paragraph is enough to see what it is.
  private const PARAGRAPH_LENGTHS = [400, 250, 150, 90, 50];

  /**
   * $ask is for scripts that have their own database connection: it gets the article object (title, sitename, text
   * with the numbered paragraphs) and gives the answer of the AI as a JSON string. Without it the prompt of the
   * website is used.
   *
   * @return array{text: string, title: string, removed: int}
   * @throws Exception
   */
  public static function clean(string $text, string $title, string $url = '', string $sitename = '', ?callable $ask = null): array {
    $text = ArticleTextCleaner::clean($text, $url, $sitename);
    $title = trim($title);
    $paragraphs = ($text === '')? [] : explode("\n\n", $text);

    // With so few paragraphs there is nothing to separate in the text, but the title can still be cleaned
    $cleanText = count($paragraphs) >= 3;
    if (! $cleanText && $title === '') return ['text' => $text, 'title' => $title, 'removed' => 0];

    $article = (object)[
      'title' => $title ?: '(unknown)',
      'sitename' => trim($sitename) ?: '(unknown)',
      'text' => self::makeList($paragraphs),
    ];

    $answer = $ask? $ask($article) : (new OpenRouterAIClient())->chatAboutArticle('article_text_cleaner', $article);
    $answer = json_decode($answer, true);

    $newTitle = self::parseTitle($answer, $title);
    if (! $cleanText) return ['text' => $text, 'title' => $newTitle, 'removed' => 0];

    $remove = self::parseRemove($answer, count($paragraphs));

    $kept = [];
    foreach ($paragraphs as $index => $paragraph) {
      if (! isset($remove[$index + 1])) $kept[] = $paragraph;
    }
    if (count($kept) === 0) throw new \Exception('The AI did not find the text of the article');

    return ['text' => implode("\n\n", $kept), 'title' => $newTitle, 'removed' => count($paragraphs) - count($kept)];
  }

  /**
   * The title from the answer, but only if it is a part of the original title: removing a website name, a label or a
   * second headline gives such a part, rewording does not. Anything else keeps the original title.
   */
  private static function parseTitle(mixed $answer, string $title): string {
    if ($title === '' || ! is_array($answer) || ! is_string($answer['title'] ?? null)) return $title;

    // Separators that are left at the start or the end of the part. Not trim(): it works on bytes and cuts a
    // multibyte character like ‘ in pieces.
    $new = preg_replace('/^[\s|–—\-:·•]+|[\s|–—\-:·•]+$/u', '', $answer['title']);
    if ($new === null) return $title;

    if (mb_strlen($new) < 8 || mb_strlen($new) * 5 < mb_strlen($title)) return $title;
    if (mb_strpos($title, $new) === false) return $title;

    return $new;
  }

  /**
   * "[1] First paragraph", one paragraph per line. Paragraphs are cut so that the list fits in the prompt.
   */
  private static function makeList(array $paragraphs): string {
    if (count($paragraphs) === 0) return '(no text)';

    foreach (self::PARAGRAPH_LENGTHS as $length) {
      $lines = [];
      foreach ($paragraphs as $index => $paragraph) {
        $short = (mb_strlen($paragraph) > $length)? mb_substr($paragraph, 0, $length) . '…' : $paragraph;
        $lines[] = '[' . ($index + 1) . '] ' . $short;
      }

      $list = implode("\n", $lines);
      if (mb_strlen($list) <= self::MAX_LIST_LENGTH) return $list;
    }

    throw new \Exception('The text is too long to clean with AI');
  }

  /**
   * The paragraph numbers to remove, from the ranges in the answer: {"remove": [{"from": 2, "to": 3, "reason": "..."}]}.
   * Numbers outside the text are ignored. An answer that is not understood is an error.
   * @return array<int, true> the numbers as keys
   */
  private static function parseRemove(mixed $answer, int $count): array {
    if (! is_array($answer) || ! is_array($answer['remove'] ?? null)) throw new \Exception('The AI gave no usable answer');

    $remove = [];
    foreach ($answer['remove'] as $range) {
      if (! is_array($range)) continue;

      $from = (int)($range['from'] ?? 0);
      $to = (int)($range['to'] ?? 0);
      for ($number = max(1, $from); $number <= min($count, $to); $number++) $remove[$number] = true;
    }

    return $remove;
  }
}
