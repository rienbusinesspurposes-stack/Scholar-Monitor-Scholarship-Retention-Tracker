<?php
// Mail settings from config/local.php or environment variables. Never store real credentials here.
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];

$config = [
    'enabled'    => filter_var($local['mail_enabled'] ?? (getenv('SCHOLAR_MAIL_ENABLED') ?: '0'), FILTER_VALIDATE_BOOLEAN),
    'host'       => trim((string)($local['mail_host'] ?? (getenv('SCHOLAR_MAIL_HOST') ?: 'smtp.gmail.com'))),
    'port'       => (int)($local['mail_port'] ?? (getenv('SCHOLAR_MAIL_PORT') ?: 587)),
    'encryption' => strtolower(trim((string)($local['mail_encryption'] ?? (getenv('SCHOLAR_MAIL_ENCRYPTION') ?: 'tls')))),
    'username'   => trim((string)($local['mail_username'] ?? (getenv('SCHOLAR_MAIL_USERNAME') ?: ''))),
    'password'   => (string)($local['mail_password'] ?? (getenv('SCHOLAR_MAIL_PASSWORD') ?: '')),
    'from_email' => trim((string)($local['mail_from_email'] ?? (getenv('SCHOLAR_MAIL_FROM_EMAIL') ?: ''))),
    'from_name'  => trim((string)($local['mail_from_name'] ?? (getenv('SCHOLAR_MAIL_FROM_NAME') ?: 'Scholar Monitor'))),
];

$config['configured'] =
    $config['host'] !== '' &&
    $config['port'] > 0 &&
    in_array($config['encryption'], ['none', 'tls', 'ssl'], true) &&
    $config['username'] !== '' &&
    $config['password'] !== '' &&
    filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) !== false;

return $config;
