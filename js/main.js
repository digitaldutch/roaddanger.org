let crashes = [];
let articles = [];
let editCrashPersons = [];
let spinnerLoad;
let pageType;
let graph;
let mapMain;
let mapMarkersBins = [];
let mapEdit;
let mapCrash;
let markerEdit;
let questionnaireCountries = [];

const PageType = Object.freeze({
  stream: 0,
  crash: 1,
  moderations: 2,
  statisticsTransportationModes: 3,
  statisticsGeneral: 4,
  statisticsMediaHumanization: 12,
  statisticsCrashPartners: 5,
  recent: 6,
  deCorrespondent: 7,
  mosaic: 8,
  export: 9,
  map: 10,
  childVictims: 11,
  lastChanged: 14,
});


function pageTypeFromUrlPath(pathName, crashID) {
  if (pathName.startsWith('/moderations')) return PageType.moderations;
  if (pathName.startsWith('/last_changed')) return PageType.lastChanged;
  if (pathName.startsWith('/mosaic')) return PageType.mosaic;
  if (pathName.startsWith('/child_victims')) return PageType.childVictims;
  if (pathName.startsWith('/map')) return PageType.map;
  if (pathName.startsWith('/statistics/general')) return PageType.statisticsGeneral;
  if (pathName.startsWith('/statistics/counterparty')) return PageType.statisticsCrashPartners;
  if (pathName.startsWith('/statistics/transportation_modes')) return PageType.statisticsTransportationModes;
  if (pathName.startsWith('/statistics/media_humanization')) return PageType.statisticsMediaHumanization;
  if (pathName.startsWith('/statistics')) return PageType.statisticsGeneral;
  if (pathName.startsWith('/export')) return PageType.export;
  if (pathName.startsWith('/decorrespondent')) return PageType.deCorrespondent;
  if (crashID) return PageType.crash;
  return PageType.recent;
}

function urlPathFromPageType(pageType) {
  switch (pageType) {
    case PageType.moderations:                   return '/moderations';
    case PageType.lastChanged:                   return '/last_changed';
    case PageType.mosaic:                        return '/mosaic';
    case PageType.childVictims:                  return '/child_victims';
    case PageType.map:                           return '/map';
    case PageType.statisticsGeneral:             return '/statistics/general';
    case PageType.statisticsCrashPartners:       return '/statistics/counterparty';
    case PageType.statisticsTransportationModes: return '/statistics/transportation_modes';
    case PageType.statisticsMediaHumanization:   return '/statistics/media_humanization';
    case PageType.export:                        return '/export';
    case PageType.deCorrespondent:               return '/decorrespondent';
    default:                                     return '/';
  }
}

function initializeFilter(pageType) {
  const pageTypesWithFilter = [
    PageType.recent,
    PageType.lastChanged,
    PageType.statisticsGeneral,
    PageType.statisticsCrashPartners,
    PageType.statisticsTransportationModes,
    PageType.mosaic,
    PageType.map,
    PageType.moderations,
    PageType.deCorrespondent
  ];

  if (pageTypesWithFilter.includes(pageType)) {
    filter = new Filter(pageType);
    filter.loadFromUrl();
  }
}

function initChildVictims(url) {
  initObserver(loadChildVictims);

  const searchHealthDead = parseInt(url.searchParams.get('hd') ?? 0);
  const searchHealthInjured = parseInt(url.searchParams.get('hi') ?? 0);

  if (searchHealthDead) document.getElementById('filterChildDead').classList.add('menuButtonSelected');
  if (searchHealthInjured) document.getElementById('filterChildInjured').classList.add('menuButtonSelected');

  loadChildVictims();
}

function initCrashList(pageType, crashID, articleID) {
  if (pageType === PageType.crash) {
    // Single crash details page. Maybe this should be a popup over the recent crashes feed
    loadCrashes(crashID, articleID);
  } else if (pageIsCrashList()) {
    initObserver(loadCrashes);
    if (pageType === PageType.mosaic) document.getElementById('cards').classList.add('mosaic');
    showReadMoreLink();
    loadCrashes();

    if (pageType === PageType.recent) loadFeaturedGraph();
  }
}

function loadPageContent(pageType, url, crashID, articleID) {
  const statisticsPages = [
    PageType.statisticsTransportationModes,
    PageType.statisticsGeneral,
    PageType.statisticsCrashPartners,
    PageType.statisticsMediaHumanization
  ];

  if (statisticsPages.includes(pageType)) {
    loadStatistics();
  } else if (pageType === PageType.childVictims) {
    initChildVictims(url);
  } else if (pageType === PageType.export) {
    initExport();
  } else if (pageType === PageType.map) {
    loadMap();
  } else {
    initCrashList(pageType, crashID, articleID);
  }
}

async function initMain() {
  initPage();

  const data = await loadUserData({getQuestionnaireCountries: true});
  questionnaireCountries = data.questionnaireCountries;
  countries = data.countries;

  initFilterPersons();

  spinnerLoad = document.getElementById('spinnerLoad');

  const url = new URL(location.href);
  const crashID = getCrashNumberFromPath(url.pathname);
  const articleID = url.searchParams.get('articleid');
  const pathName = decodeURIComponent(url.pathname);

  pageType = pageTypeFromUrlPath(pathName, crashID);

  initializeFilter(pageType);

  addPersonPropertiesHtml();

  initWatchPopStart();

  loadPageContent(pageType, url, crashID, articleID);

  if (pageType === PageType.recent) showPendingBookmarkArticle();
}

// Make initMain globally accessible for DOMContentLoaded event
window.initMain = initMain;

function initWatchPopStart(){
  // We observe the browser back-button as we do not want to reload if a crash details window is closed
  window.onpopstate = function(event) {
    const crashId = (event.state && event.state.lastCrashId)? event.state.lastCrashId : null;

    if (crashId) showCrashDetails(crashId, false);
    else {
      if (pageIsCrashList()) closeCrashDetails(false);
      else window.location.reload();
    }
  };
}

function initExport(){
  let html = '';
  for (const key of Object.keys(TransportationMode)){
    const transportationMode =  TransportationMode[key];
    const text               = transportationModeText(transportationMode);
    html += `<tr><td>${transportationMode}</td><td>${text}</td></tr>`;
  }
  document.getElementById('tbodyTransportationMode').innerHTML = html;

  html = '';
  for (const key of Object.keys(Health)){
    const health = Health[key];
    const text   = healthText(health);
    html += `<tr><td>${health}</td><td>${text}</td></tr>`;
  }
  document.getElementById('tbodyHealth').innerHTML = html;
}

function getDataFromBechdelResults(stats) {
  let questions = [];
  let averages = [];

  for (const serverItem of stats.bechdelResults) {

    const groupYear = parseInt(serverItem.yearmonth.substring(0,4));
    const groupMonth = parseInt(serverItem.yearmonth.substring(4, 6));
    const itemDate = new Date(groupYear, groupMonth-1, 1);

    let pointAverage = {
      date: itemDate,
      total: serverItem.total_articles,
      average: serverItem.total_articles > 10? serverItem.average : null,
    };

    const totalQuestions = serverItem.total_questions_passed.length - 1;
    let passed = 0;
    for (const value of serverItem.total_questions_passed) {
      const dataRow = {
        date: itemDate,
        yearmonth: serverItem.yearmonth,
        category: passed.toString() + '/' + totalQuestions,
        passed: passed,
        amount: value,
      };

      questions.push(dataRow);
      passed++;
    }

    averages.push(pointAverage);
  }

  return {questions: questions, averages: averages};
}

function showMediaHumanizationGraph(stats, elementId, title='', addClickText=false, graphType='') {
  const questionCount = stats.questionnaire.questions.length;

  const data = getDataFromBechdelResults(stats);

  let domain = [];
  for (let i=0; i<=questionCount; i++) {
    if (i === 0) domain.push('Failed all questions 😞');
    else if (i === questionCount) domain.push(`Passed all questions 😊`);
    else domain.push(i + '/' + questionCount + ' questions passed');
  }


  let colorsData = d3.schemeReds[questionCount + 1].reverse();
  const passedOptions = [...Array(questionCount + 1)].map((x, i) => i);

  if (graphType === 'reversed') {
    data.questions.reverse();
    passedOptions.reverse();
    domain.reverse();
  } else if (graphType === 'greenToRed') {
    data.questions.reverse();
    passedOptions.reverse();
    domain.reverse();

    colorsData = [
      "#befda3",
      "#f8f88a",
      "#ffbe61",
      "#fb5a4b",
    ];
  }

  const colorOrdinal = d3.scaleOrdinal(passedOptions, colorsData);

  const plot = Plot.plot({
    grid: true,

    color: {
      legend: true,
      domain: domain,
      range: colorsData,
    },

    y: {
      percent: true,
      label: '↑  Articles (%)',
    },

    marks: [
      Plot.frame(),

      Plot.areaY(data.questions,
        Plot.stackY(
          {
            offset: "normalize",
            reverse: false,
          },
          {
            x: "date",
            y: "amount",
            fill: d => colorOrdinal(d.passed),
          }
        )
      ),
    ],

  });

  const element = document.getElementById(elementId);
  element.append(plot);

  let elementTitle = '';
  if (title) {
    elementTitle = `<div class="pageSubTitle">${title}</div>`;
  }

  if (addClickText) {
    const textLearnMore = translate('click_to_learn_more');
    elementTitle += `<div class="smallFont" style="text-align: center;">(${textLearnMore})</div>`;
  }

  if (elementTitle) element.insertAdjacentHTML('afterbegin', elementTitle);
}

function showMediaHumanizationAverageGraph(stats, elementId, graphType='') {

  const questionCount = stats.questionnaire.questions.length;

  const data = getDataFromBechdelResults(stats);


  const isStars = graphType === 'stars';

  // Transform data to stars scale if needed
  const plotData = isStars
    ? data.averages.map(d => ({
      ...d,
      average: d.average !== null ? (d.average / questionCount) * 5 : null
    }))
    : data.averages;

  const plot = Plot.plot({
    grid: true,

    color: {},

    y: {
      label: isStars ? '↑ Humanization score' : '↑  Questions passed successfully',
      domain: isStars ? [0, 5] : [0, questionCount],
      ticks: isStars ? [0, 1, 2, 3, 4, 5] : [...Array(questionCount + 1)].map((x, i) => i),
      tickFormat: isStars ? d => '⭐'.repeat(Math.round(d)) : d => {
        const redCount = Math.round(d);
        const grayCount = questionCount - redCount;
        // D3 Plot tickFormat doesn't support HTML, using plain text instead
        return '⚪'.repeat(redCount) + '◯'.repeat(grayCount);
      },
    },

    marginLeft: isStars? 80 : 60,

    marks: [
      Plot.frame(),

      Plot.lineY(
        plotData,
        {
          x: "date",
          y: "average",
          stroke: "#fee5d9",
          strokeWidth: 3,
          filter: d => d.average !== null,
        }),
    ],

  });

  const element = document.getElementById(elementId);
  element.append(plot);

}



function showMediaHumanizationText(questions) {
  let htmlQuestions = '';

  let i=1;
  for (const question of questions) {
    htmlQuestions += `<div>Q${i}: ${escapeHtml(question.text)} (yes/no)</div>`;
    i += 1;
  }

  document.getElementById('graphMediaHumanizationQuestions').innerHTML = htmlQuestions;
  document.getElementById('contentLoaded').style.display = 'block';
}

function showCrashVictimsGraph(crashVictims){

  function getCrashPartnerVictims(victimMode, partnerMode){
    const victim  = crashVictims.find(v => v.transportationMode === victimMode);
    if (! victim) return 0;
    const partner = victim.crashPartners.find(p => p.transportationMode === partnerMode);
    return partner? partner.victimCount : 0;
  }

  // Put data in heatmap points layout
  let victimModes  = [];
  let partnerModes = [];
  for (const key of Object.keys(TransportationMode)) {
    victimModes.push(TransportationMode[key]);
    partnerModes.push(TransportationMode[key]);
  }

  // Add unilateral
  partnerModes.push(-1);

  let points = [];
  victimModes.forEach(victimMode => {
    partnerModes.forEach(partnerMode => {
      points.push({
        victimMode:  victimMode,
        partnerMode: partnerMode,
        value:       getCrashPartnerVictims(victimMode, partnerMode),
      });
    });
  });

  const options = {
    xLabel: translate('Counterparty'),
    yLabel: '',
  };

  const filters = filter.getFromGUI();

  if (filters.healthDead || (! filters.healthInjured)) options.yLabel = translate('Dead_(adjective)');

  if (filters.healthInjured) {
    if (options.yLabel) options.yLabel += ' / ';
    options.yLabel += translate('Injured');
  }
  if (filters.child) options.yLabel += ' (' + translate('Children') + ')';

  graph = new CrashPartnerGraph('graphPartners', points, options, filters);
  document.getElementById('graphWrapper').style.display = 'block';
}

function selectFilterStats() {
  event.target.classList.toggle('menuButtonSelected');
  loadStatistics();
}

function selectFilterChildVictims() {
  event.target.classList.toggle('menuButtonSelected');
  clearTable();

  const dead = document.getElementById('filterChildDead').classList.contains('menuButtonSelected');
  const injured = document.getElementById('filterChildInjured').classList.contains('menuButtonSelected');

  const url = new URL(window.location);
  if (dead) url.searchParams.set('hd', 1); else url.searchParams.set('hd', 0);
  if (injured) url.searchParams.set('hi', 1); else url.searchParams.set('hi', 0);

  window.history.pushState(null, null, url.toString());

  loadChildVictims();
}

function searchStatistics() {
  updateBrowserUrl(true);

  loadStatistics();
}

async function loadStatistics() {

  function showStatisticsTransportation(dbStats) {
    let html = '';
    for (const stat of dbStats.total) {
      const imageClassName = transportationModeImageClassName(stat.transportationmode, true);

      html += `<tr>
<td><div class="flexRow">
<div class="iconMedium ${imageClassName}"></div><span class="hideOnMobile" style="margin-left: 5px;">${transportationModeText(stat.transportationmode)}</span></div></td>
<td style="text-align: right;">${stat.dead}</td>
<td style="text-align: right;">${stat.injured}</td>
<td style="text-align: right;">${stat.uninjured}</td>
<td style="text-align: right;">${stat.healthunknown}</td>
<td style="text-align: right;">${stat.child}</td>
<td style="text-align: right;">${stat.underinfluence}</td>
<td style="text-align: right;">${stat.hitrun}</td>
</tr>`;
    }
    document.getElementById('tableStatsBody').innerHTML = html;
    tippy('#tableStatsBody [data-tippy-content]');
  }

  function showStatisticsGeneral(dbStats) {
    let html = `     
      <tr>
        <td>${translate('Crashes')}</td>
        <td style="text-align: right;">${dbStats.crashes.toLocaleString()}</td>
      </tr>
      <tr>
        <td>${translate('Articles')}</td>
        <td style="text-align: right;">${dbStats.articles.toLocaleString()}</td>
      </tr>
      <tr>
        <td>${translate('Humans')} (${translate('dead_(adjective)')})</td>
        <td style="text-align: right;">${dbStats.dead.toLocaleString()}</td>
      </tr>
      <tr>
        <td>${translate('Humans')} (${translate('injured')})</td>
        <td style="text-align: right;">${dbStats.injured.toLocaleString()}</td>
      </tr>`;

    document.getElementById('tableStatistics').innerHTML = html;
  }

  try {
    spinnerLoad.style.display = 'block';

    const serverData = {};

    switch (pageType) {
      case PageType.statisticsTransportationModes: {serverData.type = 'transportationModes'; break;}
      case PageType.statisticsMediaHumanization: {serverData.type = 'media_humanization'; break;}
      case PageType.statisticsGeneral: {serverData.type = 'general'; break;}
      case PageType.statisticsCrashPartners: {serverData.type = 'crashPartners'; break;}
    }

    if ([PageType.statisticsGeneral, PageType.statisticsTransportationModes, PageType.statisticsCrashPartners].includes(pageType)) {
      serverData.filter = filter.getFromGUI();
    }

    const url = '/general/ajaxGeneral.php?function=getStatistics';
    const response = await fetchFromServer(url, serverData);

    if (response.user) updateLoginGUI(response.user);
    if (response.error) showError(response.error);
    else {
      const url = new URL(location.origin);

      switch (pageType) {
        case PageType.statisticsGeneral: {url.pathname = '/statistics/general'; break;}
        case PageType.statisticsCrashPartners: {url.pathname = '/statistics/counterparty'; break;}
        case PageType.statisticsTransportationModes: {url.pathname = '/statistics/transportation_modes'; break;}
        case PageType.statisticsMediaHumanization: {url.pathname = '/statistics/media_humanization'; break;}
      }

      if ([PageType.statisticsTransportationModes, PageType.statisticsCrashPartners].includes(pageType)) {
        filter.addSearchParams(url);
      }

      switch (pageType) {

        case PageType.statisticsGeneral: {
          showStatisticsGeneral(response.statistics);
          break;
        }

        case PageType.statisticsCrashPartners: {
          showCrashVictimsGraph(response.statistics.crashVictims);
          break;
        }

        case PageType.statisticsTransportationModes: {
          showStatisticsTransportation(response.statistics);
          break;
        }

        case PageType.statisticsMediaHumanization: {
          showMediaHumanizationText(response.statistics.questionnaire.questions);
          showMediaHumanizationGraph(response.statistics, 'graphMediaHumanization');
          showMediaHumanizationGraph(response.statistics, 'graphMediaHumanizationReversed', '', false, 'reversed');
          showMediaHumanizationGraph(response.statistics, 'graphMediaHumanizationGreenToRed', '', false, 'greenToRed');
          showMediaHumanizationAverageGraph(response.statistics, 'graphMediaHumanizationAverage');
          showMediaHumanizationAverageGraph(response.statistics, 'graphMediaHumanizationStars', 'stars');
          break;
        }
      }
    }
  } catch (error) {
    showError(error.message);
  } finally {
    spinnerLoad.style.display = 'none';
  }
}


function clearTable(){
  document.getElementById('dataTableBody').innerHTML = '';
  crashes  = [];
  articles = [];
}

async function loadChildVictims(){
  let newCrashes = [];
  const serverData = {
    count: 50,
    offset: crashes.length,
    sort: 'crashDate',
    filter: {
      country: user.countryid,
      child: 1,
      healthDead: document.getElementById('filterChildDead').classList.contains('menuButtonSelected')? 1 : 0,
      healthInjured: document.getElementById('filterChildInjured').classList.contains('menuButtonSelected')? 1 : 0,
    },
  }

  try {
    observerSpinner.unobserve(spinnerLoad);
    spinnerLoad.style.display = 'block';

    let iYear = crashes.length > 0? crashes[crashes.length-1].date.getFullYear() : null;

    newCrashes = await loadCrashesFromServer(serverData);
    if (newCrashes) {

      let html = '';
      for (const crash of newCrashes) {

        const crashArticles = getCrashArticles(crash.id, articles);

        const year = crash.date.getFullYear();
        if (year !== iYear) {
          iYear = year;
          html += `<tr class="trHeader"><td colspan="3">${iYear}</td></tr>`;
        }

        let htmlIconsChildren = '';
        let htmlIconsOther = '';
        crash.persons.forEach(p => {
          // Only child victims are shown
          if (p.health === Health.dead) {
            if (p.child === 1) htmlIconsChildren += '<img src="/images/persondead_white.svg" style="width: 10px;">';
          }

          if (p.health === Health.injured) {
            if (p.child === 1) htmlIconsChildren += '<img src="/images/person_injured_white.svg" style="width: 10px;">';
          }
        });

        const title = crashArticles.length > 0? crashArticles[0].title : '';
        html += `
        <tr onclick="showCrashDetails(${crash.id})">
          <td style="white-space: nowrap;">${crash.date.toLocaleDateString()}</td>
          <td style="white-space: nowrap;">${htmlIconsChildren}${htmlIconsOther}</td>
          <td class="td400">${escapeHtml(title)}</td>
        </tr>      
      `;
      }

      document.getElementById('dataTableBody').innerHTML += html;
    }

  } finally {
    if (newCrashes.length < serverData.count) spinnerLoad.style.display = 'none';
  }

  if (newCrashes.length >= serverData.count) observerSpinner.observe(spinnerLoad);
}

async function loadCrashesFromServer(serverData){
  const url = '/general/ajaxGeneral.php?function=loadCrashes';
  const response = await fetchFromServer(url, serverData);

  if (response.error) {showError(response.error); return [];}
  else {
    prepareCrashesServerData(response);

    crashes  = crashes.concat(response.crashes);
    articles = articles.concat(response.articles);

    return response.crashes;
  }
}

async function loadCrashes(crashId=null, articleId=null){

  function showCrashes(newCrashes) {
    let html = '';
    if (newCrashes.length === 0) {
      let text;
      if (pageType === PageType.moderations) text = translate('no_moderations_found');
      else text = translate('no_crashes_found');

      html = `<div style="text-align: center;">${text}</div>`;
    } else if (pageType === PageType.mosaic) html = getMosaicHTML(newCrashes);
    else {
      if (pageType === PageType.crash) {
        html = getCrashDetailsHTML(newCrashes[0].id);
      }
      else for (let crash of newCrashes) html += getCrashListHTML(crash.id);
    }

    document.getElementById('cards').innerHTML += html;
    if (pageType === PageType.crash) showMapCrash(newCrashes[0]);
    tippy('[data-tippy-content]');
  }

  if (observerSpinner) observerSpinner.unobserve(spinnerLoad);
  let newCrashes;

  let maxLoadCount = (pageType === PageType.mosaic)? 60 : 20;
  try {
    spinnerLoad.style.display = 'block';

    const serverData = {
      count: maxLoadCount,
      offset: crashes.length,
    };

    if (crashId) {
      serverData.id = crashId;
      serverData.filter = {};
      closeFilters();
    } else {
      serverData.filter = filter.getFromGUI();
    }

    if (pageType  === PageType.moderations) {
      serverData.moderations=1;
      serverData.sort = 'lastChanged';
    }
    else if ((pageType === PageType.recent) || (pageType === PageType.mosaic)) {
      serverData.sort = 'crashDate';
    }
    else if (pageType === PageType.lastChanged) {
      serverData.sort = 'lastChanged';
    }
    else if (pageType  === PageType.deCorrespondent) {
      serverData.sort = 'crashDate';
      serverData.filter.period = 'decorrespondent';
    }

    newCrashes = await loadCrashesFromServer(serverData);

  } catch (error) {
    showError(error.message);
  } finally {
    // Hide spinner if all data is loaded
    if (newCrashes.length < maxLoadCount) spinnerLoad.style.display = 'none';
  }

  if (crashId && (crashes.length === 1)) document.title = crashes[0].title + ' | ' + websiteTitle;

  showCrashes(newCrashes);
  highlightSearchText();

  if (observerSpinner && (newCrashes.length >= maxLoadCount)) observerSpinner.observe(spinnerLoad);

  if (articleId) setTimeout(()=> {selectArticle(articleId);}, 1);
}

function showReadMoreLink() {
  const intro = document.getElementById('sectionIntro');
  if (intro) {
    const readMore = document.getElementById('introReadMore');
    if (readMore) readMore.style.display = isOverflown(intro)? 'block' : 'none';
  }
}

function showFullIntro() {
  document.getElementById('sectionIntro').classList.remove('sectionCollapsed');
  document.getElementById('introReadMore').style.display = 'none';
}

async function loadFeaturedGraph() {
  const element = document.getElementById('featuredGraph');
  if (! element || ! d3) return;

  const url = '/general/ajaxGeneral.php?function=getMediaHumanizationData';
  const response = await fetchFromServer(url, []);

  const title = translate('Media_humanization_test');
  showMediaHumanizationGraph(response.statistics, 'featuredGraph', title, true);

  element.addEventListener('click',e => window.location = '/statistics/media_humanization');

  element.style.display = 'block';
}

function delayedLoadMapData(){
  if (delayedLoadMapData.timeout) clearTimeout(delayedLoadMapData.timeout);

  delayedLoadMapData.timeout = setTimeout(loadMapDataFromServer, 1000);
}

function clearMapMarkers() {
  // Remove crash markers
  for (const crash of crashes) {
    if (crash.marker) {
      crash.marker.remove();
      crash.marker = null;
    }
  }

  crashes.length = 0;
  articles.length = 0;

  // Remove bin markers
  mapMarkersBins.forEach(m => m.remove());
  mapMarkersBins.length = 0;
}

function drawCrashMapBins(bins) {

  for (const bin of bins) {
    const el = document.createElement('div');
    el.className = 'binMarker';
    el.textContent = bin.count;

    // Optional: zoom in when clicking a bin
    el.onclick = () => {
      mapMain.easeTo({
        center: [bin.longitude, bin.latitude],
        zoom: Math.min(mapMain.getZoom() + 2, 18)
      });
    };

    const marker = new mapboxgl.Marker(el)
      .setLngLat([bin.longitude, bin.latitude])
      .addTo(mapMain);

    mapMarkersBins.push(marker);
  }
}

function showCrashesOnMap(response) {

  for (const crash of response.crashes) {

    const personDied = crash.persons.some(p => p.health === Health.dead);
    const personInjured = !personDied && crash.persons.some(p => p.health === Health.injured);

    let el = document.createElement('div');
    el.className = 'crashDot ' + (personDied ? 'crashDot--dead' : personInjured ? 'crashDot--injured' : 'crashDot--normal');

    if (personDied) {
      el.innerHTML = '<img src="/images/persondead_white.svg">';
    } else if (personInjured) {
      el.innerHTML = '<img src="/images/person_injured_white.svg">';
    }

    el.onclick = () => showCrashDetails(crash.id);

    crash.marker = new mapboxgl.Marker(el)
      .setLngLat([crash.longitude, crash.latitude])
      .addTo(mapMain);

    crashes.push(crash);
    const crashArticles = response.articles.filter(a => a.crashid === crash.id);
    articles = articles.concat(crashArticles);
  }
}

async function loadMapDataFromServer(){

  try {
    document.getElementById('spinnerHeader').style.display = 'inline-flex';

    const bounds = mapMain.getBounds();

    const serverData  = {};
    serverData.filter = filter.getFromGUI();

    // We want data from the whole world to show up
    serverData.filter.country = 'UN';

    serverData.filter.map_mode = loadMapDataFromServer.map_mode?? 'points';

    serverData.filter.area = {
      latMin: bounds._sw.lat,
      lonMin: bounds._sw.lng,
      latMax: bounds._ne.lat,
      lonMax: bounds._ne.lng,
    }

    const url = '/general/ajaxGeneral.php?function=loadMapCrashes';
    const response = await fetchFromServer(url, serverData);

    if (response.user) updateLoginGUI(response.user);
    loadMapDataFromServer.map_mode = response.bins? 'bins' : 'points';

    clearMapMarkers();

    if (response.bins) {
      drawCrashMapBins(response.bins);
    }

    prepareCrashesServerData(response);
    showCrashesOnMap(response);

    if (response.error) {showError(response.error); return [];}
  } catch (error) {
    showError(error.message);
  } finally {
    document.getElementById('spinnerHeader').style.display = 'none';
  }

}

async function getCountryMapOptions(){

  const serverData = {
    countryId: user.countryid,
  }

  const urlServer = '/general/ajaxGeneral.php?function=loadCountryMapOptions';
  const response  = await fetchFromServer(urlServer, serverData);

  if (response.error) {
    showError(response.error);
  }

  return response.options;
}

async function loadMap() {
  const options = await getCountryMapOptions();

  const url = new URL(location.href);
  const longitude = url.searchParams.get('lng') || options.map.longitude;
  const latitude = url.searchParams.get('lat') || options.map.latitude;
  const zoom = url.searchParams.get('zoom') || options.map.zoom;

  if (! mapMain) {
    mapboxgl.accessToken = mapboxKey;
    mapMain = new mapboxgl.Map({
      container: 'mapMain',
      style: 'mapbox://styles/mapbox/standard',
      center: [longitude, latitude],
      zoom: zoom,
    })
    .addControl(
      new MapboxGeocoder({
        accessToken: mapboxgl.accessToken,
        mapboxgl: mapboxgl,
        collapsed: true,
        clearOnBlur: true,
      })
    )
    .on('load', loadMapDataFromServer)
    .on('moveend', () => {
       updateBrowserUrl(false);
       delayedLoadMapData();
    });
  }
}

function prepareArticleServerData(article){
  article.publishedtime = new Date(article.publishedtime);
  article.createtime = new Date(article.createtime);
  article.streamdatetime = new Date(article.streamdatetime);
}

function prepareCrashServerData(crash) {
  crash.date = new Date(crash.date);
  crash.createtime = new Date(crash.createtime);
  crash.streamdatetime = new Date(crash.streamdatetime);

  let id = 1;
  crash.persons.forEach(person => person.id = id++);
}

function prepareCrashesServerData(data){
  data.crashes.forEach(crash => prepareCrashServerData(crash));
  data.articles.forEach(article => prepareArticleServerData(article));
}

function crashHasActiveQuestionnaires(crash) {
  return questionnaireCountries.includes('UN') || questionnaireCountries.includes(crash.countryid);
}

function getCrashListHTML(crashID){
  const crash = getCrashFromId(crashID);

  return getCrashCard(crash);
}

function getMosaicHTML(newCrashes){

  function getIconsHTML(crash){
    let html = '';
    crash.persons.forEach(person => {
      if (healthBad(person.health)) html += `<div class="iconMedium ${healthImageClassName(person.health)}"></div>`;
    });
    return html;
  }

  let html = '';
  for (const crash of newCrashes) {
    const crashArticles = getCrashArticles(crash.id, articles);
    const htmlPersons = getIconsHTML(crash);

    if (crashArticles.length > 0) {
      const article = crashArticles[0];
      if (article.urlimage) {
        html +=`<div onclick="showCrashDetails(${crash.id}); event.stopPropagation();">
<div class="thumbPersons">${htmlPersons}</div>
<div class="thumbDetails">${article.publishedtime.toLocaleDateString()}</div>
<img src="${safeUrl(article.urlimage)}" onerror="this.parentElement.style.display = 'none';">
</div>`;
      }
    }
  }

  return html;
}

function getCrashCard(crash, detailsPage=false) {

  const crashArticles = getCrashArticles(crash.id, articles);
  const canEditCrash  = user.moderator || (crash.userid === user.id);

  let htmlArticles = '';
  for (const article of crashArticles) {
    htmlArticles += getArticleCard(crash, article, detailsPage);
  }

  const htmlTopIcons = getCrashTopIcons(crash);
  let crashHeader = crash.date.toLocaleDateString() + ' | ' + translate('Crash_added_by') + ' ' + escapeHtml(crash.user);
  let titleModified = '';
  if (crash.streamtopuser) {
    switch (crash.streamtoptype) {
      case StreamTopType.edited: titleModified = ' | ' + translate('edited_by') + ' ' + escapeHtml(crash.streamtopuser); break;
      case StreamTopType.articleAdded: titleModified = ' | ' + translate('new_article_added_by') + ' ' + escapeHtml(crash.streamtopuser); break;
    }
  }

  // Created date is only added if no modified title
  if (titleModified) crashHeader += titleModified;
  else crashHeader += ' ' + crash.createtime.toLocaleDateString();

  const htmlPersons = getCrashHumansIcons(crash, false);

  let htmlQuestionnaireHelp = '';
  if (! detailsPage) {
    if (user.moderator && (crashArticles.length > 0) && (! crash.unilateral) && crashHasActiveQuestionnaires(crash)) {
      htmlQuestionnaireHelp = `
<div class="notice smallFont" style="display: flex; justify-content: space-between; align-items: center; margin: 0 5px 5px 0;" onclick="showQuestionsForm(${crash.id}, ${crashArticles[0].id});">
<div>We are doing a research project and would be grateful if you answered a few questions about media articles.</div> 
  <span class="button buttonLine" style="white-space: normal; text-align: center;">Answer research questions</span>
</div>`;
    }
  }

  let crashElId = crash.id;
  if (detailsPage) crashElId = 'details' + crashElId;

  let htmlModeration = '';
  if (crash.awaitingmoderation){
    let modHTML = '';
    if (user.moderator) modHTML = `
${translate('Approval_required')}
<div style="margin: 10px;">
  <button class="button" onclick="crashModerateOK(${crash.id})">${translate('Approve')}</button>
  <button class="button buttonGray" onclick="deleteCrash(${crash.id})">${translate('Delete')}</button>
</div>
`;
    else if (crash.userid === user.id) modHTML = translate('Contribution_thanks');
    else modHTML = translate('Moderation_pending');

    htmlModeration = `<div id="crashModeration${crashElId}" class="moderation" onclick="event.stopPropagation()">${modHTML}</div>`;
  }

  let htmlMenuEditItems = '';
  if (canEditCrash) {
    htmlMenuEditItems = `
      <div onclick="editArticle(${crash.id});">${translate('Edit')}</div>
      <div onclick="showMergeCrashForm(${crash.id});">${translate('Merge')}</div>
      <div onclick="deleteCrash(${crash.id});">${translate('Delete')}</div>
`;
  }

  if (user.moderator) {
    htmlMenuEditItems += `<div onclick="crashToTopStream(${crash.id});" data-moderator>${translate('Place_at_top_of_stream')}</div>`;
  }

  let htmlMap = '';
  if (detailsPage) {
    htmlMap = `
  <div style="margin-top: 10px;">
    <div id="crashLocationDescription" class="smallFont"></div> 
    
    <div style="position: relative;">
      <div style="position: absolute; z-index: 1; top: 10px; left: 10px; user-select: none;">
        <div class="buttonMap bgZoomIn" onclick="zoomCrashMap(${crash.id}, 1);"></div>
        <div class="buttonMap bgZoomOut" onclick="zoomCrashMap(${crash.id}, -1);"></div>
      </div>
    
      <div id="mapCrash"></div>
    </div>
  </div>`;
  }

  const crashClick = detailsPage? '' : `onclick="showCrashDetails(${crash.id});"`;
  const htmlClassClickable = detailsPage? '' : 'cardCrashClickable';
  const classCrashDetails = detailsPage? 'cardCrashDetails' : '';

  return `
<div id="crash${crashElId}" class="cardCrash ${classCrashDetails} ${htmlClassClickable}" ${crashClick}>
  <span class="postButtonArea" onclick="event.stopPropagation();">
    <span style="position: relative;">
      <span class="buttonEditPost bgTripleDots"  data-userid="${crash.userid}" onclick="showCrashMenu('${crashElId}');"></span>
    </span>
    <div id="menuCrash${crashElId}" class="buttonPopupMenu" onclick="event.preventDefault();">
      <div onclick="addArticleToCrash(${crash.id});">${translate('Add_article')}</div>
      ${htmlMenuEditItems}
    </div>            
  </span>        

  ${htmlModeration}
  
  ${htmlQuestionnaireHelp}
  
  <div class="cardTop">
    <div style="width: 100%;">
      <div class="smallFont cardTitleSmall">${crashHeader}</div>
      <div>${htmlPersons}</div>
    </div>
    ${htmlTopIcons}
  </div>

  ${htmlMap}
  
  ${htmlArticles}
</div>`;
}

function getArticleCard(crash, article, detailsPage=false) {
  const canEditArticle = user.moderator || (article.userid === user.id);

  let htmlModeration = '';

  if (article.awaitingmoderation){
    let modHTML = '';
    if (user.moderator) modHTML = `
${translate('Approval_required')}
<div style="margin: 10px;">
  <button class="button" onclick="articleModerateOK(${article.id})">${translate('Approve')}</button>
  <button class="button buttonGray" onclick="deleteArticle(${article.id})">${translate('Delete')}</button>
</div>
`;
    else if (article.userid === user.id) modHTML = translate('Contribution_thanks');
    else modHTML = translate('Moderation_pending');

    htmlModeration = `<div id="articleModeration${article.id}" class="moderation">${modHTML}</div>`;
  }

  let htmlQuestionnaires = '';
  if (user.moderator && crashHasActiveQuestionnaires(crash)) {
    htmlQuestionnaires = `<div onclick="showQuestionsForm(${crash.id}, ${article.id});" data-moderator>${translate('Questionnaires')}</div>`;
  }

  let htmlButtonAllText = '';
  if (article.hasalltext) {
    let detailsElementId = article.id;
    if (detailsPage) detailsElementId = 'details' + detailsElementId;

    htmlButtonAllText = `<div style="display: flex; justify-content: center;"><button class="buttonTiny" onclick="showAllText(this, '${detailsElementId}', ${article.id});">${translate('Show_full_text')}</button></div>`;
  }

  let htmlMenuEdit      = '';
  let buttonEditArticle = '';
  if (canEditArticle) {
    const menuId = detailsPage? 'menuDetails' : 'menuArticle';
    let articleElId = article.id;
    if (detailsPage) articleElId = 'details' + articleElId;

    buttonEditArticle = `<span class="buttonEditPost bgTripleDots" data-userid="${article.userid}" onclick="showArticleMenu('${articleElId}');"></span>`;
    htmlMenuEdit += `
        <div id="menuArticle${articleElId}" class="buttonPopupMenu" onclick="event.preventDefault();">
          <div onclick="editArticle(${crash.id},  ${article.id});">${translate('Edit')}</div>
          ${htmlQuestionnaires}
          <div onclick="deleteArticle(${article.id})">${translate('Delete')}</div>
          <a href="/reframe?articleId=${article.id}" target="tools" onclick="event.stopPropagation();">${translate('Reframe')}</a>
       </div>`;
  }

  const elementArticleTextId = detailsPage? 'articleTextdetails' + article.id : 'articleText' + article.id;
  return `
<div class="cardArticle" id="article${article.id}">

  <div class="articleBody">
    <div class="articleTitle">${escapeHtml(article.title)}</div>

    <div class="smallFont articleTitleSmall">
      <a href="${safeUrl(article.url)}" target="article" rel="noopener noreferrer" onclick="event.stopPropagation();"><span class="cardSiteName">${escapeHtml(article.sitename)}</span></a> 
      | ${article.publishedtime.toLocaleDateString()} | ${translate('added_by')} ${escapeHtml(article.user)}
    </div>  
  </div>
  
  <a href="${safeUrl(article.url)}" target="article" rel="noopener noreferrer" onclick="event.stopPropagation();">
    <div class="articleImageWrapper"><img class="articleImage" src="${safeUrl(article.urlimage)}" onerror="this.style.display='none';"></div>
  </a>
  
  <div class="articleBody">
    <span class="postButtonArea" onclick="event.stopPropagation();">
      <span style="position: relative;">
        ${buttonEditArticle}
      </span>
      ${htmlMenuEdit}                  
    </span>   
    
    ${htmlModeration}     
  
    <div id="${elementArticleTextId}" class="postText">${escapeHtml(article.text)}</div>
    ${htmlButtonAllText}
  </div>
</div>`;
}

function getCrashDetailsHTML(crashId){
  const crash = getCrashFromId(crashId);

  return getCrashCard(crash, true);
}

function getIconUnilateral() {
  return `<div class="iconSmall bgUnilateral" data-tippy-content="${translate('One-sided_crash')}"></div>`;
}

function getCrashTopIcons(crash){
  let html = '';

  if (crash.unilateral)                  html += getIconUnilateral();
  if (crash.pet)                         html += `<div class="iconSmall bgPet"  data-tippy-content="${translate('Animals')}"></div>`;
  if (crash.trafficjam)                  html += `<div class="iconSmall bgTrafficJam"  data-tippy-content="${translate('Traffic_jam_disruption')}"></div>`;
  if (crash.longitude && crash.latitude) html += `<div class="iconSmall bgGeo" data-tippy-content="${translate('Location_known')}"></div>`;

  // add country flag
  const flagFile = flagIconPath(crash.countryid);
  html += `<div class="iconSmall" style="background-image: url('${flagFile}')"></div>`;

  if (html){
    html = `
<div data-info="preventFullBorder">
  <div class="cardIcons">
    <div class="flexRow" style="justify-content: flex-end">${html}</div>
  </div>
</div>`;
  }
  return html;
}

function healthBad(health){
  return [Health.dead, Health.injured].includes(health);
}

function getCrashHumansIcons(crash, showAllHealth=true, white=false) {
  let html = '';
  let iHuman = 0;

  if (crash.persons) {
    for (const person of crash.persons) {
      iHuman++;
      html += humanIconHtml(person, iHuman, showAllHealth, white);
    }
  }

  return html;
}

function highlightSearchText() {
  const search = document.getElementById('searchText').value.trim().toLowerCase().replace(/[+-]/g, '');

  if (search) {
    let options = {
      "accuracy": {
        "value": "exactly",
        "limiters": [",", "."]
      },
      "wildcards": "enabled",
    };
    let marker = new Mark(document.querySelectorAll('.cardTitle, .articleTitle, .postText'));
    marker.mark(search, options);
  }
}

function selectArticle(articleId, smooth=false) {
  const div = document.getElementById('article' + articleId);
  if (smooth) {
    div.scrollIntoView({
      block:    'center',
      behavior: 'smooth',
      inline:   'nearest'});

  } else scrollIntoViewIfNeeded(div);
}

function selectCrash(crashID, smooth=false) {
  const div = document.getElementById('crash' + crashID);
  if (smooth){
    div.scrollIntoView({
      block: 'start',
      behavior: 'smooth',
      inline: 'nearest'});

  } else scrollIntoViewIfNeeded(div);
}

function showNewCrashForm() {
  showEditCrashForm(true);

  showMapEdit();
}

// A new crash goes in two steps: article first, then the crash data, which the AI extracts from the article
let editIsNewCrash = false;
let editExtractedKey = null;
let editExtracting = false;

function articleExtractKey() {
  return JSON.stringify([
    document.getElementById('editArticleDate').value,
    document.getElementById('editArticleTitle').value.trim(),
    document.getElementById('editArticleAllText').value.trim()]);
}

function setEditTab(tab) {
  const isCrash = tab === 'crash';
  document.getElementById('panelEditArticle').style.display = isCrash? 'none' : 'flex';
  document.getElementById('panelEditCrash').style.display = isCrash? 'flex' : 'none';
  document.getElementById('tabEditArticle').classList.toggle('editTabSelected', ! isCrash);
  document.getElementById('tabEditCrash').classList.toggle('editTabSelected', isCrash);

  // The map is created while its tab is hidden and has no size then. Measure it again when it can be seen.
  if (isCrash && mapEdit) mapEdit.resize();

  // A new crash can only be saved from the crash tab, after the crash data is there.
  // Until then the only button is the one that continues to the crash data.
  const firstStep = editIsNewCrash && ! isCrash;
  // An existing crash: the AI can read the article again
  document.getElementById('buttonReadFromArticle').style.display = editIsNewCrash? 'none' : '';
  document.getElementById('buttonContinueCrashData').style.display = firstStep? '' : 'none';
  document.getElementById('buttonSaveArticleCrash').style.display = firstStep? 'none' : '';
  document.getElementById('buttonCancelEdit').style.display = firstStep? 'none' : '';
}

async function showEditTab(tab) {
  if (tab !== 'crash' || ! editIsNewCrash) {
    setEditTab(tab);
    return;
  }

  // The AI is still working on it: show the tab, do not ask again
  if (editExtracting) {
    setEditTab('crash');
    return;
  }

  // New crash: go to the crash data and let the AI fill it in
  if (! document.getElementById('editArticleDate').value) {showError(translate('Article_date_not_filled_in')); return;}
  if (! document.getElementById('editArticleTitle').value.trim()) {showError(translate('Article_title_not_filled_in')); return;}
  if (! document.getElementById('editArticleAllText').value.trim()) {showError(translate('Article_full_text_not_filled_in')); return;}

  const key = articleExtractKey();
  if (key !== editExtractedKey && editExtractedKey !== null && ! confirm(translate('Replace_crash_data_question'))) return;

  setEditTab('crash');
  if (key !== editExtractedKey) await extractDataFromArticle(key);
}

// Show crashes that may be the same crash, so a crash is not added twice. A failure is not shown: this is only a hint.
async function checkSimilarCrashes() {
  const box = document.getElementById('similarCrashes');
  box.style.display = 'none';

  const data = {
    crashId: document.getElementById('crashIDHidden').value || null,
    date: document.getElementById('editCrashDate').value || document.getElementById('editArticleDate').value,
    countryId: document.getElementById('editCrashCountry').value,
    latitude: document.getElementById('editCrashLatitude').value,
    longitude: document.getElementById('editCrashLongitude').value,
    accuracy: document.getElementById('editCrashLocationAccuracy').value,
    title: document.getElementById('editArticleTitle').value,
    modes: editCrashPersons.map(person => person.transportationmode),
  };

  try {
    const response = await fetchFromServer('/general/ajaxGeneral.php?function=findSimilarCrashes', data);
    if (response.error || ! response.crashes || response.crashes.length === 0) return;

    let html = `<div>${escapeHtml(translate('Similar_crashes_found'))}</div><ul style="margin: 3px 0 0 0; padding-left: 20px;">`;
    for (const crash of response.crashes) {
      const distance = crash.distanceKm === null? '' : `, ${crash.distanceKm} km`;
      html += `<li><a href="/${parseInt(crash.id)}" target="_blank">${escapeHtml(crash.title)}</a> (${escapeHtml(crash.date)}${distance})</li>`;
    }
    html += '</ul>';

    box.innerHTML = html;
    box.style.display = 'block';
  } catch (error) {
    // Only a hint
  }
}

// Existing crash: fill the crash fields again with the data the AI reads in the article. Nothing is saved until Save.
async function readCrashDataFromArticle() {
  if (editExtracting) return;

  if (! document.getElementById('editArticleDate').value) {showError(translate('Article_date_not_filled_in')); return;}
  if (! document.getElementById('editArticleTitle').value.trim()) {showError(translate('Article_title_not_filled_in')); return;}
  if (! document.getElementById('editArticleAllText').value.trim()) {showError(translate('Article_full_text_not_filled_in')); return;}

  if (! confirm(translate('Replace_crash_data_question'))) return;

  await extractDataFromArticle(articleExtractKey());
}

function showEditCrashForm(isNewCrash=false) {
  closeAllPopups();

  if (! user.loggedin){
     showLoginForm();
     return;
  }

  document.getElementById('editHeader').innerText = isNewCrash? translate('Add_new_crash') : translate('Edit_crash');
  document.getElementById('crashIDHidden').value = '';
  document.getElementById('articleIDHidden').value = '';
  document.getElementById('articleInfo').innerText = '';

  document.getElementById('editArticleUrl').value = '';
  document.getElementById('editArticleTitle').value = '';
  document.getElementById('editArticleAllText').value = '';
  document.getElementById('editArticleUrlImage').value = '';
  document.getElementById('editArticleSiteName').value = '';
  document.getElementById('editArticleDate').value = '';

  document.getElementById('editCrashDate').value = '';
  document.getElementById('locationDescription').innerText = '';
  document.getElementById('editCrashLatitude').value = '';
  document.getElementById('editCrashLongitude').value = '';
  document.getElementById('editCrashLocationAccuracy').value = '';
  document.getElementById('locationDescription').value = '';
  document.getElementById('editCrashCountry').value = user.countryid;

  document.getElementById('editCrashUnilateral').classList.remove('buttonSelected');
  document.getElementById('editCrashPet').classList.remove('buttonSelected');
  document.getElementById('editCrashTrafficJam').classList.remove('buttonSelected');

  editCrashPersons = [];
  setEditCrashHumans(editCrashPersons);

  document.getElementById('formEditCrash').style.display = 'flex';

  document.getElementById('ai_extract_info').style.display = 'none';
  document.getElementById('similarCrashes').style.display = 'none';

  editIsNewCrash = isNewCrash;
  editExtractedKey = null;
  editExtracting = false;
  setEditTab('article');

  document.getElementById('editArticleUrl').focus();
}

function addPersonPropertiesHtml(){
  let htmlButtons = '';
  for (const key of Object.keys(TransportationMode)){
    const transportationMode =  TransportationMode[key];
    const bgClass = transportationModeImageClassName(transportationMode);
    const text = transportationModeText(transportationMode);
    htmlButtons += `<span id="editPersonTransportationMode${key}" class="menuButton ${bgClass}" data-tippy-content="${text}" onclick="selectPersonTransportationMode(${transportationMode}, true);"></span>`;
  }
  document.getElementById('personTransportationButtons').innerHTML = htmlButtons;

  htmlButtons = '';
  for (const key of Object.keys(Health)){
    const health = Health[key];
    const bgClass = healthImageClassName(health);
    const text = healthText(health);
    htmlButtons += `<span id="editPersonHealth${key}" class="menuButton ${bgClass}" data-tippy-content="${text}" onclick="selectPersonHealth(${health}, true);"></span>`;
  }

  document.getElementById('personHealthButtons').innerHTML = htmlButtons;
}

// Fills the crash fields with the data the AI extracts from the article. The caller has checked the article fields.
async function extractDataFromArticle(key) {
  const spinner = document.getElementById('spinnerExtractData');

  const date = document.getElementById('editArticleDate').value;
  const text = document.getElementById('editArticleAllText').value;
  const title = document.getElementById('editArticleTitle').value;

  const url = '/general/ajaxGeneral.php?function=extractDataFromArticle';
  const data = {
    text: text,
    title: title,
    date: date,
  };

  // The fields are hidden while the AI works, so nothing is typed that the answer would overwrite
  const fields = document.getElementById('crashFields');
  const buttonSave = document.getElementById('buttonSaveArticleCrash');

  try {
    editExtracting = true;
    fields.style.display = 'none';
    spinner.style.display = 'flex';
    buttonSave.disabled = true;

    const response = await fetchFromServer(url, data);

    if (response.error) {
      showError(response.error, 10);
      return;
    }

    const crash = response.data;
    editCrashPersons = [];
    let human_id = 0;
    for (const transportationMode of crash.transportation_modes) {
      const tmNumber = transportationModeFromText(transportationMode.transportation_mode);

      for (const human of transportationMode.humans) {
        human_id++;
        editCrashPersons.push({
          id: human_id,
          groupid: null,
          transportationmode: tmNumber,
          health: healthFromText(human.health),
          child: human.child,
          underinfluence: human.intoxicated,
          hitrun: human.fled_scene,
        });
      }
    }

    // Set GUI with data from AI
    document.getElementById('editCrashCountry').value = crash.location.country_code;
    document.getElementById('editCrashDate').value = crash.crash_date? crash.crash_date : null;

    selectButton('editCrashUnilateral', crash.single_party_incident);
    selectButton('editCrashPet', crash.animals_mentioned);
    selectButton('editCrashTrafficJam', crash.traffic_congestion);

    setEditCrashHumans(editCrashPersons);

    document.getElementById('locationDescription').innerText = crash.location.description;

    let latitude;
    let longitude;
    // How exact the place is, in metres. Only the geocoder knows. The coordinates of the AI are not exact.
    let accuracy = '';
    if (crash.location.geocoder_coordinates) {
      latitude = crash.location.geocoder_coordinates.latitude;
      longitude = crash.location.geocoder_coordinates.longitude;
      accuracy = crash.location.geocoder_coordinates.accuracyMeters ?? '';
    } else {
      latitude = crash.location.coordinates.latitude;
      longitude = crash.location.coordinates.longitude;
    }
    document.getElementById('editCrashLatitude').value = latitude;
    document.getElementById('editCrashLongitude').value = longitude;
    document.getElementById('editCrashLocationAccuracy').value = accuracy;

    showMapEdit(latitude, longitude).then(
      () => {
        mapEdit.resize();
        mapEdit.setZoom(12);
      }
    );

    document.getElementById('ai_extract_info').style.display = 'flex';

    checkSimilarCrashes();

  } catch (error) {
    // The crash tab stays open with the fields as they are, so the data can be filled in by hand
    showError(error.message, 10);

  } finally {
    // Also after a failure: going back and forth must not ask again, or the data filled in by hand is overwritten
    editExtractedKey = key;
    editExtracting = false;

    spinner.style.display = 'none';
    fields.style.display = '';
    buttonSave.disabled = false;
    if (mapEdit) mapEdit.resize();
  }
}

function showSelectHumansForm() {
  closeAllPopups();

  selectPersonTransportationMode( null);
  selectPersonHealth(null);

  setMenuButton('editPersonChild',false);
  setMenuButton('editPersonUnderInfluence',false);
  setMenuButton('editPersonHitRun',false);

  refreshSelectHumansIcons(editCrashPersons);

  document.getElementById('formEditPerson').style.display = 'flex';
}

function selectPersonTransportationMode(transportationMode=null, toggle=false){
  selectPersonHealth(null);
  setMenuButton('editPersonChild', false);
  setMenuButton('editPersonUnderInfluence', false);
  setMenuButton('editPersonHitRun', false);

  for (const key of Object.keys(TransportationMode)) {
    const buttonTransportationMode = TransportationMode[key];
    const button = document.getElementById('editPersonTransportationMode' + key);
    if (buttonTransportationMode === transportationMode) {
      if (toggle === true) button.classList.toggle('buttonSelected');
      else button.classList.add('buttonSelected');
    }
    else button.classList.remove('buttonSelected');
  }
}

function getSelectedPersonTransportationMode(){
  for (const key of Object.keys(TransportationMode)) {
    const buttonTransportationMode = TransportationMode[key];
    const button = document.getElementById('editPersonTransportationMode' + key);
    if (button.classList.contains('buttonSelected')) return buttonTransportationMode;
  }
  return null;
}

function selectPersonHealth(health, toggle=false) {
  for (const key of Object.keys(Health)) {
    const buttonHealth = Health[key];
    const button = document.getElementById('editPersonHealth' + key);
    if (button){
      if (buttonHealth === health) {
        if (toggle === true) button.classList.toggle('buttonSelected');
        else button.classList.add('buttonSelected');
      }
      else button.classList.remove('buttonSelected');
    }
  }
}

function selectSearchPersonDead() {
  document.getElementById('searchPersonHealthDead').classList.toggle('menuButtonSelected');
}

function selectSearchPersonInjured() {
  document.getElementById('searchPersonHealthInjured').classList.toggle('menuButtonSelected');
}

function selectSearchPersonChild() {
  document.getElementById('searchPersonChild').classList.toggle('menuButtonSelected');
}


function getSelectedPersonHealth(){
  for (const key of Object.keys(Health)) {
    const buttonHealth = Health[key];
    const button = document.getElementById('editPersonHealth' + key);
    if (button && button.classList.contains('buttonSelected')) return buttonHealth;
  }
  return null;
}

function closeEditPersonForm(){
  document.getElementById('formEditPerson').style.display = 'none';
}

function addHuman() {
  const selectedTransportationMode = getSelectedPersonTransportationMode();
  const selectedHealth = getSelectedPersonHealth();
  if (selectedTransportationMode === null) {showError(translate('No_transportation_mode_selected'), 3); return;}
  if (selectedHealth === null) {showError(translate('No_injury_selected'), 3); return;}

  let person;

  function loadPersonFromGUI(person){
    person.transportationmode = selectedTransportationMode;
    person.health = selectedHealth;
    person.child = menuButtonSelected('editPersonChild');
    person.underinfluence = menuButtonSelected('editPersonUnderInfluence');
    person.hitrun = menuButtonSelected('editPersonHitRun');
  }

  // id is auto-incremented based on the highest existing ID
  const maxId = editCrashPersons.reduce((max, person) => person.id > max? person.id : max, 0);
  person = {id: maxId + 1};

  loadPersonFromGUI(person);

  editCrashPersons.push(person);

  refreshSelectHumansIcons(editCrashPersons);
  setEditCrashHumans(editCrashPersons);
}

function deleteHuman(personId) {
  editCrashPersons = editCrashPersons.filter(person => person.id !== personId);

  refreshSelectHumansIcons(editCrashPersons);
  setEditCrashHumans(editCrashPersons);
}

function setEditCrashHumans(humans) {
  let html = '';

  let iHuman = 0;
  for (const human of humans) {
    iHuman++;
    const iconHuman = humanIconHtml(human, iHuman);

    html += iconHuman;
  }

  document.getElementById('editCrashPersons').innerHTML = html;

  tippy('[data-tippy-content]');
}
function refreshSelectHumansIcons(humans) {
  let html = '';

  let iHuman = 0;
  for (const human of humans) {
    iHuman++;
    const iconHuman = humanIconHtml(human, iHuman);

    const textDelete = translate('Delete');
    html += `<div style="display: flex; flex-direction: column; align-items: center;">
  ${iconHuman}
  <div class="buttonTiny" onclick="deleteHuman(${human.id})">${textDelete}</div>
</div>`;
  }

  if (!html) html = translate('No_humans_selected');
  document.getElementById('crashPersons').innerHTML = html;
  tippy('[data-tippy-content]');
}

function setArticleCrashFields(crashID){
  const crash = getCrashFromId(crashID);
  const crashDatetime = new Date(crash.date);

  editCrashPersons = structuredClone(crash.persons);

  document.getElementById('crashIDHidden').value = crash.id;

  document.getElementById('editCrashDate').value = dateToISO(crashDatetime);
  document.getElementById('editCrashLatitude').value = crash.latitude;
  document.getElementById('editCrashLongitude').value = crash.longitude;
  document.getElementById('editCrashLocationAccuracy').value = crash.locationaccuracy ?? '';
  document.getElementById('locationDescription').innerText = crash.locationdescription;
  document.getElementById('editCrashCountry').value = crash.countryid;

  selectButton('editCrashUnilateral', crash.unilateral);
  selectButton('editCrashPet', crash.pet);
  selectButton('editCrashTrafficJam', crash.trafficjam);

  setEditCrashHumans(crash.persons);

  document.getElementById('ai_extract_info').style.display = 'none';

  showMapEdit(crash.latitude, crash.longitude);
}

function openArticleLink(event, articleID) {
  event.stopPropagation();
  const article = getArticleFromId(articleID);
  window.open(article.url,"article");
}

function showAllText(element, articleDivId, articleId){
  event.preventDefault();
  event.stopPropagation();

  element.style.display = 'none';

  const textElement = document.getElementById('articleText' + articleDivId);
  textElement.innerHTML = '⌛';

  getArticleText(articleId).then(text => textElement.innerHTML = formatText(text));
}

function editArticle(crashID, articleID=null) {
  showEditCrashForm();

  setArticleCrashFields(crashID);

  let article;
  if (articleID) {
    article = getArticleFromId(articleID);
  } else {
    // Get first article
    const crashArticles = getCrashArticles(crashID,articles);
    if (crashArticles.length > 0) {
      article = crashArticles[0];
    }
  }

  document.getElementById('articleIDHidden').value = article? article.id : '';
  document.getElementById('articleInfo').innerText = 'Id: ' + article.id;

  document.getElementById('editArticleUrl').value = article.url;
  document.getElementById('editArticleTitle').value = article.title;
  document.getElementById('editArticleAllText').readonly = true;
  document.getElementById('editArticleAllText').value = '⌛';

  document.getElementById('editArticleUrlImage').value = article.urlimage;
  document.getElementById('editArticleSiteName').value = article.sitename;
  document.getElementById('editArticleDate').value = dateToISO(article.publishedtime);

  document.getElementById('formEditCrash').style.display = 'flex';

  // Editing the crash from the crash menu starts at the crash data, editing an article at the article
  if (! articleID) setEditTab('crash');

  getArticleText(article.id).then(
    text => {
      document.getElementById('editArticleAllText').value = text;
      document.getElementById('editArticleAllText').readonly = false;
    }
  );
}

function addArticleToCrash(crashID) {
  showEditCrashForm();

  setArticleCrashFields(crashID);

  document.getElementById('editHeader').innerText = translate('Add_article');
}

function viewCrashInTab(crashId) {
  const url = createCrashURL(crashId, '');

  window.open(url, 'crash').focus();
}

async function showQuestionsForm(crashId, articleId) {
  if (event) event.stopPropagation();

  closeCrashDetails();

  const article = getArticleFromId(articleId);
  const crash = getCrashFromId(crashId);

  const htmlUnilateral = crash.unilateral? getIconUnilateral() : '';

  document.getElementById('buttonEditCrash').addEventListener('click', () => viewCrashInTab(crashId));

  document.getElementById('questionsArticleId').value = article.id;
  document.getElementById('questionsArticleTitle').innerText = article.title;
  document.getElementById('questionsArticle').innerHTML = `<a href="${safeUrl(article.url)}" target="article" rel="noopener noreferrer">${escapeHtml(article.sitename)}</a>`;
  document.getElementById('questionsCrashButtons').innerHTML = getCrashHumansIcons(crash) + htmlUnilateral;
  document.getElementById('questionsArticleText').innerText  = '⌛';

  document.getElementById('articleQuestions').innerHTML  = '⌛';
  document.getElementById('formQuestions').style.display = 'flex';

  const response = await getArticleQuestionnaires(article.id);

  let htmlQuestionnaires = '';
  article.questionnaires = response.questionnaires;
  for (const questionnaire of article.questionnaires) {
    htmlQuestionnaires += `<tr><td colspan="2" class="sectionHeader">${escapeHtml(questionnaire.title)}</td></tr>`;

    let iQuestion = 1;
    for (const question of questionnaire.questions) {

      const yesChecked = question.answer === 1? 'checked' : '';
      const noChecked = question.answer === 0? 'checked' : '';
      const ndChecked = question.answer === 2? 'checked' : '';
      const tooltip = question.explanation? `<span class="iconTooltip" data-tippy-content="${escapeHtml(question.explanation)}"></span>` : '';
      const answerJustification = question.answerJustification? escapeHtml(question.answerJustification) : '';
      const answeredWithAI = question.answered_by_type === Answered_by_type.ai? getHtmlAIIcon(question.ai_info) : '';

      htmlQuestionnaires +=
`<tr id="q${questionnaire.id}_${question.id}">
<td>${iQuestion}) ${escapeHtml(question.text)} ${tooltip}</td>
<td style="white-space: nowrap;">

<div style="display: inline-flex; justify-content: space-between; width: 100%;">
  <div>
    <label><input name="answer${question.id}" value="1" type="radio" ${yesChecked} onclick="saveAnswer(${article.id}, ${question.id})">Yes</label>
    <label><input name="answer${question.id}" value="0" type="radio" ${noChecked} onclick="saveAnswer(${article.id}, ${question.id})">No</label>
    <label data-tippy-content="Not determinable"><input name="answer${question.id}" value="2" type="radio" ${ndChecked} onclick="saveAnswer(${article.id}, ${question.id})">n.d.</label>
  </div>
  <div id="ai_${question.id}">
    ${answeredWithAI}
  </div>
</div>

</td>
</tr>
<tr id="trJustification${question.id}"><td colspan="2">
  <div class="smallFont">Justication (not required)</div>
  <input id="justification${question.id}" type="text" value="${answerJustification}" class="inputForm" oninput="saveJustificationDelayed(${article.id}, ${question.id});">
</td></tr>
`;
      iQuestion += 1;
    }

    htmlQuestionnaires += `
<tr id="questionnaireCompleted${questionnaire.id}" class="ready" style="display: none;"><td colspan="2">All questions answered 🙏🏼</td></tr>
<tr><td colspan="2" style="border: none; height: 10px;"></td></tr>`;
  }

  if (htmlQuestionnaires) htmlQuestionnaires = `<table class="dataTable noBorder noHoverStyle">${htmlQuestionnaires}</table>`;
  else htmlQuestionnaires = '<div>No questionnaires found</div>';

  document.getElementById('articleQuestions').innerHTML      = htmlQuestionnaires;
  document.getElementById('questionsArticleText').innerHTML  = response.text? formatText(response.text) : '[Full text is not available in database]';

  for (const questionnaire of article.questionnaires) setQuestionnaireGUIVisibility(questionnaire);

  tippy('[data-tippy-content]');

}

function setQuestionnaireGUIVisibility(questionnaire) {

  document.getElementById('questionnaireCompleted' + questionnaire.id).style.display = allQuestionsAnswered(questionnaire) ? 'table-row' : 'none';

  // Set visibility for Bechdel questions. After the first no they are hidden.
  if (questionnaire.type === QuestionnaireType.bechdel) {
    let iFirstNotYesQuestion = null;
    for (let i=0; i < questionnaire.questions.length; i++) {
      const question = questionnaire.questions[i];

      if ((iFirstNotYesQuestion === null) && (question.answer !== QuestionAnswer.yes)) iFirstNotYesQuestion = i;

      let questionVisible = true;
      if (iFirstNotYesQuestion !== null) questionVisible = i <= iFirstNotYesQuestion;

      const id = 'q' + questionnaire.id + '_' + question.id;
      document.getElementById(id).style.display = questionVisible? 'table-row' : 'none';

      // Show the justification field if "not determinable" answered or not empty
      document.getElementById('trJustification' + question.id).style.display = questionVisible? 'table-row' : 'none';
    }
  }

}

async function saveAnswer(articleId, questionId) {
  const name = 'answer' + questionId;
  const answer = Number(getRadioGroupValue(name));

  const article = getArticleFromId(articleId);

  for (const questionnaire of article.questionnaires) {

    // Disable AI icon for all questions
    for (const question of questionnaire.questions) {
      question.answered_by_type = Answered_by_type.human;
      showQuestionAI_Icon('ai_' + question.id, false);
    }

    const question = questionnaire.questions.find(q => q.id === questionId);

    if (question) {
      question.answer = answer;

      // Empty justification if the user selects another answer. This also removes AI justifications
      question.answerJustification = '';

      document.getElementById('justification' + questionId).value = question.answerJustification = '';

      setQuestionnaireGUIVisibility(questionnaire);

      const url = '/general/ajaxGeneral.php?function=saveAnswer';
      const data = {
        articleId: articleId,
        questionId: question.id,
        answer: question.answer,
        justification: question.answerJustification,
      };

      const response = await fetchFromServer(url, data);

      if (response.error) showError(response.error, 10);
    }
  }
}

function saveJustificationDelayed(articleId, questionId) {
  clearTimeout(saveJustification.timeout);
  saveJustification.timeout = setTimeout(function () {saveJustification(articleId, questionId);},500);
}

async function saveJustification(articleId, questionId) {

  const url  = '/general/ajaxGeneral.php?function=saveJustification';
  const data = {
    articleId: articleId,
    questionId: questionId,
    justification: document.getElementById('justification' + questionId).value.trim(),
  };

  const response = await fetchFromServer(url, data);

  if (response.error) showError(response.error, 10);
}

function nextArticleQuestion(forward=true) {
  event.preventDefault();
  event.stopPropagation();

  const articleId = parseInt(document.getElementById('questionsArticleId').value);

  const index = articles.findIndex(article => article.id === articleId);
  let newArticle;
  if (forward) {
    if (index < articles.length - 1) newArticle = articles[index + 1];
    else {
      // Check if we are on the answer page.
      const onAnswerPage = window.location.href.includes('answer');

      if (onAnswerPage) window.location.reload();
      else showMessage('This is the last article');
    }

  } else {
    if (index > 0) newArticle = articles[index -1];
    else showMessage('This is the first article');
  }

  if (newArticle) {
    selectArticle(newArticle.id);
  }

  showQuestionsForm(newArticle.crashid, newArticle.id);
}

async function aiAnswerQuestionnaires() {
  const spinner = document.getElementById('spinnerAI');

  const articleId = Number(document.getElementById('questionsArticleId').value);

  if (! articleId) {
    showError(translate('No article ID found'));
    return;
  }

  const url = '/general/ajaxGeneral.php?function=answerQuestionnairesForArticleWithAI';
  const data = {
    articleId: articleId,
  };

  try {
    spinner.style.display = 'inline-flex';

    const response = await fetchFromServer(url, data);

    if (response.error) {
      showError(response.error, 10);
      return;
    }

    const article = getArticleFromId(articleId);

    for (const responseQuestionnaire of response.questionnaires) {
      const questionnaire = article.questionnaires.find(qn => qn.id === responseQuestionnaire.id);

      for (const responseQuestion of responseQuestionnaire.questions) {
        const question = questionnaire.questions.find(q => q.id === responseQuestion.id);

        if (question) {
          question.answer = responseQuestion.answer_id;
          question.answered_by_type = responseQuestion.answered_by_type;
          question.ai_info = responseQuestion.ai_info;
          question.answerJustification = responseQuestion.justification ?? '';

          document.getElementById('justification' + question.id).value = question.answerJustification;

          const radioGroupName = 'answer' + question.id;
          setRadioGroupValue(radioGroupName, question.answer);

          const showIcon = question.answered_by_type === Answered_by_type.ai;
          showQuestionAI_Icon('ai_' + question.id, showIcon, question.ai_info);
        }
      }

      setQuestionnaireGUIVisibility(questionnaire);
    }

    tippy('[data-tippy-content]');

  } finally {
    spinner.style.display = 'none';
  }

}

async function crashToTopStream(crashID) {
  closeAllPopups();

  const url = '/general/ajaxGeneral.php?function=crashToStreamTop&id=' + crashID;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else window.location.reload();
}

async function getArticleText(articleId) {
  const url = '/general/ajaxGeneral.php?function=getArticleText&id=' + articleId;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else return response.text;
}

async function getArticleQuestionnaires(articleId) {
  const url = '/general/ajaxGeneral.php?function=getArticleQuestionnairesAndText';
  const response = await fetchFromServer(url, {articleId: articleId});

  if (response.error) showError(response.error, 10);
  else return response;
}

async function crashModerateOK(crash) {
  closeAllPopups();

  const url      = '/general/ajaxGeneral.php?function=crashModerateOK&id=' + crash;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else if (response.ok){
    // Remove moderation div
    getCrashFromId(crash).awaitingmoderation = false;

    let divModeration = document.getElementById('crashModeration' + crash);
    if (divModeration) divModeration.remove();

    divModeration = document.getElementById('crashModerationdetails' + crash);
    if (divModeration) divModeration.remove();

    loadUserData();
  }
}

async function articleModerateOK(articleID) {
  closeAllPopups();

  const url      = '/general/ajaxGeneral.php?function=articleModerateOK&id=' + articleID;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else if (response.ok){
    // Remove moderation div
    getArticleFromId(articleID).awaitingmoderation = false;

    let divModeration = document.getElementById('articleModeration' + articleID);
    if (divModeration) divModeration.remove();
    divModeration = document.getElementById('articleModerationdetails' + articleID);
    if (divModeration) divModeration.remove();

    loadUserData();
  }
}

function domainBlacklisted(url){
  const domainBlacklist = [
    {domain: 'drimble.nl', reason: 'Drimble is not a media website but a news aggregator. Find the original source on the drimble.nl page and add that instead.'},
  ];

  return domainBlacklist.find(d => url.includes(d.domain));
}

function copyCrashDateFromArticle(){
  document.getElementById('editCrashDate').value  = document.getElementById('editArticleDate').value;
}

let readabilityLoading = null;

function loadReadability() {
  if (typeof Readability !== 'undefined') return Promise.resolve();

  if (! readabilityLoading) {
    readabilityLoading = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = '/scripts/Readability.js?v=0.6.0';
      script.onload = resolve;
      script.onerror = () => {
        readabilityLoading = null;
        reject(new Error('Readability could not be loaded'));
      };
      document.head.appendChild(script);
    });
  }

  return readabilityLoading;
}

// Finds the article in the html of a page with Readability (scripts/Readability.js), for pages that have no article text
// in their tags. The page is read in a document in memory: scripts in it do not run and images are not loaded.
// Returns the text with a blank line between paragraphs, or null.
async function articleFromHtml(html) {
  try {
    await loadReadability();

    const article = new Readability(new DOMParser().parseFromString(html, 'text/html')).parse();
    if (! article) return null;

    const body = new DOMParser().parseFromString(article.content, 'text/html').body;
    const blocks = 'h1, h2, h3, h4, h5, h6, p, li, blockquote';
    const paragraphs = [];
    body.querySelectorAll(blocks).forEach(element => {
      // Take the innermost blocks only, so a text in a blockquote is not added twice
      if (element.querySelector(blocks)) return;
      const text = element.textContent.replace(/\s+/g, ' ').trim();
      if (text) paragraphs.push(text);
    });

    const text = paragraphs.length > 0? paragraphs.join('\n\n') : article.textContent.replace(/\s+/g, ' ').trim();
    if (! text) return null;

    return {text: text, title: article.title, sitename: article.siteName};

  } catch (e) {
    // The user can still select or paste the text
    return null;
  }
}

// The text the page gives is the description and the article, as for a page with tags. The server cleans it
// like every article text, so the user sees what will be saved.
async function fillArticleTextFromHtml(html, meta) {
  const article = await articleFromHtml(html);
  if (! article) return;

  const fieldText = document.getElementById('editArticleAllText');
  const fieldTitle = document.getElementById('editArticleTitle');
  const fieldSiteName = document.getElementById('editArticleSiteName');

  if (! fieldTitle.value.trim() && article.title) fieldTitle.value = article.title;
  if (! fieldSiteName.value.trim() && article.sitename) fieldSiteName.value = article.sitename;

  let text = article.text;
  if (meta.description) text = meta.description + '\n\n' + text;

  try {
    const response = await fetchFromServer('/general/ajaxGeneral.php?function=cleanArticleText',
      {text: text, url: meta.url ?? '', sitename: fieldSiteName.value});
    if (response.text) text = response.text;
  } catch (e) {
    // The text is cleaned again when the article is saved
  }

  // Do not replace what the user has typed or pasted in the meantime
  if (fieldText.value.trim()) return;

  fieldText.value = text.slice(0, 10000);
}

function fillArticleFromMeta(meta){
  document.getElementById('editArticleUrl').value = meta.url ?? '';
  document.getElementById('editArticleTitle').value = meta.title ?? '';
  document.getElementById('editArticleUrlImage').value = meta.urlimage ?? '';
  document.getElementById('editArticleSiteName').value = meta.sitename ?? '';

  // The server cleans the text: no repeated first paragraph, no tips, bylines or adverts, tidy paragraphs
  if (meta.alltext) document.getElementById('editArticleAllText').value = meta.alltext;

  if (meta.published_time){
    try {
      const datetime = new Date(meta.published_time);
      document.getElementById('editArticleDate').value = dateToISO(datetime);
    } catch (e) {
      // Silent exception. Do nothing as dates can be invalid
    }
  }
}

// The bookmark (see /bookmarklet/) sends the article from the browser of the visitor, for websites that block the server.
// The page has been read by the server and the data waits in this tab. Open the add window with it.
function showPendingBookmarkArticle() {
  let data = null;
  try {
    data = JSON.parse(sessionStorage.getItem('bookmarkArticle'));
  } catch (e) {
    return;
  }
  if (! data || ! data.media) return;

  // After logging in the page is loaded again and this runs again
  if (! user.loggedin) {
    showLoginForm();
    return;
  }

  try {
    sessionStorage.removeItem('bookmarkArticle');
  } catch (e) {
    // The data is only used once if possible
  }

  showNewCrashForm();
  fillArticleFromMeta(data.media);

  if (! data.media.alltext && data.html) fillArticleTextFromHtml(data.html, data.media);
}

async function getArticleMetaData() {
  const showMetaData = fillArticleFromMeta;

  const urlArticle = document.getElementById('editArticleUrl').value.trim();
  if (! urlArticle) {
    showError(translate('Article_link_not_filled_in'));
    return;
  }

  const domain = domainBlacklisted(urlArticle);
  if (domain) {
    showMessage(`Links from "${escapeHtml(domain.domain)}" can not be added. ${escapeHtml(domain.reason)}`, 30);
    return
  }

  const isNewArticle = document.getElementById('articleIDHidden').value === '';

  const dataServer = {
    url: urlArticle,
    newArticle: isNewArticle,
  }

  const url = '/general/ajaxGeneral.php?function=getArticleWebpageMetaData';

  document.getElementById('spinnerMeta').style.display = 'flex';
  document.getElementById('spiderResults').innerHTML = '<img src="/images/spinner_black.svg" style="height: 40px;">';
  try {
    const response = await fetchFromServer(url, dataServer);

    if (response.error) showError(response.error);
    else {
      if (response.urlExists) {
        showMessage(translate('article_has_already_been_added') + `<br><a href='/${response.urlExists.crashId}' style='text-decoration: underline;'>${translate('Article')}</a>`, 30);
      } else if (response.tagcount.total === 0) {
        // The bookmark reads the page in the browser of the visitor, which the website may let in when it blocks the server
        const linkBookmark = `<br><a href='/bookmarklet/' target='bookmarklet' style='text-decoration: underline;'>${translate('Bookmarklet_title')}</a>`;
        if (response.media.cloudflare) showMessage(translate('No_data_found_cloudflare') + linkBookmark, 30);
        else showMessage(translate('No_data_found_on_web_page') + linkBookmark, 30);
      } else {
        showMetaData(response.media);
        if (response.html) fillArticleTextFromHtml(response.html, response.media);
      }

      document.getElementById('spiderResults').innerHTML = `
<div>${translate('Tags_found')}</div>
<table class="dataTable">
<td><td>JSON-LD:</td><td> ${response.tagcount.json_ld}</td></tr>
<td><td>Open Graph Facebook tags:</td><td> ${response.tagcount.og}</td></tr>
<td><td>Twitter tags:</td><td> ${response.tagcount.twitter}</td></tr>
<td><td>article tags:</td><td> ${response.tagcount.article}</td></tr>
<td><td>itemprop tags:</td><td> ${response.tagcount.itemprop}</td></tr>
<td><td>other tags:</td><td> ${response.tagcount.other}</td></tr>
</table>`;
    }
  } catch (error) {
    showError(error.message);
  } finally {
    setTimeout(()=>{document.getElementById('spinnerMeta').style.display = 'none';}, 1500);
  }
}

async function saveArticleCrash(){
  let crashEdited;
  let articleEdited;

  articleEdited = {
    id: document.getElementById('articleIDHidden').value,
    url: document.getElementById('editArticleUrl').value,
    sitename: document.getElementById('editArticleSiteName').value.trim(),
    title: document.getElementById('editArticleTitle').value.trim(),
    urlimage: document.getElementById('editArticleUrlImage').value.trim(),
    date: document.getElementById('editArticleDate').value,
    alltext: document.getElementById('editArticleAllText').value.trim(),
  };

  if (articleEdited.id) articleEdited.id = parseInt(articleEdited.id);

  const domain = domainBlacklisted(articleEdited.url);
  if (domain) {
    showError(`Website ${domain.domain} can not be added. ${domain.reason}`);
    return
  }

  if (! articleEdited.url) {showError(translate('Article_link_not_filled_in')); return;}
  if (! articleEdited.title) {showError(translate('Article_title_not_filled_in')); return;}
  if (! articleEdited.alltext) {showError(translate('Article_full_text_not_filled_in')); return;}
  if (articleEdited.urlimage.startsWith('http://')) {showError(translate('Article_photo_link_unsafe')); return;}
  if (! articleEdited.sitename) {showError(translate('Article_media_source_not_filled_in')); return;}
  if (! articleEdited.date) {showError(translate('Article_date_not_filled_in')); return;}

  let latitude = document.getElementById('editCrashLatitude').value;
  let longitude = document.getElementById('editCrashLongitude').value;
  latitude  = latitude? parseFloat(latitude)  : null;
  longitude = longitude? parseFloat(longitude) : null;

  const locationDescription = document.getElementById('locationDescription').innerText;

  // Both latitude and longitude need to be defined or they both are set to null
  if (! latitude)  longitude = null;
  if (! longitude) latitude  = null;

  // How exact the place is, in metres. Without a place it is unknown.
  let locationAccuracy = parseInt(document.getElementById('editCrashLocationAccuracy').value);
  if (isNaN(locationAccuracy) || latitude === null) locationAccuracy = null;
  crashEdited = {
    id: document.getElementById('crashIDHidden').value,
    date: document.getElementById('editCrashDate').value,
    countryid: document.getElementById('editCrashCountry').value,
    latitude: latitude,
    longitude: longitude,
    locationdescription: locationDescription,
    locationaccuracy: locationAccuracy,
    persons: editCrashPersons,
    unilateral: document.getElementById('editCrashUnilateral').classList.contains('buttonSelected'),
    pet: document.getElementById('editCrashPet').classList.contains('buttonSelected'),
    trafficjam: document.getElementById('editCrashTrafficJam').classList.contains('buttonSelected'),
  };

  const isNewCrash = ! crashEdited.id;
  if (crashEdited.id) crashEdited.id = parseInt(crashEdited.id);

  if (isNewCrash) {
    crashEdited.title = articleEdited.title;
  }

  if (!crashEdited.date) {showError(translate('Crash_date_not_filled_in')); return;}
  if (crashEdited.persons.length === 0) {showError(translate('No_humans_selected')); return;}
  if (!crashEdited.countryid) {showError(translate('Crash_country_not_filled_in')); return;}

  const url = '/general/ajaxGeneral.php?function=saveArticleCrash';
  const serverData = {
    article: articleEdited,
    crash: crashEdited,
  }
  const response = await fetchFromServer(url, serverData);

  if (response.error) {
    showError(response.error, 10);
    return;
  }

  // No reload only if editing crash.
  if ((! isNewCrash) && pageIsCrashPage(pageType)) {
    // Save changes in crashes cache
    const crash = crashes.find(c => c.id === crashEdited.id);

    crash.title = crashEdited.title;
    crash.text = crashEdited.text;
    crash.persons = crashEdited.persons;
    crash.date = new Date(crashEdited.date);
    crash.countryid = crashEdited.countryid;
    crash.latitude = crashEdited.latitude;
    crash.longitude = crashEdited.longitude;
    crash.locationaccuracy = crashEdited.locationaccuracy;
    crash.unilateral = crashEdited.unilateral;
    crash.pet = crashEdited.pet;
    crash.trafficjam = crashEdited.trafficjam;

    const article = articles.find(a => a.id === articleEdited.id);
    if (article){
      article.url = articleEdited.url;
      article.sitename = articleEdited.sitename;
      article.title = articleEdited.title;
      if (response.article) article.text = response.article.text;
      article.urlimage = articleEdited.urlimage;
      article.date = articleEdited.date;
      article.hasalltext = articleEdited.alltext.length > 0;
    } else if (response.article) {
      prepareArticleServerData(response.article);
      articles.push(response.article);
    }

    const div = document.getElementById('crash' + crashEdited.id);
    if (div) div.outerHTML = getCrashListHTML(crashEdited.id);
    const divDetails = document.getElementById('crashdetails' + crashEdited.id);
    if (divDetails) {
      divDetails.outerHTML = getCrashDetailsHTML(crashEdited.id);
      showMapCrash(crashEdited);
    }

  } else {
    if (pageType === PageType.recent) {
      crashEdited.id = response.crashId;
      crashEdited.userid = user.id;
      crashEdited.user = user.firstname + ' ' + user.lastname;
      crashEdited.createtime = new Date();

      prepareCrashServerData(crashEdited);
      crashes.unshift(crashEdited);

      prepareArticleServerData(response.article);
      articles.push(response.article);

      const htmlCrash = getCrashListHTML(crashEdited.id);
      document.getElementById('cards').insertAdjacentHTML('afterbegin', htmlCrash);

      selectCrash(crashEdited.id);
    } else {
      window.location.href = createCrashURL(response.crashId, crashEdited.title);
    }

    showMessage(translate('Saved'), 1);
  }

  hideElement('formEditCrash');
}

function showArticleMenu(articleDivId) {
  event.preventDefault();
  event.stopPropagation();

  const div = document.getElementById(`menuArticle${articleDivId}`);
  const menuVisible = div.style.display === 'block';
  closeAllPopups();
  if (! menuVisible) div.style.display = 'block';
}

function pageIsCrashList(){
  return [PageType.recent, PageType.lastChanged, PageType.mosaic, PageType.deCorrespondent, PageType.moderations, PageType.childVictims, PageType.map].includes(pageType);
}

function pageIsCrashPage(){
  return (pageType === PageType.crash) || pageIsCrashList();
}

function showCrashMenu(crashDivID) {
  event.preventDefault();
  event.stopPropagation();

  const div = document.getElementById(`menuCrash${crashDivID}`);
  const menuVisible = div.style.display === 'block';
  closeAllPopups();
  if (! menuVisible) div.style.display = 'block';
}

function getCrashFromId(id){
  return crashes.find(crash => crash.id === id);
}

function getPersonFromID(id){
  return editCrashPersons.find(person => person.id === id);
}

function getArticleFromId(id){
  return articles.find(article => article.id === id);
}

function getCrashArticles(crashID, articles){
  let list = articles.filter(article => article.crashid === crashID);

  // Sort on publication time
  list.sort(function(a, b) {return b.publishedtime - a.publishedtime;});
  return list;
}

async function deleteArticleDirect(articleID) {
  try {
    const url = '/general/ajaxGeneral.php?function=deleteArticle&id=' + articleID;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      // Remove article from articles array
      articles = articles.filter(a => a.id !== articleID);

      // Delete the GUI elements
      deleteElement('article' + articleID);
      deleteElement('articledetails' + articleID);

      showMessage(translate('Deleted'), 1);
    }
  } catch (error) {
    showError(error.message);
  }
}

async function deleteCrashDirect(crashID) {
  try {
    const url      = '/general/ajaxGeneral.php?function=deleteCrash&id=' + crashID;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      // Remove crash from crashes array
      crashes = crashes.filter(crash => crash.id !== crashID);

      // Delete the GUI element
      deleteElement('crash' + crashID);
      deleteElement('crashdetails' + crashID);

      showMessage(translate('Deleted'), 1);
    }
  } catch (error) {
    showError(error.message);
  }
}

function reloadCrashes(){
  if (pageType === PageType.map) {
    clearMapMarkers();

    loadMapDataFromServer();
  } else {
    document.getElementById('cards').innerHTML = '';
    window.scrollTo(0, 0);

    crashes  = [];
    articles = [];

    loadCrashes();
  }
}

function deleteArticle(id) {
  closeAllPopups();
  const article = getArticleFromId(id);

  confirmWarning(translate('Delete_article') + '<br>' + escapeHtml(article.title.substr(0, 100)),
    function (){deleteArticleDirect(id)},
    translate('Delete'), null, true);
}

function deleteCrash(crashId) {
  closeAllPopups();

  confirmWarning(translate('Delete_crash') + '<br>' + crashId,
    function (){deleteCrashDirect(crashId)},
    translate('Delete'));
}

// The merge window has two crashes: the one that is removed (mergeFrom) and the one that is kept (mergeTo).
// Each has the crash and its articles, as the crashes of the search are not in the lists of the page.
let mergeFrom = null;
let mergeTo = null;
const mergeSearchItems = {};

function distanceKilometers(latitude1, longitude1, latitude2, longitude2) {
  const toRadians = degrees => degrees * Math.PI / 180;
  const a = Math.sin(toRadians(latitude2 - latitude1) / 2) ** 2 +
    Math.cos(toRadians(latitude1)) * Math.cos(toRadians(latitude2)) * Math.sin(toRadians(longitude2 - longitude1) / 2) ** 2;

  return 12742 * Math.asin(Math.sqrt(a));
}

// One crash in the merge window. A list row has the title of the first article and can be clicked.
function mergeCrashRowHTML(item, isListRow=false) {
  const crash = item.crash;
  const crashArticles = getCrashArticles(crash.id, item.articles);

  let htmlTitles = '';
  if (isListRow) {
    if (crashArticles.length > 0) {
      htmlTitles = `<div class="mergeTitle">${escapeHtml(crashArticles[0].title)}</div>`;
    }
  } else {
    htmlTitles = crashArticles.map(article =>
      `<div class="mergeTitle">${escapeHtml(article.title)}</div><div class="mergeMeta">${escapeHtml(article.sitename)}</div>`).join('');
  }

  const img = crashArticles.length > 0 && crashArticles[0].urlimage?
    `<img src="${safeUrl(crashArticles[0].urlimage)}" alt="">` : '';

  // Compared with the crash that is removed
  const isOther = mergeFrom && crash.id !== mergeFrom.crash.id;
  let dateText = crash.date.toLocaleDateString();
  if (isOther) {
    const days = Math.round((crash.date - mergeFrom.crash.date) / 86400000);
    if (days === 0) dateText += ` (${translate('Merge_same_day')})`;
    else dateText += ` (${translate(days > 0? 'Merge_days_later' : 'Merge_days_earlier').replace('{n}', Math.abs(days))})`;
  }

  const meta = [`#${crash.id}`, dateText];
  if (isListRow && crashArticles.length > 1) meta.push(`+${crashArticles.length - 1}`);
  if (crash.locationdescription) meta.push(escapeHtml(crash.locationdescription));
  if (isOther && crash.latitude && crash.longitude && mergeFrom.crash.latitude && mergeFrom.crash.longitude) {
    const distance = distanceKilometers(
      parseFloat(mergeFrom.crash.latitude), parseFloat(mergeFrom.crash.longitude),
      parseFloat(crash.latitude), parseFloat(crash.longitude));
    meta.push(`${distance < 10? distance.toFixed(1) : Math.round(distance)} km`);
  }

  return `
  <div class="mergeCard">
    <div class="mergeThumb">${img}</div>
    <div class="mergeBody">
      ${htmlTitles}
      <div class="mergeMeta">${meta.join(' · ')}</div>
      <div class="mergePersons">${getCrashHumansIcons(crash, false)}</div>
    </div>
  </div>`;
}

function showMergeCrashForm(id) {
  closeAllPopups();
  const crash = getCrashFromId(id);

  mergeFrom = {crash: crash, articles: articles.filter(article => article.crashid === crash.id)};
  mergeTo = null;

  document.getElementById('mergeCrashSearch').value           = '';
  document.getElementById('mergeSearchResults').innerHTML     = '';
  document.getElementById('mergeListTitle').innerText         = '';
  document.getElementById('mergeEmptyHint').style.display     = 'none';
  document.getElementById('formMergeCrash').style.display     = 'flex';
  document.getElementById('spinnerMerge').style.display       = 'block';

  refreshMergeSelection();
  searchMergeCrash();
}

// Shows the crash that is removed and the one that is kept
function refreshMergeSelection() {
  document.getElementById('mergeCrashFrom').innerHTML = mergeCrashRowHTML(mergeFrom);

  const elementTo = document.getElementById('mergeCrashTo');
  elementTo.innerHTML = mergeTo? mergeCrashRowHTML(mergeTo) :
    `<div class="mergeSelectHint">${escapeHtml(translate('Merge_select_crash'))}</div>`;

  document.getElementById('buttonMergeSwap').style.display = mergeTo? '' : 'none';
  document.getElementById('buttonMergeArticle').disabled = ! mergeTo;

  // Mark the selected row in the list
  document.querySelectorAll('#mergeSearchResults .searchRow').forEach(row => {
    row.classList.toggle('searchRowSelected', mergeTo !== null && parseInt(row.dataset.crashId) === mergeTo.crash.id);
  });
}

function swapMergeCrashes() {
  if (! mergeTo) return;

  [mergeFrom, mergeTo] = [mergeTo, mergeFrom];
  searchMergeCrash();
  refreshMergeSelection();
}

function searchMergeCrashDelayed() {
  document.getElementById('spinnerMerge').style.display = 'block';

  clearTimeout(searchMergeCrashDelayed.timeout);
  searchMergeCrashDelayed.timeout = setTimeout(searchMergeCrash, 500);
}

// Crashes that look like the crash that is removed, the most likely first. Each has the crash and its articles.
async function loadMergeSuggestions() {
  const crash = mergeFrom.crash;
  const data = {
    withData: true,
    crashId: crash.id,
    date: dateToISO(crash.date),
    countryId: crash.countryid,
    latitude: crash.latitude,
    longitude: crash.longitude,
    accuracy: crash.locationaccuracy,
    title: mergeFrom.articles.length > 0? mergeFrom.articles[0].title : '',
    modes: crash.persons.map(person => person.transportationmode),
  };

  const response = await fetchFromServer('/general/ajaxGeneral.php?function=findSimilarCrashes', data);
  if (response.error) throw new Error(response.error);
  if (! response.data) return [];

  prepareCrashesServerData(response.data);

  return response.crashes.map(similar => response.data.crashes.find(found => found.id === similar.id)).filter(Boolean)
    .map(found => ({crash: found, articles: response.data.articles.filter(article => article.crashid === found.id)}));
}

// A search on text, near the date of the crash that is removed. The nearest first.
async function searchMergeCrashesOnText(text) {
  const crash = mergeFrom.crash;
  const marginDays = 60;

  const serverData = {
    count: 30,
    filter: {
      text:     text,
      period:   'custom',
      dateFrom: dateToISO(crash.date.addDays(-marginDays)),
      dateTo:   dateToISO(crash.date.addDays(marginDays)),
    },
  };

  const response = await fetchFromServer('/general/ajaxGeneral.php?function=loadCrashes', serverData);
  if (response.error) throw new Error(response.error);

  prepareCrashesServerData(response);

  const distance = found => (crash.latitude && crash.longitude && found.latitude && found.longitude)?
    distanceKilometers(parseFloat(crash.latitude), parseFloat(crash.longitude), parseFloat(found.latitude), parseFloat(found.longitude)) :
    Infinity;

  return response.crashes.filter(found => found.id !== crash.id)
    .sort((a, b) => distance(a) - distance(b))
    .map(found => ({crash: found, articles: response.articles.filter(article => article.crashid === found.id)}));
}

// Without a search text the list has the crashes that look the same. With a text it has the search results.
async function searchMergeCrash() {
  const text = document.getElementById('mergeCrashSearch').value.trim().toLowerCase();
  const fromId = mergeFrom.crash.id;

  try {
    const items = text? await searchMergeCrashesOnText(text) : await loadMergeSuggestions();

    // The person has searched again or swapped while the server was working
    if (fromId !== mergeFrom.crash.id || text !== document.getElementById('mergeCrashSearch').value.trim().toLowerCase()) return;

    let html = '';
    for (const item of items) {
      mergeSearchItems[item.crash.id] = item;
      html += `<div class="searchRow" data-crash-id="${item.crash.id}" onclick="mergeSearchResultClick(${item.crash.id})">${mergeCrashRowHTML(item, true)}</div>`;
    }
    document.getElementById('mergeSearchResults').innerHTML = html;
    document.getElementById('mergeListTitle').innerText = (! text && items.length > 0)? translate('Merge_likely_same') : '';
    document.getElementById('mergeEmptyHint').style.display = items.length === 0? 'block' : 'none';
    refreshMergeSelection();
  } catch (error) {
    showError(error.message);
  } finally {
    document.getElementById('spinnerMerge').style.display = 'none';
  }
}

function mergeSearchResultClick(crashId) {
  mergeTo = mergeSearchItems[crashId] ?? null;
  refreshMergeSelection();
}

function mergeCrash() {
  if (! mergeTo) {
    showError(translate('No_merge_crash_selected'));
    return;
  }

  const fromID = mergeFrom.crash.id;
  const toID = mergeTo.crash.id;

  async function mergeCrashesOnServer(fromID, toID){
    const url= `/general/ajaxGeneral.php?function=mergeCrashes&idFrom=${fromID}&idTo=${toID}`;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      articles.forEach(article => {if (article.crashid === fromID) article.crashid = toID;});
      crashes = crashes.filter(crash => crash.id !== fromID);

      // Update GUI
      // Delete from crash
      const fromElement = document.getElementById('crash' + fromID);
      if (fromElement) fromElement.remove();

      closePopupForm();

      // Update to crash
      const toElement = document.getElementById('crash' + toID);
      if (toElement) {
        toElement.outerHTML = getCrashListHTML(toID);
        selectCrash(toID, true);
      }
    }
  }

  const titleOf = item => item.articles.length > 0? item.articles[0].title : '';
  confirmMessage(translate('Merge_crashes') +
    `<ul><li>${escapeHtml(translate('Merge_remove_crash'))}: #${fromID} ${escapeHtml(titleOf(mergeFrom))}</li>` +
    `<li>${escapeHtml(translate('Merge_keep_crash'))}: #${toID} ${escapeHtml(titleOf(mergeTo))}</li></ul>`,
    function () {
      mergeCrashesOnServer(fromID, toID);
    }, translate('Ok'));
}


function showMainSpinner(){
  document.getElementById('mainSpinner').style.display = 'inline-block';
}

function hideMainSpinner() {
  document.getElementById('mainSpinner').style.display = 'none';
}

function crashByID(id) {
  return crashes.find(a => a.id === id);
}

function crashDetailsVisible(){
  return document.getElementById('formCrash').style.display === 'flex';
}

function showCrashDetails(crashId, addToHistory=true){
  event.stopPropagation();

  const crash = crashByID(crashId);
  const crashArticles = getCrashArticles(crash.id, articles);

  // Show crash overlay
  const divCrash = document.getElementById('formCrash');
  divCrash.style.display = 'flex';
  divCrash.scrollTop = 0;

  document.getElementById('crashDetails').innerHTML = getCrashDetailsHTML(crashId);

  document.body.style.overflow = 'hidden';

  showMapCrash(crash);
  tippy('#crashDetails [data-tippy-content]');

  // Change browser url
  const title = crashArticles.length > 0? crashArticles[0].title : '';
  const url = createCrashURL(crash.id, title);
  if (addToHistory) window.history.pushState({lastCrashId: crash.id}, title, url);

  // Firefox bug workaround: Firefox selects all text in the popup which is what we do not want.
  window.getSelection().removeAllRanges();
}

function closeCrashDetails(popHistory=true) {
  if (event) event.stopPropagation();

  const formCrashDetails = document.getElementById('formCrash');

  if (formCrashDetails.style.display === 'flex') {
    document.body.style.overflow = 'auto';
    document.getElementById('crashDetails').innerHTML = '';
    formCrashDetails.style.display = 'none';
    if (popHistory) window.history.back();
  }
}

function searchCrashes() {
  updateBrowserUrl(true);

  reloadCrashes();
}

function updateBrowserUrl(pushState=false){
  const url = new URL(location.origin);

  url.pathname = urlPathFromPageType(pageType);

  if (pageType === PageType.map) {
    const center = mapMain.getCenter();

    url.searchParams.set('lat', center.lat);
    url.searchParams.set('lng', center.lng);
    url.searchParams.set('zoom', mapMain.getZoom());
  }

  filter.addSearchParams(url);

  if (pushState) window.history.pushState(null, null, url.toString());
  else window.history.replaceState(null, null, url.toString());

}

function downloadCrashesData() {
  async function doDownload(){
    spinnerLoad.style.display = 'block';
    try {
      const url = '/admin/ajaxExport.php?function=downloadCrashesData';
      const response = await fetchFromServer(url);
      if (response.error) {
        showError(response.error, 10);
        return;
      }

      const urlFile = '/cache/' + response.filename;
      download(urlFile, response.filename.replace('.json.gz', '_' + response.date + '.json.gz'));
    } catch (error) {
      showError(error.message);
    } finally {
      spinnerLoad.style.display = 'none';
    }
  }

  confirmMessage('Export all crashes and articles? It is quite a large gzip JSON file.', doDownload, 'Download');
}

function downloadResearchData() {
  async function doDownload(){
    const spinner = document.getElementById('spinnerResearch');
    spinner.style.display = 'block';
    try {
      const url = '/admin/ajaxExport.php?function=downloadResearchData';
      const response = await fetchFromServer(url);
      if (response.error) {
        showError(response.error, 10);
        return;
      }

      const urlFile = '/cache/' + response.filename;
      download(urlFile, response.filename.replace('.json.gz', '_' + response.date + '.json.gz'));
    } catch (error) {
      showError(error.message)
    } finally {
      spinner.style.display = 'none';
    }
  }

  confirmMessage('Export research data?', doDownload, 'Download');
}

async function showMapEdit(latitude, longitude) {

  function saveMarkerPosition(lngLat){
    document.getElementById('editCrashLatitude').value = lngLat.lat.toFixed(6);
    document.getElementById('editCrashLongitude').value = lngLat.lng.toFixed(6);
    // A person placed the pin: assume that it is exact
    document.getElementById('editCrashLocationAccuracy').value = 25;
  }

  function setCrashMarker(latitude, longitude){
    if (markerEdit) markerEdit.setLngLat([longitude, latitude]);
    else {
      const markerElement = document.createElement('div');
      markerElement.innerHTML = `<img class="mapMarker" src="/images/pin.svg" alt="marker">`;
      markerElement.id = 'marker';
      // A click on the marker must not reach the map, as that would place a new marker
      markerElement.addEventListener('click', (e) => e.stopPropagation());

      markerEdit = new mapboxgl.Marker(markerElement, {anchor: 'bottom', draggable: true})
        .setLngLat([longitude, latitude])
        .addTo(mapEdit)
        .on('dragend', function() {
          const lngLat = markerEdit.getLngLat();
          saveMarkerPosition(lngLat);
        })
    }
    document.getElementById('buttonRemoveLocation').style.display = '';
  }

  function deleteCrashMarker(){
    if (markerEdit){
      markerEdit.remove();
      markerEdit = null;
    }
    document.getElementById('buttonRemoveLocation').style.display = 'none';
  }

  const countryOptions = await getCountryMapOptions();
  // Zoom out to fit into the landscape map view
  countryOptions.map.zoom -= 1;

  let showMarker = true;
  let zoomDefault = 11;
  if (! latitude || ! longitude) {
    latitude = countryOptions.map.latitude;
    longitude = countryOptions.map.longitude;

    // Show full country if no coordinates are available
    zoomDefault = countryOptions.map.zoom;

    showMarker = false;
    deleteCrashMarker();
  }

  if (! mapEdit){
    mapboxgl.accessToken = mapboxKey;
    mapEdit = new mapboxgl.Map({
      container: 'mapEdit',
      style: 'mapbox://styles/mapbox/standard',
      center: [longitude, latitude],
      zoom: zoomDefault,
    }).addControl(
      new MapboxGeocoder({
        accessToken: mapboxgl.accessToken,
        mapboxgl: mapboxgl,
        collapsed: true,
        clearOnBlur: true,
      })
    ).on('click', (e) => {
      saveMarkerPosition(e.lngLat);
      setCrashMarker(e.lngLat.lat, e.lngLat.lng);
    });

  } else {
    mapEdit.setCenter([longitude, latitude]);
    mapEdit.setZoom(zoomDefault);
  }

  if (showMarker) setCrashMarker(latitude, longitude);
}

function removeCrashLocation() {
  document.getElementById('editCrashLatitude').value  = '';
  document.getElementById('editCrashLongitude').value = '';
  document.getElementById('editCrashLocationAccuracy').value = '';
  if (markerEdit) {
    markerEdit.remove();
    markerEdit = null;
  }
  document.getElementById('buttonRemoveLocation').style.display = 'none';
}

function zoomEditMap(direction) {
  event.preventDefault();
  if (!mapEdit) return;

  const currentZoom = mapEdit.getZoom();
  const zoomChange = direction === 1 ? 2 : -2;
  const newZoom = currentZoom + zoomChange;

  mapEdit.setZoom(newZoom);

  let latitude = document.getElementById('editCrashLatitude').value;
  let longitude = document.getElementById('editCrashLongitude').value;
  latitude = latitude? parseFloat(latitude)  : null;
  longitude = longitude? parseFloat(longitude) : null;

  if (longitude && latitude) {
    mapEdit.setCenter([longitude, latitude]);
  }
}

function showMapCrash(crash) {
  const elMapCrash = document.getElementById('mapCrash');
  const elLocationDescription = document.getElementById('crashLocationDescription');

  if (crash.locationdescription) {
    elLocationDescription.style.display = 'block';
    elLocationDescription.innerText = crash.locationdescription
  } else {
    elLocationDescription.style.display = 'none';
    elLocationDescription.innerText = ''
  }

  if (! crash.latitude || ! crash.longitude) {
    elMapCrash.style.display = 'none';
    return;
  }

  let zoomLevel = 12;

  mapboxgl.accessToken = mapboxKey;
  mapCrash = new mapboxgl.Map({
    container: 'mapCrash',
    style: 'mapbox://styles/mapbox/standard',
    center: [crash.longitude, crash.latitude],
    zoom: zoomLevel,
  });

  const markerElement = document.createElement('div');
  markerElement.innerHTML = `<img class="mapMarker" src="/images/pin.svg" alt="marker">`;

  new mapboxgl.Marker(markerElement, {anchor: 'bottom'})
    .setLngLat([crash.longitude, crash.latitude])
    .addTo(mapCrash);

  elMapCrash.style.display = 'block';
}

function zoomCrashMap(crashID, direction) {
  if (!mapCrash) return;

  const currentZoom = mapCrash.getZoom();
  const zoomChange = direction === 1 ? 2 : -2;
  const newZoom = currentZoom + zoomChange;

  mapCrash.setZoom(newZoom);

  const crash = getCrashFromId(crashID);
  if (crash && crash.longitude && crash.latitude) {
    mapCrash.setCenter([crash.longitude, crash.latitude]);
  }
}

function togglePageInfo(){
  const element = document.getElementById('pageInfo');

  element.style.display = (element.style.display === 'block')? 'none' : 'block';
}
