<?php
require_once '../initialize.php';
//require_once '../config.php';
require_once '../general/OpenRouterAIClient.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
//error_reporting(0);

// Set proper headers for JSON response
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['headline']) || !isset($input['articleBody'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: headline and articleBody']);
    exit();
}

$headline = trim($input['headline']);
$articleBody = trim($input['articleBody']);

if (empty($headline) || empty($articleBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'Headline and article body cannot be empty']);
    exit();
}

/**
 * Calculate the score based on criteria results following a tiered system.
 */
function calculateScore($results) {
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

/**
 * Analyze headline using OpenRouter API
 */
function analyzeHeadline($headline, $articleBody) {
    try {
        $openrouterClient = new OpenRouterAIClient();

        $article = (object)[
          'title' => $headline,
          'text' => $articleBody,
        ];
        
        // Use the existing client to make the API call
        $llmOutputContent = $openrouterClient->chatUsingPromptFunction('article_reframe', $article);

        $llmAnalysis = json_decode($llmOutputContent, true);

        $finalAnalysis = [
            'isRelevant' => $llmAnalysis['isRelevant'],
            'originalHeadline' => $headline,
            'criteriaResults' => $llmAnalysis['criteriaResults'] ?? [],
            'score' => calculateScore($llmAnalysis['criteriaResults'] ?? []),
            'improvedHeadline' => $llmAnalysis['improvedHeadline'] ?? ($llmAnalysis['isRelevant'] ? 'Could not generate improved headline.' : ''),
            'changes' => $llmAnalysis['changes'] ?? []
        ];

        return ['analysis' => $finalAnalysis];

    } catch (Exception $e) {
        error_log('Analysis error: ' . $e->getMessage());
        
        return [
            'analysis' => [
                'isRelevant' => false,
                'originalHeadline' => $headline,
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

try {
    $result = analyzeHeadline($headline, $articleBody);
    echo json_encode($result);
} catch (Exception $e) {
    error_log('Fatal error in analyze.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} 