let pageType;
let PageTypeAccount = Object.freeze({
  account:                       0,
  resetPassword:                 1,
});

async function initAccount() {
  initPage();

  const url      = new URL(location.href);
  const pathName = decodeURIComponent(url.pathname);

  if      (pathName.startsWith('/account/resetpassword')) pageType = PageTypeAccount.resetPassword;
  else if (pathName.startsWith('/account'))               pageType = PageTypeAccount.account;
  else                                                    pageType = PageTypeAccount.account;

  if (pageType === PageTypeAccount.account) clearAccountSettings();

  await loadUserData();

  switch (pageType) {
    case PageTypeAccount.account: {
      loadAccountSettings();

      let saved = false;
      try {
        saved = sessionStorage.getItem('accountSaved') === '1';
        sessionStorage.removeItem('accountSaved');
      } catch (e) { /* storage not available */ }
      if (saved) showMessage(translate('Saved'), 4);

      break;
    }
  }
}

function loadAccountSettings() {
  // The forms are not on the page when the visitor is not logged in
  if (! user.loggedin || ! document.getElementById('profileFirstName')) return;

  document.getElementById('profileFirstName').value = user.firstname;
  document.getElementById('profileLastName').value  = user.lastname;
  document.getElementById('profileEmail').value     = user.email;
  document.getElementById('profileLanguage').value  = user.language;

  document.getElementById('passwordFormUsername').value = user.email;

  updateEmailPasswordField();
}

function clearAccountSettings() {
  const profileFirstName = document.getElementById('profileFirstName');
  if (! profileFirstName) return;

  profileFirstName.value = '';
  document.getElementById('profileLastName').value  = '';
  document.getElementById('profileEmail').value     = '';
  document.getElementById('profileEmailPassword').value = '';
  document.getElementById('profileLanguage').value  = null;
}

/**
 * Changing the email address needs the current password. The field is only shown when the address is changed.
 */
function updateEmailPasswordField() {
  const emailChanged = document.getElementById('profileEmail').value.trim().toLowerCase() !== (user.email || '').toLowerCase();
  document.getElementById('profileEmailPasswordRow').style.display = emailChanged ? 'block' : 'none';
}

async function saveProfile() {
  const userSave = {
    id:              user.id,
    firstName:       document.getElementById('profileFirstName').value.trim(),
    lastName:        document.getElementById('profileLastName').value.trim(),
    email:           document.getElementById('profileEmail').value.trim(),
    language:        document.getElementById('profileLanguage').value,
    currentPassword: document.getElementById('profileEmailPassword').value,
  }

  const url = '/general/ajaxGeneral.php?function=saveUser';
  const response = await fetchFromServer(url, userSave);

  if (response.error) showError(response.error);
  else {
    // The page is reloaded to show the new name and language. A message now would disappear with the reload:
    // show it after the reload.
    try { sessionStorage.setItem('accountSaved', '1'); } catch (e) { /* storage not available: no message */ }
    window.location.reload();
  }
}

async function changePassword() {
  const currentPassword = document.getElementById('passwordCurrent').value;
  const password        = document.getElementById('passwordNew').value.trim();
  const passwordConfirm = document.getElementById('passwordNewConfirm').value.trim();

  if (! password)                  {showError(translate('No_password_filled_in')); return;}
  if (password.length < 6)         {showError(translate('Password_less_than_6_characters')); return;}
  if (password !== passwordConfirm) {showError(translate('Passwords_not_same')); return;}

  const url = '/general/ajaxGeneral.php?function=changePassword';
  const response = await fetchFromServer(url, {
    currentPassword: currentPassword,
    password:        password,
    passwordConfirm: passwordConfirm,
  });

  if (response.error) showError(response.error);
  else {
    document.getElementById('passwordCurrent').value    = '';
    document.getElementById('passwordNew').value        = '';
    document.getElementById('passwordNewConfirm').value = '';

    showMessage(translate('Password_changed_successfully'));
  }
}

async function saveNewPassword() {
  const email      = document.getElementById('email').value;
  const password   = document.getElementById('newPassword').value.trim();
  const recoveryId = document.getElementById('recoveryid').value;

  if (! password) {showError(translate('No_password_filled_in')); return;}
  if (password.length < 6){showError(translate('Password_less_than_6_characters')); return;}

  // The new password and the token are sent in the request body, not in the url
  const url = '/general/ajaxGeneral.php?function=saveNewPassword';
  const response = await fetchFromServer(url, {email: email, recoveryid: recoveryId, password: password});
  if (response.error) showError(response.error);
  else if (response.ok) document.getElementById('main').innerHTML = `<div style="text-align: center;">${translate('Password_changed_successfully')}</div>`;
  else showError('Internal error saving password');
}