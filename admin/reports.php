<?php
// Reports: CSV downloads of scholars, grades and documents for Admin and Super Admin. Every download is written to the audit log.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/reports.php';

$user = requireRole(['Admin', 'SuperAdmin']);

$type = (string) ($_GET['type'] ?? '');
if (isset(REPORTS[$type])) { // reading only, so a plain link works and no CSRF token is needed
    [$header, $rows] = reportRows($type);
    auditLog($user['id'], 'report_exported', "$type, " . count($rows) . ' rows');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="scholar-monitor-' . $type . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: private, no-store');
    writeCsv(fopen('php://output', 'w'), $header, $rows);
    exit;
}

$pageTitle = 'Reports';
$pageIntro = 'Download the records as CSV files that open in Excel. They contain student details, so keep them private. Each download is logged.';
require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header"><div><h2>CSV downloads</h2><p>Up to date at the moment you click</p></div></div>
    <?php foreach (REPORTS as $key => [$name, $about]): ?>
    <div class="req-row">
        <span><strong><?= e($name) ?></strong><br><?= e($about) ?></span>
        <a href="reports.php?type=<?= e($key) ?>" class="btn btn-small">Download CSV</a>
    </div>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>