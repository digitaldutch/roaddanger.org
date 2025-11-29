let spinnerLoad;
let textModified = false;

async function initAdmin(){
  spinnerLoad = document.getElementById('spinnerLoad');

  await loadUserData();

  initPage();

  const url = new URL(location.href);
  if (url.pathname.startsWith('/admin/humans')) {
    initObserver(loadUsers);

    await loadUsers();
  } else if (url.pathname.startsWith('/admin/translations')) {

    window.onbeforeunload = function() {
      const modifiedTexts = tableData[0].filter(d => d.modified === true);
      if (modifiedTexts.length > 0) return 'Leave site?.';
    };

    await loadTranslations();
  } else if (url.pathname.startsWith('/admin/longtexts')) {

    const longtextId = url.searchParams.get('longtext_id');
    if (longtextId) document.getElementById('selectLongText').value = longtextId;

    const languageId = url.searchParams.get('language_id');
    if (languageId) document.getElementById('selectLanguage').value = languageId;

    window.onbeforeunload = function() {
      if (textModified) return 'Leave site?.';
    };

    await loadLongText();
  }
}

async function loadUsers(){
  let response;
  const maxLoadCount = 50;

  function showUsers(users){
    let html = '';
    for (const user of users){
      let trClass = '';
      if      (user.permission === UserPermission.admin)     trClass = ' class="bgRed" ';
      else if (user.permission === UserPermission.moderator) trClass = ' class="bgOrange" ';

      html += `<tr id="tr0_${user.id}" ${trClass}>
<td>${user.id}</td>
<td>${user.name}<br><a href="mailto:${user.email}">${user.email}</a></td>
<td>${datetimeToAge(user.lastactive)}</td>
<td>${permissionToText(user.permission)}</td>
<td style="text-align: right;">${user.article_count}</td>
<td style="text-align: right;">${user.registrationtime.toLocaleDateString()}</td>
<td class="trButton"><span class="editDetails" data-editUser>⋮</span></td>
</tr>`;
    }

    document.getElementById('tableBody').innerHTML += html;
  }

  try {
    spinnerLoad.style.display = 'block';
    observerSpinner.unobserve(spinnerLoad);

    if (! tableData[0]) tableData[0] = [];
    const url = '/admin/ajaxAdmin.php?function=loadUsers&count=' + maxLoadCount + '&offset=' + tableData[0].length;
    response  = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      response.users.forEach(user => {
        user.lastactive = new Date(user.lastactive);
        user.registrationtime = new Date(user.registrationtime);
      });

      tableData[0] = tableData[0].concat(response.users);
      showUsers(response.users);
    }

  } catch (error) {
    showError(error.message);
  } finally {
    if (response.error || (response.users.length < maxLoadCount)) spinnerLoad.style.display = 'none';
  }

  if (response.users.length >= maxLoadCount) observerSpinner.observe(spinnerLoad);
}

function showUserMenu(target) {
  let menu = document.getElementById('menuUser');
  if (menu) menu.remove();

  const td = target.closest('td');
  td.innerHTML += `
<div id="menuUser" class="buttonPopupMenu" style="display: block !important;" onclick="event.preventDefault();">
  <div onclick="adminEditUser();">${translate('Edit')}</div>
  <div onclick="adminDeleteUser()">${translate('Delete')}</div>
</div>            
  `;
}

function adminEditUser() {
  document.getElementById('userID').value         = selectedTableData[0].id;
  document.getElementById('userEmail').value      = selectedTableData[0].email;
  document.getElementById('userFirstName').value  = selectedTableData[0].firstname;
  document.getElementById('userLastName').value   = selectedTableData[0].lastname;
  document.getElementById('userPermission').value = selectedTableData[0].permission;

  document.getElementById('formEditUser').style.display    = 'flex';
}

async function deleteUserDirect() {
  try {
    const userId   = selectedTableData[0].id;
    const url      = '/admin/ajaxAdmin.php?function=deleteUser&id=' + userId;
    const response = await fetchFromServer(url);
    if (response.error) showError(response.error);
    else {
      tableData[0] = tableData[0].filter(user => user.id !== userId);
      document.getElementById('tr0_' + userId).remove();
      showMessage(translate('Deleted'), 1);
    }
  } catch (error) {
    showError(error.message);
  }
}

async function adminDeleteUser() {
  confirmWarning(`Mens #${selectedTableData[0].id} "${selectedTableData[0].name}" en alle items die dit mens heeft aangemaakt verwijderen?<br><br><b>Dit kan niet ongedaan worden!</b>`,
      function (){deleteUserDirect();},
      `Verwijder mens en zijn items`
  );
}

async function saveUser(){
  let user = {
    id:         parseInt(document.getElementById('userID').value),
    email:      document.getElementById('userEmail').value.trim(),
    firstname:  document.getElementById('userFirstName').value.trim(),
    lastname:   document.getElementById('userLastName').value.trim(),
    permission: parseInt(document.getElementById('userPermission').value),
  };

  if (! user.email) {showError(translate('Email_not_filled_in')); return;}
  if (! validateEmail(user.email)) {showError(translate('Email_not_valid')); return;}
  if (! user.firstname) {showError(translate('First_name_not_filled_in')); return;}
  if (! user.lastname) {showError(translate('Last_name_not_filled_in')); return;}

  const url= '/admin/ajaxAdmin.php?function=saveUser';
  const response = await fetchFromServer(url, user);

  if (response.error) {
    showError(response.error, 10);
  } else {
    showMessage(translate('Saved'), 1);
    window.location.reload();
  }
}

function translationNeeded(text) {
  return text && text.charAt(text.length - 1) === '*';
}

function getTranslationTableRow(translation){
  const text = translationNeeded(translation.translation)? '' : translation.translation;

  return `
<tr id="tr0_${translation.id}">
  <td>${translation.id}</td>
  <td>${translation.english}</td>
  <td contenteditable class="editableCell" oninput="saveTranslation('${translation.id}');">${text}</td>
</tr>`;
}

async function loadTranslations(){
  let response;

  function showTranslations(translations){
    let html = '';
    for (const translation of translations) html += getTranslationTableRow(translation);

    document.getElementById('tableBody').innerHTML += html;
  }

  try {
    spinnerLoad.style.display = 'block';

    const url = '/admin/ajaxModerator.php?function=loadTranslations';
    response = await fetchFromServer(url);

    document.getElementById('translationLanguage').innerText = '(' + user.language + ')';

    if (response.error) showError(response.error);
    else {
      const dataTranslationNeeded = [];
      const dataTranslated        = [];

      for (const [id, value] of Object.entries(response.translationsEnglish)) {
        const translation = user.translations[id];
        const data = translationNeeded(translation)? dataTranslationNeeded : dataTranslated;
        data.push({
          id:          id,
          english:     value,
          translation: translation? translation : '',
        });
      }

      tableData = [dataTranslationNeeded.concat(dataTranslated)];

      showTranslations(tableData[0]);

      if (! selectedTableData[0]) selectFirstTableRow();
    }

  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
  }
}

function saveTranslation(id) {
  const td   = event.target.closest('td');
  const item = tableData[0].find(d => d.id === id);

  item.translation = td.innerText.trim();
  item.modified    = true;
}

async function saveTranslations() {
  const serverData = {
    language:        user.language,
    newTranslations: tableData[0].filter(d => d.modified === true),
  };

  if (serverData.newTranslations.length === 0) {
    showMessage(translate('No_changes'), 1);
    return;
  }

  const url = '/admin/ajaxModerator.php?function=saveTranslations';
  const response = await fetchFromServer(url, serverData);

  if (response.error) showError(response.error);
  else if (response.ok){
    tableData[0].forEach(d => d.modified = false);
    showMessage(translate('Saved'), 1);
  }
}

function newTranslation(){
  document.getElementById('newTranslationId').value          = '';
  document.getElementById('newTranslationEnglishText').value = '';

  document.getElementById('formNewTranslation').style.display = 'flex';
  document.getElementById('newTranslationId').focus();
}

async function saveNewTranslation() {
  const translation = {
    id:       document.getElementById('newTranslationId').value.trim().toLowerCase(),
    english:  document.getElementById('newTranslationEnglishText').value.trim(),
    modified: true,
  };

  if (translation.id.search(' ') >= 0) {
    showError('Spaces are not allowed in the id');
    return;
  }

  const url      = '/admin/ajaxAdmin.php?function=saveNewTranslation';
  const response = await fetchFromServer(url, translation);

  if (response.error) showError(response.error);
  else if (response.ok) {
    closePopupForm();

    translation.translation = user.language === 'en'? translation.english : '';
    tableData[0].unshift(translation);

    const tr = getTranslationTableRow(translation);
    document.getElementById('tableBody').innerHTML = tr + document.getElementById('tableBody').innerHTML;

    selectTableRow(translation.id);
  }
}

function deleteTranslation() {
  if (! selectedTableData[0]) {
    showError('No translation item selected');
    return;
  }

  confirmWarning(`Delete translation item "${selectedTableData[0].id}"?<br>You should only do this if the translation id is not used in the source code`,
      async () => {
        const serverData = {
          id: selectedTableData[0].id,
        };

        const url      = '/admin/ajaxAdmin.php?function=deleteTranslation';
        const response = await fetchFromServer(url, serverData);

        if (response.error) showError(response.error);
        else if (response.ok) {
          document.getElementById('tr0_' + serverData.id).remove();
          selectedTableData[0] = null;
          tableData[0]         = tableData[0].filter(d => d.id !== serverData.id);
          showMessage(translate('Deleted'), 1);

          selectFirstTableRow();
        }
      },
      'Delete id ' + selectedTableData[0].id
  );
}

async function changeUserLanguage(){
  await saveTranslations();

  const languageId = document.getElementById('selectLanguage').value;
  const url = '/general/ajax.php?function=saveLanguage&id=' + languageId;
  const response   = await fetchFromServer(url);

  if (response.error) {
    showError(response.error);
    return;
  }

  window.location.reload();
}

async function loadLongText(){
  if (textModified) {
    await saveLongText();
  }

  const data = {
    longtextId: document.getElementById('selectLongText').value,
    languageId: document.getElementById('selectLanguage').value,
  }

  document.getElementById('translationLanguage').innerText = '(' + data.languageId + ')';
  document.getElementById('longtext').value                = '';
  document.getElementById('longtext_translation').value    = '';

  const browserUrl = new URL(window.location);
  if (data.longtextId) browserUrl.searchParams.set('longtext_id', data.longtextId); else browserUrl.searchParams.delete('longtext_id');
  if (data.languageId) browserUrl.searchParams.set('language_id', data.languageId); else browserUrl.searchParams.delete('language_id');
  window.history.pushState(null, null, browserUrl.toString());
  if (data.longtextId) {
    const url      = '/admin/ajaxAdmin.php?function=loadLongText';
    const response = await fetchFromServer(url, data);

    if (response.error) {
      showError(response.error);
      return;
    }

    textModified = false;

    const textEnglish     = response.texts.find(t => t.language_id === 'en');
    const textTranslation = response.texts.find(t => t.language_id === data.languageId);

    document.getElementById('longtext').value             = textEnglish? textEnglish.content : '';
    document.getElementById('longtext_translation').value = textTranslation? textTranslation.content : '';

    updatePreviews();
  }

  document.getElementById('longtextsDivs').style.display = data.longtextId? 'block' : 'none';
}

function updatePreviews(){
  document.getElementById('longtextPreview').innerHTML             = marked(document.getElementById('longtext').value);
  document.getElementById('longtext_translationPreview').innerHTML = marked(document.getElementById('longtext_translation').value);
}

function translationChange() {
  textModified = true;

  document.getElementById('longtext_translationPreview').innerHTML = marked(document.getElementById('longtext_translation').value);
}

async function saveLongText() {
  const data = {
    longtextId: document.getElementById('selectLongText').value,
    languageId: document.getElementById('selectLanguage').value,
    content:    document.getElementById('longtext_translation').value,
  }

  if (! data.longtextId) {
    showError('No long text selected');
    return
  }

  const url      = '/admin/ajaxAdmin.php?function=saveLongText';
  const response = await fetchFromServer(url, data);

  if (response.error) showError(response.error);
  else if (response.ok) {
    if (data.languageId === 'en') {
      document.getElementById('longtext').value = data.content;
    }
    updatePreviews();
    textModified = false;
    showMessage(translate('Saved'), 1);
  }
}

