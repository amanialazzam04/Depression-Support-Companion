<?php
declare(strict_types=1);

require __DIR__ . '/api/config.php';
require __DIR__ . '/api/db.php';

session_start();

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (isset($_GET['logout'])) {
  unset($_SESSION['is_admin']);
  header('Location: /admin.php');
  exit;
}

$adminPass = admin_password();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
  $pw = (string)($_POST['password'] ?? '');
  if ($adminPass === null) {
    $_SESSION['admin_error'] = '.';
    header('Location: /admin.php');
    exit;
  }
  if (hash_equals($adminPass, $pw)) {
    $_SESSION['is_admin'] = true;
    header('Location: /admin.php');
    exit;
  }
  $_SESSION['admin_error'] = 'Wrong password.';
  header('Location: /admin.php');
  exit;
}
//check if this admin or user 
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$isAdmin) {
  $err = (string)($_SESSION['admin_error'] ?? '');
  unset($_SESSION['admin_error']);
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — Mindful Companion</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/styles.css" />
  <style>
    .login-wrap { max-width: 560px; margin: 2.2rem auto; padding: 0 1rem; }
    .login-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem 1.35rem; }
    .login-card h1 { font-family: "Fraunces", Georgia, serif; color: var(--accent); margin: 0 0 0.5rem; }
    .login-card p { color: var(--text-muted); margin: 0 0 1rem; }
    .login-card label { display:block; font-weight: 600; margin-bottom: 0.35rem; }
    .login-card input { width: 100%; max-width: 360px; padding: 0.6rem 0.75rem; border: 1px solid var(--border); border-radius: 10px; font-family: inherit; }
    .login-actions { margin-top: 1rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    .err { margin-top: 0.75rem; color: #7b1b1b; background: #fde8e8; border: 1px solid #c53030; padding: 0.6rem 0.75rem; border-radius: 10px; }
    .hint { font-size: 0.95rem; color: var(--text-muted); }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <h1>Admin</h1>
      <p>Login to view mood check-ins saved to the database.</p>
      <form method="post" action="/admin.php">
        <input type="hidden" name="action" value="login" />
        <label for="password">Admin password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required />
        <div class="login-actions">
          <button type="submit" class="btn btn-primary">Login</button>
          <span class="hint">Set it in <code>api/.env</code> as <code>ADMIN_PASSWORD=...</code></span>
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

$limit = 500;
$alertLimit = 200;
$alerts = alerts_list($alertLimit);
$rows = moods_list($limit);
//convert the numbers to text 

function mood_label(int $score): string {
  return match ($score) {
    1 => 'Very low',
    2 => 'Low',
    3 => 'Okay',
    4 => 'Good',
    5 => 'Great',
    default => (string)$score,
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,600;1,9..144,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/styles.css" />
  <style>
    .admin-wrap { max-width: 1100px; margin: 1.5rem auto; padding: 0 1rem 2rem; }
    .admin-head { display:flex; align-items: baseline; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .admin-head h1 { font-family: "Fraunces", Georgia, serif; color: var(--accent); margin: 0; }
    .admin-actions { display:flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
    .admin-card { margin-top: 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.65rem 0.75rem; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { text-align: left; font-size: 0.95rem; color: var(--text-muted); background: #faf6f0; position: sticky; top: 0; }
    td.note { max-width: 520px; white-space: pre-wrap; word-break: break-word; }
    .muted { color: var(--text-muted); font-size: 0.95rem; }
    .pill { display:inline-block; padding: 0.15rem 0.5rem; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 0.9rem; }
    .pill--crisis { background: #fde8e8; color: #9b1c1c; }
    .empty { padding: 1rem; }
    .section-title { font-family: "Fraunces", Georgia, serif; color: var(--accent); margin: 1.75rem 0 0.5rem; font-size: 1.35rem; }
    .alert-banner {
      margin-top: 1rem;
      padding: 0.85rem 1rem;
      border-radius: var(--radius);
      border: 1px solid #c53030;
      background: #fde8e8;
      color: #7b1b1b;
      font-weight: 600;
    }
    td.message { max-width: 420px; white-space: pre-wrap; word-break: break-word; }
  </style>
</head>
<body>
  <div class="admin-wrap">
    <div class="admin-head">
      <div>
        <h1>Admin Dashboard</h1>
        <div class="muted">Safety alerts and mood check-ins</div>
      </div>
      <div class="admin-actions">
        <a class="btn btn-secondary" href="/index.php">Open app</a>
        <a class="btn btn-secondary" href="/admin.php?logout=1">Logout</a>
      </div>
    </div>

    <?php if (count($alerts) > 0): ?>
      <div class="alert-banner">
        <?= (int)count($alerts) ?> safety alert<?= count($alerts) === 1 ? '' : 's' ?> — review messages below.
      </div>
    <?php endif; ?>

    <h2 class="section-title">Safety alerts</h2>
    <div class="muted">Messages that triggered a crisis alert (self-harm / suicide keywords).</div>
    <div class="admin-card">
      <?php if (count($alerts) === 0): ?>
        <div class="empty">No safety alerts yet.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Time</th>
            <th>Type</th>
            <th>Message</th>
            <th>User</th>
            <th>Source</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alerts as $a): ?>
            <tr>
              <td><?= (int)$a['id'] ?></td>
              <td><?= h((string)$a['created_at']) ?></td>
              <td><span class="pill pill--crisis"><?= h((string)$a['alert_type']) ?></span></td>
              <td class="message"><?= h((string)$a['message']) ?></td>
              <td><?= h((string)$a['user_name']) ?></td>
              <td><?= h((string)$a['source']) ?></td>
              <td><?= h((string)$a['user_ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <h2 class="section-title">Mood check-ins</h2>
    <div class="muted">Showing latest <?= (int)count($rows) ?> (max <?= (int)$limit ?>)</div>
    <div class="admin-card">
      <?php if (count($rows) === 0): ?>
        <div class="empty">No mood entries yet.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Created</th>
            <th>Date label</th>
            <th>Mood</th>
            <th>Note</th>
            <th>User</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h((string)$r['created_at']) ?></td>
              <td><?= h((string)$r['date_label']) ?></td>
              <td><span class="pill"><?= h(mood_label((int)$r['mood_score'])) ?></span></td>
              <td class="note"><?= h((string)$r['note']) ?></td>
              <td><?= h((string)$r['user_name']) ?></td>
              <td><?= h((string)$r['user_ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

