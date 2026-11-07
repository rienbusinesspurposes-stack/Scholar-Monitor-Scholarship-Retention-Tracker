<?php
// Top of every staff page (Admin and SuperAdmin): <head>, sidebar and top bar, and the opening of .page-content.
// The page loads config/database.php, includes/security.php and includes/functions.php, calls
// requireRole(['Admin','SuperAdmin']), sets $user and $pageTitle, then includes this file.
// includes/admin_footer.php closes everything opened here.

if (!function_exists('e')) {
    function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

$navItems = [
    'dashboard.php' => ['🏠︎', 'Dashboard'],
    'scholars.php'  => ['🎓', 'Scholars'],
    'grades.php'    => ['🗒', 'Grades'],
];
$roleLabel = $user['role'] === 'SuperAdmin' ? 'Super Admin' : 'Admin';
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
                    <p><?= e($roleLabel) ?> Panel</p>
                </div>
            </div>

            <nav class="sidebar-navigation" aria-label="Staff pages">

                <p class="nav-heading">MANAGE</p>
                <ul class="nav-list">
                    <?php foreach ($navItems as $href => [$icon, $label]): ?>
                    <li>
                        <a href="<?= $href ?>" class="nav-link">
                            <span class="nav-icon" aria-hidden="true"><?= $icon ?></span>
                            <span><?= $label ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
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
                    <p>BALUQ: Scholarship Portal · <?= e($user['full_name']) ?> (<?= e($roleLabel) ?>)</p>
                </div>
                <div class="header-right">
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

                <div class="page-header">
                    <p class="section-label">BALUQ: <?= e($roleLabel) ?></p>
                    <h1><?= e($pageTitle) ?></h1>
                    <?php if (!empty($pageIntro)): ?><p><?= e($pageIntro) ?></p><?php endif; ?>
                </div>

                <?php if (!empty($_SESSION['flash'])): ?>
                <p class="form-message <?= $_SESSION['flash'][0] === 'success' ? 'success' : 'error' ?>" role="alert"><?= e($_SESSION['flash'][1]) ?></p>
                <?php unset($_SESSION['flash']); endif; ?>