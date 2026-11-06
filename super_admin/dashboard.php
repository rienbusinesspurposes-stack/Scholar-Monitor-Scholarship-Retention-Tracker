<?php
// shortcut: Super Admins use the staff pages in admin/ for now, upgrade when they get their own pages (users, access, security).
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['SuperAdmin']);
redirect('admin/dashboard.php');