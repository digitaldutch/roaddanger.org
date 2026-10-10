# roaddanger.org

A website and database for news reports about traffic crashes around the world.

Website: [roaddanger.org](https://www.roaddanger.org)

### Requirements ###
* Apache webserver with `AllowOverride All`. The [.htaccess](.htaccess) files send all pages to `index.php`.
* Https. Login cookies are secure cookies. Redirect http to https in your server configuration.
* PHP 8.3 or newer. Required PHP modules: curl, intl, mbstring, pdo_mysql
* MariaDB (recommended, the live website runs 10.11) or MySQL 8, both with full-text search support.
  On MySQL the site sets the session `sql_mode` to the MariaDB default, because some queries do not work with the
  stricter MySQL 8 defaults.
* Optional: the Chromium headless browser to download article url's. See `HEADLESS_BROWSER_COMMAND` in [config.php](config.php).
* Optional: an [OpenRouter](https://openrouter.ai) API key for the AI features and a [HERE](https://developer.here.com) API key
  for finding crash coordinates.

### Installation ###
1. Put all files on a webserver with PHP support.
2. Create a database user. The user needs the `CREATE` privilege, because the next steps create the database.
   You can also create an empty `utf8mb4` database yourself and give the user access to it only.
3. Copy [config_secret.example.php](config_secret.example.php) to `config_secret.php` and fill in your database
   connection info and API keys. Never check `config_secret.php` into git. It is in `.gitignore`.
   Every machine (live server, development PC) has its own `config_secret.php`.
4. Create the database, fill it with the initial data and create the first administrator:
   ```bash
   php install/init_database.php
   ```
   The script creates the database if it does not exist, creates all tables from
   [install/createdatabase.sql](install/createdatabase.sql) and adds the data from
   [install/init_data.sql](install/init_data.sql). It refuses to run when the database already contains tables.
   If it fails halfway, drop the database and run it again.

   The initial data is public data that every roaddanger website needs. **The site does not work without it.**
   * `languages`: the user interface texts (English, Dutch, German, French and Spanish)
   * `countries`
   * `longtexts`: the texts of the info pages
   * `ai_models` and `ai_prompts`: the settings for the AI features. The prompts belong to user 1, which is the
     administrator created by the script.

   At the end the script asks for the email address, name and password of the first user and makes this user
   administrator. Run `php install/create_admin_user.php` to add another administrator later, or `php install/init_database.php --no-user`
   to skip the question.
5. Open the website and log in with the account you just created.
6. Optional: add made-up test data (400 crashes with articles and people) to try the website:
   ```bash
   php install/seed_test_data.php
   ```
   It refuses to run when the database already contains crashes.
7. Optional: no Apache? Try the website on your own computer with PHP's built-in web server. Run this in the
   project folder and open http://localhost:8000. Only use it on localhost. It is not a production server.
   ```bash
   php -S localhost:8000 -t .
   ```

### Changes ###
See [CHANGELOG.md](CHANGELOG.md).

### License ###
This software is made available under the [MIT license](LICENSE).
