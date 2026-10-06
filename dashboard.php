<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

$classes = [
    ['09:00', 'Computer Networks',     'Lab 2',  'Dr. Menon'],
    ['11:15', 'Web Application Security', 'Hall B', 'Prof. D\'Souza'],
    ['14:00', 'HTTP and Web Servers',  'Hall A', 'Dr. Kulkarni'],
    ['16:30', 'Packet Analysis Lab',   'Lab 4',  'Mr. Fernandes'],
];
$notices = [
    ['Wireshark lab: bring your own laptop on Thursday.',      'Today'],
    ['Mini-project topics (sessions, cookies, APIs) are open.', 'Yesterday'],
    ['Semester 5 results will be published on 14 October.',    '3 days ago'],
];

app_start('Dashboard', $user); ?>
    <h1>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p class="muted"><?= e($user['department']) ?> · <?= e($user['email']) ?></p>

    <section class="block">
      <h2>Today's classes</h2>
      <div class="table-scroll"><table>
        <thead><tr><th>Time</th><th>Course</th><th>Room</th><th>Faculty</th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
          <tr><td class="time"><?= e($c[0]) ?></td><td><?= e($c[1]) ?></td><td><?= e($c[2]) ?></td><td><?= e($c[3]) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>

    <section class="block">
      <h2>Notices</h2>
      <ul class="notices">
        <?php foreach ($notices as $n): ?>
          <li><span><?= e($n[0]) ?></span><time><?= e($n[1]) ?></time></li>
        <?php endforeach; ?>
      </ul>
    </section>
<?php app_end();
