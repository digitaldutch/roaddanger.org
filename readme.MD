# hetongeluk.nl

A database for traffic accidents in the Netherlands.

Website: [hetongeluk.nl](https://www.hetongeluk.nl)

### Requirements ###
Webserver with PHP
MySQL or MariaDB with full text search support

### Installation ###
* Put all files on a webserver with PHP support. 
* Import de database on a MySQL/MariaDB server with full text search capability. The creation script is located in
[beheer/createdatabase.sql](beheer/createdatabase.sql)
* Create a `configsecret.php` file with your database connection info. Never check this one into git.
```PHP
<?php

// Data we do *not* want the source code repository
define('DB_NAME',     '');
define('DB_USER',     '');
define('DB_PASSWORD', '');
define('DB_HOST',     '');
```
* Register yourself as a user. Make yourself an administrator by setting the `permission` field to 1 in the users table.

### License ###
This software is made available with the MIT license.
