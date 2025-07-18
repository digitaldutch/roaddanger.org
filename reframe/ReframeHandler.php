<?php
require_once '../initialize.php';
require_once '../general/OpenRouterAIClient.php';

//ini_set('display_errors', 1);
//error_reporting(E_ALL);
error_reporting(0);

// Set proper headers for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

class ReframeHandler {
  private $input;

  public function __construct() {
    $this->input = json_decode(file_get_contents('php://input'), true);

    if (empty($this->input['function'])) throw new Exception('No function specified');

    switch ($this->input['function']) {

      case 'analyzeHeadline':
        $response = $this->analyzeHeadline(
          $this->input['headline'] ?? '',
          $this->input['articleBody'] ?? ''
        );
        echo json_encode($response);
        break;

      case 'getArticle':
        $response = $this->getArticle();
        echo json_encode($response);
        break;

      default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid function specified']);
        break;
    }

  }

  private function analyzeHeadline($title, $text): array {

    try {
      // Create unique ID for article title and text
      $cacheId = 'reframe_' . md5($title . '|' . $text);

      require_once '../general/Cache.php';

      // AI responses are cached for 1 hour
      $cacheResponse = Cache::get($cacheId, 3600);
      if ($cacheResponse === null) {
        $openrouterClient = new OpenRouterAIClient();

        $article = (object)[
          'title' => $title,
          'text' => $text,
        ];

        // Use the existing client to make the API call
        $llmOutputContent = $openrouterClient->chatUsingPromptFunction('article_reframe', $article);

        $llmAnalysis = json_decode($llmOutputContent, true);

        $finalAnalysis = [
          'isRelevant' => $llmAnalysis['isRelevant'],
          'originalHeadline' => $title,
          'criteriaResults' => $llmAnalysis['criteriaResults'] ?? [],
          'score' => $this->calculateScore($llmAnalysis['criteriaResults'] ?? []),
          'improvedHeadline' => $llmAnalysis['improvedHeadline'] ?? ($llmAnalysis['isRelevant'] ? 'Could not generate improved headline.' : ''),
          'changes' => $llmAnalysis['changes'] ?? []
        ];

        Cache::set($cacheId, json_encode($finalAnalysis));
      } else {
        $finalAnalysis = json_decode($cacheResponse, true);
      }
      return ['analysis' => $finalAnalysis];

    } catch (Exception $e) {
      error_log('Analysis error: ' . $e->getMessage());

      return [
        'analysis' => [
          'isRelevant' => false,
          'originalHeadline' => $title,
          'score' => 0,
          'criteriaResults' => [
            ['criterionId' => 1, 'passed' => false, 'explanation' => $e->getMessage()],
            ['criterionId' => 2, 'passed' => false, 'explanation' => 'Evaluation stopped due to error.'],
            ['criterionId' => 3, 'passed' => false, 'explanation' => 'Evaluation stopped due to error.'],
          ],
          'improvedHeadline' => 'Error during analysis. Please try again.',
          'changes' => []
        ]
      ];
    }
  }

  private function getArticle(): array {
    try {
      $articleId = $this->input['articleId'];
      if (!$articleId) throw new Exception('No article ID provided');

      $sql = 'SELECT title, alltext FROM articles WHERE id=:id';
      global $database;
      $article = $database->fetchObject($sql, [':id' => $articleId]);
      if (!$article) throw new Exception('Article not found');

      return [
        'title' => $article->title,
        'text' => $article->alltext,
      ];
    } catch (Exception $e) {
      return [
        'error' => $e->getMessage()
      ];
    }
  }

  private function calculateScore($results): int {
    /**
     * Calculate the score based on criteria results following a tiered system.
     */
    if (!$results || count($results) === 0) return 0;

    $criterion1 = null;
    $criterion2 = null;
    $criterion3 = null;

    foreach ($results as $result) {
      if ($result['criterionId'] === 1) $criterion1 = $result;
      if ($result['criterionId'] === 2) $criterion2 = $result;
      if ($result['criterionId'] === 3) $criterion3 = $result;
    }

    if (!$criterion1 || !$criterion1['passed']) return 0;
    if (!$criterion2 || !$criterion2['passed']) return 1;
    if (!$criterion3 || !$criterion3['passed']) return 2;

    return 3;
  }

}

$handler = new ReframeHandler();