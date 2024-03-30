<?php
$VERSION = 524;
$VERSION_DATE = '30 March 2024';

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
//
// *** End settings that belong in configsecret.php ***

const COOKIE_DOMAIN = '';

const WEBSITE_NAME = 'roaddanger';

const DEFAULT_COUNTRY_ID = 'NL';

// https://docs.mapbox.com/mapbox-gl-js/guides/
const MAPBOX_GL_JS = 'https://api.mapbox.com/mapbox-gl-js/v2.7.0/mapbox-gl.js';
const MAPBOX_GL_CSS = 'https://api.mapbox.com/mapbox-gl-js/v2.7.0/mapbox-gl.css';

// https://docs.mapbox.com/mapbox-gl-js/example/mapbox-gl-geocoder/
const MAPBOX_GEOCODER_JS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js';
const MAPBOX_GEOCODER_CSS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css';
