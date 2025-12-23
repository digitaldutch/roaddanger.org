class Filter {

  #filterFunction;
  constructor(pageType) {
    this.#resetFilters();

    const isStatisticsPage = [
      PageType.statisticsCrashPartners,
      PageType.statisticsTransportationModes
    ].includes(pageType);

    this.#filterFunction = isStatisticsPage ? searchStatistics : searchCrashes;

    // Attach event handlers
    this.onEnterKey = this.#onEnterKey.bind(this);
    this.onFilter = this.#doFilter.bind(this);
    document.getElementById('filterButton').addEventListener('click', this.onFilter);
    document.getElementById('searchText').addEventListener('keydown', this.onEnterKey);
    document.getElementById('searchSiteName').addEventListener('keydown', this.onEnterKey);
    document.getElementById('searchUserId').addEventListener('keydown', this.onEnterKey);
  }

  #onEnterKey(event) {
    if (event.key !== 'Enter') return;

    this.#doFilter();
  }

  #doFilter() {
    this.#filterFunction();
    this.#updateStatusBar();
  }

  loadFromUrl() {
    this.#resetFilters();

    const url = new URL(location.href);

    this.filters.country = user.countryid;
    setFilterCountry(this.filters.country);

    this.filters.healthDead = url.searchParams.get('hd')? parseInt(url.searchParams.get('hd')) : 0;
    this.filters.healthInjured = url.searchParams.get('hi')? parseInt(url.searchParams.get('hi')) : 0;

    this.filters.child = url.searchParams.get('child')? parseInt(url.searchParams.get('child')) : 0;
    this.filters.text = url.searchParams.get('search') ?? '';
    this.filters.period = url.searchParams.get('period') ?? 'all';
    this.filters.dateFrom = url.searchParams.get('date_from') ?? null;
    this.filters.dateTo = url.searchParams.get('date_to') ?? null;
    this.filters.siteName = url.searchParams.get('siteName') ?? [];
    this.filters.userId = url.searchParams.get('user_id') ?? null;

    const personsParam = url.searchParams.get('persons');
    this.filters.persons = personsParam ? personsParam.split(',') : [];

    this.setToGUI();
  }

  addSearchParams(url) {
    this.getFromGUI();

    if (this.filters.healthDead) url.searchParams.set('hd', 1);
    if (this.filters.healthInjured) url.searchParams.set('hi', 1);
    if (this.filters.child) url.searchParams.set('child', 1);

    if (this.filters.text) url.searchParams.set('search', this.filters.text);
    if (this.filters.siteName) url.searchParams.set('siteName', this.filters.siteName);
    if (this.filters.userId) url.searchParams.set('user_id', this.filters.userId);

    if (this.filters.period) {
      url.searchParams.set('period', this.filters.period);

      if (this.filters.dateFrom) url.searchParams.set('date_from', this.filters.dateFrom);
      if (this.filters.dateTo) url.searchParams.set('date_to', this.filters.dateTo);
    }

    if (this.filters.persons.length > 0) url.searchParams.set('persons', this.filters.persons.join());
  }

  #resetFilters() {
    this.filters = {
      country: '',

      healthDead: 0,
      healthInjured: 0,
      child: 0,

      text: '',
      period: 'all',
      dateFrom: null,
      dateTo: null,
      persons: [],
      siteName: '',
      userId: '',
    }
  }

  clearGUI() {
    this.#resetFilters();

    this.setToGUI();

    this.#doFilter();
  }

  setToGUI() {
    if (! document.getElementById('filterBar')) return;

    function setSearchButton(id, on) {
      const button = document.getElementById(id);
      if (button) {
        if (on) button.classList.add('menuButtonSelected')
        else button.classList.remove('menuButtonSelected');
      }
    }

    function setFilterValue(id, value) {
      const element = document.getElementById(id);
      if (element) {
        element.value = value;
      }
    }

    function setFilterDataValue(id, value) {
      const element = document.getElementById(id);
      if (element && value) element.dataset.value = value;
    }

    setFilterDataValue('filterCountry', this.filters.country);

    setSearchButton('searchPersonHealthDead', this.filters.healthDead);
    setSearchButton('searchPersonHealthInjured', this.filters.healthInjured);
    setSearchButton('searchPersonChild', this.filters.child);

    setFilterValue('searchText', this.filters.text);
    setFilterValue('searchPeriod', this.filters.period);
    setFilterValue('searchDateFrom', this.filters.dateFrom);
    setFilterValue('searchDateTo', this.filters.dateTo);
    setFilterValue('searchSiteName', this.filters.siteName);
    setFilterValue('searchUserId', this.filters.userId);

    setPersonsFilter(this.filters.persons);

    this.setFilterFieldsVisibility();

    this.#updateStatusBar();
  }

  setFilterFieldsVisibility() {
    const elPeriod = document.getElementById('searchPeriod');
    if (elPeriod) {
      const elFrom = document.getElementById('searchDateFrom');
      const elTo = document.getElementById('searchDateTo');

      const custom = elPeriod.value === 'custom';

      if (custom) {
        elFrom.classList.add('active');
        elTo.classList.add('active');
      } else {
        elFrom.classList.remove('active');
        elTo.classList.remove('active');
      }
    }

    const elUserId = document.getElementById('searchUserId');
    if (elUserId.value) elUserId.classList.add('active');
  }

  getFromGUI() {
    if (filterBarOpen()) {
      const buttonDead = document.getElementById('searchPersonHealthDead');
      const buttonInjured = document.getElementById('searchPersonHealthInjured');
      const searchSiteName = document.getElementById('searchSiteName');
      const searchUserId = document.getElementById('searchUserId');

      this.#resetFilters();

      this.filters = {
        healthDead: buttonDead && buttonDead.classList.contains('menuButtonSelected')? 1 : 0,
        healthInjured: buttonInjured && buttonInjured.classList.contains('menuButtonSelected')? 1 : 0,
        child: document.getElementById('searchPersonChild').classList.contains('menuButtonSelected')? 1 : 0,

        text: document.getElementById('searchText').value.trim().toLowerCase(),
        period: document.getElementById('searchPeriod').value,
        persons: getPersonsFromFilter(),
        siteName: (searchSiteName && searchSiteName.value.trim().toLowerCase()) ?? '',
        userId: (searchUserId && (searchUserId.style.display !== 'none') && searchUserId.value) ?? '',
      }

      if (this.filters.period === 'custom') {
        this.filters.dateFrom = document.getElementById('searchDateFrom').value;
        this.filters.dateTo = document.getElementById('searchDateTo').value;
      }

    } else this.#resetFilters();

    const filterCountry = document.getElementById('filterCountry');
    if (filterCountry) this.filters.country = filterCountry.dataset.value;

    return this.filters;
  }

  #updateStatusBar() {
    const filterStatus = document.getElementById('filterStatus');
    if (! filterStatus) return;

    const textClearFilters = translate('Clear_filters');
    let html = '';

    this.getFromGUI();

    const activeFilters = new Map;

    if (this.filters.healthDead) activeFilters.set("dead", {label: translate('Dead_(adjective)')});

    if (this.filters.healthInjured) activeFilters.set("injured", {label: translate('Injured')});

    if (this.filters.child) activeFilters.set("child", {label: translate('Child')});

    if (this.filters.text.trim()) activeFilters.set("text", {label: this.filters.text});

    if (this.filters.period && (this.filters.period !== 'all')) {
      const elPeriod = document.getElementById('searchPeriod');
      const periodLabel = elPeriod.options[elPeriod.selectedIndex].text;
      activeFilters.set("period", {label: periodLabel});
    }

    if (this.filters.persons.length > 0) {
      const html = getTransportationModeFilterHtml(true);
      activeFilters.set("persons", {label: html});
    }

    if (this.filters.siteName.trim()) activeFilters.set("siteName", {label: this.filters.siteName});

    if (this.filters.userId) activeFilters.set("userId", {label: translate('Human') + ' Id ' + this.filters.userId});

    if (activeFilters.size > 0) {
      for (const [key, filter] of activeFilters) {
        html += `<div class="filterStatusItem">${filter.label}<button onclick="filter.removeFilter('${key}');"></button></div>`;
      }

      html += `<button class="button buttonImportant buttonMobileSmall" onclick="filter.clearGUI()">${textClearFilters}</button>`;
      filterStatus.classList.add('active');
    } else {
      html = '';
      filterStatus.classList.remove('active');
    }

    filterStatus.innerHTML = html;
  }

  removeFilter(key) {

    switch (key) {
      case 'dead':
        this.filters.healthDead = 0;
        break;

      case 'injured':
        this.filters.healthInjured = 0;
        break;

      case 'child':
        this.filters.child = 0;
        break;

      case 'text':
        this.filters.text = '';
        break;

      case 'period':
        this.filters.period = 'all';
        break;

      case 'persons':
        this.filters.persons = [];
        break;

      case 'siteName':
        this.filters.siteName = '';
        break;

      case 'userId':
        this.filters.userId = '';
        break;
    }

    this.setToGUI();

    this.#filterFunction();
  }

}