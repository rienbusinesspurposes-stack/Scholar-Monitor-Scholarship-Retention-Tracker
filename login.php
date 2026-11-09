<?php
// Step 1 of logging in: username or email plus password. A correct password emails a 6-digit code,
// and login_verification.php finishes the login.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if ($current = currentUser()) {
    redirect(homeFor($current['role']));
}

$error = '';
$identifier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (str_contains($identifier, ':')) {
        $identifier = ''; // no username or email has a colon, and the sign-up and reset throttles use colon keys, so typed text must never match one
    }

    if (loginIsLocked($identifier)) {
        $error = 'Too many failed attempts. Please try again in a few minutes.';
    } else {
        $account = null;
        if ($identifier !== '' && strlen($identifier) <= 255 && strlen($password) <= 72) {
            $stmt = $pdo->prepare('SELECT id, email, password_hash, is_active FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$identifier, $identifier]);
            $account = $stmt->fetch() ?: null;
        }
        if ($account) {
            $valid = password_verify($password, $account['password_hash']);
        } else {
            password_hash($password, PASSWORD_DEFAULT); // same work as a real check, so the response time does not reveal which names exist
            $valid = false;
        }

        if ($valid && $account['is_active']) {
            $id = (int) $account['id'];
            $code = createLoginConfirmation($id);
            $sent = sendMail(
                $account['email'],
                'Your Scholar Monitor login code',
                "Your login code is $code\n\nIt expires in " . LOGIN_CODE_MINUTES . " minutes. If you did not try to log in, ignore this message."
            );
            if ($sent) {
                session_regenerate_id(true);
                $_SESSION['pending_login'] = [
                    'user_id'    => $id,
                    'identifier' => $identifier,
                    'expires'    => time() + LOGIN_CODE_MINUTES * 60,
                    'resends'    => 0,
                    'last_send'  => time(),
                ];
                auditLog($id, 'login_code_sent');
                redirect('login_verification.php');
            }
            auditLog($id, 'login_code_failed', 'the email could not be sent');
            $error = 'We could not send your login code. Please try again later.';
        } else {
            recordFailedLogin($identifier);
            auditLog($account ? (int) $account['id'] : null, 'login_failed', $valid ? 'account is inactive' : null);
            $error = 'Wrong username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MCC BaluQ</title>

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


        <!-- Right Login Section -->
        <section class="login-section">

            <div class="login-card">

                <div class="mobile-brand">
                    <img src="assets/images/BALUQ-LOGO-Q.png"
                         alt="BaluQ Logo"
                         class="mobile-logo">

                    <h2>"Balu Qu, iSKOLAR Qu"</h2>
                </div>

                <div class="login-heading">
                    <span class="welcome-label">WELCOME BACK!</span>
                    <h2>Sign in to your account</h2>
                    <p>Enter your account details to continue.</p>
                </div>

                <form id="login-form" method="post">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="identifier">Username or email</label>

                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            value="<?= htmlspecialchars($identifier, ENT_QUOTES) ?>"
                            placeholder="Enter your username or email"
                            maxlength="255"
                            autocomplete="username"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>

                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                maxlength="72"
                                autocomplete="current-password"
                                required>

                            <button
                                type="button"
                                id="toggle-password"
                                class="password-toggle">
                                Show
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>

                    <p id="login-error" class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>

                    <button type="submit" class="login-button">
                        Sign In
                    </button>

                </form>

                <div class="login-divider">
                    <span>STUDENT PORTAL</span>
                </div>

                <p class="login-help">
                    No account yet?
                    <a href="register.php">Create an account</a>
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