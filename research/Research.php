<?php

class Research {
  static function getBechdelResult(array $answers): array {
    $bechdelResult = Answer::yes;

    $totalQuestionsPassed = 0;
    foreach ($answers as $answer) {
      if      ($answer === Answer::no->value)              {$bechdelResult = Answer::no; break;}
      else if ($answer === Answer::notDeterminable->value) {$bechdelResult = Answer::notDeterminable; break;}
      else if ($answer === null)                           {$bechdelResult = null; break;}
      else if (($answer === Answer::yes->value))           {$totalQuestionsPassed += 1;}
    }

    return [
      'result' => $bechdelResult,
      'total_questions_passed' => $totalQuestionsPassed,
    ];
  }

  static function passesArticleFilter($article, $articleFilter): bool {

    if ($articleFilter['questionsPassed'] === 'nd') {
      if ($article['bechdelResult']['result']->value != Answer::notDeterminable->value) return false;
    } else {
      if ($article['bechdelResult']['result']->value === Answer::notDeterminable->value) return false;

      if ($article['bechdelResult']['total_questions_passed'] !== (int)$articleFilter['questionsPassed']) return false;
    }

    if ($articleFilter['group'] === 'year') {
      if ($articleFilter['groupData'] != $article['article_year']) return false;
    } else if ($articleFilter['group'] === 'month') {
      if ($articleFilter['groupData'] != $article['article_year_month']) return false;
    } else if ($articleFilter['group'] === 'source') {
      if ($articleFilter['groupData'] != $article['sitename']) return false;
    } else if ($articleFilter['group'] === 'country') {
      if ($articleFilter['groupData'] != $article['countryid']) return false;
    }

    return true;
  }

  /**
   * @throws Exception
   */
  static function loadQuestionnaireResults(array $filter, string $group, array $articleFilter): array {
    global $database;

    $bechdelResults = null;

    $result = ['ok' => true];

    // Get questionnaire info
    $sql = <<<SQL
SELECT
  q.title,
  q.country_id,
  c.name AS country,
  q.type,
  q.public
FROM questionnaires q
LEFT JOIN countries c ON q.country_id = c.id
WHERE q.id=:questionnaire_id;
SQL;

    $params = [':questionnaire_id' => $filter['questionnaireId']];
    $questionnaire = $database->fetch($sql, $params);

    if (($filter['public'] === 1) && ($questionnaire['public'] !== 1)) {
      throw new \Exception("Questionnaire " . $filter['questionnaireId'] . " is not public");
    }

    $SQLWhereAnd = ' ';
    addPersonsWhereSql($SQLWhereAnd, $filter);

    if (! empty($filter['country']) and ($filter['country'] !== 'UN')){
      addSQLWhere($SQLWhereAnd, 'c.countryid="' . $filter['country'] . '"');
    }

    if (! empty($filter['timeSpan'])) {

      $timeSpan = $filter['timeSpan'];
      if ($timeSpan === 'from2022') {
        addSQLWhere($SQLWhereAnd, "EXTRACT(YEAR FROM c.date) >= 2022");
      } else {
        $yearOffset = match ($timeSpan) {
          '1year' => 1,
          '2year' => 2,
          '3year' => 3,
          '5year' => 5,
          '10year' => 10,
          default => null
        };

        if ($yearOffset !== null) {
          $startYear = date("Y") - $yearOffset + 1;
          addSQLWhere($SQLWhereAnd, "EXTRACT(YEAR FROM c.date) >= $startYear");
        }

      }

    }

    if (isset($filter['noUnilateral']) && ($filter['noUnilateral'] === 1)){
      addSQLWhere($SQLWhereAnd, " c.unilateral !=1 ");
    }

    // Get questionnaire answers
    // ***** Standard questionnaire type *****
    if ($questionnaire['type'] === QuestionnaireType::standard->value) {

      $sql = <<<SQL
SELECT
  a.questionid AS id,
  q.text,
  a.answer,
  count(a.answer) AS aantal
FROM answers a
  LEFT JOIN articles ar                ON ar.id = a.articleid
  LEFT JOIN crashes c                  ON ar.crashid = c.id
  LEFT JOIN questionnaire_questions qq ON qq.question_id = a.questionid
  LEFT JOIN questions q                ON a.questionid = q.id
WHERE qq.questionnaire_id=:questionnaire_id
  $SQLWhereAnd
GROUP BY qq.question_order, answer
ORDER BY qq.question_order
SQL;

      $params = [':questionnaire_id' => $filter['questionnaireId']];
      $dbQuestions = $database->fetchAllGroup($sql, $params);

      $questions = [];
      foreach ($dbQuestions as $questionId => $dbQuestion) {
        $questions[] = [
          'question_id'      => $questionId,
          'question'         => $dbQuestion[0]['text'],
          'no'               => $dbQuestion[0]['aantal'] ?? 0,
          'yes'              => $dbQuestion[1]['aantal'] ?? 0,
          'not_determinable' => $dbQuestion[2]['aantal'] ?? 0,
        ];

      }

      $result['questions'] = $questions;

      if (isset($articleFilter['getArticles']) && $articleFilter['getArticles'] === true) {
        $sql = <<<SQL
SELECT
  a.answer,
  ar.crashid,
  c.countryid,
  c.unilateral AS crash_unilateral,
  c.date AS crash_date,
  ar.id,
  ar.title,
  ar.publishedtime,
  ar.sitename
FROM answers a
LEFT JOIN articles ar                ON ar.id = a.articleid
LEFT JOIN crashes c                  ON ar.crashid = c.id
LEFT JOIN questionnaire_questions qq ON qq.question_id = a.questionid
LEFT JOIN questions q                ON a.questionid = q.id
WHERE a.questionid=:questionId
ORDER BY ar.publishedtime DESC;
SQL;
        $params = [':questionId' => $articleFilter['questionId']];
        $articles = $database->fetchAll($sql, $params);

        $result['articles'] = $articles;

        $result['crashes'] = [];
        foreach ($articles as $article) {
          $result['crashes'][] = [
            'id' => $article['crashid'],
            'countryid' => $article['countryid'],
            'date' => $article['crash_date'],
            'unilateral' => $article['crash_unilateral'] === 1,
          ];
        }

      }

    } else {
      // ***** Bechdel type questionnaire *****

      // Get questionnaire questions
      $sql = <<<SQL
SELECT
  q.id,
  q.text
FROM questionnaire_questions qq
LEFT JOIN questions q ON q.id = qq.question_id
WHERE qq.questionnaire_id=:questionnaire_id
ORDER BY qq.question_order
SQL;
      $questionnaire['questions'] = $database->fetchAll($sql, $params);

      function getInitBechdelResults($questions) {
        $results = [
          'yes'                    => 0,
          'no'                     => 0,
          'not_determinable'       => 0,
          'total_articles'         => 0,
          'total_questions_passed' => [],
        ];

        for ($i=0; $i<=count($questions); $i++) {
          $results['total_questions_passed'][$i] = 0;
        };

        return $results;
      }

      $sql = <<<SQL
SELECT
  ar.crashid,
  ar.id,
  ar.publishedtime,
  ar.title,
  ar.url,
  ar.sitename,
  c.countryid,
  c.date                                                AS crash_date,
  c.unilateral                                          AS crash_unilateral,
  c.countryid                                           AS crash_countryid,
  YEAR(ar.publishedtime)                                AS article_year,
  EXTRACT(YEAR_MONTH FROM ar.publishedtime)             AS article_year_month,
  GROUP_CONCAT(a.questionid ORDER BY qq.question_order) AS question_ids,
  GROUP_CONCAT(a.answer     ORDER BY qq.question_order) AS answers
FROM answers a
  LEFT JOIN articles ar                ON ar.id = a.articleid
  LEFT JOIN crashes c                  ON ar.crashid = c.id
  LEFT JOIN questionnaire_questions qq ON qq.question_id = a.questionid
WHERE a.questionid in (SELECT question_id FROM questionnaire_questions WHERE questionnaire_id=:questionnaire_id)
  $SQLWhereAnd
GROUP BY a.articleid
ORDER BY ar.publishedtime DESC;
SQL;

      $params = [
        ':questionnaire_id' => $filter['questionnaireId'],
      ];

      $articles = [];
      $crashes = [];

      $statement = $database->prepare($sql);
      $statement->execute($params);
      while ($article = $statement->fetch(PDO::FETCH_ASSOC)) {
        $article['publishedtime'] = datetimeDBToISO8601($article['publishedtime']);

        // Format and clean up article questions and answers data
        $articleQuestionIds = explode(',', $article['question_ids']);
        $articleAnswers = explode(',', $article['answers']);

        $article['questions'] = [];
        foreach ($questionnaire['questions'] as $question) {
          $index  = array_search($question['id'], $articleQuestionIds);
          $answer = $index === false? null : (int)$articleAnswers[$index];
          $article['questions'][$question['id']] = $answer;
        }

        unset($article['question_ids']);
        unset($article['answers']);

        $articleBechdel = self::getBechdelResult($article['questions']);
        $articleBechdel['total_questions'] = count($article['questions']);

        // Get the group where the article belongs to
        switch ($group) {
          case 'year': {
            $bechdelResultsGroup = &$bechdelResults[$article['article_year']];
            break;
          }

          case 'month': {
            $bechdelResultsGroup = &$bechdelResults[$article['article_year_month']];
            break;
          }

          case 'source': {
            $bechdelResultsGroup = &$bechdelResults[$article['sitename']];
            break;
          }

          case 'country': {
            $bechdelResultsGroup = &$bechdelResults[$article['crash_countryid']];
            break;
          }

          default: $bechdelResultsGroup = &$bechdelResults;
        }

        // Initialize every group to zero if the first article in the group
        if (! isset($bechdelResultsGroup)) $bechdelResultsGroup = getInitBechdelResults($questionnaire['questions']);

        if ($articleBechdel['result'] !== null) {
          switch ($articleBechdel['result']) {

            case Answer::no: {
              $bechdelResultsGroup['no'] += 1;
              $bechdelResultsGroup['total_articles'] += 1;
              $bechdelResultsGroup['total_questions_passed'][$articleBechdel['total_questions_passed']] += 1;
              break;
            }

            case Answer::yes: {
              $bechdelResultsGroup['yes'] += 1;
              $bechdelResultsGroup['total_articles'] += 1;
              $bechdelResultsGroup['total_questions_passed'][$articleBechdel['total_questions_passed']] += 1;
              break;
            }

            case Answer::notDeterminable: {
              $bechdelResultsGroup['not_determinable'] += 1;
              break;
            }

            default: throw new \Exception('Internal error: Unknown Bechdel result');
          }

          if (! empty($articleFilter['getArticles'])) {
            $article['bechdelResult'] = $articleBechdel;

            if (self::passesArticleFilter($article, $articleFilter)) {
              $articles[] = $article;
              $crashes[] = [
                'id' => $article['crashid'],
                'date' => $article['crash_date'],
                'countryid' => $article['crash_countryid'],
                'unilateral' => $article['crash_unilateral'] === 1,
              ];
            }
          }
        }

      }

      if ($group === 'year') {
        $resultsArray = [];
        foreach ($bechdelResults as $year => $bechdelResult) {
          $bechdelResult['year'] = $year;
          $resultsArray[] = $bechdelResult;
        }
        $result['bechdelResults'] = $resultsArray;
      } else if ($group === 'month') {
        $resultsArray = [];
        foreach ($bechdelResults as $yearMonth => $bechdelResult) {
          $bechdelResult['yearmonth'] = (string)$yearMonth;
          $resultsArray[] = $bechdelResult;
        }
        $result['bechdelResults'] = $resultsArray;
      } else if ($group === 'source') {
        $resultsArray = [];
        foreach ($bechdelResults as $source => $bechdelResult) {
          $bechdelResult['sitename'] = $source;
          $resultsArray[] = $bechdelResult;
        }
        $result['bechdelResults'] = $resultsArray;
      } else if ($group === 'country') {
        $resultsArray = [];
        foreach ($bechdelResults as $countryId => $bechdelResult) {
          $bechdelResult['countryid'] = $countryId;
          $resultsArray[] = $bechdelResult;
        }
        $result['bechdelResults'] = $resultsArray;
      } else {
        $result['bechdelResults'][] = $bechdelResults;
      }

      if (! empty($filter['minArticles'])) {
        $filtered = [];
        foreach ($result['bechdelResults'] as $row) {
          if ($row['total_articles'] >= $filter['minArticles']) {
            $filtered[] = $row;
          }
        }

        $result['bechdelResults'] = $filtered;
      }

      if (! empty($articleFilter['getArticles'])) {
        $result = [
          'ok' => true,
          'crashes' => $crashes,
          'articles' => array_slice($articles, $articleFilter['offset'], 1000),
        ];
      }
    }

    $result['questionnaire'] = $questionnaire;

    return $result;
  }
}
