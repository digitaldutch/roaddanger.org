<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
require_once '../general/utils.php';
require_once 'AjaxResearch.php';

$function = $_REQUEST['function'];

// Public functions
if ($function === 'loadQuestionnaireResults') {
  echo AjaxResearch::loadQuestionnaireResults();
  return;
}

// The stuff below is only for administrators
global $user;
if (! $user->admin) {
  dieWithJSONErrorMessage('Permission error: Only administrators allowed');
}

if ($function === 'loadQuestionnaires') echo AjaxResearch::loadQuestionnaires();
else if ($function === 'saveQuestion') echo AjaxResearch::saveQuestion();
else if ($function === 'deleteQuestion') echo AjaxResearch::deleteQuestion();
else if ($function === 'saveQuestionsOrder') echo AjaxResearch::saveQuestionsOrder();
else if ($function === 'saveQuestionnaire')  echo AjaxResearch::saveQuestionnaire();
else if ($function === 'deleteQuestionnaire') echo AjaxResearch::deleteQuestionnaire();
else if ($function === 'loadArticlesUnanswered') echo AjaxResearch::loadArticlesUnanswered();
else echo json_encode(['ok' => false, 'error' => 'Function not found']);

