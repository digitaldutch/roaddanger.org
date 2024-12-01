<?php

require_once '../initialize.php';
require_once '../HtmlBuilder.php';

global $VERSION;
global $database;
global $user;

$uri = urldecode($_SERVER['REQUEST_URI']);

if (str_starts_with($uri, '/account/resetpassword')) $pageType = 'resetPassword';
else if (str_starts_with($uri, '/account')) $pageType = 'account';
else $pageType = 'none';

if ($pageType === 'account') {

  $languages = $database->fetchAll("SELECT id, name FROM languages ORDER BY name;");
  $languageOptions = '';
  foreach ($languages as $language) {
    $selected = $language['id'] === $user->languageId? 'selected' : '';
    $languageOptions .= "<option value='{$language['id']}' {$selected}>{$language['name']}</option>";
  }

  $texts = translateArray(['First_name', 'Last_name', 'Email', 'New_password', 'Confirm_password', 'Settings',
    'Language', 'Save']);

  $htmlMain = <<<HTML
  <div class="pageSubTitle">Account</div>

  <form class="formPage" onsubmit="saveUser(); return false;">  

    <label class="inputLabel">{$texts['First_name']}
      <input id="profileFirstName" class="inputForm" type="text" autocomplete="given-name">
    </label>
    
    <label class="inputLabel">{$texts['Last_name']}
      <input id="profileLastName" class="inputForm" type="text" autocomplete="family-name">
    </label>
    
    <label class="inputLabel">{$texts['Email']}
      <input id="profileEmail" class="inputForm" type="email" autocomplete="email">
    </label>
    
    <label class="inputLabel">{$texts['New_password']}
      <input id="profileNewPassword" class="inputForm" type="password" autocomplete="new-password">
    </label>
    
    <label class="inputLabel">{$texts['Confirm_password']}
      <input id="profileNewPasswordConfirm" class="inputForm" type="password" autocomplete="new-password">
    </label>
    
    <div class="formSubHeader">{$texts['Settings']}</div>
    
    <label class="inputLabel">{$texts['Language']}
      <select id="profileLanguage" class="inputForm">$languageOptions</select>
    </label>
    
    <div class="buttonBar">
      <input type="submit" class="button buttonImportant" style="margin-left: 0;" value="{$texts['Save']}">
    </div>

  </form>  
HTML;

} else if ($pageType === 'resetPassword') {
  $email      = $_REQUEST['email']? $_REQUEST['email'] : null;
  $recoveryId = $_REQUEST['recoveryid']? $_REQUEST['recoveryid'] : null;

  $texts = translateArray(['Save', 'Reset_password', 'New_password']);

  if (isset($email) && isset($recoveryId)){
    $htmlMain = <<<HTML
  <div>
    <div class="popupHeader">{$texts['Reset_password']}</div>
    
    <div id="spinnerReset" class="spinner"></div>

    <input id="recoveryid" type="hidden" value="$recoveryId">
    <input id="email" type="hidden" value="$email">

    <label for="newPassword">{$texts['New_password']}</label>
    <input id="newPassword" class="popupInput" type="password" autocomplete="current-password">

    <div id="loginError" class="formError"></div>
    <div class="popupFooter">
      <input id="buttonLogin" type="button" class="button" value="{$texts['Save']}" onclick="saveNewPassword();">
    </div>
  </div>
HTML;
  } else $htmlMain = 'Error: Invalid reset password request';
} else $htmlMain = 'Error: No page type found.';

$htmlMain = <<<HTML
<div id="pageMain">
  <div id="main" class="pageInner" style="max-width: 500px;">
    $htmlMain
  </div>
</div>
HTML;

$head = <<<HTML
<script src="account.js?v=$VERSION"></script>
HTML;

$html =
  HtmlBuilder::getHTMLBeginMain('Reset wachtwoord', $head, 'initAccount') .
  $htmlMain .
  HtmlBuilder::getHTMLEnd();

echo $html;