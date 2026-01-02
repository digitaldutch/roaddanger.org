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
    $headlessCommand = PHP_OS_FAMILY === 'Windows'? HEADLESS_BROWSER_COMMAND_WINDOWS : HEADLESS_BROWSER_COMMAND;

    $command = $headlessCommand . $urlDownload;

    exec($command, $output, $statusCode);
    if ($statusCode === 0) {
      return implode("\n", $output);
    } else {
      throw new RuntimeException("Unable to download webpage from headless browser. Set correct command in config.php.");
    }
  }

  function getPageMediaMetaData(string $html, string $url): array{
    $metaOut = [
      'json-ld' => [],
      'og' => [],
      'twitter' => [],
      'article' => [],
      'itemprop' => [],
      'other' => [],
      'cloudflare' => false,
    ];

    // Convert to UTF-8 as some websites do not use it. We need it for parsing.
    $html = mb_convert_encoding($html, 'UTF-8',  mb_detect_encoding($html, 'UTF-8, ISO-8859-1', true));

    libxml_use_internal_errors(true); // Suppress libxml warnings internally

    $dom = new DOMDocument();

    // Dirty way to tell DOM it really is UTF-8
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);

    libxml_clear_errors(); // Clear any errors collected

    // See Google structured data guidelines https://developers.google.com/search/docs/guides/intro-structured-data#structured-data-guidelines
    // json-ld tags. Used by Google.

    // Find JSON-LD meta data
    $scripts = $dom->getElementsByTagName('script');
    foreach ($scripts as $script) {
      if ($script->getAttribute('type') === 'application/ld+json') {
        $ldJson = trim($script->nodeValue);
        $this->parseLdJson($ldJson, $metaOut['json-ld']);
      }
    }

    $metaTags = $dom->getElementsByTagName('meta');
    foreach ($metaTags as $tag) {
      $property = $tag->getAttribute('property');
      $name = $tag->getAttribute('name');
      $content = $tag->getAttribute('content');
      $itemprop = $tag->getAttribute('itemprop');

      // Open Graph tags (og:* in property or name attributes)
      if (str_starts_with($property, 'og:') || str_starts_with($name, 'og:')) {
        $key = $property ?: $name;
        $metaOut['og'][$key] = $content;
      }

      // Twitter tags (twitter:* in property or name attributes)
      if (str_starts_with($property, 'twitter:') || str_starts_with($name, 'twitter:')) {
        $key = $property ?: $name;
        $metaOut['twitter'][$key] = $content;
      }

      // Article tags (article:* in property or name attributes)
      if (str_starts_with($property, 'article:') || str_starts_with($name, 'article:')) {
        $key = $property ?: $name;
        $metaOut['article'][$key] = $content;
      }

      // Itemprop tags (e.g., datePublished) in itemprop attribute
      if ($itemprop) {
        $metaOut['itemprop'][$itemprop] = $content;
      }

      // Description meta tag (description in property or name attributes)
      if (($property === 'description' || $name === 'description') && !empty($content)) {
        $metaOut['other']['description'] = $content;
      }
    }


    // h1 tag
    $h1Tags = $dom->getElementsByTagName('h1');

    // Check if at least one <h1> tag exists
    if ($h1Tags->length > 0) {
      // Get the text content of the first <h1> tag
      $metaOut['other']['h1'] = trim($h1Tags->item(0)->textContent);
    }

    // Time tag
    $timeTags = $dom->getElementsByTagName('time');

    // Check if at least one <time> tag exists
    if ($timeTags->length > 0) {
      // Get the datetime attribute of the first <time> tag
      $dateText = $timeTags->item(0)->getAttribute('datetime');

      if (!empty($dateText)) {
        try {
          $date = new DateTime($dateText);
          $current_date = new DateTime();

          // Check if the date is in the past
          if ($date < $current_date) {
            $metaOut['other']['time'] = $date->format('Y-m-d');
          }
        } catch (Exception $e) {
          // Do nothing for ill formed time tags
        }
      }
    }

    $metaOut['other']['domain'] = $this->extractDomain($url);

    $metaOut['cloudflare'] = str_contains(strtolower($html), 'cloudflare') !== false;

    return $metaOut;
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

  private const JSON_LD_TYPE = 'application/ld+json';

  function parseLdJson(string $json, array &$result): void {
    $trimmedJson = trim($json);
    if (empty($trimmedJson)) return;

    $result ??= []; // Initialize $result if not already set
    $decodedJson = json_decode($trimmedJson);
    if (empty($decodedJson)) return;

    $this->processJsonData($decodedJson, $result);
  }

  private function processJsonData(mixed $data, array &$result): void {
    if (is_array($data)) {
      foreach ($data as $entry) {
        $this->parseLdObject($result, $entry);
      }
    } else {
      $this->parseLdObject($result, $data);
    }
  }

  function parseLdObject(array &$result, object $data): void {
    // Handle `@graph` property, which contains multiple objects
    if (!empty($data->{'@graph'})) {
      $this->processGraph($result, $data->{'@graph'});
      return;
    }

    // Handle standard fields
    if (!empty($data->headline)) {
      $result['headline'] = $data->headline;
    }
    if (!empty($data->articleBody)) {
      $result['articleBody'] = $data->articleBody;
    }
    if (!empty($data->description)) {
      $result['description'] = $data->description;
    }
    if (!empty($data->datePublished)) {
      $result['datePublished'] = $data->datePublished;
    }
    if (!empty($data->image)) {
      $result['image'] = is_string($data->image)
        ? $data->image
        : ($data->image->url ?? null);
    }
    if (!empty($data->publisher->name)) {
      $result['publisher'] = $data->publisher->name;
    }

  }

  private function processGraph(array &$result, array $graph): void {
    $articleFound = false;

    foreach ($graph as $item) {
      if (is_object($item) && !empty($item->{'@type'})) {
        // Check for NewsArticle as the primary type
        if ($this->isOfType($item->{'@type'}, 'NewsArticle')) {
          $this->parseLdObject($result, $item);
          $articleFound = true; // Mark as article found
          break; // Stop on the first valid NewsArticle
        }
      }
    }

    // Fallback to WebPage if no NewsArticle was found
    if (!$articleFound) {
      foreach ($graph as $item) {
        if (is_object($item) && !empty($item->{'@type'}) && $this->isOfType($item->{'@type'}, 'WebPage')) {
          $this->parseLdObject($result, $item);
          break; // Stop on the first valid WebPage
        }
      }
    }
  }

  private function isOfType(mixed $typeField, string $expectedType): bool {
    // Check if @type matches a specific value
    if (is_string($typeField)) {
      return $typeField === $expectedType;
    }

    if (is_array($typeField)) {
      return in_array($expectedType, $typeField, true);
    }

    return false;
  }

  private function getLongestAvailableTag(?array $tags): string {
    if (! isset($tags)) return '';

    $result = '';
    foreach ($tags as $tag) {
      // Sometimes the tag is an array. In that case we use the first element.
      if (is_array($tag)) {
        if (count($tag) > 0) $tag = $tag[0];
        else $tag = null;
      }

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

    $media['cloudflare'] = $metaData['cloudflare'];

    return $media;
  }
}

function parseMetaDataFromUrl(string $url): array {
  $parser = new TMetaParser($url);

  $urlDownload = $url;
  $pageHtml = $parser->downloadWebpage($urlDownload);
  if ($pageHtml === false) {
    // Use a headless browser if CURL fails. This happens if CURL is blocked
    $pageHtml = $parser->downloadUsingHeadlessBrowser($urlDownload);
    $metaData = $parser->getPageMediaMetaData($pageHtml, $url);
    $tagCount = $parser->getTagStats($metaData);
  } else {
    $metaData = $parser->getPageMediaMetaData($pageHtml, $url);
    $tagCount = $parser->getTagStats($metaData);

    // if no tags found, try the headless browser
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