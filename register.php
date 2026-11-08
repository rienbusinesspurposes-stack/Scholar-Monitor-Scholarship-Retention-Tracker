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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account | MCC BaluQ</title>

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
                    <span class="welcome-label">JOIN BALUQ</span>
                    <h2>Create an account</h2>
                    <p>Sign up with your email. Your login codes are sent there.</p>
                </div>

                <?php if ($created): ?>
                <p class="login-success" role="status">Your account was created. <a href="login.php">Log in</a> with your username and password. A code will be emailed to you each time.</p>
                <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES) ?>" maxlength="50" autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" maxlength="255" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($fullName, ENT_QUOTES) ?>" maxlength="150" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" maxlength="72" autocomplete="new-password" required>
                    </div>
                    <p class="login-hint">10 to 72 characters.</p>

                    <div class="form-group">
                        <label for="password_again">Password again</label>
                        <input type="password" id="password_again" name="password_again" maxlength="72" autocomplete="new-password" required>
                    </div>

                    <?php foreach ($errors as $message): ?>
                    <p class="login-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES) ?></p>
                    <?php endforeach; ?>

                    <button type="submit" class="login-button">Create account</button>
                </form>
                <?php endif; ?>

                <div class="login-divider">
                    <span>STUDENT PORTAL</span>
                </div>

                <p class="login-help">
                    Already have an account?
                    <a href="login.php">Log in</a>
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