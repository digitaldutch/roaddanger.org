<?php

require_once '../initialize.php';

global $database;
global $user;
global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (strpos($uri, '/admin/humans')         === 0)         $pageType = PageType::humans;
else if (strpos($uri, '/admin/translations')   === 0)         $pageType = PageType::translations;
else if (strpos($uri, '/admin/longtexts')      === 0)         $pageType = PageType::longTexts;
else if (strpos($uri, '/admin/questionnaires/options') === 0) $pageType = PageType::questionnaireOptions;
else if (strpos($uri, '/admin/questionnaires') === 0)         $pageType = PageType::questionnaireResults;
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
       
    <div id="newTranslationError" class="formError"></div>

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
  $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div style="text-align: center;">You are not a moderator or administrator. Log in as moderator or administrator.</div>
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
HTML;
} else {

  if ($pageType === PageType::humans) {
    if (! $user->admin) $mainHTML = htmlNoAdmin();
    else {

      $texts = translateArray(['Admin', 'Humans', 'Id', 'Name', 'Last_active', 'Permission']);

      $mainHTML = <<<HTML
<div id="main" class="pageInner scrollPage">
  <div class="pageSubTitle">{$texts['Admin']} - {$texts['Humans']}</div>
  
  <div class="panelTableOverflow">
    <table id="tableData" class="dataTable">
      <thead>
        <tr>
          <th>{$texts['Id']}</th>
          <th>{$texts['Name']}</th>
          <th>{$texts['Last_active']}</th>
          <th>{$texts['Permission']}</th>
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

    $htmlEnd = getFormEditUser();
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

  <div class="smallFont" style="margin-bottom: 10px; text-align: center;">Please help making this website more accessible by translating these texts into your own language.</div>

  <div style="margin-bottom: 5px;">
    <select id="selectLanguage" class="searchInput" oninput="changeUserLanguage();">$languageOptions</select>

    <button class="button" onclick="saveTranslations();">{$texts['Save']}</button>
    <button class="button buttonGray" onclick="newTranslation();" data-inline-admin>{$texts['New']}</button>
    <button class="button buttonRed" onclick="deleteTranslation();" data-inline-admin>{$texts['Delete']}</button>
  </div>

  <div class="panelTableOverflow">
    <table id="tableData" class="dataTable" style="user-select: text;">
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
  <div class="smallFont" style="margin-bottom: 10px;">Please help making this website more accessible by translating these texts into your own language.</div>

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
  } else if ($pageType === PageType::questionnaireOptions) {

    $texts = translateArray(['Questionnaires', 'Admin', 'Id', 'New', 'Edit', 'Delete', 'Save', 'Cancel', 'Sort_questions']);

    // Add countries
    $countryOptions = '';
    foreach ($database->countries as $country) {
      $countryOptions .= "<option value='{$country['id']}'>{$country['name']}</option>";
    }

    $mainHTML = <<<HTML
<div id="main" class="scrollPage" style="padding: 5px;">

  <div class="tabBar" onclick="tabBarClick();">
    <div id="tab_questionaires" class="tabSelected">Questionaires</div>
    <div id="tab_questions">Questions</div>
  </div>

  <div class="tabContent" id="tabContent_questionaires">

    <div style="margin-bottom: 5px;">
      <button class="button" onclick="newQuestionaire();" data-inline-admin>{$texts['New']}</button>
      <button class="button" onclick="editQuestionaire();" data-inline-admin>{$texts['Edit']}</button>
      <button class="button buttonRed" onclick="deleteQuestionaire();" data-inline-admin>{$texts['Delete']}</button>
    </div>
  
    <table id="table_questionaires" class="dataTable" style="min-width: 500px;">
      <thead>
        <tr><th>Id</th><th>Title</th><th>Type</th><th>Country ID</th><th>Active</th></tr>
      </thead>
      <tbody id="tableBodyQuestionaires" onclick="tableDataClick(event, 1);" ondblclick="editQuestionaire();">    
     </tbody>
    </table>  
    
    <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
  </div>

  <div class="tabContent" id="tabContent_questions" style="display: none;">
    <div style="margin-bottom: 5px;">
      <button class="button" onclick="newQuestion();" data-inline-admin>{$texts['New']}</button>
      <button class="button" onclick="editQuestion();" data-inline-admin>{$texts['Edit']}</button>
      <button class="button buttonRed" onclick="deleteQuestion();" data-inline-admin>{$texts['Delete']}</button>
    </div>
    <div class="smallFont" style="margin: 0 0 5px 5px;">Sort questions with drag and drop.</div>
  
    <table id="table_questions" class="dataTable" style="min-width: 500px;">
      <thead>
        <tr><th>Id</th><th>Question</th><th>Explanation</th></tr>
      </thead>
      <tbody id="tableBodyQuestions" onclick="tableDataClick(event);" ondblclick="editQuestion();">    
     </tbody>
    </table>  
    
    <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
  </div>

</div>

<div id="formQuestion" class="popupOuter">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="saveQuestion(); return false;">

    <div id="headerQuestion" class="popupHeader"></div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>
       
    <input id="questionId" type="hidden">

    <label for="questionText">Question text</label>
    <input id="questionText" class="popupInput" type="text" maxlength="100">
       
    <label for="questionExplanation">Explanation</label>
    <input id="questionExplanation" class="popupInput" type="text" maxlength="200">
       
    <div class="popupFooter">
      <input type="submit" class="button" style="margin-left: 0;" value="{$texts['Save']}">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>
    
  </form>
</div>

<div id="formQuestionaire" class="popupOuter">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="saveQuestionnaire(); return false;">

    <div id="headerQuestionaire" class="popupHeader"></div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>
       
    <input id="questionaireId" type="hidden">

    <label for="questionaireTitle">Title</label>
    <input id="questionaireTitle" class="popupInput" type="text" maxlength="100">
       
    <label for="questionaireType">Type</label>
    <select id="questionaireType">
      <option value="0">Standard</option>
      <option value="1">Bechdel test</option>
    </select>    
       
    <label for="questionaireCountryId">Country</label>
    <select id="questionaireCountryId">$countryOptions</select>    

    <label><input id="questionaireActive" type="checkbox">Active</label>

    <div class="formSubHeader">Questions</div> 
    
    <div>
      <div class="menuButton bgAdd" onclick="addQuestionToQuestionnaire();"></div>
      <div class="menuButton bgRemove" onclick="removeQuestionFromQuestionnaire();"></div>
    </div>

    <div class="smallFont" style="margin: 0 0 5px 5px;">Sort questions with drag and drop.</div>

    <div id="questionaireQuestions">No questions selected</div>

    <div class="popupFooter">
      <input type="submit" class="button" style="margin-left: 0;" value="{$texts['Save']}">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>
    
  </form>
</div>

<div id="formAddQuestion" class="popupOuter">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="selectQuestionToQuestionnaire(); return false;">

    <div id="headerQuestion" class="popupHeader">Add question to questionnaire</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="addQuestionQuestions"></div>
       
    <div class="popupFooter">
      <input type="submit" class="button" style="margin-left: 0;" value="Add">
      <input type="button" class="button buttonGray" value="Cancel" onclick="closePopupForm();">
    </div>
    
  </form>
</div>
HTML;
  } else if ($pageType === PageType::questionnaireResults) {
    $texts = translateArray(['Questionnaires', 'Results']);

    $questionnaires = $database->getQuestionnaires();

    $questionnairesOptions = '';
    foreach ($questionnaires as $questionnaire) {
      $questionnairesOptions .= "<option value='{$questionnaire['id']}'>{$questionnaire['title']}</option>";
    }

    $mainHTML = <<<HTML
<div id="pageMain">
  <div class="pageSubTitle">{$texts['Questionnaires']} | {$texts['Results']}</div>
  
  <div class="searchBar" style="display: flex;">
    <div class="toolbarItem"><select id="filterQuestionnaire" oninput="loadQuestionnairResults()">$questionnairesOptions</select></div>
  </div>

  <table class="dataTable">
    <thead>
      <tr>
        <th>Question</th>
        <th>Yes</th>
        <th>No</th>
        <th>n.d.</th>
      </tr>
    </thead>  
    <tbody id="tableBody">
      
    </tbody>
  </table>  
      
</div>
HTML;

  }
}

$html =
  getHTMLBeginMain('Beheer', $head, 'initAdmin', false, false) .
  $mainHTML .
  getHTMLEnd($htmlEnd);

echo $html;