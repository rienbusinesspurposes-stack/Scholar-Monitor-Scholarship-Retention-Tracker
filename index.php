<?php
// Public front page. A logged-in visitor goes straight to their own home page; everyone else sees this page.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

if ($user = currentUser()) {
    redirect(homeFor($user['role']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCC BaluQ | Scholarship Retention Tracker</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS styles -->
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>

<body>

    <main>

        <section class="landing-hero">
            <img src="assets/images/BALUQ-LOGO-WHITE.png" alt="BaluQ logo" class="landing-logo">
            <p class="landing-label">Mabalacat City College</p>
            <h1>Keep your scholarship. Know where you stand.</h1>
            <p class="landing-lead">
                MCC BaluQ tracks each scholar's grades and renewal documents, and tells you
                early when a grade is getting close to the minimum.
            </p>
            <div class="landing-actions">
                <a href="login.php" class="landing-btn">Sign in</a>
                <a href="register.php" class="landing-btn landing-btn-outline">Create an account</a>
            </div>
            <p class="landing-tagline">Ing abante mu, Ing bukas mu.</p>
        </section>

        <section class="landing-features" aria-label="What BaluQ does">
            <article class="landing-card">
                <h2>Track your grades</h2>
                <p>See your latest average next to your program's minimum, and whether your status is Active, At Risk or Ineligible.</p>
            </article>
            <article class="landing-card">
                <h2>Send your documents</h2>
                <p>Upload each requirement as a PDF, JPG or PNG. If one is rejected, you are told why so you can fix it.</p>
            </article>
            <article class="landing-card">
                <h2>Never miss a deadline</h2>
                <p>Get notifications and email reminders before a document is due, and when your grade needs attention.</p>
            </article>
        </section>

    </main>

    <footer class="landing-footer">
        © <?= date('Y') ?> BALUQ. All Rights Reserved.
    </footer>

</body>
</html>