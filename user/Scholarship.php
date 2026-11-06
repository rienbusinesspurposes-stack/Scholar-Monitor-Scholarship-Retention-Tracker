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
$hasGrade = $gwa !== false && $gwa !== null;

$statusLabel = ['None' => 'No Record', 'Pending' => 'Pending', 'Active' => 'Active', 'AtRisk' => 'At Risk', 'Ineligible' => 'Ineligible', 'Ended' => 'Ended'];
$statusText  = [
    'None'       => 'No scholarship record is linked to your account yet.',
    'Pending'    => 'Waiting for staff to verify your scholarship.',
    'Active'     => 'Your scholarship is currently active.',
    'AtRisk'     => 'Your grade is close to the minimum. Keep it up.',
    'Ineligible' => 'Your grade is below the minimum for your scholarship.',
    'Ended'      => 'Your scholarship has ended.',
];
?>

            <section class="dashboard-content">

                <section class="summary-section">

                    <h2 class="section-title"><?= $program ? e($program['name']) : 'Scholarship' ?></h2>

                    <div class="summary-grid">

                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Scholarship Status</span>
                                <span class="card-icon" aria-hidden="true">🎖</span>
                            </div>
                            <h2><span class="<?= $status === 'Active' ? 'status-active' : 'status-warning' ?>"><?= e($statusLabel[$status]) ?></span></h2>
                            <p><?= e($statusText[$status]) ?></p>
                        </article>

                        <?php if ($program): ?>
                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Minimum Average</span>
                                <span class="card-icon" aria-hidden="true">🕮</span>
                            </div>
                            <h3><?= number_format((float) $program['min_grade'], 2) ?></h3>
                            <p>The average you must keep.</p>
                        </article>

                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Your Latest Average</span>
                                <span class="card-icon" aria-hidden="true">🗒</span>
                            </div>
                            <h3><?= $hasGrade ? number_format((float) $gwa, 2) : '—' ?></h3>
                            <p><?= $hasGrade
                                ? number_format(abs((float) $gwa - (float) $program['min_grade']), 2) . ((float) $gwa >= (float) $program['min_grade'] ? ' points above the minimum.' : ' points below the minimum.')
                                : 'No grade recorded yet.' ?></p>
                            <a href="academics-record.php" class="card-link">View grade history →</a>
                        </article>
                        <?php endif; ?>

                    </div>

                </section>

                <?php if ($program): ?>
                <section class="content-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Scholarship Details</h2>
                            <p>How your scholarship is checked</p>
                        </div>
                        <a href="documents.php">My Documents</a>
                    </div>

                    <?php if ($program['description']): ?>
                    <article class="alert-item">
                        <div class="alert-icon info-icon" aria-hidden="true">i</div>
                        <div class="alert-details">
                            <h3>About this scholarship</h3>
                            <p><?= e($program['description']) ?></p>
                        </div>
                    </article>
                    <?php endif; ?>

                    <article class="alert-item">
                        <div class="alert-icon info-icon" aria-hidden="true">i</div>
                        <div class="alert-details">
                            <h3>Student number</h3>
                            <p><?= e($scholar['student_number']) ?></p>
                        </div>
                    </article>

                    <article class="alert-item">
                        <div class="alert-icon info-icon" aria-hidden="true">i</div>
                        <div class="alert-details">
                            <h3>Verification</h3>
                            <p><?= $program['verified_at'] ? 'Verified on ' . e(date('F j, Y', strtotime($program['verified_at']))) : 'Not verified yet.' ?></p>
                        </div>
                    </article>

                    <article class="alert-item">
                        <div class="alert-icon warning-icon" aria-hidden="true">!</div>
                        <div class="alert-details">
                            <h3>When you are at risk</h3>
                            <p>Your status becomes At Risk when your average is less than <?= number_format((float) $program['warning_margin'], 2) ?> points above the minimum, and Ineligible when it falls below the minimum.</p>
                        </div>
                    </article>

                </section>
                <?php endif; ?>

            </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>