<?php

// Copy this file to config_secret.php and fill in the settings.
// - config_secret.php is excluded from git, so passwords never enter the source code repository
//   and local settings on your server are not overwritten.
// - Every machine (live server, development PC) has its own config_secret.php.

// Database settings
const DB_HOST     = 'localhost';
const DB_NAME     = 'database_name';
const DB_USER     = 'database_user';
const DB_PASSWORD = 'database_password';

const EMAIL_FOR_ERRORS = 'you@your_domain.com';

// OpenRouter is used for processing several AI tasks
const OPENROUTER_API_KEY = 'your_openrouter_api_key';

// HERE is used for finding the exact coordinates of crashes based on a location description
// AI itself is much less accurate than the HERE API
const HERE_API_KEY = 'your_here_api_key';
