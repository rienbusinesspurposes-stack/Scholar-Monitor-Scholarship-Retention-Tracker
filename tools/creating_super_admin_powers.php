<?php
// Creates the first Super Admin account. Run once from Git Bash:  php tools/creating_super_admin_powers.php
// What you type is visible on screen, so choose a password nobody is watching you type.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}

$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require $root . '/config/database.php';
require $root . '/includes/security.php';
require $root . '/includes/functions.php';

function stop(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function ask(string $label): string
{
    echo $label . ': ';
    $line = fgets(STDIN);
    if ($line === false) {
        stop("\nNo input received. Run this in an interactive Git Bash window.");
    }
    return rtrim($line, "\r\n");
}

if ((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'SuperAdmin'")->fetchColumn() > 0) {
    stop('A Super Admin already exists, so nothing was created. More accounts are added from inside the system.');
}

$username = trim(ask('Username (3-50 letters, digits, _ or .)'));
if (!isValidUsername($username)) {
    stop('That username is not allowed: use 3 to 50 letters, digits, _ or . only.');
}
$email = trim(ask('Email address'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    stop('That email address is not valid.');
}
$fullName = trim(ask('Full name'));
if ($fullName === '' || mb_strlen($fullName) > 150) {
    stop('The full name must be 1 to 150 characters.');
}
$password = ask('Password (12 to 72 characters)');
if (mb_strlen($password) < 12 || strlen($password) > 72) { // 72 bytes is the most bcrypt reads
    stop('The password must be 12 to 72 characters.');
}
if ($password !== ask('Type the password again')) {
    stop('The two passwords do not match. Nothing was created.');
}

try {
    $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, 'SuperAdmin']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        stop('That username or email is already used by another account. Nothing was created.');
    }
    throw $e;
}
$id = (int) $pdo->lastInsertId();
auditLog($id, 'super_admin_created', 'created with tools/creating_super_admin_powers.php');
echo "Super Admin '$username' created (id $id).\n";