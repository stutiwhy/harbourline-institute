<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

$c   = session_get_cookie_params();
$sid = session_id();
// Never display the real session ID or cookie value: anyone who sees it could act as you
$hide = fn(string $v) => preg_replace('/(' . preg_quote(session_name(), '/') . '=)[^;]+/', '$1•••', $v);

$reqHeaders = function_exists('getallheaders') ? getallheaders() : [];
$resHeaders = [];
foreach (headers_list() as $h) {
    [$k, $v] = explode(':', $h, 2) + [1 => ''];
    $resHeaders[$k] = $hide(trim($v));
}
foreach ($reqHeaders as $k => $v) $reqHeaders[$k] = $hide($v);

app_start('Session inspector', $user); ?>
    <h1>Session inspector</h1>
    <p class="muted">What the browser and server are exchanging right now, on this page.</p>

    <section class="block">
      <h2>Your session</h2>
      <p class="note">HTTP is stateless. The server recognises you only because your browser sends this ID back in a cookie on every request.</p>
      <?php kv([
        'Cookie name'     => '<code>' . e(session_name()) . '</code>',
        'Session ID'      => '<code>' . e(substr($sid, 0, 6)) . '••••••</code> (' . strlen($sid) . ' characters, random)',
        'Signed in at'    => e(date('H:i:s, d M Y', $_SESSION['started'] ?? time())),
        'Idle timeout in' => '<span data-countdown="' . SESSION_IDLE . '">' . gmdate('i:s', SESSION_IDLE) . '</span> if you do nothing',
        'Bound to'        => e(ua_short($_SERVER['HTTP_USER_AGENT'] ?? '')) . ' (a different browser using this ID is rejected)',
      ]); ?>
    </section>

    <section class="block">
      <h2>Cookie protections</h2>
      <?php kv([
        'HttpOnly' => $c['httponly'] ? 'Yes. JavaScript cannot read the cookie, so injected scripts cannot steal it.' : 'No',
        'SameSite' => e((string)$c['samesite']) . '. The browser will not attach it to requests started by other sites.',
        'Secure'   => $c['secure'] ? 'Yes. Only sent over HTTPS.' : 'No, because this page is on plain HTTP. Use HTTPS in production.',
        'Lifetime' => 'Session cookie. It is deleted when the browser closes.',
      ]); ?>
    </section>

    <section class="block">
      <h2>This connection</h2>
      <?php kv([
        'Your IP : port'   => e(client_ip() . ' : ' . ($_SERVER['REMOTE_PORT'] ?? '?')),
        'Server IP : port' => e(($_SERVER['SERVER_ADDR'] ?? '?') . ' : ' . ($_SERVER['SERVER_PORT'] ?? '?')),
        'Request line'     => '<code>' . e(($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' ' . ($_SERVER['SERVER_PROTOCOL'] ?? '')) . '</code>',
        'Encrypted (TLS)'  => $https ? 'Yes' : 'No',
      ]); ?>
    </section>

    <section class="block">
      <h2>Request headers (browser to server)</h2>
      <?php kv(array_map('e', $reqHeaders)); ?>
    </section>

    <section class="block">
      <h2>Response headers (server to browser)</h2>
      <p class="note">The security headers set in <code>config.php</code> appear here, along with the cookie being refreshed.</p>
      <?php kv(array_map('e', $resHeaders)); ?>
    </section>
<?php app_end();
