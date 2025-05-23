# roaddanger.org

A website and database for news reports about traffic crashes around the world.

Website: [roaddanger.org](https://www.roaddanger.org)

### Requirements ###
* Webserver with PHP support. Required PHP modules: curl
* MySQL/MariaDB with full text search support
* A headless browser if you want to enable metadata download from more websites

### Installation ###
* Put all files on a webserver with PHP support. 
* Import de database on a MySQL/MariaDB server with full text search capability. The database creation script is located in
[admin/createdatabase.sql](admin/createdatabase.sql)
* Read the [config.php](config.php) file. It tells you to create a `configsecret.php` file with your database connection info. Never check this one into git.
```PHP
<?php

// *** Do not fill in the database settings ***
// - Create a new file named configsecret.php and fill these settings there.
// - Exclude configsecret.php from checking into your source code repository. 
// - This is to prevent passwords from entering the source code repository.
const DB_HOST     = '';
const DB_NAME     = '';
const DB_USER     = '';
const DB_PASSWORD = '';
```
* Register yourself as a user on the website. Make yourself an administrator in MySQL by 
setting the `permission` for yourself field to `1` in the users table.

### License ###
This software is made available under the [MIT license](LICENSE).
