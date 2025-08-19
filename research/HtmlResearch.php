<?php

class HtmlResearch {

  public static function questionnaireSettings(): string {
    global $database;

    $texts = translateArray(['Questionnaires', 'Admin', 'Id', 'New', 'Edit', 'Delete', 'Save', 'Cancel',
      'Sort_questions']);

    // Add countries
    $countryOptions = '';
    foreach ($database->countries as $country) {
      $countryOptions .= "<option value='{$country['id']}'>{$country['name']}</option>";
    }

    return <<<HTML
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
        <tr><th>Id</th><th>Title</th><th>Type</th><th>Country ID</th><th>Questions active</th><th>Results public</th></tr>
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
    <input id="questionText" class="popupInput" type="text" maxlength="200">
       
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
    
    <label><input id="questionnairePublic" type="checkbox">Public results</label>

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
  }

  public static function pageFillIn(): string {
    $texts = translateArray(['Questionnaires', 'fill_in', 'Injury', 'Dead_(adjective)', 'Child', 'Exclude_unilateral',
      'Filter']);

    $textIntro = translateLongText('questionnaires_fill_in');
    $htmlSearchPersons = HtmlBuilder::getSearchPersonsHtml();

    return <<<HTML
<div id="pageMain">
  <div class="pageSubTitle">{$texts['Questionnaires']} | {$texts['fill_in']}</div>

  <div class="pageInner">
    $textIntro  
  </div>

  <div id="searchBar" class="searchBarTransparent" style="display: flex;">
    <div class="toolbarItem">
      <span id="filterResearchDead" class="menuButton bgDeadWhite" data-tippy-content="{$texts['Injury']}: {$texts['Dead_(adjective)']}" onclick="clickQuestionnaireOption();"></span>      
      <span id="filterResearchChild" class="menuButton bgChildWhite" data-tippy-content="{$texts['Child']}" onclick="clickQuestionnaireOption();"></span>      
      <span id="filterResearchNoUnilateral" class="menuButton bgNoUnilateralWhite" data-tippy-content="{$texts['Exclude_unilateral']}" onclick="clickQuestionnaireOption();"></span>      
    </div>
  
    $htmlSearchPersons

    <div class="toolbarItem">
      <div class="button buttonMobileSmall buttonImportant" onclick="selectFilterQuestionnaireFillIn(event)">{$texts['Filter']}</div>
    </div>
    
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg" alt="spinner"></div>
  
  <div id="tableWrapper" class="panelTableOverflow blackOnWhite" style="display: none;">
    <table class="dataTable">
      <tbody id="dataTableArticles"></tbody>
    </table>
  </div>
HTML;
  }

  public static function pageResults(): string {

    global $database;
    global $user;

    $texts = translateArray(['Filter', 'Questionnaires', 'results', 'Injury', 'Dead_(adjective)', 'Child',
      'Exclude_unilateral', 'Always']);

    $publicOnly = ! $user->admin;
    $questionnaires = $database->getQuestionnaires($publicOnly);

    $questionnairesOptions = '';
    foreach ($questionnaires as $questionnaire) {
      $publicText = (! $publicOnly) && $questionnaire['public'] === 0? ' (admin only)' : ' (public)';
      $questionnairesOptions .= "<option value='{$questionnaire['id']}'>{$questionnaire['title']}$publicText</option>";
    }

    $htmlSearchPersons = HtmlBuilder::getSearchPersonsHtml();
    $htmlSearchCountry = HtmlBuilder::getSearchCountryHtml('filterResearchCountry', 'UN');

    return <<<HTML
<div id="pageMain">
<div class="pageInner">
  <div class="pageSubTitle">{$texts['Questionnaires']} | {$texts['results']}</div>
  
  <div class="searchBarTransparent" style="display: flex;">
    <div class="toolbarItem"><select id="filterQuestionnaire" class="searchInput" oninput="questionnaireFilterChange()">$questionnairesOptions</select></div>
    
    <div class="toolbarItem">
      <select id="filterResearchGroup" class="searchInput" onchange="selectFilterQuestionnaireResults();">
        <option value="" selected>No groups</option>
        <option value="year">Group by year</option>
        <option value="month">Group by month</option>
        <option value="source">Group by source</option>
        <option value="country">Group by country</option>
      </select>
    </div>
  
    <div class="toolbarItem">
      <select id="filterMinArticles" class="searchInput" onchange="selectFilterQuestionnaireResults();" data-tippy-content="Minimum amount of articles for a group to be visible">
        <option value="0">[No minimum]</option>
        <option value="1">Min. 1 article</option>
        <option value="2">Min. 2 articles</option>
        <option value="3" selected>Min. 3 articles</option>
        <option value="5">Min. 5 articles</option>
        <option value="10">Min. 10 articles</option>
      </select>
    </div>
  
  </div>
  
  <div id="searchBar" class="searchBarTransparent" style="display: flex;">
    <div class="toolbarItem">
      <span id="filterResearchDead" class="menuButton bgDeadWhite" data-tippy-content="{$texts['Injury']}: {$texts['Dead_(adjective)']}" onclick="clickQuestionnaireOption();"></span>      
      <span id="filterResearchChild" class="menuButton bgChildWhite" data-tippy-content="{$texts['Child']}" onclick="clickQuestionnaireOption();"></span>      
      <span id="filterResearchNoUnilateral" class="menuButton bgNoUnilateralWhite" data-tippy-content="{$texts['Exclude_unilateral']}" onclick="clickQuestionnaireOption();"></span>      
    </div>
    
    <div class="toolbarItem">
      $htmlSearchCountry
    </div>
    
    <div class="toolbarItem">
      <select id="filterResearchTimeSpan" class="searchInput">
        <option value="">[{$texts['Always']}]</option>
        <option value="from2022">From 2022</option>
        <option value="1year">1 year</option>
        <option value="2year">2 years</option>
        <option value="3year">3 years</option>
        <option value="5year">5 years</option>
        <option value="10year">10 years</option>
      </select>
    </div>
    
    $htmlSearchPersons
    
    <div class="toolbarItem">
      <div class="button buttonMobileSmall buttonImportant" style="margin-left: 0;" onclick="selectFilterQuestionnaireResults()">{$texts['Filter']}</div>
    </div>

  </div>

  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>

  <div id="questionnaireInfo" class="smallFont" style="margin-bottom: 10px;"></div>

  <div id="questionnaireBechdelIntro" style="width: 100%; margin-bottom: 10px; display: none;">
    <div style="font-weight: bold;">An article passes this test if all questions below are answered 'Yes':</div>
    <div id="questionnaireBechdelQuestions"></div>
  </div>
     
  <div id="questionnaireBars"></div>

  <div id="headerStatistics" style="display: none; width: 100%; margin-top: 10px; font-weight: bold; text-align: left;">Statistics</div>
  
  <table id="tableStatistics" class="dataTable" onclick="onClickStatisticsTable();">
    <thead id="tableStatisticsHead"></thead>  
    <tbody id="tableStatisticsBody" style="cursor: pointer;"></tbody>
  </table>  
      
</div>
</div>

<div id="formResultArticles" class="popupOuter">

  <div class="formFixed" onclick="event.stopPropagation();">how 
   
    <div id="headerResultArticles" class="popupHeader">Result articles</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="resultArticles" class="flexColumn" style="overflow: auto;">Loading...</div>
                            
  </div>
  
</div>
HTML;
  }

  public static function pageAITest(): string {

    return <<<HTML
<div id="pageMain">
  <div class="pageInner"> 
    <div class="pageSubTitle">AI prompt builder</div>
    <div id="aiInfo" class="smallFont" style="text-align: right;">&nbsp;</div> 
  
    <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>

    <div id="aiForm" style="display: none;">
      <div class="labelDiv">AI model</div>
      <div class="smallFont">To help you choose, read the <a href="https://openrouter.ai/rankings" target="openrouter">list of most popular AI models</a>.</div>
      <div style="display: flex;">
        <select id="aiModel" class="inputForm" oninput="aiModelChange();"></select>
        <button class="button buttonGray buttonLine" onclick="showAddAiModelForm();">Add</button>
        <button class="button buttonGray buttonLine" onclick="removeAiModel();">Remove</button>
        <button class="button buttonGray buttonLine" onclick="updateAiModelsInfo();">Update All</button>
      </div>
      <div id="aiModelInfo" class="smallFont"></div>
      
      <input type="hidden" id="aiPromptId" value="">
      <input type="hidden" id="aiCrashId" value="">
      
      <div class="labelDiv">System prompt</div>
      <textarea id="aiSystemPrompt" class="inputForm" style="height: 100px; resize: vertical;"></textarea> 
    
      <div id="section_structured_outputs" style="display: none;">
        <div class="labelDiv">Response format [JSON]</div>
        <div class="smallFont">For models supporting structured ouputs. Read instructions on the <a href="https://openrouter.ai/docs/features/structured-outputs" target="openrouter">openrouter.ai website</a>.</div>
        <textarea id="aiResponseFormat" class="inputForm" style="height: 100px; resize: vertical;"></textarea> 
      </div>
      
      <div class="labelDiv">User prompt <span class="smallFont" id="promptInfo"></span></div>
      <div class="smallFont">Available tags:
      <button class="buttonTiny" onclick="insertAITag('article_title');">[article_title]</button>, 
      <button class="buttonTiny" onclick="insertAITag('article_text');">[article_text]</button>. 
      These are replaced by the media article title and full text.</div>
      <textarea id="aiPrompt" class="inputForm" style="height: 100px; resize: vertical;"></textarea>
      
      <div id="articleSection">
      
        <div style="display: flex; justify-content: space-between;">
          <div>Article
            <span class="smallFont"> 
              ID <input class="inputSmall" type="text" id="aiArticleId" style="width: 50px;">
              <button class="buttonTiny" onclick="loadAiArticle();">Load from ID</button>
              <button class="buttonTiny" onclick="viewAiCrash();">View Crash</button>
            </span>
          </div>
          <div>
            <button class="buttonTiny" onclick="loadAiArticle('latest');">Latest</button>
            <button class="buttonTiny" onclick="loadAiArticle('back');">Back</button>
            <button class="buttonTiny" onclick="loadAiArticle('next');">Next</button>      
          </div>
        
        </div>

        <div id="aiArticle" class="readOnlyInput" style="min-height: 100px; max-height: 300px; margin-top: 5px; resize: vertical; overflow: auto;"></div>    
      </div>

      <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
      <div style="display: flex; justify-content: flex-end; margin: 10px 0;">
        <button class="button buttonImportant" onclick="aiRunPrompt();" style="margin-left: 0">Run prompt</button>     
        <button class="button button buttonGray" onclick="showLoadAiPromptForm();">Load</button>     
        <button class="button button buttonGray" onclick="aiNewPrompt();">New</button>     
        <button class="button button buttonGray" onclick="aiSavePrompt();">Save</button>     
        <button class="button button buttonGray" onclick="aiSaveCopyPrompt();">Save copy</button>     
      </div>    
    </div>
    
    <div id="spinnerRunPrompt" style="display: none; margin: 5px 0; justify-content: center;"><img alt="Spinner" src="/images/spinner.svg"></div>

    <div id="groupAiResponse" style="display: none">
      <div>Server response <button class="buttonTiny" onclick="copyServerResponse();">Copy</button></div>
      <div id="aiResponse" class="readOnlyInput" style=""></div>    
      <div class="smallFont">
        <button class="buttonTiny" style="border: none; margin-left: 0;" onclick="showGenerationSummary();">Refresh</button>
        <span id="aiResponseMeta"></span>
      </div>
    </div>
    
  </div> 
</div>

<div id="formAddAiModel" class="popupOuter">
  <div class="formFullPage" onclick="event.stopPropagation();" style="width: 100%;">

    <div id="headerQuestion" class="popupHeader">Add openrouter.ai AI model to selection</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <label>Search
    <input id="filterAiModels" class="inputForm" type="search" oninput="doFilterAiModels();"></label>
    <div class="smallFont">To help you choose, read the <a href="https://openrouter.ai/rankings" target="openrouter">list of most popular AI models</a>.</div>
   
    <div class="formScrolling">
      <table class="dataTable tableWhiteHeader">
        <thead>
          <tr>
            <th style="text-align: left;">Model</th>
            <th style="text-align: left;">Selected</th>
            <th style="text-align: left;">Cost/M</th>
            <th style="text-align: left;">Structured outputs</th>
            <th style="text-align: left;">Created</th>
          </tr>
        </thead>
        <tbody id="addAiModelsBody" onclick="clickAiModelRow()"></tbody>
      </table>      
    </div>

    <div id="spinnerLoadAddModels" style="margin: 5px 0;justify-content: center;"><img alt="Spinner" src="/images/spinner.svg"></div>
          
    <div class="popupFooter">
      <div id="addAiModelInfo" class="infoBlock" style="display: none; margin: 5px 0 10px 0;"></div>
    </div>
    
    <div class="popupFooter">
      <button class="button" style="margin-left: 0;" onclick="addAiModel();">Add</button>
      <button class="button buttonGray" onclick="closePopupForm();">Cancel</button>
    </div>
    
  </div>
</div>

<div id="formLoadAiModel" class="popupOuter">
  <div class="formFullPage" onclick="event.stopPropagation();" style="width: 100%;">

    <div id="headerQuestion" class="popupHeader">Load prompt</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div class="formScrolling">
      <table class="dataTable tableWhiteHeader">
        <thead>
          <tr>
            <th>Id</th>
            <th>Model</th>
            <th>Owner</th>
            <th>Function</th>
            <th>User prompt</th>
            <th>System prompt</th>
            <th>Response format</th>
            <th>Article Id</th>
          </tr>
        </thead>
        <tbody id="loadAiQueriesBody" onclick="clickaiPromptRow()" ondblclick="loadaiPrompt();"></tbody>
      </table>      
    </div>

    <div id="spinnerLoadQueries" style="margin: 5px 0;justify-content: center;"><img alt="Spinner" src="/images/spinner.svg"></div>
          
    <div class="popupFooter">
      <button class="button" style="margin-left: 0;" onclick="loadaiPrompt();">Load</button>
      <button class="button buttonRed" onclick="deleteAiPrompt();">Delete</button>
      <button class="button buttonGray" onclick="closePopupForm();">Cancel</button>
    </div>
    
  </div>
</div>
HTML;
  }


}