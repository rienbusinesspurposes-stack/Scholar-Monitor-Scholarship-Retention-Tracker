<?php
// Profile: the student's own account details, read only.
// shortcut: no editing yet, upgrade with a change-password form (passwordProblem() already holds the rule) when it is wanted.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['User']);
$pageTitle = 'Profile';
require __DIR__ . '/../includes/user_header.php'; // also sets $scholar

$program = null;
if ($scholar) {
    $stmt = $pdo->prepare('SELECT name FROM scholarship_programs WHERE id = ?');
    $stmt->execute([$scholar['program_id']]);
    $program = $stmt->fetchColumn() ?: null;
}
$parts    = preg_split('/\s+/', trim((string) $user['full_name']));
$initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
?>

                <div class="page-header">
                    <p class="section-label">BALUQ: Student Portal</p>
                    <h1>Profile</h1>
                    <p>Your account and scholarship details.</p>
                </div>

                <div class="two-col">

                    <section class="card profile-card">
                        <div class="profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
                        <h2 class="profile-name"><?= e($user['full_name']) ?></h2>
                        <p class="profile-meta">Student No.: <?= e($scholar['student_number'] ?? '—') ?></p>
                        <p class="profile-meta"><?= $program ? e($program) : 'No scholarship program yet' ?></p>
                        <?php if ($scholar): ?><span class="profile-badge"><?= e(STATUS_LABEL[$scholar['status']] ?? $scholar['status']) ?></span><?php endif; ?>
                    </section>

                    <section class="content-panel">
                        <div class="panel-header"><div><h2>Account</h2><p>Sign-in details</p></div></div>
                        <div class="req-row"><span>Full name</span><span><?= e($user['full_name']) ?></span></div>
                        <div class="req-row"><span>Username</span><span><?= e($user['username']) ?></span></div>
                        <div class="req-row"><span>Email</span><span><?= e($user['email']) ?></span></div>
                        <p class="panel-note">To change your details or password, please contact the scholarship administrator.</p>
                    </section>

                </div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>