<?php
// Self-check for includes/settings.php. Run from Git Bash:  php tools/test_settings.php
// Needs sql/004_settings.sql to have been run. Everything happens inside one database transaction that is rolled back at the end.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'test_settings';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/functions.php';

$total = 0;
$failed = 0;
function check(string $label, bool $ok): void
{
    global $total, $failed;
    $total++;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
}

$table = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'")->fetchColumn();
if (!$table) {
    echo "The settings table is missing. Run sql/004_settings.sql first.\n";
    exit(1);
}

$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO users (username, email, password_hash, full_name, role) VALUES ('zz_set', 'zz_set@example.test', 'x', 'Settings Test', 'SuperAdmin')");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec('DELETE FROM settings'); // rolled back at the end
    settingsStored(true);

    check('with nothing saved the defaults apply', setting('alert_due_soon_days') === 7 && setting('session_idle_minutes') === 30);
    check('saving a valid number works and applies at once', saveSetting($uid, 'alert_due_soon_days', '14') === null && setting('alert_due_soon_days') === 14);
    check('the other setting is not affected', setting('session_idle_minutes') === 30);
    check('spaces around the number are ignored', saveSetting($uid, 'session_idle_minutes', ' 45 ') === null && setting('session_idle_minutes') === 45);
    check('saving again replaces the value and keeps one row', saveSetting($uid, 'alert_due_soon_days', '3') === null
        && (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE name = 'alert_due_soon_days'")->fetchColumn() === 1 && setting('alert_due_soon_days') === 3);
    check('the lowest and highest allowed numbers are accepted', saveSetting($uid, 'alert_due_soon_days', '1') === null && saveSetting($uid, 'alert_due_soon_days', '60') === null);

    foreach (['0', '61', '-3', '7.5', '1e1', 'abc', '', '   ', '١٢', '99999'] as $bad) {
        check('refused: ' . var_export($bad, true), saveSetting($uid, 'alert_due_soon_days', $bad) !== null && setting('alert_due_soon_days') === 60);
    }
    check('idle minutes below 5 or above 240 are refused', saveSetting($uid, 'session_idle_minutes', '4') !== null && saveSetting($uid, 'session_idle_minutes', '241') !== null && setting('session_idle_minutes') === 45);
    check('a setting that does not exist is refused', saveSetting($uid, 'no_such_setting', '5') !== null
        && (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE name = 'no_such_setting'")->fetchColumn() === 0);
    check('who changed it is recorded', (int) $pdo->query("SELECT updated_by FROM settings WHERE name = 'session_idle_minutes'")->fetchColumn() === $uid);
    check('each change is in the audit log', (int) $pdo->query("SELECT COUNT(*) FROM security_audit_logs WHERE action = 'setting_changed' AND user_id = $uid")->fetchColumn() === 5 // the five successful saves; refused ones are not logged
        && (int) $pdo->query("SELECT COUNT(*) FROM security_audit_logs WHERE action = 'setting_changed' AND details = 'session_idle_minutes = 45'")->fetchColumn() === 1);

    // A value edited straight in the database to something unusable must never reach the code.
    $pdo->exec("UPDATE settings SET value = 'abc' WHERE name = 'alert_due_soon_days'");
    $pdo->exec("UPDATE settings SET value = '0' WHERE name = 'session_idle_minutes'");
    settingsStored(true);
    check('a damaged stored value falls back to the default', setting('alert_due_soon_days') === 7 && setting('session_idle_minutes') === 30);
    $pdo->exec("UPDATE settings SET value = '999999' WHERE name = 'session_idle_minutes'");
    settingsStored(true);
    check('an out-of-range stored value falls back to the default', setting('session_idle_minutes') === 30);

    $pdo->exec("DELETE FROM users WHERE id = $uid");
    check('deleting the account that changed a setting keeps the setting', (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 2);
} finally {
    $pdo->rollBack();
}
settingsStored(true);
check('nothing was kept', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'zz_set'")->fetchColumn() === 0);

echo "\n$total checks, $failed failed" . ($failed ? "  <-- fix these before going on\n" : "  -- all good\n");
exit($failed ? 1 : 0);