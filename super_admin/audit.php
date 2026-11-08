<?php
// Audit log: who did what and when (logins, role changes, grade and document decisions). Read only, Super Admin only.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['SuperAdmin']);
$pageTitle = 'Audit log';
$pageIntro = 'Newest first, 50 per page.';

const AUDIT_PAGE_SIZE = 50;

$actions = $pdo->query('SELECT DISTINCT action FROM security_audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$action = (string) ($_GET['action'] ?? '');
if (!in_array($action, $actions, true)) {
    $action = '';
}

$where = $action === '' ? '' : 'WHERE l.action = ?';
$params = $action === '' ? [] : [$action];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM security_audit_logs l $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / AUDIT_PAGE_SIZE));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

$stmt = $pdo->prepare(
    "SELECT l.action, l.details, l.ip_address, l.created_at, u.username
     FROM security_audit_logs l LEFT JOIN users u ON u.id = l.user_id
     $where
     ORDER BY l.id DESC
     LIMIT " . AUDIT_PAGE_SIZE . ' OFFSET ' . (($page - 1) * AUDIT_PAGE_SIZE)
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$link = fn(int $n) => '?' . http_build_query(array_filter(['action' => $action, 'page' => $n > 1 ? $n : null]));

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <form method="get" class="stack-form">
        <label for="action">Show only</label>
        <select name="action" id="action">
            <option value="">All events</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>"<?= $a === $action ? ' selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2><?= $total ?> event<?= $total === 1 ? '' : 's' ?></h2><p>Page <?= $page ?> of <?= $pages ?></p></div></div>
    <?php if (!$rows): ?>
        <p>Nothing has been logged yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>Who</th><th>Event</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td>
                        <td><?= $r['username'] === null ? '—' : e($r['username']) ?></td>
                        <td><?= e($r['action']) ?></td>
                        <td><?= e($r['details']) ?></td>
                        <td><?= e($r['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="panel-note">
            <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>" class="card-link">← Newer</a><?php endif; ?>
            <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>" class="card-link">Older →</a><?php endif; ?>
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>