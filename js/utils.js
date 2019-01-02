let user;
let xTouchDown      = null;
let yTouchDown      = null;
let TTouchDown      = Object.freeze({none:0, openNavigation:1, closeNavigation:2});
let touchDownAction = TTouchDown.none;

const TUserPermission = Object.freeze({newuser: 0, admin: 1, moderator: 2});
const TAgeCategory = Object.freeze({child: 0, grownup: 1, elderly: 2});
const TTransportationMode = Object.freeze({
  unknown: 0, pedestrian: 1, bicycle: 2, scooter: 3, motorcycle: 4, car: 5, taxi: 6, emergencyVehicle: 7, deliveryVan: 8,  tractor: 9,
  bus: 10, tram: 11, truck: 12, train: 13, wheelchair: 14, mopedCar: 15});
const THealth = Object.freeze({unknown: 0, unharmed: 1, injured: 2, dead: 3});

const fetchOptions = { // Required for Safari. Safari sets the credentials by default to none, resulting in no cookies being sent and login failure in the AJAX script :(
  method:      'GET',
  headers:     {'Content-Type': 'application/json', 'Cache': 'no-cache'},
  credentials: 'same-origin',
};

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
  var time = addLeadingZero(date.getHours(), 2) + ':' + addLeadingZero(date.getMinutes(), 2);

  if (addSeconds)      time += ':' + addLeadingZero(date.getSeconds(), 2);
  if (addMilliSeconds) time += '.' + addLeadingZero(date.getMilliseconds(), 3);

  return time;
}

function datetimeToAge(datetime) {
  if (! datetime) return '';
  // var ApproxDaysPerYear     = 365.25;
  var ApproxDaysPerMonth    = 30.4375;
  var minutesPerDay         = 60 * 24;
  var secondsPerDay         = 60 * minutesPerDay;
  var ApproxSecondsPerMonth = ApproxDaysPerMonth * secondsPerDay;
  var ApproxSecondsPerYear  = ApproxSecondsPerMonth * 12;

  var text;
  var age = (Date.now() - datetime.getTime()) / 1000;

  var unborn = age < 0;
  if (unborn) age = -age;

  if (age > (100 * ApproxSecondsPerYear)) {
    text = ''; // Age is invalid if more than 100 years old
  } else if (age < secondsPerDay) {
    if (age < 60)              text = '< 1 minuut';
    else if (age < (2 * 60))   text = '1 minuut';
    else if (age < 3600)       text = Math.floor(age / 60) + ' minuten';
    else if (age < (2 * 3600)) text = '1 uur';
    else                       text = Math.floor(age / 3600) + ' uur';
  }
  else if (age < 2  * secondsPerDay)         text = '1 dag';
  else if (age < 7  * secondsPerDay)         text = Math.floor(age / secondsPerDay) + ' dagen';
  else if (age < 14 * secondsPerDay)         text = '1 week';
  else if (age <      ApproxSecondsPerMonth) text = Math.floor(age / (7 * secondsPerDay)) + ' weken';
  else if (age < 2  * ApproxSecondsPerMonth) text = '1 maand';
  else if (age <      ApproxSecondsPerYear)  text = Math.floor(age / ApproxSecondsPerMonth) + ' maanden';
  else if (age < 2  * ApproxSecondsPerYear)  text = '1 jaar';
  else                                       text = Math.floor(age / ApproxSecondsPerYear) + ' jaar';

  if (unborn) text = 'over ' + text;
  else text += ' geleden';

  return text;
}

function dateToAge(date) {
  if (! date) return '';
  // var ApproxDaysPerYear     = 365.25;
  var ApproxDaysPerMonth    = 30.4375;
  var minutesPerDay         = 60 * 24;
  var secondsPerDay         = 60 * minutesPerDay;
  var ApproxSecondsPerMonth = ApproxDaysPerMonth * secondsPerDay;
  var ApproxSecondsPerYear  = ApproxSecondsPerMonth * 12;

  var text;
  var age = (Date.now() - date.getTime()) / 1000;

  var unborn = age < 0;
  if (unborn) age = -age;

  if (age > (100 * ApproxSecondsPerYear)) {
    text = ''; // Age is invalid if more than 100 years old
  }
  else if (age < secondsPerDay)              return unborn? 'morgen' : 'vandaag';
  else if (age < 2  * secondsPerDay)         return unborn? 'overmorgen' : 'gisteren';
  else if (age < 7  * secondsPerDay)         text = Math.floor(age / secondsPerDay) + ' dagen';
  else if (age < 14 * secondsPerDay)         text = '1 week';
  else if (age <      ApproxSecondsPerMonth) text = Math.floor(age / (7 * secondsPerDay)) + ' weken';
  else if (age < 2  * ApproxSecondsPerMonth) text = '1 maand';
  else if (age <      ApproxSecondsPerYear)  text = Math.floor(age / ApproxSecondsPerMonth) + ' maanden';
  else if (age < 2  * ApproxSecondsPerYear)  text = '1 jaar';
  else                                       text = Math.floor(age / ApproxSecondsPerYear) + ' jaar';

  if (unborn) text = 'over ' + text;
  else text += ' geleden';

  return text;
}

function hideDiv(id){
  document.getElementById(id).style.display = 'none';
}

function showError(text, secondsVisible) {
  if (secondsVisible == null) secondsVisible = 5;
  showMessage(text, secondsVisible, '#F9BDBD');
}

function showMessage(text, secondsVisible=3, BGColor='#ffffff') {
  clearTimeout(showMessage.timeoutMessage);
  let div = document.getElementById('floatingMessage');
  div.style.display         = 'flex';
  div.style.backgroundColor = BGColor;
  document.getElementById('messageText').innerHTML = text;

  if (secondsVisible !== -1) showMessage.timeoutMessage = setTimeout(hideMessage, secondsVisible * 1000);
}

function hideMessage() {
  let div = document.getElementById('floatingMessage');
  div.style.display         = "none";
  div.style.backgroundColor = '#F9BDBD';
  document.getElementById('messageText').innerHTML = '';
}

function confirmMessage(text, okCallback, buttonOKText='OK', header='Bevestigen', isWarning=false){
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
    hideDiv('formConfirmOuter');
    okCallback();
    return false; // Prevent closing window
  };
}

function escapeHtml(text) {
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
  var year    = dateISO.substr(0, 4);
  var month   = dateISO.substr(5, 2) - 1; // Month is zero based
  var day     = dateISO.substr(8, 2);
  var hours   = timeISO.substr(0, 2);
  var minutes = timeISO.substr(3, 2);

  var date = new Date(year, month, day, hours, minutes);
  return date.toISOString();
}

function inputDateToISO8601(inputDate){
  var year    = inputDate.substr(0, 4);
  var month   = inputDate.substr(5, 2) - 1; // Month is zero based
  var day     = inputDate.substr(8, 2);

  var date = new Date(year, month, day);
  return date.toISOString();
}

function closePopupForm() {
  document.querySelectorAll('.popupOuter').forEach(form => {if (form.style.display === 'flex') form.style.display = 'none';});
  closeAllPopups();
  hideMessage();
}

function closeAllPopups() {
  document.querySelectorAll('.buttonPopupMenu').forEach(
      popup => {if (popup.style.display === 'block') popup.style.display = 'none';}
    );
}

function validateEmail(email) {
  var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
  return re.test(email);
}

function is_valid_url(url) {
  var re = /((([A-Za-z]{3,9}:(?:\/\/)?)(?:[\-;:&=\+\$,\w]+@)?[A-Za-z0-9\.\-]+|(?:www\.|[\-;:&=\+\$,\w]+@)[A-Za-z0-9\.\-]+)((?:\/[\+~%\/\.\w\-_]*)?\??(?:[\-\+=&;%@\.\w_]*)#?(?:[\.\!\/\\\w]*))?)/;
  return re.test(url);
}

function showLoginForm(){
  hideMessage();
  document.getElementById('loginError').style.display   = 'none';
  document.getElementById('spinnerLogin').style.display = 'none';
  document.getElementById('formLogin').style.display    = 'flex';
}

function showLoginError(text) {
  document.getElementById('loginError').innerHTML     = text;
  document.getElementById('loginError').style.display = 'flex';
}

async function logOut() {
  hideMessage();
  const url = "/ajax.php?function=logout";

  const response = await fetch(url, fetchOptions);
  const text     = await response.text();
  const user     = JSON.parse(text);
  if (! user.loggedin) {
    showMessage('Uitloggen succesvol', 1);
    window.location.reload();
  } else showError('Interne fout bij uitloggen.');
}

function loginIntern(email, password, stayLoggedIn=0) {
  let url = "/ajax.php?function=login" +
    "&email="        + encodeURIComponent(email) +
    "&password="     + encodeURIComponent(password) +
    "&stayLoggedIn=" + stayLoggedIn;
  document.getElementById('loginError').style.display = "none";

  return fetch(url, fetchOptions)
    .then(responseJSON => responseJSON.json())
    .then(user => {
      if      (! user.emailexists) showLoginError('Email adres onbekend');
      else if (! user.loggedin)    showLoginError('Wachtwoord verkeerd');
      return user;
    })
    .catch(error => showLoginError(error));
}

function updateLoginGUI(userNew){
  user = userNew;
  const buttonPerson = document.getElementById('buttonPerson');

  document.getElementById('menuProfile').style.display = user.loggedin? 'block' : 'none';
  document.getElementById('menuLogin').style.display   = user.loggedin? 'none' : 'block';
  document.getElementById('menuLogout').style.display  = user.loggedin? 'block' : 'none';

  if (user.loggedin) {
    document.getElementById('loginName').innerText   = user.firstname;
    document.getElementById('menuProfile').innerHTML = user.firstname + '<div class="smallFont">' + permissionToText(user.permission) + '</div>';
    buttonPerson.classList.remove('buttonPerson');
    buttonPerson.classList.add('bgPersonLoggedIn');
  } else {
    document.getElementById('loginName').innerText   = 'Log in';
    document.getElementById('menuProfile').innerText = '';
    buttonPerson.classList.add('buttonPerson');
    buttonPerson.classList.remove('bgPersonLoggedIn');
  }

  document.querySelectorAll('.buttonEditPost').forEach(
    button => {
      const buttonUserid   = parseInt(button.getAttribute('data-userid'));
      const canEditArticle = user.loggedin && ((user.permission !== TUserPermission.newuser) || (buttonUserid === user.id));
      button.style.display = canEditArticle? 'inline-block' : 'none';
    }
  );

  // Show/hide moderator items
  document.querySelectorAll('[data-moderator]').forEach(d => {d.style.display = user.moderator? 'block' : 'none'});
  document.querySelectorAll('[data-admin]').forEach(d => {d.style.display = user.admin? 'block' : 'none'});

}

function checkLogin() {
  showHideRegistrationFields(false);

  let email        = document.getElementById('loginEmail').value;
  let password     = document.getElementById('loginPassword').value;
  var stayLoggedIn = (document.getElementById('stayLoggedIn').checked)? 1 : 0;

  if (! validateEmail(email))     showLoginError('Geen geldig email ingevuld');
  else if (password.length === 0) showLoginError('Geen wachtwoord ingevuld');
  else {
    document.getElementById('spinnerLogin').style.display = 'block';
    loginIntern(email, password, stayLoggedIn).then(user => {
        if (user.loggedin) {
          hideDiv('formLogin');
          showMessage('Inloggen succesvol', 1);
          window.location.reload();
        } else document.getElementById('spinnerLogin').style.display = 'none';
      }
    );
  }

  // Prevent default form submit close action
  return false;
}

function showHideRegistrationFields(show) {
  document.getElementById('divFirstName').style.display       = show? 'flex' : 'none';
  document.getElementById('divLastName').style.display        = show? 'flex' : 'none';
  document.getElementById('divPasswordConfirm').style.display = show? 'flex' : 'none';
}

async function checkRegistration(){
  showHideRegistrationFields(true);

  let user = {};
  user.email           = document.getElementById('loginEmail').value.trim();
  user.firstname       = document.getElementById('loginFirstName').value.trim();
  user.lastname        = document.getElementById('loginLastName').value.trim();
  user.password        = document.getElementById('loginPassword').value.trim();
  user.passwordconfirm = document.getElementById('loginPasswordConfirm').value.trim();

  if (! validateEmail(user.email))            showLoginError('Geen geldig email ingevuld.');
  else if (user.firstname.length < 1)         showLoginError('Geen voornaam ingevuld');
  else if (user.lastname.length < 1)          showLoginError('Geen achternaam ingevuld');
  else if (user.password.length < 6)          showLoginError('Wachtwoord moet minimaal 6 karakters lang zijn.');
  else if (user.password !== user.passwordconfirm) showLoginError('Wachtwoorden zijn niet gelijk.');
  else {

    document.getElementById('spinnerLogin').style.display = 'block';
    try {
      const url = '/ajax.php?function=register';
      const optionsFetch = {
        method:  'POST',
        body: JSON.stringify(user),
        headers: {'Content-Type': 'application/json'},
      };
      const response = await fetch(url, optionsFetch);
      const text     = await response.text();
      const data     = JSON.parse(text);
      if (data.error) {
        showError(data.error, 10);
      } else {
        if (data.ok) {
          loginIntern(user.email, user.password)
            .then(user => {
              if (user.loggedin) {
                updateLoginGUI(user);
                hideDiv('formLogin');

                // Clear registratie velden
                document.getElementById('loginEmail').value           = '';
                document.getElementById('loginFirstName').value       = '';
                document.getElementById('loginLastName').value        = '';
                document.getElementById('loginPassword').value        = '';
                document.getElementById('loginPasswordConfirm').value = '';

                showMessage('Registratie succesvol', 1);

                window.location.reload();
              }
            }
          );
        }
      }

    } finally {
      document.getElementById('spinnerLogin').style.display = 'none';
    }
  }
  return false;
}

function loginForgotPassword() {
  var email = document.getElementById('loginEmail').value.trim().toLowerCase();

  if (! email)                     showLoginError('Geen email adres ingevuld');
  else if (! validateEmail(email)) showLoginError('Geen geldig email adres ingevuld');
  else sendResetPasswordInstructions(email);
}

async function sendResetPasswordInstructions(email) {
  const url = '/ajax.php?function=sendPasswordResetInstructions&email=' + encodeURIComponent(email);

  const response = await fetch(url, fetchOptions);
  const text     = await response.text();
  const data     = JSON.parse(text);
  if (data.error) showError(data.error);
  else if (data.ok) {
    showMessage('Email met wachtwoord reset instructies is verzonden naar ' + email + '.', 3);
  } else showError('Interne fout bij wachtwoord resetten.');
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

function getAccidentNumberFromPath(path){
  const matches = path.match(/^[\/]?(\d+)/);
  if (matches && (matches.length === 2)) return parseInt(matches[1]);
  else return null;
}

function createAccidentURL(id, title){
  title = title.toLowerCase().replace(/\s+/g, '-').replace(/[^a-zA-Z0-9_-]+/g, '');
  return '/' + id + '/' + encodeURIComponent(title);
}

function selectButton(id, selected){
  let classList = document.getElementById(id).classList;
  if (selected) classList.add('buttonSelected');
  else classList.remove('buttonSelected');
}

function toggleMenuButton(element){
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

function isScrolledIntoView(element) {
  let rect       = element.getBoundingClientRect();
  let elemTop    = rect.top;
  let elemBottom = rect.bottom;

  // Only completely visible elements return true:
  // let isVisible = (elemTop >= 0) && (elemBottom <= window.innerHeight);

  // Partially visible elements return true:
  return elemTop < window.innerHeight && elemBottom >= 0;
}

function initMenuSwipe() {
  document.addEventListener('touchstart', function (event) {
    if ((event.touches[0].clientX < 60) && (! navigationIsOpen())) { // Only start navigation open swipe on left side of screen
      touchDownAction = TTouchDown.openNavigation;
    } else if (navigationIsOpen()){
      touchDownAction = TTouchDown.closeNavigation;
    } else {
      touchDownAction = TTouchDown.none;
    }

    if (touchDownAction === TTouchDown.none) {
      xTouchDown = null;
      yTouchDown = null;
    } else {
      xTouchDown = event.touches[0].clientX;
      yTouchDown = event.touches[0].clientY;
    }
  });

  document.addEventListener('touchmove', function (event) {
    if (touchDownAction === TTouchDown.none) return;
    let xTouch = event.touches[0].clientX;
    let yTouch = event.touches[0].clientY;
    let xDiff  = xTouchDown - xTouch;
    let yDiff  = yTouchDown - yTouch;
    let x=0;
    let navigation;
    if (Math.abs(xDiff) > Math.abs(yDiff)) {
      if (touchDownAction === TTouchDown.closeNavigation){
        if (xDiff > 0) {
          navigation = document.getElementById('navigation');
          x = navigation.offsetWidth;
          if (xTouch < navigation.offsetWidth) {
            x -= Math.min(navigation.offsetWidth, xTouchDown) - xTouch;
          }
          navigation.style.transition = 'none';
          navigation.style.transform  = 'translateX(' + x + 'px)';
        }
      } else if (touchDownAction === TTouchDown.openNavigation) {
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
    touchDownAction             = TTouchDown.none
  }

  document.addEventListener('touchend', function (event) {
    if (touchDownAction === TTouchDown.none) return;
    let xTouch = event.changedTouches[0].clientX;

    if (touchDownAction === TTouchDown.closeNavigation) {
      let navigation = document.getElementById('navigation');
      let xDiffInsidenavigation = Math.min(navigation.offsetWidth, xTouchDown) - xTouch;
      if (xDiffInsidenavigation > (navigation.offsetWidth / 2)) closeNavigation();
    } else if (touchDownAction === TTouchDown.openNavigation){
      let navigation = document.getElementById('navigation');
      if (xTouch > (navigation.offsetWidth / 2)) openNavigation();
    }
    endNavigationSwipe();
  });

  document.addEventListener('touchcancel', function () {
    if (touchDownAction === TTouchDown.none) return;
    endNavigationSwipe();
  });
}

function permissionToText(permission) {
  switch (permission) {
    case 0: return 'Helper';
    case 1: return 'Beheerder';
    case 2: return 'Moderator';
  }
}

function initPage(){
  document.onclick = closeAllPopups;
  initMenuSwipe();

  tippy.setDefaults({
    arrow:     true,
    arrowType: 'round',
    duration:  100
  });
  tippy('[data-tippy-content]');
}

function initPageUser(){
  initPage();
  loadUserData();
}

async function loadUserData() {
  try {
    let url = '/ajax.php?function=getuser';
    const response = await fetch(url, fetchOptions);
    const text = await response.text();
    data = JSON.parse(text);
    if (data.user) updateLoginGUI(data.user);
  } catch (error) {
    showError(error.message);
  }
}

function loginClick() {
  event.stopPropagation();
  closeAllPopups();

  if (user.loggedin) showPersonMenu();
  else showLoginForm();
}

function showPersonMenu(){
  document.getElementById('menuPerson').style.display = 'block';
}


function download(filename, text) {
  var element = document.createElement('a');
  element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
  element.setAttribute('download', filename);

  element.style.display = 'none';
  document.body.appendChild(element);

  element.click();

  document.body.removeChild(element);
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
    case TTransportationMode.unknown:          return 'Onbekend';
    case TTransportationMode.pedestrian:       return 'Voetganger';
    case TTransportationMode.bicycle:          return 'Fiets';
    case TTransportationMode.scooter:          return 'Snorfiets/Scooter/Brommer';
    case TTransportationMode.motorcycle:       return 'Motorfiets';
    case TTransportationMode.car:              return 'Personenauto';
    case TTransportationMode.taxi:             return 'Taxi/Uber';
    case TTransportationMode.emergencyVehicle: return 'Hulpverleningsvoertuig';
    case TTransportationMode.deliveryVan:      return 'Bestelwagen';
    case TTransportationMode.tractor:          return 'Landbouwvoertuig';
    case TTransportationMode.bus:              return 'Bus';
    case TTransportationMode.tram:             return 'Tram';
    case TTransportationMode.truck:            return 'Vrachtwagen';
    case TTransportationMode.train:            return 'Trein';
    case TTransportationMode.wheelchair:       return 'Scootmobiel';
    case TTransportationMode.mopedCar:         return 'Brommobiel/Tuktuk';
    default:                                   return '';
  }
}

function transportationModeImage(transportationMode) {
  switch (transportationMode) {
    case TTransportationMode.unknown:          return 'bgUnknown';
    case TTransportationMode.pedestrian:       return 'bgPedestrian';
    case TTransportationMode.bicycle:          return 'bgBicycle';
    case TTransportationMode.scooter:          return 'bgScooter';
    case TTransportationMode.motorcycle:       return 'bgMotorcycle';
    case TTransportationMode.car:              return 'bgCar';
    case TTransportationMode.taxi:             return 'bgTaxi';
    case TTransportationMode.emergencyVehicle: return 'bgEmergencyVehicle';
    case TTransportationMode.deliveryVan:      return 'bgDeliveryVan';
    case TTransportationMode.tractor:          return 'bgTractor';
    case TTransportationMode.bus:              return 'bgBus';
    case TTransportationMode.tram:             return 'bgTram';
    case TTransportationMode.truck:            return 'bgTruck';
    case TTransportationMode.train:            return 'bgTrain';
    case TTransportationMode.wheelchair:       return 'bgWheelchair';
    case TTransportationMode.mopedCar:         return 'bgMopedCar';
    default:                                   return 'bgUnknown';
  }
}

function transportationModeIcon(transportationMode, addTooltip=true) {
  const bg      = transportationModeImage(transportationMode);
  const text    = 'Vervoermiddel: ' + transportationModeText(transportationMode);
  const tooltip = addTooltip? 'data-tippy-content="' + text + '"' : '';
  return `<div class="iconMedium ${bg}" ${tooltip}></div>`;
}

function healthIcon(healthStatus, addTooltip=true) {
  const bg      = healthImage(healthStatus);
  const text    = 'Letsel: ' + healthText(healthStatus);
  const tooltip = addTooltip? 'data-tippy-content="' + text + '"' : '';
  return `<div class="iconMedium ${bg}" ${tooltip}></div>`;
}

function healthText(healthStatus) {
  switch (healthStatus) {
    case THealth.unknown:  return 'Onbekend';
    case THealth.unharmed: return 'Ongedeerd';
    case THealth.injured:  return 'Gewond';
    case THealth.dead:     return 'Dood';
    default:               return '';
  }
}

function healthImage(healthStatus) {
  switch (healthStatus) {
    case THealth.unknown:  return 'bgUnknown';
    case THealth.unharmed: return 'bgUnharmed';
    case THealth.injured:  return 'bgInjured';
    case THealth.dead:     return 'bgDead';
    default:               return 'bgUnknown';
  }
}

function clone(obj) {
  // See: https://stackoverflow.com/questions/728360/most-elegant-way-to-clone-a-javascript-object
  // Handle the 3 simple types, and null or undefined
  if (null == obj || "object" != typeof obj) return obj;

  // Handle Date
  if (obj instanceof Date) {
    var lDate = new Date();
    lDate.setTime(obj.getTime());
    return lDate;
  }

  // Handle Array
  if (obj instanceof Array) {
    var lArray = [];
    for (var i = 0, len = obj.length; i < len; i++) {
      lArray[i] = clone(obj[i]);
    }
    return lArray;
  }

  // Handle Object
  if (obj instanceof Object) {
    var lObject = {};
    for (var attr in obj) {
      if (obj.hasOwnProperty(attr)) lObject[attr] = clone(obj[attr]);
    }
    return lObject;
  }

  throw new Error("Unable to copy obj! Its type isn't supported.");
}