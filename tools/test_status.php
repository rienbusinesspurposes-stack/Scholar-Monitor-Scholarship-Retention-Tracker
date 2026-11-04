<?php
// Self-check for includes/scholar_status.php. Run from Git Bash:  php tools/test_status.php
// Everything happens inside one database transaction that is rolled back at the end, so no data is kept.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'test_status';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/scholar_status.php';

$total = 0;
$failed = 0;
function check(string $label, bool $ok): void
{
    global $total, $failed;
    $total++;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
}

// The rule itself: program asks for 85 with a warning margin of 3.
foreach ([
    [84.99, 'Ineligible'], [0, 'Ineligible'], ['84.99', 'Ineligible'],
    [85, 'AtRisk'], ['85.00', 'AtRisk'], [87.99, 'AtRisk'],
    [88, 'Active'], [100, 'Active'],
] as [$grade, $want]) {
    check("grade " . var_export($grade, true) . " is $want", statusForGrade($grade, '85.00', '3.00') === $want);
}
check('no margin: exactly the minimum is Active', statusForGrade(85, 85, 0) === 'Active');
check('a grade exactly on the safe line is Active (decimal rounding cannot flip it)', statusForGrade('0.30', '0.10', '0.20') === 'Active');

// Saving statuses.
$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO users (username, email, password_hash, full_name) VALUES ('zz_status', 'zz_status@example.test', 'x', 'Status Test')");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO scholarship_programs (name, min_grade, warning_margin) VALUES ('zz status program', 85, 3)");
    $pid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO scholars (user_id, program_id, student_number, status) VALUES ($uid, $pid, 'ZZ-STATUS', 'Active')");
    $sid = (int) $pdo->lastInsertId();
    $grade = fn(string $term, float $avg) => $pdo->exec("INSERT INTO grade_records (scholar_id, term, average) VALUES ($sid, '$term', $avg)");
    $state = fn() => $pdo->query("SELECT status FROM scholars WHERE id = $sid")->fetchColumn();
    $logs = fn() => (int) $pdo->query("SELECT COUNT(*) FROM security_audit_logs WHERE action = 'scholar_status_changed' AND details LIKE 'scholar $sid:%'")->fetchColumn();

    check('a scholar that does not exist returns null', refreshScholarStatus(0) === null);
    check('no grade yet: status is left alone', refreshScholarStatus($sid) === 'Active' && $state() === 'Active');
    $grade('T1', 86.5);
    check('86.5 becomes AtRisk and is saved', refreshScholarStatus($sid) === 'AtRisk' && $state() === 'AtRisk');
    check('the change was written to the audit log', $logs() === 1);
    check('checking again changes nothing and logs nothing', refreshScholarStatus($sid) === 'AtRisk' && $logs() === 1);
    $grade('T2', 80);
    check('a lower new grade becomes Ineligible', refreshScholarStatus($sid) === 'Ineligible' && $state() === 'Ineligible');
    $grade('T3', 92);
    check('a recovered grade becomes Active again', refreshScholarStatus($sid) === 'Active' && $state() === 'Active');
    $pdo->exec("UPDATE scholars SET status = 'Pending' WHERE id = $sid");
    $grade('T4', 50);
    check('Pending is never changed by a grade', refreshScholarStatus($sid) === 'Pending' && $state() === 'Pending');
    $pdo->exec("UPDATE scholars SET status = 'Ended' WHERE id = $sid");
    check('Ended is never changed by a grade', refreshScholarStatus($sid) === 'Ended' && $state() === 'Ended');
} finally {
    $pdo->rollBack();
}
check('nothing was kept', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'zz_status'")->fetchColumn() === 0);

echo "\n$total checks, $failed failed" . ($failed ? "  <-- fix these before going on\n" : "  -- all good\n");
exit($failed ? 1 : 0);