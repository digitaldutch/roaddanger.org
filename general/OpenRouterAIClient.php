<?php

require_once '../config_secret.php';

class OpenRouterAIClient {
  private const string DEFAULT_MODEL = 'openrouter/auto';

  /**
   * @throws Exception
   */
  private function callServer(string $URL, ?array $postData = null): array {
    $ch = curl_init($URL);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . OPENROUTER_API_KEY,
      'Content-Type: application/json',
      'HTTP-Referer: https://' . WEBSITE_DOMAIN,
      'X-Title: ' . WEBSITE_NAME,
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
      throw new \Exception('Error calling openrouter.ai API: ' . $response);
    }

    return $response;
  }

  /**
   * @throws Exception
   */
  public function chatAboutArticle(string $promptFunction, object $article): string {
    if (empty($article->title)) throw new \Exception('Article title is empty');
    if (empty($article->text)) throw new \Exception('Article text is empty');

    global $database;

    $sql = 'SELECT model_id, system_prompt, user_prompt, response_format FROM ai_prompts WHERE function = :id';
    $params = ['id' => $promptFunction];
    $prompt = $database->fetchObject($sql, $params);

    if ($prompt === false) throw new \Exception("AI prompt function '$promptFunction' not found");

    $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, $article);

    $response = $this->chat($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

    return $response;
  }

  /**
   * @throws Exception
   */
  public function chatAnswerArticleQuestionnaires($articleId, $questionnaire_id=null): array {
    global $database;

    $sql = "SELECT title, alltext AS text, publishedtime FROM articles WHERE id = :id";
    $article = $database->fetchObject($sql, ['id' => $articleId]);

    // The user prompt limit is 10000. Cutting the article text to prevent errors.
    // We need some space for the questionnaires too.
    if (strlen($article->text) > 7000) $article->text = substr($article->text, 0, 7000);

    $prompt = $database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='questionnaire_answerer';");

    $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, $article);
    $questionnairesData = $database->loadQuestionnairesData(false, $questionnaire_id);

    $prompt->user_prompt = replaceAI_QuestionnaireTags($prompt->user_prompt, $questionnairesData);

    $AIResults = $this->chatWithMeta($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

    $AIResults['response'] = json_decode($AIResults['response']);
    $questionnairesData = $AIResults['response']->questionnaires;

    // Save answers to the database
    foreach ($questionnairesData AS &$questionnaire) {
      foreach ($questionnaire->questions AS &$question) {
        $question->answer_id = aiAnswerToAnswerId($question->answer);
        $question->answered_by_type = 2;
        $question->ai_info = $AIResults['model'];

        $database->saveAnswer($articleId, $question->id, $question->answer_id,
          $question->justification, true, $question->ai_info);
      }
    }

    return [
      'questionnaires' => $questionnairesData,
      'model_id' => $prompt->model_id,
    ];
  }

  /**
   * @throws Exception
   */
  public function chat(
    string $userPrompt,
    string $systemPrompt = '',
    string $model = self::DEFAULT_MODEL,
    string $responseFormat = ''
  ): string {

    $responseOpenRouter = $this->chatFromOpenRouter($userPrompt, $systemPrompt, $model, $responseFormat);

    // Extract content from the first assistant response, if available
    if (isset($responseOpenRouter['choices'][0]['message']['content'])) {
      return $responseOpenRouter['choices'][0]['message']['content'];
    }

    throw new \Exception('Invalid openrouter response');
  }

  /**
   * @throws Exception
   */
  public function chatWithMeta(string $user_prompt, string $systemPrompt = '', string $model = self::DEFAULT_MODEL,
                               string $responseFormat = ''): array {

    $responseOpenRouter = $this->chatFromOpenRouter($user_prompt, $systemPrompt, $model, $responseFormat);

    return [
      'response' => $responseOpenRouter['choices'][0]['message']['content'],
      'id' => $responseOpenRouter['id'],
      'model' => $responseOpenRouter['model'],
      'tokens_prompt' => $responseOpenRouter['usage']['prompt_tokens'],
      'tokens_completion' => $responseOpenRouter['usage']['completion_tokens'],
    ];
  }

  /**
   * @throws Exception
   */
  private function chatFromOpenRouter(string $userPrompt, string $systemPrompt = '',
                                     string $model = self::DEFAULT_MODEL, string $responseFormat = ''): array {
    if (strlen($userPrompt) > 10000) {
      throw new \Exception('The "User prompt" exceeds the maximum allowed length of 10000 characters');
    }

    if (strlen($systemPrompt) > 10000) {
      throw new \Exception('The "system prompt" exceeds the maximum allowed length of 10000 characters');
    }

    if (strlen($responseFormat) > 10000) {
      throw new \Exception('The "response format" exceeds the maximum allowed length of 10000 characters');
    }

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
      throw new \Exception('Invalid openrouter generation response');
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
      throw new \Exception('Invalid openrouter generation response');
    }

    return is_numeric($generation->total_cost) ? (float)$generation->total_cost : null;
  }

  /**
   * @throws Exception
   */
  public function getAllModels(): array {

    require_once 'Cache.php';
    $cacheResponse = Cache::get('openRouterGetAllModels', 3600);

    if ($cacheResponse === null) {
      $url = 'https://openrouter.ai/api/v1/models';
      $response = $this->callServer($url,);

      if (isset($response['error']['message'])) {
        throw new \Exception($response['error']['message']);
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
          'cost_input' => (float)$modelOpenRouter['pricing']['prompt'],
          'cost_output' => (float)$modelOpenRouter['pricing']['completion'],
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
      throw new \Exception($response['error']['message']);
    }

    $total_credits = $response['data']['total_credits'];
    $total_usage = $response['data']['total_usage'];

    $remaining_credits = $total_credits - $total_usage;

    return $remaining_credits;
  }
}