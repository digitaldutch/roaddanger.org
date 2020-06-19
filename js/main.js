let crashes          = [];
let crashesFound     = [];
let articles         = [];
let articlesFound    = [];
let editCrashPersons = [];
let spinnerLoad;
let pageType;
let graph;
let mapMain;
let mapEdit;
let mapCrash;
let markerEdit;
let PageType = Object.freeze({
  stream:                        0,
  crash:                         1,
  moderations:                   2,
  statisticsTransportationModes: 3,
  statisticsGeneral:             4,
  statisticsCrashPartners:       5,
  recent:                        6,
  deCorrespondent:               7,
  mosaic:                        8,
  export:                        9,
  map:                           10,
  childDeaths:                   11,
});


function initMain() {
  initPage();
  initSearchBar();

  spinnerLoad = document.getElementById('spinnerLoad');

  const url                 = new URL(location.href);
  const crashID             = getCrashNumberFromPath(url.pathname);
  const articleID           = url.searchParams.get('articleid');
  const searchText          = url.searchParams.get('search');
  const searchPeriod        = url.searchParams.get('period');
  const searchDateFrom      = url.searchParams.get('date_from');
  const searchDateTo        = url.searchParams.get('date_to');
  const searchSiteName      = url.searchParams.get('sitename');
  const searchPersons       = url.searchParams.get('persons');
  const searchHealthDead    = url.searchParams.get('hd');
  const searchHealthInjured = url.searchParams.get('hi');
  const searchChild         = url.searchParams.get('child');
  const pathName            = decodeURIComponent(url.pathname);

  if      (pathName.startsWith('/moderaties'))                 pageType = PageType.moderations;
  else if (pathName.startsWith('/stream'))                     pageType = PageType.stream;
  else if (pathName.startsWith('/decorrespondent'))            pageType = PageType.deCorrespondent;
  else if (pathName.startsWith('/mozaiek'))                    pageType = PageType.mosaic;
  else if (pathName.startsWith('/kinddoden'))                  pageType = PageType.childDeaths;
  else if (pathName.startsWith('/map'))                        pageType = PageType.map;
  else if (pathName.startsWith('/statistieken/algemeen'))      pageType = PageType.statisticsGeneral;
  else if (pathName.startsWith('/statistieken/andere_partij')) pageType = PageType.statisticsCrashPartners;
  else if (pathName.startsWith('/statistieken/vervoertypes'))  pageType = PageType.statisticsTransportationModes;
  else if (pathName.startsWith('/statistieken'))               pageType = PageType.statisticsGeneral;
  else if (pathName.startsWith('/exporteren'))                 pageType = PageType.export;
  else if (crashID)                                            pageType = PageType.crash;
  else                                                         pageType = PageType.recent;

  let title = '';
  switch (pageType){
    case PageType.stream:          title = 'Laatst gewijzigde ongelukken'; break;
    case PageType.deCorrespondent: title = 'De Correspondent week<br>14 t/m 20 januari 2019'; break;
    case PageType.moderations:     title = 'Moderaties'; break;
    case PageType.recent:          title = translate('Recent_crashes'); break;
    default:                       title = '';
  }

  if (title) document.getElementById('pageSubTitle').innerHTML = title;

  const searchButtonExists = document.getElementById('buttonSearch');
  if (searchButtonExists && (searchText || searchPeriod || searchSiteName || searchHealthDead || searchChild || searchPersons)) {
    document.body.classList.add('searchBody');
    document.getElementById('searchText').value = searchText;

    if (searchPeriod)   document.getElementById('searchPeriod').value   = searchPeriod;
    if (searchDateFrom) document.getElementById('searchDateFrom').value = searchDateFrom;
    if (searchDateTo)   document.getElementById('searchDateTo').value   = searchDateTo;

    document.getElementById('searchSiteName').value = searchSiteName;

    if (searchHealthDead)    document.getElementById('searchPersonHealthDead').classList.add('buttonSelectedBlue');
    if (searchHealthInjured) document.getElementById('searchPersonHealthInjured').classList.add('buttonSelectedBlue');
    if (searchChild)         document.getElementById('searchPersonChild').classList.add('buttonSelectedBlue');
    if (searchPersons)       setPersonsFilter(searchPersons);
  }
  setCustomRangeVisibility();

  addEditPersonButtons();

  initWatchPopStart();

  if ((pageType === PageType.statisticsTransportationModes) ||
      (pageType === PageType.statisticsGeneral) ||
      (pageType === PageType.statisticsCrashPartners)) {
    initStatistics();
    loadStatistics();
  } else if (pageType === PageType.childDeaths){
    initObserver(loadChildDeaths);

    document.getElementById('filterChildDead').classList.add('buttonSelectedBlue'); // Dead is always selected
    if (searchHealthInjured) document.getElementById('filterChildInjured').classList.add('buttonSelectedBlue');

    loadChildDeaths();
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
    loadCrashes();
  }
}

function initStatistics(){
  const url = new URL(location.href);
  if ([PageType.statisticsTransportationModes, PageType.statisticsCrashPartners].includes(pageType)){
    const period           = url.searchParams.get('period');
    const searchDateFrom   = url.searchParams.get('date_from');
    const searchDateTo     = url.searchParams.get('date_to');
    const searchChild      = url.searchParams.get('child');

    if (period)         document.getElementById('filterStatsPeriod').value   = period;
    if (searchChild)    document.getElementById('filterStatsChild').classList.add('buttonSelectedBlue');
    if (searchDateFrom) document.getElementById('filterStatsDateFrom').value = searchDateFrom;
    if (searchDateTo)   document.getElementById('filterStatsDateTo').value   = searchDateTo;
  }

  setCustomFilterVisibility();
}

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
  loadUserData();

  let html = '';
  for (const key of Object.keys(TTransportationMode)){
    const transportationMode =  TTransportationMode[key];
    const text               = transportationModeText(transportationMode);
    html += `<tr><td>${transportationMode}</td><td>${text}</td></tr>`;
  }
  document.getElementById('tbodyTransportationMode').innerHTML = html;

  html = '';
  for (const key of Object.keys(THealth)){
    const health = THealth[key];
    const text   = healthText(health);
    html += `<tr><td>${health}</td><td>${text}</td></tr>`;
  }
  document.getElementById('tbodyHealth').innerHTML = html;
}

function showCrashVictimsGraph(crashVictims){

  function getCrashPartnerVictims(victimMode, partnerMode){
    const victim  = crashVictims.find(v => v.transportationMode === victimMode);
    if (! victim) return 0;
    const partner = victim.crashPartners.find(p => p.transportationMode === partnerMode);
    return partner? partner.victimCount : 0;
  }

  // Put data in heatmap points layout
  let victimModes = [];
  let partnerModes = [];
  for (const key of Object.keys(TTransportationMode)) {
    victimModes.push(TTransportationMode[key]);
    partnerModes.push(TTransportationMode[key]);
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
    xLabel: 'Tegenpartij',
    yLabel: 'Verkeersdoden',
  };

  const filter = {
    period:   document.getElementById('filterStatsPeriod').value,
    dateFrom: document.getElementById('filterStatsDateFrom').value,
    dateTo:   document.getElementById('filterStatsDateTo').value,
    child:    document.getElementById('filterStatsChild').classList.contains('buttonSelectedBlue'),
  }
  graph = new CrashPartnerGraph('graphPartners', points, options, filter);
}

function selectFilterChild() {
  document.getElementById('filterStatsChild').classList.toggle('buttonSelectedBlue');
  loadStatistics();
}

function selectFilterChildDeaths() {
  event.target.classList.toggle('buttonSelectedBlue');
  clearTable();

  const dead    = document.getElementById('filterChildDead').classList.contains('buttonSelectedBlue');
  const injured = document.getElementById('filterChildInjured').classList.contains('buttonSelectedBlue');

  const url = new URL(window.location);
  if (dead)    url.searchParams.set('hd', 1); else url.searchParams.delete('hd');
  if (injured) url.searchParams.set('hi', 1); else url.searchParams.delete('hi');

  window.history.pushState(null, null, url.toString());

  loadChildDeaths();
}

async function loadStatistics(){

  function showStatisticsTransportation(dbStats) {
    let html = '';
    for (const stat of dbStats.total) {
      const icon = transportationModeIcon(stat.transportationmode, true);
      html += `<tr>
<td><div class="flexRow">${icon}<span class="hideOnMobile" style="margin-left: 5px;">${transportationModeText(stat.transportationmode)}</span></div></td>
<td style="text-align: right;">${stat.dead}</td>
<td style="text-align: right;">${stat.injured}</td>
<td style="text-align: right;">${stat.unharmed}</td>
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
    document.getElementById('main').innerHTML = `
    <table class="dataTable">
    
      <tr class="trHeader"><td colspan="2">De Correspondent week (14 t/m 20 januari 2019)</td></tr>
      
      <tr>
        <td>Ongelukken</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.crashes}</td>
      </tr>
      <tr>
        <td>Artikelen</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.articles}</td>
      </tr>
      <tr>
        <td>Doden</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.dead}</td>
      </tr>
      <tr>
        <td>Gewond</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.injured}</td>
      </tr>
      <tr>
        <td>Toegevoegde ongelukken</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.crashesAdded}</td>
      </tr>
      <tr>
        <td>Toegevoegde artikelen</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.articlesAdded}</td>
      </tr>
      <tr>
        <td>Mensen die zich aangemeld hebben op deze site</td>
        <td style="text-align: right;">${dbStats.deCorrespondent.users}</td>
      </tr>

      <tr class="trHeader"><td colspan="2">Vandaag</td></tr>
      
      <tr>
        <td>Ongelukken</td>
        <td style="text-align: right;">${dbStats.today.crashes}</td>
      </tr>
      <tr>
        <td>Artikelen</td>
        <td style="text-align: right;">${dbStats.today.articles}</td>
      </tr>
      <tr>
        <td>Doden</td>
        <td style="text-align: right;">${dbStats.today.dead}</td>
      </tr>
      <tr>
        <td>Gewond</td>
        <td style="text-align: right;">${dbStats.today.injured}</td>
      </tr>        
      <tr>
        <td>Toegevoegde Ongelukken</td>
        <td style="text-align: right;">${dbStats.today.crashesAdded}</td>
      </tr>
      <tr>
        <td>Toegevoegde Artikelen</td>
        <td style="text-align: right;">${dbStats.today.articlesAdded}</td>
      </tr>

      <tr class="trHeader"><td colspan="2">7 dagen</td></tr>

      <tr>
        <td>Ongelukken</td>
        <td style="text-align: right;">${dbStats.sevenDays.crashes}</td>
      </tr>
      <tr>
        <td>Artikelen</td>
        <td style="text-align: right;">${dbStats.sevenDays.articles}</td>
      </tr>
      <tr>
        <td>Doden</td>
        <td style="text-align: right;">${dbStats.sevenDays.dead}</td>
      </tr>
      <tr>
        <td>Gewond</td>
        <td style="text-align: right;">${dbStats.sevenDays.injured}</td>
      </tr>        
      <tr>
        <td>Toegevoegde Ongelukken</td>
        <td style="text-align: right;">${dbStats.sevenDays.crashesAdded}</td>
      </tr>
      <tr>
        <td>Toegevoegde Artikelen</td>
        <td style="text-align: right;">${dbStats.sevenDays.articlesAdded}</td>
      </tr>

      <tr class="trHeader"><td colspan="2">Totaal in database</td></tr>

      <tr>
        <td>Ongelukken</td>
        <td style="text-align: right;">${dbStats.total.crashes}</td>
      </tr>
      <tr>
        <td>Artikelen</td>
        <td style="text-align: right;">${dbStats.total.articles}</td>
      </tr>
      <tr>
        <td>Doden</td>
        <td style="text-align: right;">${dbStats.total.dead}</td>
      </tr>
      <tr>
        <td>Gewond</td>
        <td style="text-align: right;">${dbStats.total.injured}</td>
      </tr>                
      <tr>
        <td>Mensen die zich aangemeld hebben op deze site</td>
        <td style="text-align: right;">${dbStats.total.users}</td>
      </tr>
      
    </table>
`;
  }

  try {
    spinnerLoad.style.display = 'block';

    setCustomFilterVisibility();

    const serverData = {};

    switch (pageType) {
      case PageType.statisticsTransportationModes: {serverData.type = 'transportationModes'; break;}
      case PageType.statisticsGeneral:             {serverData.type = 'general';             break;}
      case PageType.statisticsCrashPartners:       {serverData.type = 'crashPartners';       break;}
    }

    if ([PageType.statisticsTransportationModes, PageType.statisticsCrashPartners].includes(pageType)) {
      serverData.filter = {
        period:   document.getElementById('filterStatsPeriod').value,
        dateFrom: document.getElementById('filterStatsDateFrom').value,
        dateTo:   document.getElementById('filterStatsDateTo').value,
        child:    document.getElementById('filterStatsChild').classList.contains('buttonSelectedBlue')? 1 : 0,
      };
    }

    const url      = '/ajax.php?function=getStatistics';
    const response = await fetchFromServer(url, serverData);

    if (response.user) updateLoginGUI(response.user);
    if (response.error) showError(response.error);
    else {

      let url = window.location.origin + '/statistieken';
      switch (pageType) {
        case PageType.statisticsGeneral:             {url += '/algemeen';      break;}
        case PageType.statisticsCrashPartners:       {url += '/andere_partij'; break;}
        case PageType.statisticsTransportationModes: {url += '/vervoertypes';  break;}
      }

      if ([PageType.statisticsTransportationModes, PageType.statisticsCrashPartners].includes(pageType)) {
        url += '?period=' + serverData.filter.period;
        if (serverData.filter.child) url += '&child=1';
        if (serverData.filter.period === 'custom') {
          if (serverData.filter.dateFrom) url += '&date_from=' + serverData.filter.dateFrom;
          if (serverData.filter.dateTo)   url += '&date_to='   + serverData.filter.dateTo;
        }
      }

      window.history.replaceState(null, null, url);

      switch (pageType) {
        case PageType.statisticsGeneral:             {showStatisticsGeneral(response.statistics);              break;}
        case PageType.statisticsCrashPartners:       {showCrashVictimsGraph(response.statistics.crashVictims); break;}
        case PageType.statisticsTransportationModes: {showStatisticsTransportation(response.statistics);       break;}
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

async function loadChildDeaths(){

  let newCrashes = [];
  const serverData = {
    count:   20,
    offset:  crashes.length,
    sort:    'crashDate',
    getUser: !user,
    filter: {
      child:         1,
      healthDead:    document.getElementById('filterChildDead').classList.contains('buttonSelectedBlue')? 1 : 0,
      healthInjured: document.getElementById('filterChildInjured').classList.contains('buttonSelectedBlue')? 1 : 0,
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

        const year = crash.date.getFullYear();
        if (year !== iYear) {
          iYear = year;
          html += `<tr class="trHeader"><td colspan="3">${iYear}</td></tr>`;
        }

        let htmlIconsChildren = '';
        let htmlIconsOther    = '';
        crash.persons.forEach(p => {
          if (p.health === THealth.dead) {
            if (p.child === 1) htmlIconsChildren += '<img src="/images/persondead.svg" style="width: 10px;">';
            else htmlIconsOther += '<img src="/images/persondead_black.svg" style="width: 10px;">';
          }

          if (p.health === THealth.injured) {
            if (p.child === 1) htmlIconsChildren += '<img src="/images/person_injured.svg" style="width: 10px;">';
            else htmlIconsOther += '<img src="/images/person_injured_black.svg" style="width: 10px;">';
          }
        });

        html += `
        <tr onclick="showCrashDetails(${crash.id})">
          <td style="white-space: nowrap;">${crash.date.pretty()}</td>
          <td style="white-space: nowrap;">${htmlIconsChildren}${htmlIconsOther}</td>
          <td class="td400">${crash.title}</td>
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
  const url      = '/ajax.php?function=loadCrashes';
  const response = await fetchFromServer(url, serverData);

  if (response.user) updateLoginGUI(response.user);

  if (response.error) {showError(response.error); return [];}
  else {
    prepareCrashServerData(response);

    crashes  = crashes.concat(response.crashes);
    articles = articles.concat(response.articles);

    return response.crashes;
  }
}

async function loadCrashes(crashID=null, articleID=null){

  function showCrashes(newCrashes){
    let html = '';
    if (newCrashes.length === 0) {
      let text = '';
      if (pageType === PageType.moderations) text = 'Geen moderaties gevonden';
      else text = 'Geen ongelukken gevonden';

      html = `<div style="text-align: center;">${text}</div>`;
    } else if (pageType === PageType.mosaic) html = getMosaicHTML(newCrashes);
    else {
      if (pageType === PageType.crash) {
        html = getCrashDetailsHTML(newCrashes[0].id);
      }
      else for (let crash of newCrashes) html += getCrashListHTML(crash.id);
    }

    document.getElementById('cards').innerHTML += html;
    if (pageType === PageType.crash) showMapCrash(newCrashes[0].latitude, newCrashes[0].longitude);
    tippy('[data-tippy-content]');
  }

  if (observerSpinner) observerSpinner.unobserve(spinnerLoad);
  let newCrashes;

  let maxLoadCount = (pageType === PageType.mosaic)? 60 : 20;
  try {
    spinnerLoad.style.display = 'block';

    const serverData = {
      count:   maxLoadCount,
      offset:  crashes.length,
      getUser: !user,
    };

    serverData.filter = getSearchFilter();

    if (crashID) serverData.id = crashID;
    if (pageType  === PageType.moderations) serverData.moderations=1;
    else if ((pageType === PageType.recent) || (pageType === PageType.mosaic)) serverData.sort = 'crashDate';
    else if (pageType  === PageType.deCorrespondent) {
      serverData.sort          = 'crashDate';
      serverData.filter.period = 'decorrespondent';
    }

    newCrashes = await loadCrashesFromServer(serverData);

  } catch (error) {
    showError(error.message);
  } finally {
    // Hide spinner if all data is loaded
    if (newCrashes.length < maxLoadCount) spinnerLoad.style.display = 'none';
  }

  if (crashID && (crashes.length === 1)) document.title = crashes[0].title + ' | Het Ongeluk';

  showCrashes(newCrashes);
  highlightSearchText();

  if (observerSpinner && (newCrashes.length >= maxLoadCount)) observerSpinner.observe(spinnerLoad);

  if (articleID) setTimeout(()=> {selectArticle(articleID);}, 1);
}

function getSearchFilter(){
  if (searchVisible()) {
    return {
      text:          document.getElementById('searchText').value.trim().toLowerCase(),
      period:        document.getElementById('searchPeriod').value,
      dateFrom:      document.getElementById('searchDateFrom').value,
      dateTo:        document.getElementById('searchDateTo').value,
      persons:       getPersonsFromFilter(),
      siteName:      document.getElementById('searchSiteName').value.trim().toLowerCase(),
      healthDead:    document.getElementById('searchPersonHealthDead').classList.contains('buttonSelectedBlue')? 1 : 0,
      healthInjured: document.getElementById('searchPersonHealthInjured').classList.contains('buttonSelectedBlue')? 1 : 0,
      child:         document.getElementById('searchPersonChild').classList.contains('buttonSelectedBlue')? 1 : 0,
    };
  }  else return {};
}

function delayedLoadMapData(){
  if (delayedLoadMapData.timeout) clearTimeout(delayedLoadMapData.timeout);

  delayedLoadMapData.timeout = setTimeout(loadMapDataFromServer, 1000);
}

async function loadMapDataFromServer(){

  try {
    document.getElementById('spinnerTitle').style.display = 'inline-flex';

    const bounds = mapMain.getBounds();
    const serverData  = {
      count:   200,
      getUser: ! user,
      sort:    'crashDate',
    };

    serverData.filter = getSearchFilter();
    serverData.filter.area = {
      latMin: bounds._sw.lat,
      lonMin: bounds._sw.lng,
      latMax: bounds._ne.lat,
      lonMax: bounds._ne.lng,
    }

    const url      = '/ajax.php?function=loadCrashes';
    const response = await fetchFromServer(url, serverData);

    if (response.user) updateLoginGUI(response.user);

    prepareCrashServerData(response);

    for (const crash of response.crashes) {
      if (! crashes.find(c => c.id === crash.id)) {
        const markerElement = document.createElement('div');
        const personDied    = crash.persons.find(p => p.health === THealth.dead);
        let   personInjured = false;
        if (! personDied) personInjured = crash.persons.find(p => p.health === THealth.injured);

        const imgSrc = personDied? 'persondead.svg' : personInjured? 'person_injured.svg' : 'crash_icon.svg';

        markerElement.innerHTML = `<img class="crashIcon" src="/images/${imgSrc}">`;
        markerElement.onclick = () => {showCrashDetails(crash.id)};

        crash.marker = (new mapboxgl.Marker(markerElement)
          .setLngLat([crash.longitude, crash.latitude])
          .addTo(mapMain));

        crashes.push(crash);
        const crashArticles = response.articles.filter(a => a.accidentid === crash.id);
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
    document.getElementById('spinnerTitle').style.display = 'none';
  }

}

async function loadMap() {
  const latitudeNL  = 52.16;
  const longitudeNL = 5.41;
  const zoomNL      = 6;

  const url = new URL(location.href);
  const longitude        = url.searchParams.get('lng')  || longitudeNL;
  const latitude         = url.searchParams.get('lat')  || latitudeNL;
  const zoom             = url.searchParams.get('zoom') || zoomNL;

  if (! mapMain){
    mapboxgl.accessToken = mapboxKey;

    mapMain = new mapboxgl.Map({
      container: 'mapMain',
      style:     'mapbox://styles/mapbox/streets-v9',
      center:    [longitude, latitude],
      zoom:      zoom,
    })
    .addControl(
      new MapboxGeocoder({
        accessToken: mapboxgl.accessToken,
        mapboxgl:    mapboxgl,
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

function prepareCrashServerData(data){
  data.crashes.forEach(crash => {
    crash.date           = new Date(crash.date);
    crash.createtime     = new Date(crash.createtime);
    crash.streamdatetime = new Date(crash.streamdatetime);

    let id = 1;
    crash.persons.forEach(person => person.id = id++);
  });

  data.articles.forEach(article => prepareArticleServerData(article));
}

function getCrashGUIButtons(crash){
  let buttons = [];
  crash.persons.forEach(person => {
    // In the GUI buttons are used to visualise each person or group of persons.
    // We group persons who are in the same transportation item (eg 4 persons in a car).
    let button;
    if (person.groupid) {
      // All persons in same group are added to 1 button/icon in the GUI
      button = buttons.find(button => button.groupid === person.groupid);
      // Create new button if it does not yet exist
      if (! button) {
        button = {groupid: person.groupid, persons: []};
        buttons.push(button);
      }
    } else {
      // Persons without group always get their own GUI button/icon
      button = {groupid: null, persons: []};
      buttons.push(button);
    }
    button.persons.push(person);
  });
  return buttons;
}

function getCrashListHTML(crashID){
  const crash         = getCrashFromID(crashID);
  const crashArticles = getCrashArticles(crash.id, articles);
  const canEditCrash  = user.moderator || (crash.userid === user.id);

  let htmlArticles = '';
  for (let article of crashArticles) {

    const canEditArticle = user.moderator || (article.userid === user.id);
    let htmlModeration = '';

    if (article.awaitingmoderation){
      let modHTML = '';
      if (user.moderator) modHTML = `
Lieve moderator, dit artikel van "${article.user}" wacht op moderatie.
<div style="margin: 10px;">
  <button class="button" onclick="articleModerateOK(${article.id})">Keur artikel goed</button>
  <button class="button buttonGray" onclick="deleteArticle(${article.id})">Verwijder artikel</button>
</div>
`;
      else if (article.userid === user.id) modHTML = 'Bedankt voor het toevoegen van dit artikel. Je bijdrage wordt spoedig gemodereerd en is tot die tijd nog niet voor iedereen zichtbaar.';
      else modHTML = 'Dit artikel wordt spoedig gemodereerd en is tot die tijd nog niet zichtbaar op de voorpagina.';

      htmlModeration = `<div id="articleModeration${article.id}" class="moderation">${modHTML}</div>`;
    }

    let htmlButtonAllText = '';
    if (user.moderator && article.hasalltext) htmlButtonAllText = `<span class="buttonSelectionSmall bgArticle" data-userid="${article.userid}" data-tippy-content="Toon alle tekst" onclick="toggleAllText(this, event, ${article.id}, ${article.id});"></span>`;

    let htmlMenuEdit      = '';
    let buttonEditArticle = '';
    if (canEditArticle) {
      buttonEditArticle = `<span class="buttonEditPost bgTripleDots" data-userid="${article.userid}" onclick="showArticleMenu(event, ${article.id});"></span>`;
      htmlMenuEdit += `
        <div id="menuArticle${article.id}" class="buttonPopupMenu" onclick="event.preventDefault();">
          <div onclick="editArticle(${crash.id},  ${article.id});">Bewerken</div>
          <div onclick="deleteArticle(${article.id})">Verwijderen</div>
       </div>`;
    }

    htmlArticles +=`
<div class="cardArticle" id="article${article.id}">
  <a href="${article.url}" target="article" onclick="event.stopPropagation();">
    <div class="articleImageWrapper"><img class="articleImage" src="${article.urlimage}" onerror="this.style.display='none';"></div>
  </a>
  <div class="articleBody">
    <span class="postButtonArea" onclick="event.stopPropagation();">
      <span style="position: relative;">
        ${htmlButtonAllText}
        ${buttonEditArticle}
      </span>
      ${htmlMenuEdit}                  
    </span>   
    
    ${htmlModeration}     
  
    <div class="smallFont articleTitleSmall">
      <a href="${article.url}" target="article" onclick="event.stopPropagation();"><span class="cardSiteName">${escapeHtml(article.sitename)}</span></a> 
      | ${article.publishedtime.pretty()} | toegevoegd door ${article.user}
    </div>
  
    <div class="articleTitle">${escapeHtml(article.title)}</div>
    <div id="articleText${article.id}" class="postText">${escapeHtml(article.text)}</div>
  </div>
</div>`;
  }

  const htmlTopIcons = getCrashTopIcons(crash, true);
  let titleSmall     = 'aangemaakt door ' + crash.user;
  let titleModified  = '';
  if (crash.streamtopuser) {
    switch (crash.streamtoptype) {
      case TStreamTopType.edited:       titleModified = ' | aangepast door '                + crash.streamtopuser; break;
      case TStreamTopType.articleAdded: titleModified = ' | nieuw artikel toegevoegd door ' + crash.streamtopuser; break;
      case TStreamTopType.placedOnTop:  titleModified = ' | omhoog geplaatst door '         + crash.streamtopuser; break;
    }
  }

  // Created date is only added if no modified title
  if (titleModified) titleSmall += titleModified;
  else titleSmall += ' ' + crash.createtime.pretty();

  const htmlPersons = getCrashButtonsHTML(crash, false, true);

  let htmlModeration = '';
  if (crash.awaitingmoderation){
    let modHTML = '';
    if (user.moderator) modHTML = `
Lieve moderator, deze bijdrage van "${crash.user}" wacht op moderatie.
<div style="margin: 10px;">
  <button class="button" onclick="crashModerateOK(${crash.id})">Keur bijdrage goed</button>
  <button class="button buttonGray" onclick="deleteCrash(${crash.id})">Verwijder bijdrage</button>
</div>
`;
    else if (crash.userid === user.id) modHTML = 'Bedankt voor het toevoegen van onderstaand bericht. Je bijdrage wordt spoedig gemodereerd en is tot die tijd nog niet voor iedereen zichtbaar.';
    else modHTML = 'Deze bijdrage wordt spoedig gemodereerd en is tot die tijd nog niet zichtbaar op de voorpagina.';

    htmlModeration = `<div id="crashModeration${crash.id}" class="moderation" onclick="event.stopPropagation()">${modHTML}</div>`;
  }

  let htmlMenuEditItems = '';
  if (canEditCrash) {
    htmlMenuEditItems = `
      <div onclick="editCrash(${crash.id});">Bewerken</div>
      <div onclick="showMergeCrashForm(${crash.id});">Samenvoegen</div>
      <div onclick="deleteCrash(${crash.id});">Verwijderen</div>
`;
  }

  if (user.moderator) htmlMenuEditItems += `<div onclick="crashToTopStream(${crash.id});" data-moderator>Plaats bovenaan stream</div>`;

  return `
<div id="crash${crash.id}" class="cardCrashList" onclick="showCrashDetails(${crash.id}); event.stopPropagation();">
  <span class="postButtonArea" onclick="event.stopPropagation();">
    <span style="position: relative;">
      <span class="buttonEditPost bgTripleDots"  data-userid="${crash.userid}" onclick="showCrashMenu(event, ${crash.id});"></span>
    </span>
    <div id="menuCrash${crash.id}" class="buttonPopupMenu" onclick="event.preventDefault();">
      <div onclick="addArticleToCrash(${crash.id});">Artikel toevoegen</div>
      ${htmlMenuEditItems}
    </div>            
  </span>        

  ${htmlModeration}
   
  <div class="cardTop">
    <div style="width: 100%;">
      <div class="smallFont cardTitleSmall">${crash.date.pretty()} | ${titleSmall}</div>
      <div class="cardTitle">${escapeHtml(crash.title)}</div>
      <div>${htmlPersons}</div>
    </div>
    ${htmlTopIcons}
  </div>

  <div class="postText">${escapeHtml(crash.text)}</div>    
  
  ${htmlArticles}
</div>`;
}

function getMosaicHTML(newCrashes){
  function getIconsHTML(crash){
    let html = '';
    crash.persons.forEach(person => {
      if (healthVisible(person.health)) html += `<div class="iconSmall ${healthImage(person.health)}"></div>`;
    });
    return html;
  }

  let html = '';
  for (let crash of newCrashes) {
    const crashArticles = getCrashArticles(crash.id, articles);
    const htmlPersons = getIconsHTML(crash);

    if (crashArticles.length > 0) {
      let article = crashArticles[0];
      html +=`<div onclick="showCrashDetails(${crash.id}); event.stopPropagation();">
<div class="thumbPersons">${htmlPersons}</div>
<div class="thumbDetails">${article.publishedtime.pretty()}</div>
<img src="${article.urlimage}" onerror="this.style.visibility='hidden';">
</div>`;
    }
  }

  return html;
}

function getCrashDetailsHTML(crashId){
  const crash         = getCrashFromID(crashId);
  const crashArticles = getCrashArticles(crash.id, articles);
  const canEditCrash  = user.moderator || (crash.userid === user.id);

  let htmlArticles = '';
  for (let article of crashArticles) {
    const articleDivID = 'details' + article.id;
    let htmlModeration = '';
    if (article.awaitingmoderation){
      let modHTML = '';
      if (user.moderator) modHTML = `
Lieve moderator, dit artikel van "${article.user}" wacht op moderatie.
<div style="margin: 10px;">
  <button class="button" onclick="articleModerateOK(${article.id})">Keur artikel goed</button>
  <button class="button buttonGray" onclick="deleteArticle(${article.id})">Verwijder artikel</button>
</div>
`;
      else if (article.userid === user.id) modHTML = 'Bedankt voor het toevoegen van dit artikel. Je bijdrage wordt spoedig gemodereerd en is tot die tijd nog niet voor iedereen zichtbaar.';
      else modHTML = 'Dit artikel wordt spoedig gemodereerd en is tot die tijd nog niet zichtbaar op de voorpagina.';

      htmlModeration = `<div id="articleModeration${articleDivID}" class="moderation" onclick="event.stopPropagation()">${modHTML}</div>`;
    }

    let htmlButtonAllText = '';
    if (user.moderator && article.hasalltext) htmlButtonAllText = `<span class="buttonSelectionSmall bgArticle" data-userid="${article.userid}" data-tippy-content="Toon alle tekst" onclick="toggleAllText(this, event, '${articleDivID}', ${article.id});"></span>`;

    htmlArticles +=`
<div class="cardArticle" id="article${articleDivID}" onclick="closeAllPopups(); event.stopPropagation();">
  <a href="${article.url}" target="article">
    <div class="articleImageWrapper"><img class="articleImage" src="${article.urlimage}" onerror="this.style.display='none';"></div>
  </a>
  <div class="articleBody">
    <span class="postButtonArea" onclick="event.stopPropagation();">
      <span style="position: relative;">
        ${htmlButtonAllText}
        <span class="buttonEditPost bgTripleDots" data-userid="${article.userid}" onclick="showArticleMenu(event, '${articleDivID}');"></span>
      </span>
      <div id="menuArticle${articleDivID}" class="buttonPopupMenu" onclick="event.preventDefault();">
        <div onclick="editArticle(${crash.id},  ${article.id});">Bewerken</div>
        <div onclick="deleteArticle(${article.id})">Verwijderen</div>
      </div>            
    </span>   
    
    ${htmlModeration}     
  
    <div class="smallFont articleTitleSmall">
      <a href="${article.url}" target="article"><span class="cardSiteName">${escapeHtml(article.sitename)}</span></a> 
      | ${article.publishedtime.pretty()} | toegevoegd door ${article.user}
    </div>
  
    <div class="articleTitle">${escapeHtml(article.title)}</div>
    <div id="articleText${articleDivID}" class="postText">${escapeHtml(article.text)}</div>
  </div>
</div>`;
  }

  const htmlTopIcons = getCrashTopIcons(crash);
  let titleSmall     = 'aangemaakt door ' + crash.user;
  let titleModified  = '';
  if (crash.streamtopuser) {
    switch (crash.streamtoptype) {
      case TStreamTopType.edited:       titleModified = ' | aangepast door '                + crash.streamtopuser; break;
      case TStreamTopType.articleAdded: titleModified = ' | nieuw artikel toegevoegd door ' + crash.streamtopuser; break;
      case TStreamTopType.placedOnTop:  titleModified = ' | omhoog geplaatst door '         + crash.streamtopuser; break;
    }
  }

  // Created date is only added if no modified title
  if (titleModified) titleSmall += titleModified;
  else titleSmall += ' ' + crash.createtime.pretty();

  const htmlPersons = getCrashButtonsHTML(crash, false);

  const crashDivId = 'details' + crash.id;
  let htmlModeration = '';
  if (crash.awaitingmoderation){
    let modHTML = '';
    if (user.moderator) modHTML = `
Lieve moderator, deze bijdrage van "${crash.user}" wacht op moderatie.
<div style="margin: 10px;">
  <button class="button" onclick="crashModerateOK(${crash.id})">Keur bijdrage goed</button>
  <button class="button buttonGray" onclick="deleteCrash(${crash.id})">Verwijder bijdrage</button>
</div>
`;
    else if (crash.userid === user.id) modHTML = 'Bedankt voor het toevoegen van onderstaand bericht. Je bijdrage wordt spoedig gemodereerd en is tot die tijd nog niet voor iedereen zichtbaar.';
    else modHTML = 'Deze bijdrage wordt spoedig gemodereerd en is tot die tijd nog niet zichtbaar op de voorpagina.';

    htmlModeration = `<div id="crashModeration${crashDivId}" class="moderation" onclick="event.stopPropagation()">${modHTML}</div>`;
  }

  let htmlMenuEditItems = '';
  if (canEditCrash) {
    htmlMenuEditItems = `
      <div onclick="editCrash(${crash.id});">Bewerken</div>
      <div onclick="showMergeCrashForm(${crash.id});">Samenvoegen</div>
      <div onclick="deleteCrash(${crash.id});">Verwijderen</div>
`;
  }

  if (user.moderator) htmlMenuEditItems += `<div onclick="crashToTopStream(${crash.id});" data-moderator>Plaats bovenaan stream</div>`;

  return `
<div id="crash${crashDivId}" class="cardCrashDetails">
  <span class="postButtonArea" onclick="event.stopPropagation();">
    <span style="position: relative;"><span class="buttonEditPost bgTripleDots"  data-userid="${crash.userid}" onclick="showCrashMenu(event, '${crashDivId}');"></span></span>
    <div id="menuCrash${crashDivId}" class="buttonPopupMenu" onclick="event.preventDefault();">
      <div onclick="addArticleToCrash(${crash.id});">Artikel toevoegen</div>
      ${htmlMenuEditItems}
    </div>            
  </span>        

  ${htmlModeration}
   
  <div class="cardTop">
    <div style="width: 100%;">
      <div class="smallFont cardTitleSmall">${crash.date.pretty()} | ${titleSmall}</div>
      <div class="cardTitle">${escapeHtml(crash.title)}</div>
      <div>${htmlPersons}</div>
    </div>
    ${htmlTopIcons}
  </div>

  <div class="postText">${escapeHtml(crash.text)}</div>    
  
  ${htmlArticles}
  <div id="mapCrash"></div>
</div>`;
}

function getCrashTopIcons(crash, allowClick=false){
  let html = '';
  if (crash.unilateral)                  html += '<div class="iconSmall bgUnilateral" data-tippy-content="Eenzijdig ongeluk"></div>';
  if (crash.pet)                         html += '<div class="iconSmall bgPet"  data-tippy-content="Dier(en)"></div>';
  if (crash.trafficjam)                  html += '<div class="iconSmall bgTrafficJam"  data-tippy-content="File/Hinder"></div>';
  if (crash.longitude && crash.latitude) html += '<div class="iconSmall bgGeo" data-tippy-content="Locatie bekend"></div>';

  if (html){
    const htmlClick   = allowClick? '' : 'onclick="stopPropagation();"';
    html = `
    <div data-info="preventFullBorder">
      <div class="cardIcons" ${htmlClick}>
        <div class="flexRow" style="justify-content: flex-end">${html}</div>
      </div>
    </div>`;
  }
  return html;
}

function healthVisible(health){
  return [THealth.dead, THealth.injured].includes(health);
}

function getCrashButtonsHTML(crash, showAllHealth=true, allowClick=false) {
  function getGroupButtonHTML(button) {
    if (button.persons.length < 1) return '';
    const person1 = button.persons[0];
    const bgTransportation = transportationModeImage(person1.transportationmode);
    let tooltip            = transportationModeText(person1.transportationmode);
    let iconsGroup         = `<div class="iconMedium ${bgTransportation}" data-tippy-content="${tooltip}"></div>`;
    let htmlPersons        = '';

    for (const person of button.persons){
      let tooltip = 'Mens ' + person.id +
        '<br>Letsel: ' + healthText(person.health);
      if (person.child)          tooltip += '<br>Kind';
      if (person.underinfluence) tooltip += '<br>Onder invloed';
      if (person.hitrun)         tooltip += '<br>Doorrijden/vluchten';

      const showHealth = showAllHealth || healthVisible(person.health);
      let htmlPerson = '';
      if (showHealth)            htmlPerson += `<div class="iconMedium ${healthImage(person.health)}"></div>`;
      if (person.child)          htmlPerson += '<div class="iconMedium bgChild"></div>';
      if (person.underinfluence) htmlPerson += '<div class="iconMedium bgAlcohol"></div>';
      if (person.hitrun)         htmlPerson += '<div class="iconMedium bgHitRun"></div>';

      if (htmlPerson) htmlPersons += `<div class="crashButtonSub" data-tippy-content="${tooltip}">${htmlPerson}</div>`;
    }

    const clickClass = allowClick? '' : 'defaultCursor';
    return `<div class="crashButton ${ clickClass}">
  ${iconsGroup}
  ${htmlPersons}
</div>`;
  }

  const buttons = getCrashGUIButtons(crash);
  let html = '';
  for (const button of buttons) html += getGroupButtonHTML(button);
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

function selectArticle(articleID, smooth=false) {
  const div = document.getElementById('article' + articleID);
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
      block:    'start',
      behavior: 'smooth',
      inline:   'nearest'});

  } else scrollIntoViewIfNeeded(div);
}

function showNewCrashForm() {
  showEditCrashForm();
  showMapEdit();
}

function showEditCrashForm() {
  closeAllPopups();

  if (! user.loggedin){
     showLoginForm();
     return;
  }

  document.getElementById('editHeader').innerText       = 'Nieuw artikel en ongeluk toevoegen';
  document.getElementById('buttonSaveArticle').value    = 'Opslaan';
  document.getElementById('crashIDHidden').value        = '';
  document.getElementById('articleIDHidden').value      = '';

  document.getElementById('editArticleUrl').value       = '';
  document.getElementById('editArticleTitle').value     = '';
  document.getElementById('editArticleText').value      = '';
  document.getElementById('editArticleAllText').value   = '';
  document.getElementById('editArticleUrlImage').value  = '';
  document.getElementById('editArticleSiteName').value  = '';
  document.getElementById('editArticleDate').value      = '';

  document.getElementById('editCrashTitle').value       = '';
  document.getElementById('editCrashText').value        = '';
  document.getElementById('editCrashDate').value        = '';
  document.getElementById('editCrashLatitude').value    = '';
  document.getElementById('editCrashLongitude').value   = '';

  document.getElementById('editCrashUnilateral').classList.remove('buttonSelected');
  document.getElementById('editCrashPet').classList.remove('buttonSelected');
  document.getElementById('editCrashTrafficJam').classList.remove('buttonSelected');
  document.getElementById('editCrashTree').classList.remove('buttonSelected');

  editCrashPersons = [];
  refreshCrashPersonsGUI(editCrashPersons);

  document.querySelectorAll('[data-hideedit]').forEach(d => {d.style.display = 'inline-block';});

  document.getElementById('editCrashSection').style.display   = 'flex';
  document.getElementById('editArticleSection').style.display = 'flex';
  document.getElementById('formEditCrash').style.display      = 'flex';

  document.getElementById('editArticleUrl').focus();

  document.querySelectorAll('[data-readonlyhelper]').forEach(d => {d.readOnly = ! user.moderator;});
  document.querySelectorAll('[data-hidehelper]').forEach(d => {d.style.display = ! user.moderator? 'none' : 'flex';});
}

function addEditPersonButtons(){
  let htmlButtons = '';
  for (const key of Object.keys(TTransportationMode)){
    const transportationMode =  TTransportationMode[key];
    const bgClass            = transportationModeImage(transportationMode);
    const text               = transportationModeText(transportationMode);
    htmlButtons += `<span id="editPersonTransportationMode${key}" class="menuButton ${bgClass}" data-tippy-content="${text}" onclick="selectPersonTransportationMode(${transportationMode}, true);"></span>`;
  }
  document.getElementById('personTransportationButtons').innerHTML = htmlButtons;

  htmlButtons = '';
  for (const key of Object.keys(THealth)){
    const health =  THealth[key];
    const bgClass = healthImage(health);
    const text    = healthText(health);
    htmlButtons += `<span id="editPersonHealth${key}" class="menuButton ${bgClass}" data-tippy-content="${text}" onclick="selectPersonHealth(${health}, true);"></span>`;
  }
  document.getElementById('personHealthButtons').innerHTML = htmlButtons;
}

function showEditPersonForm(personID=null) {
  closeAllPopups();
  const person = getPersonFromID(personID);

  document.getElementById('editPersonHeader').innerText       = person? 'Mens bewerken' : 'Nieuw mens toevoegen';
  document.getElementById('personIDHidden').value             = person? person.id : '';
  document.getElementById('buttonDeletePerson').style.display = person? 'inline-flex' : 'none';

  selectPersonTransportationMode(person? person.transportationmode : null);
  selectPersonHealth(person? person.health : null);

  setMenuButton('editPersonChild',person? person.child : false);
  setMenuButton('editPersonUnderInfluence',person? person.underinfluence : false);
  setMenuButton('editPersonHitRun',person? person.hitrun : false);

  document.getElementById('formEditPerson').style.display = 'flex';
}

function selectPersonTransportationMode(transportationMode, toggle=false){
  selectPersonHealth(null);
  setMenuButton('editPersonChild', false);
  setMenuButton('editPersonUnderInfluence', false);
  setMenuButton('editPersonHitRun', false);

  for (const key of Object.keys(TTransportationMode)) {
    const buttonTransportationMode = TTransportationMode[key];
    const button = document.getElementById('editPersonTransportationMode' + key);
    if (buttonTransportationMode === transportationMode) {
      if (toggle === true) button.classList.toggle('buttonSelected');
      else button.classList.add('buttonSelected');
    }
    else button.classList.remove('buttonSelected');
  }
}

function getSelectedPersonTransportationMode(){
  for (const key of Object.keys(TTransportationMode)) {
    const buttonTransportationMode = TTransportationMode[key];
    const button = document.getElementById('editPersonTransportationMode' + key);
    if (button.classList.contains('buttonSelected')) return buttonTransportationMode;
  }
  return null;
}

function selectPersonHealth(health, toggle=false) {
  for (const key of Object.keys(THealth)) {
    const buttonHealth = THealth[key];
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
  document.getElementById('searchPersonHealthDead').classList.toggle('buttonSelectedBlue');
}

function selectSearchPersonInjured() {
  document.getElementById('searchPersonHealthInjured').classList.toggle('buttonSelectedBlue');
}

function selectSearchPersonChild() {
  document.getElementById('searchPersonChild').classList.toggle('buttonSelectedBlue');
}


function getSelectedPersonHealth(){
  for (const key of Object.keys(THealth)) {
    const buttonHealth = THealth[key];
    const button = document.getElementById('editPersonHealth' + key);
    if (button && button.classList.contains('buttonSelected')) return buttonHealth;
  }
  return null;
}

function closeEditPersonForm(){
  document.getElementById('formEditPerson').style.display = 'none';
}

function savePerson(stayOpen=false) {
  const selectedTransportationMode = getSelectedPersonTransportationMode();
  const selectedHealth             = getSelectedPersonHealth();
  if (selectedTransportationMode === null) {showError('Geen vervoertype geselecteerd', 3); return;}
  if (selectedHealth             === null) {showError('Geen letsel geselecteerd', 3); return;}

  const personID = parseInt(document.getElementById('personIDHidden').value);
  let person;

  function loadPersonFromGUI(person){
    person.transportationmode = selectedTransportationMode;
    person.health             = selectedHealth;
    person.child              = menuButtonSelected('editPersonChild');
    person.underinfluence     = menuButtonSelected('editPersonUnderInfluence');
    person.hitrun             = menuButtonSelected('editPersonHitRun');
  }

  if (personID){
    person = getPersonFromID(personID);
    loadPersonFromGUI(person);
  } else {
    person = {id: editCrashPersons.length + 1};
    loadPersonFromGUI(person);

    editCrashPersons.push(person);
  }

  refreshCrashPersonsGUI(editCrashPersons);

  if (stayOpen !== true) closeEditPersonForm();
  else showMessage('Mens opgeslagen', 0.5);
}

function deletePerson() {
  confirmMessage('Mens verwijderen?',
    function () {
      const personID      = parseInt(document.getElementById('personIDHidden').value);
      editCrashPersons = editCrashPersons.filter(person => person.id !== personID);
      refreshCrashPersonsGUI(editCrashPersons);
      closeEditPersonForm();
    });
}

function refreshCrashPersonsGUI(persons=[]) {
  let html = '';

  for (let person of persons){
    const iconTransportation = transportationModeIcon(person.transportationmode);
    const iconHealth         = healthIcon(person.health);
    let buttonsOptions = '';
    if (person.child)          buttonsOptions += '<div class="iconSmall bgChild" data-tippy-content="Kind"></div>';
    if (person.underinfluence) buttonsOptions += '<div class="iconSmall bgAlcohol" data-tippy-content="Onder invloed"></div>';
    if (person.hitrun)         buttonsOptions += '<div class="iconSmall bgHitRun" data-tippy-content="Doorrijden/vluchten"></div>';

    html += `<div class="editCrashPerson" onclick="showEditPersonForm(${person.id});">
${iconHealth} ${iconTransportation} ${buttonsOptions}
</div>
`;
  }

  document.getElementById('editCrashPersons').innerHTML = html;
  tippy('[data-tippy-content]');
}

function setNewArticleCrashFields(crashID){
  const crash = getCrashFromID(crashID);
  const crashDatetime = new Date(crash.date);

  // Shallow copy
  editCrashPersons = clone(crash.persons);

  document.getElementById('crashIDHidden').value      = crash.id;

  document.getElementById('editCrashTitle').value     = crash.title;
  document.getElementById('editCrashText').value      = crash.text;
  document.getElementById('editCrashDate').value      = dateToISO(crashDatetime);
  document.getElementById('editCrashLatitude').value  = crash.latitude;
  document.getElementById('editCrashLongitude').value = crash.longitude;

  selectButton('editCrashUnilateral',  crash.unilateral);
  selectButton('editCrashPet',         crash.pet);
  selectButton('editCrashTrafficJam',  crash.trafficjam);
  selectButton('editCrashTree',        crash.tree);

  refreshCrashPersonsGUI(crash.persons);

  showMapEdit(crash.latitude, crash.longitude);
}

function openArticleLink(event, articleID) {
  event.stopPropagation();
  const article = getArticleFromID(articleID);
  window.open(article.url,"article");
}

function toggleAllText(element, event, articleDivId, articleId){
  event.preventDefault();
  event.stopPropagation();

  toggleSelectionButton(element);

  const article = getArticleFromID(articleId);
  const textElement = document.getElementById('articleText' + articleDivId);
  if (element.classList.contains('buttonSelected')) {
    textElement.innerHTML = '⌛';
    getArticleText(articleId).then(text => textElement.innerHTML = formatText(text));
  } else textElement.innerHTML = formatText(article.text);
}

function editArticle(crashID, articleID) {
  showEditCrashForm(crashID);
  setNewArticleCrashFields(crashID);

  const article = getArticleFromID(articleID);

  document.getElementById('editHeader').innerText           = 'Artikel bewerken';
  document.getElementById('buttonSaveArticle').value        = 'Opslaan';

  document.getElementById('articleIDHidden').value          = article? article.id : '';

  document.getElementById('editArticleUrl').value           = article.url;
  document.getElementById('editArticleTitle').value         = article.title;
  document.getElementById('editArticleText').value          = article.text;
  document.getElementById('editArticleAllText').readonly    = true;
  document.getElementById('editArticleAllText').value       = '⌛';

  document.getElementById('editArticleUrlImage').value      = article.urlimage;
  document.getElementById('editArticleSiteName').value      = article.sitename;
  document.getElementById('editArticleDate').value          = dateToISO(article.publishedtime);

  document.getElementById('formEditCrash').style.display    = 'flex';
  document.getElementById('editCrashSection').style.display = 'none';

  const text = getArticleText(articleID).then(
    text => {
      document.getElementById('editArticleAllText').value    = text;
      document.getElementById('editArticleAllText').readonly = false;
    }
  );
}

function addArticleToCrash(crashID) {
  showEditCrashForm(crashID);
  setNewArticleCrashFields(crashID);

  document.getElementById('editHeader').innerText           = 'Artikel toevoegen';
  document.getElementById('editCrashSection').style.display = 'none';
}

function editCrash(crashID) {
  showEditCrashForm(crashID);
  setNewArticleCrashFields(crashID);

  document.getElementById('editHeader').innerText             = 'Ongeluk bewerken';
  document.getElementById('editArticleSection').style.display = 'none';

  document.querySelectorAll('[data-hideedit]').forEach(d => {d.style.display = 'none';});
}

async function crashToTopStream(crashID) {
  closeAllPopups();

  const url      = '/ajax.php?function=crashToStreamTop&id=' + crashID;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else window.location.reload();
}

async function getArticleText(articleId) {
  const url      = '/ajax.php?function=getArticleText&id=' + articleId;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else return response.text;
}

async function crashModerateOK(crash) {
  closeAllPopups();

  const url      = '/ajax.php?function=crashModerateOK&id=' + crash;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else if (response.ok){
    // Remove moderation div
    getCrashFromID(crash).awaitingmoderation = false;

    let divModeration = document.getElementById('crashModeration' + crash);
    if (divModeration) divModeration.remove();

    divModeration = document.getElementById('crashModerationdetails' + crash);
    if (divModeration) divModeration.remove();
  }
}

async function articleModerateOK(articleID) {
  closeAllPopups();

  const url      = '/ajax.php?function=articleModerateOK&id=' + articleID;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else if (response.ok){
    // Remove moderation div
    getArticleFromID(articleID).awaitingmoderation = false;

    let divModeration = document.getElementById('articleModeration' + articleID);
    if (divModeration) divModeration.remove();
    divModeration = document.getElementById('articleModerationdetails' + articleID);
    if (divModeration) divModeration.remove();
  }
}

function domainBlacklisted(url){
  let domainBlacklist = [
    {domain: 'assercourant.nl',   reason: 'Website staat foto embedding niet toe wegens buggy cookie firewall (Dec 2018).'},
    {domain: 'drimble.nl',        reason: 'Drimble is geen media website, maar een nieuws verzamelwebsite. Zoek de bron op de drimble.nl pagina en plaats die.'},
    {domain: 'onswestbrabant.nl', reason: 'Website staat vol met buggy tags (Dec 2018).'},
  ];
  return domainBlacklist.find(d => url.includes(d.domain));
}

function copyCrashInfoFromArticle(){
  document.getElementById('editCrashTitle').value = document.getElementById('editArticleTitle').value;
}

function copyCrashDateFromArticle(){
  document.getElementById('editCrashDate').value  = document.getElementById('editArticleDate').value;
}

async function getArticleMetaData() {
  function showMetaData(meta){
    document.getElementById('editArticleUrl').value      = meta.url;
    document.getElementById('editArticleTitle').value    = meta.title;
    document.getElementById('editArticleText').value     = meta.description;
    document.getElementById('editArticleUrlImage').value = meta.urlimage;
    document.getElementById('editArticleSiteName').value = meta.sitename;
    if (meta.published_time){
      try {
        const datetime = new Date(meta.published_time);
        document.getElementById('editArticleDate').value = dateToISO(datetime);
      } catch (e) {
        // Do nothing
      }
    }
    if (meta.title === '') showMessage('Tarantula heeft geen gegevens gevonden in de web pagina.', 30);
  }

  let urlArticle = document.getElementById('editArticleUrl').value.trim();
  if (! urlArticle) {
    showError('Geen artikel link (URL) ingevuld');
    return;
  }

  const domain = domainBlacklisted(urlArticle);
  if (domain) {
    showMessage(`Links van "${domain.domain}" kunnen niet worden toegevoegd. ${domain.reason}`, 30);
    return
  }

  const isNewArticle = document.getElementById('articleIDHidden').value === '';
  const url          = '/ajax.php?function=getPageMetaData';

  document.getElementById('spinnerMeta').style.display = 'flex';
  document.getElementById('tarantulaResults').innerHTML = '<img src="/images/spinner.svg" style="height: 30px;">';
  try {
    const response = await fetchFromServer(url, {url: urlArticle, newArticle: isNewArticle});

    if (response.error) showError(response.error);
    else {
      if (response.urlExists) showMessage(`Bericht is al toegevoegd aan database.<br><a href='/${response.urlExists.crashId}' style='text-decoration: underline;'>Klik hier.</a>`, 30);
      else showMetaData(response.media);

      document.getElementById('tarantulaResults').innerHTML = `Gevonden:<br>
Open Graph Facebook tags: ${response.tagcount.og}<br>
Twitter tags: ${response.tagcount.twitter}<br>
article tags: ${response.tagcount.article}<br>
itemprop tags: ${response.tagcount.itemprop}<br>
other tags: ${response.tagcount.other}
`;
    }
  } catch (error) {
    showError(error.message);
  } finally {
    setTimeout(()=>{document.getElementById('spinnerMeta').style.display = 'none';}, 1000);
  }
}

async function saveArticleCrash(){
  let crashEdited;
  let articleEdited;

  const saveArticle = document.getElementById('editArticleSection').style.display !== 'none';
  if (saveArticle){
    articleEdited = {
      id:       document.getElementById('articleIDHidden').value,
      url:      document.getElementById('editArticleUrl').value,
      sitename: document.getElementById('editArticleSiteName').value.trim(),
      title:    document.getElementById('editArticleTitle').value.trim(),
      text:     document.getElementById('editArticleText').value.trim(),
      urlimage: document.getElementById('editArticleUrlImage').value.trim(),
      date:     document.getElementById('editArticleDate').value,
      alltext:  document.getElementById('editArticleAllText').value.trim(),
    };
    if (articleEdited.id)  articleEdited.id  = parseInt(articleEdited.id);

    const domain = domainBlacklisted(articleEdited.url);
    if (domain) {
      showError(`Website ${domain.domain} kan niet worden toegevoegd. Reden: ${domain.reason}`);
      return
    }

    if (! articleEdited.url)                          {showError('Geen artikel link ingevuld'); return;}
    if (! articleEdited.title)                        {showError('Geen artikel titel ingevuld'); return;}
    if (! articleEdited.text)                         {showError('Geen artikel tekst ingevuld'); return;}
    if (articleEdited.urlimage.startsWith('http://')) {showError('Artikel foto link is onveilig. Begint met "http:". Probeer of de "https:" versie werkt. Laat anders dit veld leeg.'); return;}
    if (! articleEdited.sitename)                     {showError('Geen artikel mediabron ingevuld'); return;}
    if (! articleEdited.date)                         {showError('Geen artikel datum ingevuld'); return;}
  }

  let latitude  = document.getElementById('editCrashLatitude').value;
  let longitude = document.getElementById('editCrashLongitude').value;
  latitude  = latitude?  parseFloat(latitude)  : null;
  longitude = longitude? parseFloat(longitude) : null;

  // Both latitude and longitude need to be defined or they both are set to null
  if (! latitude)  longitude = null;
  if (! longitude) latitude  = null;
  crashEdited = {
    id:         document.getElementById('crashIDHidden').value,
    title:      document.getElementById('editCrashTitle').value,
    text:       document.getElementById('editCrashText').value,
    date:       document.getElementById('editCrashDate').value,
    latitude:   latitude,
    longitude:  longitude,
    persons:    editCrashPersons,
    unilateral: document.getElementById('editCrashUnilateral').classList.contains('buttonSelected'),
    pet:        document.getElementById('editCrashPet').classList.contains('buttonSelected'),
    trafficjam: document.getElementById('editCrashTrafficJam').classList.contains('buttonSelected'),
    tree:       document.getElementById('editCrashTree').classList.contains('buttonSelected'),
  };

  if (crashEdited.id) crashEdited.id = parseInt(crashEdited.id);

  const saveCrash = document.getElementById('editCrashSection').style.display !== 'none';
  if (saveCrash){
    if (saveArticle && (! user.moderator)) crashEdited.title = articleEdited.title;
    if (!crashEdited.title)               {showError('Geen ongeluk titel ingevuld'); return;}
    if (!crashEdited.date)                {showError('Geen ongeluk datum ingevuld'); return;}
    if (crashEdited.persons.length === 0) {showError('Geen personen toegevoegd'); return;}
  }

  const url = '/ajax.php?function=saveArticleCrash';
  const serverData = {
    article:      articleEdited,
    crash:        crashEdited,
    saveArticle:  saveArticle,
    saveCrash:    saveCrash,
  }
  const response = await fetchFromServer(url, serverData);

  if (response.error) {
    showError(response.error, 10);
  } else {
    const editingCrash = crashEdited.id !== '';

    // No reload only if editing crash. Other cases for now give problems and require a full page reload.
    if (editingCrash && pageIsCrashPage(pageType)) {
      if (saveCrash){
        // Save changes in crashes cache
        let i = crashes.findIndex(crash => {return crash.id === crashEdited.id});
        crashes[i].title      = crashEdited.title;
        crashes[i].text       = crashEdited.text;
        crashes[i].persons    = crashEdited.persons;
        crashes[i].date       = new Date(crashEdited.date);
        crashes[i].latitude   = crashEdited.latitude;
        crashes[i].longitude  = crashEdited.longitude;
        crashes[i].unilateral = crashEdited.unilateral;
        crashes[i].pet        = crashEdited.pet;
        crashes[i].tree       = crashEdited.tree;
        crashes[i].trafficjam = crashEdited.trafficjam;
      } else if (saveArticle) {
        let i = articles.findIndex(article => {return article.id === articleEdited.id});
        if (i >= 0){
          articles[i].url        = articleEdited.url;
          articles[i].sitename   = articleEdited.sitename;
          articles[i].title      = articleEdited.title;
          articles[i].text       = articleEdited.text;
          articles[i].urlimage   = articleEdited.urlimage;
          articles[i].date       = articleEdited.date;
          articles[i].hasalltext = articleEdited.alltext.length > 0;
        } else if (response.article){
          prepareArticleServerData(response.article);
          articles.push(response.article);
        }
      }

      const div = document.getElementById('crash' + crashEdited.id);
      if (div) div.outerHTML = getCrashListHTML(crashEdited.id);
      const divDetails = document.getElementById('crashdetails' + crashEdited.id);
      if (divDetails) {
        divDetails.outerHTML = getCrashDetailsHTML(crashEdited.id);
        showMapCrash(crashEdited.latitude, crashEdited.longitude);
      }
    } else {
      // New crash
      window.location.href = createCrashURL(response.crashId, crashEdited.title);
      let text = '';
      if (articleEdited) {
        text = articleEdited.id? 'Artikel opgeslagen' : 'Artikel toegevoegd';
      } else text = 'Ongeluk opgeslagen';
      showMessage(text, 1);
    }
    hideElement('formEditCrash');
  }
}

function showArticleMenu(event, articleDivId) {
  event.preventDefault();
  event.stopPropagation();

  const div = document.getElementById(`menuArticle${articleDivId}`);
  const menuVisible = div.style.display === 'block';
  closeAllPopups();
  if (! menuVisible) div.style.display = 'block';
}

function pageIsCrashList(){
  return [PageType.recent, PageType.stream, PageType.mosaic, PageType.deCorrespondent, PageType.moderations, PageType.childDeaths, PageType.map].includes(pageType);
}

function pageIsCrashPage(){
  return (pageType === PageType.crash) || pageIsCrashList();
}

function showCrashMenu(event, crashDivID) {
  event.preventDefault();
  event.stopPropagation();

  const div = document.getElementById(`menuCrash${crashDivID}`);
  const menuVisible = div.style.display === 'block';
  closeAllPopups();
  if (! menuVisible) div.style.display = 'block';
}

function getCrashFromID(id){
  return crashes.find(crash => crash.id === id);
}

function getPersonFromID(id){
  return editCrashPersons.find(person => person.id === id);
}

function getArticleFromID(id){
  return articles.find(article => article.id === id);
}

function getCrashArticles(crashID, articles){
  let list = articles.filter(article => article.accidentid === crashID);

  // Sort on publication time
  list.sort(function(a, b) {return b.publishedtime - a.publishedtime;});
  return list;
}

async function deleteArticleDirect(articleID) {
  try {
    const url      = '/ajax.php?function=deleteArticle&id=' + articleID;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      // Remove article from articles array
      articles = articles.filter(a => a.id !== articleID);

      // Delete the GUI elements
      deleteElement('article' + articleID);
      deleteElement('articledetails' + articleID);

      showMessage('Artikel verwijderd');
    }
  } catch (error) {
    showError(error.message);
  }
}

async function deleteCrashDirect(crashID) {
  try {
    const url      = '/ajax.php?function=deleteCrash&id=' + crashID;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      // Remove crash from crashes array
      crashes = crashes.filter(crash => crash.id !== crashID);

      // Delete the GUI element
      deleteElement('crash' + crashID);
      deleteElement('crashdetails' + crashID);

      showMessage('Ongeluk verwijderd');
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
  const article = getArticleFromID(id);

  confirmMessage(`Artikel "${article.title.substr(0, 100)}" verwijderen?`,
    function (){deleteArticleDirect(id)},
    'Verwijder artikel', null, true);
}

function deleteCrash(id) {
  closeAllPopups();
  const crash = getCrashFromID(id);

  confirmMessage(`Ongeluk "${crash.title.substr(0, 100)}" verwijderen?`,
    function (){deleteCrashDirect(id)},
    'Verwijder ongeluk', null, true);
}

function crashRowHTML(crash, isSearch=false){

  function innerHTML(crash, allArticles) {
    const htmlPersons = getCrashButtonsHTML(crash, false);

    const crashArticles = getCrashArticles(crash.id, allArticles);
    const img = (crashArticles.length > 0)? `<img class="thumbnail" src="${crashArticles[0].urlimage}">` : '';
    return `
  <div class="flexRow" style="justify-content: space-between;">
    <div style="padding: 3px;">
      ${crash.title}
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
  const crash = getCrashFromID(id);

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
    const crash      = getCrashFromID(crashID);

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
        getUser:  !user,
        text:     document.getElementById('mergeCrashSearch').value.trim().toLowerCase(),
        period:   'custom',
        dateFrom: dateToISO(dateFrom),
        dateTo:   dateToISO(dateTo),
      },
    };

    const url      = '/ajax.php?function=loadCrashes';
    const response = await fetchFromServer(url, serverData);

    if (response.error) showError(response.error);
    else if (response.ok){
      prepareCrashServerData(response);
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
  const toID   = parseInt(document.getElementById('mergeToCrashIDHidden').value);
  if (! toID) showError('Geen samenvoeg crash geselecteerd');

  const crashFrom = getCrashFromID(parseInt(fromID));
  const crashTo   = crashesFound.find(crash => crash.id === toID);

  async function mergeCrashesOnServer(fromID, toID){
    const url      = `/ajax.php?function=mergeCrashes&idFrom=${fromID}&idTo=${toID}`;
    const response = await fetchFromServer(url);

    if (response.error) showError(response.error);
    else {
      articles.forEach(article => {if (article.accidentid === fromID) article.accidentid = toID;});
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

  confirmMessage(`Ongeluk <br>#${crashFrom.id} ${crashFrom.title}<br><br>samenvoegen met<br>#${crashTo.id} ${crashTo.title}?`,
    function () {
      mergeCrashesOnServer(fromID, toID);
    }, 'Ja, voeg samen');
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
  const crash = crashByID(crashId);

  // Show crash overlay
  const divCrash = document.getElementById('formCrash');
  divCrash.style.display = 'flex';
  divCrash.scrollTop = 0;

  document.getElementById('crashDetails').innerHTML = getCrashDetailsHTML(crashId);

  document.body.style.overflow = 'hidden';

  showMapCrash(crash.latitude, crash.longitude);
  tippy('#crashDetails [data-tippy-content]');

  // Change browser url
  const url = createCrashURL(crash.id, crash.title);
  if (addToHistory) window.history.pushState({lastCrashId: crash.id}, crash.title, url);

  // Firefox bug workaround: Firefox selects all text in the popup which is what we do not want.
  window.getSelection().removeAllRanges();
}

function closeCrashDetails(popHistory=true) {
  if (event) event.stopPropagation();

  document.body.style.overflow = 'auto';
  document.getElementById('crashDetails').innerHTML = '';
  document.getElementById('formCrash').style.display = 'none';
  if (popHistory) window.history.back();
}

function searchVisible(){
  return document.body.classList.contains('searchBody');
}

function toggleSearchBar() {
  document.body.classList.toggle('searchBody');
  if (searchVisible()) document.getElementById('searchText').focus();
}

function startSearchKey(event) {
  if (event.key === 'Enter') startSearch();
}

function toggleCheckOptions(event, id) {
  if (event) event.stopPropagation();
  let divOptions = document.getElementById('search' + id);
  let divArrow   = document.getElementById('arrow' + id);
  if (divOptions.style.display === 'block'){
    divOptions.style.display = 'none';
    divArrow.classList.remove('inputArrowDownOpen');
  } else {
    divOptions.style.display = 'block';
    divArrow.classList.add('inputArrowDownOpen');
  }
}

function initSearchBar(){
  let html = '';
  for (const key of Object.keys(TTransportationMode)){
    const transportationMode     =  TTransportationMode[key];
    const id                     = 'tm' + transportationMode;
    const text                   = transportationModeText(transportationMode);
    const iconTransportationMode = transportationModeImage(transportationMode);
    html +=
`<div id="${id}" class="optionCheckImage" onclick="searchPersonClick(${transportationMode});">
  <span class="checkbox"></span>
  <div class="iconMedium ${iconTransportationMode}" data-tippy-content="${text}"></div>
  <span id="searchDeadTm${transportationMode}" class="searchIcon bgDead" data-tippy-content="Dood" onclick="searchPersonOptionClick(event, 'Dead', ${transportationMode});"></span>      
  <span id="searchInjuredTm${transportationMode}" class="searchIcon bgInjured" data-tippy-content="Gewond" onclick="searchPersonOptionClick(event, 'Injured', ${transportationMode});"></span>      
  <span id="searchRestrictedTm${transportationMode}" data-personRestricted class="searchIcon ${iconTransportationMode} mirrorHorizontally" data-tippy-content="Tegenpartij was ook ${text}" onclick="searchPersonOptionClick(event, 'Restricted', ${transportationMode});"></span>      
  <span id="searchUnilateralTm${transportationMode}" data-personUnilateral class="searchIcon bgUnilateral" data-tippy-content="Eenzijdig ongeluk" onclick="searchPersonOptionClick(event, 'Unilateral', ${transportationMode});"></span>      
</div>`;
  }

  document.getElementById('searchSearchPersons').innerHTML = html;
}

function setCustomRangeVisibility() {
  const custom = document.getElementById('searchPeriod').value === 'custom';
  document.getElementById('searchDateFrom').style.display = custom? 'block' : 'none';
  document.getElementById('searchDateTo').style.display   = custom? 'block' : 'none';
}

function setCustomFilterVisibility() {
  const filterPeriod = document.getElementById('filterStatsPeriod');
  if (filterPeriod) {
    const custom = filterPeriod.value === 'custom';
    document.getElementById('filterStatsDateFrom').style.display = custom? 'inline-flex' : 'none';
    document.getElementById('filterStatsDateTo').style.display   = custom? 'inline-flex' : 'none';
  }
}

function toggleSearchPersons(event) {
  toggleCheckOptions(event, 'SearchPersons');
}

function searchPersonClick(transportationMode) {
  document.getElementById('tm' + transportationMode).classList.toggle('itemSelected');
  unselectSingleOnlyOptionsIfMultiplePersonsSelected();
  updateTransportationModeFilterInput();
}

function searchPersonSelect(transportationMode) {
  document.getElementById('tm' + transportationMode).classList.add('itemSelected');
  unselectSingleOnlyOptionsIfMultiplePersonsSelected();
  updateTransportationModeFilterInput();
}

function unselectSingleOnlyOptionsIfMultiplePersonsSelected(){
  if (document.querySelectorAll('#searchSearchPersons .itemSelected').length > 1) {
    const singleOnlyButtons = document.querySelectorAll('[data-personRestricted], [data-personUnilateral]');
    singleOnlyButtons.forEach(e => e.classList.remove('inputSelectButtonSelected'));
  }
}

function searchPersonOptionClick(event, buttonType, transportationMode) {
  event.stopPropagation();

  const optionButton = document.getElementById(`search${buttonType}Tm` + transportationMode);
  optionButton.classList.toggle('inputSelectButtonSelected');

  const optionSelected = optionButton.classList.contains('inputSelectButtonSelected');
  if (optionSelected){
    // Unselect other person selects if restricted or unilateral option is selected.
    if ((buttonType === 'Restricted') || (buttonType === 'Unilateral')){
      const thisPersonSelect = document.getElementById('tm' + transportationMode);
      const allPersonSelects = document.querySelectorAll('#searchSearchPersons .itemSelected');
      if (allPersonSelects.length > 1) allPersonSelects.forEach(p => {if (p !== thisPersonSelect) p.classList.remove('itemSelected');});
    }

    // Unselect unilateral if restricted selected
    if (buttonType === 'Restricted') document.querySelectorAll('[data-personUnilateral]').forEach(e => e.classList.remove('inputSelectButtonSelected'));

    // Unselect restricted if unilateral selected
    if (buttonType === 'Unilateral') document.querySelectorAll('[data-personRestricted]').forEach(e => e.classList.remove('inputSelectButtonSelected'));
  }

  searchPersonSelect(transportationMode);
  updateTransportationModeFilterInput();
}

function updateTransportationModeFilterInput(){
  let html = '';

  for (const key of Object.keys(TTransportationMode)){
    const transportationMode =  TTransportationMode[key];
    const transportationText =  transportationModeText(transportationMode);
    const elementId          = 'tm' + transportationMode;
    const element            = document.getElementById(elementId);
    if (element.classList.contains('itemSelected')) {
      let icon = transportationModeImage(transportationMode);
      html += `<span class="inputIconGroup"><span class="searchDisplayIcon ${icon}" data-tippy-content="${transportationText}"></span>`;

      const deadSelected = document.getElementById('searchDeadTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (deadSelected){
        let icon = healthImage(THealth.dead);
        html += `<span class="searchDisplayIcon ${icon}" data-tippy-content="Dood"></span>`;
      }

      const injuredSelected = document.getElementById('searchInjuredTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (injuredSelected){
        let icon = healthImage(THealth.injured);
        html += `<span class="searchDisplayIcon ${icon}" data-tippy-content="Gewond"></span>`;
      }

      const restrictedSelected = document.getElementById('searchRestrictedTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (restrictedSelected){
        html += `<span class="searchDisplayIcon  ${icon} mirrorHorizontally" data-tippy-content="Tegenpartij was ook ${transportationText}"></span>`;
      }

      const unilateralSelected = document.getElementById('searchUnilateralTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (unilateralSelected){
        html += `<span class="searchDisplayIcon bgUnilateral" data-tippy-content="Eenzijdig ongeluk"></span>`;
      }

      html += '</span>';
    }
  }

  // Show placeholder text if no persons selected
  if (html === '') html = 'Mensen';
  document.getElementById('inputSearchPersons').innerHTML = html;
  tippy('#inputSearchPersons [data-tippy-content]');
}

function getPersonsFromFilter(){
  let persons = [];

  for (const key of Object.keys(TTransportationMode)){
    const transportationMode =  TTransportationMode[key];
    const buttonPerson       = document.getElementById('tm' + transportationMode);
    if (buttonPerson.classList.contains('itemSelected')) {
      let person = transportationMode;

      const deadSelected = document.getElementById('searchDeadTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (deadSelected) person += 'd';

      const injuredSelected = document.getElementById('searchInjuredTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (injuredSelected) person += 'i';

      const restrictedSelected = document.getElementById('searchRestrictedTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (restrictedSelected) person += 'r';

      const unilateralSelected = document.getElementById('searchUnilateralTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (unilateralSelected) person += 'u';

      persons.push(person);
    }
  }

  return persons;
}

function setPersonsFilter(personsCommaString){
  let persons = personsCommaString.split(',').map(p => {
    return {
      transportationMode: parseInt(p),
      dead:               p.includes('d'),
      injured:            p.includes('i'),
      restricted:         p.includes('r'),
      unilateral:         p.includes('u'),
    };
  });

  for (const key of Object.keys(TTransportationMode)){
    const transportationMode =  TTransportationMode[key];
    const element            = document.getElementById('tm'                 + transportationMode);
    const buttonDead         = document.getElementById('searchDeadTm'       + transportationMode);
    const buttonInjured      = document.getElementById('searchInjuredTm'    + transportationMode);
    const buttonRestricted   = document.getElementById('searchRestrictedTm' + transportationMode);
    const buttonUnilateral   = document.getElementById('searchUnilateralTm' + transportationMode);

    const person = persons.find(p => p.transportationMode === transportationMode);
    if (person){
      element.classList.add('itemSelected');
      if (person.dead)       buttonDead.classList.add('inputSelectButtonSelected');
      if (person.injured)    buttonInjured.classList.add('inputSelectButtonSelected');
      if (person.restricted) buttonRestricted.classList.add('inputSelectButtonSelected');
      if (person.unilateral) buttonUnilateral.classList.add('inputSelectButtonSelected');
    } else element.classList.remove('itemSelected');
  }
  updateTransportationModeFilterInput();
}

function startSearch() {
  updateBrowserUrl(true);
  reloadCrashes();
}

function updateBrowserUrl(pushState=false){
  const searchText          = document.getElementById('searchText').value.trim().toLowerCase();
  const searchPeriod        = document.getElementById('searchPeriod').value;
  const searchDateFrom      = document.getElementById('searchDateFrom').value;
  const searchDateTo        = document.getElementById('searchDateTo').value;
  const searchSiteName      = document.getElementById('searchSiteName').value.trim().toLowerCase();
  const searchHealthDead    = document.getElementById('searchPersonHealthDead').classList.contains('buttonSelectedBlue');
  const searchHealthInjured = document.getElementById('searchPersonHealthInjured').classList.contains('buttonSelectedBlue');
  const searchChild         = document.getElementById('searchPersonChild').classList.contains('buttonSelectedBlue');
  const searchPersons       = getPersonsFromFilter();

  const url = new URL(location.origin);

  if      (pageType === PageType.deCorrespondent) url.pathname = '/decorrespondent';
  else if (pageType === PageType.stream)          url.pathname = '/stream';
  else if (pageType === PageType.mosaic)          url.pathname = '/mozaiek';
  else if (pageType === PageType.map)             url.pathname = '/map';

  if (pageType === PageType.map) {
    const center = mapMain.getCenter();
    url.searchParams.set('lat', center.lat);
    url.searchParams.set('lng', center.lng);
    url.searchParams.set('zoom', mapMain.getZoom());
  }

  if (searchText)     url.searchParams.set('search', searchText);
  if (searchSiteName) url.searchParams.set('siteName', searchSiteName);
  if (searchPeriod){
    url.searchParams.set('period', searchPeriod);

    if (searchDateFrom) url.searchParams.set('date_from', searchDateFrom);
    if (searchDateTo)   url.searchParams.set('date_to', searchDateTo);
  }
  if (searchHealthDead)         url.searchParams.set('hd', 1);
  if (searchHealthInjured)      url.searchParams.set('hi', 1);
  if (searchChild)              url.searchParams.set('child', 1);
  if (searchPersons.length > 0) url.searchParams.set('persons', searchPersons.join());

  if (pushState) window.history.pushState(null, null, url.toString());
  else           window.history.replaceState(null, null, url);

}

function downloadData() {
  async function doDownload(period=''){
    spinnerLoad.style.display = 'block';
    try {
      const url      = '/admin/exportdata.php?function=downloadData&period=all&format=zjson';
      const response = await fetchFromServer(url);

      const urlFile = '/admin/' + response.filename;
      download(urlFile, response.filename);
    } finally {
      spinnerLoad.style.display = 'none';
    }
  }

  confirmMessage('Data van alle ongelukken exporteren?', doDownload, 'Download');
}

function downloadCorrespondentData() {
  async function doDownload(){
    const spinner = document.getElementById('spinnerDownloadDeCorrespondentData');
    spinner.style.display = 'block';
    try {
      let url        = '/admin/exportdata.php?function=downloadCorrespondentWeekData';
      const response = await fetchFromServer(url);

      url = '/admin/' + response.filename;
      download(url, response.filename);
    } finally {
      spinner.style.display = 'none';
    }
  }

  confirmMessage('Ongelukken uit De Correspondent week exporteren in *.csv formaat?', doDownload, 'Download');
}

function downloadCorrespondentDataArticles() {
  async function doDownload(){
    const spinner = document.getElementById('spinnerDownloadDeCorrespondentData');
    spinner.style.display = 'block';
    try {
      let url        = '/admin/exportdata.php?function=downloadCorrespondentWeekArticles';
      const response = fetchFromServer(url);

      url = '/admin/' + response.filename;
      download(url, response.filename);
    } finally {
      spinner.style.display = 'none';
    }
  }

  confirmMessage('Artikels uit De Correspondent week exporteren in *.csv formaat?', doDownload, 'Download');
}

function showMapEdit(latitude, longitude) {

  function saveMarkerPosition(lngLat){
    document.getElementById('editCrashLatitude').value  = lngLat.lat.toFixed(6);
    document.getElementById('editCrashLongitude').value = lngLat.lng.toFixed(6);
  }

  function setCrashMarker(latitude, longitude){
    if (mapCrash) mapCrash.setLngLat(new mapboxgl.LngLat(longitude, latitude));
    else {
      const markerElement     = document.createElement('div');
      markerElement.innerHTML = `<img class="mapMarker" src="/images/pin.svg" alt="marker">`;
      markerElement.id        = 'marker';
      markerElement.addEventListener('click', (e) => {
        e.stopPropagation();
        confirmMessage(`Locatie verwijderen?`, () => {
          document.getElementById('editCrashLatitude').value  = '';
          document.getElementById('editCrashLongitude').value = '';
          deleteCrashMarker();
        });
       });

      deleteCrashMarker();
      markerEdit = new mapboxgl.Marker(markerElement, {anchor: 'bottom', draggable: true})
        .setLngLat([longitude, latitude])
        .addTo(mapEdit)
        .on('dragend', function(e) {
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

  const latitudeNL  = 52.16;
  const longitudeNL = 5.41;
  const zoomLevel   = 6;
  let showMarker = true;
  if (! latitude || ! longitude) {
    latitude   = latitudeNL;
    longitude  = longitudeNL;
    showMarker = false;
    deleteCrashMarker();
  }

  if (! mapEdit){
    mapboxgl.accessToken = mapboxKey;
    mapEdit = new mapboxgl.Map({
      container: 'mapEdit',
      style:     'mapbox://styles/mapbox/streets-v9',
      center:    [longitude, latitude],
      zoom:      zoomLevel,
    }).addControl(
      new MapboxGeocoder({
        accessToken: mapboxgl.accessToken,
        mapboxgl:    mapboxgl,
        clearOnBlur: true,
      })
    ).on('click', (e) => {
      saveMarkerPosition(e.lngLat);
      setCrashMarker(e.lngLat.lat, e.lngLat.lng);
    });

  } else {
    mapEdit.setCenter([longitude, latitude]);
    mapEdit.setZoom(zoomLevel);
  }

  if (showMarker) setCrashMarker(latitude, longitude);
}

function showMapCrash(latitude, longitude) {
  if (! latitude || ! longitude) {
    document.getElementById('mapCrash').style.display = 'none';
    return;
  }

  let zoomLevel = 6;

  mapboxgl.accessToken = mapboxKey;
  const mapCrash = new mapboxgl.Map({
    container: 'mapCrash',
    style:     'mapbox://styles/mapbox/streets-v9',
    center:    [longitude, latitude],
    zoom:      zoomLevel,
  });

  const markerElement = document.createElement('div');
  markerElement.innerHTML = `<img class="mapMarker" src="/images/pin.svg" alt="marker">`;

  const mapMarker = new mapboxgl.Marker(markerElement, {anchor: 'bottom'})
    .setLngLat([longitude, latitude])
    .addTo(mapCrash);

  document.getElementById('mapCrash').style.display = 'block';
}

function togglePageInfo(){
  const element = document.getElementById('pageInfo');

  element.style.display = (element.style.display === 'block')? 'none' : 'block';
}