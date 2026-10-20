<?php

/**
 * Adding an article from a website that blocks the server (for example with a Cloudflare check).
 *
 * GET:  the page with the bookmark that the visitor drags to the bookmarks bar.
 * POST: sent by that bookmark from the article page the visitor is reading: the url and the html of the page.
 *       The server reads the title, text and other data from it, the same way as for a downloaded page, and passes the
 *       result to the add article window of the website (see showPendingBookmarkArticle in main.js).
 */

require_once '../initialize.php';
require_once '../HtmlBuilder.php';
require_once '../general/RateLimit.php';
require_once '../general/meta_parser_utils.php';
require_once '../general/ArticleTextCleaner.php';

global $database;

const BOOKMARKLET_MAX_HTML_BYTES = 5 * 1024 * 1024;
const BOOKMARKLET_MAX_HTML_FOR_TEXT_BYTES = 1500 * 1024;
const BOOKMARKLET_MAX_SELECTION_CHARS = 50000;

function bookmarkletMessagePage(string $message): void {
  $text = htmlspecialchars($message, ENT_QUOTES);
  $name = htmlspecialchars(WEBSITE_NAME, ENT_QUOTES);
  echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">" .
    "<title>$name</title></head><body style=\"font-family: sans-serif; margin: 40px;\"><p>$text</p><p><a href=\"/\">$name</a></p></body></html>";
}

function bookmarkletReceive(): void {
  global $database;

  $url = trim((string)($_POST['url'] ?? ''));
  $html = (string)($_POST['html'] ?? '');

  $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
  if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7f]/', $url) || ! in_array($scheme, ['http', 'https'], true)) {
    bookmarkletMessagePage('Invalid url');
    return;
  }

  if ($html === '' || strlen($html) > BOOKMARKLET_MAX_HTML_BYTES) {
    bookmarkletMessagePage('The page is empty or too large');
    return;
  }

  // Parsing is cheap, but the same limit as for fetching web pages keeps this from being used as a free service
  $wait = RateLimit::fetchWaitSeconds($database);
  if ($wait > 0) {
    http_response_code(429);
    bookmarkletMessagePage(RateLimit::fetchWaitMessage($wait));
    return;
  }
  RateLimit::record($database, RateLimit::FETCH);

  $result = parseMetaDataFromHtml($url, $html);
  $selection = trim((string)($_POST['selection'] ?? ''));

  // Without tags there can still be an article text: selected by the visitor, or found in the page by the website
  if ($result['tagCount']['total'] === 0 && $selection === '' && strlen($html) > BOOKMARKLET_MAX_HTML_FOR_TEXT_BYTES) {
    bookmarkletMessagePage(translate('No_data_found_on_web_page'));
    return;
  }

  $media = $result['media'];
  if (empty($media['url'])) $media['url'] = $url;

  // Text that the visitor selected on the page is exactly what they want: it replaces what the page tags gave
  if ($selection !== '') {
    $media['alltext'] = ArticleTextCleaner::clean(mb_substr($selection, 0, BOOKMARKLET_MAX_SELECTION_CHARS), $url, $media['sitename'] ?? '');
  }

  $payload = ['media' => $media];

  // Without text the website looks for the article in the page itself (Readability, see main.js). The page waits in
  // the browser tab, which can only keep a few MB, so larger pages are skipped.
  if (empty($media['alltext']) && strlen($html) <= BOOKMARKLET_MAX_HTML_FOR_TEXT_BYTES) $payload['html'] = $html;

  // The data is passed to the website in this browser tab only. The data is put in the script with all characters
  // that could end it escaped, and the website shows it in input fields, never as html.
  $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
  $json = json_encode($payload, $flags);

  echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>" . htmlspecialchars(WEBSITE_NAME, ENT_QUOTES) . "</title></head><body>" .
    "<script>try {sessionStorage.setItem('bookmarkArticle', JSON.stringify($json));} catch (e) {} location.replace('/');</script>" .
    "</body></html>";
}

function bookmarkletOrigin(): string {
  $https = (! empty($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] !== 'off');
  $host = getTrustedHost();

  // A development site can run on another port. The name from the request is only used if it is the real server name.
  $port = (string)($_SERVER['SERVER_PORT'] ?? '');
  $isRequestHost = $host === strtolower($_SERVER['SERVER_NAME'] ?? '');
  $portSuffix = ($isRequestHost && ! in_array($port, ['', '80', '443'], true))? ':' . $port : '';

  return ($https? 'https' : 'http') . '://' . $host . $portSuffix;
}

function bookmarkletInstructionsPage(): void {
  $texts = translateArray(['Bookmarklet_title', 'Bookmarklet_intro', 'Bookmarklet_install', 'Bookmarklet_use', 'Bookmarklet_select', 'Bookmarklet_link_text']);

  // No % in the code: browsers decode it in a bookmark address. The page is sent as a form to the website, in a new tab.
  $action = bookmarkletOrigin() . '/bookmarklet/';
  $code = "javascript:(function(){var f=document.createElement('form');f.method='POST';f.action='" . $action . "';f.target='_blank';" .
    "f.acceptCharset='UTF-8';[['url',location.origin+location.pathname+location.search],['html','<!DOCTYPE html>'+document.documentElement.outerHTML],['selection',String(getSelection())]]" .
    ".forEach(function(p){var t=document.createElement('textarea');t.name=p[0];t.value=p[1];f.appendChild(t)});" .
    "document.body.appendChild(f);f.submit();f.remove()})();";
  $href = htmlspecialchars($code, ENT_QUOTES);

  $mainHTML = <<<HTML
<div id="pageMain">
  <div class="pageInner">
    <div class="pageSubTitle">{$texts['Bookmarklet_title']}</div>

    <p>{$texts['Bookmarklet_intro']}</p>
    <p>{$texts['Bookmarklet_install']}</p>
    <p><a class="button" href="$href" onclick="return false;">{$texts['Bookmarklet_link_text']}</a></p>
    <p>{$texts['Bookmarklet_use']}</p>
    <p>{$texts['Bookmarklet_select']}</p>
  </div>
</div>
HTML;

  echo HtmlBuilder::getHTMLBeginMain($texts['Bookmarklet_title'], '', 'initPageUser') .
    $mainHTML .
    HtmlBuilder::getHTMLEnd();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') bookmarkletReceive();
else bookmarkletInstructionsPage();
