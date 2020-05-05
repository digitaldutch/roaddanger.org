<?php

function getHTMLBeginMain($pageTitle='', $head='', $initFunction='', $showCrashMenu=false, $fullWindow=false){
  global $VERSION;
  $defaultLanguage = DEFAULT_LANGUAGE;

  $title = 'Het Ongeluk';
  if ($pageTitle !== '') $title = $pageTitle . ' | ' . $title;
  $initScript = ($initFunction !== '')? "<script>document.addEventListener('DOMContentLoaded', $initFunction);</script>" : '';
  $navigation = getNavigation();

  if (! cookiesApproved()) {
    $cookieWarning = <<<HTML
    <div id="cookieWarning" class="flexRow">
      <div>Deze website gebruikt cookies. 
        <a href="/overdezesite/#cookieInfo" style="text-decoration: underline; color: inherit;">Meer info.</a>
      </div>
      <div class="button" onclick="acceptCookies();">Akkoord</div>
    </div>
HTML;
  } else $cookieWarning = '';

  $htmlClass = $fullWindow? ' class="fullWindow"' : '';

  $mainMenuItems = '';
  if ($showCrashMenu) $mainMenuItems = <<<HTML
  <div id="buttonSearch" class="menuButton bgSearch" onclick="toggleSearchBar(event);"></div>
  <div id="buttonNewArticle" class="menuButton buttonAdd" onclick="showNewCrashForm();"></div>
HTML;

  return <<<HTML
<!DOCTYPE html>
<html lang="$defaultLanguage" $htmlClass>
<head>
<link href="https://fonts.googleapis.com/css?family=Lora|Montserrat" rel="stylesheet">
<link href="/main.css?v=$VERSION" rel="stylesheet" type="text/css">
<link rel="shortcut icon" type="image/png" href="/images/hetongeluk.png">
$head
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta charset="utf-8">
<title>$title</title>
<script src="/scripts/tippy.all.min.js"></script>
<script src="/js/utils.js?v=$VERSION"></script>

$initScript

</head>
<body>
$navigation

<div id="pageBar">
  <div id="topBar">
    <span class="menuButton bgMenu" onclick="toggleNavigation(event);"></span>
  
    <div class="headerMain pageTitle"><a href="/">Het Ongeluk</a></div>
   
    <div>
      $mainMenuItems
      <div id="loginButton" onclick="loginClick(event);">
        <div id="buttonPerson" class="menuButton bgPerson"></div>
        <div id="loginText">Log in</div>
        <div id="loginName" class="hideOnMobile"></div>
      </div>
  
      <div id="menuPerson" class="buttonPopupMenu">
        <div id="menuProfile" class="menuHeader"></div>
        <div id="menuLogin" onclick="showLoginForm();">Log in</div> 
        <div id="menuLogout" style="display: none;" onclick="logOut();">Log uit</div>
      </div>
    </div>
  </div>
  
  <div id="searchBar" class="searchBar">
    <div class="popupCloseCross" onclick="toggleSearchBar();"></div>

    <div class="toolbarItem">
      <span id="searchPersonHealthDead" class="menuButton bgDeadBlack" data-tippy-content="Letsel: dood" onclick="selectSearchPersonDead();"></span>      
      <span id="searchPersonHealthInjured" class="menuButton bgInjuredBlack" data-tippy-content="Letsel: gewond" onclick="selectSearchPersonInjured();"></span>      
      <span id="searchPersonChild" class="menuButton bgChild" data-tippy-content="Kind" onclick="selectSearchPersonChild();"></span>      
    </div>

    <div class="toolbarItem">
       <input id="searchText" class="searchInput"  type="search" placeholder="Zoek tekst" onkeyup="startSearchKey(event);" autocomplete="off">  
    </div>
    
    <div class="toolbarItem">
      <select id="searchPeriod" class="searchInput" oninput="setCustomRangeVisibility();" data-tippy-content="Periode">
        <option value="all" selected>Altijd</option> 
        <option value="today">Vandaag</option> 
        <option value="yesterday">Gisteren</option> 
        <option value="7days">7 dagen</option> 
        <option value="30days">30 dagen</option> 
        <option value="decorrespondent">De Correspondent week</option> 
        <option value="2019">2019</option> 
        <option value="2020">2020</option> 
        <option value="custom">Handmatige periode</option> 
      </select>
    </div>
    
    <input id="searchDateFrom" class="searchInput toolbarItem" type="date" data-tippy-content="Vanaf">
    <input id="searchDateTo" class="searchInput toolbarItem" type="date" data-tippy-content="Tot en met">
    
    <div class="toolbarItem">
      <div class="dropInputWrapper">
        <div class="searchInput dropInput" tabindex="0" onclick="toggleSearchPersons(event);">
          <span id="inputSearchPersons" class="inputIcons">Mensen</span>
          <div id="arrowSearchPersons" class="inputArrowDown"></div>  
        </div>
        
        <div id="searchSearchPersons" class="searchResultsPopup" onclick="event.stopPropagation();"></div>
      </div>      
    </div>
           
    <div class="toolbarItem">
      <input id="searchSiteName" class="searchInput" type="search" placeholder="Bron" onkeyup="startSearchKey(event);" autocomplete="off">
    </div>

    <div class="toolbarItem">
      <div class="button buttonMobileSmall" onclick="startSearch(event)">Zoek</div>
    </div>
  </div>      

</div>

$cookieWarning

HTML;
}

function getHTMLEnd($htmlEnd='', $flexFullPage=false){
  $htmlFlex = $flexFullPage? '</div>' : '';
  $forms    = getHTMLConfirm() . getLoginForm() . getFormCrash() . getFormEditCrash() . getFormMergeCrash() . getFormEditPerson();
  return <<<HTML
    $htmlEnd 
    <div id="floatingMessage" onclick="hideMessage();">
      <div id="messageCloseCross" class="popupCloseCross crossWhite"></div>
      <div id="messageText"></div>
    </div>
    <div style="clear: both;"></div>
    $htmlFlex
    $forms
</body>
HTML;
}

function getHTMLConfirm(){
  $formConfirm = <<<HTML
<div id="formConfirmOuter" class="popupOuter" style="z-index: 1000" onclick="closePopupForm();">
  <form id="formConfirm" class="floatingForm" onclick="event.stopPropagation();">

    <div id="confirmHeader" class="popupHeader">Bevestigen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <div id="confirmText" class="textMessage"></div>

    <div class="popupFooter">
      <button id="buttonConfirmOK" class="button" type="submit" autofocus>OK</button>
      <button id="buttonConfirmCancel" class="button buttonGray" type="button" onclick="hideDiv('formConfirmOuter');">Annuleren</button>
    </div>    

  </form>
</div>
HTML;

  return $formConfirm;
}

function getNavigation(){

  global $VERSION;
  global $VERSION_DATE;

  $texts = translateArray(['Crashes', 'Statistics']);

  return <<<HTML
<div id="navShadow" class="navShadow" onclick="closeNavigation()"></div>
<div id="navigation" onclick="closeNavigation();">
  <div class="navHeader">
    <div class="popupCloseCross" onclick="closeNavigation();"></div>
    <div class="navHeaderTop"><span class="pageTitle">Het Ongeluk</span></div>      
  </div>
  <div style="overflow-y: auto;">
  
    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Crashes']}</div>
      <a href="/" class="navItem">Recente ongelukken</a>
      <a href="/stream" class="navItem">Laatst gewijzigde ongelukken</a>
      <a href="/decorrespondent" class="navItem">De Correspondent week</a>
      <a href="/mozaiek" class="navItem">Mozaïek</a>
<!--      <a href="/kaart" class="navItem">Kaart</a>-->
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">{$texts['Statistics']}</div>
      <a href="/statistieken/andere_partij" class="navItem">Tegenpartij bij verkeersdoden</a>
      <a href="/statistieken/vervoertypes" class="navItem">Vervoertypes</a>
      <a href="/statistieken/algemeen" class="navItem">Algemeen</a>
    </div>

    <div class="navigationSection">
      <div class="navigationSectionHeader">Overig</div>
      <a href="/exporteren/" class="navItem">Exporteer data</a>
      <a href="/overdezesite/" class="navItem">Over deze site</a>
    </div>

    <div id="navigationAdmin" data-moderator>    
      <div class="navigationSectionHeader">Beheer</div>
  
      <div class="navigationSection">
        <a href="/beheer/mensen" class="navItem" data-admin>Mensen</a>
        <a href="/moderaties/" class="navItem">Moderaties</a>
        <a href="/beheer/opties/" class="navItem">Opties</a>
      </div>      
    </div>
    
    <div class="navFooter">
     Versie $VERSION • $VERSION_DATE 
    </div>   
 
  </div>  
</div>
HTML;
}

function getLoginForm() {
  return <<<HTML
<div id="formLogin" class="popupOuter" onclick="closePopupForm();">
  <form class="formFullPage" onclick="event.stopPropagation();" onsubmit="return checkLogin();">

    <div class="popupHeader">Log in of registreer</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>
    
    <div id="spinnerLogin" class="spinner"></div>

    <label for="loginEmail">Email</label>
    <input id="loginEmail" class="popupInput" type="email" autocomplete="email">
    
    <div id="divFirstName" class="displayNone flexColumn">
      <label for="loginFirstName">Voornaam</label>
      <input id="loginFirstName" class="popupInput" autocomplete="given-name" type="text">
    </div>
  
    <div id="divLastName" class="displayNone flexColumn">
      <label for="loginLastName">Achternaam</label>
      <input id="loginLastName" class="popupInput" autocomplete="family-name" type="text">
    </div>
    
    <label for="loginPassword">Wachtwoord</label>
    <input id="loginPassword" class="popupInput" type="password" autocomplete="current-password">

    <div id="divPasswordConfirm" class="displayNone flexColumn">
      <label for="loginPasswordConfirm">Wachtwoord bevestigen</label>
      <input id="loginPasswordConfirm" class="popupInput" type="password" autocomplete="new-password">
    </div>   
    
    <label><input id="stayLoggedIn" type="checkbox" checked>Ingelogd blijven</label>

    <div id="loginError" class="formError"></div>

    <div class="popupFooter">
      <input id="buttonLogin" type="submit" class="button" style="margin-left: 0;" value="Log in">
      <input id="buttonRegistreer" type="button" class="button buttonGray" value="Registreer" onclick="checkRegistration();">

      <span onclick="loginForgotPassword()" style="margin-left: auto; text-decoration: underline; cursor: pointer;">Wachtwoord vergeten</span>
    </div>
    
  </form>
</div>  
HTML;
}

function getFormEditCrash(){
  return <<<HTML
<div id="formEditCrash" class="popupOuter">

  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader">Nieuw artikel toevoegen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <div id="editArticleSection" class="flexColumn">
      <div class="formSubHeader">Artikel</div>

      <input id="articleIDHidden" type="hidden">
  
      <div class="labelDiv">
        <label for="editArticleUrl">Link (URL)</label>
        <span class="iconTooltip" data-tippy-content="Kopieer de link uit de adresbalk van de webpagina met het artikel"></span>
      </div>

      <div style="display: flex;">
        <input id="editArticleUrl" class="popupInput" type="url" maxlength="1000" autocomplete="off">
        <div class="button buttonLine" onclick="getArticleMetaData();">Artikel ophalen</div>
      </div>
  
      <div id="spinnerMeta" class="spiderBackground">
        <div class="popupHeader">Tarantula is aan het werk...</div>
        <div><img src="/images/tarantula.jpg" style="height: 200px;" alt="Tarantula spider"></div>
        <div id="tarantulaResults"></div> 
      </div>
  
      <label for="editArticleSiteName">Mediabron</label>
      <input id="editArticleSiteName" class="popupInput" type="text" maxlength="200" autocomplete="off" data-readonlyhelper>
     
      <label for="editArticleTitle">Titel</label>
      <input id="editArticleTitle" class="popupInput" type="text" maxlength="500" autocomplete="off" data-readonlyhelper>
  
      <label for="editArticleText">Samenvatting</label>
      <textarea id="editArticleText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off" data-readonlyhelper></textarea>
   
      <label for="editArticleUrlImage">Foto link (URL)</label>
      <input id="editArticleUrlImage" class="popupInput" type="url" maxlength="1000" autocomplete="off" data-readonlyhelper>
      
      <label for="editArticleDate">Publicatiedatum</label>
      <input id="editArticleDate" class="popupInput" type="date" autocomplete="off">

      <div class="labelDiv">
        <label for="editArticleAllText">Volledige artikel tekst (niet verplicht, maar...)</label>
        <span class="iconTooltip" data-tippy-content="Niet verplicht, maar zeer nuttig voor onze tekstanalyses. De tekst moet nu nog handmatig gekopieerd worden. Onze dank is groot als je dat wilt doen."></span>
      </div>
      <textarea id="editArticleAllText" maxlength="10000" style="height: 150px; resize: vertical;" class="popupInput" autocomplete="off"></textarea>
    </div>

    <div id="editCrashSection" class="flexColumn">
      <div class="formSubHeader">Ongeluk</div>
     
      <input id="crashIDHidden" type="hidden">
  
      <div data-hidehelper class="flexColumn">
        <label for="editCrashTitle">Titel ongeluk</label> 
        <div style="display: flex;">
          <input id="editCrashTitle" class="popupInput" type="text" maxlength="500" autocomplete="off" data-readonlyhelper>
          <span data-hideedit class="button buttonGray buttonLine" onclick="copyCrashInfoFromArticle();">Zelfde als artikel</span>
        </div>
  
        <label for="editCrashText">Tekst</label>
        <textarea id="editCrashText" maxlength="500" style="height: 50px; resize: vertical;" class="popupInput" autocomplete="off" data-readonlyhelper></textarea>
      </div>        

      <div class="labelDiv">
        <label for="editCrashDate">Datum ongeluk</label>
        <span class="iconTooltip" data-tippy-content="Vaak anders dan publicatiedatum artikel"></span>
      </div>
      <div style="display: flex;">
        <input id="editCrashDate" class="popupInput" type="date" autocomplete="off">
        <span data-hideedit class="button buttonGray buttonLine" onclick="copyCrashDateFromArticle();" ">Zelfde als artikel</span>
      </div>
          
      <div style="margin-top: 5px;">
        <div>Betrokken mensen <span class="button buttonGray buttonLine" onclick="showEditPersonForm();">Mensen toevoegen</span></div>   
        <div id="editCrashPersons"></div>
      </div>

      <div style="margin-top: 5px;">
        <div>Kenmerken van ongeluk</div>
        <div>
          <span id="editCrashUnilateral" class="menuButton bgUnilateral" data-tippy-content="Eenzijdig ongeluk" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashPet" class="menuButton bgPet" data-tippy-content="Dier(en)" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashTrafficJam" class="menuButton bgTrafficJam" data-tippy-content="File/Hinder" onclick="toggleSelectionButton(this);"></span>      
          <span id="editCrashTree" style="display: none;" class="menuButton bgTree" data-tippy-content="Boom/Paal" onclick="toggleSelectionButton(this);"></span>
        </div>
      </div>
      
      <div style="margin-top: 5px;">
        <div>Locatie <span class="smallFont">(Optioneel)</span> <span class="iconTooltip" data-tippy-content="Optioneel, omdat het lastig is om deze uit tekst of foto's te halen. Klik op kaart om een locatie te selecteren. Klik marker om locatie te verwijderen. Sleep marker om locatie aan te passen."></span></div>
            
        <label for="editArticleDate">Breedtegraad: <input id="editCrashLatitude" class="popupInput" type="number" style="width: 85px;"></label>        
        <label for="editArticleDate">Lengtegraad: <input id="editCrashLongitude" class="popupInput" type="number" style="width: 85px;"></label>
        
        <div id="mapEdit"></div>
      </div>      
      
    </div>
            
    <div class="popupFooter">
      <input id="buttonSaveArticle" type="button" class="button" value="Opslaan" onclick="saveArticleCrash();">
      <input type="button" class="button buttonGray" value="Annuleren" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
}

function getFormCrash(){
  return <<<HTML
<div id="formCrash" class="popupOuterNoClose" onclick="closeCrashDetails();">
    <div class="popupCloseCrossWhite hideOnMobile" onclick="closeCrashDetails();"></div>

  <div class="formFullPage" onclick="event.stopPropagation();">    
    <div class="showOnMobile" style="height: 15px"></div>
    <div class="popupCloseCross showOnMobile" onclick="closeCrashDetails();"></div>

    <div id="crashDetails" class="flexColumn">
    </div>
            
  </div>
  
</div>
HTML;
}

function getFormMergeCrash(){
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

      <div id="spinnerMerge" class="spinnerLine"><img src="/images/spinner.svg"></div>
      <div id="mergeSearchResults"></div>  
    </div>
            
    <div class="popupFooter">
      <input id="buttonMergeArticle" type="button" class="button" value="Voeg samen" onclick="mergeCrash();">
      <input type="button" class="button buttonGray" value="Annuleren" onclick="closePopupForm();">
    </div>    
  </form>
  
</div>
HTML;
}

function getFormEditPerson(){
  return <<<HTML
<div id="formEditPerson" class="popupOuter" style="z-index: 501;" onclick="closeEditPersonForm();">

  <div class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editPersonHeader" class="popupHeader">Nieuw mens toevoegen</div>
    <div class="popupCloseCross" onclick="closeEditPersonForm();"></div>

    <input id="personIDHidden" type="hidden">

    <div style="margin-top: 5px;">
      <div>Vervoertype</div> 
      <div id="personTransportationButtons"></div>
    </div>
            
    <div style="margin-top: 5px;">
      <div>Letsel</div> 
      <div id="personHealthButtons"></div>
    </div>

    <div style="margin-top: 5px;">
      <div>Kenmerken</div> 
      <div>
        <span id="editPersonChild" class="menuButton bgChild" data-tippy-content="Kind" onclick="toggleSelectionButton(this)"></span>            
        <span id="editPersonUnderInfluence" class="menuButton bgAlcohol" data-tippy-content="Onder invloed" onclick="toggleSelectionButton(this)"></span>            
        <span id="editPersonHitRun" class="menuButton bgHitRun" data-tippy-content="Doorrijden/vluchten" onclick="toggleSelectionButton(this)"></span>            
      </div>
    </div>
            
    <div class="popupFooter">
      <input type="button" class="button" value="Opslaan en openblijven" onclick="savePerson(true);">
      <input type="button" class="button" value="Opslaan en sluiten" onclick="savePerson();">
      <input id="buttonCloseEditPerson" type="button" class="button buttonGray" value="Sluiten" onclick="closeEditPersonForm();">
      <input id="buttonDeletePerson" type="button" class="button buttonRed" value="Verwijderen" onclick="deletePerson();">
    </div>    
  </div>
  
</div>
HTML;
}

function getFormEditUser(){
  return
    <<<HTML
<div id="formEditUser" class="popupOuter" onclick="closePopupForm();">
  <form class="formFullPage" onclick="event.stopPropagation();">
    
    <div id="editHeader" class="popupHeader">Mens gegevens aanpassen</div>
    <div class="popupCloseCross" onclick="closePopupForm();"></div>

    <input id="userID" type="hidden">

    <label for="userEmail">Email</label>
    <input id="userEmail" class="inputForm" style="margin-bottom: 10px;" type="text"">
      
    <label for="userFirstName">Voornaam</label>
    <input id="userFirstName" class="inputForm" style="margin-bottom: 10px;" type="text"">
  
    <label for="userLastName">Achternaam</label>
    <input id="userLastName" class="inputForm" style="margin-bottom: 10px;" type="text"">

    <label for="userPermission">Permissie</label>
    <select id="userPermission">
      <option value="0">Helper (ongelukken worden gemodereerd)</option>
      <option value="2">Moderator (kan alle ongelukken bewerken)</option>
      <option value="1">Beheerder</option>
    </select>
    
    <div id="editUserError" class="formError"></div>
   
    <div class="popupFooter">
      <input type="button" class="button" value="Opslaan" onclick="saveUser();">
      <input type="button" class="button buttonGray" value="Annuleren" onclick="hideDiv('formEditUser');">
    </div>    
  </form>
</div>
HTML;
}


