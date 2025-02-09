<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';
require_once 'HtmlResearch.php';

global $database;
global $user;
global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (str_starts_with($uri, '/research/questionnaires/options')) $pageType = PageType::questionnaireOptions;
else if (str_starts_with($uri, '/research/questionnaires/fill_in')) $pageType = PageType::questionnaireFillIn;
else if (str_starts_with($uri, '/research/questionnaires')) $pageType = PageType::questionnaireResults;
else die('Internal error: Unknown page type');

$htmlEnd = '';
$head = "<script src='/js/main.js?v=$VERSION'></script><script src='/research/research.js?v=$VERSION'></script>";

if ($pageType === PageType::questionnaireOptions) {
  $mainHTML = HtmlResearch::pageSettings();
} else if ($pageType === PageType::questionnaireFillIn) {
  $mainHTML = $user->isModerator()? HtmlResearch::pageFillIn() : HtmlBuilder::pageNotModerator();
} else if ($pageType === PageType::questionnaireResults) {
  $head .= "\n<script src='/scripts/d3.v7.js?v=$VERSION'></script>";
  $mainHTML = HtmlResearch::pageResults();
}

$html =
  HtmlBuilder::getHTMLBeginMain('Research', $head, 'initResearch') .
  $mainHTML .
  HtmlBuilder::getHTMLEnd($htmlEnd);

echo $html;