# harbourline-institute

a secure login and session handling system written in plain php for a dummy college, harbourline institute (department of networking and web technologies). it covers registration, login, logout, password change, account lockout and an activity log, and adds a session inspector page that shows the http and networking details behind a login session: cookie flags, ip addresses, ports, request headers and response headers. no real session id or cookie value is ever displayed or stored in this readme or by the app (see "privacy of session data").

## stack

php 8+, sqlite (pdo), html5, css3, vanilla javascript (one small file), php built-in server

## requirements

- php 8.0 or newer
- php extensions: pdo_sqlite, sqlite3, session (default), mbstring (used by register.php for name length, or swap mb_strlen for strlen)
- any modern browser
- no database server, no composer, no npm
- optional: apache or nginx instead of the built-in server
- tested target: windows with php extracted to c:\php, also works on linux and macos

## deployment

local only:
- php built-in development server on http://localhost:8000
- sqlite database file created automatically in a data folder next to the code

for a real deployment see "production notes".

## features

- user registration with server-side validation
- login with generic error messages and equal-time checks
- salted password hashing with automatic rehash upgrade
- server-side sessions with hardened cookie settings
- session id regeneration at login and after password change
- idle timeout and browser binding
- csrf tokens on every form, including logout
- account lockout after repeated failures
- per-user activity log with ip address and browser
- session inspector page with live idle countdown
- security headers on every response, including a content security policy
- responsive ui with visible keyboard focus and accessible form labels

## project structure

### core

- **config.php**: constants, security headers, session cookie settings, session start, sqlite connection and table creation, helper functions (escaping, csrf, flash messages, ip, browser name, password rules, session destruction, current user check).
- **layout.php**: shared page shells. auth pages (login, register) get the brand panel plus a form panel, app pages get a top bar with navigation and a sign out form. also holds a small key/value list renderer used by the inspector.
- **setup.php**: one-time script that creates the database and a demo user. delete it after running once.
- **index.php**: sends visitors to the dashboard if signed in, otherwise to login.

### authentication pages

- **login.php**: validates csrf, looks up the user, checks lockout, verifies the password, regenerates the session id, records the event.
- **register.php**: validates name, email, department and password, hashes the password, inserts the user, then redirects to login with a flash message.
- **logout.php**: post only with csrf check, logs the event, destroys the session and starts a clean one.
- **password.php**: verifies the current password, enforces the password rule, stores a new hash, regenerates the session id.

### protected pages

- **dashboard.php**: sample timetable and notices for the signed-in user.
- **session.php**: session inspector (cookie protections, connection details, request and response headers, masked values).
- **history.php**: the latest 25 events for the signed-in account.

### assets

- **style.css**: all styling, design tokens as css variables, one breakpoint at 800px.
- **app.js**: idle countdown on the inspector page. external file so the content security policy can block inline scripts.

### database

sqlite file at data/app.sqlite, created on first use with two tables.

**users**
- id: integer primary key, autoincrement
- name: text, required
- email: text, required, unique (stored lowercase)
- password_hash: text, required (bcrypt string, about 60 characters, contains algorithm, cost and salt)
- department: text, required
- failed_attempts: integer, default 0
- locked_until: integer unix time, default 0

**login_log**
- id: integer primary key, autoincrement
- user_id: integer, null for attempts on unknown emails
- email: text, the address that was used
- event: text, one of login_ok, login_fail, account_locked, logout, register, password_changed
- ip: text, the tcp peer address
- ua: text, the browser user-agent string
- at: integer unix time

the data folder is created with permission 0700 and an .htaccess file containing "require all denied" so apache will not serve the database file.

## authentication flow

1. the browser requests login.php and receives the form, a session cookie and a csrf token tied to that session.
2. the user submits email and password plus the csrf token over post.
3. the server rejects the request if the token is missing or does not match (compared with hash_equals).
4. the email is lowercased and trimmed, then the user row is fetched with a prepared statement.
5. if the account is locked, the user sees how many minutes remain and no password check happens.
6. password_verify compares the password with the stored hash. if the email does not exist, a fixed dummy bcrypt hash is verified instead so response time does not reveal which emails are registered.
7. on success: failed_attempts and locked_until are reset, the hash is upgraded if password_needs_rehash says so, session_regenerate_id(true) issues a new id and deletes the old session, then user_id, started, last_seen and a browser fingerprint are stored in the session, the event is logged and the user goes to the dashboard.
8. on failure: the counter goes up, the fifth failure sets a five minute lock and logs account_locked, otherwise login_fail is logged. the visible message is always the same generic text.
9. every protected page calls current_user() first and redirects to login if it returns nothing.

## password storage

- hashing uses password_hash with password_default, which is currently bcrypt. php may change this default in future versions, which is why password_needs_rehash is called at every successful login.
- a unique random salt is generated per password and stored inside the hash string, so no separate salt column is needed.
- the cost factor makes each guess slow on purpose, which limits offline brute force if the database leaks.
- plain passwords are never written to the database, the log or the session.
- verification uses password_verify, which compares in constant time.
- password rule: at least 10 characters with an upper case letter, a lower case letter and a number.
- bcrypt only reads the first 72 bytes of a password, so very long passwords are effectively truncated. argon2id has no such limit (see future scope).

## session handling

cookie settings, applied before session_start:
- name: phpsessid (php default)
- lifetime: 0, so it is a session cookie removed when the browser closes
- path: /
- httponly: true, so javascript cannot read the cookie
- samesite: strict, so the browser will not send it on requests started by other sites
- secure: true automatically when the request arrives over https, false on plain http localhost

server-side settings and behaviour:
- session.use_strict_mode is on, so the server refuses session ids it did not create. this blocks session fixation through attacker-chosen ids.
- the session id is regenerated at login and after a password change, with the old session deleted.
- idle timeout: 900 seconds. last_seen is updated on every protected request. if the gap is larger, the session is destroyed and a flash message explains why.
- browser binding: a sha-256 hash of the user-agent string is stored at login and compared on every request. a mismatch destroys the session. this stops a copied cookie being replayed from a different browser, though it is not a complete defence since a user-agent can be copied too.
- destroy_session() clears data, destroys the session, then starts a fresh empty session with a new id. this makes sure the next page still has a valid session to hold a csrf token and flash message.
- flash messages (sign out notice, account created) live in the session and are removed after one read.
- session data is stored by php in its default file storage on the server. only the random id travels to the browser.

## csrf protection

- token: 32 random bytes from random_bytes, hex encoded (64 characters), created once per session.
- every post form carries it in a hidden field, including sign out.
- comparison uses hash_equals to avoid timing differences.
- logout only acts on post requests with a valid token, so a malicious page cannot sign users out through an image tag or link.
- samesite=strict cookies add a second independent layer.

## brute force protection

- five failed attempts on an account set a five minute lock (constants in config.php, written in uppercase in the source: max_attempts and lock_seconds).
- the counter resets on a successful login.
- the lock is checked before the password, so locked accounts cannot be probed.
- every failure is logged with the source ip.
- known trade-off: an account-based lock lets someone lock out a known email on purpose. per-ip throttling is listed under future scope.

## injection and output safety

- sql: every query uses pdo prepared statements with bound values, no string concatenation.
- pdo is set to throw exceptions, and the duplicate email case on registration is handled by catching the unique constraint error.
- xss: all dynamic output goes through e(), which wraps htmlspecialchars with quotes escaped and utf-8.
- email format is checked with filter_var, department is checked against a fixed allowed list, name length is limited.

## security headers

sent on every response from config.php:
- **content-security-policy**: default-src 'self'; style-src 'self' plus fonts.googleapis.com; font-src fonts.gstatic.com; frame-ancestors 'none'; form-action 'self'. no inline scripts or inline styles are used, so none are allowed.
- **x-frame-options: deny**: the site cannot be shown inside a frame, which blocks clickjacking.
- **x-content-type-options: nosniff**: the browser must trust the declared content type.
- **referrer-policy: same-origin**: full urls are not sent to other sites.
- **cache headers**: php's session_start adds no-store and no-cache headers automatically, so signed-in pages are not kept in browser or proxy caches.

## networking and http details

what the session inspector explains:
- http is stateless. the server only recognises a user because the browser sends the session cookie with every request.
- the request line shows method, path and protocol version, for example "get /session.php http/1.1".
- client ip and port come from remote_addr and remote_port, which are the real tcp peer values. the x-forwarded-for header is deliberately ignored because any client can send it. only trust it behind a reverse proxy you control and configure.
- server ip and port show where the connection landed (localhost and 8000 on the dev server).
- tls status shows whether the request came over https.
- request headers are read with getallheaders(): host, user-agent, accept, accept-language, cookie and others.
- response headers are read with headers_list(): the security headers above and the cookie header being refreshed.
- the browser's developer tools network tab (f12) can be used side by side to compare what the server reports with what actually travelled.
- a javascript countdown starts from the idle timeout value so the timeout is visible live.

## privacy of session data

- the inspector never prints the cookie value. a regular expression replaces the value after the cookie name with a mask in both request and response headers, so it appears like `phpsessid=•••`.
- the session id row shows the cookie name, the id length and a masked value. it is not logged to the database.
- the activity log stores ip, browser string, event name and time, but never the session id or any password.
- this readme contains no real session ids, cookies, tokens or password hashes. any such value shown here is a placeholder.
- if you take screenshots for the report, avoid capturing the cookies tab of the browser developer tools.

## activity log

- events recorded: register, login_ok, login_fail, account_locked, logout, password_changed.
- failed attempts on a real account are attached to that account, so the owner sees them in the activity page. attempts on unknown emails are stored with no user id and are not shown to anyone.
- the page shows the latest 25 events with time, event badge, ip and a short browser name (parsed from the user-agent for display only).
- the log supports a simple form of risk awareness: unfamiliar ip addresses or repeated failures are visible to the user.

## user interface

- design tokens as css variables: ink #0f1b2d, page background #eef2f6, surface #ffffff, teal #0e7c7b (actions), darker teal #0a5f5e (hover), amber #f2a541 (focus ring and wordmark), error background #fdecec with text #a12626.
- type: sora for headings and wordmark, public sans for body text, loaded from google fonts with system font fallbacks.
- login and register use a split layout: a dark panel with fine horizontal "harbour lines" drawn with a repeating gradient, and a white form card. under 800px the layout stacks.
- the signed-in layout has a top bar with navigation, user name and sign out. tables scroll sideways on small screens instead of breaking the page.
- accessibility: every input has a label, errors use role alert and success messages role status, autocomplete attributes (username, current-password, new-password) help password managers, focus-visible outlines are always on.
- no frameworks, no build step.

## routes

- **/**: redirect based on sign in state
- **/login.php**: get shows form, post signs in
- **/register.php**: get shows form, post creates account
- **/dashboard.php**: signed in only
- **/session.php**: signed in only, session inspector
- **/history.php**: signed in only, activity log
- **/password.php**: signed in only, change password
- **/logout.php**: post only with csrf token

## running locally

1. install php 8+ (windows: extract to c:\php and add c:\php to path, then open a new terminal).
2. in c:\php\php.ini (copy php.ini-development if it does not exist) enable these lines by removing the leading semicolon: extension_dir = "ext", extension=pdo_sqlite, extension=sqlite3, extension=mbstring. keep extension=pdo_pgsql commented out.
3. check with `php -v` and `php -m | findstr -i sqlite`.
4. open a terminal in the project folder:

```
cd c:\users\stu\downloads\harbourline-institute
php setup.php
php -S localhost:8000
```

5. delete setup.php after the first run.
6. open http://localhost:8000.

demo account created by setup.php: stuti.m@harbourline.edu with password Stuti@1234 (for local testing only, remove it before any real use).

## configuration

constants at the top of config.php (uppercase in the source):
- session_idle: seconds of inactivity before sign out, default 900
- max_attempts: failures before lock, default 5
- lock_seconds: lock duration, default 300
- departments: allowed department list for registration
- db_file: sqlite path, default data/app.sqlite

## testing checklist

- valid login reaches the dashboard, wrong password shows the generic error
- unknown email and wrong password give the same message
- five wrong passwords lock the account, the event shows in the activity page
- visiting a protected page while signed out redirects to login
- sign out then pressing back does not reveal protected content
- waiting past the idle timeout signs the user out on the next click
- registering the same email twice is rejected, weak passwords are rejected
- submitting a form with a missing or wrong csrf token is rejected
- changing the password changes the session id
- the inspector shows masked cookie values and the same headers as the network tab

## production notes

- serve over https only, redirect http to https and add the strict-transport-security header.
- move the data folder outside the web root, or keep the deny rule if using apache. nginx ignores .htaccess, so add an equivalent location rule.
- delete setup.php and remove the demo credentials hint from the login page.
- turn off display_errors and log errors to a file.
- set the session save path to a private folder, or use a database or redis for multi-server setups.
- if behind a reverse proxy, configure php to read the forwarded client address only from that trusted proxy.
- consider moving from sqlite to mysql or postgresql for many concurrent users.

## known limitations

- the inspector shows the first six characters of the session id as an identifier. remove that prefix for stricter privacy.
- registration is open to anyone, with no email verification.
- no password reset, no multi-factor authentication, no roles.
- lockout is per account, not per ip.
- browser binding uses the user-agent string, which can be copied by an attacker who already has both cookie and header.
- the dashboard content is static sample data.

## future scope

- time-based one-time passwords (totp) and passkeys with webauthn
- email verification and expiring password reset tokens
- argon2id hashing and breached-password checks
- role-based access control for students, faculty and administrators
- per-ip rate limiting and suspicious login alerts
- list and revoke active sessions across devices
- automated tests and security scanning in a ci pipeline

## o/p demo ss

<img src="op-1.png">
<img src="op-2.png">
<img src="op-3.png">
<img src="op-4.png">
