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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enter your code - Scholar Monitor</title>
</head>
<body>
    <h1>Check your email</h1>
    <p>We sent a 6-digit code to the email address on your account. It expires in <?= LOGIN_CODE_MINUTES ?> minutes.</p>
    <?php if ($error !== ''): ?>
        <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <p><label>Code <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="12" required autocomplete="one-time-code"></label></p>
        <p><button type="submit">Log in</button></p>
    </form>
    <form method="post">
        <?= csrf_field() ?>
        <p><button type="submit" name="resend" value="1">Send a new code</button></p>
    </form>
    <p><a href="login.php">Start over</a></p>
</body>
</html>