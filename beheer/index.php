<?php

require_once '../initialize.php';

global $database;
global $user;
global $VERSION;

if ((! $user->loggedin) || (! $user->isModerator())) {
  $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div style="text-align: center;">U bent geen moderator of beheerder. Log eerst in als moderator of beheerder.</div>
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
HTML;
}

function htmlNoAdmin(){
  return <<<HTML
<div id="main" class="pageInner">
  <div style="text-align: center;">U bent geen beheerder. Log eerst in als beheerder.</div>
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
HTML;
}

if (containsText($_SERVER['REQUEST_URI'], '/mensen')) {
  if (! $user->admin) $mainHTML = htmlNoAdmin();
  else {
    $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div class="pageSubTitle">Beheer - mensen</div>
  
    <table id="tableUsers" class="dataTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Laatst actief</th>
          <th>Permissie</th>
          <th></th>
        </tr>
      </thead>  
      <tbody id="tableBody" onclick="userTableClick(event);">        
      </tbody>
    </table>    
  
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
HTML;
  }
} else if (containsText($_SERVER['REQUEST_URI'], '/beheer/exporteren')) {
  $mainHTML = <<<HTML
<div id="main" class="pageInner bgWhite">
  <div class="pageSubTitle">Beheer - Exporteren</div>
  <div id="export">
    <label>Download laatste 1000 ongelukken en artikelen in JSON formaat<br>
    <div class="buttonBar">
      <button class="button" style="margin-left: 0;" onclick="downloadData();">Download data</button></label>
    </div>  
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
  </div>
</div>
HTML;
} else if (containsText($_SERVER['REQUEST_URI'], '/beheer/opties')) {

  $sql = "SELECT value FROM options WHERE name=:name;";
  $message = $database->fetchSingleValue($sql, [':name' => 'globalMessage']);

  $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div class="pageSubTitle">Beheer - opties</div>
  <div>
    <label for="optionGlobalMessage">Website mededeling (wordt op alle ongeluk pagina's getoond)</label>
    <textarea id="optionGlobalMessage" class="textArea" maxlength="1000">$message</textarea>
    <div class="smallFont">Externe link:  [url=https://www.hetongeluk.nl]Het Ongeluk[/url]<br>
    Interne link:  [url=/overdezesite]Over deze site[/url]<br>
    Paragrafen: Voeg lege regels toe
    </div>
    
    <div class="buttonBar">
      <button class="button" style="margin-left: 0;" onclick="saveOptions();">Opslaan</button>
    </div>
  </div>
</div>
HTML;
}

$htmlEnd = getFormEditUser();
$head = "<script src='/beheer/admin.js?v=$VERSION'></script>";
$html =
  getHTMLBeginMain('Beheer', $head, 'initAdmin') .
  $mainHTML .
  getHTMLEnd($htmlEnd);

echo $html;