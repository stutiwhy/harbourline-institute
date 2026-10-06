<?php
// Run once:  php setup.php   (or open it in the browser), then DELETE this file.
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    exit("Setup already done. Delete setup.php.\n");
}

$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, department) VALUES (?, ?, ?, ?)');
$stmt->execute([
    'Stuti M',
    'stuti.m@harbourline.edu',
    password_hash('Stuti@1234', PASSWORD_DEFAULT),   // bcrypt/argon2 with random salt
    'Mechanical Engineering',
]);

echo "Done. Demo login:\n  stuti.m@harbourline.edu\n  Stuti@1234\n\nNow delete setup.php.\n";
