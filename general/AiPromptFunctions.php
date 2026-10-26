<?php

/**
 * The places on the website where an AI prompt is used.
 *
 * A prompt is linked to a place with its function (ai_prompts.function, unique): the website code asks for the prompt
 * of a function, for example 'article_analyst'. A prompt without a function is only used in tests.
 * Add a place here when the code starts to use a new function. The AI prompt builder shows these names.
 */
class AiPromptFunctions {

  /**
   * @return array<string, array{name: string, description: string, planned?: bool}>
   */
  public static function all(): array {
    return [
      'article_analyst' => [
        'name' => 'Add article: extract crash data',
        'description' => 'Reads a news article and fills in the new crash: the people, the place and the date.',
      ],
      'article_location' => [
        'name' => 'Find the place of a crash (script)',
        'description' => 'Finds the place where a crash happened in an article. Used by install/backfill_locations.php, to add the place to crashes that have none.',
      ],
      'questionnaire_answerer' => [
        'name' => 'Answer questionnaires with AI',
        'description' => 'Answers the questions of the questionnaires for an article.',
      ],
      'article_reframe_UVA' => [
        'name' => 'ReFrame headline analysis (UVA page)',
        'description' => 'Rewrites a headline and checks it on three criteria, for the UVA ReFrame page.',
      ],
      'article_reframe' => [
        'name' => 'ReFrame article text',
        'description' => 'Rewrites the title and text of a crash article (the page /reframe_text/).',
      ],
      'translator' => [
        'name' => 'Translate articles',
        'description' => 'Translates an article into a language that the reader speaks. The website does not use this yet.',
        'planned' => true,
      ],
    ];
  }

  public static function exists(string $function): bool {
    return array_key_exists($function, self::all());
  }
}
