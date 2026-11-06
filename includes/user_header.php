<?php
// Top of every student page: <head>, sidebar and top bar.
// The page loads config/database.php, includes/security.php and includes/functions.php, calls requireRole(['User']),
// sets $user and $pageTitle, then includes this file. It leaves $scholar (null if the account has none) and $unread for the page.

function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

$stmt = $pdo->prepare('SELECT id, program_id, student_number, status FROM scholars WHERE user_id = ?');
$stmt->execute([$user['id']]);
$scholar = $stmt->fetch() ?: null;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
$stmt->execute([$user['id']]);
$unread = (int) $stmt->fetchColumn();

$navItems = [
    'dashboard.php'        => ['🏠︎', 'Dashboard'],
    'scholarship.php'      => ['🎖', 'My Scholarship'],
    'academics-record.php' => ['🗒', 'Academic Records'],
    'documents.php'        => ['✉', 'My Documents'],
    'notifications.php'    => ['🛎', 'Notifications'],
    'deadlines.php'        => ['🗓', 'Deadlines'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | GranTrack</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Styles (load order: theme > base > layout > components) -->
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">
</head>

<body>

    <div class="app-container">

        <!-- ================= SIDEBAR ================= -->
        <aside class="sidebar" id="sidebar" aria-label="Main navigation">

            <div class="sidebar-brand">
                <img src="../assets/images/grantrack-login-logo-white.png" alt="GranTrack logo" class="brand-logo">

                <div class="brand-info">
                    <h2>GranTrack</h2>
                    <p>"Bantay Grant, Gabay sa Pangarap"</p>
                </div>
            </div>

            <nav class="sidebar-navigation" aria-label="Student pages">

                <p class="nav-heading">MAIN MENU</p>

                <ul class="nav-list">
                    <?php foreach ($navItems as $href => [$icon, $label]): ?>
                    <li>
                        <a href="<?= $href ?>" class="nav-link">
                            <span class="nav-icon" aria-hidden="true"><?= $icon ?></span>
                            <span><?= $label ?></span>
                            <?php if ($href === 'notifications.php' && $unread > 0): ?>
                            <span class="notification-count" aria-label="<?= $unread ?> unread"><?= $unread ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <p class="nav-heading account-heading">ACCOUNT</p>

                <ul class="nav-list">
                    <li>
                        <a href="profile.php" class="nav-link">
                            <span class="nav-icon" aria-hidden="true">👤</span>
                            <span>My Profile</span>
                        </a>
                    </li>
                </ul>

            </nav>

            <div class="sidebar-footer">
                <a href="#" class="logout-link" id="logout-button">
                    <span aria-hidden="true">➜]</span>
                    <span>Log Out</span>
                </a>

                <div class="sidebar-version">
                    <p>© <span data-year></span> MCC GranTrack</p>
                </div>
            </div>

        </aside>

        <!-- Dims the page when the mobile drawer is open -->
        <div class="sidebar-backdrop" aria-hidden="true"></div>


        <!-- ================= MAIN CONTENT ================= -->
        <main class="main-content">

            <header class="top-header">

                <div class="header-left">
                    <button type="button"
                            class="menu-toggle"
                            aria-label="Open navigation menu"
                            aria-controls="sidebar"
                            aria-expanded="false">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>

                    <div class="header-title">
                        <h2><?= e($pageTitle) ?></h2>
                        <p>GranTrack: Scholarship Management System</p>
                    </div>
                </div>

                <div class="header-actions">

                    <a href="notifications.php"
                       class="header-notification"
                       aria-label="View notifications">
                        🔔
                        <?php if ($unread > 0): ?><span class="notification-dot"></span><?php endif; ?>
                    </a>

                    <div class="student-account">
                        <div class="student-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1))) ?></div>

                        <div class="student-details">
                            <strong><?= e($user['full_name']) ?></strong>
                            <span><?= e($scholar['student_number'] ?? '') ?></span>
                        </div>
                    </div>

                </div>

            </header>