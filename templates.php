<?php

function getHTMLBeginMain($pageTitle='', $head='', $initFunction='', $addSearchBar=false, $showButtonAdd=false){
  global $VERSION;

  $texts = translateArray(['Cookie_warning', 'More_info', 'Accept', 'Add']);

  if ($pageTitle !== '') $title = $pageTitle . ' | ' . WEBSITE_NAME;
  else $title = WEBSITE_NAME;

  $initScript = ($initFunction !== '')? "<script>document.addEventListener('DOMContentLoaded', $initFunction);</script>" : '';
  $navigation = getNavigation();

  if (! cookiesApproved()) {
    $cookieWarning = <<<HTML
    <div id="cookieWarning" class="flexRow">
      <div>{$texts['Cookie_warning']}
        <a href="/aboutthissite/#cookies" style="text-decoration: underline; color: inherit;">{$texts['More_info']}</a>
      </div>
      <div class="button" onclick="acceptCookies();">{$texts['Accept']}</div>
    </div>
HTML;
  } else $cookieWarning = '';

  $buttons = '';
  if ($addSearchBar) {
    $buttons .= '<div id="buttonSearch" class="menuButtonBlack bgSearchWhite" onclick="toggleSearchBar(event);"></div>';
  }

  if ($showButtonAdd) {
    $buttons .= <<<HTML
<div id="buttonNewCrash" class="buttonHeader buttonImportant" onclick="showNewCrashForm();">
  <div class="buttonIcon bgAdd"></div>
  <div class="hideOnMobile buttonInsideMargin">{$texts['Add']}</div>
</div>
HTML;
  }

  global $database;
  global $user;

  // Add countries
  $countryOptions = '';
  foreach ($database->countries as $country) {
    $class = $country['id'] === $user->country['id']? "class='menuSelected'" : '';
    $countryOptions .= "<div $class onclick=\"selectCountry('{$country['id']}')\"><div class='menuIcon' style='margin-right: 5px;background-image: url({$country['flagFile']});'></div>{$country['name']}</div>";
  }

  $languages = $database->fetchAll("SELECT id, name FROM languages ORDER BY name;");
  $languageOptions = '';
  foreach ($languages as $language) {
    $class = $language['id'] === $user->languageId? "class='menuSelected'" : '';
    $languageOptions .= "<div $class onclick=\"setLanguage('{$language['id']}')\">{$language['name']}</div>";
  }

  $texts = translateArray(['Log_out', 'Log_in', 'Account', 'Country', 'Language']);
  $websiteName = WEBSITE_NAME;

  $htmlSearchBar  = $addSearchBar? getHtmlSearchBar() : '';

  return <<<HTML
<!DOCTYPE html>
<html lang="$user->languageId">
<head>
<link href="https://fonts.googleapis.com/css?family=Lora|Montserrat" rel="stylesheet">
<link href="/main.css?v=$VERSION" rel="stylesheet" type="text/css">
<link rel="shortcut icon" type="image/png" href="/images/roaddanger_icon.png">
<script src="/scripts/popper.min.js"></script><script src="/scripts/tippy-bundle.umd.min.js"></script>
<script src='/js/utils.js?v=$VERSION'></script>
$head
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta charset="utf-8">
<title>$title</title>

$initScript

</head>
<body style="overscroll-behavior-x: none;">
$navigation

<div class="flexToFullPage">

  <div id="topBar">
    <span class="menuButtonBlack bgMenuWhite" onclick="toggleNavigation(event);"></span>
  
    <div class="headerMain pageTitle">
      <span>
        <a href="/">$websiteName</a>        
        <img id="spinnerTitle" src="/images/spinner.svg" style="display: none; height: 17px; margin-left: 5px;" alt="Spinner">
      </span>
    </div>
   
    <div style="display: flex; position: relative;">
      $buttons
      
      <span style="position: relative;">
        <div class="buttonHeader" onclick="profileClick(event);">
          <div id="buttonProfile" class="buttonIcon bgPersonWhite"></div>
          <div id="loginText" class="hideOnMobile buttonInsideMargin">...</div>
          <div id="loginName" class="hideOnMobile buttonInsideMargin"></div>
        </div>
  
        <div id="menuPerson" class="buttonPopupMenu">
          <div id="menuProfile" class="navigationSectionHeader"></div>
          <a href="/account">{$texts['Account']}</a>
          <div id="menuLogin" onclick="showLoginForm();">{$texts['Log_in']}</div> 
          <div id="menuLogout" style="display: none;" onclick="logOut();">{$texts['Log_out']}</div>
        </div>
      </span>
  
      <span style="position: relative;">
        <div id="buttonLanguages" class="buttonHeader" onclick="countryClick(event);">
          <div id="iconCountry" class="buttonIcon"></div>
          <div class="buttonIcon buttonInsideMargin bgLanguageWhite"></div>
        </div>
        
        <div id="menuCountries" class="buttonPopupMenu">
          <div class="navigationSectionHeader">{$texts['Country']}</div>
          $countryOptions
          <div class="navigationSectionHeader">{$texts['Language']}</div>
          $languageOptions
        </div>
      </span>
  
    </div>
  </div>
  
  $htmlSearchBar
  
  $cookieWarning
HTML;
}

function getHtmlSearchBar(){
  $texts = translateArray(['Child', 'Dead_(adjective)', 'Injured', 'Search', 'Source', 'Search_text_hint']);

  $htmlSearchCountry = getSearchCountryHtml();
  $htmlSearchPeriod = getSearchPeriodHtml();
  $htmlSearchPersons = getSearchPersonsHtml();

  return <<<HTML
  <div id="searchBar" class="searchBar">
    <div class="popupCloseCross closeCrossWhite" onclick="toggleSearchBar();"></div>

    <div class="toolbarItem">
      <span id="searchPersonHealthDead" class="menuButtonBlack bgDeadWhite" data-tippy-content="{$texts['Dead_(adjective)']}" onclick="selectSearchPersonDead();"></span>      
      <span id="searchPersonHealthInjured" class="menuButtonBlack bgInjuredWhite" data-tippy-content="{$texts['Injured']}" onclick="selectSearchPersonInjured();"></span>      
      <span id="searchPersonChild" class="menuButtonBlack bgChildWhite" data-tippy-content="{$texts['Child']}" onclick="selectSearchPersonChild();"></span>      
    </div>

    <div class="toolbarItem">
       <input id="searchText" class="searchInput textInputWidth"  type="search" data-tippy-content="{$texts['Search_text_hint']}" placeholder="{$texts['Search']}" onkeyup="startSearchKey(event);" autocomplete="off">  
    </div>
    
    <div class="toolbarItem">$htmlSearchCountry</div>
    
    $htmlSearchPeriod
    
    $htmlSearchPersons    
           
    <div class="toolbarItem">
      <input id="searchSiteName" class="searchInput textInputWidth" type="search" placeholder="{$texts['Source']}" onkeyup="startSearchKey(event);" autocomplete="off">
    </div>

    <div class="toolbarItem">
      <div class="button buttonMobileSmall buttonImportant" style="margin-left: 0;" onclick="startSearch(event)">{$texts['Search']}</div>
    </div>
  </div>      
HTML;

}

function getHTMLEnd($htmlEnd=''){
  $forms    = getHTMLConfirm() . getLoginForm() . getFormCrash() . getFormEditCrash() . getFormMergeCrash() .
    getFormEditPerson() . getFormQuestionnaires();
  return <<<HTML
</div>
    $htmlEnd 
    <div id="floatingMessage" onclick="closeMessage();">
      <div id="messageCloseCross" class="popupCloseCross crossWhite"></div>
      <div id="messageText"></div>
    </div>
    <div style="clear: both;"></div>
   
    $forms
</body>
HTML;
}

function getHTMLConfirm(){
  $texts = translateArray(['Confirm', 'Ok', 'Cancel']);

  $formConfirm = <<<HTML
<div id="formConfirmOuter" class="popupOuter" style="z-index: 1000" onclick="closeConfirm();">
  <form id="formConfirm" class="floatingForm" onclick="event.stopPropagation();">

    <div id="confirmHeader" class="popupHeader">{$texts['Confirm']}</div>
    <div class="popupCloseCross" onclick="closeConfirm();"></div>

    <div id="confirmText" class="textMessage"></div>

    <div class="popupFooter">
      <button id="buttonConfirmOK" class="button" type="submit" autofocus>{$texts['Ok']}</button>
      <button id="buttonConfirmCancel" class="button buttonGray" type="button" onclick="closeConfirm();">{$texts['Cancel']}</button>
    </div>    

  </form>
</div>
HTML;

  return $formConfirm;
}

function getNavigation(){

  global $VERSION;
  global $VERSION_DATE;

  $texts = translateArray(['Admin', 'Crashes', 'Statistics', 'Translations', 'Long_texts', 'Other', 'Recent_crashes',
    'Child_victims', 'Mosaic', 'The_correspondent_week', 'Map', 'General', 'deadly_crashpartners',
    'Counterparty_in_crashes', 'Transportation_modes', 'Export_data', 'About_this_site', 'Humans', 'Moderations', 'Last_modified_crashes', 'Options',
    'Version', 'Questionnaires', 'fill_in', 'settings', 'results', 'Reporting_experiences', 'Research',
    'Graphs_and_statistics', 'Media_humanization_test']);

  $websiteTitle = WEBSITE_NAME;

  return <<<HTML
<div id="navShadow" class="navShadow" onclick="closeNavigation()"></div>
<div id="navigation" onclick="closeNavigation();">
  <div class="navHeader">
    <div class="popupCloseCross closeCrossWhite" onclick="closeNavigation();"></div>
    <div class="navHeaderTop"><span class="pageTitle">{$websiteTitle}</span></div>      
  </div>
  <div style="overflow-y: auto;">
  
    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Crashes']}</div>
      <a id="nav_recent_crashes" href="/" class="navItem">{$texts['Recent_crashes']}</a>
      <a href="/child_victims" class="navItem">{$texts['Child_victims']}</a>
      <a href="/mosaic" class="navItem">{$texts['Mosaic']}</a>
      <a href="/map" class="navItem">{$texts['Map']}</a>
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Graphs_and_statistics']}</div>
      <a href="/statistics/media_humanization" class="navItem">{$texts['Media_humanization_test']}</a>
      <a href="/statistics/counterparty" class="navItem">{$texts['Counterparty_in_crashes']}</a>
      <a href="/statistics/transportation_modes" class="navItem">{$texts['Transportation_modes']}</a>
      <a href="/statistics/general" class="navItem">{$texts['General']}</a>
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Research']}</div>
      <a href="/research/questionnaires/options" class="navItem" data-admin>{$texts['Questionnaires']} | {$texts['settings']}</a>
      <a href="/research/questionnaires/" class="navItem" data-admin>{$texts['Questionnaires']} | {$texts['results']}</a>
      <a href="/research/questionnaires/fill_in" class="navItem" data-admin>{$texts['Questionnaires']} | {$texts['fill_in']}</a>
      <a href="/reporting_experiences/" class="navItem">{$texts['Reporting_experiences']}</a>
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Other']}</div>
      <a href="/decorrespondent" class="navItem">{$texts['The_correspondent_week']}</a>
      <a href="/export/" class="navItem">{$texts['Export_data']}</a>
      <a href="/aboutthissite/" class="navItem">{$texts['About_this_site']}</a>
    </div>

    <div id="navigationAdmin" data-moderator>    
      <div class="navigationSectionHeader">{$texts['Admin']}</div>
  
      <div class="navigationSection">
        <a href="/admin/humans" class="navItem" data-admin>{$texts['Humans']}</a>
        <a href="/moderations/" class="navItem">{$texts['Moderations']}</a>
        <a href="/admin/translations/" class="navItem" data-moderator>{$texts['Translations']}</a>
        <a href="/admin/longtexts/" class="navItem" data-moderator>{$texts['Long_texts']}</a>
        <a href="/last_changed" class="navItem">{$texts['Last_modified_crashes']}</a>
      </div>      
    </div>
    
    <div class="navFooter">
     {$texts['Version']} $VERSION • $VERSION_DATE 
    </div>   
 
  </div>  
</div>
HTML;
}

function getLoginForm() {
  $texts = translateArray(['Cancel', 'Email', 'Log_in_or_register', 'First_name', 'Last_name', 'Password', 'Confirm_password', 'Log_in',
    'Register', 'Stay_logged_in', 'Forgot_password']);

  return <<<HTML
<div id="formLogin" class="popupOuter">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="checkLogin(); return false;">

    <div class="popupHeader">{$texts['Log_in_or_register']}</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>

    <label for="loginEmail">{$texts['Email']}</label>
    <input id="loginEmail" class="popupInput" type="email" autocomplete="email">
    
    <div id="divFirstName" class="displayNone flexColumn">
      <label for="loginFirstName">{$texts['First_name']}</label>
      <input id="loginFirstName" class="popupInput" autocomplete="given-name" type="text">
    </div>
  
    <div id="divLastName" class="displayNone flexColumn">
      <label for="loginLastName">{$texts['Last_name']}</label>
      <input id="loginLastName" class="popupInput" autocomplete="family-name" type="text">
    </div>
    
    <label for="loginPassword">{$texts['Password']}</label>
    <input id="loginPassword" class="popupInput" type="password" autocomplete="current-password">

    <div id="divPasswordConfirm" class="displayNone flexColumn">
      <label for="loginPasswordConfirm">{$texts['Confirm_password']}</label>
      <input id="loginPasswordConfirm" class="popupInput" type="password" autocomplete="new-password">
    </div>   
    
    <label><input id="stayLoggedIn" type="checkbox" checked>{$texts['Stay_logged_in']}</label>

    <div id="loginError" class="formError"></div>

    <div class="popupFooter">
      <input id="buttonLogin" type="submit" class="button" style="margin-left: 0;" value="{$texts['Log_in']}">
      <input id="buttonRegistreer" type="button" class="button buttonGray" value="{$texts['Register']}" onclick="checkRegistration();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">

      <span onclick="loginForgotPassword()" style="margin-left: auto; text-decoration: underline; cursor: pointer;">{$texts['Forgot_password']}</span>
    </div>
    
  </form>
</div>  
HTML;
}

function getFormEditCrash(){
  $texts = translateArray(['Article', 'Crash', 'Fetch_article', 'Link_url', 'Title', 'Media_source', 'Summary',
    'Full_text',
    'Photo_link_url', 'Same_as_article', 'Add_humans', 'Publication_date', 'Text', 'Date', 'Involved_humans',
    'Animals', 'Traffic_jam_disruption', 'One-sided_crash',
    'Location', 'Characteristics', 'Save', 'Cancel',
    'Spider_is_working', 'Full_text_info', 'Link_info', 'Accident_date_info', 'Accident_text_info', 'Edit_location_instructions']);

  $htmlSearchCountry = getSearchCountryHtml('', 'editCrashCountry');

  return <<<HTML
<div id="formEditCrash" class="popupOuter">

  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader"></div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <div id="editArticleSection" class="flexColumn">
      <div class="formSubHeader">{$texts['Article']}</div>

      <input id="articleIDHidden" type="hidden">
  
      <div class="labelDiv">
        <label for="editArticleUrl">{$texts['Link_url']}</label>
        <span class="iconTooltip" data-tippy-content="{$texts['Link_info']}"></span>
      </div>

      <div style="display: flex;">
        <input id="editArticleUrl" class="popupInput" type="url" maxlength="1000" autocomplete="off">
        <div class="button buttonLine" onclick="getArticleMetaData();">{$texts['Fetch_article']}</div>
      </div>
  
      <div id="spinnerMeta" class="spiderBackground">
        <div class="popupHeader">{$texts['Spider_is_working']}</div>
        <div><img src="/images/tarantula.jpg" style="height: 200px;" alt="Spider"></div>
        <div id="tarantulaResults"></div> 
      </div>
  
      <label for="editArticleSiteName">{$texts['Media_source']}</label>
      <input id="editArticleSiteName" class="popupInput" type="text" maxlength="200" autocomplete="off" data-readonlyhelper>
     
      <label for="editArticleTitle">{$texts['Title']}</label>
      <input id="editArticleTitle" class="popupInput" type="text" maxlength="500" autocomplete="off" data-readonlyhelper>
  
      <label for="editArticleText">{$texts['Summary']}</label>
      <textarea id="editArticleText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off" data-readonlyhelper></textarea>
   
      <label for="editArticleUrlImage">{$texts['Photo_link_url']}</label>
      <input id="editArticleUrlImage" class="popupInput" type="url" maxlength="1000" autocomplete="off" data-readonlyhelper>
      
      <label for="editArticleDate">{$texts['Publication_date']}</label>
      <input id="editArticleDate" class="popupInput" type="date" autocomplete="off">

      <div class="labelDiv">
        <label for="editArticleAllText">{$texts['Full_text']}</label>
        <span class="iconTooltip" data-tippy-content="{$texts['Full_text_info']}"></span>
      </div>
      <textarea id="editArticleAllText" maxlength="10000" style="height: 150px; resize: vertical;" class="popupInput" autocomplete="off"></textarea>
    </div>

    <div id="editCrashSection" class="flexColumn">
      <div class="formSubHeader">{$texts['Crash']}</div>
     
      <input id="crashIDHidden" type="hidden">
  
      <div data-crash-edit-only class="flexColumn">
        <label for="editCrashTitle">{$texts['Title']}</label> 
        <div style="display: flex;">
          <input id="editCrashTitle" class="popupInput" type="text" maxlength="500" autocomplete="off" data-readonlyhelper>
          <span data-hideedit class="button buttonGray buttonLine" onclick="copyCrashInfoFromArticle();">{$texts['Same_as_article']}</span>
        </div>
  
        <div class="labelDiv">
          <label for="editCrashText">{$texts['Text']}</label>
          <span class="iconTooltip" data-tippy-content="{$texts['Accident_text_info']}"></span>
        </div>
        <textarea id="editCrashText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off" data-readonlyhelper></textarea>
      </div>        

      <div class="labelDiv">
        <label for="editCrashDate">{$texts['Date']}</label>
        <span class="iconTooltip" data-tippy-content="{$texts['Accident_date_info']}"></span>
      </div>
      <div style="display: flex;">
        <input id="editCrashDate" class="popupInput" type="date" autocomplete="off">
        <span data-hideedit class="button buttonGray buttonLine" onclick="copyCrashDateFromArticle();" ">{$texts['Same_as_article']}</span>
      </div>
          
      <div style="margin-top: 5px;">
        <div>{$texts['Involved_humans']} <div class="button buttonGray buttonLine" role="button" onclick="showEditPersonForm();">{$texts['Add_humans']}</div></div>   
        <div id="editCrashPersons"></div>
      </div>

      <div style="margin-top: 5px;">
        <div>{$texts['Characteristics']}</div>
        <div>
          <span id="editCrashUnilateral" class="menuButton bgUnilateral" data-tippy-content="{$texts['One-sided_crash']}" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashPet" class="menuButton bgPet" data-tippy-content="{$texts['Animals']}" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashTrafficJam" class="menuButton bgTrafficJam" data-tippy-content="{$texts['Traffic_jam_disruption']}" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashTree" style="display: none;" class="menuButton bgTree" data-tippy-content="Boom/Paal" onclick="toggleSelectionButton(this);"></span>
        </div>
      </div>
      
      <div style="margin-top: 5px;">
        <div>{$texts['Location']} $htmlSearchCountry
        <span class="iconTooltip" data-tippy-content="{$texts['Edit_location_instructions']}"></span></div>
            
        <input id="editCrashLatitude" type="hidden"><input id="editCrashLongitude" type="hidden">
               
        <div id="mapEdit"></div>
      </div>      
      
    </div>
            
    <div class="popupFooter">
      <input id="buttonSaveArticle" type="button" class="button" value="{$texts['Save']}" onclick="saveArticleCrash();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
}

function getFormCrash(){
  return <<<HTML
<div id="formCrash" class="popupOuter" onclick="closeCrashDetails();">
    <div class="popupCloseCrossWhiteFullScreen hideOnMobile" onclick="closeCrashDetails();"></div>

  <div class="formFullPage" onclick="event.stopPropagation();">    
    <div class="showOnMobile" style="height: 15px"></div>
    <div class="popupCloseCross showOnMobile" onclick="closeCrashDetails();"></div>

    <div id="crashDetails" class="flexColumn">
    </div>
  </div>
  
</div>
HTML;
}

function getFormQuestionnaires() {

  return <<<HTML
<div id="formQuestions" class="popupOuter">

  <div class="formFullPage" onclick="event.stopPropagation();">

    <input id="questionsArticleId" type="hidden">
    
    <div class="popupHeader">Article questions</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div style="text-align: right;">
      <div class="button buttonGray" onclick="nextArticleQuestions(false);">Previous article</div>
      <div class="button buttonGray" onclick="nextArticleQuestions(true);">Next article</div>
    </div>    

    <div id="articleQuestions" class="flexColumn">Loading...</div>
                    
    <div class="sectionHeader">Crash <span id="buttonEditCrash" class="link" style="font-weight: normal; margin-bottom: 5px;">view/edit</span></div>
    <div id="questionsCrashButtons" style="display: flex;"></div>

    <div class="sectionHeader">Article link</div>
    <div id="questionsArticle"></div>

    <div class="sectionHeader">Article title</div>
    <div id="questionsArticleTitle" class="readOnlyInput"></div>
    
    <div class="sectionHeader">Article text</div>
    <div id="questionsArticleText" class="readOnlyInput"></div>
        
  </div>
  
</div>
HTML;
}

function getFormMergeCrash(){

  $texts = translateArray(['Cancel']);

  return <<<HTML
<div id="formMergeCrash" class="popupOuter" onclick="closePopupForm();">

  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div class="popupHeader">Ongeluk samenvoegen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <input id="mergeFromCrashIDHidden" type="hidden">

    <div class="flexColumn">
      <div class="formSubHeader">Ongeluk</div>
     
      <div id="mergeCrashFrom" class="crashRow"></div>
    </div>
            
    <div id="mergeCrashSection" class="flexColumn">
      <div class="formSubHeader">Samenvoegen met</div>
     
      <input id="mergeToCrashIDHidden" type="hidden">
      <div id="mergeCrashTo" class="crashRow"></div>  
  
      <div class="flexRow">
        
        <div class="flexColumn" style="flex-grow: 1;">
          <label for="mergeCrashSearch">Zoek ongeluk</label>
          <input id="mergeCrashSearch" class="popupInput" style="margin-right: 5px;" type="search" autocomplete="off"  placeholder="Zoek tekst" onkeyup="searchMergeCrashDelayed();">
        </div>
        
        <div class="flexColumn">
        <label>Datum</label>
        <select id="mergeCrashSearchDay" oninput="searchMergeCrashDelayed();">
          <option value=""></option>
          <option value="0">Zelfde dag</option>
          <option value="1">1 dag marge</option>
          <option value="2">2 dagen marge</option>
          <option value="7">7 dagen marge</option>
          <option value="30">30 dagen marge</option>
        </select>
        </div>
      </div>

      <div id="spinnerMerge" class="spinnerLine"><img src="/images/spinner.svg" alt="Spinner"></div>
      <div id="mergeSearchResults"></div>  
    </div>
            
    <div class="popupFooter">
      <input id="buttonMergeArticle" type="button" class="button" value="Voeg samen" onclick="mergeCrash();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
}

function getFormEditPerson(){
  $texts = translateArray(['Transportation_mode', 'Characteristics', 'Child', 'Intoxicated', 'Drive_on_or_fleeing', 'Injury',
    'Close', 'Delete', 'Save_and_stay_open', 'Save_and_close']);

  return <<<HTML
<div id="formEditPerson" class="popupOuter" style="z-index: 501;" onclick="closeEditPersonForm();">

  <div class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editPersonHeader" class="popupHeader">Nieuw mens toevoegen</div>
    <div class="popupCloseCross" onclick="closeEditPersonForm();"></div>

    <input id="personIDHidden" type="hidden">

    <div style="margin-top: 5px;">
      <div>${texts['Transportation_mode']}</div> 
      <div id="personTransportationButtons"></div>
    </div>
            
    <div style="margin-top: 5px;">
      <div>{$texts['Injury']}</div> 
      <div id="personHealthButtons"></div>
    </div>

    <div style="margin-top: 5px;">
      <div>{$texts['Characteristics']}</div> 
      <div>
        <span id="editPersonChild" class="menuButton bgChild" data-tippy-content="{$texts['Child']}" onclick="toggleSelectionButton(this)"></span>            
        <span id="editPersonUnderInfluence" class="menuButton bgAlcohol" data-tippy-content="{$texts['Intoxicated']}" onclick="toggleSelectionButton(this)"></span>            
        <span id="editPersonHitRun" class="menuButton bgHitRun" data-tippy-content="{$texts['Drive_on_or_fleeing']}" onclick="toggleSelectionButton(this)"></span>            
      </div>
    </div>
            
    <div class="popupFooter">
      <input type="button" class="button" value="{$texts['Save_and_stay_open']}" onclick="savePerson(true);">
      <input type="button" class="button" value="{$texts['Save_and_close']}" onclick="savePerson();">
      <input id="buttonCloseEditPerson" type="button" class="button buttonGray" value="{$texts['Close']}" onclick="closeEditPersonForm();">
      <input id="buttonDeletePerson" type="button" class="button buttonRed" value="{$texts['Delete']}" onclick="deletePerson();">
    </div>    
  </div>
  
</div>
HTML;
}

function getFormEditUser(){

  $texts = translateArray(['Save', 'Cancel', 'Helper', 'Moderator', 'Administrator', 'Email', 'First_name',
    'Last_name', 'Permission']);

  return
    <<<HTML
<div id="formEditUser" class="popupOuter" onclick="closePopupForm();">
  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader">Mens aanpassen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <input id="userID" type="hidden">

    <label for="userEmail">{$texts['Email']}</label>
    <input id="userEmail" class="inputForm" style="margin-bottom: 10px;" type="text"">
      
    <label for="userFirstName">{$texts['First_name']}</label>
    <input id="userFirstName" class="inputForm" style="margin-bottom: 10px;" type="text"">
  
    <label for="userLastName">{$texts['Last_name']}</label>
    <input id="userLastName" class="inputForm" style="margin-bottom: 10px;" type="text"">

    <label for="userPermission">{$texts['Permission']}</label>
    <select id="userPermission">
      <option value="0">{$texts['Helper']} (ongelukken worden gemodereerd)</option>
      <option value="2">{$texts['Moderator']} (kan alle ongelukken bewerken)</option>
      <option value="1">{$texts['Administrator']}</option>
    </select>
    
    <div id="editUserError" class="formError"></div>
   
    <div class="popupFooter">
      <input type="button" class="button" value="{$texts['Save']}" onclick="saveUser();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>    
  </form>
</div>
HTML;
}

function getSearchPeriodHtml($onInputFunctionName = ''){
  $texts = translateArray(['Always', 'Today', 'Yesterday', 'days', 'The_correspondent_week', 'Custom_period', 'Period', 'Start_date', 'End_date']);

  $onInputFunction = $onInputFunctionName === ''? '' : $onInputFunctionName . '();';
  $onInputSelect   = 'oninput="setCustomRangeVisibility();' . $onInputFunction . '"';
  $onInputDates    = $onInputFunction? 'oninput="' . $onInputFunction . '"' : '';

  return <<<HTML
<div class="toolbarItem">
  <select id="searchPeriod" class="searchInput" $onInputSelect data-tippy-content="{$texts['Period']}">
    <option value="all" selected>{$texts['Always']}</option> 
    <option value="today">{$texts['Today']}</option> 
    <option value="yesterday">{$texts['Yesterday']}</option> 
    <option value="7days">7 {$texts['days']}</option> 
    <option value="30days">30 {$texts['days']}</option> 
    <option value="2024">2024</option>
    <option value="2023">2023</option>
    <option value="2022">2022</option>
    <option value="2021">2021</option>
    <option value="2020">2020</option>
    <option value="2019">2019</option> 
    <option value="custom">{$texts['Custom_period']}</option>          
  </select>
</div>

<input id="searchDateFrom" class="searchInput toolbarItem" type="date" data-tippy-content="{$texts['Start_date']}" $onInputDates>
<input id="searchDateTo" class="searchInput toolbarItem" type="date" data-tippy-content="{$texts['End_date']}" $onInputDates>
HTML;
}

function getSearchPersonsHtml() {
  $texts = translateArray(['Humans']);

  return <<<HTML
    <div class="toolbarItem">
      <div class="dropInputWrapper">
        <div class="searchInput dropInput" tabindex="0" onclick="toggleSearchPersons(event);">
          <span id="inputSearchPersons">{$texts['Humans']}</span>
          <div id="arrowSearchPersons" class="inputArrowDown"></div>  
        </div>
        
        <div id="searchSearchPersons" class="searchResultsPopup" onclick="event.stopPropagation();"></div>
      </div>      
    </div>
HTML;

}

function getSearchCountryHtml(string $onInputFunctionName = '', string $elementId='searchCountry', string $selectedCountryId=''): string {
  global $database;
  global $user;

  if ($selectedCountryId === '') $selectedCountryId = $user->country['id'];

  $texts = translateArray(['Country']);

  $onInputFunction = $onInputFunctionName === ''? '' : 'oninput="' . $onInputFunctionName . '();"';

  $countryOptions = '';
  foreach ($database->countries as $country) {
    $selected = $country['id'] === $selectedCountryId? "selected" : '';
    $countryOptions .= "<option value='{$country['id']}' $selected>{$country['name']}</option>";
  }

  return <<<HTML
<select id="$elementId" class="searchInput" $onInputFunction data-tippy-content="{$texts['Country']}">
  $countryOptions
</select>
HTML;
}