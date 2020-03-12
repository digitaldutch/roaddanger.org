<?php

$VERSION      = 293;
$VERSION_DATE = '12 maart 2019';

require_once 'configsecret.php';

// Parameters below are defined in configsecret.php
// Database connection data
//define('DB_HOST',     '');
//define('DB_NAME',     '');
//define('DB_USER',     '');
//define('DB_PASSWORD', '');

// Other settings
define('DOMAIN_NAME',   'hetongeluk.nl');
define('DOMAIN_EMAIL',  'info@hetongeluk.nl');
define('SERVER_DOMAIN', 'https://www.' . DOMAIN_NAME);

// Default interface language
//   en: English
//   nl: Dutch
define('DEFAULT_LANGUAGE', 'nl');

