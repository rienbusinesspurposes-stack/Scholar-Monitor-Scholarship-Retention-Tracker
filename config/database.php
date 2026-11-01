<?php
// Database connection. Set values in config/local.php or environment variables; never commit secrets.
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
$DB_HOST = $local['host'] ?? (getenv('SCHOLAR_DB_HOST') ?: 'localhost');
$DB_NAME = $local['name'] ?? (getenv('SCHOLAR_DB_NAME') ?: 'scholar_monitor');
$DB_USER = $local['user'] ?? (getenv('SCHOLAR_DB_USER') ?: 'root');
$DB_PASS = $local['pass'] ?? (getenv('SCHOLAR_DB_PASS') ?: '');

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Scholar Monitor database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the server configuration and the PHP error log.');
}
