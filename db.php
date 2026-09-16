<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function moods_store_path(): string {
  $p = cfg('MOODS_STORE_PATH');
  if ($p && trim($p) !== '') return $p;
  return __DIR__ . '/moods.jsonl';
}

function users_store_path(): string {
  $p = cfg('USERS_STORE_PATH');
  if ($p && trim($p) !== '') return $p;
  return __DIR__ . '/users.jsonl';
}

/**
 * @return array{id:int,name:string,password_hash:string,created_at:string}|null
 */
function users_find_by_name(string $name): ?array {
  $name = trim($name);
  if ($name === '') return null;
  $path = users_store_path();
  if (!is_file($path)) return null;
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) return null;
  // Scan from end for latest record (fast enough for demo).
  for ($i = count($lines) - 1; $i >= 0; $i--) {
    $line = trim((string)$lines[$i]);
    if ($line === '') continue;
    $row = json_decode($line, true);
    if (!is_array($row)) continue;
    if (!isset($row['name'])) continue;
    if ((string)$row['name'] === $name) {
      return [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'password_hash' => (string)($row['password_hash'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
      ];
    }
  }
  return null;
}

function users_next_id(): int {
  $path = users_store_path();
  if (!is_file($path)) return 1;
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) return 1;
  $lastId = 0;
  foreach ($lines as $line) {
    $line = trim((string)$line);
    if ($line === '') continue;
    $row = json_decode($line, true);
    if (is_array($row) && isset($row['id'])) {
      $id = (int)$row['id'];
      if ($id > $lastId) $lastId = $id;
    }
  }
  return $lastId + 1;
}

/**
 * Create a new user.
 * @return array{id:int,name:string,password_hash:string,created_at:string}
 */
function users_create(string $name, string $password): array {
  $name = trim($name);
  if ($name === '') throw new RuntimeException('bad_name');
  if (strlen($name) > 50) $name = substr($name, 0, 50);
  if (strlen($password) < 4) throw new RuntimeException('weak_password');

  if (users_find_by_name($name) !== null) {
    throw new RuntimeException('user_exists');
  }

  $row = [
    'id' => users_next_id(),
    'name' => $name,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'created_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
  ];

  $path = users_store_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
  $fh = fopen($path, 'ab');
  if ($fh === false) throw new RuntimeException('cannot_open_users_store');
  if (flock($fh, LOCK_EX)) {
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    fflush($fh);
    flock($fh, LOCK_UN);
  } else {
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
  }
  fclose($fh);

  return $row;
}

function moods_next_id(): int {
  $path = moods_store_path();
  if (!is_file($path)) return 1;
  $fh = fopen($path, 'rb');
  if ($fh === false) return 1;
  $lastId = 0;
  while (!feof($fh)) {
    $line = fgets($fh);
    if ($line === false) break;
    $line = trim($line);
    if ($line === '') continue;
    $row = json_decode($line, true);
    if (is_array($row) && isset($row['id'])) {
      $id = (int)$row['id'];
      if ($id > $lastId) $lastId = $id;
    }
  }
  fclose($fh);
  return $lastId + 1;
}

/**
 * Save one mood row. Uses a JSONL file so it works even when PHP has no DB drivers.
 * @param array{id:int,created_at:string,date_label:string,mood_score:int,note:string,user_name:string,user_ip:string} $row
 */
function moods_insert(array $row): void {
  $path = moods_store_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
  $fh = fopen($path, 'ab');
  if ($fh === false) {
    throw new RuntimeException('cannot_open_store');
  }
  // Simple append + file lock.
  if (flock($fh, LOCK_EX)) {
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    fflush($fh);
    flock($fh, LOCK_UN);
  } else {
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
  }
  fclose($fh);
}

/**
 * Read latest moods, newest first.
 * @return array<int, array{id:int,created_at:string,date_label:string,mood_score:int,note:string,user_name:string,user_ip:string}>
 */
function moods_list(int $limit): array {
  $path = moods_store_path();
  if (!is_file($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) return [];
  $rows = [];
  for ($i = count($lines) - 1; $i >= 0; $i--) {
    $line = trim((string)$lines[$i]);
    if ($line === '') continue;
    $row = json_decode($line, true);
    if (!is_array($row)) continue;
    $rows[] = $row;
    if (count($rows) >= $limit) break;
  }
  return $rows;
}

function alerts_store_path(): string {
  $p = cfg('ALERTS_STORE_PATH');
  if ($p && trim($p) !== '') return $p;
  return __DIR__ . '/alerts.jsonl';
}

function alerts_next_id(): int {
  $path = alerts_store_path();
  if (!is_file($path)) return 1;
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) return 1;
  $lastId = 0;
  foreach ($lines as $line) {
    $line = trim((string)$line);
    if ($line === '') continue;
    $row = json_decode($line, true);
    if (is_array($row) && isset($row['id'])) {
      $id = (int)$row['id'];
      if ($id > $lastId) $lastId = $id;
    }
  }
  return $lastId + 1;
}

/**
 * @param array{id:int,created_at:string,alert_type:string,message:string,user_name:string,user_ip:string,source:string} $row
 */
function alerts_insert(array $row): void {
  $path = alerts_store_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
  $fh = fopen($path, 'ab');
  if ($fh === false) {
    throw new RuntimeException('cannot_open_alerts_store');
  }
  if (flock($fh, LOCK_EX)) {
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    fflush($fh);
    flock($fh, LOCK_UN);
  } else {
    fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
  }
  fclose($fh);
}

/**
 * @return array<int, array{id:int,created_at:string,alert_type:string,message:string,user_name:string,user_ip:string,source:string}>
 */
function alerts_list(int $limit): array {
  $path = alerts_store_path();
  if (!is_file($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) return [];
  $rows = [];
  for ($i = count($lines) - 1; $i >= 0; $i--) {
    $line = trim((string)$lines[$i]);
    if ($line === '') continue;
    $row = json_decode($line, true);
    if (!is_array($row)) continue;
    $rows[] = $row;
    if (count($rows) >= $limit) break;
  }
  return $rows;
}

/**
 * Log a safety alert for the admin dashboard.
 */
function alerts_log(string $message, string $alertType, string $source, string $userName = '', string $userIp = ''): void {
  $message = trim($message);
  if ($message === '') return;
  if (strlen($message) > 500) $message = substr($message, 0, 500);

  alerts_insert([
    'id' => alerts_next_id(),
    'created_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    'alert_type' => $alertType,
    'message' => $message,
    'user_name' => $userName,
    'user_ip' => $userIp,
    'source' => $source,
  ]);
}

function admin_password(): ?string {
  $p = cfg('ADMIN_PASSWORD');
  if ($p === null) return null;
  $p = trim($p);
  return $p === '' ? null : $p;
}

function require_admin(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'admin_required';
    exit;
  }
}

?>
