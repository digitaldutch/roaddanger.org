let user;
let observerSpinner;
let tableData = [];
let selectedTableData = [];

let xTouchDown      = null;
let yTouchDown      = null;
let TouchDown       = Object.freeze({none:0, openNavigation:1, closeNavigation:2});
let touchDownAction = TouchDown.none;

const mapboxKey     = 'pk.eyJ1IjoiamFuZGVyayIsImEiOiJjazI4dTVzNW8zOWw4M2NtdnRhMGs4dDc1In0.Cxw10toXdLoC1eqVaTn1RQ';
const websiteTitle  = 'Roaddanger.org';

// Enumerated types
const UserPermission = Object.freeze({newUser: 0, admin: 1, moderator: 2});
const TransportationMode = Object.freeze({
  unknown: 0, pedestrian: 1, bicycle: 2, motorScooter: 3, motorcycle: 4, car: 5, taxi: 6, emergencyVehicle: 7, deliveryVan: 8,  tractor: 9,
  bus: 10, tram: 11, truck: 12, train: 13, wheelchair: 14, mopedCar: 15, scooter: 16});
const Health = Object.freeze({unknown: 0, unharmed: 1, injured: 2, dead: 3});
const StreamTopType = Object.freeze({unknown: 0, edited: 1, articleAdded: 2, placedOnTop: 3});
const QuestionnaireType = Object.freeze({standard: 0, bechdel: 1});
const QuestionAnswer = Object.freeze({no: 0, yes: 1, notDeterminable: 2});

if (!Date.prototype.addDays) {
  Date.prototype.addDays = function(days) {
    if (days === 0) return this;
    let newDate = new Date(this.valueOf());
    newDate.setDate(newDate.getDate() + parseInt(days));
    return newDate;
  }
}

if (!Date.prototype.pretty) {
  Date.prototype.pretty = function() {
    return this.toLocaleDateString('nl', {year: 'numeric', month: 'long', day: 'numeric' });
  }
}

Array.prototype.move = function(from, to) {
  // https://stackoverflow.com/a/7180095/63849
  this.splice(to, 0, this.splice(from, 1)[0]);
};

// Array Remove
Array.prototype.remove = function(from, to) {
  let rest = this.slice((to || from) + 1 || this.length);
  this.length = from < 0 ? this.length + from : from;
  return this.push.apply(this, rest);
};

async function fetchFromServer(url, data={}, parseJSON=true){
  const optionsFetch = {
    method:      'POST',
    body:        JSON.stringify(data),
    headers:     {'Content-Type': 'application/json', 'Cache': 'no-cache'},
    credentials: 'same-origin',
  };

  const response = await fetch(url, optionsFetch);
  const responseText = await response.text();
  if (! responseText) throw new Error('Internal error: No response from server');

  return parseJSON? JSON.parse(responseText) : responseText;
}

function isInt(value) {
  let x;
  if (isNaN(value)) {
    return false;
  }
  x = parseFloat(value);
  return (x | 0) === x;
}

function dateFromISO(datetimeISO){
  if (! datetimeISO) return null;
  datetimeISO = datetimeISO.replace(' ', 'T');
  return new Date(datetimeISO)
}

function addLeadingZero(n){
  return n<10? '0'+n:''+n;
}

function dateToISO(date) {   // ISO 8601 date format
  return date.getFullYear() + '-' + addLeadingZero((date.getMonth() + 1)) + '-' + addLeadingZero(date.getDate());
}

function timeToISO(date, addSeconds=false, addMilliSeconds=false) {
  // ISO 8601 datetime format
  let time = addLeadingZero(date.getHours(), 2) + ':' + addLeadingZero(date.getMinutes(), 2);

  if (addSeconds)      time += ':' + addLeadingZero(date.getSeconds(), 2);
  if (addMilliSeconds) time += '.' + addLeadingZero(date.getMilliseconds(), 3);

  return time;
}

function datetimeToAge(datetime) {
  if (! datetime) return '';
  // let ApproxDaysPerYear     = 365.25;
  let ApproxDaysPerMonth    = 30.4375;
  let minutesPerDay         = 60 * 24;
  let secondsPerDay         = 60 * minutesPerDay;
  let ApproxSecondsPerMonth = ApproxDaysPerMonth * secondsPerDay;
  let ApproxSecondsPerYear  = ApproxSecondsPerMonth * 12;

  let text;
  let age = (Date.now() - datetime.getTime()) / 1000;

  let unborn = age < 0;
  if (unborn) age = -age;

  if (age > (100 * ApproxSecondsPerYear)) {
    text = ''; // Age is invalid if more than 100 years old
  } else if (age < secondsPerDay) {
    if (age < 60)              text = '< 1 ' + translate('minute');
    else if (age < (2 * 60))   text = '1 ' + translate('minute');
    else if (age < 3600)       text = Math.floor(age / 60) + ' ' + translate('minutes');
    else if (age < (2 * 3600)) text = '1 ' + translate('hour');
    else                       text = Math.floor(age / 3600) + ' ' + translate('hours');
  }
  else if (age < 2  * secondsPerDay)         text = '1 ' + translate('day');
  else if (age < 7  * secondsPerDay)         text = Math.floor(age / secondsPerDay) + ' ' + translate('days');
  else if (age < 14 * secondsPerDay)         text = '1 ' + translate('week');
  else if (age <      ApproxSecondsPerMonth) text = Math.floor(age / (7 * secondsPerDay)) + ' ' + translate('weeks');
  else if (age < 2  * ApproxSecondsPerMonth) text = '1 ' + translate('month');
  else if (age <      ApproxSecondsPerYear)  text = Math.floor(age / ApproxSecondsPerMonth) + ' ' + translate('months');
  else if (age < 2  * ApproxSecondsPerYear)  text = '1 ' + translate('year');
  else                                       text = Math.floor(age / ApproxSecondsPerYear) + ' ' + translate('years');

  if (unborn) text = translate('in_(time)') + ' ' + text;
  else text += ' ' + translate('ago');

  return text;
}

function hideElement(id){
  document.getElementById(id).style.display = 'none';
}

function deleteElement(id){
  const element = document.getElementById(id);
  if (element) element.remove();
}

function showError(text, secondsVisible=5) {
  showMessage(text, secondsVisible, true);
}

function showMessage(text, secondsVisible=3, errorMessage=false) {
  clearTimeout(showMessage.timeoutMessage);
  const divForm  = document.getElementById('floatingMessage');
  const divCross = document.getElementById('messageCloseCross');

  if (errorMessage) {
    divForm.classList.add('errorMessage');
    divCross.classList.remove('crossWhite');
  } else {
    divForm.classList.remove('errorMessage');
    divCross.classList.add('crossWhite');
  }

  document.getElementById('messageText').innerHTML = text;

  divForm.style.display = 'flex';

  if (secondsVisible !== -1) showMessage.timeoutMessage = setTimeout(closeMessage, secondsVisible * 1000);
}

function closeMessage() {
  let div = document.getElementById('floatingMessage');
  div.style.display = 'none';
  div.classList.remove('errorMessage');
  document.getElementById('messageText').innerHTML = '';
}

function confirmWarning(text, okCallback, buttonOKText=translate('Ok'), header=translate('Confirm')){
  confirmMessage(text, okCallback, buttonOKText, header, true);
}

function confirmMessage(text, okCallback, buttonOKText=translate('Ok'), header=translate('Confirm'), isWarning=false){
  if (isWarning) {
    document.getElementById('buttonConfirmOK').className            = 'button buttonWarning';
    document.getElementById('formConfirm').style.backgroundColor    = '#ffdb9d';
  } else {
    document.getElementById('buttonConfirmOK').className            = 'button';
    document.getElementById('formConfirm').style.backgroundColor    = '#ffffff';
  }
  document.getElementById('confirmHeader').innerHTML                = header;
  document.getElementById('confirmText').innerHTML                  = text;
  document.getElementById('formConfirmOuter').style.display         = 'flex';
  document.getElementById('buttonConfirmOK').innerText              = buttonOKText;
  document.getElementById('buttonConfirmCancel').style.display      = 'inline-block';
  document.getElementById('buttonConfirmOK').onclick = function(){
    closeConfirm();
    okCallback();
    return false; // Prevent closing window
  };
}

function closeConfirm() {
  hideElement('formConfirmOuter');
}

function escapeHtml(text) {
  if (! text) return '';
  let map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
  return text.replace(/[&<>"']/g, m => map[m]);
}

function inputDateTimeToISO8601(dateISO, timeISO){
  let year    = dateISO.substr(0, 4);
  let month   = dateISO.substr(5, 2) - 1; // Month is zero based
  let day     = dateISO.substr(8, 2);
  let hours   = timeISO.substr(0, 2);
  let minutes = timeISO.substr(3, 2);

  let date = new Date(year, month, day, hours, minutes);
  return date.toISOString();
}

function inputDateToISO8601(inputDate){
  let year    = inputDate.substr(0, 4);
  let month   = inputDate.substr(5, 2) - 1; // Month is zero based
  let day     = inputDate.substr(8, 2);

  let date = new Date(year, month, day);
  return date.toISOString();
}

function closePopupForm() {
  if (event) event.target.closest('.popupOuter').style.display = 'none';
  else {
    document.querySelectorAll('.popupOuter').forEach(form => {if (form.style.display === 'flex') form.style.display = 'none';});
  }

  closeAllPopups();
  closeMessage();
}

function closeAllPopups() {
  document.querySelectorAll('.buttonPopupMenu').forEach(
      popup => {if (popup.style.display === 'block') popup.style.display = 'none';}
    );
  document.querySelectorAll('.buttonPopupMenuTemp').forEach(menu => menu.remove());
  document.body.style.overflow = 'auto';

  const searchPersons = document.getElementById('searchSearchPersons');
  if (searchPersons && (searchPersons.style.display  === 'block')) toggleSearchPersons(event);
}

function validateEmail(email) {
  const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
  return re.test(email);
}

function is_valid_url(url) {
  let re = /((([A-Za-z]{3,9}:(?:\/\/)?)(?:[\-;:&=+$,\w]+@)?[A-Za-z0-9.\-]+|(?:www\.|[\-;:&=+$,\w]+@)[A-Za-z0-9.\-]+)((?:\/[+~%\/.\w\-_]*)?\??(?:[\-+=&;%@.\w_]*)#?(?:[.!\/\\\w]*))?)/;
  return re.test(url);
}

function showLoginForm(){
  closeMessage();
  document.getElementById('loginError').style.display   = 'none';
  document.getElementById('spinnerLogin').style.display = 'none';
  document.getElementById('formLogin').style.display    = 'flex';
}

function showLoginError(text) {
  document.getElementById('loginError').innerHTML     = text;
  document.getElementById('loginError').style.display = 'flex';
}

async function logOut() {
  closeMessage();
  const url  = "/general/ajax.php?function=logout";
  const user = await fetchFromServer(url);
  if (! user.loggedin) {
    showMessage(translate('Logged_out_successfully'), 1);
    window.location.reload();
  } else showError('Interne error while logging out');
}

async function loginIntern(email, password, stayLoggedIn=0) {
  const url = "/general/ajax.php?function=login" +
    "&email="        + encodeURIComponent(email) +
    "&password="     + encodeURIComponent(password) +
    "&stayLoggedIn=" + stayLoggedIn;

  document.getElementById('loginError').style.display = "none";

  const user = await fetchFromServer(url);
  if (user.error) showLoginError(user.error);
  else {
    if      (! user.emailexists) showLoginError(translate('Email_adres_unknown'));
    else if (! user.loggedin)    showLoginError(translate('Password_incorrect'));
    return user;
  }
}

function updateLoginGUI(userNew){
  user = userNew;
  const buttonPerson = document.getElementById('buttonPerson');

  // New crash button is only visible after user data is loaded, because new crash function checks if user is logged in.
  const buttonNewCrash = document.getElementById('buttonNewCrash');
  if (buttonNewCrash) buttonNewCrash.style.display = 'inline-block';

  document.getElementById('menuProfile').style.display = user.loggedin? 'block' : 'none';
  document.getElementById('menuLogin').style.display   = user.loggedin? 'none' : 'block';
  document.getElementById('menuLogout').style.display  = user.loggedin? 'block' : 'none';

  if (user.loggedin) {
    document.getElementById('loginName').style.display = 'inline-block';
    document.getElementById('loginText').style.display = 'none';
    document.getElementById('loginName').innerText     = user.firstname;
    document.getElementById('menuProfile').innerHTML   = user.firstname + ' ' + user.lastname + '<div class="smallFont">' + permissionToText(user.permission) + '</div>';
    buttonPerson.classList.remove('buttonPerson');
    buttonPerson.classList.add('bgPersonLoggedIn');
  } else {
    document.getElementById('loginName').style.display = 'none';
    document.getElementById('loginText').style.display = 'inline-block';
    document.getElementById('loginText').innerText     = 'Log in';
    document.getElementById('menuProfile').innerText   = '';
    buttonPerson.classList.add('buttonPerson');
    buttonPerson.classList.remove('bgPersonLoggedIn');
  }

  document.getElementById('iconCountry').style.backgroundImage = `url(/images/flags/${user.countryid.toLowerCase()}.svg)`;

  document.getElementById('titleCountry').innerHTML = ' | ' + user.country.name;

  document.querySelectorAll('.buttonEditPost').forEach(
    button => {
      const buttonUserId = parseInt(button.getAttribute('data-userid'));
      const canEditArticle = user.loggedin && ((user.permission !== UserPermission.newUser) || (buttonUserId === user.id));
      button.style.display = canEditArticle? 'inline-block' : 'none';
    }
  );

  // Show/hide moderator items
  document.querySelectorAll('[data-moderator]').forEach(d => {d.style.display = user.moderator? 'block' : 'none'});
  document.querySelectorAll('[data-admin]').forEach(d => {d.style.display = user.admin? 'block' : 'none'});
  document.querySelectorAll('[data-inline-admin]').forEach(d => {d.style.display = user.admin? 'inline-block' : 'none'});
}

async function selectCountry(countryId) {
  const urlServer = '/general/ajax.php?function=loadCountryDomain';
  const response  = await fetchFromServer(urlServer, {countryId: countryId});

  if (response.error) {
    showError(response.error);
  }

  if (response.domain) {
    const url = new URL(location.href);
    url.hostname = response.domain;

    location.href = url.href;
  }
}

async function setLanguage(languageId){
  const url      = '/general/ajax.php?function=setLanguage&id=' + languageId;
  const response = await fetchFromServer(url);

  if (response.error) {
    showError(response.error);
    return;
  }

  window.location.reload();
}

async function checkLogin() {
  showHideRegistrationFields(false);

  const email        = document.getElementById('loginEmail').value;
  const password     = document.getElementById('loginPassword').value;
  const stayLoggedIn = document.getElementById('stayLoggedIn').checked? 1 : 0;

  if (! validateEmail(email))     showLoginError(translate('Email_not_valid'));
  else if (password.length === 0) showLoginError(translate('Password_not_filled_in'));
  else {
    document.getElementById('spinnerLogin').style.display = 'block';

    try {
      const user = await loginIntern(email, password, stayLoggedIn);

      if (user.loggedin) {
        updateLoginGUI(user);

        hideElement('formLogin');
        showMessage(translate('Log_in_successful'), 1);
        window.location.reload();
      } else document.getElementById('spinnerLogin').style.display = 'none';

    } catch (e) {
      alert(e.message);
    }
  }
}

function showHideRegistrationFields(show) {
  document.getElementById('divFirstName').style.display       = show? 'flex' : 'none';
  document.getElementById('divLastName').style.display        = show? 'flex' : 'none';
  document.getElementById('divPasswordConfirm').style.display = show? 'flex' : 'none';
}

async function checkRegistration(){
  showHideRegistrationFields(true);

  const userNew = {
    email:           document.getElementById('loginEmail').value.trim(),
    firstname:       document.getElementById('loginFirstName').value.trim(),
    lastname:        document.getElementById('loginLastName').value.trim(),
    password:        document.getElementById('loginPassword').value.trim(),
    passwordconfirm: document.getElementById('loginPasswordConfirm').value.trim(),
  };

  if (! validateEmail(userNew.email))            showLoginError(translate('Email_not_valid'));
  else if (userNew.firstname.length < 1)         showLoginError(translate('First_name_not_filled_in'));
  else if (userNew.lastname.length < 1)          showLoginError(translate('Last_name_not_filled_in'));
  else if (userNew.password.length < 6)          showLoginError(translate('Password_less_than_6_characters'));
  else if (userNew.password !== userNew.passwordconfirm) showLoginError(translate('Passwords_not_same'));
  else {

    document.getElementById('spinnerLogin').style.display = 'block';
    try {
      const url      = '/general/ajax.php?function=register';
      const response = await fetchFromServer(url, userNew);

      if (response.error) {
        showError(response.error, 10);
      } else {
        if (response.ok) {
          const user = await loginIntern(userNew.email, userNew.password);
          if (user.loggedin) {
            updateLoginGUI(user);

            hideElement('formLogin');

            // Clear registratie velden
            document.getElementById('loginEmail').value           = '';
            document.getElementById('loginFirstName').value       = '';
            document.getElementById('loginLastName').value        = '';
            document.getElementById('loginPassword').value        = '';
            document.getElementById('loginPasswordConfirm').value = '';

            showMessage(translate('Registration_successful'), 1);

            window.location.reload();
          }
        }
      }

    } finally {
      document.getElementById('spinnerLogin').style.display = 'none';
    }
  }
  return false;
}

function loginForgotPassword() {
  let email = document.getElementById('loginEmail').value.trim().toLowerCase();

  if (! email)                     showLoginError(translate('Email_not_filled_in'));
  else if (! validateEmail(email)) showLoginError(translate('Email_not_valid'));
  else sendResetPasswordInstructions(email);
}

async function sendResetPasswordInstructions(email) {
  const url      = '/general/ajax.php?function=sendPasswordResetInstructions&email=' + encodeURIComponent(email);
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error);
  else if (response.ok) {
    showMessage(translate('Email_with_reset_password_instructions_sent') + ' ' + email + '.', 3);
  } else showError('Interne error while resetting password');
}

function scrollIntoViewIfNeeded(target) {
  let rect = target.getBoundingClientRect();
  if (rect.bottom > window.innerHeight) {
    target.scrollIntoView({
      block:    'center',
      inline:   'nearest'});
  }
  if (rect.top < 0) {
    target.scrollIntoView({
      block:    'center',
      inline:   'nearest'});
  }
}

function getCrashNumberFromPath(path){
  const matches = path.match(/^[\/]?(\d+)/);
  if (matches && (matches.length === 2)) return parseInt(matches[1]);
  else return null;
}

function createCrashURL(id, title){
  title = title.toLowerCase().replace(/\s+/g, '-').replace(/[^a-zA-Z0-9_-]+/g, '');
  return '/' + id + '/' + encodeURIComponent(title);
}

function selectButton(id, selected){
  let classList = document.getElementById(id).classList;
  if (selected) classList.add('buttonSelected');
  else classList.remove('buttonSelected');
}

function toggleSelectionButton(element){
  element.classList.toggle('buttonSelected');
}

function setMenuButton(id, active){
  const classList = document.getElementById(id).classList;
  if (active) classList.add('buttonSelected');
  else classList.remove('buttonSelected');
}

function menuButtonSelected(id){
  return document.getElementById(id).classList.contains('buttonSelected');
}

function initMenuSwipe() {

  document.addEventListener('touchstart', function (event) {
    // Only start navigation open swipe on left side of screen
    if ((event.touches[0].clientX < 60) && (! navigationIsOpen())) {
      touchDownAction = TouchDown.openNavigation;
    } else if (navigationIsOpen()){
      touchDownAction = TouchDown.closeNavigation;
    } else {
      touchDownAction = TouchDown.none;
    }

    if (touchDownAction === TouchDown.none) {
      xTouchDown = null;
      yTouchDown = null;
    } else {
      xTouchDown = event.touches[0].clientX;
      yTouchDown = event.touches[0].clientY;
    }
  });

  document.addEventListener('touchmove', function (event) {
    if (touchDownAction === TouchDown.none) return;
    let xTouch = event.touches[0].clientX;
    let yTouch = event.touches[0].clientY;
    let xDiff  = xTouchDown - xTouch;
    let yDiff  = yTouchDown - yTouch;
    let x=0;
    let navigation;
    if (Math.abs(xDiff) > Math.abs(yDiff)) {
      closeAllPopups();

      if (touchDownAction === TouchDown.closeNavigation){
        if (xDiff > 0) {
          navigation = document.getElementById('navigation');
          x = navigation.offsetWidth;
          if (xTouch < navigation.offsetWidth) {
            x -= Math.min(navigation.offsetWidth, xTouchDown) - xTouch;
          }
          navigation.style.transition = 'none';
          navigation.style.transform  = 'translateX(' + x + 'px)';
        }
      } else if (touchDownAction === TouchDown.openNavigation) {
        navigation = document.getElementById('navigation');
        if (xDiff < 0) x = Math.min(xTouch, navigation.offsetWidth);
        navigation.style.transition = 'none';
        navigation.style.transform  = 'translateX(' + x + 'px)';
      }
    }
  });

  function endNavigationSwipe() {
    let navigation              = document.getElementById('navigation');
    navigation.style.transform  = '';
    navigation.style.transition = '';
    xTouchDown                  = null;
    yTouchDown                  = null;
    touchDownAction             = TouchDown.none
  }

  document.addEventListener('touchend', function (event) {
    if (touchDownAction === TouchDown.none) return;
    let xTouch = event.changedTouches[0].clientX;

    if (touchDownAction === TouchDown.closeNavigation) {
      let navigation = document.getElementById('navigation');
      let xDiffInsidenavigation = Math.min(navigation.offsetWidth, xTouchDown) - xTouch;
      if (xDiffInsidenavigation > (navigation.offsetWidth / 2)) closeNavigation();
    } else if (touchDownAction === TouchDown.openNavigation){
      let navigation = document.getElementById('navigation');
      if (xTouch > (navigation.offsetWidth / 2)) openNavigation();
    }
    endNavigationSwipe();
  });

  document.addEventListener('touchcancel', function () {
    if (touchDownAction === TouchDown.none) return;
    endNavigationSwipe();
  });
}

function permissionToText(permission) {
  switch (permission) {
    case 0: return translate('Helper');
    case 1: return translate('Administrator');
    case 2: return translate('Moderator');
  }
}

function questionnaireTypeToText(type) {
  switch (type) {
    case QuestionnaireType.standard: return 'Standard';
    case QuestionnaireType.bechdel:  return 'Bechdel test';
    default: return 'Unknown';
  }
}

function bechdelAnswerToText(answer) {
  switch (answer) {
    case QuestionAnswer.no:              return 'Failed';
    case QuestionAnswer.yes:             return 'Passed';
    case QuestionAnswer.notDeterminable: return 'Not determinable';
    default: return 'Niet alle vragen beantwoord';
  }
}

function initPage(){
  document.addEventListener('click', closeAllPopups);
  initMenuSwipe();

  tippy.setDefaultProps({
    arrow: true,
    arrowType: 'round',
    interactive: false,
    duration: 100
  });
  tippy('[data-tippy-content]');
}

function initPageUser(){
  initPage();
  loadUserData();
}

async function loadUserData(options=[]) {
  try {
    const url = '/general/ajax.php?function=loadUserData';
    const response = await fetchFromServer(url, options);
    if (response.user) updateLoginGUI(response.user);

    return response.extraData;
  } catch (error) {
    showError(error.message);
  }
}

function loginClick(event) {
  event.stopPropagation();
  closeAllPopups();

  if (! user) return;

  if (user.loggedin) togglePersonMenu();
  else showLoginForm();
}

function countryClick(event){
  event.stopPropagation();

  const div    = document.getElementById('menuCountries');
  const isOpen = div.style.display === 'block';

  closeAllPopups();

  div.style.display = isOpen? 'none' : 'block';
}

function togglePersonMenu(){
  const div = document.getElementById('menuPerson');

  div.style.display = div.style.display === 'block'? 'none' : 'block';
}

function toggleNavigation(event) {
  event.stopPropagation();
  if (navigationIsOpen()) closeNavigation();
  else                    openNavigation();
}

function navigationIsOpen() {
  return document.getElementById('navigation').classList.contains('navigationOpen');
}

function openNavigation() {
  document.getElementById('navigation').classList.add('navigationOpen');
  document.getElementById('navShadow').classList.add('navShadowOpen');
  document.getElementById('navShadow').classList.remove('navShadowClose');
}

function closeNavigation() {
  document.getElementById('navigation').classList.remove('navigationOpen');
  document.getElementById('navShadow').classList.remove('navShadowOpen');
  document.getElementById('navShadow').classList.add('navShadowClose');
}

function transportationModeText(transportationMode) {
  switch (transportationMode) {
    case TransportationMode.unknown:          return translate('Unknown');
    case TransportationMode.pedestrian:       return translate('Pedestrian');
    case TransportationMode.scooter:          return translate('Scooter');
    case TransportationMode.bicycle:          return translate('Bicycle');
    case TransportationMode.motorScooter:     return translate('Motor_scooter');
    case TransportationMode.motorcycle:       return translate('Motorcycle');
    case TransportationMode.car:              return translate('Car');
    case TransportationMode.taxi:             return translate('Taxi');
    case TransportationMode.emergencyVehicle: return translate('Emergency_vehicle');
    case TransportationMode.deliveryVan:      return translate('Delivery_van');
    case TransportationMode.tractor:          return translate('Agricultural_vehicle');
    case TransportationMode.bus:              return translate('Bus');
    case TransportationMode.tram:             return translate('Tram');
    case TransportationMode.truck:            return translate('Truck');
    case TransportationMode.train:            return translate('Train');
    case TransportationMode.wheelchair:       return translate('Mobility_scooter');
    case TransportationMode.mopedCar:         return translate('Moped_car');
    default:                                   return '';
  }
}

function transportationImageFileName(transportationModeValue){
  switch (transportationModeValue) {
    case 0:  return 'unknown.svg';
    case 1:  return 'pedestrian.svg';
    case 2:  return 'bicycle.svg';
    case 3:  return 'motor_scooter.svg';
    case 4:  return 'motorcycle.svg';
    case 5:  return 'car.svg';
    case 6:  return 'taxi.svg';
    case 7:  return 'emergencyvehicle.svg';
    case 8:  return 'deliveryvan.svg';
    case 9:  return 'tractor.svg';
    case 10: return 'bus.svg';
    case 11: return 'tram.svg';
    case 12: return 'truck.svg';
    case 13: return 'train.svg';
    case 14: return 'wheelchair.svg';
    case 15: return 'mopedcar.svg';
    case 16: return 'scooter.svg';
  }
}

function transportationModeImage(transportationMode) {
  switch (transportationMode) {
    case TransportationMode.unknown:          return 'bgUnknown';
    case TransportationMode.pedestrian:       return 'bgPedestrian';
    case TransportationMode.bicycle:          return 'bgBicycle';
    case TransportationMode.scooter:          return 'bgScooter';
    case TransportationMode.motorScooter:     return 'bgMotorScooter';
    case TransportationMode.motorcycle:       return 'bgMotorcycle';
    case TransportationMode.car:              return 'bgCar';
    case TransportationMode.taxi:             return 'bgTaxi';
    case TransportationMode.emergencyVehicle: return 'bgEmergencyVehicle';
    case TransportationMode.deliveryVan:      return 'bgDeliveryVan';
    case TransportationMode.tractor:          return 'bgTractor';
    case TransportationMode.bus:              return 'bgBus';
    case TransportationMode.tram:             return 'bgTram';
    case TransportationMode.truck:            return 'bgTruck';
    case TransportationMode.train:            return 'bgTrain';
    case TransportationMode.wheelchair:       return 'bgWheelchair';
    case TransportationMode.mopedCar:         return 'bgMopedCar';
    default:                                   return 'bgUnknown';
  }
}

function transportationModeIcon(transportationMode, addTooltip=true, small=false) {
  const bg        = transportationModeImage(transportationMode);
  const text      = translate('Transportation_mode')  + ': ' + transportationModeText(transportationMode);
  const tooltip   = addTooltip? 'data-tippy-content="' + text + '"' : '';
  const className = small? 'iconSmall' : 'iconMedium';
  return `<div class="${className} ${bg}" ${tooltip}></div>`;
}

function healthIcon(healthStatus, addTooltip=true) {
  const bg      = healthImage(healthStatus);
  const text    = translate('Injury') + ': ' + healthText(healthStatus);
  const tooltip = addTooltip? 'data-tippy-content="' + text + '"' : '';
  return `<div class="iconMedium ${bg}" ${tooltip}></div>`;
}

function healthText(healthStatus) {
  switch (healthStatus) {
    case Health.unknown:  return translate('Unknown');
    case Health.unharmed: return translate('Unharmed');
    case Health.injured:  return translate('Injured');
    case Health.dead:     return translate('Dead_(adjective)');
    default:               return '';
  }
}

function healthImage(healthStatus) {
  switch (healthStatus) {
    case Health.unknown:  return 'bgUnknown';
    case Health.unharmed: return 'bgUnharmed';
    case Health.injured:  return 'bgInjured';
    case Health.dead:     return 'bgDead';
    default:               return 'bgUnknown';
  }
}

function acceptCookies() {
  createCookie('cookiesAccepted', 1, 3650);
  document.getElementById('cookieWarning').style.display = 'none';
}

function createCookie(name, value, days) {
  // https://www.quirksmode.org/js/cookies.html
  let expires = "";

  if (days) {
    let date = new Date();
    date.setTime(date.getTime()+(days*24*60*60*1000));
    expires = "; expires="+date.toGMTString();
  }

  // Make cookies work for all subdomains bij adding a dot before the main domain name
  let domainParts = location.host.split('.');
  domainParts.shift();
  const domain = '.'+domainParts.join('.');

  document.cookie = name + "=" + value + expires + "; path=/; SameSite=Lax; Secure; domain=" + domain;
}

function formatText(text) {
  text = escapeHtml(text);
  text = text.replace(/(?:\r\n{2,}|\r{2,}|\n{2,})/g, '<br><br>');
  return text;
}

function download(uri, filename) {
  let element = document.createElement('a');
  element.setAttribute('href', uri);
  element.setAttribute('download', filename);
  element.style.display = 'none';
  document.body.appendChild(element);
  element.click();
  document.body.removeChild(element);
}

function initObserver(callFunction){
  observerSpinner = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {if (entry.isIntersecting) callFunction();});
  }, {threshold: 0.9});
}

/**
 * Input is key. Output is translated text.
 * First character is automatically capitalized if the first character of the key is capitalized.
 * @param key
 * @return {string|*}
 */

function translate(key){

  if (! user.translations) {
    throw new Error('Internal error: User translations not loaded');
  }

  let textTranslated = user.translations[key.toLowerCase()];

  if (! textTranslated) textTranslated = key + '**';

  function initialIsCapital(text){
    return text && (text[0] !== text[0].toLowerCase());
  }

  function capitalizeFirstLetter(text) {
    if (! text) return '';
    else {
      let firstChar = text.charAt(0);
      if ((firstChar === '(') &&(text.length > 1)) {
        firstChar = text.charAt(1);
        return '(' + firstChar.toUpperCase() + text.slice(2);
      } else {
        return firstChar.toUpperCase() + text.slice(1);
      }
    }
  }

  if (initialIsCapital(key)) textTranslated = capitalizeFirstLetter(textTranslated);

  return textTranslated;
}

function hideSelectedTableRow(tableIndex=0){
  if (selectedTableData[tableIndex]) {
    const element = document.getElementById(`tr${tableIndex}_` + selectedTableData[tableIndex].id);
    if (element) element.classList.remove('trSelected');
  }
}

function selectTableRow(id=null, tableIndex=0) {
  hideSelectedTableRow(tableIndex);

  selectedTableData[tableIndex] = getSelectedTableData(id, tableIndex);

  if (! selectedTableData[tableIndex]) showError(`Data row id ${id} not found`)

  // Select first element if none found
  if ((! selectedTableData[tableIndex]) && (tableData[tableIndex].length > 0)) selectedTableData[tableIndex] = tableData[tableIndex][0];

  showSelectedTableRow(tableIndex);
}

function selectFirstTableRow(tableIndex=0) {
  if (tableData[tableIndex].length > 0) selectTableRow(tableData[tableIndex][0].id, tableIndex);
  else selectedTableData[tableIndex] = null;
}

function showSelectedTableRow(tableIndex=0){
  if (selectedTableData[tableIndex]) document.getElementById(`tr${tableIndex}_` + selectedTableData[tableIndex].id).classList.add('trSelected');
}

function getSelectedTableData(id, tableIndex=0) {
  // == used on purpose as strings are sometimes compared to integers
  return tableData[tableIndex].find(d => d.id == id);
}

function flagIconPath(countryId) {
  return `/images/flags/${countryId.toLowerCase()}.svg`;
}

function onDragEnter(event) {
  event.currentTarget.classList.add('dragOver');
}

function onDragLeave(event) {
  event.currentTarget.classList.remove('dragOver');
}

function onDragEnd(event) {
  event.target.classList.remove('dragged');
}

function onDragOver(event){
  event.preventDefault();
  event.dataTransfer.dropEffect = "move";
  return false;
}

function onDropRow(event, sourceId, insertAfter=true){
  const trSource  = document.getElementById(sourceId);
  const divTarget = event.target;
  const trTarget  = divTarget.closest('tr');

  event.currentTarget.classList.remove('dragOver');
  trSource.classList.remove('dragged');

  if (insertAfter) trTarget.parentNode.insertBefore(trSource, trTarget.nextSibling);
  else             trTarget.parentNode.insertBefore(trSource, trTarget);
}

function tabBarClick() {
  document.querySelectorAll('.tabSelected').forEach(d => d.classList.remove('tabSelected'));

  const tabSelected = event.target.closest('div');
  tabSelected.classList.add('tabSelected');

  const idContent = 'tabContent_' + tabSelected.id.substr(4);
  document.querySelectorAll('.tabContent').forEach(d => d.style.display = 'none');
  document.getElementById(idContent).style.display = 'block';
}

async function getQuestions(questionnaireId) {
  const url      = '/general/ajax.php?function=getQuestions&questionnaireId=' + questionnaireId;
  const response = await fetchFromServer(url);

  if (response.error) showError(response.error, 10);
  else return response;
}

function tableDataClick(event, tableIndex=0){
  event.stopPropagation();

  const tr = event.target.closest('tr');
  if (tr) {
    const id = tr.id.substr(4);
    selectTableRow(id, tableIndex);
  }

  closeAllPopups();
  if (event.target.hasAttribute('data-editUser')) showUserMenu(event.target);
}

function allQuestionsAnswered(questionnaire) {

  if (questionnaire.type === QuestionnaireType.standard) {
    for (const question of questionnaire.questions) {
      if (! Number.isInteger(question.answer)) return false;
    }
    return true;
  } else if (questionnaire.type === QuestionnaireType.bechdel) {
    const bechdelResult = getBechdelResult(questionnaire.questions);

    return bechdelResult !== null;
  }
}

/**
 * @param questions
 * @return {null|number}
 */
function getBechdelResult(questions) {
  for (const question of questions) {
    if      (question.answer === QuestionAnswer.no)              return QuestionAnswer.no;
    else if (question.answer === QuestionAnswer.notDeterminable) return QuestionAnswer.notDeterminable;
    else if (question.answer !== QuestionAnswer.yes)             return null;
  }

  return QuestionAnswer.yes;
}

function toggleSearchPersons(event) {
  toggleCheckOptions(event, 'SearchPersons');
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
  if (! document.getElementById('searchBar')) return;

  let html = '';
  for (const key of Object.keys(TransportationMode)){
    const transportationMode     =  TransportationMode[key];
    const id                     = 'tm' + transportationMode;
    const text                   = transportationModeText(transportationMode);
    const iconTransportationMode = transportationModeImage(transportationMode);
    html +=
      `<div id="${id}" class="optionCheckImage" onclick="searchPersonClick(${transportationMode});">
  <span class="checkbox"></span>
  <div class="iconMedium ${iconTransportationMode}" data-tippy-content="${text}"></div>
  <span id="searchDeadTm${transportationMode}" class="searchIcon bgDead" data-tippy-content="${translate('Dead_(adjective)')}" onclick="searchPersonOptionClick(event, 'Dead', ${transportationMode});"></span>      
  <span id="searchInjuredTm${transportationMode}" class="searchIcon bgInjured" data-tippy-content="${translate('Injured')}" onclick="searchPersonOptionClick(event, 'Injured', ${transportationMode});"></span>      
  <span id="searchRestrictedTm${transportationMode}" data-personRestricted class="searchIcon ${iconTransportationMode} mirrorHorizontally" data-tippy-content="${translate('Counterparty_same_mode')}" onclick="searchPersonOptionClick(event, 'Restricted', ${transportationMode});"></span>      
  <span id="searchUnilateralTm${transportationMode}" data-personUnilateral class="searchIcon bgUnilateral" data-tippy-content="${translate('One-sided_crash')}" onclick="searchPersonOptionClick(event, 'Unilateral', ${transportationMode});"></span>      
</div>`;
  }

  document.getElementById('searchSearchPersons').innerHTML = html;

  setCustomRangeVisibility();
}

function setCustomRangeVisibility() {
  const elementPeriod = document.getElementById('searchPeriod');

  if (elementPeriod) {
    const custom = elementPeriod.value === 'custom';
    document.getElementById('searchDateFrom').style.display = custom? 'block' : 'none';
    document.getElementById('searchDateTo').style.display   = custom? 'block' : 'none';
  }
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

function updateTransportationModeFilterInput(){
  let html = '';

  for (const key of Object.keys(TransportationMode)){
    const transportationMode =  TransportationMode[key];
    const transportationText =  transportationModeText(transportationMode);
    const elementId          = 'tm' + transportationMode;
    const element            = document.getElementById(elementId);
    if (element.classList.contains('itemSelected')) {
      let icon = transportationModeImage(transportationMode);
      html += `<span class="inputIconGroup"><span class="searchDisplayIcon ${icon}" data-tippy-content="${transportationText}"></span>`;

      const deadSelected = document.getElementById('searchDeadTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (deadSelected){
        let icon = healthImage(Health.dead);
        html += `<span class="searchDisplayIcon ${icon}" data-tippy-content="${translate('Dead_(adjective)')}"></span>`;
      }

      const injuredSelected = document.getElementById('searchInjuredTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (injuredSelected){
        let icon = healthImage(Health.injured);
        html += `<span class="searchDisplayIcon ${icon}" data-tippy-content="${translate('Injured')}"></span>`;
      }

      const restrictedSelected = document.getElementById('searchRestrictedTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (restrictedSelected){
        html += `<span class="searchDisplayIcon  ${icon} mirrorHorizontally" data-tippy-content="${translate('Counterparty_same_mode')}"></span>`;
      }

      const unilateralSelected = document.getElementById('searchUnilateralTm' + transportationMode).classList.contains('inputSelectButtonSelected');
      if (unilateralSelected){
        html += `<span class="searchDisplayIcon bgUnilateral" data-tippy-content="${translate('One_sided_crash')}"></span>`;
      }

      html += '</span>';
    }
  }

  // Show placeholder text if no persons selected
  if (html === '') html = translate('Humans');
  document.getElementById('inputSearchPersons').innerHTML = html;
  tippy('#inputSearchPersons [data-tippy-content]');
}

function getPersonsFromFilter(){
  let persons = [];

  for (const key of Object.keys(TransportationMode)){
    const transportationMode =  TransportationMode[key];
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

  for (const key of Object.keys(TransportationMode)){
    const transportationMode =  TransportationMode[key];
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