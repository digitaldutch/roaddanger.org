<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';

global $database;
global $user;
global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (str_starts_with($uri, '/admin/humans')) $pageType = PageType::humans;
else if (str_starts_with($uri, '/admin/translations')) $pageType = PageType::translations;
else if (str_starts_with($uri, '/admin/longtexts')) $pageType = PageType::longTexts;
else die('Internal error: Unknown page type');

function htmlNoAdmin(){
  return <<<HTML
<div id="main" class="pageInner">
  <div style="text-align: center;">You are not an administrator. Log in as an administrator.</div>
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
HTML;
}

function getFormNewTranslation() {
  $texts = translateArray(['Save', 'Cancel', 'Id', 'English_text']);
  return <<<HTML
<div id="formNewTranslation" class="popupOuter">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="saveNewTranslation(); return false;">

    <div class="popupHeader">New translation item</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>
    
    <div class="notice" style="margin: 10px 0;">To be used by developers only if translation id's have been added to the code.</div>
    
    <label>{$texts['Id']}
    <input id="newTranslationId" class="inputForm" type="text"></label>
    
    <label>{$texts['English_text']}
    <input id="newTranslationEnglishText" class="inputForm" type="text"></label>
       
    <div class="popupFooter">
      <input type="submit" class="button" style="margin-left: 0;" value="{$texts['Save']}">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>
    
  </form>
</div>  
HTML;
}

$htmlEnd = '';
$head = "<script src='/admin/admin.js?v=$VERSION'></script>";

if ((! $user->loggedIn) || (! $user->isModerator())) {
  $mainHTML = HtmlBuilder::pageNotModerator();
} else {

  if ($pageType === PageType::humans) {
    if (! $user->admin) $mainHTML = htmlNoAdmin();
    else {

      $texts = translateArray(['Admin', 'Humans', 'Id', 'Name', 'Last_active', 'Permission', 'Articles', 'Registered']);

      $mainHTML = <<<HTML
<div id="main" class="scrollPage">
  <div class="pageSubTitle">{$texts['Admin']} - {$texts['Humans']}</div>
  
  <div class="panelTableOverflow">
    <table id="tableData" class="dataTable tableWhiteHeader noWrap">
      <thead>
        <tr>
          <th>{$texts['Id']}</th>
          <th>{$texts['Name']}</th>
          <th>{$texts['Last_active']}</th>
          <th>{$texts['Permission']}</th>
          <th>{$texts['Articles']}</th>
          <th>{$texts['Registered']}</th>
          <th></th>
        </tr>
      </thead>  
      <tbody id="tableBody" onclick="tableDataClick(event);" ondblclick="adminEditUser();">        
      </tbody>
    </table>    
    <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
  </div>
  
</div>
HTML;
    }

    $htmlEnd = HtmlBuilder::getFormEditUser();
  } else if ($pageType === PageType::translations) {

    $languages       = $database->fetchAll("SELECT id, name FROM languages ORDER BY name;");
    $languageOptions = '';
    foreach ($languages as $language) {
      $selected = $language['id'] === $user->languageId? 'selected' : '';
      $languageOptions .= "<option value='{$language['id']}' {$selected}>{$language['name']}</option>";
    }

    $texts  = translateArray(['Translations', 'Translation', 'Admin', 'Id', 'English', 'New', 'Delete', 'Save']);

    $mainHTML = <<<HTML
<div id="main" class="pageInner scrollPage" style="max-width: fit-content;">
  <div class="pageSubTitle">{$texts['Admin']} - {$texts['Translations']}</div>

  <div class="smallFont" style="margin-bottom: 10px; text-align: center;">Help making this website more accessible by translating these texts into your own language.</div>

  <div style="margin-bottom: 5px;">
    <select id="selectLanguage" class="searchInput" oninput="changeUserLanguage();">$languageOptions</select>

    <button class="button" onclick="saveTranslations();">{$texts['Save']}</button>
    <button class="button buttonGray" onclick="newTranslation();" data-inline-admin>{$texts['New']}</button>
    <button class="button buttonRed" onclick="deleteTranslation();" data-inline-admin>{$texts['Delete']}</button>
  </div>

  <div class="panelTableOverflow">
    <table id="tableData" class="dataTable tableWhiteHeader" style="user-select: text;">
      <thead>
        <tr><th>{$texts['Id']}</th><th>{$texts['English']}</th><th>{$texts['Translation']} <span id="translationLanguage"></span></th></tr>
      </thead>
      <tbody id="tableBody" onclick="tableDataClick(event);">    
     </tbody>
    </table>  
  </div>
  
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>

</div>
HTML;

    $htmlEnd = getFormNewTranslation();

  } else if ($pageType === PageType::longTexts) {

    $languages = $database->fetchAll("SELECT id, name FROM languages ORDER BY name;");
    $languageOptions = '';
    foreach ($languages as $language) {
      $selected = $language['id'] === $user->languageId? 'selected' : '';
      $languageOptions .= "<option value='{$language['id']}' {$selected}>{$language['name']}</option>";
    }

    $longTexts = $database->fetchAll("SELECT id FROM longtexts WHERE language_id='EN' ORDER BY id;");
    $longTextOptions = '<option value="">[select long text]</option>';
    foreach ($longTexts as $longText) {
      $longTextOptions .= "<option value='{$longText['id']}'>{$longText['id']}</option>";
    }

    $texts = translateArray(['Long_texts', 'Translation', 'Admin', 'Id', 'English', 'New', 'Delete', 'Save']);

    $head .= "\n<script src='/scripts/marked.min.js'></script>";

    $mainHTML = <<<HTML
<div id="pageMain">
  <div class="pageSubTitle">{$texts['Admin']} - {$texts['Long_texts']}</div>
  <div class="smallFont" style="margin-bottom: 10px;">Help making this website more accessible by translating these texts into your own language.</div>

  <div style="margin-bottom: 5px;">
    <select id="selectLongText" class="searchInput" oninput="loadLongText();">$longTextOptions</select>
    <select id="selectLanguage" class="searchInput" style="margin-left: 5px;" oninput="loadLongText();">$languageOptions</select>
    <button class="button" onclick="saveLongText();">{$texts['Save']}</button>
  </div>

  <div id="longtextsDivs" style="width: 100%;">
  
    <div style="display: flex; flex-direction: row; justify-content: space-between; width: 100%;">    
      <div style="width: calc(50% - 2px); box-sizing: border-box;">
        <div>{$texts['English']}</div>
        <textarea id="longtext" readonly class="translationArea"></textarea>
      </div>
      
      <div style="width: calc(50% - 2px); box-sizing: border-box;">
        <div>{$texts['Translation']} <span id="translationLanguage"></span></div>
        <textarea id="longtext_translation" oninput="translationChange();" class="translationArea"></textarea>
      </div>
    </div>
    
    <div style="display: flex; flex-direction: row; justify-content: space-between; width: 100%;">    
      <div style="width: calc(50% - 2px); box-sizing: border-box;">
        <div>Preview</div>
        <div id="longtextPreview" class="translationArea"></div>
      </div>
      
      <div style="width: calc(50% - 2px); box-sizing: border-box;">
        <div>Preview</div>
        <div id="longtext_translationPreview" class="translationArea"></div>
      </div>
    </div>
    
    <div>Use Markdown code for formatting:<br>
      <a href="https://www.markdownguide.org/cheat-sheet/" target="markdown">Cheat sheet</a><br>
      <a href="https://www.markdownguide.org/basic-syntax/" target="markdown">Documentation</a><br>
    </div>
  </div>
  
</div>
HTML;

    $htmlEnd = getFormNewTranslation();
  }
}

$html =
  HtmlBuilder::getHTMLBeginMain('Admin', $head, 'initAdmin') .
  $mainHTML .
  HtmlBuilder::getHTMLEnd($htmlEnd);

echo $html;