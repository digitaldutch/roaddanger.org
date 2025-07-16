<?php

class ReframeTextHandler {

  /**
   * @throws Exception
   */
  static function reframeArticle(): false|string {
    $article = json_decode(file_get_contents('php://input'));

    if (empty($article->text)) throw new \Exception('No text provided');
    if (empty($article->title)) throw new \Exception('No title provided');

    global $database;
    try {
      $prompt = $database->fetchObject("SELECT model_id, user_prompt, system_prompt, response_format FROM ai_prompts WHERE function='article_reframe';");

      require_once '../general/OpenRouterAIClient.php';

      $prompt->user_prompt = replaceArticleTags($prompt->user_prompt, $article);

      $openrouter = new OpenRouterAIClient();
      $AIResults = $openrouter->chatWithMeta($prompt->user_prompt, $prompt->system_prompt, $prompt->model_id, $prompt->response_format);

      $AIResults['response'] = json_decode($AIResults['response']);

      $result = ['ok' => true, 'data' => $AIResults['response']];
    } catch (\Throwable $e){
      $result = ['ok' => false, 'error' => $e->getMessage()];
    }

    return json_encode($result);
  }

}