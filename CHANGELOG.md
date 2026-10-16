# Changelog

16 october 2026 v786:
- Export data: the generated export files (crashes and research data, renewed after 24 hours) are now stored in the cache folder instead of the admin folder.
  The cache folder is cleaned automatically and is not in git. The old files in the admin folder can be deleted.

16 october 2026 v785:
- Top bar: the initials circle is a bit bigger (26 pixels), so it is as high as the Add button.

16 october 2026 v784:
- Top bar, small screens:
  - The title now stays in the middle of the screen. The buttons on the right can no longer push it aside.
  - The language button moves to the navigation menu on screens narrower than 400 pixels.
- Top bar, logged in: round badge with the initials of the first name (Jan Derk gives JD) instead of the person icon.
  The first name is shown next to it when there is room, with the full name as tooltip.
  If fewer than a few characters fit, only the initials are shown.

16 october 2026 v783:
- Search icon in the top bar on all pages with filters. It opens the filters with the cursor in the search field.
  The filter button scrolls out of view, the top bar does not. A dot on the icon shows that filters are active.
- Small screens: the top bar has room for the extra icon (the title no longer gets pushed off the screen).

16 october 2026 v782:
- The summary field is removed from the add and edit crash window. It was confusing next to the full text.
  The summary shown in the lists and used in search is now made by the server from the start of the full text (max 300 characters, cut at the end of a sentence).
  An existing summary is not overwritten.
- Fetch article: the description is only put in front of the full text if the article text does not already start with it. About 1 in 6 texts had the first paragraph twice.

15 october 2026 v781:
- The full text of an article is now required when saving a crash, in the form and on the server. Research needs it.
  The hint next to the field says to copy the text from the article if it is not filled automatically.
  New text for the error and the hint in all languages (nl, en, de, es, fr).

11 october 2026 v780:
- The bus icon has a higher roof and a raked windscreen. It looked like a box.

11 october 2026 v779:
- New transportation mode icons, all in one style: straight lines and hard corners.
  The old cars looked too friendly for a website about crashes. The car has a higher bonnet and a sharp nose, the taxi a roof sign,
  the truck is clearly bigger than the van. The bus, tram, train, tractor, pedestrian, bicycle, scooters, wheelchair and moped car follow the same style.
- The one-sided crash icon, its filter button and the unknown icon are redrawn in the same style. The one-sided icon no longer depends on a font.

11 october 2026 v778:
- AGENTS.md: security rules (escaping, inline event handlers, credentials in the request body, rate limits).

11 october 2026 v777:
- Security: tooltips are shown as plain text. No tooltip uses HTML, so allowHTML is removed from all tippy calls.
  Text that ends up in a tooltip can no longer be run as HTML.

11 october 2026 v776:
- Security: final check for cross-site scripting (XSS). All places that show text from users are escaped now.
  - The title of an article was shown unescaped in the "delete article" question. A moderator could be attacked with a crafted title.
  - Also escaped: the reason of a blocked website, prompt and model names and question texts on the research pages, tooltips of questions, and the email address in the password reset email.
  - No other problems found in the pages, the url parameters and the reframe tools.

11 october 2026 v775:
- Account page redesigned: a profile card and a separate change password card. It also shows a log in button when you are not logged in.
- Security: changing your email address or password needs your current password.
  - The current password field for the email address only appears when you change the email address.
  - Wrong guesses count as failed logins, so these fields cannot be used to guess the password.
  - Changing the password has its own server function (changePassword). The profile function no longer accepts a password.
  - New texts profile, change_password, current_password and current_password_info, in English, German, Spanish, French and Dutch. Add them to the languages table before uploading. They are in install/init_data.sql for new websites.

11 october 2026 v774:
- Security: fetching a web page for the add article form is limited for visitors who are not moderators.
  - 60 free requests per hour per network, and 1000 per day for everybody. After that the block starts at 1 minute and doubles.
  - No database change: the table rate_limit_events from v766 is used.

11 october 2026 v773:
- Security: the login no longer shows whether an email address is registered.
  - The server does not send emailexists anymore. The login form shows "Password incorrect" for an unknown email address and for a wrong password.
  - A login with an unknown email address takes as long as one with a wrong password.

11 october 2026 v772:
- Security: changing your password on the profile page now ends the "stay logged in" logins on all other devices. The login of this browser stays.

11 october 2026 v771:
- Fixed: saving the profile with an invalid password (too short, or different from the confirmation) still saved the name, email and language.
  Everything is now checked first and saved in one go.
- The profile also checks that the email address is not used by another user and that the language exists.

11 october 2026 v770:
- Security: the AI functions are protected against abuse and costs.
  - Answering questionnaires with AI is now for moderators only. It saved answers in the database for anyone.
  - AI requests from other visitors (extract article data, both reframe tools) are limited per network and in total per day.
    After the free requests the block starts at 1 minute and doubles, as with login attempts. Moderators are not limited.
  - No database change: the table rate_limit_events from v766 is used.

10 october 2026 v769:
- Security: a visitor can no longer switch on development mode with a fake Host header.
  Development mode (error messages on screen, background scripts startable from the browser) now also requires that the request comes from the computer itself.
- Security: an initialization error no longer shows file names and the stack trace to visitors. It is written to the error log.
- The sender name of emails uses the trusted host name.

10 october 2026 v768:
- Security: a visitor gets a new session id when logging in (with a password or with the remember me cookie).
  A session id that someone else planted before the login can no longer be used afterwards.

10 october 2026 v767:
- Security: the server now sends security headers with every page and ajax response.
  - Other websites cannot show this website in a frame. Browsers do not guess file types. Fewer details go to other websites in the referrer.
  - Browsers keep using https for this website after a visit.

10 october 2026 v766:
- Security: limits on login attempts, registrations and password reset requests.
  - After a few free tries, the visitor is blocked. The block starts at 1 minute and doubles with every next try, up to 1 hour.
  - Applies to failed logins (per account and network, per network, per account), registrations and password reset requests.
  - The error message says how long to wait.
  - New table rate_limit_events (see install/createdatabase.sql). Create it on the server before uploading. Without the table nothing is limited.

10 october 2026 v765:
- Fixed: the AI prompt functions did not work on MySQL 8.4: the column function is now quoted in the SQL.
  This affects the AI prompts page, saving prompts, and the AI article analysis and answering.
- AGENTS.md: SQL rule about the function column.

10 october 2026 v764:
- Fixed: calling an unknown function, or a function without permission, on the research ajax page gave a PHP fatal error. It now gives a clear error message.

10 october 2026 v763:
- Security: the ajax pages only accept POST requests with a JSON body from this website. Links and forms on other websites can no longer trigger actions.

10 october 2026 v762:
- Security: login and password reset now only accept data in the request body, not in the url.

10 october 2026 v761:
- All error messages from the server and the pages are now in English (registration, profile, password reset, moderation, delete user, blocked domain message).
- The password reset page title is now in English.

10 october 2026 v760:
- Security: password reset and "stay logged in" are handled more carefully.
- Security: login and password reset data are no longer sent in the url.
- Database change. Run this before uploading this version:
  - ALTER TABLE users MODIFY passwordhash varchar(255) NULL, MODIFY passwordrecoveryid varchar(64) NULL;
  - ALTER TABLE logins MODIFY tokenhash varchar(255) NULL;
- The password reset email is now in English.
- config_secret.example.php: new optional TRUSTED_HOSTS setting for other hosts, like a test server.

10 october 2026 v759:
- Security: text, names and links from users are now shown more carefully on several pages.
- Security: links entered by users must be http or https links.
- Security: the password reset page handles its url parameters more carefully.

10 october 2026 v758:
- Security: the login check is stricter.
- Security: downloading article webpages is stricter: urls are checked, redirects are checked, timeouts and a certificate check are added.

10 october 2026 v757:
- Admin - Humans on a phone: each user is now a small card with all information, made with CSS from the same table cells.
  This replaces the hidden columns of v756.

10 october 2026 v756:
- Admin - Humans: easier to use on a phone. Id, last active, permission and registered are hidden on small screens.
  The permission and last active time are shown below the name instead. The table no longer scrolls sideways.

10 october 2026 v755:
- README: optional installation step to try the website with PHP's built-in web server.

10 october 2026 v754:
- Friendly urls: the .htaccess files now use FallbackResource instead of mod_rewrite.
- The http to https redirect is removed from the .htaccess files. Set it in the server configuration.
- README: requirements updated.

10 october 2026 v753:
- cache folder added to .gitignore.

10 october 2026 v752:
- New AGENTS.md with instructions for AI coding agents.
- Private folder test renamed to scratch (not in git).

9 october 2026 v751:
- Installation: install/init_database.php now ends with a message that you can open the website and log in.

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