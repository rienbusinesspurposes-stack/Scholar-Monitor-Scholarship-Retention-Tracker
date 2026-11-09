<?php
// The alert job. Run it from Git Bash:  php tools/send_alerts.php
// Schedule it once a day (Windows Task Scheduler on Laragon, or cron on a server). Running it twice in a day is harmless.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'send_alerts';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/functions.php';
require $root . '/includes/scholar_status.php';
require $root . '/includes/mailer.php';
require $root . '/includes/alerts.php';

$done = runAlerts();
echo "Student reminders: {$done['students']}\nStaff emails sent: {$done['staff']}\nStaff emails failed: {$done['failed']}\n";
exit($done['failed'] ? 1 : 0);