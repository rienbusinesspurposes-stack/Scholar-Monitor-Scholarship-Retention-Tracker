<?php
// Shared GranTrack portal shell. Include after database, security and functions.
$portalUser = requireLogin();
function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function portalStart(string $title, string $section): void {
    global $portalUser;
    $role = $portalUser['role'];
    $base = '../assets/';
    $nav = $role === 'User'
        ? [['dashboard.php','Dashboard','⌂'],['scholarship.php','My Scholarship','🎖'],['academics-record.php','Academic Records','▤'],['documents.php','My Documents','▧'],['deadlines.php','Deadlines','◷'],['notifications.php','Notifications','♢'],['profile.php','My Profile','♙']]
        : ($role === 'Admin'
            ? [['dashboard.php','Dashboard','⌂'],['scholars.php','Scholars','♙'],['programs.php','Scholarship Programs','🎖'],['grades.php','Academic Records','▤'],['documents.php','Document Review','▧']]
            : [['dashboard.php','Dashboard','⌂'],['users.php','User Management','♙'],['programs.php','Scholarship Programs','🎖'],['audit.php','Audit Logs','▤']]);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.h($title).' | GranTrack</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="'.$base.'css/theme.css"><link rel="stylesheet" href="'.$base.'css/base.css"><link rel="stylesheet" href="'.$base.'css/layout.css"><link rel="stylesheet" href="'.$base.'css/components.css"><link rel="stylesheet" href="'.$base.'css/portal.css"></head><body><div class="app-container"><aside class="sidebar" id="sidebar"><div class="sidebar-brand"><img class="brand-logo" src="'.$base.'images/grantrack-login-logo-white.png" alt="GranTrack"><div class="brand-info"><h2>GranTrack</h2><p>Bantay Grant, Gabay sa Pangarap</p></div></div><nav class="sidebar-navigation"><p class="nav-heading">MAIN MENU</p><ul class="nav-list">';
    foreach ($nav as [$url,$label,$icon]) {
        $active = $section === $url ? ' active' : '';
        echo '<li><a class="nav-link'.$active.'" href="'.h($url).'"><span class="nav-icon" aria-hidden="true">'.h($icon).'</span><span>'.h($label).'</span></a></li>';
    }
    if (!empty($_SESSION['portal_flash'])) { echo '<div class="portal-notice success" role="status">'.h($_SESSION['portal_flash']).'</div>'; unset($_SESSION['portal_flash']); } echo '</ul><p class="nav-heading account-heading">ACCOUNT</p><ul class="nav-list"><li><form method="post" action="../logout.php">'.csrf_field().'<button class="nav-link" type="submit" style="width:100%;border:0;background:transparent;text-align:left"><span class="nav-icon">↪</span><span>Log Out</span></button></form></li></ul></nav><div class="sidebar-footer"><div class="sidebar-version"><p>© '.date('Y').' MCC GranTrack</p></div></div></aside><div class="sidebar-backdrop" aria-hidden="true"></div><main class="main-content"><header class="top-header"><div class="header-left"><button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button><div class="header-title"><h2>'.h($role).' Portal</h2><p>GranTrack: Scholarship Management System</p></div></div><div class="header-actions"><div class="student-account"><div class="student-avatar">'.h(mb_strtoupper(mb_substr($portalUser['full_name'],0,1))).'</div><div class="student-details"><strong>'.h($portalUser['full_name']).'</strong><span>'.h($role).'</span></div></div></div></header><section class="dashboard-content"><p class="section-label">'.h(strtoupper($role).' PORTAL').'</p><h1 class="portal-page-title">'.h($title).'</h1>';
}
function portalEnd(): void {
    echo '</section><footer class="main-footer"><p>© '.date('Y').' MCC GranTrack</p></footer></main></div><script src="../assets/js/nav.js"></script></body></html>';
}
function portalNotice(string $message, string $type='info'): void {
    echo '<div class="portal-notice '.h($type).'" role="status">'.h($message).'</div>';
}
function portalTable(array $headers, array $rows): void {
    echo '<div class="portal-table-wrap"><table class="portal-table"><thead><tr>';
    foreach($headers as $head) echo '<th>'.h($head).'</th>';
    echo '</tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="'.count($headers).'" class="portal-empty">No records found.</td></tr>';
    foreach($rows as $row){ echo '<tr>'; foreach($row as $cell) echo '<td>'.$cell.'</td>'; echo '</tr>'; }
    echo '</tbody></table></div>';
}
