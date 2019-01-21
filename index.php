<?php

require_once 'initialize.php';

global $VERSION;
global $user;

$showCrashMenu = false;
if (strpos($_SERVER['REQUEST_URI'], '/statistieken/algemeen') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - algemeen</div>

  <div id="statisticsGeneral">
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

  $head = "<script src=\"/js/main.js?v=$VERSION\"></script>";

} else if (strpos($_SERVER['REQUEST_URI'], '/statistieken') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - vervoermiddelen</div>
  <div class="sectionIntro" style="text-align: center;">Dit zijn de cijfers over de ongelukken tot nog toe in de database.</div>
  <div id="statistics">
  
    <div style="margin: 5px 10px;">
      <div class="filterElement">
        Periode<br>
        <select id="filterStatsPeriod" oninput="loadStatistics();">
          <option value="today">Vandaag</option> 
          <option value="yesterday">Gisteren</option> 
          <option value="7days">7 dagen</option> 
          <option value="30days">30 dagen</option> 
          <option value="all" selected>Alles</option> 
        </select>
      </div>
    </div>

    <table id="tableStats" class="dataTable">
      <thead>
        <tr>
          <th style="text-align: left;">Vervoermiddel</th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgDead" data-tippy-content="Dood"></div> <div class="hideOnMobile">Dood</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgInjured" data-tippy-content="Gewond"></div> <div  class="hideOnMobile">Gewond</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnharmed" data-tippy-content="Ongedeerd"></div> <div  class="hideOnMobile">Ongedeerd</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnknown" data-tippy-content="Letsel onbekend"></div> <div  class="hideOnMobile">Onbekend</div></div></th>
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

  $head = "<script src=\"/js/main.js?v=$VERSION\"></script>";

} else {
  $showCrashMenu = true;
  $generalMessage = $database->fetchSingleValue("SELECT value FROM options WHERE name='globalMessage';");
  $messageHTML = formatMessage($generalMessage);

  if (isset($generalMessage)) {
    $introText .= <<<HTML
    <div class="sectionIntro">$messageHTML</div>
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
  getHTMLBeginMain('', $head, 'initMain', $showCrashMenu) .
  $mainHTML .
  getHTMLEnd();

echo $html;