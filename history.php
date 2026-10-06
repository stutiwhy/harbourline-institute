<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

$stmt = db()->prepare('SELECT event, ip, ua, at FROM login_log WHERE user_id = ? ORDER BY id DESC LIMIT 25');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$labels = ['login_ok' => ['Signed in', 'good'], 'login_fail' => ['Failed sign-in', 'bad'],
           'account_locked' => ['Account locked', 'bad'], 'logout' => ['Signed out', ''],
           'register' => ['Account created', ''], 'password_changed' => ['Password changed', 'good']];

app_start('Activity', $user); ?>
    <h1>Account activity</h1>
    <p class="muted">The last 25 events on your account. If you see an IP address you don't recognise, change your password.</p>
    <section class="block">
      <div class="table-scroll"><table>
        <thead><tr><th>When</th><th>Event</th><th>IP address</th><th>Browser</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$text, $cls] = $labels[$r['event']] ?? [$r['event'], '']; ?>
          <tr>
            <td><?= e(date('d M, H:i:s', (int)$r['at'])) ?></td>
            <td><span class="badge <?= $cls ?>"><?= e($text) ?></span></td>
            <td><code><?= e((string)$r['ip']) ?></code></td>
            <td><?= e(ua_short($r['ua'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
<?php app_end();
