<?php

class TMetaParser {

  private string $url;

  function __construct(string $url) {
    $this->url = $url;
  }
  function downloadWebpage(string $urlDownload): bool|string {
    // download url can be different from website url if an archive is used to retrieve the page content
    $headers = [
      "Accept-Encoding:gzip,deflate",
      'User-Agent:' . $_SERVER['HTTP_USER_AGENT']
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $urlDownload);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_ENCODING,"gzip");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION,true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,false); // Don't verify authenticity of peer

    $data = curl_exec($curl);

    curl_close($curl);
    return $data;
  }

  function downloadUsingHeadlessBrowser($urlDownload): null|string {
    $command = HEADLESS_BROWSER_COMMAND . $urlDownload;

    exec($command, $output, $statusCode);
    if ($statusCode === 0) {
      return implode("\n", $output);
    } else {
      throw new RuntimeException("Unable to download webpage from headless browser. Set correct command in config.php.");
    }
  }

  function getPageMediaMetaData(string $html, string $url): array{
    $meta = [
      'json-ld' => [],
      'og' => [],
      'twitter' => [],
      'article' => [],
      'itemprop' => [],
      'other' => []
    ];

    // Handle UTF 8 properly
    $html = mb_convert_encoding($html, 'UTF-8',  mb_detect_encoding($html, 'UTF-8, ISO-8859-1', true));

    // Some website html encode their tag names
    $html = html_entity_decode($html);

    // See Google structured data guidelines https://developers.google.com/search/docs/guides/intro-structured-data#structured-data-guidelines
    // json-ld tags. Used by Google.

    // Find JSON-LD meta data
    $matches = null;
    preg_match_all('~<\s*script\s+[^<>]*ld\+json[^<>]*>(.*)</script>~iUs', $html, $matches);
    for ($i=0; $i<count($matches[1]); $i++) {
      $ldJson = trim($matches[1][$i]);
      $this->parse_ld_json($ldJson, $meta['json-ld']);
    }

    // Open Graph tags
    // Check for both property and name attributes. nu.nl uses incorrectly name
    $matches = null;
    preg_match_all('~<\s*meta\s+[^<>]*(?:property|name)=[\'"](og:[^"]+)[\'"]\s+[^<>]*content="([^"]*)~i', $html,$matches);
    for ($i=0; $i<count($matches[1]); $i++) $meta['og'][$matches[1][$i]] = $matches[2][$i];

    // Twitter tags
    $matches = null;
    // Check for both property and name attributes. nu.nl uses incorrectly name
    preg_match_all('~<\s*meta\s+[^<>]*(?:property|name)="(twitter:[^"]+)"\s+[^<>]*content="([^"]*)~i', $html,$matches);
    for ($i=0; $i<count($matches[1]); $i++) $meta['twitter'][$matches[1][$i]] = $matches[2][$i];

    // Article tags
    $matches = null;
    preg_match_all('~<\s*meta\s+[^<>]*(?:property|name)="(article:[^"]+)"\s+[^<>]*content="([^"]*)~i', $html,$matches);
    for ($i=0; $i<count($matches[1]); $i++) $meta['article'][$matches[1][$i]] = $matches[2][$i];

    // Itemprop content general tags
    $matches = null;
    // content must not be empty. Thus + instead of *
    preg_match_all('~<\s*[^<>]*itemprop="(datePublished)"\s+[^<>]*content="([^"]+)~i', $html,$matches);
    for ($i=0; $i<count($matches[1]); $i++) $meta['itemprop'][$matches[1][$i]] = $matches[2][$i];

    // h1 tag
    $matches = null;
    preg_match_all('~<h1\b[^>]*>(.*?)</h1>~is', $html,$matches);
    if (count($matches[1]) > 0) $meta['other']['h1'] = $matches[1][0];

    // Description meta tag
    $matches = null;
    preg_match_all('~<\s*meta\s+[^<>]*(?:property|name)=[\'"](description)[\'"]\s+[^<>]*content=[\'"]([^"\']*)~i', $html,$matches);
    if (count($matches[1]) > 0) $meta['other']['description'] = $matches[2][0];

    // Time tag
    preg_match_all('~<\s*time\s+[^<>]*?datetime\s*=\s*[\'"]([^"\']*?)[\'"][^<>]*?>~i', $html,$matches);
    if (count($matches[1]) > 0) {
      $dateText = $matches[1][0];
      try {
        $date = new DateTime($dateText);
        $current_date = new DateTime();
        if ($date < $current_date) $meta['other']['time'] = $date->format('Y-m-d');
      } catch (Exception) {
        // Silent exception
      }
    }

    $meta['other']['domain'] = $this->extractDomain($url);

    return $meta;
  }

  function getTagStats(array $tags): array{
    $stats = [
      'json_ld' => count($tags['json-ld']),
      'og' => count($tags['og']),
      'twitter' => count($tags['twitter']),
      'article' => count($tags['article']),
      'itemprop' => count($tags['itemprop']),
      'other' => count($tags['other']),
    ];

    $stats['total'] = $stats['json_ld'] + $stats['og'] + $stats['twitter'] + $stats['article'] + $stats['itemprop'];

    return $stats;
  }

  function extractDomain(string $url): string {
    // Ensure the URL includes a protocol
    $url = parse_url($url, PHP_URL_SCHEME) ? $url : 'https://' . $url;

    // Parse the host
    $host = parse_url($url, PHP_URL_HOST);

    // Split the host into parts
    $hostParts = explode('.', $host);

    // Return just the domain (second to last and last parts)
    if (count($hostParts) >= 2) {
      return $hostParts[count($hostParts) - 2] . '.' . $hostParts[count($hostParts) - 1];
    }

    // If the host is invalid or does not have at least two parts
    return $host;
  }

  function parse_ld_json($json, &$result): void {
    $ldJson = trim($json);
    if (! isset($ldJson)) return;

    if (! isset($result)) $result = [];
    $ld = json_decode($ldJson);
    if (! isset($ld)) return;

    if (is_array($ld)) foreach ($ld as $entry) $this->parseLdObject($result, $entry);
    else $this->parseLdObject($result, $ld);
  }

  function parseLdObject(&$result, $data) {
    if (isset($data->headline))      $result['headline']      = $data->headline;
    if (isset($data->articleBody))   $result['articleBody']   = $data->articleBody;
    if (isset($data->description))   $result['description']   = $data->description;
    if (isset($data->datePublished)) $result['datePublished'] = $data->datePublished;
    if (isset($data->image)) {
      if (is_string($data->image)) $result['image'] = $data->image;
      else if (is_object($data->image) && isset($data->image->url)) $result['image'] = $data->image->url;
    }
    if (isset($data->publisher) && isset($data->publisher->name)) $result['publisher'] = $data->publisher->name;
  }

  private function getLongestAvailableTag(?array $tags): string {
    if (! isset($tags)) return '';

    $result = '';
    foreach ($tags as $tag) {
      if (isset($tag)) {
        $tag = trim($tag);
        if ((! empty($tag)) && (strlen($tag) > strlen($result))) $result = $tag;
      }
    }
    return $result;
  }

  function mediaTagsFromMetaData(array $metaData): array {
    $ldJsonTags = $metaData['json-ld'];
    $ogTags = $metaData['og'];
    $twitterTags = $metaData['twitter'];
    $articleTags = $metaData['article'];
    $itemPropTags = $metaData['itemprop'];

    // Get best tag (we assume it is the longest one) and decode HTML entities to normal text
    $media = [
      'url' => $this->getLongestAvailableTag([$ogTags['og:url']?? null, $this->url]),
      'urlimage' => $this->getLongestAvailableTag([$ldJsonTags['image']?? null, $ogTags['og:image']?? null]),
      'title' => html_entity_decode(htmlspecialchars_decode(strip_tags($this->getLongestAvailableTag([$ldJsonTags['headline']?? null, $ogTags['og:title']?? null, $twitterTags['twitter:title']?? null]))),ENT_QUOTES),
      'description' => html_entity_decode(strip_tags(htmlspecialchars_decode($this->getLongestAvailableTag([$ldJsonTags['description']?? null, $ogTags['og:description']?? null, $twitterTags['twitter:description']?? null]))),ENT_QUOTES),
      'article_body' => html_entity_decode(strip_tags(htmlspecialchars_decode($this->getLongestAvailableTag([$ldJsonTags['articleBody']?? null]))),ENT_QUOTES),
      'sitename' => html_entity_decode(htmlspecialchars_decode($this->getLongestAvailableTag([$ldJsonTags['publisher']?? null, $ogTags['og:site_name']?? null, $metaData['other']['domain']?? null])),ENT_QUOTES),
      'published_time' => $this->getLongestAvailableTag([$ldJsonTags['datePublished']?? null, $ogTags['og:article:published_time']?? null, $articleTags['article:published_time']?? null, $itemPropTags['datePublished']?? null, $articleTags['article:modified_time']?? null]),
    ];

    // Replace http with https on image tags. Some sites still send unsecure links
    $media['urlimage'] = str_replace('http://', 'https://', $media['urlimage']);
    if (substr($media['urlimage'], 0, 1) === '/') {
      $parse = parse_url($media['url']);
      $media['urlimage'] = 'https://' . $parse['host'] . $media['urlimage'];
    }

    // Plan C if no other info available: Use H1 for title. Description for description
    if (($media['title']          === '') && (isset($metaData['other']['h1'])))          $media['title']          = $metaData['other']['h1'];
    if (($media['description']    === '') && (isset($metaData['other']['description']))) $media['description']    = $metaData['other']['description'];
    if (($media['published_time'] === '') && (isset($metaData['other']['time'])))        $media['published_time'] = $metaData['other']['time'];

    // Check if valid published_time string
    if (strlen($media['published_time']) >= 1) {
      try {
        $dateTime = new DateTime($media['published_time']);
        $media['published_time'] = $dateTime->format(DateTime::ISO8601);
      } catch (Exception $e) {
        if (strlen($media['published_time']) > 10) {
          $media['published_time'] = substr($media['published_time'], 0, 10);
        }
      }
    }

    return $media;
  }
}

function parseMetaDataFromUrl(string $url): array {
  $parser = new TMetaParser($url);

  $urlDownload = $url;
  $pageHtml = $parser->downloadWebpage($urlDownload);
  if ($pageHtml === false) {
    // Use headless browser if CURL fails. This happens if CURL is blocked
    $pageHtml = $parser->downloadUsingHeadlessBrowser($urlDownload);
    $metaData = $parser->getPageMediaMetaData($pageHtml, $url);
    $tagCount = $parser->getTagStats($metaData);
  } else {
    $metaData = $parser->getPageMediaMetaData($pageHtml, $url);
    $tagCount = $parser->getTagStats($metaData);

    // if no tags found, try headless browser
    if ($tagCount['total'] <= 0) {
      $pageHtml = $parser->downloadUsingHeadlessBrowser($urlDownload);
      $metaData = $parser->getPageMediaMetaData($pageHtml, $url);
      $tagCount = $parser->getTagStats($metaData);
    }
  }

  $media = $parser->mediaTagsFromMetaData($metaData);

  return [
    'media' => $media,
    'tagCount' => $tagCount,
  ];
}