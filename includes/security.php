<?php
// Session hardening, security headers, CSRF, audit log, login lockout and emailed login codes.
// Load config/database.php first: these functions use its $pdo connection.

require_once __DIR__ . '/settings.php'; // the idle logout time is a setting (session_idle_minutes)

const LOGIN_MAX_FAILS      = 5;   // failures within an hour before a lockout starts
const LOGIN_MAX_FAILS_PER_IP = 50; // failures on any names from one address within an hour
const LOGIN_CODE_MINUTES   = 10;
const LOGIN_CODE_MAX_TRIES = 5;
const LOGIN_CODE_HOURLY_TRIES = 15; // across all of one account's codes, so asking for a new code does not reset the allowance

function startSecureSession(): void
{
    if (PHP_SAPI === 'cli' || headers_sent() || session_status() !== PHP_SESSION_NONE) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store'); // the Back button must not show private pages after logout

    // shortcut: HTTPS is read from the server variable only, upgrade if the site ever sits behind a proxy
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('SMSESSID');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > setting('session_idle_minutes') * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

function verifyCsrfToken(?string $token): bool
{
    return $token !== null && $token !== '' && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

// Call at the top of every page or action that changes data. Accepts the form field or the AJAX header.
function enforceCsrfOnPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $token = $_POST['_csrf'] ?? '';
    if ($token === '') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (!is_string($token) || !verifyCsrfToken($token)) {
        http_response_code(403);
        exit('Invalid or expired form. Go back, reload the page and try again.');
    }
}

// shortcut: only REMOTE_ADDR is trusted (X-Forwarded-For can be faked), upgrade if deployed behind a proxy
function clientIp(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// A failed log write is reported to the PHP error log but never blocks the user's action.
function auditLog(?int $userId, string $action, ?string $details = null): void
{
    global $pdo;
    try {
        $pdo->prepare(
            'INSERT INTO security_audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            mb_substr($action, 0, 60),
            $details === null ? null : mb_substr($details, 0, 500),
            clientIp(),
            mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255) ?: null,
        ]);
    } catch (PDOException $e) {
        error_log('Scholar Monitor audit log failed: ' . $e->getMessage());
    }
}

function loginKey(string $identifier): string
{
    return mb_substr(mb_strtolower(trim($identifier)), 0, 255);
}

// Locked after LOGIN_MAX_FAILS failures in the last hour: 5 minutes, then +5 for each further failure, max 60.
// Counted per username/email, whether or not the account exists, so the page never reveals which names are real.
// Check this before checking the password, and do not record attempts made while locked.
function loginIsLocked(string $identifier): bool
{
    global $pdo;
    // One address trying many different names (password spraying). Sign-up and reset throttle rows hold a colon and are not logins.
    // shortcut: classmates behind one shared address share this limit, upgrade by raising LOGIN_MAX_FAILS_PER_IP if that ever bites.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND identifier NOT LIKE '%:%' AND attempted_at > NOW() - INTERVAL 60 MINUTE");
    $stmt->execute([clientIp()]);
    if ((int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_PER_IP) {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS fails, TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS idle
         FROM login_attempts
         WHERE identifier = ? AND attempted_at > NOW() - INTERVAL 60 MINUTE'
    );
    $stmt->execute([loginKey($identifier)]);
    $row = $stmt->fetch();
    if ((int) $row['fails'] < LOGIN_MAX_FAILS) {
        return false;
    }
    $lockMinutes = min(60, 5 * ((int) $row['fails'] - LOGIN_MAX_FAILS + 1));
    return (int) $row['idle'] < $lockMinutes * 60;
}

function recordFailedLogin(string $identifier): void
{
    global $pdo;
    $pdo->prepare('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)')
        ->execute([loginKey($identifier), clientIp()]);
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
}

function clearFailedLogins(string $identifier): void
{
    global $pdo;
    $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([loginKey($identifier)]);
}

// Returns the 6-digit code to email. Only a hash is stored, and any older unused code is cancelled.
function createLoginConfirmation(int $userId): string
{
    global $pdo;
    $code = sprintf('%06d', random_int(0, 999999));
    $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([$userId]);
    $pdo->prepare(
        'INSERT INTO login_confirmations (user_id, code_hash, expires_at)
         VALUES (?, ?, NOW() + INTERVAL ' . LOGIN_CODE_MINUTES . ' MINUTE)'
    )->execute([$userId, password_hash($code, PASSWORD_BCRYPT)]); // bcrypt is 60 chars, fits CHAR(64)
    return $code;
}

// Every wrong guess uses up one of LOGIN_CODE_MAX_TRIES, and a code works only once.
function verifyLoginConfirmation(int $userId, string $code): bool
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(tries), 0) FROM login_confirmations WHERE user_id = ? AND created_at > NOW() - INTERVAL 60 MINUTE');
    $stmt->execute([$userId]);
    if ((int) $stmt->fetchColumn() >= LOGIN_CODE_HOURLY_TRIES) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT id, code_hash FROM login_confirmations
         WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $use = $pdo->prepare('UPDATE login_confirmations SET tries = tries + 1 WHERE id = ? AND tries < ? AND used_at IS NULL');
    $use->execute([$row['id'], LOGIN_CODE_MAX_TRIES]);
    if ($use->rowCount() !== 1 || !password_verify($code, $row['code_hash'])) {
        return false;
    }
    $done = $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
    $done->execute([$row['id']]);
    return $done->rowCount() === 1;
}

startSecureSession();