
async function saveNewPassword() {
  const email      = document.getElementById('email').value;
  const password   = document.getElementById('newPassword').value.trim();
  const recoveryid = document.getElementById('recoveryid').value;

  if (! password) {showError('Geen wachtwoord ingevuld'); return;}
  if (password.length < 6){showError('Wachtwoord is minder dan 6 karakters'); return;}

  const url = '/ajax.php?function=saveNewPassword' +
    '&email=' + encodeURIComponent(email) +
    '&recoveryid=' + encodeURIComponent(recoveryid) +
    '&password=' + encodeURIComponent(password);

  const response = await fetch(url, fetchOptions);
  const text     = await response.text();
  const data     = JSON.parse(text);
  if (data.error) showError(data.error);
  else if (data.ok) document.getElementById('main').innerHTML = '<div style="text-align: center;">Wachtwoord succesvol aangepast</div>';
  else showError('Interne fout bij wachtwoord resetten.');
}