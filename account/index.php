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

  $texts = translateArray(['Account', 'Profile', 'First_name', 'Last_name', 'Email', 'Language', 'Save', 'Current_password',
    'Current_password_info', 'Change_password', 'New_password', 'Confirm_password', 'Log_in_or_register', 'Log_in']);

  if ($user->loggedIn) {
    $htmlMain = <<<HTML
  <div class="accountPage">
    <h1>{$texts['Account']}</h1>

    <form class="accountCard" onsubmit="saveProfile(); return false;">
      <h2>{$texts['Profile']}</h2>

      <div class="accountRow">
        <label class="accountField">{$texts['First_name']}
          <input id="profileFirstName" type="text" autocomplete="given-name" maxlength="100">
        </label>
        <label class="accountField">{$texts['Last_name']}
          <input id="profileLastName" type="text" autocomplete="family-name" maxlength="100">
        </label>
      </div>

      <label class="accountField">{$texts['Email']}
        <input id="profileEmail" type="email" autocomplete="email" maxlength="250" oninput="updateEmailPasswordField();">
      </label>

      <!-- Only shown when the email address is changed -->
      <div id="profileEmailPasswordRow" class="accountEmailPassword" style="display: none;">
        <label class="accountField">{$texts['Current_password']}
          <input id="profileEmailPassword" type="password" autocomplete="current-password">
        </label>
        <div class="accountHint">{$texts['Current_password_info']}</div>
      </div>

      <label class="accountField">{$texts['Language']}
        <select id="profileLanguage">$languageOptions</select>
      </label>

      <div class="accountButtons">
        <input type="submit" class="button buttonImportant" value="{$texts['Save']}">
      </div>
    </form>

    <form class="accountCard" onsubmit="changePassword(); return false;">
      <h2>{$texts['Change_password']}</h2>

      <!-- Helps password managers to find the right fields -->
      <input type="email" autocomplete="username" style="display: none;" aria-hidden="true" tabindex="-1" id="passwordFormUsername">

      <label class="accountField">{$texts['Current_password']}
        <input id="passwordCurrent" type="password" autocomplete="current-password">
      </label>

      <label class="accountField">{$texts['New_password']}
        <input id="passwordNew" type="password" autocomplete="new-password">
      </label>

      <label class="accountField">{$texts['Confirm_password']}
        <input id="passwordNewConfirm" type="password" autocomplete="new-password">
      </label>

      <div class="accountButtons">
        <input type="submit" class="button buttonImportant" value="{$texts['Change_password']}">
      </div>
    </form>
  </div>
HTML;
  } else {
    $htmlMain = <<<HTML
  <div class="accountPage">
    <h1>{$texts['Account']}</h1>
    <div class="accountCard" style="text-align: center;">
      <p>{$texts['Log_in_or_register']}</p>
      <div class="accountButtons" style="justify-content: center;">
        <div class="button buttonImportant" onclick="showLoginForm();">{$texts['Log_in']}</div>
      </div>
    </div>
  </div>
HTML;
  }

} else if ($pageType === 'resetPassword') {
  $email      = ! empty($_REQUEST['email'])      ? htmlspecialchars((string)$_REQUEST['email'], ENT_QUOTES, 'UTF-8')      : null;
  $recoveryId = ! empty($_REQUEST['recoveryid']) ? htmlspecialchars((string)$_REQUEST['recoveryid'], ENT_QUOTES, 'UTF-8') : null;

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
  HtmlBuilder::getHTMLBeginMain(($pageType === 'account') ? 'Account' : 'Reset password', $head, 'initAccount') .
  $htmlMain .
  HtmlBuilder::getHTMLEnd();

echo $html;