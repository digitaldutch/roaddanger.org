<?php

require_once '../initialize.php';

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
  getHTMLBeginMain($texts['About_this_site'], '', 'initPageUser') .
  $mainHTML .
  getHTMLEnd();

echo $html;