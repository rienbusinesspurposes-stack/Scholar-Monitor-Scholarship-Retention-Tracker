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
    'Active'     => ['On track', 'approved'],
    'AtRisk'     => ['Close to the minimum', 'pending'],
    'Ineligible' => ['Below the minimum', 'new'],
];
?>

            <section class="dashboard-content">

                <section class="content-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Grade History</h2>
                            <p><?= $program ? 'Minimum average to keep your scholarship: ' . number_format((float) $program['min_grade'], 2) : 'Your recorded grade averages' ?></p>
                        </div>
                    </div>

                    <?php foreach ($grades as $grade):
                        [$label, $class] = $badge[statusForGrade($grade['average'], $program['min_grade'], $program['warning_margin'])];
                    ?>
                    <article class="alert-item">
                        <div class="alert-icon info-icon" aria-hidden="true">i</div>
                        <div class="alert-details">
                            <h3><?= e($grade['term']) ?></h3>
                            <p>Average: <?= number_format((float) $grade['average'], 2) ?></p>
                            <span>Recorded <?= e(date('F j, Y', strtotime($grade['created_at']))) ?></span>
                        </div>
                        <span class="alert-status <?= $class ?>"><?= $label ?></span>
                    </article>
                    <?php endforeach; ?>
                    <?php if (!$grades): ?><p><?= $scholar ? 'No grades have been recorded yet.' : 'No scholarship record is linked to your account yet.' ?></p><?php endif; ?>

                </section>

            </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>