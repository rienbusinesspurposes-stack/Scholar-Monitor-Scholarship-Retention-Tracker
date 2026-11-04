<?php
// Sign-up for scholars. Every account made here is a plain User; staff accounts are never created from this page.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

if ($current = currentUser()) {
    redirect(homeFor($current['role']));
}

$errors = [];
$created = false;
$username = $email = $fullName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $again = (string) ($_POST['password_again'] ?? '');

    // At most 20 sign-up attempts per hour from one address, so nobody can mass-create accounts or probe for emails.
    $throttle = 'register:' . clientIp();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > NOW() - INTERVAL 60 MINUTE');
    $stmt->execute([$throttle]);
    if ((int) $stmt->fetchColumn() >= 20) {
        $errors[] = 'Too many sign-up attempts. Please try again later.';
    } else {
        recordFailedLogin($throttle);
        if (!isValidUsername($username)) {
            $errors[] = 'The username must be 3 to 50 letters, digits, _ or . only.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors[] = 'Enter a valid email address. Your login codes are sent there.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 150 || !mb_check_encoding($fullName, 'UTF-8') || preg_match('/\p{C}/u', $fullName)) {
            $errors[] = 'Enter your full name (up to 150 characters).';
        }
        if ($problem = passwordProblem($password)) {
            $errors[] = $problem;
        } elseif ($password !== $again) {
            $errors[] = 'The two passwords do not match.';
        }

        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, 'User']);
                auditLog((int) $pdo->lastInsertId(), 'registered');
                $created = true;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $errors[] = 'That username or email is already in use.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account - Scholar Monitor</title>
</head>
<body>
    <h1>Create an account</h1>
    <?php if ($created): ?>
        <p role="status">Your account was created. <a href="login.php">Log in</a> with your username and password. A code will be emailed to you each time.</p>
    <?php else: ?>
        <?php foreach ($errors as $message): ?>
            <p role="alert"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
        <?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <p><label>Username <input type="text" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES) ?>" maxlength="50" required autocomplete="username"></label></p>
            <p><label>Email <input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" maxlength="255" required autocomplete="email"></label></p>
            <p><label>Full name <input type="text" name="full_name" value="<?= htmlspecialchars($fullName, ENT_QUOTES) ?>" maxlength="150" required autocomplete="name"></label></p>
            <p><label>Password (10 to 72 characters) <input type="password" name="password" maxlength="72" required autocomplete="new-password"></label></p>
            <p><label>Password again <input type="password" name="password_again" maxlength="72" required autocomplete="new-password"></label></p>
            <p><button type="submit">Create account</button></p>
        </form>
    <?php endif; ?>
    <p><a href="login.php">Already have an account? Log in</a></p>
</body>
</html>