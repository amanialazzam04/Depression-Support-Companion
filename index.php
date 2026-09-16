<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/api/db.php';

//Cross Site Scripting (XSS)

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
//logout 
if (isset($_GET['logout'])) {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], (bool)$params["secure"], (bool)$params["httponly"]);
  }
  session_destroy();
  header('Location: /index.php');
  exit;
}
//login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
  $name = trim((string)($_POST['name'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  // mbstring may be missing on some PHP installs; fall back to substr.
  $name = function_exists('mb_substr') ? mb_substr($name, 0, 50) : substr($name, 0, 50);

  if ($name === '') {
    $_SESSION['login_error'] = 'Please enter your name.';
    header('Location: /index.php');
    exit;
  }
  if (strlen($password) < 4) {
    $_SESSION['login_error'] = 'Password must be at least 4 characters.';
    header('Location: /index.php');
    exit;
  }
//new user 
  $user = users_find_by_name($name);
  if ($user === null) {
    // Auto-register on first login (simple demo UX).
    try {
      users_create($name, $password);
    } catch (Throwable $e) {
      $_SESSION['login_error'] = 'Could not create user. Try a different name.';
      header('Location: /index.php');
      exit;
    }
    $_SESSION['user_name'] = $name;
    header('Location: /index.php');
    exit;
  }

  $hash = (string)($user['password_hash'] ?? '');
  if ($hash === '' || !password_verify($password, $hash)) {
    $_SESSION['login_error'] = 'Wrong name or password.';
    header('Location: /index.php');
    exit;
  }

  $_SESSION['user_name'] = $name;
  header('Location: /index.php');
  exit;
}

$loggedIn = isset($_SESSION['user_name']) && (string)$_SESSION['user_name'] !== '';

if (!$loggedIn) {
  $err = (string)($_SESSION['login_error'] ?? '');
  unset($_SESSION['login_error']);
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — Depression Support Companion</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/styles.css" />
  <style>
    .login-wrap { max-width: 520px; margin: 2.2rem auto; padding: 0 1rem; }
    .login-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem 1.35rem; }
    .login-card h1 { font-family: "Fraunces", Georgia, serif; color: var(--accent); margin: 0 0 0.5rem; }
    .login-card p { color: var(--text-muted); margin: 0 0 1rem; }
    .login-card label { display:block; font-weight: 600; margin-bottom: 0.35rem; }
    .login-card input { width: 100%; max-width: 360px; padding: 0.6rem 0.75rem; border: 1px solid var(--border); border-radius: 10px; font-family: inherit; }
    .login-actions { margin-top: 1rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    .err { margin-top: 0.75rem; color: #7b1b1b; background: #fde8e8; border: 1px solid #c53030; padding: 0.6rem 0.75rem; border-radius: 10px; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <h1>Depression Support Companion</h1>
      <p>Login to start your chat and questionnaire. This is not a medical diagnosis.</p>
      <form method="post" action="/index.php">
        <input type="hidden" name="action" value="login" />
        <label for="name">Your name (or nickname)</label>
        <input id="name" name="name" maxlength="50" autocomplete="name" required />
        <label for="password" style="margin-top:0.75rem;">Password</label>
        <input id="password" name="password" type="password" minlength="4" autocomplete="current-password" required />
        <div class="login-actions">
          <button type="submit" class="btn btn-primary">Enter</button>
          <span class="panel-intro" style="margin:0;">First time: creates your account.</span>
        </div>
        <?php if ($err !== ''): ?>
          <div class="err"><?= h($err) ?></div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</body>
</html>
  <?php
  exit;
}

// Logged in: serve the existing frontend, injecting user name for per-user storage keys.
$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
  http_response_code(500);
  echo 'Failed to load index.html';
  exit;
}
//send name to java script 
$userName = (string)$_SESSION['user_name'];
$injected = "<script>window.__USER_NAME__=" . json_encode($userName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";</script>\n</head>";

header('Content-Type: text/html; charset=utf-8');
echo str_replace('</head>', $injected, $html);
?>
