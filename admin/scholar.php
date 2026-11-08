<?php
// One scholar in full: standing, grade history and documents, plus ending or reinstating the scholarship.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $scholarId = (int) ($_POST['scholar_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'end') {
        $error = endScholarship($scholarId, (string) ($_POST['reason'] ?? ''), $user['id']);
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Scholarship ended. The student was notified.'];
    } elseif (($_POST['action'] ?? '') === 'reinstate') {
        $error = reinstateScholarship($scholarId, $user['id']);
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Scholarship reinstated. The student was notified.'];
    }
    redirect('admin/scholar.php?id=' . $scholarId);
}

$scholarId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT sc.id, sc.student_number, sc.status, sc.verified_at, sc.ended_at, sc.ended_reason,
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