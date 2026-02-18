let aiModels;
let lastGenerationId;
let aiPrompt = null;
let questionnaire = null;

async function initResearch(){
  spinnerLoad = document.getElementById('spinnerLoad');

  await loadUserData();

  initPage();

  initFilterPersons();

  const url = new URL(location.href);

  setResearchFilterFromUrl(url);

  if (url.pathname.startsWith('/research/questionnaires/settings')) {
    if (! user.admin) {showError('Not an administrator'); return;}

    await loadQuestionnaires();
  } else if (url.pathname.startsWith('/research/questionnaires/fill_in')) {
    if (! user.moderator) {showError('Permission error: Not a moderator'); return;}

    await loadArticlesUnanswered();
  } else if (url.pathname.startsWith('/research/questionnaires')) {
    const idQuestionnaire = url.searchParams.get('id');
    if (idQuestionnaire) document.getElementById('filterQuestionnaire').value = idQuestionnaire;
    await loadQuestionnaireResults();
  } else if (url.pathname.startsWith('/research/ai_prompt_builder')) {
    await initAITest();
  }
}

function setResearchFilterFromUrl(url) {
  const searchHealthDead = url.searchParams.get('hd');
  const searchChild = url.searchParams.get('child');
  const searchTimeSpan = url.searchParams.get('timeSpan');
  const searchCountry = url.searchParams.get('country');
  const searchGroup = url.searchParams.get('group');
  const searchMinArticles = url.searchParams.get('minArticles');
  const searchPersons = url.searchParams.get('persons');
  const searchNoUnilateral = url.searchParams.get('noUnilateral');

  const filterResearchDead = document.getElementById('filterResearchDead');
  const filterResearchChild = document.getElementById('filterResearchChild');
  const filterResearchTimeSpan = document.getElementById('filterResearchTimeSpan');
  const filterResearchCountry = document.getElementById('filterResearchCountry');
  const filterResearchGroup = document.getElementById('filterResearchGroup');
  const filterMinArticles = document.getElementById('filterMinArticles');
  const filterNoUnilateral = document.getElementById('filterResearchNoUnilateral');

  if (searchHealthDead && filterResearchDead) filterResearchDead.classList.add('menuButtonSelected');
  if (searchChild && filterResearchChild) filterResearchChild.classList.add('menuButtonSelected');
  if (searchTimeSpan && filterResearchTimeSpan) filterResearchTimeSpan.value = searchTimeSpan;
  if (searchCountry && filterResearchCountry) filterResearchCountry.value = searchCountry;
  if (searchGroup && filterResearchGroup) filterResearchGroup.value = searchGroup;
  if (searchMinArticles && filterMinArticles) filterMinArticles.value = searchMinArticles;

  if (searchPersons) {
    const personsCodes = searchPersons.split(',');
    setPersonsFilter(personsCodes);
  }

  if (filterNoUnilateral) {
    if (searchNoUnilateral && (searchNoUnilateral === "0")) filterNoUnilateral.classList.add('menuButtonSelected');
    else filterNoUnilateral.classList.add('menuButtonSelected');
  }
}


async function loadQuestionnaires() {

  try {
    spinnerLoad.style.display = 'block';

    const url = '/research/ajaxResearch.php?function=loadQuestionnaires';
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

    const data = {
      filter: {
        healthDead:   document.getElementById('filterResearchDead').classList.contains('menuButtonSelected')? 1 : 0,
        child:        document.getElementById('filterResearchChild').classList.contains('menuButtonSelected')? 1 : 0,
        noUnilateral: document.getElementById('filterResearchNoUnilateral').classList.contains('menuButtonSelected')? 1 : 0,
        persons:      getPersonsFromFilter(),
      },
    }

    const url = '/research/ajaxResearch.php?function=loadArticlesUnanswered';
    const response = await fetchFromServer(url, data);

    response.articles.forEach(article => {
      article.crash_date  = new Date(article.crash_date);
    });

    if (response.error) showError(response.error);
    else {

      crashes  = response.crashes;
      articles = response.articles;

      let html = '';
      for (const article of response.articles) {
        const crash = getCrashFromId(article.crashid);
        let htmlIcons = getCrashHumansIcons(crash);
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
      document.getElementById('tableWrapper').style.display = 'block';
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

function getBarSegment(widthPercentage, backgroundColor, textColor, text='', tooltip='') {
  const htmlTooltip = tooltip? ` data-tippy-content="${tooltip}"` : '';
  let label = text;

  // Hide label if too item too small to show it
  if (widthPercentage < 2) {
    label = '';
  }
  return `<div style="width: ${widthPercentage}%; background-color: ${backgroundColor}; color: ${textColor}"${htmlTooltip}><span>${label}</span></div>`;
}

function textColorFor(bg) {
  const c = d3.color(bg).rgb();
  // perceived luminance (0..255-ish)
  const L = 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b;
  return L < 140 ? "#fff" : "#000";
}

function getBechdelBarHtml(bechdelResults, questions, group='') {
  if (! bechdelResults) return ['', '<div>No results found</div>'];

  let bechdelItems = [];
  let total = 0;
  for (let i= bechdelResults.total_questions_passed.length - 1; i >= 0 ; i--) {
    const amount = bechdelResults.total_questions_passed[i];

    total += amount;
    const segment = {passed: i, amount: amount};

    bechdelItems.push(segment);
  }

  let groupName = '';
  switch (group) {
    case 'year': groupName = bechdelResults.year; break;
    case 'month': groupName = bechdelResults.yearmonth; break;
    case 'source': groupName = bechdelResults.sitename; break;
    case 'country': groupName = bechdelResults.countryid; break;
  }

  let htmlBar = '';
  const itemColors = d3.schemeRdYlGn[bechdelItems.length].slice().reverse();

  let i = 0;
  bechdelItems.forEach(item => {
    item.amountPercentage = item.amount / total * 100;

    const colorBarSegment = itemColors[i];
    const colorText = textColorFor(colorBarSegment);

    if ((item.amount) && (total > 0)) {
      const tooltip = `Humane score: ${item.passed} of ${questions.length} • ${item.amount} articles • ${item.amountPercentage.toFixed(1)} %`;
      htmlBar += getBarSegment(item.amountPercentage, colorBarSegment, colorText, item.passed, tooltip);
    }

    i++;
  });

  if (! htmlBar) htmlBar = '<div></div>';
  htmlBar = `<div class="questionnaireBar" style="white-space: nowrap;" data-group="${groupName}">${htmlBar}</div>`;

  return htmlBar;
}

async function downloadQuestionnaireResults(articleFilter={}) {
  const data = {
    filter: {
      questionnaireId: parseInt(document.getElementById('filterQuestionnaire').value),
      healthDead: document.getElementById('filterResearchDead').classList.contains('menuButtonSelected')? 1 : 0,
      child: document.getElementById('filterResearchChild').classList.contains('menuButtonSelected')? 1 : 0,
      noUnilateral: document.getElementById('filterResearchNoUnilateral').classList.contains('menuButtonSelected')? 1 : 0,
      timeSpan: document.getElementById('filterResearchTimeSpan').value,
      country: document.getElementById('filterResearchCountry').value,
      minArticles: parseInt(document.getElementById('filterMinArticles').value),
      persons: getPersonsFromFilter(),
    },
    group: document.getElementById('filterResearchGroup').value,
    articleFilter: articleFilter,
  }

  if (! data.filter.questionnaireId) {
    throw new Error('No questionnaire selected');
  }

  const url = '/research/ajaxResearch.php?function=loadQuestionnaireResults';
  return await fetchFromServer(url, data);
}

async function loadQuestionnaireResults() {

  const elResults = document.getElementById('questionnaireResults');
  try {
    spinnerLoad.style.display = 'block';
    elResults.style.display = 'none';

    const group = document.getElementById('filterResearchGroup').value;

    const response = await downloadQuestionnaireResults();

    if (response.error) showError(response.error);
    else if (response.ok) {
      questionnaire = response.questionnaire;

      document.getElementById('questionnaireHeader').innerText = questionnaire.title;
      document.getElementById('questionnaireInfo').innerHTML =
        'Type: ' + questionnaireTypeToText(questionnaire.type) +
        '<br>Country: ' + questionnaire.country +
        '<br>Public: ' + (questionnaire.public? 'Yes' : 'No') +
        '<br>Active: ' + (questionnaire.active? 'Yes' : 'No');

      let htmlQuestions = '';
      let htmlTableHead = '';
      let htmlTableBody = '';
      let htmlBars = '';

      // Show Bechdel settings if bechdel questionnaire
      const bechdelElements = document.querySelectorAll('[data-bechdel-option="true"]');
      bechdelElements.forEach(element => {
        element.style.display = questionnaire.type === QuestionnaireType.bechdel? 'block' : 'none';
      });

      // ***** Standard type questionnaire *****
      if (questionnaire.type === QuestionnaireType.standard) {

        htmlTableHead = '<tr><th style="text-align: left;">Question</th><th>Yes</th><th>No</th><th>n.d.</th></tr>';
        for (const question of questionnaire.questions) {
          htmlTableBody += `<tr data-question-id="${question.question_id}"><td>${question.question_id} ${question.question}<td style="text-align: right;">${question.yes}</td><td style="text-align: right;">${question.no}</td><td style="text-align: right;">${question.not_determinable}</td></tr>`;
        }

      } else if (questionnaire.type === QuestionnaireType.bechdel) {
        // ***** Bechdel type questionnaire *****
        let i=1;
        for (const question of questionnaire.questions) {
          htmlQuestions += `<tr><td style="vertical-align: top;">Q${i}:</td><td style="font-style: italic;">${question.text}</td></tr>`;
          i += 1;
        }

        if (htmlQuestions) htmlQuestions = '<table>' + htmlQuestions + '</table>';

        // Draw Bechdel bars
        let htmlBar;
        if (group === 'year') {
          response.bechdelResults.sort((a, b) => b.year - a.year);

          for (const groupResults of response.bechdelResults) {
            htmlBar = getBechdelBarHtml(groupResults, questionnaire.questions, group);

            const textAverage = groupResults.average.toFixed(2);
            const textTotal = groupResults.total_articles.toString();
            const htmlBarLabel = `<div data-tippy-content="Average score: ${textAverage} Articles: ${textTotal}">${groupResults.year}</div>`;

            htmlBars += htmlBarLabel + htmlBar;
          }

        } else if (group === 'month') {
          response.bechdelResults.sort((a, b) => b.yearmonth - a.yearmonth);

          for (const groupResults of response.bechdelResults) {
            htmlBar = getBechdelBarHtml(groupResults, questionnaire.questions, group);

            const groupYear = parseInt(groupResults.yearmonth.substring(0,4));
            const groupMonth = parseInt(groupResults.yearmonth.substring(4, 6));
            const tempDate = new Date(groupYear, groupMonth-1, 1);
            const monthText = tempDate.toLocaleString('default', { month: 'short' });

            const textAverage = groupResults.average.toFixed(2);
            const textTotal = groupResults.total_articles.toString();
            const htmlBarLabel = `<div data-tippy-content="Average score: ${textAverage} Articles: ${textTotal}">${groupYear + ' ' +  monthText}</div>`;

            htmlBars += htmlBarLabel + htmlBar;
          }

        } else if (group === 'source') {
          response.bechdelResults.sort((a, b) => b.average - a.average);

          for (const groupResults of response.bechdelResults) {
            htmlBar = getBechdelBarHtml(groupResults, questionnaire.questions, group);

            const textAverage = groupResults.average.toFixed(2);
            const textTotal = groupResults.total_articles.toString();
            const htmlBarLabel = `<div data-tippy-content="Average score: ${textAverage} Articles: ${textTotal}">${groupResults.sitename}</div>`;

            htmlBars += htmlBarLabel + htmlBar;
          }

        } else if (group === 'country') {
          response.bechdelResults.sort((a, b) => b.average - a.average);

          for (const groupResults of response.bechdelResults) {
            htmlBar = getBechdelBarHtml(groupResults, questionnaire.questions, group);

            const textAverage = groupResults.average.toFixed(2);
            const textTotal = groupResults.total_articles.toString();
            const htmlBarLabel = `<div data-tippy-content="Average score: ${textAverage} Articles: ${textTotal}">${groupResults.countryid}</div>`;

            htmlBars += htmlBarLabel + htmlBar;
          }

        } else {
          const textTotal = response.bechdelResults[0].total_articles.toString();

          htmlBar = getBechdelBarHtml(response.bechdelResults[0], questionnaire.questions);
          htmlBars += `<div data-tippy-content="Articles: ${textTotal}">All articles</div>` + htmlBar;
        }

        htmlTableHead = '';
      }

      document.getElementById('questionnaireBechdelIntro').style.display = htmlQuestions? 'block' : 'none';
      document.getElementById('questionnaireBechdelQuestions').innerHTML = htmlQuestions;
      document.getElementById('questionnaireBars').innerHTML = htmlBars;
      document.getElementById('tableQStatsHead').innerHTML = htmlTableHead;
      document.getElementById('tableQStatsBody').innerHTML = htmlTableBody;
      document.getElementById('headerStatistics').style.display = 'none';

      tippy('[data-tippy-content]', {allowHTML: true});
    }

  } catch (error) {
    showError(error.message);
  } finally {
    elResults.style.display = 'block';
    spinnerLoad.style.display = 'none';
  }

}

function exportQuestionnaire(asJSON=true) {
  confirmWarning('Download questionnaire data in JSON format?', async () => {

    const response = await downloadQuestionnaireResults();
    let output = {
      questionnaire:  response.questionnaire,
    }
    if (questionnaire.type === QuestionnaireType.standard) {
      output.questions = response.questions;
    } else {
      output.bechdelResults = response.bechdelResults;
    }

    const blob = new Blob([JSON.stringify(output, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `questionnaire_data_${questionnaire.id}.json`;
    link.click();
    URL.revokeObjectURL(url);
  });
}


function selectedAiModel() {
  const modelId = document.getElementById('aiModel').value;
  return aiModels.find(m => m.id === modelId);
}

function aiModelChange() {
  const model = selectedAiModel();

  const cost_input = (model.cost_input * 1e6).toFixed(2);
  const cost_output = (model.cost_output * 1e6).toFixed(2);
  const structured_outputs = model.structured_outputs? ' | structured outputs' : '';

  document.getElementById('aiModelInfo').innerHTML =
    `Created ${model.created.toLocaleDateString()}` +
    ` | $${cost_input}/M input tokens | $${cost_output}/M ouput tokens` +
    ` | ${model.context_length.toLocaleString()} context` +
    structured_outputs +
    `<br><br>` + model.description;

  showStructuredOutputSection(model.structured_outputs);
}

async function showLoadAiPromptForm() {
  const spinner = document.getElementById('spinnerLoadQueries');
  const tableBodyQueries = document.getElementById('loadAiQueriesBody');

  try {
    tableBodyQueries.innerHTML = '';
    document.getElementById('formLoadAiModel').style.display = 'flex';

    spinner.style.display = 'flex';

    const url = '/research/ajaxResearch.php?function=aiGetPromptList';
    const response = await fetchFromServer(url);

    tableData[1] = response.queries;
    selectedTableData[1] = null;

    tableBodyQueries.innerHTML = getHtmlTableBodyAiPrompts();

  } finally {
    spinner.style.display = 'none';
  }
}
async function showAddAiModelForm() {
  const spinner = document.getElementById('spinnerLoadAddModels');
  const tableBodyModels = document.getElementById('addAiModelsBody');

  tableBodyModels.innerHTML = '';
  document.getElementById('addAiModelInfo').style.display = 'none';
  document.getElementById('formAddAiModel').style.display = 'flex';
  document.getElementById('filterAiModels').value = '';

  try {
    spinner.style.display = 'flex';

    const url = '/research/ajaxResearch.php?function=aiGetAvailableModels';
    const response = await fetchFromServer(url);

    tableData = [response.models];
    selectedTableData = [];

    tableBodyModels.innerHTML = getHtmlTableBodyAiModels();

  } finally {
    spinner.style.display = 'none';
  }
}

async function updateAiModelsInfo() {
  try {
    spinnerLoad.style.display = 'block';

    const url = '/research/ajaxResearch.php?function=updateModelsDatabase';
    const response = await fetchFromServer(url);

    if (response.error) {
      showError(response.error);
      return;
    } else if (response.ok) {
      showMessage('Info for all available models successfully updated')
    }

    // Refresh GUI
    initAITest();

  } finally {
    spinnerLoad.style.display = 'none';
  }
}

function getHtmlTableBodyAiModels() {
  const filter = document.getElementById('filterAiModels').value.toLowerCase();
  
  let htmlModels = '';
  for (const model of tableData[0]) {
    if (! filter || model.name.toLowerCase().includes(filter)) {
      const modelExisting = aiModels.find(m => m.id === model.id);
      const selected = modelExisting? 'Yes' : '';
      const structured_outputs = model.structured_outputs? 'Yes' : '';

      const created = new Date(model.created);
      const cost = '$' + (model.cost_input * 1e6).toFixed(2) + ' → $' + (model.cost_output * 1e6).toFixed(2);
      htmlModels += `<tr id="tr0_${model.id}"><td>${model.name}</td><td>${selected}</td><td>${cost}</td><td>${structured_outputs}</td><td>${created.toLocaleDateString()}</td></tr>`;
    }
  }

  return htmlModels;
}

function getHtmlTableBodyAiPrompts() {
  let htmlQueries = '';
  for (const prompt of tableData[1]) {
    htmlQueries += `<tr id="tr1_${prompt.id}"><td>${prompt.id}</td><td>${prompt.model_id}</td><td>${prompt.user}</td><td>${prompt.function}</td>` +
      `<td>${truncateText(prompt.user_prompt, 30)}</td><td>${truncateText(prompt.system_prompt, 30)}</td>` +
      `<td>${truncateText(prompt.response_format, 30)}</td><td>${prompt.article_id??''}</td></tr>`;
  }

  return htmlQueries;
}

function doFilterAiModels() {
  document.getElementById('addAiModelsBody').innerHTML = getHtmlTableBodyAiModels();
}

function clickAiModelRow() {
  tableDataClick(event);

  const model = selectedTableData[0];

  const div = document.getElementById('addAiModelInfo');
  div.innerHTML = `<b>${model.name}</b><br>${model.description}`;
  div.style.display = 'block';
}

function clickaiPromptRow() {
  tableDataClick(event, 1);
}

async function addAiModel() {
  const model = selectedTableData[0];

  if (! model) {
    showError('No AI model selected');
    return;
  }

  const modelExisting = aiModels.find(m => m.id === model.id);
  if (modelExisting) {
    showError('AI model is already added');
    return;
  }

  const serverData = {
    model_id: model.id,
  };

  const url = '/research/ajaxResearch.php?function=selectAiModel';
  const response = await fetchFromServer(url, serverData);

  if (response.error) showError(response.error);
  else if (response.ok) {
    location.reload();
  }
}

async function loadAiPrompt() {
  aiPrompt = selectedTableData[1];

  if (! aiPrompt) {
    showError('No AI prompt selected');
    return;
  }

  const model = aiModels.find(m => m.id === aiPrompt.model_id);
  if (! model) {
    showError(`AI model "${aiPrompt.model_id}" is not available. Add this model or select another one.`);
  }

  document.getElementById('aiPromptId').value = aiPrompt.id;
  document.getElementById('aiModel').value = aiPrompt.model_id;
  document.getElementById('aiSystemPrompt').value = aiPrompt.system_prompt;
  document.getElementById('aiPrompt').value = aiPrompt.user_prompt;
  document.getElementById('aiResponseFormat').value = aiPrompt.response_format;
  document.getElementById('aiFunction').value = aiPrompt.function;

  document.getElementById('aiArticleId').value = aiPrompt.article_id;
  document.getElementById('aiArticle').innerText = '';

  document.getElementById('promptInfo').innerText = `id: ${aiPrompt.id} | user: ${aiPrompt.user}`;
  if (aiPrompt.function) document.getElementById('promptInfo').innerText += ` | function: ${aiPrompt.function}`;

  document.getElementById('formLoadAiModel').style.display = 'none';

  loadArticle(aiPrompt.article_id);

  showStructuredOutputSection(model && model.structured_outputs);
}

function showStructuredOutputSection(show) {
  document.getElementById('section_structured_outputs').style.display = show? 'block' : 'none';

}

async function loadArticle(id) {
  const divArticle = document.getElementById('aiArticle');
  const divArticleId = document.getElementById('aiArticleId');
  const divCrashId = document.getElementById('aiCrashId');
  divArticle.innerText = '';
  divCrashId.value = null;
  divArticleId.value = null;

  if (id) {
    const url = '/research/ajaxResearch.php?function=loadArticle';
    const response = await fetchFromServer(url, {id: id});

    if (response.error) {
      showError(response.error);
      return;
    }

    divArticleId.value = response.article.id;
    divCrashId.value = response.article.crashid;
    divArticle.innerText =
      'Date:\n ' + response.article.date + '\n\n' +
      'Title:\n ' + response.article.title + '\n\n' +
      'Text:\n' + response.article.text;
  }
}

async function deleteAiPrompt() {
  const prompt = selectedTableData[1];

  if (! prompt) {
    showError('No AI prompt selected');
    return;
  }

  confirmWarning(`Delete prompt?<br>Id: ${prompt.id}<br>Prompt: ${truncateText(prompt.user_prompt, 100)}`,
    async () => {
      let response
      const spinner = document.getElementById('spinnerLoadQueries');
      try {
        spinner.style.display = 'flex';

        const url = '/research/ajaxResearch.php?function=aiDeletePrompt';
        response = await fetchFromServer(url, {id: prompt.id});

      } finally {
        spinner.style.display = 'none';
      }

      if (response.error) {
        showError(response.error);
        return;
      }

      showLoadAiPromptForm();
    }, 'Delete prompt'
    )
}

function removeAiModel() {
  const model = selectedAiModel();
  if (! model) {
    showError('No AI model selected');
    return;
  }

  confirmWarning(`Are you sure you want to remove this AI model?<br>${model.name}?`,
    async () => {

      const serverData = {
        model_id: model.id,
      };

      const url = '/research/ajaxResearch.php?function=removeAiModel';
      const response = await fetchFromServer(url, serverData);

      if (response.error) showError(response.error);
      else if (response.ok) {
        initAITest();
      }

    },
    `Remove model`);
}

async function initAITest() {
  try {
    spinnerLoad.style.display = 'block';

    const url = '/research/ajaxResearch.php?function=aiInit';
    const response = await fetchFromServer(url);

    if (response.error) {
      showError(response.error);
      return;
    }

    aiModels = response.models;

    let htmlSelectModels;
    aiModels.forEach(model => {
      model.created = new Date(model.created);

      const cost_input = (model.cost_input * 1e6).toFixed(2);
      const cost_output = (model.cost_output * 1e6).toFixed(2);
      const structured_outputs = model.structured_outputs? ' | SO' : '';
      htmlSelectModels += `<option value="${model.id}">${model.name} | ${model.created.toLocaleDateString()} | $${cost_input}/$${cost_output} ${structured_outputs}</option>`;
    });

    updateCreditsLeft(response.credits);
    document.getElementById('aiModel').innerHTML = htmlSelectModels;
    document.getElementById('aiForm').style.display = 'block';

    aiModelChange();

    if (response.error) showError(response.error);
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
  const publicText = questionnaire.public? '✔' : '';

  return `<tr id="tr1_${questionnaire.id}">
  <td>${questionnaire.id}</td>
  <td>${questionnaire.title}</td>
  <td>${questionnaireTypeToText(questionnaire.type)}</td>
  <td>${questionnaire.country_id}</td>
  <td style="text-align: center">${activeText}</td>
  <td style="text-align: center">${publicText}</td>
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
    id: document.getElementById('questionId').value,
    text: document.getElementById('questionText').value.trim(),
    explanation: document.getElementById('questionExplanation').value.trim(),
  };

  if (! serverData.text) {showError('Text field is empty'); return;}

  const url = '/research/ajaxResearch.php?function=saveQuestion';
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

      const url      = '/research/ajaxResearch.php?function=deleteQuestion';
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
  document.getElementById('questionnairePublic').checked            = false;
  document.getElementById('tbodyQuestionnaireQuestions').innerHTML = '';

  document.getElementById('headerQuestionnaire').innerText   = 'New questionnaire';
  document.getElementById('formQuestionnaire').style.display = 'flex';
  document.getElementById('questionnaireTitle').focus();
}

function getQuestionnaireQuestionRow(question) {
return `<tr id="tr2_${question.id}" draggable="true" ondragstart="onDragRowStart(event, ${question.id})" ondrop="onDropQuestion(event, ${question.id}, 2)" ondragenter="onDragEnter(event)" ondragleave="onDragLeave(event)" ondragover="onDragOver(event)" ondragend="onDragRowQuestion(event)"><td>${question.id}</td><td>${question.text}</td></tr>`;
}

function editQuestionnaire() {
  document.getElementById('questionnaireId').value        = selectedTableData[1].id;
  document.getElementById('questionnaireTitle').value     = selectedTableData[1].title;
  document.getElementById('questionnaireType').value      = selectedTableData[1].type;
  document.getElementById('questionnaireCountryId').value = selectedTableData[1].country_id;
  document.getElementById('questionnaireActive').checked  = selectedTableData[1].active;
  document.getElementById('questionnairePublic').checked  = selectedTableData[1].public;

  document.getElementById('tbodyQuestionnaireQuestions').innerHTML = '';
  document.getElementById('headerQuestionnaire').innerText         = 'Edit questionnaire';
  document.getElementById('formQuestionnaire').style.display       = 'flex';
  document.getElementById('questionnaireTitle').focus();

  document.getElementById('questionnaireSpinner').style.display = 'block';

  const questionnaire = selectedTableData[1];
  let html = '';
  let questionnaireQuestions = [];
  for (const question_id of questionnaire.question_ids) {
    const question = tableData[0].find(q => q.id === question_id);
    questionnaireQuestions.push(question);

    html += getQuestionnaireQuestionRow(question);
  }

  tableData[2] = questionnaireQuestions;

  document.getElementById('tbodyQuestionnaireQuestions').innerHTML = html;
  document.getElementById('questionnaireNoneFound').style.display = html? 'none' : 'block';
  document.getElementById('questionnaireSpinner').style.display = 'none';

  if (! selectedTableData[2]) selectFirstTableRow(2);
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
    public:      document.getElementById('questionnairePublic').checked,
    questionIds: questionIds,
  };

  if (! serverData.title)       {showError('Title field is empty'); return;}
  if (serverData.type === null) {showError('No type selected'); return;}
  if (! serverData.countryId)   {showError('No country selected'); return;}

  const url = '/research/ajaxResearch.php?function=saveQuestionnaire';
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

          const url      = '/research/ajaxResearch.php?function=deleteQuestionnaire';
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
  const url         = '/research/ajaxResearch.php?function=saveQuestionsOrder';
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
  if (event.target.classList.contains('menuButtonBlack')) event.target.classList.toggle('menuButtonSelected');
}

function selectFilterQuestionnaireResults() {
  const dead = document.getElementById('filterResearchDead').classList.contains('menuButtonSelected');
  const child = document.getElementById('filterResearchChild').classList.contains('menuButtonSelected');
  const noUnilateral = document.getElementById('filterResearchNoUnilateral').classList.contains('menuButtonSelected');
  const timeSpan = document.getElementById('filterResearchTimeSpan').value;
  const country = document.getElementById('filterResearchCountry').value;
  const group = document.getElementById('filterResearchGroup').value;
  const minArticles = document.getElementById('filterMinArticles').value;

  const searchPersons = getPersonsFromFilter();

  const url = new URL(window.location);
  if (dead) url.searchParams.set('hd', 1); else url.searchParams.delete('hd');
  if (child) url.searchParams.set('child', 1); else url.searchParams.delete('child');
  if (! noUnilateral) url.searchParams.set('noUnilateral', 0); else url.searchParams.delete('noUnilateral');

  if (timeSpan) url.searchParams.set('timeSpan', timeSpan); else url.searchParams.delete('timeSpan');
  if (country) url.searchParams.set('country', country); else url.searchParams.delete('country');
  if (group) url.searchParams.set('group', group); else url.searchParams.delete('group');
  if (minArticles > 0) url.searchParams.set('minArticles', minArticles); else url.searchParams.delete('minArticles');
  if (searchPersons.length > 0) url.searchParams.set('persons', searchPersons.join()); else url.searchParams.delete('persons');

  window.history.pushState(null, null, url.toString());

  loadQuestionnaireResults();
}

function selectFilterQuestionnaireFillIn() {
  const dead          = document.getElementById('filterResearchDead').classList.contains('menuButtonSelected');
  const child         = document.getElementById('filterResearchChild').classList.contains('menuButtonSelected');
  const noUnilateral  = document.getElementById('filterResearchNoUnilateral').classList.contains('menuButtonSelected');

  const searchPersons = getPersonsFromFilter();

  const url = new URL(window.location);
  if (dead)                     url.searchParams.set('hd', 1); else url.searchParams.delete('hd');
  if (child)                    url.searchParams.set('child', 1); else url.searchParams.delete('child');
  if (! noUnilateral)           url.searchParams.set('noUnilateral', 0); else url.searchParams.delete('noUnilateral');
  if (searchPersons.length > 0) url.searchParams.set('persons', searchPersons.join()); else url.searchParams.delete('persons');

  window.history.pushState(null, null, url.toString());

  loadArticlesUnanswered();
}

async function showQuestionnaireArticles(articleFilter, title) {
  document.getElementById('headerQuestionnaireArticles').innerText = title;
  document.getElementById('formQuestionnaireArticles').style.display = 'flex';
  const divResult = document.getElementById('questionnaireArticles');
  divResult.innerHTML = 'Loading...';

  const response = await downloadQuestionnaireResults(articleFilter);
  articles = response.articles;
  crashes = response.crashes;

  let html = '';
  if (questionnaire.type === QuestionnaireType.bechdel) {

    for (const article of response.articles) {
      const result = bechdelAnswerToText(article.bechdelResult.result);
      const publishedtime  = new Date(article.publishedtime);

      html += `
      <tr id="article${article.id}" onclick="showQuestionsForm(${article.crashid}, ${article.id})">
        <td>${article.crashid}</td>
        <td>${article.id}</td>
        <td>${article.bechdelResult.total_questions_passed}/${article.bechdelResult.total_questions}: ${result}</td>
        <td>${publishedtime.pretty()}</td>
        <td>${article.countryid}</td>
        <td class="td400">${article.sitename}</td>
      </tr>`;
    }

    if (html) html = `<table class="dataTable tableWhiteHeader"><tr><th>Crash Id</th><th>Article Id</th><th>Bechdel result</th><th>Published</th><th>Country</th><th>Source</th></tr>${html}</table>`;

  } else {
    for (const article of response.articles) {
      const publishedtime= new Date(article.publishedtime);

      html += `
<tr id="article${article.id}" onclick="showQuestionsForm(${article.crashid}, ${article.id})">
  <td class="td400">${article.title}</td>
  <td>${answerToText(article.answer)}</td>
  <td>${publishedtime.pretty()}</td>
  <td>${article.countryid}</td>
  <td>${article.crashid}</td>
  <td>${article.id}</td>
</tr>`;
    }

    if (html) html = `
<table class="dataTable tableWhiteHeader">
<tr>
  <th>Article title</th>
  <th>Answer</th>
  <th>Published</th>
  <th>Country</th>
  <th>Crash Id</th>
  <th>Article Id</th>
</tr>
  ${html}
</table>`;
  }

  divResult.innerHTML = html;
}

async function clickQuestionnaireBars() {
  const target = event.target.closest('div[data-group]');
  if (! target) return;

  const articleFilter = {
    getArticles: true,
    offset: 0,
  }

  let title = '';
  if (questionnaire.type === QuestionnaireType.bechdel) {
    const groupData = target? target.getAttribute('data-group') : '';

    const group = document.getElementById('filterResearchGroup').value;
    title = 'Articles' + (groupData? ': ' + groupData : '');

    articleFilter.group = group;
    articleFilter.groupData = groupData;
  } else {
    articleFilter.questionId = parseInt(trTarget.getAttribute('data-question-id'));
    const question = questionnaire.questions.find(q => q.question_id === articleFilter.questionId);
    title = 'Articles: ' + question.question;
  }

  showQuestionnaireArticles(articleFilter, title);
}

function onClickQuestionnaireStatisticsTable() {
  const trTarget = event.target.closest('tr');

  const group = document.getElementById('filterResearchGroup').value;

  const articleFilter = {
    getArticles: true,
    offset: 0,
  }

  let title = '';
  if (questionnaire.type === QuestionnaireType.bechdel) {
    title = group;
    articleFilter.group = group;
    articleFilter.groupData = trTarget.getAttribute('data-group');
  } else {
    articleFilter.questionId = parseInt(trTarget.getAttribute('data-question-id'));
    const question = questionnaire.questions.find(q => q.question_id === articleFilter.questionId);
    title = 'Articles Q: ' + question.question;
  }

  showQuestionnaireArticles(articleFilter, title);
}

function insertAITag(tagName) {
  const element = document.getElementById('aiPrompt');
  const tag = `[${tagName}]`
  const text = element.value;

  const start = element.selectionStart;
  const end = element.selectionEnd;
  element.value = text.slice(0, start) + tag + text.slice(end);
  element.selectionStart = element.selectionEnd = start + tag.length;
}

async function aiRunPrompt() {

  const spinner = document.getElementById('spinnerRunPrompt');
  try {
    spinner.style.display = 'flex';

    const model = selectedAiModel();

    const divResponse = document.getElementById('aiResponse');
    const divMeta =document.getElementById('aiResponseMeta');
    const groupResponse = document.getElementById('groupAiResponse');

    divResponse.innerText = '';
    divMeta.innerText = '';
    groupResponse.style.display = 'none';

    const data = {
      model: document.getElementById('aiModel').value,
      userPrompt: document.getElementById('aiPrompt').value,
      systemPrompt: document.getElementById('aiSystemPrompt').value,
      responseFormat: model?.structured_outputs? document.getElementById('aiResponseFormat').value : '',
      articleId: document.getElementById('aiArticleId').value,
    }

    if (data.userPrompt.length < 1) {showError('User prompt is empty'); return;}

    divResponse.innerText = '...';

    const url = '/research/ajaxResearch.php?function=aiRunPrompt';
    const serverResponse = await fetchFromServer(url, data);

    if (serverResponse.error) {
      divResponse.innerText = 'ERROR: ' + serverResponse.error;
    } else {
      // Add the AI model to the response
      let AIResponse;
      try {
        const parsedResponse = JSON.parse(serverResponse.response);
        AIResponse = {model: model.name, ...parsedResponse};
      } catch (e) {
        // If the response is not valid JSON, display as plain text
        AIResponse = {model: model.name, response: serverResponse.response};
      }
      divResponse.innerText = JSON.stringify(AIResponse);

      lastGenerationId = serverResponse.id;

      // Wait for a second, otherwise the meta-info is not yet available
      divMeta.innerText = 'Checking prompt meta info...';
      setTimeout(() => {showGenerationSummary();}, 2000);
    }

    groupResponse.style.display = 'block';

  } finally {
    spinner.style.display = 'none';
  }
}

function aiNewPrompt() {
  aiPrompt = null;

  document.getElementById('aiPromptId').value = null;
  document.getElementById('aiModel').value = null;
  document.getElementById('aiPrompt').value = null;
  document.getElementById('aiSystemPrompt').value = null;
  document.getElementById('aiResponseFormat').value = null;
  document.getElementById('aiFunction').value = '';

  document.getElementById('promptInfo').innerText = ``;
}
async function aiSavePrompt() {

  async function savePrompt() {
    const spinner = document.getElementById('spinnerRunPrompt');
    try {
      spinner.style.display = 'flex';

      const model = selectedAiModel();
      if (! model) {showError('No model selected'); return;}

      const data = {
        id: document.getElementById('aiPromptId').value,
        modelId: document.getElementById('aiModel').value,
        userPrompt: document.getElementById('aiPrompt').value,
        systemPrompt: document.getElementById('aiSystemPrompt').value,
        function: document.getElementById('aiFunction').value,
        responseFormat: model.structured_outputs? document.getElementById('aiResponseFormat').value : '',
        articleId: document.getElementById('aiArticleId').value,
      }

      if (data.userPrompt.length < 1) {showError('User prompt is empty'); return;}

      const url = '/research/ajaxResearch.php?function=aiSavePrompt';
      const response = await fetchFromServer(url, data);

      if (response.error) {
        showError(response.error);
        return;
      }

      showMessage('Prompt saved');
      if (! data.id) {
        data.id = response.id;
        data.user = user.firstname + ' ' + user.lastname;
        document.getElementById('promptInfo').innerText = `id: ${data.id} | user: ${data.user}`;
        document.getElementById('aiPromptId').value = data.id;
      }

    } finally {
      spinner.style.display = 'none';
    }
  }

  if (aiPrompt) {
    if (aiPrompt.function) {
      confirmWarning(`This prompt is actively used on this website.<br><br>Current function: ${aiPrompt.function}<br><br>Are you really, really sure you want to overwrite it?`,
        savePrompt,
        `Yes overwrite`
      );
    }
  } else savePrompt();

}

function aiSaveCopyPrompt() {
  document.getElementById('aiPromptId').value = null;
  aiSavePrompt();
}

function copyServerResponse() {
  const response = document.getElementById('aiResponse').innerText;
  copyToClipboard(response);
  showMessage('Copied to clipboard', 1);
}

async function loadAiArticle(command='') {
  const articleId = document.getElementById('aiArticleId').value;

  if (! articleId && (command !== 'latest')) {
    showError('No article ID found');
    return;
  }

  const url = '/research/ajaxResearch.php?function=loadArticle';
  const response = await fetchFromServer(url, {id: articleId, command: command});

  if (response.error) {
    showError(response.error);
    return;
  }

  document.getElementById('aiArticleId').value = response.article.id;
  document.getElementById('aiCrashId').value = response.article.crashid;
  document.getElementById('aiArticle').innerText =
    'Date:\n ' + response.article.date + '\n\n' +
    'Title:\n ' + response.article.title + '\n\n' +
    'Text:\n' + response.article.text;
}

function viewAiCrash() {
  const articleId = document.getElementById('aiCrashId').value;
  if (! articleId) {
    showError('No article ID found');
    return;
  }

  viewCrashInTab(articleId);
}

function updateCreditsLeft(creditsLeft) {
  document.getElementById('aiInfo').innerHTML = `Openrouter.ai credits left: €${creditsLeft.toFixed(6)}`;
}

async function showGenerationSummary() {
  if (! lastGenerationId) {
    showError('No generation id');
    return;
  }

  const divMeta =document.getElementById('aiResponseMeta');
  divMeta.innerText = 'Checking prompt meta info...';

  const url = '/research/ajaxResearch.php?function=aiGetGenerationInfo';
  const response = await fetchFromServer(url, {id: lastGenerationId});

  if (response.error) {
    divMeta.innerHTML = 'Error: ' + response.error;
    return;
  }

  const generation = response.generation;
  divMeta.innerHTML = generation.model +
    ` | tokens ${generation.native_tokens_prompt} → ${generation.native_tokens_completion}` +
    ` | cost ${formatCost(generation.total_cost)} ${formatCost(generation.total_cost * 1000)}/k` +
    ` | tps ${generation.tps.toFixed(1)}` +
    ` | latency ${generation.latency}` +
    ` | <span class="link" onclick="showFullGenerationInfo('${generation.id}');">all info</span>`;

  updateCreditsLeft(response.credits);
}

function formatCost(cost) {
  if (cost < 0.00001) {
    return `$${cost.toFixed(8)}`; // e.g., $0.00000165
  } else if (cost < 0.0001) {
    return `$${cost.toFixed(7)}`;
  } else if (cost < 0.001) {
    return `$${cost.toFixed(6)}`;
  } else if (cost < 0.01) {
    return `$${cost.toFixed(5)}`;
  } else {
    return `$${cost.toFixed(4)}`; // Round bigger values
  }
}
async function showFullGenerationInfo(generationId) {
  const spinner = document.getElementById('spinnerLoad');
  try {
    spinner.style.display = 'block';

    const url = '/research/ajaxResearch.php?function=aiGetGenerationInfo';
    const response = await fetchFromServer(url, {id: generationId});
    
    if (response.error) {
      showError(response.error);
      return;
    }

    const generationsHtml = `
      <table class="dataTable ">
        <tr><th>Property</th><th>Value</th></tr>
        ${Object.entries(response.generation).map(([key, value]) => `
          <tr>
            <td>${key}</td>
            <td>${value}</td>
          </tr>
        `).join('')}
      </table>`;
    
    showMessage(generationsHtml, 60);

  } finally {
    spinner.style.display = 'none';
  }
}
