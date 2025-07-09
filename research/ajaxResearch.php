<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../initialize.php';
require_once '../general/utils.php';
require_once '../general/OpenRouterAIClient.php';
require_once './ResearchHandler.php';

$function = $_REQUEST['function'];

// Public functions
if ($function === 'loadQuestionnaireResults') {
  echo ResearchHandler::loadQuestionnaireResults();
  return;
}

global $user;

// The stuff below is for moderators only
if (!$user->isModerator()) {
  dieWithJSONErrorMessage('Permission error: Only moderators allowed');
}

if ($function === 'aiRunPrompt') {
  echo ResearchHandler::aiRunPrompt();
  return;
} else if ($function === 'aiInit') {
  echo ResearchHandler::aiInit();
  return;
} else if ($function === 'aiGetAvailableModels') {
  echo ResearchHandler::aiGetAvailableModels();
  return;
} else if ($function === 'aiGetGenerationInfo') {
  echo ResearchHandler::aiGetGenerationInfo();
  return;
} else if ($function === 'loadArticle') {
  echo ResearchHandler::loadArticle();
  return;
} else if ($function === 'selectAiModel') {
  echo ResearchHandler::selectAiModel();
  return;
} else if ($function === 'removeAiModel') {
  echo ResearchHandler::removeAiModel();
  return;
} else if ($function === 'updateModelsDatabase') {
  echo ResearchHandler::updateModelsDatabase();
  return;
} else if ($function === 'aiSavePrompt') {
  echo ResearchHandler::aiSavePrompt();
  return;
} else if ($function === 'aiGetPromptList') {
  echo ResearchHandler::aiGetPromptList();
  return;
} else if ($function === 'aiDeletePrompt') {
  echo ResearchHandler::aiDeletePrompt();
  return;
} else if ($function === 'loadArticlesUnanswered') {
  echo ResearchHandler::loadArticlesUnanswered();
  return;
}

// The stuff below is only for administrators
if (!$user->admin) {
  dieWithJSONErrorMessage('Permission error: Only administrators allowed');
}

if ($function === 'loadQuestionnaires') {
  echo ResearchHandler::loadQuestionnaires();
  return;
} else if ($function === 'saveQuestion') {
  echo ResearchHandler::saveQuestion();
  return;
} else if ($function === 'deleteQuestion') {
  echo ResearchHandler::deleteQuestion();
  return;
} else if ($function === 'saveQuestionsOrder') {
  echo ResearchHandler::saveQuestionsOrder();
  return;
} else if ($function === 'saveQuestionnaire') {
  echo ResearchHandler::saveQuestionnaire();
  return;
} else if ($function === 'deleteQuestionnaire') {
  echo ResearchHandler::deleteQuestionnaire();
  return;
} else {
  echo json_encode(['ok' => false, 'error' => 'Function not found']);
  return;
}

