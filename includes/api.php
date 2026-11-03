<?php
// Helpers for the JSON endpoints in each role's api/ folder (called from the page with fetch/AJAX).
// Load config/database.php, includes/security.php and includes/functions.php first.

function apiOut(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE) ?: '{"error":"Server error"}';
    exit;
}

// Start of every endpoint: answers in JSON only (never a redirect or an HTML error page) and returns the user.
// A POST endpoint needs the CSRF token in the X-CSRF-Token header or a _csrf field.
function apiBoot(array $roles, string $method = 'GET'): array
{
    ini_set('display_errors', '0'); // a PHP warning printed into the response would break the JSON
    set_exception_handler(function (Throwable $e): void {
        error_log('Scholar Monitor API error: ' . $e->getMessage());
        apiOut(500, ['error' => 'Server error']);
    });

    $user = currentUser();
    if (!$user) {
        apiOut(401, ['error' => 'Not logged in']);
    }
    if (!in_array($user['role'], $roles, true)) {
        auditLog($user['id'], 'access_denied', $_SERVER['REQUEST_URI'] ?? null);
        apiOut(403, ['error' => 'Access denied']);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        apiOut(405, ['error' => 'Method not allowed']);
    }
    if ($method === 'POST') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        if (!is_string($token) || !verifyCsrfToken($token)) {
            apiOut(403, ['error' => 'Invalid or expired token. Reload the page.']);
        }
    }
    return $user;
}
