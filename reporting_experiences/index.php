<?php

require_once '../initialize.php';

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
  getHTMLBeginMain($texts['About_this_site'], '', 'initPageUser') .
  $mainHTML .
  getHTMLEnd();

echo $html;