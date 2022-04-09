<?php

$VERSION      = 468;
$VERSION_DATE = '9 April 2022';

require_once 'configsecret.php';

// *** Do not fill in the database settings ***
// - Create a new file named configsecret.php and fill these settings there.
// - Exclude configsecret.php from checking into your source code repository.
// - This is to prevent passwords from entering the source code repository.
// const DB_HOST     = '';
// const DB_NAME     = '';
// const DB_USER     = '';
// const DB_PASSWORD = '';

// Note: The dot is added to make sure cookies works across all subdomains
const COOKIE_DOMAIN = '.roaddanger.org';
const WEBSITE_TITLE = 'Roaddanger.org';

const DEFAULT_COUNTRY_ID = 'NL';

// https://docs.mapbox.com/mapbox-gl-js/guides/
const MAPBOX_GL_JS  = 'https://api.mapbox.com/mapbox-gl-js/v2.7.0/mapbox-gl.js';
const MAPBOX_GL_CSS = 'https://api.mapbox.com/mapbox-gl-js/v2.7.0/mapbox-gl.css';

// https://docs.mapbox.com/mapbox-gl-js/example/mapbox-gl-geocoder/
const MAPBOX_GEOCODER_JS  = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js';
const MAPBOX_GEOCODER_CSS = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css';
