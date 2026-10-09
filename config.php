<?php
$VERSION = 749;
$VERSION_DATE = '9 October 2026';

// Passwords and API keys live in config_secret.php, which is excluded from git.
// Copy config_secret.example.php to config_secret.php and fill in the settings.
if (!file_exists(__DIR__ . '/config_secret.php')) {
  http_response_code(500);
  exit('Configuration missing: copy config_secret.example.php to config_secret.php and fill in the settings.');
}

require_once 'config_secret.php';

const WEBSITE_NAME = 'roaddanger';
const WEBSITE_DOMAIN = 'roaddanger.org';
const DEFAULT_COUNTRY_ID = 'UN';
const DEFAULT_LANGUAGE = 'en';

// See: https://docs.mapbox.com/mapbox-gl-js/guides/
const MAPBOX_GL_JS = 'https://api.mapbox.com/mapbox-gl-js/v3.17.0/mapbox-gl.js';
const MAPBOX_GL_CSS = 'https://api.mapbox.com/mapbox-gl-js/v3.17.0/mapbox-gl.css';

// See: https://docs.mapbox.com/mapbox-gl-js/example/mapbox-gl-geocoder/
const MAPBOX_GEOCODER_JS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.1.0/mapbox-gl-geocoder.min.js';
const MAPBOX_GEOCODER_CSS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.1.0/mapbox-gl-geocoder.css';

// Command to start the headless browser and return DOM.
// The headless browser is needed if a media website uses a dummy-first page which loads the main page with JavaScript.
// The DPG Media group does this (e.g., ad.nl).
// Install Chromium or another browser on your server if you want to enable loading websites using a headless browser
// --disable-gpu is used as servers often run without a desktop environment.
// --log-level=3 to suppress error messages which are triggered by the lack of a desktop environment, but don't matter
// --no-sandbox Use only if sandbox does not work, which can happen when PHP is hardened

// Debian Linux with Chromium
const HEADLESS_BROWSER_COMMAND = 'chromium --headless=new --dump-dom --disable-gpu --log-level=3 --no-sandbox';

// Windows 11 with Google Chrome
const HEADLESS_BROWSER_COMMAND_WINDOWS = '"C:\Program Files\Google\Chrome\Application\chrome.exe" --headless=new --dump-dom';

// Command to run PHP scripts from the command line on Linux
const PHP_COMMAND_LINE = 'php8.4';

// Command to run PHP scripts from the command line on Windows
const PHP_COMMAND_LINE_WINDOWS = 'C:\laragon\bin\php\php-8.4.14-nts-Win32-vs17-x64\php.exe';