<?php

class GeneralHandler {

  static public function extractDataFromArticle():false|string {
    $article = json_decode(file_get_contents('php://input'));

    global $database;
    try {
      $prompt = $database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='article_analist';");

      require_once '../general/OpenRouterAIClient.php';

      $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, $article);

      $openrouter = new OpenRouterAIClient();
      $AIResults = $openrouter->chatWithMeta($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

      $AIResults['response'] = json_decode($AIResults['response']);

      // Get coordinates also from geocoder as AI is not very good at it yet
      $geocoder_prompt = $AIResults['response']->location->geocoder_prompt;
      $AIResults['response']->location->geocoder_coordinates = geocodeLocation($geocoder_prompt);

      $result = ['ok' => true, 'data' => $AIResults['response']];
    } catch (\Throwable $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  static public function loadCountryDomain(): false|string {
    global $database;
    try {
      $data = json_decode(file_get_contents('php://input'), true);

      $sql = 'SELECT domain from countries WHERE id=:id;';
      $params = [':id' => $data['countryId']];
      $domain = $database->fetchSingleValue($sql, $params);

      $result = ['ok' => true,
        'domain' => $domain,
      ];
    } catch (\Throwable $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }

  static public function loadCountryMapOptions(): false|string {
    global $database;
    global $user;

    try {
      $sql = 'SELECT options from countries WHERE id=:id;';
      $params = [':id' => $user->country['id']];
      $optionsJson = $database->fetchSingleValue($sql, $params);

      if (! isset($optionsJson)) throw new Exception('No country options found for ' . $user->country['id']);
      $options     = json_decode($optionsJson);

      $result = ['ok' => true,
        'options' => $options,
      ];
    } catch (\Exception $e) {
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }
    return json_encode($result);
  }
}
