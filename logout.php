<?php
// Logs out. Only a POST with a valid CSRF token counts, so another site cannot log people out by linking here.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $user = currentUser();
    if ($user) {
        auditLog((int) $user['id'], 'logout');
    }
    forceLogout();
    redirect('login.php');
}
redirect('index.php');