<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'; // keeps timing equal for unknown emails

$error = '';
$email = '';
$notice = flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['locked_until'] > time()) {
            $mins  = (int)ceil(($user['locked_until'] - time()) / 60);
            $error = "Too many attempts. Try again in $mins minute" . ($mins > 1 ? 's' : '') . '.';
        } else {
            $ok = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);

            if ($user && $ok) {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = 0 WHERE id = ?')->execute([$user['id']]);

                session_regenerate_id(true);                       // new ID after login: stops session fixation
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['started']   = time();
                $_SESSION['last_seen'] = time();
                $_SESSION['ua']        = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                log_event((int)$user['id'], $email, 'login_ok');
                header('Location: dashboard.php');
                exit;
            }

            if ($user) {
                $attempts = $user['failed_attempts'] + 1;
                $lock     = $attempts >= MAX_ATTEMPTS ? time() + LOCK_SECONDS : 0;
                $pdo->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?')
                    ->execute([$lock ? 0 : $attempts, $lock, $user['id']]);
                log_event((int)$user['id'], $email, $lock ? 'account_locked' : 'login_fail');
            } else {
                log_event(null, $email, 'login_fail');
            }
            $error = 'Email or password is incorrect.';
        }
    }
}

auth_start('Sign in'); ?>
    <form method="post" class="card" novalidate>
      <h2>Sign in</h2>
      <p class="muted">Use your institute email address.</p>
      <?php if ($notice): ?><div class="alert ok" role="status"><?= e($notice) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>

      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Sign in</button>
      <p class="hint">New here? <a href="register.php">Create an account</a></p>
      <p class="hint">Demo: stuti.m@harbourline.edu / Stuti@1234</p>
    </form>
<?php auth_end();
