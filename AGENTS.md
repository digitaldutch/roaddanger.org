# Instructions for AI coding agents

roaddanger.org is a website and database for news reports about traffic crashes around the world.
PHP without a framework, vanilla JavaScript, MariaDB/MySQL. Pages are built on the server (`HtmlBuilder.php`).
Data is loaded with AJAX calls to `ajax*.php` files, which use a handler class (`*Handler.php`).

## Compatibility
* PHP 8.3 or newer.
* SQL and PHP must work on **MariaDB** (the live website runs 10.11) and on **MySQL 8**.
  `database.php` sets the session `sql_mode` and the query time limit for the database type. Keep it that way.
* In SQL, always put the column `function` (table `ai_prompts`) between backticks. MySQL 8.4 does not accept it without.
* No Composer and no framework. Libraries are in `scripts/`.
* Code style: two spaces for indentation. Match the code around your change.

## Versions and the changelog
The maintainer raises the version and writes the changelog entry when committing. Every commit is one version:
1. Raise `$VERSION` (and `$VERSION_DATE` if the date changed) in `config.php`.
2. Add an entry at the top of `CHANGELOG.md`, in the same style as the entries below it:
   `9 october 2026 v751:` followed by `- ` lines, with sub-items indented by two spaces. Keep it short.
3. Use the text of that entry as the commit message.

In pull requests from others, do not change `$VERSION` or `CHANGELOG.md`. Describe the change in the pull request.

## Secrets and databases
* Never read, print or commit `config_secret.php`. It holds passwords and API keys and is in `.gitignore`.
  `config_secret.example.php` is the template.
* Develop against a separate local database. Never run scripts against a database that is in use.
* A few results are cached for several minutes in `cache/` (see `general/Cache.php`). Old data after a
  database change can be that cache.

## Database and installation
* [install/](install/) holds the scripts to set up a website. This folder is **not uploaded to servers**.
  Do not put code in it that the running website needs.
  * `createdatabase.sql`: the table definitions. Update it when you change a table. It must load on MySQL in strict mode.
  * `init_data.sql`: the public data every website needs (languages, countries, long texts, AI models and prompts).
  * `init_database.php`, `create_admin_user.php`, `seed_test_data.php`: see the README.
* The user interface texts are stored in the database (table `languages`), not in files. Use `translate('Key')`
  or `translateArray([...])`. A new text needs a new key in that table.
* The code expects questionnaire 7 to be the public media humanization test (home page graph).

## Running and testing
* There are no automated tests.
* Run PHP scripts from the command line with `-d xdebug.mode=off`. An IDE that listens for a debugger
  can pause the script and make it look stuck.
* Check changes in the browser on the local website.
* Keep the README short and direct.
