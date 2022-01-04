let spinnerLoad;

async function initResearch(){
  spinnerLoad = document.getElementById('spinnerLoad');

  await loadUserData();

  // Only moderators allowed
  if (! user.moderator) return;

  initPage();

  const url = new URL(location.href);
  const searchHealthDead = url.searchParams.get('hd');
  const searchChild      = url.searchParams.get('child');

  if (searchHealthDead) document.getElementById('filterResearchDead').classList.add('buttonSelectedBlue');
  if (searchChild)      document.getElementById('filterResearchChild').classList.add('buttonSelectedBlue');

  if (url.pathname.startsWith('/research/questionnaires/options')) {

    await loadQuestionnaires();
  } else if (url.pathname.startsWith('/research/questionnaires')) {

    const idQuestionnaire = url.searchParams.get('id');
    if (idQuestionnaire) document.getElementById('filterQuestionnaire').value = idQuestionnaire;

    await loadQuestionnaireResults();
  }
}

async function loadQuestionnaires() {

  try {
    spinnerLoad.style.display = 'block';

    const url = '/research/ajax.php?function=loadQuestionnaires';
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      tableData = [
        response.questions,
        response.questionnaires,
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
  for (const questionnaire of tableData[1]) html += getQuestionnaireTableRow(questionnaire);
  document.getElementById('tableBodyQuestionnaires').innerHTML += html;
  if ((tableData[1].length > 0) && (! selectedTableData[1])) selectTableRow(tableData[1][0].id, 1);
}

function questionnaireFilterChange() {
  const url = new URL(location.href);

  const questionnaireId = parseInt(document.getElementById('filterQuestionnaire').value);

  url.searchParams.set('id', questionnaireId);

  window.history.replaceState(null, null, url);

  loadQuestionnaireResults();
}

async function loadQuestionnaireResults() {
  const data = {
    filter: {
      questionnaireId: parseInt(document.getElementById('filterQuestionnaire').value),
      healthDead:      document.getElementById('filterResearchDead').classList.contains('buttonSelectedBlue')? 1 : 0,
      child:           document.getElementById('filterResearchChild').classList.contains('buttonSelectedBlue')? 1 : 0,
    }
  }

  const url      = '/research/ajax.php?function=loadQuestionnaireResults';
  const response = await fetchFromServer(url, data);

  if (response.error) showError(response.error);
  else if (response.ok) {

    document.getElementById('questionnaireInfo').innerHTML = 'Questionnaire type: ' + questionnaireTypeToText(response.questionnaire.type) +
      ` | Country: ` + response.questionnaire.country;

    let htmlQuestions = '';
    let htmlBars      = '';
    let htmlHead      = '';
    let htmlBody      = '';

    if (response.questionnaire.type === QuestionnaireType.standard) {

      htmlHead = '<tr><th style="text-align: left;">Question</th><th>Yes</th><th>No</th><th>n.d.</th></tr>';
      for (const question of response.questions) {
        htmlBody += `<tr><td>${question.question_id} ${question.question}<td style="text-align: right;">${question.yes}</td><td style="text-align: right;">${question.no}</td><td style="text-align: right;">${question.not_determinable}</td></tr>`;
      }

    } else if (response.questionnaire.type === QuestionnaireType.bechdel) {

      let i=1;
      for (let question of response.questionnaire.questions) {
        htmlQuestions += `<div>${i}) ${question.text}</div>`;
        i += 1;
      }

      const stats = {
        yes:              response.bechdelResults.yes,
        no:               response.bechdelResults.no,
        not_determinable: response.bechdelResults.not_determinable,
      };

      stats.total = stats.yes + stats.no + stats.not_determinable;
      if (stats.total > 0) {
        stats.yes_percentage              = 100 * stats.yes / stats.total;
        stats.no_percentage               = 100 * stats.no / stats.total;
        stats.not_determinable_percentage = 100 * stats.not_determinable / stats.total;
      }

      htmlBars = `<div>
  <div style="width: ${stats.yes_percentage}%; background-color: #8eff8e;"><span>${Math.round(stats.yes_percentage)}%</span></div>
  <div style="width: ${stats.no_percentage}%; background-color: #ffa2a2;"><span>${Math.round(stats.no_percentage)}%</span></div>
  <div style="width: ${stats.not_determinable_percentage}%; background-color: #cccccc;"><span>${Math.round(stats.not_determinable_percentage)}%</span></div>
</div>`;
      htmlHead = '<tr><th style="width: 33%;">Passed</th><th style="width: 33%;">Failed</th><th style="width: 33%;">Not determinable</th></tr>';
      htmlBody += `<tr>
  <td style="text-align: center;">${Math.round(stats.yes)} (${Math.round(stats.yes_percentage)}%)</td>
  <td style="text-align: center;">${Math.round(stats.no)} (${Math.round(stats.no_percentage)}%)</td>
  <td style="text-align: center;">${Math.round(stats.not_determinable)} (${Math.round(stats.not_determinable_percentage)}%)</td>
</tr>`;

      document.getElementById('questionnaireBechdelIntro').style.display = 'block';
      document.getElementById('questionnaireBechdelQuestions').innerHTML = htmlQuestions;
    }

    document.getElementById('questionnaireBars').innerHTML             = htmlBars;
    document.getElementById('tableHead').innerHTML                     = htmlHead;
    document.getElementById('tableBody').innerHTML                     = htmlBody;
  }
}

function getQuestionTableRow(question){
  const explanationText = question.explanation? question.explanation : '';
  return `<tr id="tr0_${question.id}" draggable="true" ondragstart="onDragRowStart(event, ${question.id})" ondrop="onDropQuestion(event, ${question.id}, 0, 'saveQuestionOrder')" ondragenter="onDragEnter(event)" ondragleave="onDragLeave(event)" ondragover="onDragOver(event)" ondragend="onDragRowQuestion(event)">
  <td>${question.id}</td>
  <td>${question.text}</td>
  <td>${explanationText}</td>
</tr>`;
}

function getQuestionnaireTableRow(questionnaire){
  const activeText = questionnaire.active? '✔' : '';
  return `<tr id="tr1_${questionnaire.id}">
  <td>${questionnaire.id}</td>
  <td>${questionnaire.title}</td>
  <td>${questionnaireTypeToText(questionnaire.type)}</td>
  <td>${questionnaire.country_id}</td>
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

  const url      = '/research/ajax.php?function=saveQuestion';
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

      const url      = '/research/ajax.php?function=deleteQuestion';
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

function newQuestionnaire() {
  document.getElementById('questionnaireId').value                  = null;
  document.getElementById('questionnaireTitle').value               = null;
  document.getElementById('questionnaireType').value                = null;
  document.getElementById('questionnaireCountryId').value           = null;
  document.getElementById('questionnaireActive').checked            = false;
  document.getElementById('tbodyQuestionnaireQuestions').innerHTML = '';

  document.getElementById('headerQuestionnaire').innerText   = 'New questionnaire';
  document.getElementById('formQuestionnaire').style.display = 'flex';
  document.getElementById('questionnaireTitle').focus();
}

function getQuestionnaireQuestionRow(question) {
return `<tr id="tr2_${question.id}" draggable="true" ondragstart="onDragRowStart(event, ${question.id})" ondrop="onDropQuestion(event, ${question.id}, 2)" ondragenter="onDragEnter(event)" ondragleave="onDragLeave(event)" ondragover="onDragOver(event)" ondragend="onDragRowQuestion(event)"><td>${question.id}</td><td>${question.text}</td></tr>`;
}

function editQuestionnaire() {
  document.getElementById('questionnaireId').value            = selectedTableData[1].id;
  document.getElementById('questionnaireTitle').value         = selectedTableData[1].title;
  document.getElementById('questionnaireType').value          = selectedTableData[1].type;
  document.getElementById('questionnaireCountryId').value     = selectedTableData[1].country_id;
  document.getElementById('questionnaireActive').checked      = selectedTableData[1].active;

  document.getElementById('tbodyQuestionnaireQuestions').innerHTML = '';
  document.getElementById('headerQuestionnaire').innerText         = 'Edit questionnaire';
  document.getElementById('formQuestionnaire').style.display       = 'flex';
  document.getElementById('questionnaireTitle').focus();

  document.getElementById('questionnaireSpinner').style.display = 'block';
  getQuestions(selectedTableData[1].id).then(
    response => {
      tableData[2] = response.questions;
      let html = '';
      for (const question of response.questions) {
        html += getQuestionnaireQuestionRow(question);
      }

      document.getElementById('tbodyQuestionnaireQuestions').innerHTML = html;
      document.getElementById('questionnaireNoneFound').style.display  = html? 'none' : 'block';
      document.getElementById('questionnaireSpinner').style.display        = 'none';

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

  confirmWarning(`Remove question?<br><br>${question.id}: ${question.text}`, () => {
    tableData[2] = tableData[2].filter(q => q.id !== question.id);
    document.getElementById('tr2_' + question.id).remove();
    selectFirstTableRow(2);
  });
}

async function selectQuestionToQuestionnaire() {
  const newQuestion = selectedTableData[3];

  tableData[2].push(newQuestion);

  const html = getQuestionnaireQuestionRow(newQuestion);
  const div = document.getElementById('tbodyQuestionnaireQuestions');
  div.insertAdjacentHTML("beforeend", html);

  closePopupForm();
}

async function saveQuestionnaire() {
  if (! selectedTableData[1]) {
    showError('No questionnaire selected');
    return;
  }

  const questionIds = tableData[2].map(item => item.id);

  const serverData = {
    id:          parseInt(document.getElementById('questionnaireId').value),
    title:       document.getElementById('questionnaireTitle').value.trim(),
    type:        parseInt(document.getElementById('questionnaireType').value),
    countryId:   document.getElementById('questionnaireCountryId').value,
    active:      document.getElementById('questionnaireActive').checked,
    questionIds: questionIds,
  };

  if (! serverData.title)       {showError('Title field is empty'); return;}
  if (serverData.type === null) {showError('No type selected'); return;}
  if (! serverData.countryId)   {showError('No country selected'); return;}

  const url      = '/research/ajax.php?function=savequestionnaire';
  const response = await fetchFromServer(url, serverData);

  if (response.error) showError(response.error);
  else if (response.ok) {
    location.reload();
  }
}

function deleteQuestionnaire() {
  if (! selectedTableData[1]) {
    showError('No questionnaire selected');
    return;
  }

  confirmWarning(`Delete questionnaire ${selectedTableData[1].id} - "${selectedTableData[1].title}"?`, () => {
      confirmWarning(`To be sure I am asking one last time:<br><br>Delete questionnaire ${selectedTableData[1].id} - "${selectedTableData[1].title}"?`,
        async () => {
          const serverData = {
            id: selectedTableData[1].id,
          };

          const url      = '/research/ajax.php?function=deleteQuestionnaire';
          const response = await fetchFromServer(url, serverData);

          if (response.error) showError(response.error);
          else if (response.ok) {
            document.getElementById('tr1_' + serverData.id).remove();
            selectedTableData[1] = null;
            tableData[1]         = tableData[1].filter(d => d.id !== serverData.id);
            showMessage('Questionnaire deleted', 1);

            selectFirstTableRow(1);
          }
        },
        `Yes I really want to delete this questionnaire`
      );
    },
    `Delete questionnaire id ${selectedTableData[1].id}`);
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
  const url         = '/research/ajax.php?function=saveQuestionsOrder';
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

function selectFilterQuestionnaireResults() {
  event.target.classList.toggle('buttonSelectedBlue');

  const dead  = document.getElementById('filterResearchDead').classList.contains('buttonSelectedBlue');
  const child = document.getElementById('filterResearchChild').classList.contains('buttonSelectedBlue');

  const url = new URL(window.location);
  if (dead)  url.searchParams.set('hd', 1); else url.searchParams.delete('hd');
  if (child) url.searchParams.set('child', 1); else url.searchParams.delete('child');

  window.history.pushState(null, null, url.toString());

  loadQuestionnaireResults();
}
