<?php

$VERSION      = 435;
$VERSION_DATE = '13 January 2022';

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

define('DEFAULT_COUNTRY_ID', 'NL');

