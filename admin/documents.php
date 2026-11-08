<?php
// Staff review of uploaded documents: approve, or reject with a reason the student can act on.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Documents';
$pageIntro = 'Review what students uploaded. A rejection needs a reason so the student knows what to fix.';

const NOTE_MAX = 300;

// Approves or rejects one submitted document. $storedName is the file the reviewer was shown, so a student who
// uploads a new file while the page is open cannot get the old file's decision. Returns an error message, or null.
function reviewDocument(int $adminId, int $docId, string $storedName, string $decision, string $note): ?string
{
    global $pdo;

    $note = trim($note);
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return 'Choose Approve or Reject.';
    }
    if ($decision === 'reject' && $note === '') {
        return 'Please say why the document is rejected so the student can fix it.';
    }
    if (mb_strlen($note) > NOTE_MAX) {
        return 'The reason must be ' . NOTE_MAX . ' characters or fewer.';
    }

    $info = $pdo->prepare(
        'SELECT sc.user_id, r.name
         FROM scholar_documents d
         JOIN scholars sc ON sc.id = d.scholar_id
         JOIN document_requirements r ON r.id = d.requirement_id
         WHERE d.id = ?'
    );
    $info->execute([$docId]);
    $doc = $info->fetch();
    if (!$doc) {
        return 'That document was not found.';
    }

    $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';
    $message = $decision === 'approve'
        ? 'Your document "' . $doc['name'] . '" was approved.'
        : 'Your document "' . $doc['name'] . '" was rejected: ' . $note . ' Please upload it again.';
    $message = mb_substr($message, 0, 500); // the notifications column holds 500 characters

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            "UPDATE scholar_documents
             SET status = ?, note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'Submitted' AND stored_name = ?"
        );
        $update->execute([$newStatus, $decision === 'reject' ? $note : null, $adminId, $docId, $storedName]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return 'That document was already reviewed or the student just uploaded a new file. Reload the page.';
        }

        // A second review of a re-uploaded file replaces the old alert and makes it unread again.
        $pdo->prepare(
            "INSERT INTO notifications (user_id, message, dedupe_key) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP"
        )->execute([$doc['user_id'], $message, 'doc_' . $docId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    auditLog($adminId, $decision === 'approve' ? 'document_approved' : 'document_rejected', "document $docId");
    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $error = reviewDocument(
        $user['id'],
        (int) ($_POST['document_id'] ?? 0),
        (string) ($_POST['stored_name'] ?? ''),
        (string) ($_POST['decision'] ?? ''),
        (string) ($_POST['note'] ?? '')
    );
    $_SESSION['flash'] = $error
        ? ['error', $error]
        : ['success', ($_POST['decision'] ?? '') === 'approve' ? 'Document approved. The student was notified.' : 'Document rejected. The student was notified.'];
    redirect('admin/documents.php');
}

// Waiting for review first (oldest first so nobody waits forever), then the rest, newest decision first.
$docs = $pdo->query(
    "SELECT d.id, d.stored_name, d.original_name, d.status, d.note, d.submitted_at, d.reviewed_at,
            u.full_name, sc.student_number, r.name AS requirement_name
     FROM scholar_documents d
     JOIN scholars sc ON sc.id = d.scholar_id
     JOIN users u ON u.id = sc.user_id
     JOIN document_requirements r ON r.id = d.requirement_id
     ORDER BY CASE d.status WHEN 'Submitted' THEN 0 ELSE 1 END, d.submitted_at ASC, d.id ASC
     LIMIT 200"
)->fetchAll();
$waiting = count(array_filter($docs, fn($d) => $d['status'] === 'Submitted'));

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header">
        <div><h2>Uploaded documents</h2><p><?= $waiting > 0 ? $waiting . ' waiting for review' : 'Nothing is waiting for review' ?></p></div>
    </div>

    <?php if (!$docs): ?>
        <p>No documents have been uploaded yet.</p>
    <?php else: ?>
        <?php foreach ($docs as $d):
            $pill = match ($d['status']) { 'Approved' => 'is-done', 'Rejected' => 'is-missing', default => 'is-pending' };
        ?>
        <article class="review-item">
            <div class="review-head">
                <div>
                    <h3><?= e($d['requirement_name']) ?></h3>
                    <p><?= e($d['full_name']) ?> · <?= e($d['student_number']) ?> · uploaded <?= e(date('M j, Y g:i A', strtotime($d['submitted_at']))) ?></p>
                </div>
                <span class="req-status <?= $pill ?>"><?= e($d['status']) ?></span>
            </div>

            <p class="deadline-meta">
                File: <a href="document_file.php?id=<?= (int) $d['id'] ?>" target="_blank" rel="noopener" class="card-link"><?= e($d['original_name']) ?></a>
            </p>

            <?php if ($d['status'] === 'Submitted'): ?>
            <form method="post" class="review-form">
                <?= csrf_field() ?>
                <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                <input type="hidden" name="stored_name" value="<?= e($d['stored_name']) ?>">
                <label class="sr-only" for="note-<?= (int) $d['id'] ?>">Reason (needed to reject)</label>
                <input type="text" id="note-<?= (int) $d['id'] ?>" name="note" maxlength="<?= NOTE_MAX ?>" placeholder="Reason (needed to reject)">
                <button type="submit" name="decision" value="approve" class="btn btn-small">Approve</button>
                <button type="submit" name="decision" value="reject" class="btn btn-small btn-danger">Reject</button>
            </form>
            <?php elseif ($d['status'] === 'Rejected' && $d['note']): ?>
            <p class="deadline-meta document-note">Reason given: <?= e($d['note']) ?></p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>