<?php
// Sets a new password from the link in the reset email. Each link works once.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.user_id, u.username, u.email
         FROM password_reset_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.is_active = 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch() ?: null;
}

$errors = [];
$done = false;
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $password = (string) ($_POST['password'] ?? '');
    if ($problem = passwordProblem($password)) {
        $errors[] = $problem;
    } elseif ($password !== (string) ($_POST['password_again'] ?? '')) {
        $errors[] = 'The two passwords do not match.';
    } else {
        $id = (int) $row['user_id'];
        $pdo->beginTransaction();
        $use = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $use->execute([$row['id']]);
        if ($use->rowCount() === 1) { // two people opening the same link at once: only one wins
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$id]);
            $pdo->prepare('UPDATE login_confirmations SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$id]);
            $pdo->commit();
            clearFailedLogins($row['username']); // a locked-out person can log in again
            clearFailedLogins($row['email']);
            auditLog($id, 'password_reset_done');
            sendMail($row['email'], 'Your Scholar Monitor password was changed', "The password for your Scholar Monitor account was just changed.\n\nIf this was not you, ask an administrator for help right away.");
            $done = true;
        } else {
            $pdo->rollBack();
            $row = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password | MCC BaluQ</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS styles -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/app-extra.css">
</head>

<body>

    <main class="login-container">

        <!-- Left Branding Section -->
        <section class="login-brand">
            <img src="assets/images/BALUQ-LOGO-WHITE.png"
                 alt="BaluQ Logo"
                 class="login-logo">

            <h1>MCC BaluQ</h1>

            <p class="brand-tagline">
                Ing abante mu, Ing bukas mu.
            </p>

            <div class="brand-message">
                <h2>Your Scholarship Journey Starts Here!</h2>
                <p>
                    Monitor your academic progress, track scholarship
                    requirements, and stay updated with important alerts.
                </p>
            </div>

        </section>


        <!-- Right Section -->
        <section class="login-section">

            <div class="login-card">

                <div class="mobile-brand">
                    <img src="assets/images/BALUQ-LOGO-Q.png"
                         alt="BaluQ Logo"
                         class="mobile-logo">

                    <h2>"Balu Qu, iSKOLAR Qu"</h2>
                </div>

                <div class="login-heading">
                    <span class="welcome-label">RESET PASSWORD</span>
                    <h2>Choose a new password</h2>
                    <p>Pick a password you have not used here before.</p>
                </div>

                <?php if ($done): ?>
                <p class="login-success" role="status">Your password was changed. <a href="login.php">Log in</a> with the new password.</p>
                <?php elseif (!$row): ?>
                <p class="login-error" role="alert">This reset link is not valid or has expired. <a href="forgot_password.php">Ask for a new one</a>.</p>
                <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

                    <div class="form-group">
                        <label for="password">New password</label>
                        <input type="password" id="password" name="password" maxlength="72" autocomplete="new-password" required>
                    </div>
                    <p class="login-hint">10 to 72 characters.</p>

                    <div class="form-group">
                        <label for="password_again">New password again</label>
                        <input type="password" id="password_again" name="password_again" maxlength="72" autocomplete="new-password" required>
                    </div>

                    <?php foreach ($errors as $message): ?>
                    <p class="login-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
                    <?php endforeach; ?>

                    <button type="submit" class="login-button">Change password</button>
                </form>
                <?php endif; ?>

                <div class="login-divider">
                    <span>STUDENT PORTAL</span>
                </div>

                <p class="login-help">
                    <a href="login.php">Back to log in</a>
                </p>

            </div>

            <footer class="login-footer">
                © <span id="login-year"></span> BALUQ. All Rights Reserved.
            </footer>

        </section>

    </main>

    <script src="assets/js/login.js"></script>

</body>
</html>