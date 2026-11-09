<?php
// Self-check for includes/alerts.php. Run from Git Bash:  php tools/test_alerts.php
// Everything happens inside one database transaction that is rolled back at the end, and a fake mail function is used,
// so no data is kept and no real email is sent, even if mail is switched on in config/local.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'test_alerts';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/functions.php';
require $root . '/includes/scholar_status.php';
require $root . '/includes/mailer.php';
require $root . '/includes/alerts.php';

$total = 0;
$failed = 0;
function check(string $label, bool $ok): void
{
    global $total, $failed;
    $total++;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
}

$mailOk = true;
$mails = [];
$fake = function (string $to, string $subject, string $body) use (&$mails, &$mailOk): bool {
    $mails[] = compact('to', 'subject', 'body');
    return $mailOk;
};

$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO users (username, email, password_hash, full_name) VALUES ('zz_alert_s', 'zz_alert_s@example.test', 'x', 'Alert Student')");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO users (username, email, password_hash, full_name, role) VALUES ('zz_alert_a', 'zz_alert_a@example.test', 'x', 'Alert Admin', 'Admin')");
    $aid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES ('zz_alert_off', 'zz_alert_off@example.test', 'x', 'Alert Off', 'Admin', 0)");
    $pdo->exec("INSERT INTO scholarship_programs (name, min_grade, warning_margin) VALUES ('zz alert program', 85, 3)");
    $pid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO scholars (user_id, program_id, student_number, status, verified_at) VALUES ($uid, $pid, 'ZZ-ALERT', 'Active', NOW())");
    $sid = (int) $pdo->lastInsertId();

    $req = function (string $name, ?string $due, int $active = 1) use ($pdo, $pid): int {
        $stmt = $pdo->prepare('INSERT INTO document_requirements (program_id, name, due_date, is_active) VALUES (?, ?, ' . ($due ?? 'NULL') . ', ?)');
        $stmt->execute([$pid, $name, $active]);
        return (int) $pdo->lastInsertId();
    };
    $doc = fn(int $rid, string $status) => $pdo->exec("INSERT INTO scholar_documents (scholar_id, requirement_id, stored_name, original_name, status) VALUES ($sid, $rid, 'zz$rid', 'zz.pdf', '$status')");
    $keys = fn(string $who = 'user') => $pdo->query('SELECT dedupe_key FROM notifications WHERE user_id = ' . ($who === 'user' ? $uid : $aid) . ' ORDER BY dedupe_key')->fetchAll(PDO::FETCH_COLUMN);
    $message = fn(string $key) => (string) $pdo->query("SELECT message FROM notifications WHERE user_id = $uid AND dedupe_key = '$key'")->fetchColumn();
    $toAdmin = function () use (&$mails): array { // by reference: $mails is replaced between runs
        return array_values(array_filter($mails, fn($m) => $m['to'] === 'zz_alert_a@example.test'));
    };

    $soon = $req('Soon document', 'CURDATE() + INTERVAL 3 DAY');
    $late = $req('Overdue document', 'CURDATE() - INTERVAL 3 DAY');
    $today = $req('Due today', 'CURDATE()');
    $far = $req('Far document', 'CURDATE() + INTERVAL 30 DAY');
    $edge = $req('Edge document', 'CURDATE() + INTERVAL ' . ALERT_DUE_SOON_DAYS . ' DAY');
    $undated = $req('No date document', null);
    $retired = $req('Retired document', 'CURDATE() + INTERVAL 2 DAY', 0);
    $done = $req('Approved document', 'CURDATE() + INTERVAL 2 DAY');
    $bad = $req('Rejected document', 'CURDATE() + INTERVAL 2 DAY');
    $sent = $req('Submitted document', 'CURDATE() + INTERVAL 2 DAY');
    $doc($done, 'Approved');
    $doc($bad, 'Rejected');
    $doc($sent, 'Submitted');

    // Documents.
    $r = runAlerts($fake);
    $expected = ["due_{$sid}_{$soon}_soon", "due_{$sid}_{$late}_late", "due_{$sid}_{$today}_soon", "due_{$sid}_{$edge}_soon", "due_{$sid}_{$bad}_soon"];
    sort($expected);
    check('reminders go out for the right documents only', $keys() === $expected);
    check('the run reports one reminder per document', $r['students'] >= 5);
    check('a due-soon message names the document and the date', str_contains($message("due_{$sid}_{$soon}_soon"), 'Soon document')
        && str_contains($message("due_{$sid}_{$soon}_soon"), date('F j, Y', strtotime('+3 days'))));
    check('an overdue message says it was due', str_contains($message("due_{$sid}_{$late}_late"), 'was due on'));
    check('a rejected document says it must be uploaded again', str_contains($message("due_{$sid}_{$bad}_soon"), 'uploaded again'));
    check('no staff email while nobody is near the minimum grade', $toAdmin() === []);
    check('the run was written to the audit log', (int) $pdo->query("SELECT COUNT(*) FROM security_audit_logs WHERE action = 'alerts_run'")->fetchColumn() >= 1);

    $before = count($keys());
    runAlerts($fake);
    check('running again adds no reminders', count($keys()) === $before);
    $pdo->exec("UPDATE notifications SET read_at = NOW() WHERE user_id = $uid");
    runAlerts($fake);
    check('a reminder the student already read does not come back', (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $uid AND read_at IS NULL")->fetchColumn() === 0);

    $pdo->exec("UPDATE scholar_documents SET status = 'Approved' WHERE scholar_id = $sid AND requirement_id = $bad");
    $pdo->exec("UPDATE document_requirements SET due_date = CURDATE() - INTERVAL 1 DAY WHERE id = $soon");
    runAlerts($fake);
    check('a document that turns overdue gets one new reminder', in_array("due_{$sid}_{$soon}_late", $keys(), true) && count($keys()) === $before + 1);

    $newReq = $req('Added later', 'CURDATE() + INTERVAL 1 DAY');
    $pdo->exec("UPDATE scholars SET status = 'Ended' WHERE id = $sid");
    runAlerts($fake);
    check('an Ended scholar gets no new reminders', !in_array("due_{$sid}_{$newReq}_soon", $keys(), true));
    $pdo->exec("UPDATE scholars SET status = 'Active' WHERE id = $sid");

    // Staff digest.
    $grade = fn(string $term, float $avg) => $pdo->exec("INSERT INTO grade_records (scholar_id, term, average) VALUES ($sid, '$term', $avg)");
    $grade('T1', 92);
    $mails = [];
    runAlerts($fake);
    check('a safe grade sends staff nothing', $toAdmin() === []);

    $grade('T2', 86.5);
    $mails = [];
    $r = runAlerts($fake);
    check('a scholar close to the minimum is emailed to an active admin', count($toAdmin()) === 1 && str_contains($toAdmin()[0]['body'], 'ZZ-ALERT') && str_contains($toAdmin()[0]['body'], 'At Risk'));
    check('the email gives the average and the minimum', str_contains($toAdmin()[0]['body'], '86.50') && str_contains($toAdmin()[0]['body'], '85.00'));
    check('a switched-off admin is not emailed', array_filter($mails, fn($m) => $m['to'] === 'zz_alert_off@example.test') === []);
    check('the run counts the emails sent', $r['staff'] >= 1);
    check('the sent alert is recorded as already read', (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $aid AND LEFT(dedupe_key, 6) = 'staff_' AND read_at IS NOT NULL")->fetchColumn() === 1);
    check('the student is not sent a grade alert by the job', count(array_filter($keys(), fn($k) => str_starts_with($k, 'staff_') || str_starts_with($k, 'grade_'))) === 0);

    $mails = [];
    runAlerts($fake);
    check('the same problem is not emailed twice', $toAdmin() === []);

    $grade('T3', 80);
    $mails = [];
    runAlerts($fake);
    check('a worse grade (now Ineligible) is emailed again', count($toAdmin()) === 1 && str_contains($toAdmin()[0]['body'], 'Ineligible') && str_contains($toAdmin()[0]['subject'], '1 scholar'));

    $mailOk = false;
    $grade('T4', 70);
    $mails = [];
    $r = runAlerts($fake);
    check('a failed email is counted', $r['failed'] >= 1 && count($toAdmin()) === 1);
    $recorded = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $aid AND LEFT(dedupe_key, 6) = 'staff_'")->fetchColumn();
    check('a failed email is not recorded as sent', $recorded === 2);
    $mailOk = true;
    $mails = [];
    runAlerts($fake);
    check('the failed email is tried again on the next run', count($toAdmin()) === 1);

    $grade('T5', 95);
    $mails = [];
    runAlerts($fake);
    check('a recovered scholar is not emailed', $toAdmin() === []);
} finally {
    $pdo->rollBack();
}
check('nothing was kept', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE 'zz\\_alert%'")->fetchColumn() === 0);

echo "\n$total checks, $failed failed" . ($failed ? "  <-- fix these before going on\n" : "  -- all good\n");
exit($failed ? 1 : 0);