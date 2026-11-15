<?php
// Profile: the student's own account details, and a form to change their password.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$user = requireRole(['User']);

// Changes the logged-in student's password. Returns an error message, or null on success.
// Wrong current passwords count toward the same lockout as logging in, so a stolen session cannot guess it.
function changePassword(array $user, string $current, string $new, string $again): ?string
{
    global $pdo;
    $key = 'password:' . $user['id']; // the colon keeps these rows out of the per-address login limit

    if (loginIsLocked($key)) {
        return 'Too many wrong passwords. Please try again later.';
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();
    if ($hash === false || !password_verify($current, $hash)) {
        recordFailedLogin($key);
        auditLog($user['id'], 'password_change_failed', 'wrong current password');
        return 'Your current password is not correct.';
    }
    if ($problem = passwordProblem($new)) {
        return $problem;
    }
    if ($new !== $again) {
        return 'The two new passwords do not match.';
    }
    if ($new === $current) {
        return 'Choose a password that is different from your current one.';
    }

    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    // An unused reset link must not be able to override the password just chosen.
    $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL')->execute([$user['id']]);
    clearFailedLogins($key);
    auditLog($user['id'], 'password_changed');
    sendMail($user['email'], 'Your Scholar Monitor password was changed', "The password for your Scholar Monitor account was just changed.\n\nIf this was not you, ask an administrator for help right away.");
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $error = changePassword($user, (string) ($_POST['current_password'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['password_again'] ?? ''));
    if ($error === null) {
        // The change ends every session opened with the old password; this one gets the new stamp so it stays logged in.
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $_SESSION['pw_stamp'] = passwordStamp((string) $stmt->fetchColumn());
        session_regenerate_id(true);
    }
    $_SESSION['flash'] = $error === null ? ['success', 'Your password was changed.'] : ['error', $error];
    redirect('user/profile.php'); // reload as a GET so a refresh does not submit again
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

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

                <?php if ($flash): ?>
                <p class="form-message <?= $flash[0] === 'success' ? 'success' : 'error' ?>" role="alert"><?= e($flash[1]) ?></p>
                <?php endif; ?>

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
                        <p class="panel-note">To change your name or email, please contact the scholarship administrator.</p>
                    </section>

                </div>

                <section class="content-panel panel-gap">
                    <div class="panel-header"><div><h2>Change Password</h2><p>10 to 72 characters. You stay logged in here; any other device is logged out.</p></div></div>
                    <form method="post" class="stack-form">
                        <?= csrf_field() ?>

                        <label for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password" maxlength="72" autocomplete="current-password" required>

                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" minlength="10" maxlength="72" autocomplete="new-password" required>

                        <label for="password_again">New password again</label>
                        <input type="password" id="password_again" name="password_again" minlength="10" maxlength="72" autocomplete="new-password" required>

                        <button type="submit" class="btn">Change password</button>
                    </form>
                </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>