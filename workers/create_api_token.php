<?php

// Makes a token for the import API (api/import.php). Command line only.
//   php create_api_token.php --user=12 --name="Lexis Nexis" [--autoapprove] [--max-ai=200]
// --user: the user that owns what the token imports. --autoapprove: imported crashes are published without moderation.
// The token is shown once. Only a hash is saved.

if (php_sapi_name() !== 'cli') die('Script may only run from command line');

chdir(__DIR__);
require_once '../config.php';
require_once '../database.php';

$options = getopt('', ['user:', 'name:', 'autoapprove', 'max-ai:']);
if (empty($options['user']) || empty($options['name'])) die("Usage: php create_api_token.php --user=ID --name=\"Name\" [--autoapprove] [--max-ai=200]\n");

$database = new Database();
$database->open();

$userId = (int)$options['user'];
$user = $database->fetch('SELECT id, email FROM users WHERE id=:id AND permission IN (1, 2)', [':id' => $userId]);
if (! $user) die("User $userId does not exist or is not a moderator or admin\n");

$token = bin2hex(random_bytes(32));
$database->execute(
  'INSERT INTO api_tokens (userid, name, tokenhash, autoapprove, maxaiperday) VALUES (:userid, :name, :hash, :autoapprove, :maxai)',
  [':userid' => $userId, ':name' => $options['name'], ':hash' => hash('sha256', $token),
   ':autoapprove' => isset($options['autoapprove']) ? 1 : 0, ':maxai' => (int)($options['max-ai'] ?? 200)]);

echo "Token for '{$options['name']}' (user {$user['email']}). It is not shown again:\n$token\n";
