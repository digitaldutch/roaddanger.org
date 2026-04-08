<?php
$VERSION = 723;
$VERSION_DATE = '8 April 2026';

require_once 'config_secret.php';

// *** Do not fill in the database settings below! ***
// Instead:
// - Create a new file named config_secret.php and copy and fill in the settings below there.
// - Exclude config_secret.php from checking into your source code repository.
// - This is to:
//    - prevent passwords from entering the source code repository (e.g. git)
//    - prevent local settings on your server from being overwritten.
//
// *** config_secret.php content start ***
//
// Database settings
// const DB_HOST = 'localhost';
// const DB_NAME = 'database_name';
// const DB_USER = 'database_user';
// const DB_PASSWORD = 'database_password';
//
// const EMAIL_FOR_ERRORS = 'you@your_domain.com';
//
// OpenRouter is used for processing several AI tasks
// const OPENROUTER_API_KEY = 'your_openrouter_api_key';
//
// HERE is used for finding the exact coordinates of crashes based on a location description
// AI itself is much less accurate than the HERE API
// const HERE_API_KEY = 'your_here_api_key';
//
// *** config_secret.php content end ***

// *** End settings that belong in config_secret.php ***

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

// Debian Linux with Chromium
const HEADLESS_BROWSER_COMMAND = 'chromium --headless=new --dump-dom --disable-gpu --log-level=3';

// Windows 11 with Google Chrome
const HEADLESS_BROWSER_COMMAND_WINDOWS = '"C:\Program Files\Google\Chrome\Application\chrome.exe" --headless=new --dump-dom';

// Command to run PHP scripts from the command line on Linux
const PHP_COMMAND_LINE = 'php8.4';

// Command to run PHP scripts from the command line on Windows
const PHP_COMMAND_LINE_WINDOWS = 'C:\laragon\bin\php\php-8.4.14-nts-Win32-vs17-x64\php.exe';