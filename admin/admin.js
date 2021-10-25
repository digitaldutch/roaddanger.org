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
      const modifiedTexts = tableData.filter(d => d.modified === true);
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
  } else if (url.pathname.startsWith('/admin/questions')) {

    await loadQuestions();
  }
}

async function loadUsers(){
  let response;
  const maxLoadCount = 50;

  function showUsers(users){
    let html = '';
    for (const user of users){
      let trClass = '';
      if      (user.permission === TUserPermission.admin)     trClass = ' class="bgRed" ';
      else if (user.permission === TUserPermission.moderator) trClass = ' class="bgOrange" ';

      html += `<tr id="tr${user.id}" ${trClass}>
<td>${user.id}</td>
<td>${user.name}<br><a href="mailto:${user.email}">${user.email}</a></td>
<td>${datetimeToAge(user.lastactive)}</td>
<td>${permissionToText(user.permission)}</td>
<td class="trButton"><span class="editDetails">⋮</span></td>
</tr>`;
    }

    document.getElementById('tableBody').innerHTML += html;
  }

  try {
    spinnerLoad.style.display = 'block';
    observerSpinner.unobserve(spinnerLoad);

    const url = '/admin/ajax.php?function=loadUsers&count=' + maxLoadCount + '&offset=' + tableData.length;
    response  = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      response.users.forEach(user => {
        user.lastactive = new Date(user.lastactive);
      });

      tableData = tableData.concat(response.users);
      showUsers(response.users);
    }

  } catch (error) {
    showError(error.message);
  } finally {
    if (response.error || (response.users.length < maxLoadCount)) spinnerLoad.style.display = 'none';
  }

  if (response.users.length >= maxLoadCount) observerSpinner.observe(spinnerLoad);
}

function tableDataClick(event){
  event.stopPropagation();

  const tr = event.target.closest('tr');
  if (tr) {
    const id = tr.id.substr(2);
    selectTableRow(id);
  }

  closeAllPopups();
  if (event.target.classList.contains('editDetails')) showUserMenu(event.target);
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
  document.getElementById('userID').value         = selectedTableData.id;
  document.getElementById('userEmail').value      = selectedTableData.email;
  document.getElementById('userFirstName').value  = selectedTableData.firstname;
  document.getElementById('userLastName').value   = selectedTableData.lastname;
  document.getElementById('userPermission').value = selectedTableData.permission;

  document.getElementById('formEditUser').style.display    = 'flex';
}

async function deleteUserDirect() {
  try {
    const userId   = selectedTableData.id;
    const url      = '/admin/ajax.php?function=deleteUser&id=' + userId;
    const response = await fetchFromServer(url);
    if (response.error) showError(response.error);
    else {
      tableData = tableData.filter(user => user.id !== userId);
      document.getElementById('tr' + userId).remove();
      showMessage(translate('Deleted'), 1);
    }
  } catch (error) {
    showError(error.message);
  }
}

async function adminDeleteUser() {
  confirmWarning(`Mens #${selectedTableData.id} "${selectedTableData.name}" en alle items die dit mens heeft aangemaakt verwijderen?<br><br><b>Dit kan niet ongedaan worden!</b>`,
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

  if (! user.email)                {showError(translate('Email_not_filled_in')); return;}
  if (! validateEmail(user.email)) {showError(translate('Email_not_valid')); return;}
  if (! user.firstname)            {showError(translate('First_name_not_filled_in')); return;}
  if (! user.lastname)             {showError(translate('Last_name_not_filled_in')); return;}

  const url      = '/admin/ajax.php?function=saveUser';
  const response = await fetchFromServer(url, user);

  if (response.error) {
    showError(response.error, 10);
  } else {
    showMessage(translate('Saved'), 1);
    window.location.reload();
  }
}

function afterLoginAction(){
  window.location.reload();
}

function translationNeeded(text) {
  return text && text.charAt(text.length - 1) === '*';
}

function getTranslationTableRow(translation){
  const text = translationNeeded(translation.translation)? '' : translation.translation;

  return `
<tr id="tr${translation.id}">
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

      tableData = dataTranslationNeeded.concat(dataTranslated);

      showTranslations(tableData);

      if ((tableData.length > 0) && (! selectedTableData)) selectTableRow(tableData[0].id);
    }

  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
  }
}

function saveTranslation(id) {
  const td   = event.target.closest('td');
  const item = tableData.find(d => d.id === id);

  item.translation = td.innerText.trim();
  item.modified    = true;
}

async function saveTranslations() {
  const serverData = {
    language:        user.language,
    newTranslations: tableData.filter(d => d.modified === true),
  };

  if (serverData.newTranslations.length === 0) {
    showMessage(translate('No_changes'), 1);
    return;
  }

  const url      = '/admin/ajaxModerator.php?function=saveTranslations';
  const response = await fetchFromServer(url, serverData);

  if (response.error) showError(response.error);
  else if (response.ok){
    tableData.forEach(d => d.modified = false);
    showMessage(translate('Saved'), 1);
  }
}

function newTranslation(){
  document.getElementById('newTranslationId').value         = '';
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

  const url      = '/admin/ajax.php?function=saveNewTranslation';
  const response = await fetchFromServer(url, translation);

  if (response.error) showError(response.error);
  else if (response.ok) {
    closePopupForm();

    translation.translation = user.language === 'en'? translation.english : '';
    tableData.unshift(translation);

    const tr = getTranslationTableRow(translation);
    document.getElementById('tableBody').innerHTML = tr + document.getElementById('tableBody').innerHTML;

    selectTableRow(translation.id);
  }
}

function deleteTranslation() {
  if (! selectedTableData) {
    showError('No translation item selected');
    return;
  }

  confirmWarning(`Delete translation item "${selectedTableData.id}"?<br>You should only do this if the translation id is not used in the source code`,
      async () => {
        const serverData = {
          id: selectedTableData.id,
        };

        const url      = '/admin/ajax.php?function=deleteTranslation';
        const response = await fetchFromServer(url, serverData);

        if (response.error) showError(response.error);
        else if (response.ok) {
          document.getElementById('tr' + serverData.id).remove();
          selectedTableData = null;
          tableData         = tableData.filter(d => d.id !== serverData.id);
          showMessage(translate('Deleted'), 1);

          if (tableData.length > 0) selectTableRow(tableData[0]);
        }
      },
      'Delete id ' + selectedTableData.id
  );
}

async function changeUserLanguage(){
  await saveTranslations();

  const languageId = document.getElementById('selectLanguage').value;
  const url        = '/ajax.php?function=setLanguage&id=' + languageId;
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
    const url      = '/admin/ajax.php?function=loadLongText';
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

  const url      = '/admin/ajax.php?function=saveLongText';
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

async function loadQuestions() {

  try {
    spinnerLoad.style.display = 'block';

    const url = '/admin/ajax.php?function=loadQuestions';
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      tableData = response.questions;

      let html = '';
      for (const question of tableData) html += getQuestionTableRow(question);
      document.getElementById('tableBody').innerHTML += html;

      if ((tableData.length > 0) && (! selectedTableData)) selectTableRow(tableData[0].id);
    }

  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
  }
}

function getQuestionTableRow(question){
  const activeText = question.active? '✔' : '';
  return `
<tr id="tr${question.id}">
  <td>${question.id}</td>
  <td>${question.text}</td>
  <td>${question.explanation}</td>
  <td style="text-align: center">${activeText}</td>
</tr>`;
}

function newQuestion() {
  document.getElementById('questionId').value           = null;
  document.getElementById('questionText').value         = '';
  document.getElementById('questionExplanation').value  = '';
  document.getElementById('questionActive').checked     = true;

  document.getElementById('headerQuestion').innerText   = 'New Question';
  document.getElementById('formQuestion').style.display = 'flex';
  document.getElementById('questionText').focus();
}

function editQuestion() {
  document.getElementById('questionId').value           = selectedTableData.id;
  document.getElementById('questionText').value         = selectedTableData.text;
  document.getElementById('questionExplanation').value  = selectedTableData.explanation;
  document.getElementById('questionActive').checked     = selectedTableData.active;

  document.getElementById('headerQuestion').innerText   = 'Edit Question';
  document.getElementById('formQuestion').style.display = 'flex';
  document.getElementById('questionText').focus();
}

async function saveQuestion() {
  if (! selectedTableData) {
    showError('No question selected');
    return;
  }

  const serverData = {
    id:          document.getElementById('questionId').value,
    text:        document.getElementById('questionText').value.trim(),
    explanation: document.getElementById('questionExplanation').value.trim(),
    active:      document.getElementById('questionActive').checked,
  };

  if (! serverData.text) {showError('Text field is empty'); return;}

  const url      = '/admin/ajax.php?function=saveQuestion';
  const response = await fetchFromServer(url, serverData);

  if (response.error) showError(response.error);
  else if (response.ok) {
    location.reload();
  }
}

function deleteQuestion() {
  if (! selectedTableData) {
    showError('No question selected');
    return;
  }

  confirmWarning(`Delete crash question "${selectedTableData.id}"?<br><b>Think twice. It deletes the question and all answers!</b>`,
    async () => {
      const serverData = {
        id: selectedTableData.id,
      };

      const url      = '/admin/ajax.php?function=deleteQuestion';
      const response = await fetchFromServer(url, serverData);

      if (response.error) showError(response.error);
      else if (response.ok) {
        document.getElementById('tr' + serverData.id).remove();
        selectedTableData = null;
        tableData         = tableData.filter(d => d.id !== serverData.id);
        showMessage(translate('Deleted'), 1);

        if (tableData.length > 0) selectTableRow(tableData[0].id);
      }
    },
    `Delete question id ${selectedTableData.id} and all answers`
  );
}
