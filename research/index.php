<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';
require_once 'HtmlResearch.php';

global $database;
global $user;
global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);

if (str_starts_with($uri, '/research/questionnaires/setup')) $pageType = PageType::questionnaireSetup;
else if (str_starts_with($uri, '/research/questionnaires/answer')) $pageType = PageType::answerQuestionnaires;
else if (str_starts_with($uri, '/research/questionnaires')) $pageType = PageType::questionnaireResults;
else if (str_starts_with($uri, '/research/ai_prompt_builder')) $pageType = PageType::ai_prompt_builder;
else if (str_starts_with($uri, '/research/research_uva_2026')) $pageType = PageType::research_uva_2026;
else if (str_starts_with($uri, '/research/ai_tasks')) $pageType = PageType::ai_tasks;
else die('Internal error: Unknown page type');

$htmlEnd = '';
$head = "<script src='/js/main.js?v=$VERSION'></script><script src='/research/research.js?v=$VERSION'></script>";

if ($pageType === PageType::questionnaireSetup) {
  $mainHTML = HtmlResearch::questionnaireSettings();
} else if ($pageType === PageType::answerQuestionnaires) {
  $mainHTML = $user->isModerator()? HtmlResearch::QuestionnaireAnswer() : HtmlBuilder::pageNotModerator();
} else if ($pageType === PageType::questionnaireResults) {
  $head .= "\n<script src='/scripts/d3.v7.js?v=$VERSION'></script>";
  $mainHTML = HtmlResearch::questionnaireResults();
} else if ($pageType === PageType::ai_prompt_builder) {
  $mainHTML = $user->isModerator()? HtmlResearch::pageAIPromptBuilder() : HtmlBuilder::pageNotModerator();
} else if ($pageType === PageType::ai_tasks) {
  $mainHTML = HtmlResearch::page_AI_tasks();
} else if ($pageType === PageType::research_uva_2026) {
  $mainHTML = HtmlResearch::pageResearch_UVA_2026();
}

$html =
  HtmlBuilder::getHTMLBeginMain('Research', $head, 'initResearch') .
  $mainHTML .
  HtmlBuilder::getHTMLEnd($htmlEnd);

echo $html;