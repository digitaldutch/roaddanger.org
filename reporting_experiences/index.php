<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';

global $user;
$pageText = $user->translateLongText('reporting_experiences');
$texts = translateArray(['Reporting_experiences']);

$mainHTML = <<<HTML
<div id="main" class="pageInner bgWhite">
  
<div class="pageSubTitle">{$texts['Reporting_experiences']}</div>
  
$pageText
</div>
HTML;

$html =
  HtmlBuilder::getHTMLBeginMain($texts['Reporting_experiences'], '', 'initPageUser') .
  $mainHTML .
  HtmlBuilder::getHTMLEnd();

echo $html;