<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

allow_cors();

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
//convert json to array 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $body = json_decode($raw ?: 'null', true);
  if (!is_array($body)) {
    json_out(['error' => 'bad_json'], 400);
  }

  $score = (int)($body['score'] ?? 0);
  $note = trim((string)($body['note'] ?? ''));
  $dateLabel = trim((string)($body['date'] ?? ''));
  if ($dateLabel === '') {
    $dateLabel = (new DateTimeImmutable('now'))->format('Y-m-d');
  }
//check num 
  if ($score < 1 || $score > 5) {
    json_out(['error' => 'score_out_of_range'], 400);
  }
  if (strlen($note) > 120) {
    $note = substr($note, 0, 120);
  }

  $userName = '';
  if (isset($_SESSION['user_name']) && (string)$_SESSION['user_name'] !== '') {
    $userName = (string)$_SESSION['user_name'];
  } else if (isset($body['name'])) {
    $userName = trim((string)$body['name']);
    if (strlen($userName) > 50) $userName = substr($userName, 0, 50);
  }

  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  $createdAt = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);

  $id = moods_next_id();
  moods_insert([
    'id' => $id,
    'created_at' => $createdAt,
    'date_label' => $dateLabel,
    'mood_score' => $score,
    'note' => $note,
    'user_name' => $userName,
    'user_ip' => $ip,
  ]);

  json_out(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // Admin-only view.
  require_admin();

  $limit = (int)($_GET['limit'] ?? 200);
  if ($limit < 1) $limit = 1;
  if ($limit > 2000) $limit = 2000;

  $rows = moods_list($limit);

  json_out(['ok' => true, 'rows' => $rows]);
}

json_out(['error' => 'method_not_allowed'], 405);

?>
