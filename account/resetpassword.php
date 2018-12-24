<?php

require_once '../initialize.php';

global $VERSION;

$email      = $_REQUEST['email']? $_REQUEST['email'] : null;
$recoveryid = $_REQUEST['recoveryid']? $_REQUEST['recoveryid'] : null;

if (isset($email) && isset($recoveryid)){
  $html = <<<HTML
  <div>
    <div class="popupHeader">Reset wachtwoord</div>
    
    <div id="spinnerReset" class="spinner"></div>

    <input id="recoveryid" type="hidden" value="$recoveryid">
    <input id="email" type="hidden" value="$email">

    <label for="newPassword">Nieuw wachtwoord</label>
    <input id="newPassword" class="popupInput" type="password" autocomplete="current-password">

    <div id="loginError" class="formError"></div>
    <div class="popupFooter">
      <input id="buttonLogin" type="button" class="button" value="Opslaan" onclick="saveNewPassword();">
    </div>
  </div>
HTML;
} else {
  $html = <<<HTML
  <div style="text-align: center;">Wachtwoord reset code is verlopen.</div>
HTML;
}

$mainHTML = <<<HTML
<div id="main" class="pageInner">
  $html
</div>
HTML;

$head = '<script src="resetpassword.js?v=' . $VERSION . '"></script>';
$html =
  getHTMLBeginMain('Reset wachtwoord', $head, 'initMenuSwipe') .
  $mainHTML .
  getHTMLEnd();

echo $html;