<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$errors = [];
$name = $email = $dept = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $dept  = (string)($_POST['department'] ?? '');
    $pw    = (string)($_POST['password'] ?? '');

    if (!csrf_valid())                                $errors[] = 'Your session expired. Please try again.';
    if ($name === '' || mb_strlen($name) > 80)        $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Enter a valid email address.';
    if (!in_array($dept, DEPARTMENTS, true))          $errors[] = 'Choose a department.';
    if (!valid_password($pw))                         $errors[] = PASSWORD_RULE;
    if ($pw !== (string)($_POST['confirm'] ?? ''))    $errors[] = 'The two passwords do not match.';

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO users (name, email, password_hash, department) VALUES (?,?,?,?)')
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $dept]);
            log_event((int)db()->lastInsertId(), $email, 'register');
            flash('Account created. You can sign in now.');
            header('Location: login.php');
            exit;
        } catch (PDOException $ex) {
            $errors[] = 'That email address is already registered.';
        }
    }
}

auth_start('Create account'); ?>
    <form method="post" class="card" novalidate>
      <h2>Create account</h2>
      <p class="muted">Student and staff registration.</p>
      <?php foreach ($errors as $er): ?><div class="alert" role="alert"><?= e($er) ?></div><?php endforeach; ?>

      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label for="name">Full name</label>
      <input id="name" name="name" type="text" value="<?= e($name) ?>" autocomplete="name" required>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required>
      <label for="department">Department</label>
      <select id="department" name="department" required>
        <option value="">Choose…</option>
        <?php foreach (DEPARTMENTS as $d): ?>
          <option<?= $d === $dept ? ' selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required>
      <p class="hint left"><?= PASSWORD_RULE ?></p>
      <label for="confirm">Confirm password</label>
      <input id="confirm" name="confirm" type="password" autocomplete="new-password" required>
      <button type="submit">Create account</button>
      <p class="hint">Already registered? <a href="login.php">Sign in</a></p>
    </form>
<?php auth_end();
