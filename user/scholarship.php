<?php
// My Scholarship: the student's program, current standing and the rules that decide it.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['User']);
$pageTitle = 'My Scholarship';
require __DIR__ . '/../includes/user_header.php'; // also sets $scholar

$status  = 'None';
$program = null;
$gwa     = null;
if ($scholar) {
    $status = refreshScholarStatus((int) $scholar['id'], (int) $user['id']) ?? 'None';

    $stmt = $pdo->prepare(
        'SELECT p.name, p.description, p.min_grade, p.warning_margin, s.verified_at
         FROM scholars s JOIN scholarship_programs p ON p.id = s.program_id
         WHERE s.id = ?'
    );
    $stmt->execute([$scholar['id']]);
    $program = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT average FROM grade_records WHERE scholar_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$scholar['id']]);
    $gwa = $stmt->fetchColumn(); // false when no grade is recorded yet
}
$reqs = [];
if ($scholar) {
    $stmt = $pdo->prepare(
        'SELECT r.name, d.status
         FROM document_requirements r
         LEFT JOIN scholar_documents d ON d.requirement_id = r.id AND d.scholar_id = ?
         WHERE r.program_id = ? AND r.is_active = 1
         ORDER BY r.due_date IS NULL, r.due_date, r.name'
    );
    $stmt->execute([$scholar['id'], $scholar['program_id']]);
    $reqs = $stmt->fetchAll();
}
$year = (int) date('Y') - ((int) date('n') < 6 ? 1 : 0); // academic year runs June to May
$hasGrade = $gwa !== false && $gwa !== null;

?>

                <div class="page-header">
                    <p class="section-label">BALUQ: Student Portal</p>
                    <h1>My Scholarship</h1>
                    <p>Your scholarship status and renewal requirements.</p>
                </div>

                <div class="two-col">

                    <section class="content-panel">
                        <div class="panel-header"><div><h2>Scholarship Details</h2><p>Current academic year</p></div></div>
                        <?php if ($program): ?>
                        <div class="req-row"><span>Scholarship</span><span><?= e($program['name']) ?></span></div>
                        <div class="req-row"><span>Academic Year</span><span><?= $year ?>–<?= $year + 1 ?></span></div>
                        <div class="req-row"><span>Student Number</span><span><?= e($scholar['student_number']) ?></span></div>
                        <div class="req-row"><span>Status</span><span class="req-status <?= $status === 'Active' ? 'is-done' : ($status === 'Ineligible' ? 'is-missing' : 'is-pending') ?>"><?= e(STATUS_LABEL[$status]) ?></span></div>
                        <div class="req-row"><span>Minimum Average</span><span><?= number_format((float) $program['min_grade'], 2) ?></span></div>
                        <div class="req-row"><span>Latest Average</span><span><?= $hasGrade ? number_format((float) $gwa, 2) : '—' ?></span></div>
                        <div class="req-row"><span>Verified</span><span><?= $program['verified_at'] ? e(date('F j, Y', strtotime($program['verified_at']))) : 'Not yet' ?></span></div>
                        <p class="panel-note"><?= e(STATUS_TEXT[$status]) ?>
                            Your status becomes At Risk when your average is less than <?= number_format((float) $program['warning_margin'], 2) ?> points above the minimum, and Ineligible when it falls below it.</p>
                        <?php if ($program['description']): ?><p class="panel-note"><?= e($program['description']) ?></p><?php endif; ?>
                        <a href="academics-record.php" class="card-link">View grade history →</a>
                        <?php else: ?>
                        <p><?= e(STATUS_TEXT['None']) ?></p>
                        <?php endif; ?>
                    </section>

                    <section class="content-panel">
                        <div class="panel-header"><div><h2>Renewal Requirements</h2><p>Your required documents</p></div><a href="documents.php">My Documents</a></div>
                        <?php foreach ($reqs as $req):
                            [$label, $class] = match ($req['status']) {
                                'Approved'  => ['Approved', 'is-done'],
                                'Submitted' => ['Submitted', 'is-pending'],
                                'Rejected'  => ['Rejected', 'is-missing'],
                                default     => ['Missing', 'is-missing'],
                            };
                        ?>
                        <div class="req-row"><span><?= e($req['name']) ?></span><span class="req-status <?= $class ?>"><?= $label ?></span></div>
                        <?php endforeach; ?>
                        <?php if (!$reqs): ?><p>No documents are required right now.</p><?php endif; ?>
                    </section>

                </div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>