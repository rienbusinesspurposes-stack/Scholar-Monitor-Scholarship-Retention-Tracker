<?php
// The alert job's logic, run once a day by tools/send_alerts.php.
//  - Students get a reminder for each required document that is missing (never uploaded, or rejected) when its due date
//    is within the alert_due_soon_days setting, and once more when it is overdue.
//  - Staff (active Admins and Super Admins) get one email listing scholars who are At Risk or Ineligible on their latest grade.
// Every alert has a key that names the problem, so the same problem is reported once and a changed one is reported again.
// Load config/database.php, includes/security.php, includes/scholar_status.php and includes/mailer.php first.

// Saves one alert. Returns true only when it is new. $read starts it as read: staff have no inbox page, so for them the
// row is only the record that the email was sent.
// INSERT IGNORE: the only error expected here is the duplicate key that means "already reported".
function storeAlert(int $userId, string $key, string $message, bool $read = false): bool
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO notifications (user_id, message, dedupe_key, read_at)
         VALUES (?, ?, ?, ' . ($read ? 'CURRENT_TIMESTAMP' : 'NULL') . ')'
    );
    $stmt->execute([$userId, mb_substr($message, 0, 500), $key]); // the message column holds 500 characters
    return $stmt->rowCount() === 1;
}

// Runs the whole job. Returns ['students' => new reminders, 'staff' => emails sent, 'failed' => emails that failed].
// $send is the mail function, so a test can pass a fake one and send nothing.
function runAlerts(?callable $send = null): array
{
    global $pdo;
    $send ??= 'sendMail';
    $counts = ['students' => 0, 'staff' => 0, 'failed' => 0];

    // Statuses first, in case a program's minimum grade changed since the last grade entry.
    $ids = $pdo->query("SELECT id FROM scholars WHERE status IN ('Active','AtRisk','Ineligible')")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        refreshScholarStatus((int) $id);
    }

    // Student reminders. Only live scholars (not Ended) with a due date; a retired requirement is skipped.
    $missing = $pdo->query(
        'SELECT s.user_id, s.id AS scholar_id, r.id AS requirement_id, r.name, r.due_date,
                r.due_date < CURDATE() AS overdue, d.status AS doc_status
         FROM scholars s
         JOIN users u ON u.id = s.user_id AND u.is_active = 1
         JOIN document_requirements r ON r.program_id = s.program_id AND r.is_active = 1
              AND r.due_date IS NOT NULL AND r.due_date <= CURDATE() + INTERVAL ' . setting('alert_due_soon_days') . " DAY
         LEFT JOIN scholar_documents d ON d.scholar_id = s.id AND d.requirement_id = r.id
         WHERE s.status <> 'Ended' AND (d.id IS NULL OR d.status = 'Rejected')"
    )->fetchAll();
    foreach ($missing as $m) {
        $late = (int) $m['overdue'] === 1;
        $date = date('F j, Y', strtotime($m['due_date']));
        $what = $m['doc_status'] === 'Rejected' ? "Your \"{$m['name']}\" was rejected and must be uploaded again" : "\"{$m['name']}\" has not been submitted";
        $text = $late ? "$what. It was due on $date." : "Reminder: $what. It is due on $date.";
        // shortcut: a reminder stays unread after the student uploads, upgrade by marking it read when the document arrives.
        $counts['students'] += storeAlert((int) $m['user_id'], "due_{$m['scholar_id']}_{$m['requirement_id']}_" . ($late ? 'late' : 'soon'), $text);
    }

    // Staff digest. The key holds the latest grade record and the status, so a scholar is mentioned again only when that changes.
    $risky = $pdo->query(
        "SELECT s.id, s.status, s.student_number, u.full_name, p.name AS program, p.min_grade, g.id AS grade_id, g.average
         FROM scholars s
         JOIN users u ON u.id = s.user_id AND u.is_active = 1
         JOIN scholarship_programs p ON p.id = s.program_id
         JOIN grade_records g ON g.id = (SELECT MAX(id) FROM grade_records WHERE scholar_id = s.id)
         WHERE s.status IN ('AtRisk','Ineligible')
         ORDER BY s.status DESC, u.full_name"
    )->fetchAll();
    $lines = [];
    foreach ($risky as $r) {
        $lines["staff_{$r['id']}_{$r['grade_id']}_" . strtolower($r['status'])] =
            "{$r['full_name']} ({$r['student_number']}), {$r['program']}: " . STATUS_LABEL[$r['status']]
            . ', average ' . number_format((float) $r['average'], 2) . ' against a minimum of ' . number_format((float) $r['min_grade'], 2);
    }

    $link = appUrl() === '' ? '' : "\n\nReview them here: " . appUrl() . '/admin/scholars.php';
    $staff = $pdo->query("SELECT id, email, full_name FROM users WHERE role IN ('Admin','SuperAdmin') AND is_active = 1")->fetchAll();
    $sent = $pdo->prepare("SELECT dedupe_key FROM notifications WHERE user_id = ? AND LEFT(dedupe_key, 6) = 'staff_'");
    foreach ($staff as $member) {
        $sent->execute([$member['id']]);
        $new = array_diff_key($lines, array_flip($sent->fetchAll(PDO::FETCH_COLUMN)));
        if (!$new) {
            continue;
        }
        $body = "Hello {$member['full_name']},\n\nThese scholars are close to losing eligibility or are already below the minimum grade:\n\n- "
            . implode("\n- ", $new) . $link;
        // The records are saved only after the email went out, so a failed email is tried again on the next run.
        if ($send($member['email'], 'Scholarship alert: ' . count($new) . ' scholar(s) need attention', $body)) {
            foreach ($new as $key => $line) {
                storeAlert((int) $member['id'], $key, "Emailed to you: $line", true);
            }
            $counts['staff']++;
        } else {
            $counts['failed']++;
        }
    }

    auditLog(null, 'alerts_run', "student reminders {$counts['students']}, staff emails {$counts['staff']}, failed {$counts['failed']}");
    return $counts;
}