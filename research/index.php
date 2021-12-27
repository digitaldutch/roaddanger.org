<?php

require_once '../initialize.php';

global $database;
global $user;
global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);
if      (strpos($uri, '/research/questionnaires/options') === 0) $pageType = PageType::questionnaireOptions;
else if (strpos($uri, '/research/questionnaires') === 0)         $pageType = PageType::questionnaireResults;
else die('Internal error: Unknown page type');


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
$head = "<script src='/research/research.js?v=$VERSION'></script>";

if ((! $user->loggedIn) || (! $user->isModerator())) {
  $mainHTML = <<<HTML
<div id="main" class="pageInner">
  <div style="text-align: center;">You are not a moderator. Log in as moderator or administrator.</div>
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
HTML;
} else {

  if ($pageType === PageType::questionnaireOptions) {

    $texts = translateArray(['Questionnaires', 'Admin', 'Id', 'New', 'Edit', 'Delete', 'Save', 'Cancel', 'Sort_questions']);

    // Add countries
    $countryOptions = '';
    foreach ($database->countries as $country) {
      $countryOptions .= "<option value='{$country['id']}'>{$country['name']}</option>";
    }

    $mainHTML = <<<HTML
<div id="main" class="scrollPage" style="padding: 5px;">

  <div class="tabBar" onclick="tabBarClick();">
    <div id="tab_questionnaires" class="tabSelected">Questionnaires</div>
    <div id="tab_questions">Questions</div>
  </div>

  <div class="tabContent" id="tabContent_questionnaires">

    <div style="margin-bottom: 5px;">
      <button class="button" onclick="newQuestionnaire();" data-inline-admin>{$texts['New']}</button>
      <button class="button" onclick="editQuestionnaire();" data-inline-admin>{$texts['Edit']}</button>
      <button class="button buttonRed" onclick="deleteQuestionnaire();" data-inline-admin>{$texts['Delete']}</button>
    </div>
  
    <table id="table_questionnaires" class="dataTable" style="min-width: 500px;">
      <thead>
        <tr><th>Id</th><th>Title</th><th>Type</th><th>Country ID</th><th>Active</th></tr>
      </thead>
      <tbody id="tableBodyQuestionnaires" onclick="tableDataClick(event, 1);" ondblclick="editQuestionnaire();">    
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

<div id="formQuestionnaire" class="popupOuter">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="saveQuestionnaire(); return false;">

    <div id="headerQuestionnaire" class="popupHeader"></div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>
       
    <input id="questionnaireId" type="hidden">

    <label for="questionnaireTitle">Title</label>
    <input id="questionnaireTitle" class="popupInput" type="text" maxlength="100">
       
    <label for="questionnaireType">Type</label>
    <select id="questionnaireType">
      <option value="0">Standard</option>
      <option value="1">Bechdel test</option>
    </select>    
       
    <label for="questionnaireCountryId">Country</label>
    <select id="questionnaireCountryId">$countryOptions</select>    

    <label><input id="questionnaireActive" type="checkbox">Active</label>

    <div class="formSubHeader">Questions</div> 
    
    <div>
      <div class="menuButton bgAdd" onclick="addQuestionToQuestionnaire();"></div>
      <div class="menuButton bgRemove" onclick="removeQuestionFromQuestionnaire();"></div>
    </div>

    <div class="smallFont" style="margin: 0 0 5px 5px;">Sort questions with drag and drop.</div>

    <table class="dataTable">
      <thead><th>Id</th><th>Question</th></thead>
      <tbody id="tbodyQuestionnaireQuestions" onclick="tableDataClick(event, 2);"></tbody>
    </table>

    <div id="questionnaireSpinner" class="spinner"></div>
    <div id="questionnaireNoneFound" style="display: none;">No questions found</div>

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
    <div class="toolbarItem"><select id="filterQuestionnaire" oninput="questionnaireFilterChange()">$questionnairesOptions</select></div>
  </div>

  <div id="questionnaireInfo" class="smallFont" style="margin-bottom: 10px;"></div>

  <div id="questionnaireBechdelIntro" style="width: 100%; margin-bottom: 10px; display: none;">
    <div style="font-weight: bold;">An article passes this test if all questions below are answered 'Yes':</div>
    <div id="questionnaireBechdelQuestions"></div>
  </div>
     
  <div id="questionnaireBars"></div>

  <table class="dataTable"">
    <thead id="tableHead"></thead>  
    <tbody id="tableBody"></tbody>
  </table>  
      
</div>
HTML;

  }
}

$html =
  getHTMLBeginMain('Research', $head, 'initResearch', false, false) .
  $mainHTML .
  getHTMLEnd($htmlEnd);

echo $html;