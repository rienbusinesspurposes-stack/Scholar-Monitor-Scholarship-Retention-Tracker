<?php
// Entry point: send visitors to their own home page, or to the login page.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
$user = currentUser();
redirect($user ? homeFor($user['role']) : 'login.php');
