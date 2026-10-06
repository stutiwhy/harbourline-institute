<?php
declare(strict_types=1);

const DB_FILE      = __DIR__ . '/data/app.sqlite';
const SESSION_IDLE = 900;   // log out after 15 min of inactivity
const MAX_ATTEMPTS = 5;     // failed logins before temporary lock
const LOCK_SECONDS = 300;   // lock length (5 min)
const DEPARTMENTS  = ['Computer Networks', 'Web Technologies', 'Cyber Security', 'Marine Engineering'];

// --- Security headers (sent with every page) ---
header('X-Content-Type-Options: nosniff');        // no MIME sniffing
header('X-Frame-Options: DENY');                  // can't be embedded in a frame (clickjacking)
header('Referrer-Policy: same-origin');           // don't leak URLs to other sites
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'none'; form-action 'self'");

// --- Secure session setup ---
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure'   => $https,      // HTTPS only, when the site uses HTTPS
    'httponly' => true,        // invisible to JavaScript
    'samesite' => 'Strict',    // not sent on cross-site requests
]);
ini_set('session.use_strict_mode', '1');
session_start();

// --- Database ---
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, department TEXT NOT NULL,
        failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, email TEXT,
        event TEXT NOT NULL, ip TEXT, ua TEXT, at INTEGER NOT NULL)');
    return $pdo;
}

// --- Helpers ---
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// REMOTE_PORT/ADDR is the real TCP peer. X-Forwarded-For is client-supplied and can be faked,
// so it is deliberately NOT trusted here (only trust it behind a proxy you control).
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }

function ua_short(?string $ua): string {
    $ua = $ua ?? '';
    $b  = str_contains($ua, 'Edg') ? 'Edge' : (str_contains($ua, 'Firefox') ? 'Firefox'
        : (str_contains($ua, 'Chrome') ? 'Chrome' : (str_contains($ua, 'Safari') ? 'Safari' : 'Unknown browser')));
    $os = str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Android') ? 'Android'
        : (str_contains($ua, 'iPhone') ? 'iOS' : (str_contains($ua, 'Mac') ? 'macOS' : (str_contains($ua, 'Linux') ? 'Linux' : ''))));
    return trim("$b on $os", ' on');
}

function log_event(?int $uid, string $email, string $event): void {
    db()->prepare('INSERT INTO login_log (user_id, email, event, ip, ua, at) VALUES (?,?,?,?,?,?)')
        ->execute([$uid, $email, $event, client_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '', time()]);
}

function valid_password(string $p): bool {
    return strlen($p) >= 10 && preg_match('/[a-z]/', $p) && preg_match('/[A-Z]/', $p) && preg_match('/\d/', $p);
}
const PASSWORD_RULE = 'At least 10 characters with upper case, lower case and a number.';

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_valid(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

// Ends the session and starts a fresh, empty one (so forms on the next page still get a CSRF token)
function destroy_session(): void {
    $_SESSION = [];
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    if (time() - ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE) { destroy_session(); flash('Signed out after 15 minutes of inactivity.'); return null; }
    // Session bound to the browser that logged in: a stolen cookie used from another browser is rejected
    if (($_SESSION['ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) { destroy_session(); return null; }
    $_SESSION['last_seen'] = time();

    $s = db()->prepare('SELECT id, name, email, department FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    return $s->fetch() ?: null;
}
