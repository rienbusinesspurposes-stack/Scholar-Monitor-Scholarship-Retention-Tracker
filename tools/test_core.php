<?php
// Self-check for the core files. Run from Git Bash:  php tools/test_core.php
// Everything happens inside one database transaction that is rolled back at the end, so no data is kept.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$finished = false;
register_shutdown_function(function () use (&$finished) {
    if (!$finished) {
        echo "\nThe self-check stopped before it finished. Fix the problem shown above and run it again.\n";
        exit(1);
    }
});

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'test_core';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/functions.php';

$total = 0;
$failed = 0;

function check(string $label, bool $ok): void
{
    global $total, $failed;
    $total++;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
}

function run(string $sql, array $params = []): PDOStatement
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function rejects(string $sql, array $params = []): bool
{
    try {
        run($sql, $params);
        return false;
    } catch (PDOException $e) {
        return true;
    }
}

function makeUser(string $role = 'User', int $active = 1): int
{
    global $pdo;
    $n = 'tc_' . bin2hex(random_bytes(4));
    run('INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)',
        [$n, "$n@example.test", 'x', 'Test User', $role, $active]);
    return (int) $pdo->lastInsertId();
}

try {
    $pdo->beginTransaction();

    echo "-- Environment\n";
    check('PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='));
    check('pdo_mysql and mbstring are loaded', extension_loaded('pdo_mysql') && extension_loaded('mbstring'));
    check('real (native) prepared statements: stacked queries are refused', rejects('SELECT 1; SELECT 2'));
    check('database uses utf8mb4', run('SELECT DEFAULT_CHARACTER_SET_NAME AS c FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()')->fetch()['c'] === 'utf8mb4');

    echo "-- Folder protection\n";
    foreach (['config', 'includes', 'sql', 'tools', 'uploads/documents'] as $dir) {
        $f = "$root/$dir/.htaccess";
        check("$dir/.htaccess blocks web access", is_file($f) && str_contains(file_get_contents($f), 'Require all denied'));
    }
    check('.gitignore keeps config/local.php out of git', str_contains((string) @file_get_contents("$root/.gitignore"), 'config/local.php'));

    echo "-- Database structure\n";
    foreach (['users', 'login_attempts', 'login_confirmations', 'password_reset_tokens', 'security_audit_logs'] as $table) {
        $engine = run('SELECT ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->fetch();
        check("table $table exists and is InnoDB", $engine && $engine['e'] === 'InnoDB');
    }
    $type = run("SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetch();
    check("users.role allows exactly SuperAdmin, Admin, User", $type && $type['t'] === "enum('SuperAdmin','Admin','User')");
    $a = makeUser();
    $name = run('SELECT username FROM users WHERE id = ?', [$a])->fetch()['username'];
    check('duplicate username is refused', rejects('INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)', [$name, 'other@example.test', 'x', 'X']));
    check('duplicate email is refused', rejects('INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)', ['tc_other', "$name@example.test", 'x', 'X']));
    check('a role outside the three is refused (needs strict SQL mode)', rejects('INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)', ['tc_bad', 'bad@example.test', 'x', 'X', 'Teacher']));
    check('login code for a user that does not exist is refused', rejects('INSERT INTO login_confirmations (user_id, code_hash, expires_at) VALUES (?, ?, NOW())', [999999999, str_repeat('a', 64)]));
    $b = makeUser();
    createLoginConfirmation($b);
    run('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, NOW())', [$b, hash('sha256', 'tc')]);
    run("INSERT INTO security_audit_logs (user_id, action) VALUES (?, 'tc_marker')", [$b]);
    run('DELETE FROM users WHERE id = ?', [$b]);
    check('deleting a user removes their login codes and reset tokens',
        (int) run('SELECT COUNT(*) AS c FROM login_confirmations WHERE user_id = ?', [$b])->fetch()['c'] === 0
        && (int) run('SELECT COUNT(*) AS c FROM password_reset_tokens WHERE user_id = ?', [$b])->fetch()['c'] === 0);
    check('...but keeps their audit history, with the user left blank', run("SELECT user_id FROM security_audit_logs WHERE action = 'tc_marker'")->fetch()['user_id'] === null);

    echo "-- Login lockout\n";
    $who = 'tc_' . bin2hex(random_bytes(4));
    check('a new name is not locked', !loginIsLocked($who));
    for ($i = 0; $i < 4; $i++) {
        recordFailedLogin($who);
    }
    check('4 failures: not locked', !loginIsLocked($who));
    recordFailedLogin(' ' . strtoupper($who) . ' ');
    check('5th failure locks (capital letters and spaces make no difference)', loginIsLocked($who));
    check('a different name is not affected', !loginIsLocked($who . 'x'));
    run('UPDATE login_attempts SET attempted_at = NOW() - INTERVAL 4 MINUTE WHERE identifier = ?', [$who]);
    check('still locked after 4 minutes', loginIsLocked($who));
    run('UPDATE login_attempts SET attempted_at = NOW() - INTERVAL 6 MINUTE WHERE identifier = ?', [$who]);
    check('lock ends after 6 minutes (first lock is 5)', !loginIsLocked($who));
    recordFailedLogin($who);
    run('UPDATE login_attempts SET attempted_at = NOW() - INTERVAL 7 MINUTE WHERE identifier = ?', [$who]);
    check('6th failure makes the lock longer (10 minutes)', loginIsLocked($who));
    clearFailedLogins($who);
    check('a successful login clears the failures', !loginIsLocked($who));

    echo "-- Emailed login codes\n";
    $u = makeUser();
    $code = createLoginConfirmation($u);
    $hash = run('SELECT code_hash FROM login_confirmations WHERE user_id = ?', [$u])->fetch()['code_hash'];
    check('code is 6 digits', (bool) preg_match('/^\d{6}$/', $code));
    check('only a hash is stored, never the code', $hash !== $code && strlen($hash) <= 64);
    $wrong = $code === '000000' ? '111111' : '000000';
    check('wrong code is refused', !verifyLoginConfirmation($u, $wrong));
    check('right code is accepted', verifyLoginConfirmation($u, $code));
    check('the same code cannot be used twice', !verifyLoginConfirmation($u, $code));
    $code = createLoginConfirmation($u);
    for ($i = 0; $i < LOGIN_CODE_MAX_TRIES; $i++) {
        verifyLoginConfirmation($u, $wrong);
    }
    check('after 5 wrong tries even the right code is refused', !verifyLoginConfirmation($u, $code));
    $code = createLoginConfirmation($u);
    run('UPDATE login_confirmations SET expires_at = NOW() - INTERVAL 1 SECOND WHERE user_id = ? AND used_at IS NULL', [$u]);
    check('an expired code is refused', !verifyLoginConfirmation($u, $code));
    $other = makeUser();
    $code = createLoginConfirmation($u);
    check("one user's code does not work for another user", !verifyLoginConfirmation($other, $code) && verifyLoginConfirmation($u, $code));

    echo "-- CSRF and audit log\n";
    $_SESSION = [];
    $t = csrfToken();
    check('CSRF token is 64 characters and stays the same', (bool) preg_match('/^[a-f0-9]{64}$/', $t) && $t === csrfToken());
    check('the right token passes', verifyCsrfToken($t));
    check('a wrong, empty or missing token fails', !verifyCsrfToken('x') && !verifyCsrfToken('') && !verifyCsrfToken(null));
    auditLog($u, 'tc_audit', str_repeat('é', 800));
    $row = run("SELECT details, ip_address FROM security_audit_logs WHERE action = 'tc_audit'")->fetch();
    check('audit entry is saved with the address, long text cut to 500 characters', $row && mb_strlen($row['details']) === 500 && $row['ip_address'] === '127.0.0.1');
    $quiet = true;
    $logFile = tempnam(sys_get_temp_dir(), 'tc'); // the expected error goes here instead of onto the screen
    $oldLog = ini_set('error_log', $logFile);
    try { auditLog(999999999, 'tc_audit'); } catch (Throwable $e) { $quiet = false; }
    ini_set('error_log', (string) $oldLog);
    unlink($logFile);
    check('a failed audit write never stops the page', $quiet);

    echo "-- Roles and the logged-in user\n";
    check('each role goes to its own home page', homeFor('SuperAdmin') === 'super_admin/dashboard.php' && homeFor('Admin') === 'admin/dashboard.php' && homeFor('User') === 'user/dashboard.php');
    check('an unknown role never reaches an admin page', homeFor('Hacker') === 'user/dashboard.php');
    check('usernames: letters, digits, _ and . only, 3 to 50 long', isValidUsername('ana_1.x') && !isValidUsername('ab') && !isValidUsername('a b') && !isValidUsername("ana\n") && !isValidUsername('a@b'));
    $_SESSION = [];
    check('nobody logged in: currentUser is empty', currentUser() === null);
    foreach (['abc', -5, 0] as $fake) {
        $_SESSION['user_id'] = $fake;
        check("a made-up session id (" . var_export($fake, true) . ") is not a login", currentUser() === null);
    }
    $id = makeUser('Admin');
    $_SESSION['user_id'] = $id;
    check('a real session id returns the account and its role', (currentUser()['role'] ?? '') === 'Admin');
    run("UPDATE users SET role = 'User' WHERE id = ?", [$id]);
    check('a role changed in the database applies at once', (currentUser()['role'] ?? '') === 'User');
    run('UPDATE users SET is_active = 0 WHERE id = ?', [$id]);
    check('a deactivated account is logged out', currentUser() === null && empty($_SESSION['user_id']));

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nERROR  " . $e->getMessage() . "\n";
    $finished = true;
    exit(1);
}

$finished = true;
echo "\n$total checks, $failed failed" . ($failed ? "  <-- fix these before going on\n" : "  -- all good\n");
exit($failed ? 1 : 0);
