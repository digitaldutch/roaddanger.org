let pageType;
let PageTypeAccount = Object.freeze({
  account:                       0,
  resetPassword:                 1,
});

async function initAccount() {
  initPage();

  clearAccountSettings();

  await loadUserData();

  const url      = new URL(location.href);
  const pathName = decodeURIComponent(url.pathname);

  if      (pathName.startsWith('/account/resetpassword')) pageType = PageTypeAccount.resetPassword;
  else if (pathName.startsWith('/account'))               pageType = PageTypeAccount.account;
  else                                                    pageType = PageTypeAccount.account;

  switch (pageType) {
    case PageTypeAccount.account: {
      loadAccountSettings();

      break;
    }
  }
}

function loadAccountSettings() {
  document.getElementById('profileFirstName').value = user.firstname;
  document.getElementById('profileLastName').value  = user.lastname;
  document.getElementById('profileEmail').value     = user.email;

  document.getElementById('profileLanguage').value  = user.language;
}

function clearAccountSettings() {
  document.getElementById('profileFirstName').value = '';
  document.getElementById('profileLastName').value  = '';
  document.getElementById('profileEmail').value     = '';

  document.getElementById('profileLanguage').value  = null;
}

async function saveUser() {
  const userSave = {
    id:              user.id,
    firstName:       document.getElementById('profileFirstName').value.trim(),
    lastName:        document.getElementById('profileLastName').value.trim(),
    email:           document.getElementById('profileEmail').value.trim(),
    language:        document.getElementById('profileLanguage').value,
    password:        document.getElementById('profileNewPassword').value.trim(),
    passwordConfirm: document.getElementById('profileNewPasswordConfirm').value.trim(),
  }

  const url      = '/ajax.php?function=saveAccount';
  const response = await fetchFromServer(url, userSave);

  if (response.error) showError(response.error);
  else {
    showMessage('Gegevens zijn opgeslagen');
  }
}

async function saveNewPassword() {
  const email      = document.getElementById('email').value;
  const password   = document.getElementById('newPassword').value.trim();
  const recoveryId = document.getElementById('recoveryid').value;

  if (! password) {showError('Geen wachtwoord ingevuld'); return;}
  if (password.length < 6){showError('Wachtwoord is minder dan 6 karakters'); return;}

  const url = '/ajax.php?function=saveNewPassword' +
    '&email=' + encodeURIComponent(email) +
    '&recoveryid=' + encodeURIComponent(recoveryId) +
    '&password=' + encodeURIComponent(password);

  const response = await fetchFromServer(url);
  if (response.error) showError(response.error);
  else if (response.ok) document.getElementById('main').innerHTML = '<div style="text-align: center;">Wachtwoord succesvol aangepast</div>';
  else showError('Interne fout bij wachtwoord resetten.');
}