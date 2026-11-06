<?php
// Top of every staff page: <head>, sidebar and top bar. Mirrors includes/user_header.php for Admin/SuperAdmin.
// The page loads config/database.php, includes/security.php and includes/functions.php, calls
// requireRole(['Admin','SuperAdmin']), sets $user and $pageTitle, then includes this file.
// shortcut: plain markup, no front-end styling pass yet, upgrade once the admin design is ready.

if (!function_exists('e')) {
    function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

$navItems = [
    'dashboard.php' => ['🏠︎', 'Dashboard'],
    'scholars.php'  => ['🎓', 'Scholars'],
];
$roleLabel = $user['role'] === 'SuperAdmin' ? 'Super Admin' : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | GranTrack</title>
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">GranTrack</div>
            <nav class="sidebar-nav">
                <?php foreach ($navItems as $href => [$icon, $label]):
                    $here = basename($_SERVER['PHP_SELF'] ?? '');
                    $active = strcasecmp($here, $href) === 0;
                ?>
                    <a href="<?= e($href) ?>" class="nav-item<?= $active ? ' active' : '' ?>">
                        <span class="nav-icon"><?= $icon ?></span>
                        <span class="nav-label"><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form id="logout-form" method="post" action="../logout.php">
                <?= csrf_field() ?>
            </form>
            <a href="#" class="nav-item logout-trigger" onclick="document.getElementById('logout-form').submit(); return false;">
                <span class="nav-icon">⎋</span>
                <span class="nav-label">Log out</span>
            </a>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <h1><?= e($pageTitle) ?></h1>
                <div class="top-bar-user">
                    <span class="user-name"><?= e($user['full_name']) ?></span>
                    <span class="role-badge"><?= e($roleLabel) ?></span>
                </div>
            </header>

            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="form-message <?= $_SESSION['flash']['type'] === 'error' ? 'error' : 'success' ?>">
                    <?= e($_SESSION['flash']['text']) ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>