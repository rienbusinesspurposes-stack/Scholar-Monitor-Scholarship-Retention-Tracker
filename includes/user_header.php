<?php
// Top of every student page: <head>, sidebar and top bar, and the opening of .page-content.
// The page loads config/database.php, includes/security.php and includes/functions.php, calls requireRole(['User']),
// sets $user and $pageTitle, then includes this file. It leaves $scholar (null if the account has none) and $unread for the page.
// includes/user_footer.php closes everything opened here.

$stmt = $pdo->prepare('SELECT id, program_id, student_number, status FROM scholars WHERE user_id = ?');
$stmt->execute([$user['id']]);
$scholar = $stmt->fetch() ?: null;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
$stmt->execute([$user['id']]);
$unread = (int) $stmt->fetchColumn();

$navItems = [
    'dashboard.php'        => ['🏠︎', 'Dashboard'],
    'scholarship.php'      => ['🎖', 'My Scholarship'],
    'academics-record.php' => ['🗒', 'Academics Record'],
    'documents.php'        => ['✉', 'Documents'],
    'notifications.php'    => ['🛎', 'Notifications'],
    'deadlines.php'        => ['🗓', 'Deadlines'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | BALUQ</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Styles (load order: theme > base > layout > components > app-extra) -->
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/app-extra.css">
</head>

<body>

    <div class="app-container">

        <!-- Sidebar navigation -->
        <aside class="sidebar" id="sidebar" aria-label="Main navigation">

            <div class="sidebar-brand">
                <img src="../assets/images/BALUQ-LOGO-Q.png" alt="BaluQ logo" class="brand-logo">
                <div class="brand-info">
                    <h2>BALUQ</h2>
                    <p>Scholarship Monitoring</p>
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

                <p class="nav-heading">ACCOUNT</p>
                <ul class="nav-list">
                    <li>
                        <a href="profile.php" class="nav-link">
                            <span class="nav-icon" aria-hidden="true">𐀪</span>
                            <span>Profile</span>
                        </a>
                    </li>
                </ul>

            </nav>

            <div class="sidebar-footer">
                <a href="#" class="logout-link" data-logout>
                    <span aria-hidden="true">➜]</span>
                    <span>Log Out</span>
                </a>
                <div class="sidebar-version">
                    <p>© <span data-year></span> MCC BALUQ</p>
                </div>
            </div>

        </aside>

        <!-- Mobile backdrop -->
        <div class="sidebar-backdrop" aria-hidden="true"></div>


        <main class="main-content">

            <!-- Top bar -->
            <header class="top-header">
                <div class="header-left">
                    <h3>Mabalacat City College</h3>
                    <p>BALUQ: Scholarship Portal</p>
                </div>
                <div class="header-right">
                    <a href="notifications.php" class="header-notification"
                       aria-label="Notifications<?= $unread > 0 ? ', ' . $unread . ' unread' : '' ?>">
                        🔔
                        <?php if ($unread > 0): ?><span class="notification-badge"><?= $unread ?></span><?php endif; ?>
                    </a>
                    <button type="button" class="menu-toggle" aria-controls="sidebar"
                            aria-expanded="true" aria-label="Hide navigation menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </header>


            <!-- Page content -->
            <section class="page-content">