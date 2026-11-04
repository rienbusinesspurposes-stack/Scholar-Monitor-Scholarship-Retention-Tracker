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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password - Scholar Monitor</title>
</head>
<body>
    <h1>Reset your password</h1>
    <?php if ($done): ?>
        <p role="status">Your password was changed. <a href="login.php">Log in</a> with the new password.</p>
    <?php elseif (!$row): ?>
        <p role="alert">This reset link is not valid or has expired. <a href="forgot_password.php">Ask for a new one</a>.</p>
    <?php else: ?>
        <?php foreach ($errors as $message): ?>
            <p role="alert"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
        <?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <p><label>New password (10 to 72 characters) <input type="password" name="password" maxlength="72" required autocomplete="new-password"></label></p>
            <p><label>New password again <input type="password" name="password_again" maxlength="72" required autocomplete="new-password"></label></p>
            <p><button type="submit">Change password</button></p>
        </form>
    <?php endif; ?>
</body>
</html>