<?php
// Notifications: the student's alerts, newest first, with one button to mark them all as read.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['User']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $pdo->prepare('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL')
        ->execute([$user['id']]);
    redirect('user/notifications.php'); // reload as a GET so the sidebar count updates
}

$pageTitle = 'Notifications';
require __DIR__ . '/../includes/user_header.php'; // also sets $unread

// shortcut: shows the latest 50 only, upgrade with paging if students collect more
$stmt = $pdo->prepare('SELECT message, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$stmt->execute([$user['id']]);
$alerts = $stmt->fetchAll();
?>

            <section class="dashboard-content">

                <section class="content-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Your Notifications</h2>
                            <p><?= $unread > 0 ? $unread . ' unread' : 'You are all caught up' ?></p>
                        </div>
                        <?php if ($unread > 0): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="reminder-link mark-read-button">Mark all as read</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($alerts as $alert): ?>
                    <article class="alert-item">
                        <div class="alert-icon info-icon" aria-hidden="true">i</div>
                        <div class="alert-details">
                            <h3><?= e($alert['message']) ?></h3>
                            <span><?= e(date('F j, Y, g:i A', strtotime($alert['created_at']))) ?></span>
                        </div>
                        <?php if ($alert['read_at'] === null): ?><span class="alert-status new">New</span><?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                    <?php if (!$alerts): ?><p>No notifications yet.</p><?php endif; ?>

                </section>

            </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>