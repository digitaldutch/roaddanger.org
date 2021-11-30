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
  } else if (url.pathname.startsWith('/admin/questionnaires')) {

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

      html += `<tr id="tr0_${user.id}" ${trClass}>
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

    if (! tableData[0]) tableData[0] = [];
    const url = '/admin/ajax.php?function=loadUsers&count=' + maxLoadCount + '&offset=' + tableData[0].length;
    response  = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      response.users.forEach(user => {
        user.lastactive = new Date(user.lastactive);
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

function tableDataClick(event, tableIndex=0){
  event.stopPropagation();

  const tr = event.target.closest('tr');
  if (tr) {
    const id = tr.id.substr(4);
    selectTableRow(id, tableIndex);
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
    const url      = '/admin/ajax.php?function=deleteUser&id=' + userId;
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

  const url      = '/admin/ajaxModerator.php?function=saveTranslations';
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

  const url      = '/admin/ajax.php?function=saveNewTranslation';
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

        const url      = '/admin/ajax.php?function=deleteTranslation';
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

    const url = '/admin/ajax.php?function=loadQuestionaires';
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      tableData = [
        response.questions,
        response.questionaires,
        [],
        response.questions,
      ];
    }
  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
  }

  let html = '';
  for (const question of tableData[0]) html += getQuestionTableRow(question);
  document.getElementById('tableBodyQuestions').innerHTML += html;
  if (! selectedTableData[0]) selectFirstTableRow();

  html = '';
  for (const questionaire of tableData[1]) html += getQuestionaireTableRow(questionaire);
  document.getElementById('tableBodyQuestionaires').innerHTML += html;
  if ((tableData[1].length > 0) && (! selectedTableData[1])) selectTableRow(tableData[1][0].id, 1);
}

function getQuestionTableRow(question){
  const explanationText = question.explanation? question.explanation : '';
  return `<tr id="tr0_${question.id}" draggable="true" ondragstart="onDragRowStart(event, ${question.id})" ondrop="onDropQuestion(event, ${question.id}, 0, 'saveQuestionOrder')" ondragenter="onDragEnter(event)" ondragleave="onDragLeave(event)" ondragover="onDragOver(event)" ondragend="onDragRowQuestion(event)">
  <td>${question.id}</td>
  <td>${question.text}</td>
  <td>${explanationText}</td>
</tr>`;
}

function getQuestionaireTableRow(questionaire){
  const activeText = questionaire.active? '✔' : '';
  return `<tr id="tr1_${questionaire.id}">
  <td>${questionaire.id}</td>
  <td>${questionaire.title}</td>
  <td>${questionaireTypeToText(questionaire.type)}</td>
  <td style="text-align: center">${activeText}</td>
</tr>`;
}

function newQuestion() {
  document.getElementById('questionId').value           = null;
  document.getElementById('questionText').value         = '';
  document.getElementById('questionExplanation').value  = '';

  document.getElementById('headerQuestion').innerText   = 'New question';
  document.getElementById('formQuestion').style.display = 'flex';
  document.getElementById('questionText').focus();
}

function editQuestion() {
  document.getElementById('questionId').value           = selectedTableData[0].id;
  document.getElementById('questionText').value         = selectedTableData[0].text;
  document.getElementById('questionExplanation').value  = selectedTableData[0].explanation;

  document.getElementById('headerQuestion').innerText   = 'Edit question';
  document.getElementById('formQuestion').style.display = 'flex';
  document.getElementById('questionText').focus();
}

async function saveQuestion() {
  if (! selectedTableData[0]) {
    showError('No question selected');
    return;
  }

  const serverData = {
    id:          document.getElementById('questionId').value,
    text:        document.getElementById('questionText').value.trim(),
    explanation: document.getElementById('questionExplanation').value.trim(),
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
  if (! selectedTableData[0]) {
    showError('No question selected');
    return;
  }

  confirmWarning(`Delete crash question "${selectedTableData[0].id}"?<br><b>Think twice. It deletes the question and all answers!</b>`,
    async () => {
      const serverData = {
        id: selectedTableData[0].id,
      };

      const url      = '/admin/ajax.php?function=deleteQuestion';
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
    `Delete question id ${selectedTableData[0].id} and all answers`
  );
}

function newQuestionaire() {
  document.getElementById('questionaireId').value            = null;
  document.getElementById('questionaireTitle').value         = null;
  document.getElementById('questionaireType').value          = null;
  document.getElementById('questionaireActive').checked      = false;
  document.getElementById('questionaireQuestions').innerHTML = '';

  document.getElementById('headerQuestionaire').innerText   = 'New questionaire';
  document.getElementById('formQuestionaire').style.display = 'flex';
  document.getElementById('questionaireTitle').focus();
}

function getQuestionaireQuestionRow(question) {
return `<tr id="tr2_${question.id}" draggable="true" ondragstart="onDragRowStart(event, ${question.id})" ondrop="onDropQuestion(event, ${question.id}, 2)" ondragenter="onDragEnter(event)" ondragleave="onDragLeave(event)" ondragover="onDragOver(event)" ondragend="onDragRowQuestion(event)"><td>${question.id}</td><td>${question.text}</td></tr>`;
}

function editQuestionaire() {
  document.getElementById('questionaireId').value            = selectedTableData[1].id;
  document.getElementById('questionaireTitle').value         = selectedTableData[1].title;
  document.getElementById('questionaireType').value          = selectedTableData[1].type;
  document.getElementById('questionaireActive').checked      = selectedTableData[1].active;
  document.getElementById('questionaireQuestions').innerHTML = '⌛';

  document.getElementById('headerQuestionaire').innerText    = 'Edit questionaire';
  document.getElementById('formQuestionaire').style.display  = 'flex';
  document.getElementById('questionaireTitle').focus();

  getQuestions(selectedTableData[1].id).then(
    response => {
      tableData[2] = response.questions;
      let html = '';
      for (const question of response.questions) {
        html += getQuestionaireQuestionRow(question);
      }

      if (html) html = `<table class="dataTable"><thead><th>Id</th><th>Question</th></thead><tbody id="tbodyQuestionnaireQuestions" onclick="tableDataClick(event, 2);">${html}</tbody></table>`;
      else      html = '<div>No questions found</div>';

      document.getElementById('questionaireQuestions').innerHTML = html;

      if (! selectedTableData[2]) selectFirstTableRow(2);
    }
  );
}

async function addQuestionToQuestionnaire() {
  document.getElementById('formAddQuestion').style.display = 'flex';

  const newQuestions     = tableData[0];
  const currentQuestions = tableData[2];

  let html = '';
  for (const newQuestion of newQuestions) {
    // Leave out already selected questions
    if (currentQuestions.find(q => q.id === newQuestion.id)) continue;

    html += `<tr id="tr3_${newQuestion.id}"><td>${newQuestion.id}</td><td>${newQuestion.text}</td></tr>`;
  }

  if (html) html = `<table class="dataTable"><thead><th>Id</th><th>Question</th></thead><tbody onclick="tableDataClick(event, 3);" ondblclick="selectQuestionToQuestionnaire();">${html}</tbody></table>`;
  else      html = '<div>No questions found</div>';

  document.getElementById('addQuestionQuestions').innerHTML = html;
}

async function removeQuestionFromQuestionnaire() {
  const question = selectedTableData[2];
  if (! question) {
    showError('No question selected');
    return;
  }

  confirmWarning(`Remove question?<br></br>${question.id}: ${question.text}`, () => {
    tableData[2] = tableData[2].filter(q => q.id !== question.id);
    document.getElementById('tr2_' + question.id).remove();
    selectFirstTableRow(2);
  });
}

async function selectQuestionToQuestionnaire() {
  const newQuestion = selectedTableData[3];

  tableData[2].push(newQuestion);

  const html = getQuestionaireQuestionRow(newQuestion);
  document.getElementById('tbodyQuestionnaireQuestions').insertAdjacentHTML("beforeend", html);

  closePopupForm();
}

async function saveQuestionnaire() {
  if (! selectedTableData[1]) {
    showError('No questionaire selected');
    return;
  }

  const questionIds = tableData[2].map(item => item.id);

  const serverData = {
    id:          parseInt(document.getElementById('questionaireId').value),
    title:       document.getElementById('questionaireTitle').value.trim(),
    type:        parseInt(document.getElementById('questionaireType').value),
    active:      document.getElementById('questionaireActive').checked,
    questionIds: questionIds,
  };

  if (! serverData.title)       {showError('Title field is empty'); return;}
  if (serverData.type === null) {showError('Type is not selected'); return;}

  const url      = '/admin/ajax.php?function=savequestionnaire';
  const response = await fetchFromServer(url, serverData);

  if (response.error) showError(response.error);
  else if (response.ok) {
    location.reload();
  }
}

function deleteQuestionaire() {
  if (! selectedTableData[1]) {
    showError('No questionaire selected');
    return;
  }

  confirmWarning(`Delete questionaire ${selectedTableData[1].id} - "${selectedTableData[1].title}"?`, () => {
      confirmWarning(`To be sure I am asking one last time:<br><br>Delete questionaire ${selectedTableData[1].id} - "${selectedTableData[1].title}"?`,
        async () => {
          const serverData = {
            id: selectedTableData[1].id,
          };

          const url      = '/admin/ajax.php?function=deleteQuestionaire';
          const response = await fetchFromServer(url, serverData);

          if (response.error) showError(response.error);
          else if (response.ok) {
            document.getElementById('tr1_' + serverData.id).remove();
            selectedTableData[1] = null;
            tableData[1]         = tableData[1].filter(d => d.id !== serverData.id);
            showMessage('Questionaire deleted', 1);

            selectFirstTableRow(1);
          }
        },
        `Yes I really want to delete this questionaire`
      );
    },
    `Delete questionaire id ${selectedTableData[1].id}`);
}

function onDragRowStart(event, id){
  const row = event.target;
  const dragData = {id: id, trId: row.id};
  event.dataTransfer.setData("text/plain", JSON.stringify(dragData));
  row.classList.add('dragged');

  const table = row.closest('table');
  table.classList.add('tableDragging');
}

async function saveQuestionOrder(){
  const questionIds = tableData[0].map(item => item.id);
  const url         = '/admin/ajax.php?function=saveQuestionsOrder';
  const response    = await fetchFromServer(url, questionIds);

  if (response.error) {
    showError(response.error);
    return;
  }

  showMessage('Questions order saved', 1);
}

function onDropQuestion(event, id, tableIndex, saveFunctionName=''){
  const dragData    = JSON.parse(event.dataTransfer.getData("text/plain"));
  const sourceIndex = tableData[tableIndex].findIndex(i => i.id === dragData.id);
  const targetIndex = tableData[tableIndex].findIndex(i => i.id === id);

  tableData[tableIndex].move(sourceIndex, targetIndex);

  const insertAfter = sourceIndex < targetIndex;
  onDropRow(event, dragData.trId, insertAfter);

  if (saveFunctionName) window[saveFunctionName]();
}

function onDragRowQuestion(event) {
  const table = event.target.closest('table');
  table.classList.remove('tableDragging');
  onDragEnd(event);
}
