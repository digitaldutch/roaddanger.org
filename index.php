<?php

require_once 'initialize.php';

global $VERSION;
global $user;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (strpos($uri, '/stream')                     === 0) $pageType = PageType::stream;
else if (strpos($uri, '/decorrespondent')            === 0) $pageType = PageType::deCorrespondent;
else if (strpos($uri, '/kaart')                      === 0) $pageType = PageType::map;
else if (strpos($uri, '/moderaties')                 === 0) $pageType = PageType::moderations;
else if (strpos($uri, '/mozaiek')                    === 0) $pageType = PageType::mosaic;
else if (strpos($uri, '/kinddoden')                  === 0) $pageType = PageType::childDeaths;
else if (strpos($uri, '/statistieken/algemeen')      === 0) $pageType = PageType::statisticsGeneral;
else if (strpos($uri, '/statistieken/andere_partij') === 0) $pageType = PageType::statisticsCrashPartners;
else if (strpos($uri, '/statistieken/vervoertypes')  === 0) $pageType = PageType::statisticsTransportationModes;
else if (strpos($uri, '/exporteren')                 === 0) $pageType = PageType::export;
else                                                               $pageType = PageType::recent;

$fullWindow       = false;
$showButtonSearch = false;
$showButtonAdd    = false;
$head = "<script src='/js/main.js?v=$VERSION'></script>";
if ($pageType === PageType::statisticsCrashPartners){
  $head .= "<script src='/scripts/d3.v5.js?v=$VERSION'></script><script src='/js/d3CirclePlot.js?v=$VERSION'></script>";
}

// Open streetmap
//<link rel='stylesheet' href='https://unpkg.com/leaflet@1.3.1/dist/leaflet.css' integrity='sha512-Rksm5RenBEKSKFjgI3a41vrjkw4EVPlJ3+OiI65vTjIdo9brlAacEuKOiQ5OFh7cOI1bkDwLqdLw3Zg0cRJAAQ==' crossorigin=''/>
//<script src='https://unpkg.com/leaflet@1.3.1/dist/leaflet.js' integrity='sha512-/Nsx9X4HebavoBvEBuyp3I7od5tA0UzAxs+j83KgC8PU0kgB4XiK4Lfe4y4cgBtaRJQEIFCW+oC506aPT2L1zw==' crossorigin=''></script>

// Maptiler using mapbox
//  <script src="https://cdn.maptiler.com/ol/v5.3.0/ol.js"></script>
//  <script src="https://cdn.maptiler.com/ol-mapbox-style/v4.3.1/olms.js"></script>
//  <link rel="stylesheet" href="https://cdn.maptiler.com/ol/v5.3.0/ol.css">

// Mapbox
//<link href='https://api.tiles.mapbox.com/mapbox-gl-js/v0.53.1/mapbox-gl.css' rel='stylesheet'>
//<script src='https://api.tiles.mapbox.com/mapbox-gl-js/v0.53.1/mapbox-gl.js'></script>

if (pageWithMap($pageType)) {
  $head .= <<<HTML
<link href='https://api.mapbox.com/mapbox-gl-js/v1.9.0/mapbox-gl.css' rel='stylesheet'>
<script src='https://api.mapbox.com/mapbox-gl-js/v1.9.0/mapbox-gl.js'></script>
HTML;
}

if (pageWithEditMap($pageType)) {
  $head .= <<<HTML
<script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.4.2/mapbox-gl-geocoder.min.js"></script>
<link href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.4.2/mapbox-gl-geocoder.css" type="text/css" rel="stylesheet">
HTML;
}


if ($pageType === PageType::statisticsGeneral) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - algemeen</div>

  <div id="main">
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;
} else if ($pageType === PageType::childDeaths) {

  $showButtonAdd = true;

  $mainHTML = <<<HTML
<div class="pageInner">

  <div class="pageSubTitle"><img src="/images/child.svg" style="height: 20px; position: relative; top: 2px;"> Kinddoden</div>
  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="smallFont" style="text-decoration: underline; cursor: pointer" onclick="togglePageInfo();">Zo help je de representativiteit van deze tabel te verbeteren.</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; margin: 10px 0;">
In deze live-tabel zie je hoeveel kinderen er bij verkeersongevallen zijn omgekomen en op <a href="/">deze website</a> zijn toegevoegd.
Dit is een onvolledige tabel die representatiever wordt naarmate er meer berichten worden toegevoegd. <a href="/overdezesite">Zo help je mee</a>.   
</div>

  <div class="searchBar" style="display: flex; padding-bottom: 0;">

    <div class="toolbarItem">
      <span id="filterChildDead" class="menuButton bgDeadBlack" data-tippy-content="Letsel: Dood" onclick="selectFilterChildDeaths();"></span>      
      <span id="filterChildInjured" class="menuButton bgInjuredBlack" data-tippy-content="Letsel: Gewond" onclick="selectFilterChildDeaths();"></span>      
    </div>
    
  </div>

  <div id="main">
    <div class="scrollTableWrapper">
      <table class="dataTable">
        <tbody id="dataTableBody"></tbody>
      </table>
    </div>
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

} else if ($pageType === PageType::map) {
  $fullWindow = true;

  $mainHTML = <<<HTML
  <div id="mapMain"></div>
HTML;

} else if ($pageType === PageType::statisticsCrashPartners) {
  $mainHTML = <<<HTML
<div class="pageInner">

  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="pageSubTitleFont">Doden in het verkeer en hun tegenpartij</div>
      <div class="smallFont" style="text-decoration: underline; cursor: pointer" onclick="togglePageInfo();">Zo help je de representativiteit van deze tabel te verbeteren.</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; margin: 10px 0;">
In deze live-tabel zie je welke partijen en tegenpartijen betrokken zijn bij verkeersongevallen die het nieuws haalden en op <a href="/">deze website</a> zijn toegevoegd. 
Je kunt op de cijfers doorklikken om naar de nieuwsberichten te gaan.<br><br>

Dit is een onvolledige live grafiek die representatiever wordt naarmate er meer berichten worden toegevoegd. <a href="/overdezesite">Zo help je mee</a>. 
Een tabel op basis van de eveneens onvolledige politiestatistieken over het jaar 2017 vind je <a href="https://twitter.com/tverka/status/1118898388039348225">hier</a>. Bron: swov/de Correspondent.  
</div>

  <div id="statistics">
  
    <div class="searchBar" style="display: flex;">

      <div class="toolbarItem">
        <span id="filterStatsChild" class="menuButton bgChild" data-tippy-content="Kind" onclick="selectFilterChild();"></span>      
      </div>

      <div class="toolbarItem">
        <select id="filterStatsPeriod" class="searchInput" oninput="loadStatistics();" data-tippy-content="Periode">
          <option value="all" selected>Altijd</option> 
          <option value="7days">7 dagen</option> 
          <option value="30days">30 dagen</option> 
          <option value="decorrespondent">De Correspondent week</option> 
          <option value="2019">2019</option> 
          <option value="2020">2020</option>
          <option value="custom">Handmatige periode</option>          
        </select>
      </div>
      
      <input id="filterStatsDateFrom" oninput="loadStatistics();" class="searchInput toolbarItem" type="date" data-tippy-content="Vanaf">
      <input id="filterStatsDateTo" oninput="loadStatistics();"  class="searchInput toolbarItem" type="date" data-tippy-content="Tot en met">  
    </div>

    <div id="graphPartners" style="position: relative;"></div>
   
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

} else if ($pageType === PageType::statisticsTransportationModes) {
  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">Statistieken - vervoertypes<span class="iconTooltip" data-tippy-content="Dit zijn de cijfers over de ongelukken tot nog toe in de database."></span></div>
  
  <div id="statistics">
  
    <div class="searchBar" style="display: flex;">

      <div class="toolbarItem">
        <span id="filterStatsChild" class="menuButton bgChild" data-tippy-content="Kind" onclick="selectFilterChild();"></span>      
      </div>

      <div class="toolbarItem">
        <select id="filterStatsPeriod" class="searchInput" oninput="loadStatistics();" data-tippy-content="Periode">
          <option value="all" selected>Altijd</option> 
          <option value="today">Vandaag</option> 
          <option value="yesterday">Gisteren</option> 
          <option value="7days">7 dagen</option> 
          <option value="30days">30 dagen</option> 
          <option value="decorrespondent">De Correspondent week</option> 
          <option value="2019">2019</option> 
          <option value="2020">2020</option> 
          <option value="custom">Handmatige periode</option> 
        </select>
      </div>
      
      <input id="filterStatsDateFrom" oninput="loadStatistics();" class="searchInput toolbarItem" type="date" data-tippy-content="Vanaf">
      <input id="filterStatsDateTo" oninput="loadStatistics();" class="searchInput toolbarItem" type="date" data-tippy-content="Tot en met">  
     
    </div>

    <table class="dataTable">
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

} else if ($pageType === PageType::export) {
  $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div class="pageSubTitle">Exporteren</div>
  <div id="export">

    <div class="sectionTitle">Download</div>

    <div>Alle ongeluk data met artikelen en inclusief meta data kan gedownload worden in gzip JSON formaat. Het bestand wordt elke 24 uur ververst.
    Dit export bestand bevat niet de volledige artikel teksten. Voor onderzoekers zijn deze wel beschikbaar. 
    Email ons (<a href="mailto:info@hetongeluk.nl">info@hetongeluk.nl</a>) als u daar belangstelling voor heeft.
    </div> 
    <div class="buttonBar" style="justify-content: center; margin-bottom: 30px;">
      <button class="button" style="margin-left: 0; height: auto;" onclick="downloadData();">Download alle data<br>in gzip JSON formaat</button>
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
    
    <div id="dataCorrespondent" class="sectionTitle">De Correspondent week</div>
    <div class="buttonBar" style="margin-bottom: 30px;">
      <button class="button" style="margin-left: 0; height: auto;" onclick="downloadCorrespondentData();">Download De Correspondent week ongelukken<br>in *.csv formaat</button>
      <br>
      <button class="button" style="height: auto;" onclick="downloadCorrespondentDataArticles();">Download De Correspondent week artikelen<br>in *.csv formaat</button>
    </div>  
    <div id="spinnerDownloadDeCorrespondentData" class="spinnerLine"><img src="/images/spinner.svg"></div>
        
  </div>
</div>
HTML;
} else {
  $showButtonSearch = true;
  $showButtonAdd    = true;
  $generalMessage   = $database->fetchSingleValue("SELECT value FROM options WHERE name='globalMessage';");
  $messageHTML      = formatMessage($generalMessage);

  $introText = "<div id='pageSubTitle' class='pageSubTitle'></div>";

  if (isset($generalMessage) && in_array($pageType, [PageType::recent, PageType::stream, PageType::deCorrespondent, PageType::crash])) {
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
  getHTMLBeginMain('', $head, 'initMain', $showButtonSearch, $showButtonAdd, $fullWindow) .
  $mainHTML .
  getHTMLEnd();

echo $html;