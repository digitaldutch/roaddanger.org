# thecrashes.org

A database for news reports about traffic crashes in the Netherlands.

Website: [thecrashes.org](https://www.thecrashes.org)

### Requirements ###
* Webserver with PHP support
* MySQL/MariaDB with full text search support

### Installation ###
* Put all files on a webserver with PHP support. 
* Import de database on a MySQL/MariaDB server with full text search capability. The database creation script is located in
[admin/createdatabase.sql](beheer/createdatabase.sql)
* Read the [config.php](config.php) file. It tells you to create a `configsecret.php` file with your database connection info. Never check this one into git.
```PHP
<?php

// Data we do *not* want in the source code repository
const DB_HOST     = '';
const DB_NAME     = '';
const DB_USER     = '';
const DB_PASSWORD = '';
```
* Register yourself as a user on the website. Make yourself an administrator in MySQL by 
setting the `permission` for yourself field to `1` in the users table.

### License ###
This software is made available under the [MIT license](LICENSE).
