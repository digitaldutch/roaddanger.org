<?php

require_once '../configsecret.php';

class OpenRouterAIClient {
  private const string DEFAULT_MODEL = 'openrouter/auto';

  /**
   * @throws Exception
   */
  private function callServer(string $URL, array $postData = null): array {
    $ch = curl_init($URL);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . OPENROUTER_API_KEY,
      'Content-Type: application/json',
      'HTTP-Referer: https://roaddanger.org',
      'X-Title: RoadDanger',
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (isset($postData)) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response = json_decode($response, true);

    if (isset($response['error'])) {
      $errorMsg = $response['error']['message'] ?? 'Unknown error';

      // Check for metadata.raw, parse and extract its message
      if (
        isset($response['error']['metadata']['raw']) &&
        ($meta = json_decode($response['error']['metadata']['raw'], true)) &&
        isset($meta['error']['message'])
      ) {
        $errorMsg .= ' | Provider: ' . $meta['error']['message'];
      }

      throw new \Exception($errorMsg, $response['error']['code'] ?? 0);
    }
    if ($httpCode !== 200 || !$response) {
      throw new Exception('Error calling openrouter.ai API: ' . $response);
    }

    return $response;
  }

  /**
   * @throws Exception
   */
  public function chatWithMeta(string $user_prompt, string $systemPrompt = '', string $model = self::DEFAULT_MODEL,
                               string $responseFormat = ''): array {

    if (strlen($user_prompt) > 5000) {
      throw new Exception('The "User prompt" exceeds the maximum allowed length of 5000 characters');
    }

    if (strlen($systemPrompt) > 5000) {
      throw new Exception('The "system prompt" exceeds the maximum allowed length of 5000 characters');
    }
    
    if (strlen($responseFormat) > 5000) {
      throw new Exception('The "response format" exceeds the maximum allowed length of 5000 characters');
    }

    $responseOpenRouter = $this->chatFromOpenRouter($user_prompt, $systemPrompt, $model, $responseFormat);

    return [
      'response' => $responseOpenRouter['choices'][0]['message']['content'],
      'id' => $responseOpenRouter['id'],
      'tokens_prompt' => $responseOpenRouter['usage']['prompt_tokens'],
      'tokens_completion' => $responseOpenRouter['usage']['completion_tokens'],
    ];
  }

  /**
   * @throws Exception
   */
  public function chat(
    string $userPrompt,
    string $systemPrompt = '',
    string $model = self::DEFAULT_MODEL
  ): string {

    $responseOpenRouter = $this->chatFromOpenRouter($userPrompt, $systemPrompt, $model);

    // Extract content from the first assistant response, if available
    if (isset($responseOpenRouter['choices'][0]['message']['content'])) {
      return $responseOpenRouter['choices'][0]['message']['content'];
    }

    throw new Exception('Invalid openrouter response');
  }

  /**
   * @throws Exception
   */
  public function chatFromOpenRouter(string $userPrompt, string $systemPrompt = '',
                                     string $model = self::DEFAULT_MODEL, string $responseFormat = ''): array {
    $messages = [];

    if (! empty($systemPrompt)) {
      $messages[] = [
        'role' => 'system',
        'content' => $systemPrompt,
      ];
    }

    $messages[] = [
      'role' => 'user',
      'content' => $userPrompt,
    ];

    $postData = [
      'model' => $model,
      'messages' => $messages,
    ];

    if (! empty($responseFormat)) {
      $postData['response_format'] = json_decode($responseFormat);
    }

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    return $this->callServer($url, $postData);
  }

  /**
   * Don't call this directly after a chat. It takes a few seconds to be available
   * @throws Exception
   */
  public function getGenerationInfo(string $generationId): object {
    $url = "https://openrouter.ai/api/v1/generation?id=$generationId";
    $serverResponse = $this->callServer($url);

    if (! isset($serverResponse['data'])) {
      throw new Exception('Invalid openrouter generation response');
    }

    $generation = (object) $serverResponse['data'];

    $generation->tps = $generation->generation_time > 0? 1000 * $generation->native_tokens_completion / $generation->generation_time : 999999;

    return $generation;
  }

  /**
   * Don't call this directly after a chat. It takes a few seconds to be available
   * @throws Exception
   */
  public function getChatCost(string $generationId): ?float {
    $generation = $this->getGenerationInfo($generationId);

    if (! isset($generation->total_cost)) {
      throw new Exception('Invalid openrouter generation response');
    }

    return is_numeric($generation->total_cost) ? (float)$generation->total_cost : null;
  }

  /**
   * @throws Exception
   */
  public function getAllModels(): array {

    require_once 'Cache.php';
    $cacheResponse = Cache::get('openRouterGetAllModels', 30);

    if ($cacheResponse === null) {
      $url = 'https://openrouter.ai/api/v1/models';
      $response = $this->callServer($url,);

      if (isset($response['error']['message'])) {
        throw new Exception($response['error']['message']);
      }

      $modelsOpenRouter = $response['data'];

      $models = [];
      foreach ($modelsOpenRouter as &$modelOpenRouter) {

        $dateCreated = new DateTime();
        $dateCreated->setTimestamp($modelOpenRouter['created']);

        $models[] = [
          'name' => $modelOpenRouter['name'],
          'id' => $modelOpenRouter['id'],
          'description' => $modelOpenRouter['description'],
          'context_length' => $modelOpenRouter['context_length'],
          'created' => $dateCreated->format('Y-m-d'),
          'cost_input' => floatval($modelOpenRouter['pricing']['prompt']),
          'cost_output' => floatval($modelOpenRouter['pricing']['completion']),
          'structured_outputs' => in_array('structured_outputs', $modelOpenRouter['supported_parameters']),
        ];
      }

      Cache::set('openRouterGetAllModels', json_encode($models));
    } else {
      $models = json_decode($cacheResponse, true);
    }

    return $models;
  }

  /**
   * @throws Exception
   */
  public function getCredits(): float {
    $url = 'https://openrouter.ai/api/v1/credits';
    $response = $this->callServer($url,);

    if (isset($response['error']['message'])) {
      throw new Exception($response['error']['message']);
    }

    $total_credits = $response['data']['total_credits'];
    $total_usage = $response['data']['total_usage'];

    $remaining_credits = $total_credits - $total_usage;

    return $remaining_credits;
  }
}