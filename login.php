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
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign In | MCC GranTrack</title><link rel="stylesheet" href="assets/css/login.css"><link rel="stylesheet" href="assets/css/theme.css"></head>
<body><main class="login-container"><section class="login-brand"><img src="assets/images/grantrack-login-logo-white.png" alt="GranTrack" class="login-logo"><h1>MCC GranTrack</h1><p class="brand-tagline">Bantay Grant, Gabay sa Pangarap.</p><div class="brand-message"><h2>Your Scholarship Journey Starts Here!</h2><p>Monitor your academic progress, track scholarship requirements, and stay updated with important alerts.</p></div><p class="brand-footer">MCC Scholarship Monitoring System</p></section><section class="login-section"><div class="login-card"><div class="login-heading"><span class="welcome-label">WELCOME BACK!</span><h2>Sign in to your account</h2><p>Enter your username or school email and password.</p></div><?php if ($error !== ''): ?><p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form method="post" action="login.php"><?= csrf_field() ?><div class="form-group"><label for="identifier">Username or Email</label><input id="identifier" type="text" name="identifier" value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>" maxlength="255" placeholder="Enter your username or email" autocomplete="username" required></div><div class="form-group"><label for="password">Password</label><input id="password" type="password" name="password" maxlength="72" placeholder="Enter your password" autocomplete="current-password" required></div><div class="login-options"><a href="forgot_password.php">Forgot password?</a></div><button class="login-button" type="submit">Sign In</button></form><div class="login-divider"><span>SECURE STUDENT PORTAL</span></div><p class="login-help">New student? <a href="register.php">Create an account</a></p></div><footer class="login-footer">© <?= date('Y') ?> MCC GranTrack. All Rights Reserved.</footer></section></main></body></html>