<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Dashboard';

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

<section class="summary-grid">
    <div class="summary-card status-pending">
        <span class="summary-label">Pending verification</span>
        <span class="summary-value"><?= $counts['Pending'] ?></span>
    </div>
    <div class="summary-card status-active">
        <span class="summary-label">Active</span>
        <span class="summary-value"><?= $counts['Active'] ?></span>
    </div>
    <div class="summary-card status-warning">
        <span class="summary-label">At risk</span>
        <span class="summary-value"><?= $counts['AtRisk'] ?></span>
    </div>
    <div class="summary-card status-danger">
        <span class="summary-label">Ineligible</span>
        <span class="summary-value"><?= $counts['Ineligible'] ?></span>
    </div>
    <div class="summary-card">
        <span class="summary-label">Ended</span>
        <span class="summary-value"><?= $counts['Ended'] ?></span>
    </div>
    <div class="summary-card">
        <span class="summary-label">Documents awaiting review</span>
        <span class="summary-value"><?= $awaitingReview ?></span>
    </div>
</section>

<section class="panel">
    <h2>Needs attention</h2>
    <?php if (!$needsAttention): ?>
        <p>Nothing needs attention right now.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student #</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Latest grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($needsAttention as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['student_number']) ?></td>
                        <td><?= e($row['program_name']) ?></td>
                        <td><?= e($row['status']) ?></td>
                        <td><?= $row['latest_grade'] === null ? '—' : e($row['latest_grade']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a href="scholars.php" class="card-link">Go to Scholars →</a></p>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>