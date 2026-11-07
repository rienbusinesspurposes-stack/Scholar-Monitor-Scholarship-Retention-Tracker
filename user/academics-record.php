<?php
// Academic Records: the student's grade averages, newest first, each checked against the program's minimum.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['User']);
$pageTitle = 'Academic Records';
require __DIR__ . '/../includes/user_header.php'; // also sets $scholar

$program = null;
$grades  = [];
if ($scholar) {
    $stmt = $pdo->prepare('SELECT min_grade, warning_margin FROM scholarship_programs WHERE id = ?');
    $stmt->execute([$scholar['program_id']]);
    $program = $stmt->fetch();

    // Newest first by entry order, the same "latest" that scholar_status.php uses.
    $stmt = $pdo->prepare('SELECT term, average, created_at FROM grade_records WHERE scholar_id = ? ORDER BY id DESC');
    $stmt->execute([$scholar['id']]);
    $grades = $stmt->fetchAll();
}
// result of statusForGrade() => [badge text, badge class]
$badge = [
    'Active'     => ['On track', 'is-done'],
    'AtRisk'     => ['Close to the minimum', 'is-pending'],
    'Ineligible' => ['Below the minimum', 'is-missing'],
];
?>

                <div class="page-header">
                    <p class="section-label">BALUQ: Student Portal</p>
                    <h1>Academics Record</h1>
                    <p><?= $program ? 'Minimum average to keep your scholarship: ' . number_format((float) $program['min_grade'], 2) . '.' : 'Your recorded grade averages.' ?></p>
                </div>

                <section class="content-panel">
                    <?php if ($grades): ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>Term</th><th>Average</th><th>Remarks</th><th>Recorded</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $grade):
                                    [$label, $class] = $badge[statusForGrade($grade['average'], $program['min_grade'], $program['warning_margin'])];
                                ?>
                                <tr>
                                    <td><?= e($grade['term']) ?></td>
                                    <td><?= number_format((float) $grade['average'], 2) ?></td>
                                    <td><span class="req-status <?= $class ?>"><?= $label ?></span></td>
                                    <td><?= e(date('F j, Y', strtotime($grade['created_at']))) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p><?= $scholar ? 'No grades have been recorded yet.' : 'No scholarship record is linked to your account yet.' ?></p>
                    <?php endif; ?>
                </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>