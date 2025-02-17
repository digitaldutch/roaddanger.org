<?php
$VERSION = 579;
$VERSION_DATE = '17 February 2025';

require_once 'configsecret.php';

// *** Do not fill in the database settings below! ***
// Instead:
// - Create a new file named configsecret.php and copy and fill in the settings below there.
// - Exclude configsecret.php from checking into your source code repository.
// - This is to:
//    - prevent passwords from entering the source code repository (e.g. git)
//    - prevent local settings on your server from being overwritten.
//
// const DB_HOST = 'localhost';
// const DB_NAME = 'database_name';
// const DB_USER = 'database_user';
// const DB_PASSWORD = 'database_password';
// const EMAIL_FOR_ERRORS = 'you@your_domain.com';
//
// *** End settings that belong in configsecret.php ***

const COOKIE_DOMAIN = '';

const WEBSITE_NAME = 'roaddanger';

const DEFAULT_COUNTRY_ID = 'NL';

// https://docs.mapbox.com/mapbox-gl-js/guides/
const MAPBOX_GL_JS = 'https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.js';
const MAPBOX_GL_CSS = 'https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.css';

// https://docs.mapbox.com/mapbox-gl-js/example/mapbox-gl-geocoder/
const MAPBOX_GEOCODER_JS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js';
const MAPBOX_GEOCODER_CSS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css';

// Command to start headless browser and return DOM.
// The headless browser is needed if a media website uses a dummy first page which loads the main page with JavaScript.
// The DPG Media group does this (e.g. ad.nl).
// Install Chromium or another browser on your server if you want to enable loading websites using a headless browser
// --disable-gpu is used as servers often run without desktop environment.
// --log-level=3 to suppress error messages which are triggeredby the lack of a desktop environment, but don't matter
// The media url is appended to the end of this command. Hence, the single space character at the end of the command.

// Debian Linux with Chromium
const HEADLESS_BROWSER_COMMAND = 'chromium --headless=new --dump-dom --disable-gpu --log-level=3 ';

// Windows 11 with Google Chrome
//const HEADLESS_BROWSER_COMMAND = '"C:\Program Files\Google\Chrome\Application\chrome.exe" --headless=new --dump-dom ';