<?php

class HtmlBuilder {

  public static function getHTMLBeginMain(string $pageTitle='', string $head='', string $initFunction='',
      string $searchFunction='', bool $showButtonAdd=false): string {
    global $VERSION;

    $texts = translateArray(['Cookie_warning', 'More_info', 'Accept', 'Add']);
    $addSearchBar = $searchFunction !== '';

    if (! empty($pageTitle)) $title = $pageTitle . ' | ' . WEBSITE_NAME;
    else $title = WEBSITE_NAME;

    $initScript = ($initFunction !== '') ? "<script>document.addEventListener('DOMContentLoaded', $initFunction)</script>" : '';
    $navigation = self::getNavigation();

    $cookiesAccepted = $_COOKIE['cookiesAccepted'] ?? null === '1';

    if (! $cookiesAccepted) {
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

    $websiteName = WEBSITE_NAME;

    global $database;
    global $user;

    // Add countries
    $countryOptions = '';
    foreach ($database->countries as $country) {
      $class = $country['id'] === $user->countryId ? "class='menuSelected'" : '';
      $countryOptions .= "<div $class onclick=\"selectLocation('{$country['id']}')\"><div class='menuIcon' style='margin-right: 5px;background-image: url({$country['flagFile']});'></div>{$country['name']}</div>";
    }

    $languages = $database->fetchAll("SELECT id, name FROM languages ORDER BY name;");
    $languageOptions = '';
    foreach ($languages as $language) {
      $class = $language['id'] === $user->languageId ? "class='menuSelected'" : '';
      $languageOptions .= "<div $class onclick=\"setLanguage('{$language['id']}')\">{$language['name']}</div>";
    }

    $texts = translateArray(['Log_out', 'Log_in', 'Account', 'Location', 'Language']);

    if ($searchFunction === 'searchCrashes') {
      $keySearchFunction = 'keySearchCrashes';
    } else {
      $keySearchFunction = '';
    }

    $htmlSearchBar = $addSearchBar ? self::getHtmlSearchBar($searchFunction, $keySearchFunction) : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="$user->languageId">
<head>
<link href="/main.css?v=$VERSION" rel="stylesheet" type="text/css">
<link rel="shortcut icon" type="image/png" href="/images/favicon.svg">
<script src="/scripts/popper.min.js"></script><script src="/scripts/tippy-bundle.umd.min.js"></script>
<script src='/js/utils.js?v=$VERSION'></script>
$head
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="utf-8">
<title>$title</title>

$initScript

</head>
<body style="overscroll-behavior-x: none;">
$navigation

<div class="flexToFullPage">

  <div id="topBar">
    <div style="display: inline-flex; align-items: center;">
      <span class="menuButtonBlack bgMenuWhite" onclick="toggleNavigation(event);"></span>
      <img id="spinnerHeader" src="/images/spinner.svg" style="display: none; height: 17px; margin-left: 5px;" alt="Spinner">
    </div>
  
    <div class="headerMain">
      <a href='/'class="pageTitle">$websiteName</a>        
  
      <div style="position: relative;">
        <div id="filterCountry" class="buttonHeader" style="height: auto; width: auto; display: none;" onclick="countryClick();">
          <img id="filterCountryFlag" src="/images/flags/un.svg" style="width: 15px; height: 15px; margin-right: 3px;">
          <div id="filterCountryName">nbsp;</div>
          <div class="bgArrowDownWhite" style="width: 15px; height: 15px; position: relative; top: 2px; margin-left: 3px;"></div> 
        </div>     
          
        <div id="menuCountries" class="buttonPopupMenu">
          <div class="navigationSectionHeader">{$texts['Location']}</div>
          $countryOptions
        </div>      
      </div>
      
    </div>
   
    <div style="display: flex; position: relative; justify-self: flex-end;">
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
        <div id="buttonLanguages" class="buttonHeader" onclick="languageClick();">
          <div class="buttonIcon buttonInsideMargin bgLanguageWhite"></div>
        </div>
        
        <div id="menuLanguages" class="buttonPopupMenu" style="right: 0;">
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

  public static function getHtmlSearchBar(string $searchFunction, string $keySearchFunction='', bool $inline=false,
                                          bool $addPersons=true, bool $addHealth=true): string {
    $texts = translateArray(['Child', 'Dead_(adjective)', 'Injured', 'Search', 'Source', 'Search_text_hint']);

    $htmlSearchPeriod = self::getSearchPeriodHtml();
    $htmlSearchPersons = $addPersons? self::getSearchPersonsHtml() : '';

    $searchBarClass = $inline ?'searchBarTransparent' : 'searchBar';
    $htmlCloseSearchBar = $inline ? '' : '<div class="popupCloseCross closeCrossWhite" onclick="toggleSearchBar();"></div>';
    $htmlKeyUp = $keySearchFunction === ''? '' : "onkeyup='{$keySearchFunction}()'";

    $htmlHealth = '';
    if ($addHealth) {
      $htmlHealth = <<<HTML
      <span id="searchPersonHealthDead" class="menuButtonBlack bgDeadWhite" data-tippy-content="{$texts['Dead_(adjective)']}" onclick="selectSearchPersonDead();"></span>      
      <span id="searchPersonHealthInjured" class="menuButtonBlack bgInjuredWhite" data-tippy-content="{$texts['Injured']}" onclick="selectSearchPersonInjured();"></span>
HTML;

    }

    return <<<HTML
  <div id="searchBar" class="$searchBarClass">
    $htmlCloseSearchBar

    <div class="toolbarItem">
      $htmlHealth
      <span id="searchPersonChild" class="menuButtonBlack bgChildWhite" data-tippy-content="{$texts['Child']}" onclick="selectSearchPersonChild();"></span>      
    </div>
   
    <div class="toolbarItem">
       <input id="searchText" class="searchInput textInputWidth"  type="search" data-tippy-content="{$texts['Search_text_hint']}" placeholder="{$texts['Search']}" $htmlKeyUp autocomplete="off">  
    </div>

    $htmlSearchPeriod
    
    $htmlSearchPersons    
           
    <div class="toolbarItem">
      <input id="searchSiteName" class="searchInput textInputWidth" type="search" placeholder="{$texts['Source']}" $htmlKeyUp autocomplete="off">
    </div>

    <div class="toolbarItem">
      <div class="button buttonMobileSmall buttonImportant" style="margin-left: 0;" onclick="$searchFunction()">{$texts['Search']}</div>
    </div>
  </div>      
HTML;

  }

  public static function getHTMLEnd($htmlEnd = ''): string {
    $forms = self::getHTMLConfirm() . self::getLoginForm() . self::getFormCrash() . self::getFormEditCrash() . self::getFormMergeCrash() .
      self::getFormEditPerson() . self::getFormQuestionnaires();
    return <<<HTML
</div>
    $htmlEnd 
    <div id="floatingMessage" onclick="closeMessage();">
      <div id="messageCloseCross" class="popupCloseCross"></div>
      <div id="messageText"></div>
    </div>
    <div style="clear: both;"></div>
   
    $forms
</body>
HTML;
  }

  public static function getHTMLConfirm(): string {
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

  public static function getNavigation(): string {

    global $VERSION;
    global $VERSION_DATE;

    $texts = translateArray(['Admin', 'Crashes', 'Statistics', 'Translations', 'Long_texts', 'Other', 'Recent_crashes',
      'Child_victims', 'Mosaic', 'The_correspondent_week', 'Map', 'General', 'deadly_crashpartners',
      'Counterparty_in_crashes', 'Transportation_modes', 'Export_data', 'About_this_site', 'Humans', 'Moderations', 'Last_modified_crashes', 'Options',
      'Version', 'Questionnaires', 'fill_in', 'settings', 'results', 'AI_prompt_builder', 'Research',
      'Graphs_and_statistics', 'Media_humanization_test', 'Tools', 'Reframe']);

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
      <a href="/research/questionnaires/settings" class="navItem" data-admin>{$texts['Questionnaires']} | {$texts['settings']}</a>
      <a href="/research/questionnaires/" class="navItem">{$texts['Questionnaires']} | {$texts['results']}</a>
      <a href="/research/questionnaires/fill_in" class="navItem" data-moderator>{$texts['Questionnaires']} | {$texts['fill_in']}</a>
      <a href="/research/ai_prompt_builder/" class="navItem" data-moderator>{$texts['AI_prompt_builder']}</a>
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Tools']}</div>
      <a href="/reframe" target="tools" class="navItem">{$texts['Reframe']}</a>
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

  public static function getLoginForm(): string {
    $texts = translateArray(['Cancel', 'Email', 'Log_in_or_register', 'First_name', 'Last_name', 'Password', 'Confirm_password', 'Log_in',
      'Register', 'Stay_logged_in', 'Forgot_password']);

    return <<<HTML
<div id="formLogin" class="popupOuter">
  <form id="formLoginInner" class="formFullPage" onclick="event.stopPropagation();" onsubmit="checkLogin(); return false;">

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

  public static function getFormEditCrash(): string {
    $texts = translateArray(['Article', 'Crash', 'Fetch_article', 'Link_url', 'Title', 'Media_source', 'Summary',
      'Full_text', 'Extract_data_from_text', 'Ai_extract_info',
      'Photo_link_url', 'Same_as_article', 'Select_humans', 'Publication_date', 'Text', 'Date', 'Involved_humans',
      'Animals', 'Traffic_jam_disruption', 'One-sided_crash',
      'Location', 'Characteristics', 'Save', 'Cancel',
      'Fetching_web_page', 'Full_text_info', 'Link_info', 'Accident_date_info', 'Edit_location_instructions']);

    $htmlLocation = self::getSearchLocationHtml( 'editCrashCountry');

    return <<<HTML
<div id="formEditCrash" class="popupOuter">

  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader"></div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <div style="overflow-y: auto">
    <div class="flexColumn">
      
      <div class="formSubHeader" style="display: flex; align-items: center; justify-content: space-between;">
        <div>{$texts['Article']}</div>
        <div id="articleInfo" class="smallFont" style="font-weight: normal;">ID: 123</div>
      </div>
    
      
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
        <div class="popupHeader">{$texts['Fetching_web_page']}</div>
        <div id="spiderResults"></div> 
      </div>
  
      <label for="editArticleSiteName">{$texts['Media_source']}</label>
      <input id="editArticleSiteName" class="popupInput" type="text" maxlength="200" autocomplete="off">
     
      <label for="editArticleTitle">{$texts['Title']}</label>
      <input id="editArticleTitle" class="popupInput" type="text" maxlength="500" autocomplete="off">
  
      <label for="editArticleText">{$texts['Summary']}</label>
      <textarea id="editArticleText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off"></textarea>
   
      <label for="editArticleUrlImage">{$texts['Photo_link_url']}</label>
      <input id="editArticleUrlImage" class="popupInput" type="url" maxlength="1000" autocomplete="off">
      
      <label for="editArticleDate">{$texts['Publication_date']}</label>
      <input id="editArticleDate" class="popupInput" type="date" autocomplete="off">

      <div class="labelDiv">
        <label for="editArticleAllText">{$texts['Full_text']}</label>
        <span class="iconTooltip" data-tippy-content="{$texts['Full_text_info']}"></span>
      </div>
      <textarea id="editArticleAllText" maxlength="10000" style="height: 150px; resize: vertical;" class="popupInput" autocomplete="off"></textarea>
    
      <div style="display: flex; flex-direction: column;">
        <div style="display: flex; align-items: center;">
      
          <button class="button buttonGray" style="margin: 5px 0;" onclick="extractDataFromArticle();">{$texts['Extract_data_from_text']}</button>
          <img id="spinnerExtractData" src="/images/spinner_black.svg" style="display: none; height: 17px; margin-left: 5px;" alt="Spinner">
        </div>
        <div id="ai_extract_info" class="smallFont notice">{$texts['Ai_extract_info']}</div>
      </div>  
    </div>

    <div class="flexColumn">
      <div class="formSubHeader">{$texts['Crash']}</div>
     
      <input id="crashIDHidden" type="hidden">
  
      <div class="labelDiv">
        <label for="editCrashDate">{$texts['Date']}</label>
        <span class="iconTooltip" data-tippy-content="{$texts['Accident_date_info']}"></span>
      </div>
      <div style="display: flex;">
        <input id="editCrashDate" class="popupInput" type="date" autocomplete="off">
        <span class="button buttonGray buttonLine" onclick="copyCrashDateFromArticle();">{$texts['Same_as_article']}</span>
      </div>
          
      <div class="labelDiv">
        <div>{$texts['Involved_humans']}</div>   
        <div id="editCrashPersons"></div>
        <div class="button buttonGray" role="button" style="margin: 5px 0;" onclick="showSelectHumansForm();">{$texts['Select_humans']}</div>
      </div>

      <div class="labelDiv">
        <div>{$texts['Characteristics']}</div>
        <div>
          <span id="editCrashUnilateral" class="menuButton bgUnilateral" data-tippy-content="{$texts['One-sided_crash']}" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashPet" class="menuButton bgPet" data-tippy-content="{$texts['Animals']}" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashTrafficJam" class="menuButton bgTrafficJam" data-tippy-content="{$texts['Traffic_jam_disruption']}" onclick="toggleSelectionButton(this);"></span>      
        </div>
      </div>
      
      <div class="labelDiv">
        <div>{$texts['Location']} $htmlLocation
        <span class="iconTooltip" data-tippy-content="{$texts['Edit_location_instructions']}"></span></div>
            
        <input id="editCrashLatitude" type="hidden"><input id="editCrashLongitude" type="hidden">
        <div id="locationDescription" class="smallFont"></div>

        <div style="position: relative;">
          <div style="position: absolute; z-index: 1; top: 10px; left: 10px; user-select: none;">
            <div class="buttonMap bgZoomIn" onclick="zoomEditMap(1);"></div>
            <div class="buttonMap bgZoomOut" onclick="zoomEditMap(-1);"></div>
          </div>
          <div id="mapEdit"></div>
        </div>

      </div>      
      
    </div>
    </div>
            
    <div class="popupFooter">
      <input type="button" class="button" value="{$texts['Save']}" onclick="saveArticleCrash();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
  }

  public static function getFormCrash(): string {
    return <<<HTML
<div id="formCrash" class="popupOuter" onclick="closeCrashDetails();">
    <div class="popupCloseCrossWhiteFullScreen hideOnMobile" onclick="closeCrashDetails();"></div>

  <div class="formFullPage" onclick="event.stopPropagation();">    
    <div class="showOnMobile" style="height: 15px"></div>
    <div class="popupCloseCross showOnMobile" onclick="closeCrashDetails();"></div>
    
    <div style="overflow-y: auto;">
      <div id="crashDetails" class="flexColumn"></div>
    </div>
  </div>
  
</div>
HTML;
  }

  public static function getFormQuestionnaires(): string {

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

  public static function getFormMergeCrash(): string {
    $texts = translateArray(['Cancel', 'Merge', 'Date', 'Crash', 'Merge_crashes', 'Search']);

    return <<<HTML
<div id="formMergeCrash" class="popupOuter" onclick="closePopupForm();">

  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div class="popupHeader">{$texts['Merge_crashes']}</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <input id="mergeFromCrashIDHidden" type="hidden">

    <div class="flexColumn">
      <div class="formSubHeader">{$texts['Crash']}</div>
     
      <div id="mergeCrashFrom" class="crashRow"></div>
    </div>
            
    <div id="mergeCrashSection" class="flexColumn">
      <div class="formSubHeader">{$texts['Merge']}</div>
     
      <input id="mergeToCrashIDHidden" type="hidden">
      <div id="mergeCrashTo" class="crashRow"></div>  
  
      <div class="flexRow">
        
        <div class="flexColumn" style="flex-grow: 1;">
          <label for="mergeCrashSearch">&nbsp;</label>
          <input id="mergeCrashSearch" class="popupInput" style="margin-right: 5px;" type="search" autocomplete="off"  placeholder="{$texts['Search']}" onkeyup="searchMergeCrashDelayed();">
        </div>
        
        <div class="flexColumn">
        <label>{$texts['Date']}</label>
        <select id="mergeCrashSearchDay" oninput="searchMergeCrashDelayed();">
          <option value=""></option>
          <option value="0">Same day</option>
          <option value="1">1 day margin</option>
          <option value="2">2 days margin</option>
          <option value="7">7 days margin</option>
          <option value="30">30 days margin</option>
        </select>
        </div>
      </div>

      <div id="spinnerMerge" class="spinnerLine"><img src="/images/spinner.svg" alt="Spinner"></div>
      <div id="mergeSearchResults"></div>  
    </div>
            
    <div class="popupFooter">
      <input id="buttonMergeArticle" type="button" class="button" value="{$texts['Merge']}" onclick="mergeCrash();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
  }

  public static function getFormEditPerson(): string {
    $texts = translateArray(['Select_humans', 'Involved_humans', 'Add_human', 'Transportation_mode', 'Characteristics',
      'Child', 'Intoxicated', 'Drive_on_or_fleeing', 'Injury',
      'Close', 'Delete', 'Add']);

    return <<<HTML
<div id="formEditPerson" class="popupOuter" style="z-index: 501;" onclick="closeEditPersonForm();">

  <div class="formFullPage" onclick="event.stopPropagation();">
    
    <div class="popupHeader">{$texts['Select_humans']}</div>
    <div class="popupCloseCross" onclick="closeEditPersonForm();"></div>

    <div class="formSubHeader">{$texts['Add_human']}</div>

    <div style="border: solid 1px #000; border-radius: 5px; padding: 5px 5px 8px 5px;">
      <div>
        <div>{$texts['Transportation_mode']}</div> 
        <div id="personTransportationButtons"></div>
      </div>
              
      <div class="labelDiv">
        <div>{$texts['Injury']}</div> 
        <div id="personHealthButtons"></div>
      </div>
  
      <div class="labelDiv">
        <div>{$texts['Characteristics']}</div> 
        <div>
          <span id="editPersonChild" class="menuButton bgChild" data-tippy-content="{$texts['Child']}" onclick="toggleSelectionButton(this)"></span>            
          <span id="editPersonUnderInfluence" class="menuButton bgAlcohol" data-tippy-content="{$texts['Intoxicated']}" onclick="toggleSelectionButton(this)"></span>            
          <span id="editPersonHitRun" class="menuButton bgHitRun" data-tippy-content="{$texts['Drive_on_or_fleeing']}" onclick="toggleSelectionButton(this)"></span>            
        </div>
      </div>
  
      <div style="margin-top: 5px;">
        <input type="button" class="button" value="{$texts['Add']}" onclick="addHuman();">
      </div>
    </div>
    
    <div class="formSubHeader">{$texts['Involved_humans']}</div>
    <div id="crashPersons" style="display: flex; justify-content: center;"></div>
                
    <div class="popupFooter">
      <input id="buttonCloseEditPerson" type="button" class="button buttonGray" value="{$texts['Close']}" onclick="closeEditPersonForm();">
    </div>    
  </div>
  
</div>
HTML;
  }

  public static function getFormEditUser(): string {

    $texts = translateArray(['Save', 'Cancel', 'Edit_human', 'Helper', 'Moderator', 'Administrator', 'Email', 'First_name',
      'Last_name', 'Permission']);

    return
      <<<HTML
<div id="formEditUser" class="popupOuter" onclick="closePopupForm();">
  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader">{$texts['Edit_human']}</div>
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
      <option value="0">{$texts['Helper']} (crashes are moderated)</option>
      <option value="2">{$texts['Moderator']} (can edit all crashes)</option>
      <option value="1">{$texts['Administrator']}</option>
    </select>
    
    <div class="popupFooter">
      <input type="button" class="button" value="{$texts['Save']}" onclick="saveUser();">
      <input type="button" class="button buttonGray" value="{$texts['Cancel']}" onclick="closePopupForm();">
    </div>    
  </form>
</div>
HTML;
  }

  public static function pageChildVictims(): string {
    global $user;
    $texts = translateArray(['Child_victims', 'Injury', 'Dead_(adjective)', 'Injured', 'Help_improve_data_accuracy']);
    $intro = $user->translateLongText('child_victims_info');

    return <<<HTML

<div id="pageMain">

  <div class="pageSubTitle"><img src="/images/child_white.svg" style="height: 20px; position: relative; top: 2px;"> {$texts['Child_victims']}</div>
  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="smallFont" style="text-decoration: underline; cursor: pointer" onclick="togglePageInfo();">{$texts['Help_improve_data_accuracy']}</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; max-width: 600px; margin: 10px 0;">
    $intro
  </div>

  <div id="searchBar" class="searchBarTransparent" style="display: flex; padding-bottom: 0;">

    <div class="toolbarItem">
      <span id="filterChildDead" class="menuButtonBlack bgDeadWhite" data-tippy-content="{$texts['Injury']}: {$texts['Dead_(adjective)']}" onclick="selectFilterChildVictims();"></span>      
      <span id="filterChildInjured" class="menuButtonBlack bgInjuredWhite" data-tippy-content="{$texts['Injury']}: {$texts['Injured']}" onclick="selectFilterChildVictims();"></span>      
    </div>
    
  </div>

  <div class="pageInner">
    <table class="dataTable">
      <tbody id="dataTableBody"></tbody>
    </table>  
  </div>

  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
   
</div>
HTML;
  }

  public static function pageMosaic(): string {
    return <<<HTML
<div id="pageMain">
  <div id="cards"></div>
  <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
</div>
HTML;

  }
  public static function pageHumanizationTest(): string {
    global $user;

    $texts = translateArray(['Media_humanization_test']);
    $infoText = $user->translateLongText('media_humanization_info');

    return <<<HTML
<div id="pageMain">

  <div style="width: 100%; max-width: 700px;">

  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="pageSubTitleFont">{$texts['Media_humanization_test']}</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; margin: 10px 0;">
</div>

  <div id="statistics">
  
    <div id="graphMediaHumanizationIntro" style="margin-top: 10px; display: none;">
      <div>$infoText</div>
      <div id="graphMediaHumanizationQuestions"></div>
    </div>
    
    <div id="graphMediaHumanization" style="position: relative;"></div>
   
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg" alt="Spinner"></div>
  </div>
</div>
HTML;
  }

  public static function pageStatsCrashPartners(): string {
    $texts = translateArray(['Counterparty_in_crashes', 'Always', 'days', 'the_correspondent_week', 'Custom_period',
      'Help_improve_data_accuracy', 'Child', 'Injury', 'Injured', 'Dead_(adjective)', 'Search_text_hint',
      'Search']);

    global $user;
    $infoText = $user->translateLongText('counter_party_info');

    $htmlSearchBar = self::getHtmlSearchBar(
      searchFunction: 'searchStatistics',
      keySearchFunction: 'startStatsSearchKey',
      inline: true,
      addPersons: false
    );

    return <<<HTML
<div id="pageMain">

  <div style="width: 100%; max-width: 700px;">

  <div style="display: flex; flex-direction: column; align-items: center">
    <div style="text-align: left;">
      <div class="pageSubTitleFont">{$texts['Counterparty_in_crashes']}</div>
      <div class="smallFont" style="text-decoration: underline; cursor: pointer" onclick="togglePageInfo();">{$texts['Help_improve_data_accuracy']}</div>
    </div>
  </div>
  
  <div id="pageInfo" style="display: none; margin: 10px 0;">
    $infoText
  </div>

  <div id="statistics">
  
    $htmlSearchBar

    <div id="graphWrapper" style="display: none; margin-top: 10px; padding: 5px;">
      <div id="graphPartners" style="position: relative;"></div>  
    </div>
   
  </div>
  
  <div id="spinnerLoad"><img src="/images/spinner.svg" alt="Spinner"></div>
  </div>
</div>
HTML;
  }

  public static function pageStatsTransportationModes(): string {
    $texts = translateArray(['Statistics', 'Transportation_modes', 'Transportation_mode', 'Child', 'Country',
      'Intoxicated', 'Drive_on_or_fleeing', 'Dead_(adjective)', 'Injured', 'Uninjured', 'Unknown', 'Search_text_hint',
      'Search']);

    $htmlSearchBar = self::getHtmlSearchBar(
      searchFunction: 'searchStatistics',
      inline: true,
      addPersons: false,
      addHealth: false);

    return <<<HTML
<div id="pageMain">
  <div class="pageInner">
    <div class="pageSubTitle">{$texts['Statistics']} - {$texts['Transportation_modes']}</div>
    
    <div id="statistics">
    
      $htmlSearchBar   
  
      <table class="dataTable" style="margin-top: 10px;">
        <thead>
          <tr>
            <th style="text-align: left;">{$texts['Transportation_mode']}</th>
            <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgDeadWhite" data-tippy-content="{$texts['Dead_(adjective)']}"></div> <div class="hideOnMobile">{$texts['Dead_(adjective)']}</div></div></th>
            <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgInjuredWhite" data-tippy-content="{$texts['Injured']}"></div> <div  class="hideOnMobile">{$texts['Injured']}</div></div></th>
            <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUninjured" data-tippy-content="{$texts['Uninjured']}"></div> <div  class="hideOnMobile">{$texts['Uninjured']}</div></div></th>
            <th><div class="flexRow" style="justify-content: flex-end;"><div class="iconSmall bgUnknownWhite" data-tippy-content="{$texts['Unknown']}"></div> <div  class="hideOnMobile">{$texts['Unknown']}</div></div></th>
            <th style="text-align: right;"><div class="iconSmall bgChildWhite" data-tippy-content="{$texts['Child']}"></div></th>
            <th style="text-align: right;"><div class="iconSmall bgAlcoholWhite" data-tippy-content="{$texts['Intoxicated']}"></div></th>
            <th style="text-align: right;"><div class="iconSmall bgHitRunWhite" data-tippy-content="{$texts['Drive_on_or_fleeing']}"></div></th>
          </tr>
        </thead>  
        <tbody id="tableStatsBody">
          
        </tbody>
      </table>      
    </div>
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
  </div>
</div>
HTML;  }

  public static function getSearchPeriodHtml($onInputFunctionName = ''): string {
    $texts = translateArray(['Always', 'Today', 'Yesterday', 'days', 'The_correspondent_week', 'Custom_period', 'Period', 'Start_date', 'End_date']);

    $onInputFunction = $onInputFunctionName === '' ? '' : $onInputFunctionName . '();';
    $onInputSelect = 'oninput="setCustomRangeVisibility();' . $onInputFunction . '"';
    $onInputDates = $onInputFunction ? 'oninput="' . $onInputFunction . '"' : '';

    return <<<HTML
<div class="toolbarItem">
  <select id="searchPeriod" class="searchInput" $onInputSelect data-tippy-content="{$texts['Period']}">
    <option value="all" selected>{$texts['Always']}</option> 
    <option value="today">{$texts['Today']}</option> 
    <option value="yesterday">{$texts['Yesterday']}</option> 
    <option value="7days">7 {$texts['days']}</option> 
    <option value="30days">30 {$texts['days']}</option> 
    <option value="365days">365 {$texts['days']}</option>
    <option value="custom">{$texts['Custom_period']}</option>          
  </select>
</div>

<input id="searchDateFrom" class="searchInput toolbarItem" type="date" data-tippy-content="{$texts['Start_date']}" $onInputDates>
<input id="searchDateTo" class="searchInput toolbarItem" type="date" data-tippy-content="{$texts['End_date']}" $onInputDates>
HTML;
  }

  public static function getSearchPersonsHtml(): string {
    $texts = translateArray(['Humans']);

    return <<<HTML
    <div id="searchPersons" class="toolbarItem">
      <div class="dropInputWrapper">
        <div class="searchInput dropInput" tabindex="0" onclick="toggleSearchPersons(event);">
          <div id="inputSearchPersons" style="display: flex">{$texts['Humans']}</div>
          <div id="arrowSearchPersons" class="inputArrowDown"></div>  
        </div>
        
        <div id="searchSearchPersons" class="searchResultsPopup" onclick="event.stopPropagation();"></div>
      </div>      
    </div>
HTML;

  }

  private static function addFilterCountry($country, $selectedCountryId): string {
    $selected = $country['id'] === $selectedCountryId ? "selected" : '';
    return "<option value='{$country['id']}' $selected>{$country['name']}</option>";
  }

  public static function getSearchLocationHtml(string $elementId, string $selectedCountryId = ''): string {
    global $database;
    global $user;

    if ($selectedCountryId === '') $selectedCountryId = $user->countryId;

    $texts = translateArray(['Country']);

    $countryOptions = '';
    foreach ($database->countries as $country) {
      if ($country['id'] === 'UN') $countryOptions .= self::addFilterCountry($country, $selectedCountryId);
    }

    foreach ($database->countries as $country) {
      if ($country['id'] !== 'UN') $countryOptions .= self::addFilterCountry($country, $selectedCountryId);
    }

    return <<<HTML
<select id="$elementId" class="searchInput" data-tippy-content="{$texts['Country']}">
  $countryOptions
</select>
HTML;
  }

  public static function pageExport(): string {
    return <<<HTML
<div id="pageMain">
<div class="pageInner">
  <div class="pageSubTitle">Export</div>
  <div id="export">

    <h2>Download crash and article data</h2>

    <div>All crash data is available in gzip JSON format. The download is refreshed every 24 hours.
    </div> 
    
    <div class="buttonBar" style="justify-content: center; margin-bottom: 30px;">
      <button class="button buttonImportant" onclick="downloadCrashesData();">Download crashes and articles</button>
    </div>  
    <div id="spinnerLoad"><img src="/images/spinner.svg"></div>
    
    <h3>Data specification</h3>
    
    <div>Persons > transportation mode</div>
    
    <table class="dataTable" style="width: auto; margin: 0 0 20px 0;">
      <thead>
      <tr><th>id</th><th>name</th></tr>
      </thead>
      <tbody id="tbodyTransportationMode"></tbody>
    </table>        

    <div>Persons > health</div>
    <table class="dataTable" style="width: auto; margin: 0 0 20px 0;">
      <thead>
      <tr><th>id</th><th>name</th></tr>
      </thead>
      <tbody id="tbodyHealth"></tbody>
    </table>

    <h2>Research questions and answers</h2>

    <div>The research questions and answers are available in gzip JSON format. 
    The JSON contains two arrays: questions and answers, which are the tables from the database.
    Answers objects contain a field questionid which points to the question id field in the questions table. 
    The download is refreshed every 24 hours.
    </div> 

    <div class="buttonBar" style="justify-content: center; margin-bottom: 30px;">
      <button class="button buttonImportant" onclick="downloadResearchData();">Download research data</button>
    </div>  
    <div id="spinnerResearch" class="spinnerLine"><img src="/images/spinner.svg"></div>
            
  </div>
</div>
</div>
HTML;
  }

  public static function pageNotModerator(): string {
    return <<<HTML
<div id="pageMain">
<div class="pageInner">
  <div style="text-align: center;">You are not a moderator. Log in as moderator or administrator.</div>
  <div id="spinnerLoad"><img alt="Spinner" src="/images/spinner.svg"></div>
</div>
</div>
HTML;
  }
}