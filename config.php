<?php

$VERSION      = 350;
$VERSION_DATE = '9 August 2020';

require_once 'configsecret.php';

// ***** Leave these empty and configure you database connection in configsecret.php *****
// ***** This is to prevent your passwords from entering the source code repository  *****
// const DB_HOST     = '';
// const DB_NAME     = '';
// const DB_USER     = '';
// const DB_PASSWORD = '';

// Other settings
const DOMAIN_NAME   = 'hetongeluk.nl';
const DOMAIN_EMAIL  = 'info@hetongeluk.nl';
const SERVER_DOMAIN = 'https://www.' . DOMAIN_NAME;

// Default interface language. Default is English except for Dutch name containing "hetongeluk.nl"
$domain = $_SERVER['SERVER_NAME'];
if (strpos($domain, 'hetongeluk') !== false) $language = 'nl';
else $language = 'en';

define('DEFAULT_LANGUAGE_ID', $language);

