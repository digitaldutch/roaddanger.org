# Changelog

9 october 2026 v750:
- Fixed: Admin - Humans: the menu of a user was cut off when the table was short, so Delete could not be reached.
  The menu now opens on top of the page.

9 october 2026 v749:
- Installation:
  - New install/seed_test_data.php adds made-up test data (400 crashes with articles and people) to a new website.
    This is an optional step in the README.

9 october 2026 v748:
- Installation:
  - New install/init_database.php creates the database and tables and adds the initial data from install/init_data.sql:
    languages, countries, long texts, AI models and AI prompts.
  - New install/create_admin_user.php creates an administrator. init_database.php asks for the first administrator.
  - README rewritten: requirements and installation instructions are up to date.
- MySQL 8 is now supported besides MariaDB:
  - database.php: new function isMariaDB(). Query time limit and sql_mode are set for the database type.
- Fixed: install/createdatabase.sql could not be loaded on a fresh database:
  - Foreign keys referenced a non-existing table "c" instead of crashes.
  - Tables are now created in dependency order (crashes before articles and crashpersons).
  - Column "function" in ai_prompts is now quoted.
  - articles.publishedtime default is now current_timestamp() instead of '0000-00-00 00:00:00'.

9 october 2026 v747:
- Fixed: admin/createdatabase.sql could not be loaded on a fresh database:
  - Foreign keys referenced a non-existing table "c" instead of crashes.
  - Tables are now created in dependency order (crashes before articles and crashpersons).
  - Column "function" in ai_prompts is now quoted.
  - Added a note about the relaxed sql_mode needed for the '0000-00-00' default in articles.

9 october 2026 v746:
- Code: Config template moved from a comment in config.php to config_secret.example.php.
- Code: A clear error message is shown when config_secret.php is missing.

9 october 2026 v745:
- Code: admin/createdatabase.sql updated to match the current database structure.

30 september 2026 v744:
- Filter close buttons now have a round background when hovering over them.

26 september 2026 v743:
- Statistics General:
  - Health and persons filter options added
- Filter:
  - Added year options (2026, 2025, etc).
  - Fixed: "Last x years" filter in research did not return exactly 2 years, it included the complete first year.
  - Code: SQL improved to use indices for filtering on a year.

26 september 2026 v742:
- Fixed: Webpage layout where navigation or filter sections were sometimes visible
- Fixed: French plural for crashes fixed from "crash" to "crashs"

26 september 2026 v741:
- General Statistics page now has a filter option
- Custom date filter now shows the Start en End dates on the filter status bar
- Fixed: Dead_(multiple) translation error in Dutch
- Added CHANGELOG.md file

2 August 2026 v739:
- Chromium: no-sandbox option again added as it is required on new server.

29 July 2026 v738:
- Chromium: --no-sandbox option removed as we run on a new server
- Improved documentation for installation

15 May 2026 v737:
- Questionnaire results:
- Bars now show number of crashes for each segment score.

7 May 2026 v736:
- Answer questionnaire questions form can now be closed by clicking on the background.