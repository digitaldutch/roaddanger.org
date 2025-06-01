<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';
require_once 'HtmlResearch.php';

global $database;
global $user;
global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);

if (str_starts_with($uri, '/research/questionnaires/settings')) $pageType = PageType::questionnaireSettings;
else if (str_starts_with($uri, '/research/questionnaires/fill_in')) $pageType = PageType::questionnaireFillIn;
else if (str_starts_with($uri, '/research/questionnaires')) $pageType = PageType::questionnaireResults;
else if (str_starts_with($uri, '/research/ai_prompt_builder')) $pageType = PageType::ai_prompt_builder;
else die('Internal error: Unknown page type');

$htmlEnd = '';
$head = "<script src='/js/main.js?v=$VERSION'></script><script src='/research/research.js?v=$VERSION'></script>";

if ($pageType === PageType::questionnaireSettings) {
  $mainHTML = HtmlResearch::questionnaireSettings();
} else if ($pageType === PageType::questionnaireFillIn) {
  $mainHTML = $user->isModerator()? HtmlResearch::pageFillIn() : HtmlBuilder::pageNotModerator();
} else if ($pageType === PageType::questionnaireResults) {
  $head .= "\n<script src='/scripts/d3.v7.js?v=$VERSION'></script>";
  $mainHTML = HtmlResearch::pageResults();
} else if ($pageType === PageType::ai_prompt_builder) {
  $mainHTML = HtmlResearch::pageAITest();
}

$html =
  HtmlBuilder::getHTMLBeginMain('Research', $head, 'initResearch') .
  $mainHTML .
  HtmlBuilder::getHTMLEnd($htmlEnd);

echo $html;