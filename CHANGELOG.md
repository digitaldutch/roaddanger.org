# Changelog

28 october 2026 v818:
- Cleaning of article texts: rules for 1twente.nl (photo credit News United, Google preferred source advert, tip line, closing line). A line like "Vandaag 18:17" is removed for all websites.

28 october 2026 v817:
- Export page: the option to include the full text of the articles uses the checkbox images of the menus (white on the dark page) instead of the browser checkbox (.exportOption in main.css).

28 october 2026 v816:
- Export page: moderators get a checkbox Include the full text of the articles (alltext), off by default. Without it they get the public file. The server checks that the user is a moderator (ajaxExport option includeAllText).

28 october 2026 v815:
- The public export (and /api/crashes.json.gz) no longer has the full text of the articles (alltext): the licence of some sources does not allow publishing it. Moderators get a download with alltext. The cache folder is public, so that file has a name that cannot be guessed (made with the database password). The download name stays roaddanger_data_all_text_date.json.gz.
- Export page: the field description of alltext is a row of its own.

28 october 2026 v814:
- Export page, data specification: a description of all fields of crashes, persons and articles (type, null values, date format), a shortened JSON example with a Copy button, and a table of the country codes (loaded from the countries of the website).
- The tables of codes (transportation mode, health, countryid) are next to each other. The rows of answered_by_type were outside the table body.
- The headings h2 and h3 on the export page are easier to tell apart from the text (styles under #export in main.css).

28 october 2026 v813:
- Stable url for automatic downloads of the crash data: https://roaddanger.org/api/crashes.json.gz (new api/crashes.php, rewrite rule in .htaccess). It sends the daily export, makes it when it is older than 24 hours, and supports If-Modified-Since and ETag (answer 304 if unchanged). No session or cookie. Do not use the /cache/ urls for this: they can change.
- The export code moved from admin/ExportHandler.php to general/ExportFiles.php, used by the export page and the api.
- Export page: a new part Automatic download with the url, notes, a curl and a Python example. Code blocks have a fixed width font and a Copy button (copyCodeBlock in js/utils.js, .codeBlock styles in main.css).

27 october 2026 v812:
- Add crash popup, duplicate check: each crash that may be the same has a button Add article to this crash. Only the article is added, the data of that crash does not change (saveArticleCrash option articleOnly, also works for helpers). Then you go to that crash.
- The duplicate check shows why a crash looks the same, one line each: same place (with the distance) or place nearby, same day, the same words in the title, same transportation modes. The link opens in a new tab.
- The questions "Replace crash data?" and the new one use the confirm window of the site, not the one of the browser.
  New texts in the table languages: add_article_to_this_crash, add_article_to_found_crash_question, similar_reason_exact_place, similar_reason_near_place, similar_reason_same_day, similar_reason_title_words, similar_reason_same_modes.

27 october 2026 v811:
- Add and edit crash popups: the blue focus border of a field was cut off on the left side. The scrolling area now has a little room around the fields.

26 october 2026 v810:
- The map does not show crashes with a place that is less accurate than 3000 metres (crashes.locationaccuracy, constant MAP_MAX_ACCURACY_METERS in general/utils.php). A pin in the middle of a town would look like an exact place. The crash stays in the lists and on its own page. Crashes without a known accuracy stay on the map.

26 october 2026 v809:
- Duplicate check when you add a crash: after the AI has read the article, a yellow box shows crashes that may be the same (new file general/SimilarCrashes.php, ajax function findSimilarCrashes).
  - Only crashes of the day before, the same day or the day after, in the same country. A crash that is too far away for the accuracy of both places is never the same. Two exact places count heavily, two towns hardly. Common words in the title, the same kinds of vehicles and the same day also count; people in other vehicles count against.
- Merge crashes window made clearer:
  - The crash at the top is deleted and its articles move to the crash that you keep. Both have a title for each article, a place and the people. A swap button changes which one is kept.
  - The list shows the crashes that look the same, most likely first. Typing a text searches 60 days around the crash. The date margin select is gone.
  - Each crash in the list shows the distance and the difference in days. The selected crash has a green frame. The Merge button is off until a crash is selected.
  - Merge without a selected crash showed an error and then went on anyway. The crash list of the page was not updated after a merge.
  New texts in the table languages: similar_crashes_found, merge_crashes_explanation, merge_remove_crash, merge_keep_crash, merge_select_crash, merge_likely_same, merge_swap, merge_no_similar, merge_same_day, merge_days_later, merge_days_earlier.

26 october 2026 v808:
- Edit crash, crash tab: a new button Read data from article. The AI reads the article again and fills date, humans, characteristics and place, as when you add a crash. It asks first if the data may be replaced. Nothing is saved until you click Save.
  New text read_data_from_article in the table languages.

26 october 2026 v807:
- Edit crash, map: a new button Remove location removes the pin. Clicking the marker no longer asks "Delete location?". Before, this question also appeared after moving the marker.
  New text remove_location in the table languages. Change edit_location_instructions too: it still says "Click marker to delete a location".

26 october 2026 v806:
- New column crashes.locationaccuracy: the estimated accuracy of the place in metres, NULL if unknown. Add the column to the database before you upload this version:
  `ALTER TABLE crashes ADD COLUMN locationaccuracy int unsigned NULL COMMENT 'estimated accuracy of the place in metres. NULL: unknown' AFTER locationdescription;`
  If the database refuses because of old dates 0000-00-00, run `SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';` first. The column is also in install/createdatabase.sql.
  - Add and edit crash save it, and the crash data loads it. The AI answer fills it from the geocoder (50 m for an intersection, up to a few kilometres for a road or a town). A person who places or moves the pin on the map sets it to 25 m. Deleting the pin empties it.
- Better coordinates from the AI answer: new function geocodeAiLocation in general/utils.php, also used by the add crash window.
  - HERE stays the first choice, as it is the only one that finds intersections. A result that is far from the coordinates of the AI is not used.
  - For the Netherlands, PDOK (the Dutch government geocoder, free) is asked when HERE finds nothing, or only the town for a text with a street. PDOK finds small paths, roads and junction pieces that HERE does not know. New function pdokGeocode.
- New script install/backfill_locations.php adds the place to crashes that have none. The AI reads the article (new prompt function article_location, made with --create-prompt or in the AI prompt builder) and the geocoder finds the coordinates.
  - Dry run by default, --validate to compare with crashes that have a place, --apply to add the places, --revert to remove them. Every crash seen is in the table crashes_location_backfill. Several parts can run at the same time (--part=2/4).
  - Only places that are exact to 3000 metres are added (--max-accuracy). The accuracy is saved in crashes.locationaccuracy if the column exists.
- OpenRouterAIClient does not load config_secret.php again if a script loaded a config already. The place article_location is in general/AiPromptFunctions.php.

26 october 2026 v805:
- New AI prompt builder page (research/ai_prompt_builder):
  - Three columns: the list of prompts, the editor and the test. The list replaces the Load window. A prompt that the website uses has a label with the name of its place.
  - Test a prompt on the latest articles, random recent articles or one article. Three run at the same time, with a Stop button. Every article gets a card with the people per vehicle, the place, the time, the cost and the tokens, and the raw response and the article text to open. At the top: the median time and the cost in total and per 1000.
  - The output format has a check for valid JSON and shows the number of characters of the 5000 that the database holds. A Compact button makes the JSON shorter.
  - Saving checks the output format. Save, Save copy and Delete keep working. Save copy makes a prompt that the website does not use.
  - OpenRouterAIClient::chatWithMeta also returns the cost (usage.cost) and the time of the request. New ajax functions aiFindTestArticles and aiGetCredits.
- "Use on live website" instead of the select System function:
  - New general/AiPromptFunctions.php lists the places on the website where an AI prompt is used, with a name and a description: article_analyst, questionnaire_answerer, article_reframe_UVA, article_reframe and translator (planned).
  - A prompt that the website does not use has a button Use on live website. It opens a window with the places and the prompt that each place uses now. The new prompt replaces the old one in one step (ajax function aiUseOnLive). The old prompt stays in the list without a place.
  - Saving a prompt does not change its function any more. A prompt that the website uses cannot be deleted. A prompt that the website uses cannot get another place.
- The page /reframe_text/ asks for the prompt article_reframe. Before it looked for article_reframe_text, that does not exist, so the page gave an error. A missing prompt gives a clear message.

26 october 2026 v804:
- AI prompt builder: saving a prompt without a function (also Save copy) failed with "Duplicate entry '' for key web_function" when another prompt already had an empty function. An empty function is now saved as NULL, as the column is unique. A prompt without a function is shown as [none].

26 october 2026 v803:
- AI prompt builder:
  - The Save button did nothing for a prompt that was loaded from the list and has no function. Now it is saved.
  - Save copy makes a new prompt without the function of the original, as a function can only belong to one prompt. Before, it asked if you wanted to overwrite and then failed on the unique function.

26 october 2026 v802:
- Better coordinates in the add crash window. The geocoder (HERE) in geocodeLocation (general/utils.php):
  - Only accepts a result in the country that the AI gives (in=countryCode). Before, a street or village with the same name in another country could be returned. New file general/iso_country_codes.php converts the two letter country code to the three letter code of HERE.
  - When nothing is found, the optional text geocoder_fallback of the AI (town and country) and less detailed versions of the text are tried, at most 4 requests. Every version keeps a town and a country, as a road number alone is found anywhere.
  - Returns the result type (houseNumber, intersection, street, locality, ...), a rough accuracy in metres (accuracyMeters) and simplified (true for a less detailed text). A request has a time limit of 10 seconds.
  - extractDataFromArticle does not use a result that is far from the coordinates of the AI (more than 50 km, or 25 km for a less detailed text). The coordinates of the AI are used then.

22 october 2026 v801:
- New robots.txt: search engines and other crawlers that follow the rules may only fetch the home page and the about page, with the files these pages need.
- The page /aboutthissite/ is now /about/ (there is no redirect from the old url). The links in the cookie banner, the menu and the long texts (child_victims_info, counter_party_info, website_info) are changed, also in install/init_data.sql.

22 october 2026 v800:
- The headless browser, used to fetch articles, now gets a temporary profile folder (--user-data-dir) that is removed after each run. Before, every run left a profile with cache of several MB in the home folder of the server, which filled the disk.
  - New function removeFolder in general/meta_parser_utils.php. buildHeadlessBrowserCommand has a second parameter, the profile folder.

20 october 2026 v799:
- Article text cleaning:
  - The rules for the DPG Media websites are shared by ad.nl, bd.nl, bndestem.nl, destentor.nl, ed.nl, gelderlander.nl, pzc.nl, parool.nl, trouw.nl and volkskrant.nl: "Maak ons je Google-favoriet", "Interessant voor jou", "Blijf op de hoogte" and the name of the editorial team ("Redactie", "112Redactie"). Destentor keeps its own section names.
  - A date and time on a line of its own is also removed with a comma directly after the year ("20 oktober 2026, 09:49"), with a dash or with a space.

20 october 2026 v798:
- Article text cleaning:
  - Removed: a line with place, date and time of publication on its own ("Groningen 19 oktober 16:08") and the navigation of a blog ("Bericht navigatie", "Berichtnavigatie", "Post navigation").
  - A first paragraph that starts with place, date and time ("Groningen 19 oktober 16:08 Maandagmiddag ...") is also recognised as a repeat of the text that follows. This happens when the description of a page is put in front of the article text.

20 october 2026 v797:
- New page /bookmarklet/ with a bookmark for websites that block the server (Cloudflare check). The user drags it to the bookmarks bar once and clicks it on the article page. The page is sent from the browser of the user to the website, the server reads it like a downloaded page and the add crash window opens with the article filled in. Nothing is saved on the server.
  - Text that the user selected on the page is used as the full text.
  - Without text in the page tags, the add window finds the article in the page with Readability (new library scripts/Readability.js, Apache 2.0, version 0.6.0). The text is cleaned by the server, so the user sees what will be saved.
  - The same for the fetch button: if the page has no article text in its tags, the server also returns the page and the window looks for the article in it.
  - The message when fetching is blocked or finds no data has a link to the bookmark page.
- New ajax function cleanArticleText: cleans a text like an article text, without saving.
- Article text cleaning: a short line that starts with © is a photo credit and is removed.
- parseMetaDataFromHtml and addCleanedAllText in general/meta_parser_utils.php are shared by the fetch button and the bookmark.
- New texts: bookmarklet_title, bookmarklet_intro, bookmarklet_install, bookmarklet_use, bookmarklet_select, bookmarklet_link_text.

19 october 2026 v796:
- The window to add or edit a crash has two tabs: Article and Crash.
- A new crash starts at the Article tab with one button, "Continue to crash data". It checks date, title and full text, opens the Crash tab and extracts the crash data from the article with AI.
  - The Crash tab shows a spinner and a wait text while the AI works, then the fields with a notice to check the data.
  - Going back and forth does not extract again. If the article changed, it asks before the crash data is replaced. A failed extraction is not repeated, so data filled in by hand is kept.
  - The button "Extract data with AI" is gone.
- Editing a crash from the crash menu opens the Crash tab, editing an article opens the Article tab. Adding an article to an existing crash does not extract data.
- The map on the Crash tab has the right size when the tab opens. The tab is tidier: groups with a line between them, country and address on one row, a lower map.
- New texts: continue_to_crash_data, extracting_crash_data, replace_crash_data_question. Changed: ai_extract_info. No longer used: extract_data_from_text, add_article_date, add_full_article_text.

16 october 2026 v795:
- The summary of an article is always made from its full text when the article is saved: the first paragraph, or more if it is short, up to about 300 characters, cut at the end of a sentence.
  A heading in front is skipped. Before, a summary that was filled was not changed when editing.
- New script install/regenerate_summaries.php makes the summaries of the articles that are already in the database in the same way. Dry run by default, the old summaries are saved in a backup table and can be restored.
  Articles without a full text keep their summary.

16 october 2026 v794:
- Article text cleaning: lines with the name, date and time of publication are removed ("Carin Beenen 11-02-19, 08:52 Laatste update: 12:40"). They were in 7% of the articles.
- install/clean_article_texts.php:
  - Option --port=3307 for an SSH tunnel next to a local MySQL, and a clear message if the connection fails.
  - The report no longer counts a removed hidden character (soft hyphen, zero-width space) as a removed line.

16 october 2026 v793:
- install/clean_article_texts.php: option --config=path to use the config file of another website, and the dry run asks the database for a read-only session.

16 october 2026 v792:
- The full text of an article is cleaned when it is fetched and when it is saved (new class ArticleTextCleaner, rules in general/article_text_rules.php):
  - Tidy paragraphs: one blank line between paragraphs, no trailing or double spaces, no hidden characters.
  - Removed: tip requests and WhatsApp lines, newsletter and liveblog adverts, links to other articles, bylines, photo credits, dates and comment counts on a line of their own.
  - The first paragraph is removed if the text repeats it, also with a place name in front ("Veenendaal - ..."). Subheadings of the article are kept.
- New script install/clean_article_texts.php cleans the articles that are already in the database. Dry run by default, the old texts are saved in a backup table and can be restored.
- Saving an article cuts the full text at 10,000 characters, not bytes. A multibyte character could be cut in half.

16 october 2026 v791:
- install/init_data.sql: the languages (de, en, es, fr, nl) are a copy of the live table again.
  New: the texts for the full text hint and error, from_date and up_to_date in all languages, and the French corrections.
  The English texts added_crashes and added_articles, removed on the live site, are gone.

16 october 2026 v790:
- Moderators can see that crashes or articles wait for moderation:
  - A dot on the profile button in the top bar.
  - A count next to Moderations in the profile menu and in the main menu.
  - The count updates when a crash or article is approved.

16 october 2026 v789:
- Export data: the crashes export loads all persons and articles with one query each, instead of two queries for every crash.
  With 21,000 crashes this takes 1.2 seconds instead of 4 on a local database. The exported data is identical.

16 october 2026 v788:
- Export data: only one request at a time creates an export file. It takes a lot of memory and time, and the page can be called by anyone.
  - Other requests get the existing file, even if it is older than 24 hours. Without a file they get the message to try again in a minute.
  - After an attempt, also a failed one, a new export is not created for 10 minutes.
  - The crashes download now shows error messages, like the research download already did.
- The downloaded export file has the date the export was made in its name, for example roaddanger_data_all_text_2026-10-16.json.gz.

16 october 2026 v787:
- Export data fixes:
  - The exports no longer contain crashes and articles that are still waiting for moderation, and no answers about them.
  - The publication date of an article is now in ISO 8601, like the crash date. It was in the database format, and each crash had an empty publishedtime field.
  - Removed unused variables.
- The cache cleanup ran at almost every save instead of once per hour (a test value of 6 seconds was left in).

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