<?php
function html_head(string $title, string $class = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Harbourline Institute</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600&family=Sora:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="<?= e($class) ?>">
<?php }

function auth_start(string $title): void { html_head($title, 'auth'); ?>
  <aside class="brand"><div class="brand-inner">
    <p class="wordmark">Harbourline Institute</p>
    <h1>Learn where the tide turns.</h1>
    <p class="sub">Department of Networking and Web Technologies. Portal for timetables, results and notices.</p>
  </div></aside>
  <main class="panel">
<?php }
function auth_end(): void { echo "</main>\n</body>\n</html>"; }

function app_start(string $title, array $user): void {
    html_head($title);
    $here  = basename($_SERVER['SCRIPT_NAME']);
    $links = ['dashboard.php' => 'Dashboard', 'session.php' => 'Session inspector',
              'history.php' => 'Activity', 'password.php' => 'Password']; ?>
  <header class="topbar">
    <span class="wordmark">Harbourline Institute</span>
    <nav>
      <?php foreach ($links as $f => $l): ?>
        <a href="<?= $f ?>"<?= $f === $here ? ' aria-current="page"' : '' ?>><?= $l ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <span class="who"><?= e($user['name']) ?></span>
      <button type="submit" class="ghost">Sign out</button>
    </form>
  </header>
  <main class="wrap">
<?php }
function app_end(): void { echo "</main>\n<script src=\"app.js\"></script>\n</body>\n</html>"; }

function kv(array $rows): void {
    echo '<dl class="kv">';
    foreach ($rows as $k => $v) echo '<dt>' . e((string)$k) . '</dt><dd>' . $v . '</dd>';
    echo '</dl>';
}
