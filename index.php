<?php

require_once 'initialize.php';

global $VERSION;
global $database;
global $user;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (strpos($uri, '/stream')                          === 0) $pageType = PageType::stream;
else if (strpos($uri, '/decorrespondent')                 === 0) $pageType = PageType::deCorrespondent;
else if (strpos($uri, '/map')                             === 0) $pageType = PageType::map;
else if (strpos($uri, '/moderations')                     === 0) $pageType = PageType::moderations;
else if (strpos($uri, '/mosaic')                          === 0) $pageType = PageType::mosaic;
else if (strpos($uri, '/child_deaths')                    === 0) $pageType = PageType::childDeaths;
else if (strpos($uri, '/statistics/general')              === 0) $pageType = PageType::statisticsGeneral;
else if (strpos($uri, '/statistics/counterparty')         === 0) $pageType = PageType::statisticsCrashPartners;
else if (strpos($uri, '/statistics/transportation_modes') === 0) $pageType = PageType::statisticsTransportationModes;
else if (strpos($uri, '/statistics')                      === 0) $pageType = PageType::statisticsGeneral;
else if (strpos($uri, '/export')                          === 0) $pageType = PageType::export;
else $pageType = PageType::recent;

$addSearchBar   = false;
$showButtonAdd  = false;
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
  $texts = translateArray(['Statistics', 'General']);

  $mainHTML = <<<HTML
<div id="pageMain">
  <div class="pageInner pageInnerScroll">    
    <div class="pageSubTitle">{$texts['Statistics']} - {$texts['General']}</div>
    
    <div class="panelTableOverflow">
       <table id="tableStatistics" class="dataTable"></table>
      <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
    </div>
    
  </div>
</div>
HTML;
} else if ($pageType === PageType::childDeaths) {

  $showButtonAdd = true;
  $texts = translateArray(['Child_deaths', 'Injury', 'Dead_(adjective)', 'Injured', 'Help_improve_data_accuracy']);
  $intro = $user->translateLongText('child_deaths_info');

  $mainHTML = <<<HTML

<div id="pageMain">

  <div class="pageSubTitle"><img src="/images/child.svg" style="height: 20px; position: relative; top: 2px;"> {$texts['Child_deaths']}</div>
  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="smallFont" style="text-decoration: underline; cursor: pointer" onclick="togglePageInfo();">{$texts['Help_improve_data_accuracy']}</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; max-width: 600px; margin: 10px 0;">
  $intro
</div>

  <div class="searchBar" style="display: flex; padding-bottom: 0;">

    <div class="toolbarItem">
      <span id="filterChildDead" class="menuButton bgDeadBlack" data-tippy-content="{$texts['Injury']}: {$texts['Dead_(adjective)']}" onclick="selectFilterChildDeaths();"></span>      
      <span id="filterChildInjured" class="menuButton bgInjuredBlack" data-tippy-content="{$texts['Injury']}: {$texts['Injured']}" onclick="selectFilterChildDeaths();"></span>      
    </div>
    
  </div>

  <div class="panelTableOverflow">
    <table class="dataTable">
      <tbody id="dataTableBody"></tbody>
    </table>
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
  </div>
  
</div>
  
HTML;

} else if ($pageType === PageType::map) {
  $showButtonAdd = true;
  $addSearchBar  = true;

  $mainHTML = <<<HTML
  <div id="mapMain"></div>
HTML;

} else if ($pageType === PageType::mosaic) {
  $showButtonAdd = true;
  $addSearchBar  = true;
  $mainHTML = <<<HTML
<div id="pageMain">
  <div id="cards"></div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;
} else if ($pageType === PageType::statisticsCrashPartners) {

  $texts = translateArray(['Counterparty_in_crashes', 'Always', 'days', 'the_correspondent_week', 'Custom_period',
    'Help_improve_data_accuracy', 'Child', 'Injury', 'Injured', 'Dead_(adjective)']);
  $intoText = $user->translateLongText('counter_party_info');

  $htmlSearchCountry = getSearchCountryHtml('loadStatistics');
  $htmlSearchPeriod  = getSearchPeriodHtml('loadStatistics');

  $mainHTML = <<<HTML
<div id="pageMain">

  <div style="width: 100%; max-width: 700px;">

  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="pageSubTitleFont">{$texts['Counterparty_in_crashes']}</div>
      <div class="smallFont" style="text-decoration: underline; cursor: pointer" onclick="togglePageInfo();">{$texts['Help_improve_data_accuracy']}</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; margin: 10px 0;">
  $intoText
</div>

  <div id="statistics">
  
    <div class="searchBar" style="display: flex;">

      <div class="toolbarItem">
        <span id="filterStatsInjured" class="menuButton bgInjuredBlack" data-tippy-content="{$texts['Injury']}: {$texts['Injured']}" onclick="selectFilterStats();"></span>      
        <span id="filterStatsChild" class="menuButton bgChild" data-tippy-content="{$texts['Child']}" onclick="selectFilterStats();"></span>      
      </div>
      
      <div class="toolbarItem">$htmlSearchCountry</div>
      $htmlSearchPeriod
    </div>

    <div id="graphPartners" style="position: relative;"></div>
   
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
  </div>
</div>
HTML;

} else if ($pageType === PageType::statisticsTransportationModes) {

  $texts = translateArray(['Statistics', 'Transportation_modes', 'Transportation_mode', 'Child', 'Country',
    'Intoxicated', 'Drive_on_or_fleeing', 'Dead_(adjective)', 'Injured', 'Unharmed', 'Unknown']);

  $htmlSearchCountry = getSearchCountryHtml('loadStatistics');
  $htmlSearchPeriod  = getSearchPeriodHtml('loadStatistics');

  $mainHTML = <<<HTML
<div class="pageInner">
  <div class="pageSubTitle">{$texts['Statistics']} - {$texts['Transportation_modes']}<span class="iconTooltip" data-tippy-content="Dit zijn de cijfers over de ongelukken tot nog toe in de database."></span></div>
  
  <div id="statistics">
  
    <div class="searchBar" style="display: flex;">
      <div class="toolbarItem">
        <span id="filterStatsChild" class="menuButton bgChild" data-tippy-content="{$texts['Child']}" onclick="selectFilterStats();"></span>      
      </div>

      <div class="toolbarItem">$htmlSearchCountry</div>
      $htmlSearchPeriod
      
    </div>

    <table class="dataTable">
      <thead>
        <tr>
          <th style="text-align: left;">{$texts['Transportation_mode']}</th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgDead" data-tippy-content="{$texts['Dead_(adjective)']}"></div> <div class="hideOnMobile">{$texts['Dead_(adjective)']}</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgInjured" data-tippy-content="{$texts['Injured']}"></div> <div  class="hideOnMobile">{$texts['Injured']}</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnharmed" data-tippy-content="{$texts['Unharmed']}"></div> <div  class="hideOnMobile">{$texts['Unharmed']}</div></div></th>
          <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnknown" data-tippy-content="{$texts['Unknown']}"></div> <div  class="hideOnMobile">{$texts['Unknown']}</div></div></th>
          <th style="text-align: right;"><div class="iconSmall bgChild" data-tippy-content="{$texts['Child']}"></div></th>
          <th style="text-align: right;"><div class="iconSmall bgAlcohol" data-tippy-content="{$texts['Intoxicated']}"></div></th>
          <th style="text-align: right;"><div class="iconSmall bgHitRun" data-tippy-content="{$texts['Drive_on_or_fleeing']}"></div></th>
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
  <div class="pageSubTitle">Export</div>
  <div id="export">

    <div class="sectionTitle">Download</div>

    <div>All crash data can be exported in gzip JSON format. The download is refreshed every 24 hours.
    </div> 
    
    <div class="buttonBar" style="justify-content: center; margin-bottom: 30px;">
      <button class="button" style="margin-left: 0; height: auto;" onclick="downloadData();">Download data<br>in gzip JSON formaat</button>
    </div>  
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
    
    <div class="sectionTitle">Data specification</div>
    
    <div class="tableHeader">Persons > transportationmode</div>
    
    <table class="dataTable" style="width: auto; margin: 0 0 20px 0;">
      <thead>
      <tr><th>id</th><th>name</th></tr>
      </thead>
      <tbody id="tbodyTransportationMode"></tbody>
    </table>        

    <div class="tableHeader">Persons > health</div>
    <table class="dataTable" style="width: auto; margin: 0 0 20px 0;">
      <thead>
      <tr><th>id</th><th>name</th></tr>
      </thead>
      <tbody id="tbodyHealth"></tbody>
    </table>
            
  </div>
</div>
HTML;
} else {
  $addSearchBar  = true;
  $showButtonAdd = true;
  $messageHTML   = translateLongText('website_info');

  $title = '';
  switch ($pageType){
    case PageType::stream:          $title = translate('Last_modified_crashes'); break;
    case PageType::deCorrespondent: $title = translate('The_correspondent_week') . '<br>14-20 jan. 2019'; break;
    case PageType::moderations:     $title = translate('Moderations'); break;
    case PageType::recent:          $title = translate('Recent_crashes'); break;
  }

  $introText = "<div id='pageSubTitle' class='pageSubTitle'>$title</div>";

  if (isset($messageHTML) && in_array($pageType, [PageType::recent, PageType::stream, PageType::deCorrespondent, PageType::crash])) {
    $introText .= "<div class='sectionIntro'>$messageHTML</div>";
  }

  $mainHTML = <<<HTML
<div id="pageMain">
  <div class="pageInner">
    $introText
    <div id="cards"></div>
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
  </div>
</div>
HTML;

  $head .= '<script src="/scripts/mark.es6.js"></script>';
}

$html =
  getHTMLBeginMain('', $head, 'initMain', $addSearchBar, $showButtonAdd) .
  $mainHTML .
  getHTMLEnd();

echo $html;