<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
allow_cors();

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['error' => 'method_not_allowed'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: 'null', true);

if (!is_array($body) || !isset($body['messages']) || !is_array($body['messages'])) {
  json_out(['error' => 'messages array required'], 400);
}

$messages = $body['messages'];

/**
 * آخر رسالة user
 */
$lastUserText = '';
foreach (array_reverse($messages) as $m) {
  if (is_array($m) && ($m['role'] ?? '') === 'user') {
    $lastUserText = (string)($m['content'] ?? '');
    break;
  }
}

/**
 * Crisis check
 */
if (detect_crisis($lastUserText)) {
  json_out([
    'crisis' => true,
    'reply' => "I'm really glad you reached out. Please consider contacting a professional or local emergency support. You are not alone.",
    'source' => 'safety'
  ]);
}

$apiKey = cfg('OPENAI_API_KEY');
if (!$apiKey) {
  json_out(['error' => 'missing_api_key'], 503);
}

$model = cfg('OPENAI_MODEL', 'gpt-4o-mini');

$system = "You are a supportive assistant. Be calm, short, and helpful.";

$apiMessages = [
  ['role' => 'system', 'content' => $system]
];

foreach ($messages as $m) {
  if (!is_array($m)) continue;

  $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
  $content = trim((string)($m['content'] ?? ''));

  if ($content !== '') {
    $apiMessages[] = [
      'role' => $role,
      'content' => $content
    ];
  }
}

/**
 * OpenAI Request
 */
$payload = json_encode([
  'model' => $model,
  'messages' => $apiMessages,
  'max_tokens' => 500,
  'temperature' => 0.7
]);

$result = http_post_json(
  'https://api.openai.com/v1/chat/completions',
  [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
  ],
  $payload
);
echo $result['body'];
exit;


if (!$result['ok']) {
  json_out([
    'error' => 'openai_request_failed',
    'status' => $result['status'],
    'details' => $result
  ], 500);
}

$status = $result['status'];
$data = json_decode($result['body'], true);

/**
 * If OpenAI returned error
 */
if ($status < 200 || $status >= 300) {
  json_out([
    'error' => 'openai_error',
    'status' => $status,
    'response' => $data
  ], 502);
}

/**
 * Extract reply
 */
$reply = trim($data['choices'][0]['message']['content'] ?? '');

if ($reply === '') {
  json_out([
    'error' => 'empty_response',
    'debug' => $data
  ], 502);
}

json_out([
  'reply' => $reply,
  'source' => 'openai',
  'model' => $model
]);
?>