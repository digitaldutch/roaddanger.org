<?php
require_once 'HeadlineTerms.php';

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

if (!$input || !isset($input['headline'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: headline']);
    exit();
}

$headline = $input['headline'];
$isDarkBackground = $input['isDarkBackground'] ?? false;
$showTooltips = $input['showTooltips'] ?? true;

try {
    // Get highlighted HTML
    $highlightedHTML = HeadlineTerms::createHighlightedHeadline($headline, $isDarkBackground, $showTooltips);
    
    // Also get the found terms for additional client-side use
    $foundTerms = HeadlineTerms::findHeadlineTerms($headline);
    
    echo json_encode([
        'success' => true,
        'highlightedHTML' => $highlightedHTML,
        'foundTerms' => $foundTerms,
        'termsCount' => count($foundTerms)
    ]);
    
} catch (Exception $e) {
    error_log('Highlight error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to highlight headline',
        'message' => $e->getMessage()
    ]);
} 