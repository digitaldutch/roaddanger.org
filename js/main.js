let crashes = [];
let crashesFound = [];
let articles = [];
let articlesFound = [];
let editCrashPersons = [];
let spinnerLoad;
let pageType;
let graph;
let mapMain;
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
  lastChanged: 13,
});


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

  if      (pathName.startsWith('/moderations')) pageType = PageType.moderations;
  else if (pathName.startsWith('/last_changed')) pageType = PageType.lastChanged;
  else if (pathName.startsWith('/mosaic')) pageType = PageType.mosaic;
  else if (pathName.startsWith('/child_victims')) pageType = PageType.childVictims;
  else if (pathName.startsWith('/map')) pageType = PageType.map;
  else if (pathName.startsWith('/statistics/general')) pageType = PageType.statisticsGeneral;
  else if (pathName.startsWith('/statistics/counterparty')) pageType = PageType.statisticsCrashPartners;
  else if (pathName.startsWith('/statistics/transportation_modes')) pageType = PageType.statisticsTransportationModes;
  else if (pathName.startsWith('/statistics/media_humanization')) pageType = PageType.statisticsMediaHumanization;
  else if (pathName.startsWith('/statistics')) pageType = PageType.statisticsGeneral;
  else if (pathName.startsWith('/export')) pageType = PageType.export;
  else if (pathName.startsWith('/decorrespondent')) pageType = PageType.deCorrespondent;
  else if (crashID) pageType = PageType.crash;
  else pageType = PageType.recent;


  if ([PageType.recent, PageType.lastChanged, PageType.statisticsCrashPartners, PageType.statisticsTransportationModes,
    PageType.mosaic, PageType.map, PageType.moderations, PageType.deCorrespondent, PageType.crash].includes(pageType))
  {
    filter = new Filter(pageType);
    filter.loadFromUrl();
  }

  addPersonPropertiesHtml();

  initWatchPopStart();

  if ([PageType.statisticsTransportationModes, PageType.statisticsGeneral, PageType.statisticsCrashPartners,
       PageType.statisticsMediaHumanization].includes(pageType)) {
    loadStatistics();
  } else if (pageType === PageType.statisticsMediaHumanization) {
    loadGraphMediaHumanzation();
  } else if (pageType === PageType.childVictims) {
    initObserver(loadChildVictims);

    const searchHealthDead = parseInt(url.searchParams.get('hd')?? 0);
    const searchHealthInjured = parseInt(url.searchParams.get('hi')?? 0);

    if (searchHealthDead) document.getElementById('filterChildDead').classList.add('menuButtonSelected');
    if (searchHealthInjured) document.getElementById('filterChildInjured').classList.add('menuButtonSelected');

    loadChildVictims();
  } else if (pageType === PageType.export){
    initExport();
  } else if (pageType === PageType.map){
    initWatchPopStart();
    loadMap();
  } else if (pageType === PageType.crash){
    // Single crash details page
    loadCrashes(crashID, articleID);
  } else if (pageIsCrashList() || (pageType === PageType.crash)) {
    initObserver(loadCrashes);
    if (pageType === PageType.mosaic) document.getElementById('cards').classList.add('mosaic');
    showReadMoreLink();
    loadCrashes();

    if (pageType === PageType.recent) loadFeaturedGraph();
  }

}

// Make initMain globally accessible for DOMContentLoaded event
window.initMain = initMain;

function initWatchPopStart(){
  // We observer the browser back button, because we do not want to reload if a crash details window is closed
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

function showMediaHumanizationGraph(stats, elementId, title='', addClickText=false) {

  const questionCount = stats.questionnaire.questions.length;
  let data = [];
  for (const serverItem of stats.bechdelResults) {

    const totalQuestions = serverItem.total_questions_passed.length - 1;
    let passed = 0;
    for (const value of serverItem.total_questions_passed) {

      const groupYear = parseInt(serverItem.yearmonth.substring(0,4));
      const groupMonth = parseInt(serverItem.yearmonth.substring(4, 6));
      const itemDate = new Date(groupYear, groupMonth-1, 1);

      const dataRow = {
        date: itemDate,
        yearmonth: serverItem.yearmonth,
        category: passed.toString() + '/' + totalQuestions,
        passed: passed,
        amount: value,
      };

      data.push(dataRow);
      passed++;
    }
  }

  let domain = [];
  for (let i=0; i<=questionCount; i++) {
    if (i === 0) domain.push('All failed 😞');
    else if (i === questionCount) domain.push(`All ${i} criteria passed 😊`);
    else domain.push(i + '/' + questionCount);
  }

  const passedOptions = [...Array(questionCount + 1)].map((x, i) => i);
  const colors = d3.schemeReds[questionCount + 1].reverse();
  const colorOrdinal = d3.scaleOrdinal(passedOptions, colors);

  const plot = Plot.plot({
    color: {
      legend: true,
      domain: domain,
      range: colors,
      type: 'categorical',
    },
    y: {
      percent: true,
      label: '↑  Articles (%)',
    },
    grid: true,
    marks: [
      Plot.frame(),
      Plot.areaY(data,
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
    ]
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

function showMediaHumanizationText(questions) {
  let htmlInfo = '';
  let i=1;
  for (const question of questions) {
    htmlInfo += `<div>${i}) ${question.text} (yes/no)</div>`;
    i += 1;
  }

  if (questions.length > 0) {
    htmlInfo = '<h2>Criteria</h2>' + htmlInfo;
  }

  document.getElementById('graphMediaHumanizationQuestions').innerHTML = htmlInfo;
  document.getElementById('graphMediaHumanizationIntro').style.display = 'block';
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
  loadStatistics()
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
      <tr class="trHeader"><td colspan="2">${translate('Today')}</td></tr>
      
      <tr>
        <td>${translate('Crashes')}</td>
        <td style="text-align: right;">${dbStats.today.crashes}</td>
      </tr>
      <tr>
        <td>${translate('Articles')}</td>
        <td style="text-align: right;">${dbStats.today.articles}</td>
      </tr>
      <tr>
        <td>${translate('Dead_(multiple)')}</td>
        <td style="text-align: right;">${dbStats.today.dead}</td>
      </tr>
      <tr>
        <td>${translate('Injured')}</td>
        <td style="text-align: right;">${dbStats.today.injured}</td>
      </tr>        
      <tr>
        <td>${translate('Added_crashes')}</td>
        <td style="text-align: right;">${dbStats.today.crashesAdded}</td>
      </tr>
      <tr>
        <td>${translate('Added_articles')}</td>
        <td style="text-align: right;">${dbStats.today.articlesAdded}</td>
      </tr>`;

    html += `
      <tr class="trHeader"><td colspan="2">30 ${translate('days')}</td></tr>

      <tr>
        <td>${translate('Crashes')}</td>
        <td style="text-align: right;">${dbStats.thirtyDays.crashes}</td>
      </tr>
      <tr>
        <td>${translate('Articles')}</td>
        <td style="text-align: right;">${dbStats.thirtyDays.articles}</td>
      </tr>
      <tr>
        <td>${translate('Dead_(multiple)')}</td>
        <td style="text-align: right;">${dbStats.thirtyDays.dead}</td>
      </tr>
      <tr>
        <td>${translate('Injured')}</td>
        <td style="text-align: right;">${dbStats.thirtyDays.injured}</td>
      </tr>        
      <tr>
        <td>${translate('Added_crashes')}</td>
        <td style="text-align: right;">${dbStats.thirtyDays.crashesAdded}</td>
      </tr>
      <tr>
        <td>${translate('Added_articles')}</td>
        <td style="text-align: right;">${dbStats.thirtyDays.articlesAdded}</td>
      </tr>    
    `;

    html += `
      <tr class="trHeader"><td colspan="2">${translate('Total')}</td></tr>

      <tr>
        <td>${translate('Crashes')}</td>
        <td style="text-align: right;">${dbStats.total.crashes.toLocaleString()}</td>
      </tr>
      <tr>
        <td>${translate('Articles')}</td>
        <td style="text-align: right;">${dbStats.total.articles.toLocaleString()}</td>
      </tr>
      <tr>
        <td>${translate('Dead_(multiple)')}</td>
        <td style="text-align: right;">${dbStats.total.dead.toLocaleString()}</td>
      </tr>
      <tr>
        <td>${translate('Injured')}</td>
        <td style="text-align: right;">${dbStats.total.injured.toLocaleString()}</td>
      </tr>                
      <tr>
        <td>${translate('Humans_helping_site')}</td>
        <td style="text-align: right;">${dbStats.total.users.toLocaleString()}</td>
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

    if ([PageType.statisticsTransportationModes, PageType.statisticsCrashPartners].includes(pageType)) {
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

      window.history.pushState(null, null, url.toString());

      switch (pageType) {
        case PageType.statisticsGeneral: {showStatisticsGeneral(response.statistics); break;}
        case PageType.statisticsCrashPartners: {showCrashVictimsGraph(response.statistics.crashVictims); break;}
        case PageType.statisticsTransportationModes: {showStatisticsTransportation(response.statistics); break;}
        case PageType.statisticsMediaHumanization: {
          showMediaHumanizationText(response.statistics.questionnaire.questions);
          showMediaHumanizationGraph(response.statistics, 'graphMediaHumanization');
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

async function loadGraphMediaHumanzation() {
  // showMessage('Boe');
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
          <td style="white-space: nowrap;">${crash.date.pretty()}</td>
          <td style="white-space: nowrap;">${htmlIconsChildren}${htmlIconsOther}</td>
          <td class="td400">${title}</td>
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
    tippy('[data-tippy-content]', {allowHTML: true});
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

    serverData.filter = filter.getFromGUI();

    if (crashId) serverData.id = crashId;

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

async function loadMapDataFromServer(){

  try {
    document.getElementById('spinnerHeader').style.display = 'inline-flex';

    const bounds = mapMain.getBounds();
    const serverData  = {
      count:   200,
      sort:    'crashDate',
    };

    serverData.filter = filter.getFromGUI();

    serverData.filter.area = {
      latMin: bounds._sw.lat,
      lonMin: bounds._sw.lng,
      latMax: bounds._ne.lat,
      lonMax: bounds._ne.lng,
    }

    const url = '/general/ajaxGeneral.php?function=loadCrashes';
    const response = await fetchFromServer(url, serverData);

    if (response.user) updateLoginGUI(response.user);

    prepareCrashesServerData(response);

    for (const crash of response.crashes) {
      if (! crashes.find(c => c.id === crash.id)) {
        const markerElement = document.createElement('div');
        const personDied    = crash.persons.find(p => p.health === Health.dead);
        let   personInjured = false;
        if (! personDied) personInjured = crash.persons.find(p => p.health === Health.injured);

        const imgSrc = personDied? 'persondead_red.svg' : personInjured? 'person_injured_red.svg' : 'crash_icon.svg';

        markerElement.innerHTML = `<img class="crashIcon" src="/images/${imgSrc}">`;
        markerElement.onclick = () => {showCrashDetails(crash.id)};

        crash.marker = (new mapboxgl.Marker(markerElement)
          .setLngLat([crash.longitude, crash.latitude])
          .addTo(mapMain));

        crashes.push(crash);
        const crashArticles = response.articles.filter(a => a.crashid === crash.id);
        articles = articles.concat(crashArticles);
      }
    }

    if (crashes.length > 500) {
      for (const crash of crashes) {
// TODO Clean up markers that are out of the view
      }
    }

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
  article.publishedtime  = new Date(article.publishedtime);
  article.createtime     = new Date(article.createtime);
  article.streamdatetime = new Date(article.streamdatetime);
}

function prepareCrashServerData(crash) {
  crash.date           = new Date(crash.date);
  crash.createtime     = new Date(crash.createtime);
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
<div class="thumbDetails">${article.publishedtime.pretty()}</div>
<img src="${article.urlimage}" onerror="this.parentElement.style.display = 'none';">
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
  let crashHeader = crash.date.pretty() + ' | ' + translate('Crash_added_by') + ' ' + crash.user;
  let titleModified = '';
  if (crash.streamtopuser) {
    switch (crash.streamtoptype) {
      case StreamTopType.edited: titleModified = ' | ' + translate('edited_by') + ' ' + crash.streamtopuser; break;
      case StreamTopType.articleAdded: titleModified = ' | ' + translate('new_article_added_by') + ' ' + crash.streamtopuser; break;
    }
  }

  // Created date is only added if no modified title
  if (titleModified) crashHeader += titleModified;
  else crashHeader += ' ' + crash.createtime.pretty();

  const htmlPersons = getCrashHumansIcons(crash, false);

  let htmlQuestionnaireHelp = '';
  if (! detailsPage) {
    if (user.moderator && (crashArticles.length > 0) && (! crash.unilateral) && crashHasActiveQuestionnaires(crash)) {
      htmlQuestionnaireHelp = `
<div class="notice smallFont" style="display: flex; justify-content: space-between; align-items: center; margin: 0 5px 5px 0;" onclick="showQuestionsForm(${crash.id}, ${crashArticles[0].id});">
<div>We are doing a research project and would be grateful if you answered a few questions about media articles.</div> 
  <span class="button buttonLine">Answer research questions</span>
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

  return `
<div id="crash${crashElId}" class="cardCrash ${htmlClassClickable}" ${crashClick}>
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
      <a href="${article.url}" target="article" onclick="event.stopPropagation();"><span class="cardSiteName">${escapeHtml(article.sitename)}</span></a> 
      | ${article.publishedtime.pretty()} | ${translate('added_by')} ${article.user}
    </div>  
  </div>
  
  <a href="${article.url}" target="article" onclick="event.stopPropagation();">
    <div class="articleImageWrapper"><img class="articleImage" src="${article.urlimage}" onerror="this.style.display='none';"></div>
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

function getCrashHumansIcons(crash, showAllHealth=true) {
  let html = '';
  let iHuman = 0;

  if (crash.persons) {
    for (const person of crash.persons) {
      iHuman++;
      html += humanIconHtml(person, iHuman, showAllHealth);
    }
  }

  return html;
}

function highlightSearchText() {
  const search = document.getElementById('searchText').value.trim().toLowerCase().replace(/[+-]/g, '');

  if (search) {
    let options = {
      "accuracy": {
        "value":    "exactly",
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
  if (smooth){
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
  document.getElementById('editArticleText').value = '';
  document.getElementById('editArticleAllText').value = '';
  document.getElementById('editArticleUrlImage').value = '';
  document.getElementById('editArticleSiteName').value = '';
  document.getElementById('editArticleDate').value = '';

  document.getElementById('editCrashDate').value = '';
  document.getElementById('locationDescription').innerText = '';
  document.getElementById('editCrashLatitude').value = '';
  document.getElementById('editCrashLongitude').value = '';
  document.getElementById('locationDescription').value = '';
  document.getElementById('editCrashCountry').value = user.countryid;

  document.getElementById('editCrashUnilateral').classList.remove('buttonSelected');
  document.getElementById('editCrashPet').classList.remove('buttonSelected');
  document.getElementById('editCrashTrafficJam').classList.remove('buttonSelected');

  editCrashPersons = [];
  setEditCrashHumans(editCrashPersons);

  document.getElementById('formEditCrash').style.display = 'flex';

  document.getElementById('editArticleUrl').focus();

  document.getElementById('ai_extract_info').style.display = 'none';
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

async function extractDataFromArticle() {
  event.preventDefault();

  const spinner = document.getElementById('spinnerExtractData');

  const date = document.getElementById('editArticleDate').value;
  const text = document.getElementById('editArticleAllText').value;
  const title = document.getElementById('editArticleTitle').value;

  if (! date) {
    showError(translate('Add_article_date'));
    return;
  }

  if (! text.trim()) {
    showError(translate('Add_full_article_text'));
    return;
  }

  if (! title.trim()) {
    showError(translate('Add_article_title'));
    return;
  }

  const url = '/general/ajaxGeneral.php?function=extractDataFromArticle';
  const data = {
    text: text,
    title: title,
    date: date,
  };

  try {
    spinner.style.display = 'inline-flex';

    const response = await fetchFromServer(url, data);

    if (response.error) showError(response.error, 10);

    const crash = response.data;
    editCrashPersons = [];
    for (const transportationMode of crash.transportation_modes) {
      const tmNumber = transportationModeFromText(transportationMode.transportation_mode);

      for (const human of transportationMode.humans) {
        editCrashPersons.push({
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
    if (crash.location.geocoder_coordinates) {
      latitude = crash.location.geocoder_coordinates.latitude;
      longitude = crash.location.geocoder_coordinates.longitude;
    } else {
      latitude = crash.location.coordinates.latitude;
      longitude = crash.location.coordinates.longitude;
    }
    document.getElementById('editCrashLatitude').value = latitude;
    document.getElementById('editCrashLongitude').value = longitude;

    showMapEdit(latitude, longitude).then(
      () => {mapEdit.setZoom(12);}
    );

    document.getElementById('ai_extract_info').style.display = 'flex';

  } finally {
    spinner.style.display = 'none';
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

  tippy('[data-tippy-content]', {allowHTML: true});
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
  tippy('[data-tippy-content]', {allowHTML: true});
}

function setArticleCrashFields(crashID){
  const crash = getCrashFromId(crashID);
  const crashDatetime = new Date(crash.date);

  editCrashPersons = structuredClone(crash.persons);

  document.getElementById('crashIDHidden').value = crash.id;

  document.getElementById('editCrashDate').value = dateToISO(crashDatetime);
  document.getElementById('editCrashLatitude').value = crash.latitude;
  document.getElementById('editCrashLongitude').value = crash.longitude;
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
  document.getElementById('editArticleText').value = article.text;
  document.getElementById('editArticleAllText').readonly = true;
  document.getElementById('editArticleAllText').value = '⌛';

  document.getElementById('editArticleUrlImage').value = article.urlimage;
  document.getElementById('editArticleSiteName').value = article.sitename;
  document.getElementById('editArticleDate').value = dateToISO(article.publishedtime);

  document.getElementById('formEditCrash').style.display = 'flex';

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
  const crash   = getCrashFromId(crashId);

  const onFillInPage = window.location.href.includes('fill_in');
  const htmlUnilateral = crash.unilateral? getIconUnilateral() : '';

  document.getElementById('buttonEditCrash').addEventListener('click', () => viewCrashInTab(crashId));

  document.getElementById('questionsArticleId').value        = article.id;
  document.getElementById('questionsArticleTitle').innerText = article.title;
  document.getElementById('questionsArticle').innerHTML      = `<a href="${article.url}" target="article">${article.sitename}</a>`;
  document.getElementById('questionsCrashButtons').innerHTML = getCrashHumansIcons(crash) + htmlUnilateral;
  document.getElementById('questionsArticleText').innerText  = '⌛';

  document.getElementById('articleQuestions').innerHTML  = '⌛';
  document.getElementById('formQuestions').style.display = 'flex';

  getArticleQuestionnaires(crash.countryid, article.id).then(
    response => {
      let htmlQuestionnaires = '';
      article.questionnaires = response.questionnaires;
      for (const questionnaire of article.questionnaires) {
        htmlQuestionnaires += `<tr><td colspan="2" class="sectionHeader">${questionnaire.title}</td></tr>`;

        let i = 1;
        for (const question of questionnaire.questions) {
          const yesChecked        = question.answer === 1? 'checked' : '';
          const noChecked         = question.answer === 0? 'checked' : '';
          const ndChecked         = question.answer === 2? 'checked' : '';
          const tooltip           = question.explanation? `<span class="iconTooltip" data-tippy-content="${question.explanation}"></span>` : '';
          const answerExplanation = question.answerExplanation? escapeHtml(question.answerExplanation) : '';

          htmlQuestionnaires +=
`<tr id="q${questionnaire.id}_${question.id}">
  <td>${i}) ${question.text} ${tooltip}</td>
  <td style="white-space: nowrap;">
    <label><input name="answer${question.id}" type="radio" ${yesChecked} onclick="saveAnswer(${article.id}, ${question.id}, 1)">Yes</label>
    <label><input name="answer${question.id}" type="radio" ${noChecked} onclick="saveAnswer(${article.id}, ${question.id}, 0)">No</label>
    <label data-tippy-content="Not determinable"><input name="answer${question.id}" type="radio" ${ndChecked} onclick="saveAnswer(${article.id}, ${question.id}, 2)">n.d.</label>
  </td>
</tr>
<tr id="trExplanation${question.id}" style="display: none;"><td colspan="2"><input id="explanation${question.id}" type="text" value="${answerExplanation}" placeholder="Explanation" class="inputForm" oninput="saveExplanationDelayed(${article.id}, ${question.id});"></td></tr>
`;
          i += 1;
        }

        htmlQuestionnaires += `<tr id="questionnaireCompleted${questionnaire.id}" class="ready" style="display: none;"><td colspan="2">
All questions answered 🙏🏼</td></tr>`;
        htmlQuestionnaires += '<tr><td colspan="2" style="border: none; height: 10px;"></td></tr>'
      }

      if (htmlQuestionnaires) htmlQuestionnaires = `<table class="dataTable">${htmlQuestionnaires}</table>`;
      else htmlQuestionnaires = '<div>No questionnaires found</div>';

      document.getElementById('articleQuestions').innerHTML      = htmlQuestionnaires;
      document.getElementById('questionsArticleText').innerHTML  = response.text? formatText(response.text) : '[Full text is not available in database]';

      for (const questionnaire of article.questionnaires) setQuestionnaireGUI(questionnaire);

      tippy('[data-tippy-content]', {allowHTML: true});
    }
  );
}

function setQuestionnaireGUI(questionnaire) {

  document.getElementById('questionnaireCompleted' + questionnaire.id).style.display = allQuestionsAnswered(questionnaire) ? 'table-row' : 'none';

  if (questionnaire.type === QuestionnaireType.bechdel) {
    let iFirstNotYesQuestion = null;
    for (let i=0; i < questionnaire.questions.length; i++) {
      const question = questionnaire.questions[i];

      if ((iFirstNotYesQuestion === null) && (question.answer !== QuestionAnswer.yes)) iFirstNotYesQuestion = i;

      let questionVisible = true;
      if (iFirstNotYesQuestion !== null) questionVisible = i <= iFirstNotYesQuestion;

      const id = 'q' + questionnaire.id + '_' + question.id;
      document.getElementById(id).style.display = questionVisible? 'table-row' : 'none';
    }
  }

  // Show explantion field if not determinable answered
  for (let question of questionnaire.questions) {
    document.getElementById('trExplanation' + question.id).style.display = question.answer === QuestionAnswer.notDeterminable? 'table-row' : 'none';
  }

}

async function saveAnswer(articleId, questionId, answer) {
  const article = getArticleFromId(articleId);

  for (const questionnaire of article.questionnaires) {
    for (const question of questionnaire.questions) {
      if (question.id  === questionId) {
        question.answer = answer;

        setQuestionnaireGUI(questionnaire);
        break;
      }
    }
  }

  const url = '/general/ajaxGeneral.php?function=saveAnswer';
  const data = {
    articleId:  articleId,
    questionId: questionId,
    answer:     answer,
  };

  const response = await fetchFromServer(url, data);

  if (response.error) showError(response.error, 10);
}

function saveExplanationDelayed(articleId, questionId) {
  clearTimeout(saveExplanation.timeout);
  saveExplanation.timeout = setTimeout(function () {saveExplanation(articleId, questionId);},500);
}

async function saveExplanation(articleId, questionId) {

  const url  = '/general/ajaxGeneral.php?function=saveExplanation';
  const data = {
    articleId:   articleId,
    questionId:  questionId,
    explanation: document.getElementById('explanation' + questionId).value.trim(),
  };

  const response = await fetchFromServer(url, data);

  if (response.error) showError(response.error, 10);
}

function nextArticleQuestions(forward=true) {
  event.preventDefault();
  event.stopPropagation();

  const articleId = parseInt(document.getElementById('questionsArticleId').value);

  const index = articles.findIndex(article => article.id === articleId);
  let newArticle;
  if (forward) {
    if (index < articles.length - 1) newArticle = articles[index + 1];
    else {
      // Check if we are on the fill_in page.
      const onFillInPage = window.location.href.includes('fill_in');

      if (onFillInPage) window.location.reload();
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

async function getArticleQuestionnaires(crashCountryId, articleId) {
  const url      = '/general/ajaxGeneral.php?function=getArticleQuestionnairesAndText';
  const response = await fetchFromServer(url, {crashCountryId: crashCountryId, articleId: articleId});

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
  }
}

function domainBlacklisted(url){
  const domainBlacklist = [
    {domain: 'drimble.nl', reason: 'Drimble is geen media website, maar een nieuws verzamelwebsite. Zoek de bron op de drimble.nl pagina en plaats die.'},
  ];

  return domainBlacklist.find(d => url.includes(d.domain));
}

function copyCrashDateFromArticle(){
  document.getElementById('editCrashDate').value  = document.getElementById('editArticleDate').value;
}

async function getArticleMetaData() {
  function showMetaData(meta){
    document.getElementById('editArticleUrl').value = meta.url;
    document.getElementById('editArticleTitle').value = meta.title;
    document.getElementById('editArticleText').value = meta.description;
    document.getElementById('editArticleUrlImage').value = meta.urlimage;
    document.getElementById('editArticleSiteName').value = meta.sitename;

    if (meta.article_body) document.getElementById('editArticleAllText').value  = meta.description + '\n\n' +  meta.article_body;

    if (meta.published_time){
      try {
        const datetime = new Date(meta.published_time);
        document.getElementById('editArticleDate').value = dateToISO(datetime);
      } catch (e) {
        // Silent exception. Do nothing as dates can be invalid
      }
    }
  }

  const urlArticle = document.getElementById('editArticleUrl').value.trim();
  if (! urlArticle) {
    showError(translate('Article_link_not_filled_in'));
    return;
  }

  const domain = domainBlacklisted(urlArticle);
  if (domain) {
    showMessage(`Links from "${domain.domain}" can not be added. ${domain.reason}`, 30);
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
        showMessage(translate('no_data_found_on_web_page'), 30);
      } else showMetaData(response.media);

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
    text: document.getElementById('editArticleText').value.trim(),
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
  if (! articleEdited.text) {showError(translate('Article_summary_not_filled_in')); return;}
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
  crashEdited = {
    id: document.getElementById('crashIDHidden').value,
    date: document.getElementById('editCrashDate').value,
    countryid: document.getElementById('editCrashCountry').value,
    latitude: latitude,
    longitude: longitude,
    locationdescription: locationDescription,
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
    crash.unilateral = crashEdited.unilateral;
    crash.pet = crashEdited.pet;
    crash.trafficjam = crashEdited.trafficjam;

    const article = articles.find(a => a.id === articleEdited.id);
    if (article){
      article.url = articleEdited.url;
      article.sitename = articleEdited.sitename;
      article.title = articleEdited.title;
      article.text = articleEdited.text;
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
    // Delete all markers
    crashes.forEach(c => {c.marker.remove(); c.marker = null;});
    crashes  = [];
    articles = [];

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

  confirmWarning(translate('Delete_article') + '<br>' + article.title.substr(0, 100),
    function (){deleteArticleDirect(id)},
    translate('Delete'), null, true);
}

function deleteCrash(crashId) {
  closeAllPopups();

  confirmWarning(translate('Delete_crash') + '<br>' + crashId,
    function (){deleteCrashDirect(crashId)},
    translate('Delete'));
}

function crashRowHTML(crash, isSearch=false){

  function innerHTML(crash, allArticles) {
    const htmlPersons = getCrashHumansIcons(crash, false);

    const crashArticles = getCrashArticles(crash.id, allArticles);
    let img = '';
    let title = '';
    if (crashArticles.length > 0) {
      title = crashArticles[0].title;
      img = `<img class="thumbnail" src="${crashArticles[0].urlimage}">`
    }

    return `
  <div class="flexRow" style="justify-content: space-between;">
    <div style="padding: 3px;">
      ${title}
      <div class="smallFont">#${crash.id} ${crash.date.pretty()}</div>
      <div>${htmlPersons}</div>
    </div>
    <div class="thumbnailWrapper">${img}</div>
  </div>`;
  }

  if (isSearch) {
    const html = innerHTML(crash, articlesFound);
    return `<div class="searchRow" onclick="mergeSearchResultClick(${crash.id})">${html}</div>`;
  } else return innerHTML(crash, articles);
}

function showMergeCrashForm(id) {
  closeAllPopups();
  const crash = getCrashFromId(id);

  document.getElementById('mergeFromCrashIDHidden').value     = crash.id;
  document.getElementById('mergeCrashSearch').value           = '';
  document.getElementById('mergeCrashSearchDay').value        = 0; // Same day
  document.getElementById('mergeToCrashIDHidden').value       = '';
  document.getElementById('mergeCrashTo').innerHTML           = '';
  document.getElementById('mergeCrashTo').style.display       = 'none';
  document.getElementById('mergeSearchResults').innerHTML     = '';
  document.getElementById('mergeSearchResults').style.display = 'none';
  document.getElementById('mergeCrashFrom').innerHTML         = crashRowHTML(crash);
  document.getElementById('formMergeCrash').style.display     = 'flex';
  document.getElementById('spinnerMerge').style.display       = 'block';

  searchMergeCrash();
}

function searchMergeCrashDelayed() {
  document.getElementById('spinnerMerge').style.display       = 'block';
  document.getElementById('mergeCrashTo').innerHTML           = '';
  document.getElementById('mergeCrashTo').style.display       = 'none';
  document.getElementById('mergeSearchResults').innerHTML     = '';
  document.getElementById('mergeSearchResults').style.display = 'none';
  document.getElementById('mergeToCrashIDHidden').value       = '';

  clearTimeout(searchMergeCrashDelayed.timeout);
  searchMergeCrashDelayed.timeout = setTimeout(searchMergeCrash,500);
}

async function searchMergeCrash() {
  try {
    const crashID    = parseInt(document.getElementById('mergeFromCrashIDHidden').value);
    const crash      = getCrashFromId(crashID);

    const dateSearch = document.getElementById('mergeCrashSearchDay').value;
    let dateFrom;
    let dateTo;
    if (dateSearch === '0'){
      dateFrom = crash.date;
      dateTo   = crash.date;
    } else if (isInt(dateSearch)) {
      dateFrom = crash.date.addDays(-dateSearch);
      dateTo   = crash.date.addDays(dateSearch);
    }

    const serverData = {
      count: 10,
      filter: {
        text:     document.getElementById('mergeCrashSearch').value.trim().toLowerCase(),
        period:   'custom',
        dateFrom: dateToISO(dateFrom),
        dateTo:   dateToISO(dateTo),
      },
    };

    const url      = '/general/ajaxGeneral.php?function=loadCrashes';
    const response = await fetchFromServer(url, serverData);

    if (response.error) showError(response.error);
    else if (response.ok){
      prepareCrashesServerData(response);
      crashesFound  = response.crashes;
      articlesFound = response.articles;

      let html = '';
      crashesFound.forEach(crashFound => {if (crashFound.id !== crash.id) html += crashRowHTML(crashFound, true);});
      document.getElementById('mergeSearchResults').innerHTML     = html;
      document.getElementById('mergeSearchResults').style.display = 'block';
    }
  } finally {
    document.getElementById('spinnerMerge').style.display = 'none';
  }
}

function mergeSearchResultClick(crashID) {
  const crash = crashesFound.find(crash => crash.id === crashID);
  let html = '';
  let crashId = '';
  if (crash) {
    html  = crashRowHTML(crash);
    crashId = crash.id;
  }

  document.getElementById('mergeToCrashIDHidden').value   = crashId;
  document.getElementById('mergeCrashTo').innerHTML       = html;
  document.getElementById('mergeCrashTo').style.display   = 'block';
}

function mergeCrash() {
  const fromID = parseInt(document.getElementById('mergeFromCrashIDHidden').value);
  const toID = parseInt(document.getElementById('mergeToCrashIDHidden').value);
  if (! toID) showError(translate('No_merge_crash_selected'));

  const crashFrom = getCrashFromId(parseInt(fromID));
  const crashTo = crashesFound.find(crash => crash.id === toID);

  async function mergeCrashesOnServer(fromID, toID){
    const url= `/general/ajaxGeneral.php?function=mergeCrashes&idFrom=${fromID}&idTo=${toID}`;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      articles.forEach(article => {if (article.crashid === fromID) article.crashid = toID;});
      crashes.filter(crash => crash.id !== fromID);

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

  confirmMessage(translate('Merge_crashes') +
    `<ul><li>${crashFrom.id} | ${crashFrom.title}</li><li>${crashTo.id} | ${crashTo.title}</li></ul>`,
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

function filterBarOpen(){
  const element = document.getElementById('filterBar');

  return element && element.classList.contains('active');
}

function searchCrashes() {
  updateBrowserUrl(true);

  reloadCrashes();
}

function updateBrowserUrl(pushState=false){
  const url = new URL(location.origin);

  if (pageType === PageType.deCorrespondent) url.pathname = '/decorrespondent';
  else if (pageType === PageType.lastChanged) url.pathname = '/last_changed';
  else if (pageType === PageType.mosaic) url.pathname = '/mosaic';
  else if (pageType === PageType.map) url.pathname = '/map';

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

      const urlFile = '/admin/' + response.filename;
      download(urlFile, response.filename);
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

      const urlFile = '/admin/' + response.filename;
      download(urlFile, response.filename);
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
  }

  function setCrashMarker(latitude, longitude){
    if (markerEdit) markerEdit.setLngLat([longitude, latitude]);
    else {
      const markerElement = document.createElement('div');
      markerElement.innerHTML = `<img class="mapMarker" src="/images/pin.svg" alt="marker">`;
      markerElement.id = 'marker';
      markerElement.addEventListener('click', (e) => {
        e.stopPropagation();
        confirmMessage(`Locatie verwijderen?`, () => {
          document.getElementById('editCrashLatitude').value  = '';
          document.getElementById('editCrashLongitude').value = '';
          deleteCrashMarker();
        });
       });

      markerEdit = new mapboxgl.Marker(markerElement, {anchor: 'bottom', draggable: true})
        .setLngLat([longitude, latitude])
        .addTo(mapEdit)
        .on('dragend', function() {
          const lngLat = markerEdit.getLngLat();
          saveMarkerPosition(lngLat);
        })
    }
  }

  function deleteCrashMarker(){
    if (markerEdit){
      markerEdit.remove();
      markerEdit = null;
    }
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
