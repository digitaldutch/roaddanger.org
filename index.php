<?php

require_once 'initialize.php';

global $VERSION;
global $user;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (strpos($uri, '/stream')                     === 0) $pageType = TPageType::stream;
else if (strpos($uri, '/decorrespondent')            === 0) $pageType = TPageType::deCorrespondent;
else if (strpos($uri, '/moderaties')                 === 0) $pageType = TPageType::moderations;
else if (strpos($uri, '/mozaiek')                    === 0) $pageType = TPageType::mosaic;
else if (strpos($uri, '/statistieken/algemeen')      === 0) $pageType = TPageType::statisticsGeneral;
else if (strpos($uri, '/statistieken/andere_partij') === 0) $pageType = TPageType::statisticsCrashPartners;
else if (strpos($uri, '/statistieken/vervoertypes')  === 0) $pageType = TPageType::statisticsTransportationModes;
else if (strpos($uri, '/exporteren')                 === 0) $pageType = TPageType::export;
else                                                               $pageType = TPageType::recent;

$showCrashMenu = false;
$head = "<script src=\"/js/main.js?v=$VERSION\"></script>";

if (strpos($_SERVER['REQUEST_URI'], '/statistieken/algemeen') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - algemeen</div>

  <div id="statisticsGeneral">
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

} else if (strpos($_SERVER['REQUEST_URI'], '/statistieken/andere_partij') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - Doden, andere partij<span class="iconTooltip" data-tippy-content="Dit zijn de cijfers over de ongelukken tot nog toe in de database."></span></div>
  <div id="statistics">
  
    <div style="margin: 5px 0;">
      <div class="filterElement">
        Vervoertype doden<br>
        <select id="filterVictimTransportationMode" oninput="statsCrashPartnersTransportationModeChange();">
        </select>
      </div>
    </div>

    <table id="tableStats" class="dataTable">
      <thead>
        <tr>
          <th style="text-align: left;">Andere partij</th>
          <th style="text-align: right;">Aantal doden (<span id="crashPartnerTransportationMode"></span>)</th>
          <th style="text-align: right;">Percentage</th>
        </tr>
      </thead>  
      <tbody id="tableStatsBody">
        
      </tbody>
    </table>  
  </div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

} else if (strpos($_SERVER['REQUEST_URI'], '/statistieken') === 0) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - vervoertypes<span class="iconTooltip" data-tippy-content="Dit zijn de cijfers over de ongelukken tot nog toe in de database."></span></div>
  
  <div id="statistics">
  
    <div style="margin: 5px 10px;">
      <div class="filterElement">
        Periode<br>
        <select id="filterStatsPeriod" oninput="loadStatistics();">
          <option value="today">Vandaag</option> 
          <option value="yesterday">Gisteren</option> 
          <option value="7days">7 dagen</option> 
          <option value="decorrespondent">De Correspondent week</option> 
          <option value="30days">30 dagen</option> 
          <option value="all" selected>Alles</option> 
        </select>
      </div>
    </div>

    <table id="tableStats" class="dataTable">
      <thead>
        <tr>
          <th style="text-align: left;">Vervoertype</th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgDead" data-tippy-content="Dood"></div> <div class="hideOnMobile">Dood</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgInjured" data-tippy-content="Gewond"></div> <div  class="hideOnMobile">Gewond</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnharmed" data-tippy-content="Ongedeerd"></div> <div  class="hideOnMobile">Ongedeerd</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnknown" data-tippy-content="Letsel onbekend"></div> <div  class="hideOnMobile">Onbekend</div></div></th>
          <th style="text-align: right;"><div class="iconSmall bgChild" data-tippy-content="Kind"></div></th>
          <th style="text-align: right;"><div class="iconSmall bgAlcohol" data-tippy-content="Onder invloed"></div></th>
          <th style="text-align: right;"><div class="iconSmall bgHitRun" data-tippy-content="Doorrijden/vluchten"></div></th>
        </tr>
      </thead>  
      <tbody id="tableStatsBody">
        
      </tbody>
    </table>  
  </div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

} else if (containsText($_SERVER['REQUEST_URI'], '/exporteren')) {
  $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div class="pageSubTitle">Exporteren</div>
  <div id="export">

    <div class="sectionTitle">Download</div>

    <div>Alle ongeluk data met artikelen en inclusief meta data kan gedownload worden in gzip JSON formaat. Het bestand wordt elke 24 uur ververst.
    Dit export bestand bevat niet de volledige artikel teksten. Voor onderzoekers zijn deze wel beschikbaar. 
    Email ons (<a href="mailto:info@hetongeluk.nl">info@hetongeluk.nl</a>) voor meer informatie als u daarvoor belangstelling heeft.
    </div> 
    <div class="buttonBar">
      <button class="button" style="margin-left: 0;" onclick="downloadData();">Download data</button>
    </div>  
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
    
    <div class="sectionTitle">Data uitleg</div>
    <div class="tableHeader">Persons > transportationmode</div>
    
    <table class="dataTable" style="width: auto; margin: 0 0 20px 0;">
      <thead>
      <tr><th>id</th><th>naam</th></tr>
      </thead>
      <tbody id="tbodyTransportationMode"></tbody>
    </table>        

    <div class="tableHeader">Persons > health</div>
    <table class="dataTable" style="width: auto; margin: 0 0 20px 0;">
      <thead>
      <tr><th>id</th><th>naam</th></tr>
      </thead>
      <tbody id="tbodyHealth"></tbody>
    </table>        
  </div>
</div>
HTML;
} else {
  $showCrashMenu  = true;
  $generalMessage = $database->fetchSingleValue("SELECT value FROM options WHERE name='globalMessage';");
  $messageHTML    = formatMessage($generalMessage);

  $introText = "<div id='pageSubTitle' class='pageSubTitle'>$title</div>";

  if (isset($generalMessage) && in_array($pageType, [TPageType::recent, TPageType::stream, TPageType::deCorrespondent, TPageType::crash])) {
    $introText .= "<div class='sectionIntro'>$messageHTML</div>";
  }

  $mainHTML = <<<HTML
<div class="pageInner">
  $introText
  <div id="cards"></div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

  $head .= '<script src="/scripts/mark.es6.js"></script>';
}

$html =
  getHTMLBeginMain('', $head, 'initMain', $showCrashMenu) .
  $mainHTML .
  getHTMLEnd();

echo $html;