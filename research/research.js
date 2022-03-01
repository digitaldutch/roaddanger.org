
async function initResearch(){
  spinnerLoad = document.getElementById('spinnerLoad');

  await loadUserData();

  // Only moderators allowed
  if (! user.moderator) return;

  initPage();

  initSearchBar();

  const url = new URL(location.href);
  const searchHealthDead   = url.searchParams.get('hd');
  const searchChild        = url.searchParams.get('child');
  const searchYear         = url.searchParams.get('year');
  const searchGroup        = url.searchParams.get('group');
  const searchPersons      = url.searchParams.get('persons');
  const searchNoUnilateral = url.searchParams.get('noUnilateral');

  if (searchHealthDead) document.getElementById('filterResearchDead').classList.add('buttonSelectedBlue');
  if (searchChild)      document.getElementById('filterResearchChild').classList.add('buttonSelectedBlue');
  if (searchYear)       document.getElementById('filterResearchYear').value = searchYear;
  if (searchGroup)      document.getElementById('filterResearchGroup').value = searchGroup;
  if (searchPersons)    setPersonsFilter(searchPersons);

  const filterNoUnilateral = document.getElementById('filterResearchNoUnilateral');
  if (filterNoUnilateral) {
    if (searchNoUnilateral && (searchNoUnilateral === "0")) filterNoUnilateral.classList.add('buttonSelectedBlue');
    else filterNoUnilateral.classList.add('buttonSelectedBlue');
  }

  if (url.pathname.startsWith('/research/questionnaires/options')) {

    await loadQuestionnaires();
  } else if (url.pathname.startsWith('/research/questionnaires/fill_in')) {

    await loadArticlesUnanswered();
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

async function loadArticlesUnanswered() {
  try {
    spinnerLoad.style.display = 'block';

    const url = '/research/ajax.php?function=loadArticlesUnanswered';
    const response = await fetchFromServer(url);

    response.articles.forEach(article => {
      article.crash_date  = new Date(article.crash_date);
    });

    if (response.error) showError(response.error);
    else {

      crashes  = response.crashes;
      articles = response.articles;

      let html = '';
      for (const article of response.articles) {
        const crash = getCrashFromID(article.crashid);
        let htmlIcons = getCrashButtonsHTML(crash);
        if (crash.unilateral) htmlIcons += getIconUnilateral();

        htmlIcons = '<div style="display: flex; flex-direction: row;">' + htmlIcons + '</div>'

        html += `
          <tr id="article${article.id}" onclick="showQuestionsForm(${article.crashid}, ${article.id})">
            <td style="white-space: nowrap;">${article.crash_date.pretty()}</td>
            <td class="td300">${htmlIcons}</td>
            <td class="td400">${article.title}</td>
          </tr>`;
      }

      document.getElementById('dataTableArticles').innerHTML = html;

      if (response.articles) {
        const firstArticle = response.articles[0];
        showQuestionsForm(firstArticle.crashid, firstArticle.id);
      }

    }
  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
  }
}

function questionnaireFilterChange() {
  const url = new URL(location.href);

  const questionnaireId = parseInt(document.getElementById('filterQuestionnaire').value);

  url.searchParams.set('id', questionnaireId);

  window.history.replaceState(null, null, url);

  loadQuestionnaireResults();
}

function getBarSegment(widthPercentage, color, text='') {
  return `<div style="width: ${widthPercentage}%; background-color: ${color};"><span>${text}</span></div>`;
}

function getBechdelBarHtml(bechdelResults, questions) {
  if (! bechdelResults) return ['', '<div>No results found</div>'];

  const stats = {
    yes:              bechdelResults.yes,
    no:               bechdelResults.no,
    not_determinable: bechdelResults.not_determinable,
  };
  stats.total = stats.yes + stats.no + stats.not_determinable;

  let bechdelItems = [];
  let total = 0;
  for (let i=bechdelResults.total_questions_passed.length - 1; i >=0 ; i--) {
    const amount = bechdelResults.total_questions_passed[i];

    total += amount;
    const segment = {passed: i, amount: amount};

    bechdelItems.push(segment);
  }

  let htmlStatistics = '';
  let htmlBar       = '';
  bechdelItems.forEach(item => {
    item.amountPercentage = item.amount / total * 100;
    item.text = item.passed + '/' + questions.length;
    const score = item.passed / questions.length;

    let color = '';
    switch (true) {
      case score === 1:   color = '#8eff8e'; break;
      case score >= 0.75: color = '#e8ec49'; break;
      case score >= 0.50: color = '#ffdaa2'; break;
      case score >= 0.25: color = '#ffb465'; break;
      default:            color = '#ffa2a2';
    }

    let htmlPassed = item.amount;

    if ((item.amount) && (total > 0)) {
      htmlBar += getBarSegment(item.amountPercentage, color, item.text + ': ' + Math.round(item.amountPercentage) + '%');

      htmlPassed += ' (' + item.amountPercentage.toFixed(2) + ')%';
    }

    htmlStatistics = `<tr><td>Questions answered with Yes: <span style="border-bottom: 3px solid ${color}; padding: 3px;">${item.text}</span></td>` +
      `<td style="text-align: center;"><span style="border-bottom: 3px solid ${color}; padding: 3px;">${htmlPassed}</span></td></tr>` + htmlStatistics;
  });

  if (stats.total > 0) {
    stats.yes_percentage              = 100 * stats.yes / stats.total;
    stats.no_percentage               = 100 * stats.no / stats.total;
    stats.not_determinable_percentage = 100 * stats.not_determinable / stats.total;
  }

  if (! htmlBar) htmlBar = '<table><tr><td>&nbsp;</td></tr></table>';
  htmlBar = '<div class="questionnaireBar" style="white-space: nowrap;">' + htmlBar + '</div>';
  htmlStatistics += `<tr><td>Not determinable</td><td style="text-align: center;">${stats.not_determinable}</td></tr>`;

  return [htmlBar, htmlStatistics];
}

async function loadQuestionnaireResults() {

  try {
    spinnerLoad.style.display = 'block';

    const data = {
      filter: {
        questionnaireId: parseInt(document.getElementById('filterQuestionnaire').value),
        healthDead:      document.getElementById('filterResearchDead').classList.contains('buttonSelectedBlue')? 1 : 0,
        child:           document.getElementById('filterResearchChild').classList.contains('buttonSelectedBlue')? 1 : 0,
        noUnilateral:    document.getElementById('filterResearchNoUnilateral').classList.contains('buttonSelectedBlue')? 1 : 0,
        year:            document.getElementById('filterResearchYear').value,
        persons:         getPersonsFromFilter(),
      },
      group: document.getElementById('filterResearchGroup').value,
    }

    const url      = '/research/ajax.php?function=loadQuestionnaireResults';
    const response = await fetchFromServer(url, data);

    if (response.error) showError(response.error);
    else if (response.ok) {

      document.getElementById('questionnaireInfo').innerHTML = 'Questionnaire type: ' + questionnaireTypeToText(response.questionnaire.type) +
        ` | Country: ` + response.questionnaire.country;

      let htmlQuestions = '';
      let htmlHead      = '';
      let htmlBody      = '';
      let htmlBars      = '';

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

        document.getElementById('questionnaireBechdelIntro').style.display = 'block';
        document.getElementById('questionnaireBechdelQuestions').innerHTML = htmlQuestions;

        // Draw Bechdel bars
        let htmlBar;
        let htmlStats;
        if (data.group === 'year') {
          response.bechdelResults.sort((a, b) => b.year - a.year);

          for (const groupResults of response.bechdelResults) {
            [htmlBar, htmlStats] = getBechdelBarHtml(groupResults, response.questionnaire.questions);

            const htmlYearHeader = `<div><span style="font-weight: bold; margin-top: 5px;">${groupResults.year}</span> · ${groupResults.total_articles} articles</div>`;
            htmlBars += htmlYearHeader + htmlBar;
            htmlBody += htmlYearHeader + htmlStats;
          }

        } else if (data.group === 'source') {
          response.bechdelResults.sort((a, b) => a.source.localeCompare(b.source));

          for (const groupResults of response.bechdelResults) {
            [htmlBar, htmlStats] = getBechdelBarHtml(groupResults, response.questionnaire.questions);

            const htmlBarHeader = `<div><span style="font-weight: bold; margin-top: 5px;">${groupResults.source}</span> · ${groupResults.total_articles} articles</div>`;
            htmlBars += htmlBarHeader + htmlBar;
            htmlBody += htmlBarHeader + htmlStats;
          }

        } else {
          [htmlBar, htmlStats] = getBechdelBarHtml(response.bechdelResults, response.questionnaire.questions);
          htmlBars += htmlBar;
          htmlBody += htmlStats;
        }

        htmlHead = '';
      }

      document.getElementById('questionnaireBars').innerHTML    = htmlBars;
      document.getElementById('tableHead').innerHTML            = htmlHead;
      document.getElementById('tableBody').innerHTML            = htmlBody;
      document.getElementById('headerStatistics').style.display = 'block';
    }

  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
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

function clickQuestionnaireOption() {
  if (event.target.classList.contains('menuButton')) event.target.classList.toggle('buttonSelectedBlue');
}

function selectFilterQuestionnaireResults() {
  const dead         = document.getElementById('filterResearchDead').classList.contains('buttonSelectedBlue');
  const child        = document.getElementById('filterResearchChild').classList.contains('buttonSelectedBlue');
  const noUnilateral = document.getElementById('filterResearchNoUnilateral').classList.contains('buttonSelectedBlue');
  const year         = document.getElementById('filterResearchYear').value;
  const group        = document.getElementById('filterResearchGroup').value;

  // Update url
  const searchPersons = getPersonsFromFilter();

  const url = new URL(window.location);
  if (dead)  url.searchParams.set('hd', 1); else url.searchParams.delete('hd');
  if (child) url.searchParams.set('child', 1); else url.searchParams.delete('child');
  if (! noUnilateral) url.searchParams.set('noUnilateral', 0); else url.searchParams.delete('noUnilateral');
  if (year)  url.searchParams.set('year', year); else url.searchParams.delete('year');
  if (group) url.searchParams.set('group', group); else url.searchParams.delete('group');
  if (searchPersons.length > 0) url.searchParams.set('persons', searchPersons.join());

  window.history.pushState(null, null, url.toString());

  loadQuestionnaireResults();
}
