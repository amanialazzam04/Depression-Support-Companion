<?php
declare(strict_types=1);

function load_env_file(string $path): void {
  if (!is_file($path)) return;
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) return;

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    $pos = strpos($line, '=');
    if ($pos === false) continue;

    $k = trim(substr($line, 0, $pos));
    $v = trim(substr($line, $pos + 1));
    $v = trim($v, "\"'");

    if ($k === '') continue;

    if (getenv($k) === false) {
      putenv($k . '=' . $v);
      $_ENV[$k] = $v;
    }
  }
}

load_env_file(__DIR__ . '/.env');

function cfg(string $key, ?string $default = null): ?string {
  $v = getenv($key);
  if ($v === false || $v === '') return $default;
  return $v;
}

function json_out(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
//any location send request 
function allow_cors(): void {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');

  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}
//alerts 
function detect_crisis(string $text): bool {
  $patterns = [
    '/\b(kill myself|end it all|suicid|want to die|better off dead)\b/i',
    '/\b(no reason to live|can\'t go on)\b/i',
    '/\b(self[- ]harm|hurt myself|cut myself)\b/i',
  ];

  foreach ($patterns as $re) {
    if (preg_match($re, $text)) return true;
  }

  return false;
}

//http request 
function http_post_json(string $url, array $headers, string $body): array {
  $headers = array_merge(['Content-Type: application/json'], $headers);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  if ($resp === false) {
    return [
      'ok' => false,
      'status' => $status,
      'body' => '',
      'error' => $err ?: 'Request failed'
    ];
  }

  return [
    'ok' => true,
    'status' => $status,
    'body' => $resp,
    'error' => null
  ];
}
?>