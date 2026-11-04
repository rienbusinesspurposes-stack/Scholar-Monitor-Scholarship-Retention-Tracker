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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in - Scholar Monitor</title>
</head>
<body>
    <h1>Scholar Monitor</h1>
    <?php if ($error !== ''): ?>
        <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <p><label>Username or email <input type="text" name="identifier" value="<?= htmlspecialchars($identifier, ENT_QUOTES) ?>" maxlength="255" required autocomplete="username"></label></p>
        <p><label>Password <input type="password" name="password" maxlength="72" required autocomplete="current-password"></label></p>
        <p><button type="submit">Log in</button></p>
    </form>
</body>
</html>