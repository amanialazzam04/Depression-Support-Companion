<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

allow_cors();

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $body = json_decode($raw ?: 'null', true);
  if (!is_array($body)) {
    json_out(['error' => 'bad_json'], 400);
  }

  $message = trim((string)($body['message'] ?? ''));
  $alertType = trim((string)($body['alert_type'] ?? 'crisis'));
  $source = trim((string)($body['source'] ?? 'client'));

  if ($message === '') {
    json_out(['error' => 'message_required'], 400);
  }
  if (!in_array($alertType, ['crisis', 'concern'], true)) {
    $alertType = 'crisis';
  }
  if (!in_array($source, ['client', 'server'], true)) {
    $source = 'client';
  }

  // Only log messages that match our safety rules.
  if ($alertType === 'crisis' && !detect_crisis($message)) {
    json_out(['error' => 'not_an_alert'], 400);
  }

  $userName = '';
  if (isset($_SESSION['user_name']) && (string)$_SESSION['user_name'] !== '') {
    $userName = (string)$_SESSION['user_name'];
  } else if (isset($body['name'])) {
    $userName = trim((string)$body['name']);
    if (strlen($userName) > 50) $userName = substr($userName, 0, 50);
  }

  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

  alerts_log($message, $alertType, $source, $userName, $ip);
  json_out(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  require_admin();

  $limit = (int)($_GET['limit'] ?? 200);
  if ($limit < 1) $limit = 1;
  if ($limit > 2000) $limit = 2000;

  $rows = alerts_list($limit);
  json_out(['ok' => true, 'rows' => $rows]);
}

json_out(['error' => 'method_not_allowed'], 405);

?>
