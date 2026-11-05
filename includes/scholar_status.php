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
                (SELECT g.average FROM grade_records g WHERE g.scholar_id = s.id ORDER BY g.created_at DESC, g.id DESC LIMIT 1) AS latest
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