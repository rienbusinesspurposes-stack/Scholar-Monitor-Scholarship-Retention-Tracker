<?php
// One scholar in full: standing, grade history and documents, plus editing details and ending or reinstating the scholarship.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);

// Changes a scholar's student number and/or program. Returns an error message, or null on success.
// Documents already uploaded stay linked to the old program's requirements; the new program's list starts empty.
function updateScholar(int $scholarId, int $programId, string $number, int $actorId): ?string
{
    global $pdo;

    $number = trim($number);
    if (!preg_match('/^[A-Za-z0-9-]{3,30}\z/', $number)) {
        return 'The student number must be 3 to 30 letters, digits or dashes.';
    }

    $stmt = $pdo->prepare('SELECT program_id, student_number FROM scholars WHERE id = ?');
    $stmt->execute([$scholarId]);
    $before = $stmt->fetch();
    if (!$before) {
        return 'That scholar was not found.';
    }
    $moved = (int) $before['program_id'] !== $programId;
    if (!$moved && $before['student_number'] === $number) {
        return 'Nothing was changed.';
    }

    if ($moved) {
        $stmt = $pdo->prepare('SELECT name FROM scholarship_programs WHERE id = ? AND is_active = 1');
        $stmt->execute([$programId]);
        $newProgram = $stmt->fetchColumn();
        if ($newProgram === false) {
            return 'That program was not found or is no longer active.';
        }
    }

    try {
        $pdo->prepare('UPDATE scholars SET program_id = ?, student_number = ? WHERE id = ?')
            ->execute([$programId, $number, $scholarId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That student number is already in use.';
        }
        throw $e;
    }
    auditLog($actorId, 'scholar_updated', "scholar $scholarId: number {$before['student_number']} to $number, program {$before['program_id']} to $programId");

    if ($moved) {
        // The new program has its own grade rule, so the status is checked again.
        refreshScholarStatus($scholarId, $actorId);
        notifyScholar($scholarId, 'program_changed', "Your scholarship program is now $newProgram. Check My Documents for its requirements.");
    }
    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $scholarId = (int) ($_POST['scholar_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'end') {
        $error = endScholarship($scholarId, (string) ($_POST['reason'] ?? ''), $user['id']);
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Scholarship ended. The student was notified.'];
    } elseif (($_POST['action'] ?? '') === 'reinstate') {
        $error = reinstateScholarship($scholarId, $user['id']);
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Scholarship reinstated. The student was notified.'];
    } elseif (($_POST['action'] ?? '') === 'update') {
        $error = updateScholar($scholarId, (int) ($_POST['program_id'] ?? 0), (string) ($_POST['student_number'] ?? ''), $user['id']);
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Details saved.'];
    }
    redirect('admin/scholar.php?id=' . $scholarId);
}

$scholarId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT sc.id, sc.program_id, sc.student_number, sc.status, sc.verified_at, sc.ended_at, sc.ended_reason,
            u.full_name, u.email, p.name AS program_name, p.min_grade, p.warning_margin, v.full_name AS verifier
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     JOIN scholarship_programs p ON p.id = sc.program_id
     LEFT JOIN users v ON v.id = sc.verified_by
     WHERE sc.id = ?'
);
$stmt->execute([$scholarId]);
$scholar = $stmt->fetch();
if (!$scholar) {
    $_SESSION['flash'] = ['error', 'That scholar was not found.'];
    redirect('admin/scholars.php');
}

// Active programs, plus the scholar's own program even if it was switched off since.
$stmt = $pdo->prepare('SELECT id, name FROM scholarship_programs WHERE is_active = 1 OR id = ? ORDER BY name');
$stmt->execute([$scholar['program_id']]);
$programs = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT term, average, created_at FROM grade_records WHERE scholar_id = ? ORDER BY id DESC');
$stmt->execute([$scholarId]);
$grades = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT d.id, d.original_name, d.status, d.submitted_at, r.name AS requirement_name
     FROM scholar_documents d
     JOIN document_requirements r ON r.id = d.requirement_id
     WHERE d.scholar_id = ?
     ORDER BY d.submitted_at DESC'
);
$stmt->execute([$scholarId]);
$documents = $stmt->fetchAll();

$pageTitle = $scholar['full_name'];
$pageIntro = $scholar['student_number'] . ' · ' . $scholar['program_name'];
$status = $scholar['status'];
$pill = match ($status) { 'Active' => 'is-done', 'Ineligible' => 'is-missing', default => 'is-pending' };
$when = fn(?string $time) => $time ? e(date('M j, Y g:i A', strtotime($time))) : '—';

require __DIR__ . '/../includes/admin_header.php';
?>

<p><a href="scholars.php" class="card-link">← All scholars</a></p>

<div class="two-col">

    <section class="content-panel">
        <div class="panel-header"><div><h2>Standing</h2><p>Set by the program's grade rule</p></div></div>
        <div class="req-row"><span>Status</span><span class="req-status <?= $pill ?>"><?= e(STATUS_LABEL[$status]) ?></span></div>
        <div class="req-row"><span>Email</span><span><?= e($scholar['email']) ?></span></div>
        <div class="req-row"><span>Minimum average</span><span><?= e($scholar['min_grade']) ?></span></div>
        <div class="req-row"><span>Warning margin</span><span><?= e($scholar['warning_margin']) ?></span></div>
        <div class="req-row"><span>Latest average</span><span><?= $grades ? e($grades[0]['average']) : '—' ?></span></div>
        <div class="req-row"><span>Verified</span><span><?= $scholar['verified_at'] ? $when($scholar['verified_at']) . ' by ' . e($scholar['verifier'] ?? 'a removed account') : 'Not yet' ?></span></div>
        <?php if ($status === 'Ended'): ?>
        <div class="req-row"><span>Ended</span><span><?= $when($scholar['ended_at']) ?></span></div>
        <p class="deadline-meta document-note">Reason: <?= e($scholar['ended_reason'] ?? 'not recorded') ?></p>
        <?php endif; ?>
    </section>

    <section class="content-panel">
        <?php if ($status !== 'Ended'): ?>
        <div class="panel-header"><div><h2>End scholarship</h2><p>The student is told and can no longer upload documents or get grades recorded</p></div></div>
        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="end">
            <input type="hidden" name="scholar_id" value="<?= (int) $scholar['id'] ?>">
            <label for="reason">Reason</label>
            <input type="text" name="reason" id="reason" required maxlength="300">
            <button type="submit" class="btn btn-danger" onclick="return confirm('End this scholarship? The student will be notified.')">End scholarship</button>
        </form>
        <?php else: ?>
        <div class="panel-header"><div><h2>Reinstate scholarship</h2><p>Use this if the scholarship was ended by mistake. The grade rule is applied again straight away</p></div></div>
        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reinstate">
            <input type="hidden" name="scholar_id" value="<?= (int) $scholar['id'] ?>">
            <button type="submit" class="btn">Reinstate scholarship</button>
        </form>
        <?php endif; ?>
    </section>

</div>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>Edit details</h2><p>Fix a wrong student number or move the scholar to another program. A move applies the new program's grade rule and document list, and the student is told</p></div></div>
    <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="scholar_id" value="<?= (int) $scholar['id'] ?>">

        <label for="student_number">Student number</label>
        <input type="text" name="student_number" id="student_number" required maxlength="30"
               pattern="[A-Za-z0-9\-]{3,30}" title="3 to 30 letters, digits or dashes" value="<?= e($scholar['student_number']) ?>">

        <label for="program_id">Program</label>
        <select name="program_id" id="program_id" required>
            <?php foreach ($programs as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) $scholar['program_id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn">Save details</button>
    </form>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>Grade history</h2><p>Newest first</p></div></div>
    <?php if (!$grades): ?>
        <p>No grades recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Term</th><th>Average</th><th>Recorded</th></tr></thead>
                <tbody>
                    <?php foreach ($grades as $g): ?>
                    <tr><td><?= e($g['term']) ?></td><td><?= e($g['average']) ?></td><td><?= $when($g['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>Documents</h2><p><a href="documents.php" class="card-link">Review queue →</a></p></div></div>
    <?php if (!$documents): ?>
        <p>No documents uploaded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Requirement</th><th>File</th><th>Status</th><th>Uploaded</th></tr></thead>
                <tbody>
                    <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?= e($d['requirement_name']) ?></td>
                        <td><a href="document_file.php?id=<?= (int) $d['id'] ?>" target="_blank" rel="noopener" class="card-link"><?= e($d['original_name']) ?></a></td>
                        <td><span class="req-status <?= match ($d['status']) { 'Approved' => 'is-done', 'Rejected' => 'is-missing', default => 'is-pending' } ?>"><?= e($d['status']) ?></span></td>
                        <td><?= $when($d['submitted_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>