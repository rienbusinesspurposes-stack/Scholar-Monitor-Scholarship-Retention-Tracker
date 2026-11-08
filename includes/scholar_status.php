<?php
// The one rule that decides whether a scholar is safe, near losing the scholarship, or has lost eligibility.
// Everything that shows or changes a status (dashboards, grade entry, the alert job) must call this file.

// Grades are compared in hundredths, so 85.00 against 85 is never wrong by a rounding error.
function statusForGrade(string|float $grade, string|float $minGrade, string|float $warningMargin): string
{
    $grade = (int) round((float) $grade * 100);
    $min = (int) round((float) $minGrade * 100);
    $margin = (int) round((float) $warningMargin * 100);

    if ($grade < $min) {
        return 'Ineligible';
    }
    return $grade < $min + $margin ? 'AtRisk' : 'Active';
}

// Recalculates one scholar from the latest grade they were given and saves the new status if it changed.
// Returns the status after the check, or null when the scholar does not exist.
// Pending (not yet verified) and Ended scholars, and scholars with no grade yet, are left as they are.
// shortcut: "latest" means the most recently entered grade record, not the latest term name; upgrade by adding a term date if terms are ever entered out of order.
function refreshScholarStatus(int $scholarId, ?int $actorId = null): ?string
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT s.status, p.min_grade, p.warning_margin,
                (SELECT g.average FROM grade_records g WHERE g.scholar_id = s.id ORDER BY g.id DESC LIMIT 1) AS latest
         FROM scholars s JOIN scholarship_programs p ON p.id = s.program_id
         WHERE s.id = ?'
    );
    $stmt->execute([$scholarId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (in_array($row['status'], ['Pending', 'Ended'], true) || $row['latest'] === null) {
        return $row['status'];
    }

    $new = statusForGrade($row['latest'], $row['min_grade'], $row['warning_margin']);
    if ($new !== $row['status']) {
        $pdo->prepare('UPDATE scholars SET status = ? WHERE id = ?')->execute([$new, $scholarId]);
        auditLog($actorId, 'scholar_status_changed', "scholar $scholarId: {$row['status']} to $new");
    }
    return $new;
}

// Tells the scholar something about their own scholarship. One alert per kind and scholar: a repeat replaces the old
// one and makes it unread again. $kind is a short word such as 'ended'.
function notifyScholar(int $scholarId, string $kind, string $message): void
{
    global $pdo;
    $pdo->prepare(
        "INSERT INTO notifications (user_id, message, dedupe_key)
         SELECT user_id, ?, CONCAT(?, '_', id) FROM scholars WHERE id = ?
         ON DUPLICATE KEY UPDATE message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP"
    )->execute([mb_substr($message, 0, 500), $kind, $scholarId]); // the notifications column holds 500 characters
}

// Ends a scholarship, records when and why, and tells the student. Returns an error message, or null on success.
function endScholarship(int $scholarId, string $reason, ?int $actorId = null): ?string
{
    global $pdo;
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason) > 300) {
        return 'Give a reason of 1 to 300 characters.';
    }
    $stmt = $pdo->prepare(
        "UPDATE scholars SET status = 'Ended', ended_at = CURRENT_TIMESTAMP, ended_reason = ?
         WHERE id = ? AND status <> 'Ended'"
    );
    $stmt->execute([$reason, $scholarId]);
    if ($stmt->rowCount() !== 1) {
        return 'That scholar was not found or the scholarship has already ended.';
    }
    notifyScholar($scholarId, 'ended', 'Your scholarship has ended: ' . $reason);
    auditLog($actorId, 'scholar_ended', "scholar $scholarId: $reason");
    return null;
}

// Undoes an ending (a mistake, or an appeal that was granted). A scholar who was never verified goes back to Pending,
// anyone else to Active and then straight through the grade rule. Returns an error message, or null on success.
function reinstateScholarship(int $scholarId, ?int $actorId = null): ?string
{
    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE scholars
         SET status = IF(verified_at IS NULL, 'Pending', 'Active'), ended_at = NULL, ended_reason = NULL
         WHERE id = ? AND status = 'Ended'"
    );
    $stmt->execute([$scholarId]);
    if ($stmt->rowCount() !== 1) {
        return 'That scholar was not found or the scholarship has not ended.';
    }
    $now = refreshScholarStatus($scholarId, $actorId);
    notifyScholar($scholarId, 'reinstated', 'Your scholarship has been reinstated.');
    auditLog($actorId, 'scholar_reinstated', "scholar $scholarId, now $now");
    return null;
}