<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';

global $user;
$aboutUsText = $user->translateLongText('about_us');
$texts = translateArray(['About_this_site']);

$mainHTML = <<<HTML
<div id="main" class="pageInner bgWhite">
  
<div class="pageSubTitle">{$texts['About_this_site']}</div>
  
$aboutUsText
</div>
HTML;

$html =
  HtmlBuilder::getHTMLBeginMain($texts['About_this_site'], '', 'initPageUser') .
  $mainHTML .
  HtmlBuilder::getHTMLEnd();

echo $html;