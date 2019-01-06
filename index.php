<?php

require_once 'initialize.php';

global $VERSION;
global $user;

if (stripos($_SERVER['REQUEST_URI'], '/statistieken') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken</div>
  <div class="sectionIntro" style="text-align: center;">Dit zijn de cijfers over de ongelukken tot nog toe in de database.</div>
  <div id="statistics">
    <table id="tableStats" class="dataTable">
      <thead>
        <tr>
          <th style="text-align: left;">Vervoermiddel</th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgDead"></div> <div class="hideOnMobile">Dood</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgInjured"></div> <div  class="hideOnMobile">Gewond</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnharmed"></div> <div  class="hideOnMobile">Ongedeerd</div></div></th>
          <th style="text-align: right;"><div class="iconSmall bgAlcohol" data-tippy-content="Onder invloed van alcohol of drugs"></div></th>
          <th style="text-align: right;"><div class="iconSmall bgHitRun" data-tippy-content="Doorgereden of gevlucht"></div></th>
          <th style="text-align: right;"><div class="iconSmall bgChild" data-tippy-content="Kind"></div></th>
        </tr>
      </thead>  
      <tbody id="tableStatsBody">
        
      </tbody>
    </table>  
  </div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

  $head = <<<HTML
<script src="/js/main.js?v=$VERSION"></script>
HTML;

} else {
  $introText = '';
  if ($_SERVER['REQUEST_URI'] === '/') {
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