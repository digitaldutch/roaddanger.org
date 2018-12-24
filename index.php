<?php

require_once 'initialize.php';

global $VERSION;
global $user;

if (stripos($_SERVER['REQUEST_URI'], '/statistieken') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken</div>
  <div class="sectionIntro" style="text-align: center;">Dit zijn de cijfers over de ongelukken tot nog toe in de database.</div>
  <div id="statistics"></div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

  $head = <<<HTML
<script src="/js/main.js?v=$VERSION"></script>
HTML;

} else {
  $introText = '';
  if (stripos($_SERVER['REQUEST_URI'], '/moderaties') !== 0) {
    $introText = <<<HTML
    <div class="sectionIntro">Op deze site verzamelen we nieuwsberichten over verkeersongelukken uit Nederlandse media en nieuwswebsites.
    Meedoen? Registreer jezelf via het poppetje rechts bovenin en voeg daarna je bericht toe met het plusje.
    Meer uitleg vind je in de <a href="/overdezesite">Over deze site</a> pagina.
  </div>
HTML;
  }

  $mainHTML = <<<HTML
<div class="pageInner">
  $introText
  <div id="cards"></div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

  $head = <<<HTML
<script src="/scripts/mark.es6.js"></script>
<script src="/js/main.js?v=$VERSION"></script>
HTML;
}


$html =
  getHTMLBeginMain('', $head, 'initMain', true) .
  $mainHTML .
  getHTMLEnd();

echo $html;