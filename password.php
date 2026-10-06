<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!csrf_valid())                              $errors[] = 'Your session expired. Please try again.';
    elseif (!password_verify($cur, $hash))          $errors[] = 'Your current password is incorrect.';
    elseif (!valid_password($new))                  $errors[] = PASSWORD_RULE;
    elseif ($new !== (string)($_POST['confirm'] ?? '')) $errors[] = 'The two new passwords do not match.';
    elseif ($new === $cur)                          $errors[] = 'Choose a password you have not used just now.';
    else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);               // new session ID after a privilege change
        log_event($user['id'], $user['email'], 'password_changed');
        $done = true;
    }
}

app_start('Password', $user); ?>
    <h1>Change password</h1>
    <p class="muted">Your session ID changes when you save, so any copy of the old one stops working.</p>
    <form method="post" class="block narrow" novalidate>
      <?php if ($done): ?><div class="alert ok" role="status">Password updated.</div><?php endif; ?>
      <?php foreach ($errors as $er): ?><div class="alert" role="alert"><?= e($er) ?></div><?php endforeach; ?>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="current">Current password</label>
      <input id="current" name="current" type="password" autocomplete="current-password" required>
      <label for="new">New password</label>
      <input id="new" name="new" type="password" autocomplete="new-password" required>
      <p class="hint left"><?= PASSWORD_RULE ?></p>
      <label for="confirm">Confirm new password</label>
      <input id="confirm" name="confirm" type="password" autocomplete="new-password" required>
      <button type="submit">Update password</button>
    </form>
<?php app_end();
