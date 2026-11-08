<?php
// Super Admin overview: accounts by role, failed sign-ins and the latest security events.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['SuperAdmin']);
$pageTitle = 'Dashboard';
$pageIntro = 'Accounts and security at a glance.';

$roles = array_fill_keys(['SuperAdmin', 'Admin', 'User'], 0);
foreach ($pdo->query('SELECT role, COUNT(*) AS total FROM users WHERE is_active = 1 GROUP BY role')->fetchAll() as $row) {
    $roles[$row['role']] = (int) $row['total'];
}
$switchedOff = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn();
$failedToday = (int) $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE identifier NOT LIKE 'register:%' AND attempted_at > NOW() - INTERVAL 1 DAY")->fetchColumn();
$denied = (int) $pdo->query("SELECT COUNT(*) FROM security_audit_logs WHERE action = 'access_denied' AND created_at > NOW() - INTERVAL 7 DAY")->fetchColumn();

$recent = $pdo->query(
    'SELECT l.action, l.details, l.created_at, u.username
     FROM security_audit_logs l LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 8'
)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="overview-grid">
    <article class="summary-card"><span class="card-label">Super Admins</span><h3><?= $roles['SuperAdmin'] ?></h3><p>Active accounts.</p></article>
    <article class="summary-card"><span class="card-label">Admins</span><h3><?= $roles['Admin'] ?></h3><p>Active accounts.</p></article>
    <article class="summary-card"><span class="card-label">Students</span><h3><?= $roles['User'] ?></h3><p>Active accounts.</p><a href="users.php" class="card-link">Manage users →</a></article>
    <article class="summary-card"><span class="card-label">Switched off</span><h3><?= $switchedOff ?></h3><p>Accounts that cannot sign in.</p></article>
    <article class="summary-card"><span class="card-label">Failed sign-ins</span><h3><span class="<?= $failedToday ? 'status-warning' : 'status-active' ?>"><?= $failedToday ?></span></h3><p>In the last 24 hours.</p></article>
    <article class="summary-card"><span class="card-label">Access denied</span><h3><span class="<?= $denied ? 'status-warning' : 'status-active' ?>"><?= $denied ?></span></h3><p>Blocked page visits in the last 7 days.</p></article>
</div>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>Latest events</h2><p>The newest 8</p></div><a href="audit.php">Open the audit log →</a></div>
    <?php if (!$recent): ?>
        <p>Nothing has been logged yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>Who</th><th>Event</th><th>Details</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= e(date('M j, g:i A', strtotime($r['created_at']))) ?></td>
                        <td><?= $r['username'] === null ? '—' : e($r['username']) ?></td>
                        <td><?= e($r['action']) ?></td>
                        <td><?= e($r['details']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>