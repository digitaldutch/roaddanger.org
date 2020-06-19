<?php

require_once '../initialize.php';

global $VERSION;

$uri = urldecode($_SERVER['REQUEST_URI']);

if      (strpos($uri, '/account/resetpassword') === 0) $pageType = 'resetPassword';
else if (strpos($uri, '/account')               === 0) $pageType = 'account';
else $pageType = 'none';

if ($pageType === 'account') {

  $htmlMain = <<<HTML
  <div class="pageSubTitle">Account</div>

  <form class="formPage" onsubmit="saveUser(); return false;">  

    <label class="inputLabel">Voornaam
      <input id="profileFirstName" class="inputForm" type="text" autocomplete="given-name">
    </label>
    
    <label class="inputLabel">Achternaam
      <input id="profileLastName" class="inputForm" type="text" autocomplete="family-name">
    </label>
    
    <label class="inputLabel">Email
      <input id="profileEmail" class="inputForm" type="email" autocomplete="email">
    </label>
    
    <label class="inputLabel">Nieuw wachtwoord
      <input id="profileNewPassword" class="inputForm" type="password" autocomplete="new-password">
    </label>
    
    <label class="inputLabel">Nieuw wachtwoord bevestigen
      <input id="profileNewPasswordConfirm" class="inputForm" type="password" autocomplete="new-password">
    </label>
    
    <div class="formSubHeader">Instellingen</div>
    
    <label class="inputLabel">Language
      <select id="profileLanguage" class="inputForm">
        <option value="en">English</option>
        <option value="nl">Nederlands</option>
      </select>
    </label>
    
    <div class="buttonBar">
      <input type="submit" class="button" style="margin-left: 0;" value="Opslaan">
    </div>

  </form>  
HTML;

} else if ($pageType === 'resetPassword') {
  $email      = $_REQUEST['email']? $_REQUEST['email'] : null;
  $recoveryId = $_REQUEST['recoveryid']? $_REQUEST['recoveryid'] : null;

  if (isset($email) && isset($recoveryId)){
    $htmlMain = <<<HTML
  <div>
    <div class="popupHeader">Reset wachtwoord</div>
    
    <div id="spinnerReset" class="spinner"></div>

    <input id="recoveryid" type="hidden" value="$recoveryId">
    <input id="email" type="hidden" value="$email">

    <label for="newPassword">Nieuw wachtwoord</label>
    <input id="newPassword" class="popupInput" type="password" autocomplete="current-password">

    <div id="loginError" class="formError"></div>
    <div class="popupFooter">
      <input id="buttonLogin" type="button" class="button" value="Opslaan" onclick="saveNewPassword();">
    </div>
  </div>
HTML;
  } else $htmlMain = 'Error: Invalid reset password request';
} else $htmlMain = 'Error: No page type found.';

$htmlMain = <<<HTML
<div id="main" class="pageInner" style="max-width: 500px;">
  $htmlMain
</div>
HTML;

$head = <<<HTML
<script src="account.js?v=$VERSION"></script>
HTML;

$html =
  getHTMLBeginMain('Reset wachtwoord', $head, 'initAccount') .
  $htmlMain .
  getHTMLEnd();

echo $html;