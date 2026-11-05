<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/security.php';
require_once __DIR__.'/includes/functions.php';
$user=currentUser();
if(!$user) redirect('login.php');
redirect(homeFor($user['role']));
