<?php
// Asks for a password-reset link by email. The page never says whether the address has an account.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if ($current = currentUser()) {
    redirect(homeFor($current['role']));
}

const RESET_LINK_MINUTES = 30;

$error = '';
$sent = false;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $email = trim((string) ($_POST['email'] ?? ''));

    // At most 20 requests per hour from one address.
    $throttle = 'forgot:' . clientIp();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > NOW() - INTERVAL 60 MINUTE');
    $stmt->execute([$throttle]);
    if ((int) $stmt->fetchColumn() >= 20) {
        $error = 'Too many requests. Please try again later.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        recordFailedLogin($throttle);
        $error = 'Enter a valid email address.';
    } else {
        recordFailedLogin($throttle);
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if ($account) {
            $id = (int) $account['id'];
            // At most 3 links per account per hour, so nobody can flood a person's inbox.
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ? AND created_at > NOW() - INTERVAL 60 MINUTE');
            $stmt->execute([$id]);
            $base = appUrl();
            if ($base === '') {
                error_log('Scholar Monitor: set app_url in config/local.php (or SCHOLAR_APP_URL) so reset emails can contain a link');
            } elseif ((int) $stmt->fetchColumn() < 3) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$id]);
                $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ' . RESET_LINK_MINUTES . ' MINUTE)')
                    ->execute([$id, hash('sha256', $token)]); // only the hash is stored, never the link itself
                sendMail(
                    $account['email'],
                    'Reset your Scholar Monitor password',
                    "Someone asked to reset the password for your Scholar Monitor account.\n\nOpen this link within " . RESET_LINK_MINUTES . " minutes:\n$base/reset_password.php?token=$token\n\nIf this was not you, ignore this message and your password stays the same."
                );
                auditLog($id, 'password_reset_requested');
            }
        }
        // shortcut: a real send takes longer than doing nothing, so response time can hint that an email has an account; upgrade by sending from a background job
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password | MCC BaluQ</title>

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
                    <span class="welcome-label">FORGOT PASSWORD?</span>
                    <h2>Reset your password</h2>
                    <p>Enter your email and we will send you a reset link.</p>
                </div>

                <?php if ($sent): ?>
                <p class="login-success" role="status">If that email address belongs to an account, we have sent a link to reset the password. It works for <?= RESET_LINK_MINUTES ?> minutes.</p>
                <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" maxlength="255" autocomplete="email" placeholder="Enter your email" required>
                    </div>

                    <?php if ($error !== ''): ?>
                    <p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
                    <?php endif; ?>

                    <button type="submit" class="login-button">Send reset link</button>
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