<?php
// Sends one uploaded document to staff. uploads/documents is closed to the web (.htaccess), so this is the only way to see a file,
// and only for a logged-in Admin or SuperAdmin. The file name comes from the database, never from the request.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['Admin', 'SuperAdmin']);

const SERVE_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

$id  = (string) ($_GET['id'] ?? '');
$doc = false;
if (ctype_digit($id)) { // only a plain number is an id
    $stmt = $pdo->prepare('SELECT stored_name, original_name FROM scholar_documents WHERE id = ?');
    $stmt->execute([(int) $id]);
    $doc = $stmt->fetch();
}

$path = $doc ? __DIR__ . '/../uploads/documents/' . basename($doc['stored_name']) : '';
$type = $doc ? (SERVE_TYPES[strtolower(pathinfo($doc['stored_name'], PATHINFO_EXTENSION))] ?? null) : null;
if (!$doc || $type === null || !is_file($path)) {
    http_response_code(404);
    exit('Document not found.');
}

auditLog($user['id'], 'document_viewed', 'file ' . $doc['stored_name']);

// A plain ASCII name for the header; the student's own file name is only a label.
$label = preg_replace('/[^A-Za-z0-9._-]+/', '_', $doc['original_name']) ?: 'document';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $label . '"');
header('X-Content-Type-Options: nosniff'); // the browser must trust the type above, not guess from the contents
header('Cache-Control: private, no-store');
readfile($path);