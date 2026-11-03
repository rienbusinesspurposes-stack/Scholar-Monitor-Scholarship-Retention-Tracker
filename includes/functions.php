<?php
// Shared helpers: roles, redirects, the logged-in user and the role guards.
// Load config/database.php and includes/security.php first.

const ROLES = ['SuperAdmin', 'Admin', 'User'];

function homeFor(string $role): string
{
    return match ($role) {
        'SuperAdmin' => 'super_admin/dashboard.php',
        'Admin'      => 'admin/dashboard.php',
        default      => 'user/dashboard.php',
    };
}

// URL path of the project folder: '' on a virtual host, '/Final_Project' under Laragon's www folder.
// Found by counting how many folders deep the running script sits inside the project.
function appBaseUrl(): string
{
    $root   = str_replace('\\', '/', dirname(__DIR__));
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    if (stripos($script, $root . '/') !== 0) {
        return ''; // shortcut: scripts reached through a symlink fall back to the web root, upgrade if that is ever used
    }
    $depth = substr_count(substr($script, strlen($root) + 1), '/');
    $dirs  = explode('/', trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'));
    $base  = implode('/', array_filter(array_slice($dirs, 0, max(0, count($dirs) - $depth)), 'strlen'));
    return $base === '' ? '' : '/' . $base;
}

// Internal paths only: the leading slashes are stripped so the target can never leave this site.
function redirect(string $path): never
{
    header('Location: ' . appBaseUrl() . '/' . ltrim($path, '/\\'));
    exit;
}

function isValidUsername(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_.]{3,50}\z/', $username);
}

// Reads the account fresh from the database on every call, so a changed role or a deactivated account
// takes effect on the very next request. The login code stores the id in $_SESSION['user_id'].
function currentUser(): ?array
{
    global $pdo;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, username, email, full_name, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) {
        forceLogout();
        return null;
    }
    return $user;
}

function forceLogout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

// Pass every role that may enter, e.g. requireRole(['Admin', 'SuperAdmin']). Anything else gets a 403.
function requireRole(array $roles): array
{
    $user = requireLogin();
    if (!in_array($user['role'], $roles, true)) {
        auditLog($user['id'], 'access_denied', $_SERVER['REQUEST_URI'] ?? null);
        http_response_code(403);
        exit('Access denied.');
    }
    return $user;
}
