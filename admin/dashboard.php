<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Dashboard';
$pageIntro = 'Scholar statuses and what needs your attention.';

// Status counts for the summary cards.
$counts = array_fill_keys(['Pending', 'Active', 'AtRisk', 'Ineligible', 'Ended'], 0);
$stmt = $pdo->query('SELECT status, COUNT(*) AS total FROM scholars GROUP BY status');
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}

$awaitingReview = (int) $pdo->query(
    "SELECT COUNT(*) FROM scholar_documents WHERE status = 'Submitted'"
)->fetchColumn();

// Needs Attention: Pending verification first, then Ineligible, then AtRisk, 8 at a time.
$needsAttention = $pdo->query(
    "SELECT sc.id, sc.status, sc.student_number, u.full_name, p.name AS program_name,
            (SELECT g.average FROM grade_records g WHERE g.scholar_id = sc.id ORDER BY g.id DESC LIMIT 1) AS latest_grade
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     JOIN scholarship_programs p ON p.id = sc.program_id
     WHERE sc.status IN ('Pending', 'Ineligible', 'AtRisk')
     ORDER BY FIELD(sc.status, 'Pending', 'Ineligible', 'AtRisk'), sc.updated_at DESC
     LIMIT 8"
)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="overview-grid">
    <article class="summary-card"><span class="card-label">Pending verification</span><h3><span class="status-warning"><?= $counts['Pending'] ?></span></h3><p>Waiting for staff to verify.</p></article>
    <article class="summary-card"><span class="card-label">Active</span><h3><span class="status-active"><?= $counts['Active'] ?></span></h3><p>Keeping their minimum average.</p></article>
    <article class="summary-card"><span class="card-label">At risk</span><h3><span class="status-warning"><?= $counts['AtRisk'] ?></span></h3><p>Close to the minimum.</p></article>
    <article class="summary-card"><span class="card-label">Ineligible</span><h3><span class="status-danger"><?= $counts['Ineligible'] ?></span></h3><p>Below the minimum average.</p></article>
    <article class="summary-card"><span class="card-label">Ended</span><h3><?= $counts['Ended'] ?></h3><p>Scholarship has ended.</p></article>
    <article class="summary-card"><span class="card-label">Documents awaiting review</span><h3><?= $awaitingReview ?></h3><p>Submitted by students.</p></article>
</div>

<section class="content-panel panel-gap">
    <div class="panel-header">
        <div><h2>Needs attention</h2><p>Pending first, then Ineligible, then At Risk</p></div>
        <a href="scholars.php">Go to Scholars →</a>
    </div>
    <?php if (!$needsAttention): ?>
        <p>Nothing needs attention right now.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Student</th><th>Student #</th><th>Program</th><th>Status</th><th>Latest grade</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($needsAttention as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['student_number']) ?></td>
                        <td><?= e($row['program_name']) ?></td>
                        <td><span class="req-status <?= $row['status'] === 'Ineligible' ? 'is-missing' : 'is-pending' ?>"><?= $row['status'] === 'AtRisk' ? 'At Risk' : e($row['status']) ?></span></td>
                        <td><?= $row['latest_grade'] === null ? '—' : e($row['latest_grade']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>