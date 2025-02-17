<?php

require_once 'initialize.php';
require_once 'HtmlBuilder.php';

global $VERSION;
global $database;
global $user;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (str_starts_with($uri, '/last_changed'))                    $pageType = PageType::lastChanged;
else if (str_starts_with($uri, '/decorrespondent'))                 $pageType = PageType::deCorrespondent;
else if (str_starts_with($uri, '/map'))                             $pageType = PageType::map;
else if (str_starts_with($uri, '/moderations'))                     $pageType = PageType::moderations;
else if (str_starts_with($uri, '/mosaic'))                          $pageType = PageType::mosaic;
else if (str_starts_with($uri, '/child_victims'))                   $pageType = PageType::childVictims;
else if (str_starts_with($uri, '/statistics/general'))              $pageType = PageType::statisticsGeneral;
else if (str_starts_with($uri, '/statistics/media_humanization'))   $pageType = PageType::statisticsHumanizationTest;
else if (str_starts_with($uri, '/statistics/counterparty'))         $pageType = PageType::statisticsCrashPartners;
else if (str_starts_with($uri, '/statistics/transportation_modes')) $pageType = PageType::statisticsTransportationModes;
else if (str_starts_with($uri, '/statistics'))                      $pageType = PageType::statisticsGeneral;
else if (str_starts_with($uri, '/export'))                          $pageType = PageType::export;
else $pageType = PageType::recent;

$showFullHeaderTitle = true;
$searchFunction = '';
$showButtonAdd = false;
$head = "<script src='/js/main.js?v=$VERSION'></script>";

if ($pageType === PageType::statisticsCrashPartners) {
  $head .= "<script src='/scripts/d3.v7.js?v=$VERSION'></script>
<script src='/js/d3CirclePlot.js?v=$VERSION'></script>";
} elseif (in_array($pageType, [PageType::recent, PageType::statisticsHumanizationTest])) {
  $head .= "<script src='/scripts/d3.v7.js?v=$VERSION'></script>
<script src='/scripts/plot.js?v=$VERSION'></script>";
}

// Open streetmap
//<link rel='stylesheet' href='https://unpkg.com/leaflet@1.3.1/dist/leaflet.css' integrity='sha512-Rksm5RenBEKSKFjgI3a41vrjkw4EVPlJ3+OiI65vTjIdo9brlAacEuKOiQ5OFh7cOI1bkDwLqdLw3Zg0cRJAAQ==' crossorigin=''/>
//<script src='https://unpkg.com/leaflet@1.3.1/dist/leaflet.js' integrity='sha512-/Nsx9X4HebavoBvEBuyp3I7od5tA0UzAxs+j83KgC8PU0kgB4XiK4Lfe4y4cgBtaRJQEIFCW+oC506aPT2L1zw==' crossorigin=''></script>

// Maptiler using mapbox
//  <script src="https://cdn.maptiler.com/ol/v5.3.0/ol.js"></script>
//  <script src="https://cdn.maptiler.com/ol-mapbox-style/v4.3.1/olms.js"></script>
//  <link rel="stylesheet" href="https://cdn.maptiler.com/ol/v5.3.0/ol.css">

// Mapbox tiles
//<link href='https://api.tiles.mapbox.com/mapbox-gl-js/v0.53.1/mapbox-gl.css' rel='stylesheet'>
//<script src='https://api.tiles.mapbox.com/mapbox-gl-js/v0.53.1/mapbox-gl.js'></script>

if (pageWithMap($pageType)) {
  $mapbox_js = MAPBOX_GL_JS;
  $mapbox_css = MAPBOX_GL_CSS;
  $head .= <<<HTML
<script src="$mapbox_js"></script>
<link href="$mapbox_css" type="text/css" rel="stylesheet">
HTML;
}

if (pageWithEditMap($pageType)) {
  $mapbox_geocoder_js = MAPBOX_GEOCODER_JS;
  $mapbox_geocoder_css = MAPBOX_GEOCODER_CSS;
    $head .= <<<HTML
<script src="$mapbox_geocoder_js"></script>
<link href="$mapbox_geocoder_css" type="text/css" rel="stylesheet">
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
      <div id="spinnerLoad"><img src="/images/spinner.svg" alt="spinner"></div>
    </div>
    
  </div>
</div>
HTML;
} else if ($pageType === PageType::childVictims) {

  $showButtonAdd = true;

  $mainHTML = HtmlBuilder::pageChildVictims();

} else if ($pageType === PageType::map) {
  $showButtonAdd = true;
  $searchFunction = 'searchCrashes';

  $mainHTML = '<div id="mapMain"></div>';

} else if ($pageType === PageType::mosaic) {
  $showButtonAdd = true;
  $searchFunction = 'searchCrashes';
  $mainHTML = HtmlBuilder::pageMosaic();

} else if ($pageType === PageType::statisticsHumanizationTest) {
  $mainHTML = HtmlBuilder::pageHumanizationTest();

} else if ($pageType === PageType::statisticsCrashPartners) {
  $mainHTML = HtmlBuilder::pageStatsCrashPartners();

} else if ($pageType === PageType::statisticsTransportationModes) {
  $mainHTML = HtmlBuilder::pageStatsTransportationModes();

} else if ($pageType === PageType::export) {
  $mainHTML = HtmlBuilder::pageExport();

} else {
  $searchFunction = 'searchCrashes';
  $showButtonAdd = true;
  $showFullHeaderTitle = false;
  $websiteInfo = translateLongText('website_info');

  $title = '';
  switch ($pageType){
    case PageType::lastChanged: $title = translate('Last_modified_crashes'); break;
    case PageType::deCorrespondent: $title = translate('The_correspondent_week') . '<br>14-20 jan. 2019'; break;
    case PageType::moderations: $title = translate('Moderations'); break;
    case PageType::recent: $title = translate('Recent_crashes') . '<span id="countryName"></span>'; break;
  }

  $pageTitle = WEBSITE_NAME;
  $introText = "<div id='pageSubTitle' class='pageSubTitle'>$title</div>";

  if (isset($websiteInfo) && in_array($pageType, [PageType::recent, PageType::lastChanged, PageType::deCorrespondent, PageType::crash])) {
    $readMore = translate('Read_more');
    $introText .= "<div id='sectionIntro' class='sectionIntro sectionCollapsed'>$websiteInfo</div>" .
      "<div id='introReadMore' class='readMore' onclick='showFullIntro();'>$readMore</div>";
  }

  $mainHTML = <<<HTML
<div id="pageMain">
  <div class="pageInner">
    <a id="largeTitle" href="/">$pageTitle</a>
    $introText
    <div id="featuredGraph"></div>
    <div id="cards"></div>
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
  </div>
</div>
HTML;

  $head .= '<script src="/scripts/mark.es6.js"></script>';
}

$html =
  HtmlBuilder::getHTMLBeginMain('', $head, 'initMain', $searchFunction,
    $showButtonAdd, $showFullHeaderTitle) .
  $mainHTML .
  HtmlBuilder::getHTMLEnd();

echo $html;