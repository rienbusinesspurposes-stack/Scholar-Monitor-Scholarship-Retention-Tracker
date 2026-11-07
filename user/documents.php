<?php
// My Documents: the requirements of the student's program, their review status, and the upload form.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const UPLOAD_TYPES     = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

// Returns an error message, or null when the file was saved. The type comes from the file's contents, never its name.
function saveDocument(array $scholar, int $userId, int $requirementId, ?array $file): ?string
{
    global $pdo;
    if ($scholar['status'] === 'Ended') {
        return 'Your scholarship has ended, so documents can no longer be submitted.';
    }
    $stmt = $pdo->prepare('SELECT id FROM document_requirements WHERE id = ? AND program_id = ?');
    $stmt->execute([$requirementId, $scholar['program_id']]);
    if (!$stmt->fetch()) {
        return 'Choose one of the listed requirements.';
    }
    $stmt = $pdo->prepare('SELECT id, stored_name, status FROM scholar_documents WHERE scholar_id = ? AND requirement_id = ?');
    $stmt->execute([$scholar['id'], $requirementId]);
    $existing = $stmt->fetch() ?: null;
    if ($existing && $existing['status'] === 'Approved') {
        return 'That document was already approved.';
    }

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Choose a file first.';
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAX_UPLOAD_BYTES) {
        return 'That file is too large. The limit is 5 MB.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return 'The upload did not finish. Please try again.';
    }
    $ext = UPLOAD_TYPES[(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])] ?? null;
    if ($ext === null) {
        return 'Only PDF, JPG or PNG files are allowed.';
    }

    $dir    = __DIR__ . '/../uploads/documents/';
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $stored)) {
        error_log('Scholar Monitor: could not move an uploaded document into uploads/documents');
        return 'The file could not be saved. Please try again later.';
    }
    $original = mb_substr(basename(str_replace('\\', '/', (string) $file['name'])), 0, 255);

    try {
        if ($existing) {
            $pdo->prepare(
                "UPDATE scholar_documents
                 SET stored_name = ?, original_name = ?, status = 'Submitted', note = NULL,
                     reviewed_by = NULL, reviewed_at = NULL, submitted_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            )->execute([$stored, $original, $existing['id']]);
            @unlink($dir . basename($existing['stored_name']));
        } else {
            $pdo->prepare('INSERT INTO scholar_documents (scholar_id, requirement_id, stored_name, original_name) VALUES (?, ?, ?, ?)')
                ->execute([$scholar['id'], $requirementId, $stored, $original]);
        }
    } catch (PDOException $e) {
        @unlink($dir . $stored);
        error_log('Scholar Monitor document save failed: ' . $e->getMessage());
        return 'The document could not be saved. Please try again later.';
    }
    auditLog($userId, 'document_uploaded', "requirement $requirementId");
    return null;
}

$user = requireRole(['User']);

// A file over post_max_size arrives as an empty POST without the token, so say so instead of showing the token error.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['flash'] = ['error', 'That file is too large. The limit is 5 MB.'];
    redirect('user/documents.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfOnPost();
    $stmt = $pdo->prepare('SELECT id, program_id, status FROM scholars WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $mine  = $stmt->fetch();
    $error = $mine
        ? saveDocument($mine, (int) $user['id'], (int) ($_POST['requirement_id'] ?? 0), $_FILES['document'] ?? null)
        : 'No scholarship record is linked to your account yet.';
    $_SESSION['flash'] = $error === null ? ['success', 'Document uploaded. Staff will review it.'] : ['error', $error];
    redirect('user/documents.php'); // reload as a GET so a refresh does not upload again
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'My Documents';
$pageScript = 'documents.js'; // shows the chosen file name next to the upload button
require __DIR__ . '/../includes/user_header.php'; // also sets $scholar and $unread

$rows = [];
if ($scholar) {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.name, r.description, r.due_date, d.status, d.original_name, d.note
         FROM document_requirements r
         LEFT JOIN scholar_documents d ON d.requirement_id = r.id AND d.scholar_id = ?
         WHERE r.program_id = ?
         ORDER BY r.due_date IS NULL, r.due_date, r.name'
    );
    $stmt->execute([$scholar['id'], $scholar['program_id']]);
    $rows = $stmt->fetchAll();
}
$uploadable = $scholar && $scholar['status'] !== 'Ended'
    ? array_filter($rows, fn($row) => $row['status'] !== 'Approved')
    : [];
// status => [badge text, badge class]
$badge = [
    null        => ['Missing', 'is-missing'],
    'Submitted' => ['Submitted', 'is-pending'],
    'Approved'  => ['Approved', 'is-done'],
    'Rejected'  => ['Rejected', 'is-missing'],
];
?>

                <div class="page-header">
                    <p class="section-label">BALUQ: Student Portal</p>
                    <h1>My Documents</h1>
                    <p>Upload and track your scholarship requirements.</p>
                </div>

                <?php if ($flash): ?>
                <p class="form-message <?= $flash[0] === 'success' ? 'success' : 'error' ?>" role="alert"><?= e($flash[1]) ?></p>
                <?php endif; ?>

                <div class="two-col">

                    <section class="content-panel">
                        <div class="panel-header"><div><h2>Upload a Document</h2><p>PDF, JPG or PNG, up to 5 MB. Uploading again replaces a document that is not yet approved.</p></div></div>

                        <?php if ($uploadable): ?>
                        <form method="post" enctype="multipart/form-data" class="upload-form">
                            <?= csrf_field() ?>
                            <label for="requirement_id">Requirement</label>
                            <select name="requirement_id" id="requirement_id" required>
                                <?php foreach ($uploadable as $row): ?>
                                <option value="<?= (int) $row['id'] ?>"><?= e($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="btn" for="document-upload">Choose File</label>
                            <input type="file" id="document-upload" name="document" class="sr-only" accept=".pdf,.jpg,.jpeg,.png" required>
                            <p id="file-name" class="deadline-meta">No file selected</p>
                            <button type="submit" class="btn">Upload</button>
                        </form>
                        <?php elseif ($rows): ?>
                        <p>Nothing left to upload.</p>
                        <?php else: ?>
                        <p>No documents are required right now.</p>
                        <?php endif; ?>
                    </section>

                    <section class="content-panel">
                        <div class="panel-header"><div><h2>Submission Status</h2><p>Required documents for your scholarship</p></div></div>

                        <?php foreach ($rows as $row):
                            [$label, $class] = $badge[$row['status']];
                        ?>
                        <div class="req-row"><span><?= e($row['name']) ?><?= $row['due_date'] ? ' · due ' . e(date('M j, Y', strtotime($row['due_date']))) : '' ?></span><span class="req-status <?= $class ?>"><?= $label ?></span></div>
                        <?php if ($row['original_name']): ?><p class="deadline-meta">File: <?= e($row['original_name']) ?></p><?php endif; ?>
                        <?php if ($row['status'] === 'Rejected' && $row['note']): ?><p class="deadline-meta document-note">Staff note: <?= e($row['note']) ?></p><?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><p>No documents are required right now.</p><?php endif; ?>
                    </section>

                </div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>