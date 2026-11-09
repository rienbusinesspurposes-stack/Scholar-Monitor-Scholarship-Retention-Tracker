<?php
// Self-check for includes/reports.php. Run from Git Bash:  php tools/test_reports.php
// Everything happens inside one database transaction that is rolled back at the end, so no data is kept.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'test_reports';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/functions.php';
require $root . '/includes/reports.php';

$total = 0;
$failed = 0;
function check(string $label, bool $ok): void
{
    global $total, $failed;
    $total++;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
}

// Cells that Excel would run as formulas.
check('a formula start is made plain text', csvSafe('=1+1') === "'=1+1" && csvSafe('+1') === "'+1" && csvSafe('-1') === "'-1" && csvSafe('@a') === "'@a");
check('a tab or return at the start is made plain text', csvSafe("\tx") === "'\tx" && csvSafe("\rx") === "'\rx");
check('normal text, numbers, dates, empty and null are left alone', csvSafe('Ana Cruz') === 'Ana Cruz' && csvSafe('86.50') === '86.50' && csvSafe('2026-11-09') === '2026-11-09' && csvSafe('') === '' && csvSafe(null) === '');
check('an unknown report name returns nothing', reportRows('nope') === null && reportRows('') === null);

$pdo->beginTransaction();
try {
    $evil = '=HYPERLINK("http://x.test","click")';
    $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES ('zz_rep_a', 'zz_rep_a@example.test', 'x', ?)")->execute([$evil]);
    $ua = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES ('zz_rep_b', 'zz_rep_b@example.test', 'x', ?)")->execute(['Smith, "Ann" \\ Jr.']);
    $ub = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO scholarship_programs (name, min_grade, warning_margin) VALUES ('zz report program', 85, 3)");
    $pid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO scholars (user_id, program_id, student_number, status, verified_at) VALUES ($ua, $pid, 'ZZ-REP-A', 'AtRisk', NOW())");
    $sa = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO scholars (user_id, program_id, student_number, status, verified_at, ended_at, ended_reason) VALUES ($ub, $pid, 'ZZ-REP-B', 'Ended', NOW(), NOW(), 'Withdrew')");
    $pdo->exec("INSERT INTO grade_records (scholar_id, term, average) VALUES ($sa, 'T1', 90), ($sa, 'T2', 86)");
    $pdo->exec("INSERT INTO document_requirements (program_id, name, due_date, is_active) VALUES ($pid, 'Alpha form', NULL, 1), ($pid, 'Retired form', NULL, 0), ($pid, 'Charlie form', NULL, 1)");
    $alpha = (int) $pdo->query("SELECT id FROM document_requirements WHERE program_id = $pid AND name = 'Alpha form'")->fetchColumn();
    $pdo->exec("INSERT INTO scholar_documents (scholar_id, requirement_id, stored_name, original_name, status) VALUES ($sa, $alpha, 'zzrep', 'a.pdf', 'Approved')");
    $mine = fn(array $rows, string $number) => array_values(array_filter($rows, fn($r) => $r[0] === $number));

    [$header, $rows] = reportRows('scholars');
    $a = $mine($rows, 'ZZ-REP-A')[0] ?? [];
    $b = $mine($rows, 'ZZ-REP-B')[0] ?? [];
    check('scholars: one row per scholar, ended ones included', $a && $b && count($header) === count($a));
    check('scholars: status uses the same words as the pages', ($a[4] ?? '') === 'At Risk' && ($b[4] ?? '') === 'Ended');
    check('scholars: latest grade is the last one entered, with the minimum beside it', ($a[5] ?? '') === '86.00' && ($a[6] ?? '') === '85.00');
    check('scholars: a scholar with no grade has an empty latest grade', array_key_exists(5, $b) && $b[5] === null && ($b[9] ?? '') === 'Withdrew');

    [, $rows] = reportRows('grades');
    $g = $mine($rows, 'ZZ-REP-A');
    check('grades: one row per term, in the order entered', count($g) === 2 && $g[0][3] === 'T1' && $g[1][3] === 'T2' && $g[1][4] === '86.00');
    check('grades: a scholar without grades has no rows', $mine($rows, 'ZZ-REP-B') === []);

    [, $rows] = reportRows('documents');
    $d = $mine($rows, 'ZZ-REP-A');
    $byName = array_column($d, 5, 3);
    check('documents: approved and missing requirements are both listed', ($byName['Alpha form'] ?? '') === 'Approved' && ($byName['Charlie form'] ?? '') === 'Missing');
    check('documents: a retired requirement is not listed', !isset($byName['Retired form']) && count($d) === 2);
    check('documents: an ended scholar has no rows', $mine($rows, 'ZZ-REP-B') === []);

    // The file itself.
    [$header, $rows] = reportRows('scholars');
    $out = fopen('php://memory', 'w+');
    writeCsv($out, $header, $rows);
    rewind($out);
    $text = stream_get_contents($out);
    check('the file starts with the byte order mark so Excel reads accents', str_starts_with($text, "\xEF\xBB\xBF"));
    rewind($out);
    fread($out, 3);
    $parsed = [];
    while (($line = fgetcsv($out, 0, ',', '"', '')) !== false) {
        $parsed[] = $line;
    }
    $ra = array_values(array_filter($parsed, fn($r) => ($r[0] ?? '') === 'ZZ-REP-A'))[0] ?? [];
    $rb = array_values(array_filter($parsed, fn($r) => ($r[0] ?? '') === 'ZZ-REP-B'))[0] ?? [];
    check('the header row comes first', ($parsed[0][0] ?? '') === 'Student number' && count($parsed) === count($rows) + 1);
    check('a formula in a name is saved as plain text', ($ra[1] ?? '') === "'" . $evil);
    check('commas, quotes and backslashes in a name survive', ($rb[1] ?? '') === 'Smith, "Ann" \\ Jr.');
    fclose($out);
} finally {
    $pdo->rollBack();
}
check('nothing was kept', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE 'zz\\_rep%'")->fetchColumn() === 0);

echo "\n$total checks, $failed failed" . ($failed ? "  <-- fix these before going on\n" : "  -- all good\n");
exit($failed ? 1 : 0);