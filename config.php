<?php

$VERSION      = 321;
$VERSION_DATE = '22 mei 2020';

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

// Default interface language
//   en: English
//   nl: Dutch
const DEFAULT_LANGUAGE = 'nl';

