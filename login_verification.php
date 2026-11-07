<?php
// Step 2 of logging in: the 6-digit code from the email.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

$pending = $_SESSION['pending_login'] ?? null;
if (!$pending || $pending['expires'] < time()) {
    unset($_SESSION['pending_login']);
    redirect('login.php');
}

$stmt = $pdo->prepare('SELECT id, email, role, is_active FROM users WHERE id = ?');
$stmt->execute([$pending['user_id']]);
$account = $stmt->fetch();
if (!$account || !$account['is_active']) {
    unset($_SESSION['pending_login']);
    redirect('login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $id = (int) $account['id'];

    if (isset($_POST['resend'])) {
        if ($pending['resends'] >= 3 || time() - $pending['last_send'] < 60) {
            $error = 'Please wait a minute before asking for another code. You can ask at most 3 times.';
        } else {
            $code = createLoginConfirmation($id);
            $sent = sendMail(
                $account['email'],
                'Your Scholar Monitor login code',
                "Your login code is $code\n\nIt expires in " . LOGIN_CODE_MINUTES . " minutes. If you did not try to log in, ignore this message."
            );
            if ($sent) {
                $pending['resends']++;
                $pending['last_send'] = time();
                $pending['expires'] = time() + LOGIN_CODE_MINUTES * 60;
                $_SESSION['pending_login'] = $pending;
                auditLog($id, 'login_code_resent');
                $error = 'A new code was sent. The old one no longer works.';
            } else {
                $error = 'We could not send the code. Please try again later.';
            }
        }
    } else {
        $code = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Enter the 6 digits from the email.';
        } elseif (verifyLoginConfirmation($id, $code)) {
            session_regenerate_id(true);
            $_SESSION = ['user_id' => $id, 'last_activity' => time()];
            clearFailedLogins($pending['identifier']);
            auditLog($id, 'login_ok');
            redirect(homeFor($account['role']));
        } else {
            auditLog($id, 'login_code_failed', 'wrong or expired code');
            $error = 'That code is wrong or has expired. Each code allows 5 tries; ask for a new one if needed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter your code | MCC BaluQ</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS styles -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/login.css">
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


        <!-- Right Code Section -->
        <section class="login-section">

            <div class="login-card">

                <div class="mobile-brand">
                    <img src="assets/images/BALUQ-LOGO-Q.png"
                         alt="BaluQ Logo"
                         class="mobile-logo">

                    <h2>"Balu Qu, iSKOLAR Qu"</h2>
                </div>

                <div class="login-heading">
                    <span class="welcome-label">ALMOST THERE!</span>
                    <h2>Check your email</h2>
                    <p>We sent a 6-digit code to the email on your account. It expires in <?= LOGIN_CODE_MINUTES ?> minutes.</p>
                </div>

                <form method="post">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="code">Code</label>

                        <input
                            type="text"
                            id="code"
                            name="code"
                            placeholder="6-digit code"
                            inputmode="numeric"
                            pattern="[0-9 ]*"
                            maxlength="12"
                            autocomplete="one-time-code"
                            required>
                    </div>

                    <p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>

                    <button type="submit" class="login-button">
                        Log In
                    </button>
                </form>

                <div class="login-divider">
                    <span>DIDN'T GET IT?</span>
                </div>

                <form method="post">
                    <?= csrf_field() ?>
                    <button type="submit" name="resend" value="1" class="login-button">
                        Send a new code
                    </button>
                </form>

                <p class="login-help">
                    <a href="login.php">Start over</a>
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