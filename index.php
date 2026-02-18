<?php

require_once 'initialize.php';
require_once 'HtmlBuilder.php';

global $VERSION;
global $database;
global $user;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (str_starts_with($uri, '/last_changed'))                    $pageType = PageType::lastChanged;
else if (str_starts_with($uri, '/decorrespondent'))                 $pageType = PageType::deCorrespondent;
else if (str_starts_with($uri, '/research_uva_2026'))               $pageType = PageType::research_uva_2026;
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

$searchFunction = '';
$showButtonAdd = false;
$head = "<script src='/js/main.js?v=$VERSION'></script>" .
  "<script src='/js/Filter.js?v=$VERSION'></script>";

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

[$mainHTML, $showButtonAdd, $head] = match ($pageType) {
  PageType::statisticsGeneral => [HtmlBuilder::pageStatsGeneral(), false, $head],
  PageType::research_uva_2026 => [HtmlBuilder::pageResearch_UVA_2026(), false, $head],
  PageType::childVictims => [HtmlBuilder::pageChildVictims(), true, $head],
  PageType::map => [HtmlBuilder::pageMap(), true, $head],
  PageType::mosaic => [HtmlBuilder::pageMosaic(), true, $head],
  PageType::statisticsHumanizationTest => [HtmlBuilder::pageHumanizationTest(), false, $head],
  PageType::statisticsCrashPartners => [HtmlBuilder::pageStatsCrashPartners(), false, $head],
  PageType::statisticsTransportationModes => [HtmlBuilder::pageStatsTransportationModes(), false, $head],
  PageType::export => [HtmlBuilder::pageExport(), false, $head],
  PageType::recent,
  PageType::lastChanged,
  PageType::deCorrespondent,
  PageType::moderations => [
    HtmlBuilder::pageRecent($pageType),
    true,
    $head . '<script src="/scripts/mark.es6.js"></script>'
  ],
  default => throw new Exception('Unknown page type'),
};

$html =
  HtmlBuilder::getHTMLBeginMain('', $head, 'initMain', $showButtonAdd) .
  $mainHTML .
  HtmlBuilder::getHTMLEnd();

echo $html;