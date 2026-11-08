<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Grades';
$pageIntro = 'Record a term grade; the scholar\'s status is recalculated at once.';

// Records (or corrects) one scholar's grade for a term, then recalculates their status.
// Returns an error message, or null on success.
function recordGrade(int $adminId, int $scholarId, string $term, string $average): ?string
{
    global $pdo;

    $term = trim($term);
    if ($term === '' || mb_strlen($term) > 30) {
        return 'The term must be 1 to 30 characters.';
    }
    if (!is_numeric($average) || (float) $average < 0 || (float) $average > 100) {
        return 'The grade must be a number from 0 to 100.';
    }

    $s = $pdo->prepare("SELECT status FROM scholars WHERE id = ?");
    $s->execute([$scholarId]);
    $status = $s->fetchColumn();
    if ($status === false) {
        return 'That scholar was not found.';
    }
    if (in_array($status, ['Pending', 'Ended'], true)) {
        return $status === 'Pending'
            ? 'This scholar has not been verified yet, so no grade can be recorded.'
            : 'This scholarship has ended, so no grade can be recorded.';
    }

    $pdo->prepare(
        'INSERT INTO grade_records (scholar_id, term, average, recorded_by) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE average = VALUES(average), recorded_by = VALUES(recorded_by)'
    )->execute([$scholarId, $term, $average, $adminId]);

    $newStatus = refreshScholarStatus($scholarId, $adminId);

    $pdo->prepare(
        "INSERT INTO notifications (user_id, message, dedupe_key)
         SELECT user_id, ?, CONCAT('grade_', id, '_', ?)
         FROM scholars WHERE id = ?
         ON DUPLICATE KEY UPDATE message = VALUES(message)"
    )->execute([
        "Your grade for $term has been recorded: " . number_format((float) $average, 2) . "."
        . match ($newStatus) {
            'AtRisk'     => ' Your scholarship is now At Risk because the grade is close to the minimum.',
            'Ineligible' => ' Your scholarship is now Ineligible because the grade is below the minimum.',
            default      => '',
        },
        $term,
        $scholarId,
    ]);

    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $error = recordGrade(
        $user['id'],
        (int) ($_POST['scholar_id'] ?? 0),
        (string) ($_POST['term'] ?? ''),
        (string) ($_POST['average'] ?? '')
    );
    $_SESSION['flash'] = $error
        ? ['error', $error]
        : ['success', 'Grade recorded and the scholar\'s status was recalculated.'];
    redirect('admin/grades.php');
}

// Only verified, still-active-or-watched scholars can have a grade entered.
$scholars = $pdo->query(
    "SELECT sc.id, sc.status, sc.student_number, u.full_name, p.name AS program_name,
            (SELECT g.term FROM grade_records g WHERE g.scholar_id = sc.id ORDER BY g.id DESC LIMIT 1) AS latest_term,
            (SELECT g.average FROM grade_records g WHERE g.scholar_id = sc.id ORDER BY g.id DESC LIMIT 1) AS latest_grade
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     JOIN scholarship_programs p ON p.id = sc.program_id
     WHERE sc.status IN ('Active', 'AtRisk', 'Ineligible')
     ORDER BY FIELD(sc.status, 'Ineligible', 'AtRisk', 'Active'), u.full_name"
)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header"><div><h2>Record a grade</h2><p>Entering the same term again corrects that term's grade</p></div></div>
    <?php if (!$scholars): ?>
        <p>No verified scholars yet. Add and verify a scholar first.</p>
    <?php else: ?>
        <form method="post" class="stack-form">
            <?= csrf_field() ?>

            <label for="scholar_id">Scholar</label>
            <select name="scholar_id" id="scholar_id" required>
                <option value="">Choose a scholar…</option>
                <?php foreach ($scholars as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['full_name']) ?> (<?= e($s['student_number']) ?>) — <?= e($s['program_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="term">Term</label>
            <input type="text" name="term" id="term" required maxlength="30" placeholder="e.g. 2026-2027 1st Sem">

            <label for="average">Grade average (0–100)</label>
            <input type="number" name="average" id="average" required min="0" max="100" step="0.01">

            <button type="submit" class="btn">Save grade</button>
        </form>
    <?php endif; ?>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>Verified scholars</h2><p>Latest recorded grade for each</p></div></div>
    <?php if (!$scholars): ?>
        <p>None yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Student</th><th>Student #</th><th>Program</th><th>Status</th><th>Latest term</th><th>Latest grade</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($scholars as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['student_number']) ?></td>
                        <td><?= e($row['program_name']) ?></td>
                        <td><span class="req-status <?= match ($row['status']) { 'Active' => 'is-done', 'Ineligible' => 'is-missing', default => 'is-pending' } ?>"><?= $row['status'] === 'AtRisk' ? 'At Risk' : e($row['status']) ?></span></td>
                        <td><?= $row['latest_term'] === null ? '—' : e($row['latest_term']) ?></td>
                        <td><?= $row['latest_grade'] === null ? '—' : e($row['latest_grade']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>