<?php

$VERSION      = 402;
$VERSION_DATE = '22 October 2021';

require_once 'configsecret.php';

// Leave these empty and configure you database connection in configsecret.php
// This is to prevent passwords from entering the source code repository
// const DB_HOST     = '';
// const DB_NAME     = '';
// const DB_USER     = '';
// const DB_PASSWORD = '';

// Note: The dot is added to make sure cookies works across all subdomains
const COOKIE_DOMAIN = '.thecrashes.org';

define('DEFAULT_COUNTRY_ID', 'UN');

