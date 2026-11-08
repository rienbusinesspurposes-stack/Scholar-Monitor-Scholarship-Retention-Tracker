<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Scholars';
$pageIntro = 'Add students to a scholarship program and verify them.';

// Adds a User-role account as a Pending scholar in a program. Returns an error message, or null on success.
function addScholar(int $adminId, int $userId, int $programId, string $number): ?string
{
    global $pdo;

    $number = trim($number);
    if (!preg_match('/^[A-Za-z0-9-]{3,30}\z/', $number)) {
        return 'The student number must be 3 to 30 letters, digits or dashes.';
    }

    $u = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'User' AND is_active = 1");
    $u->execute([$userId]);
    if (!$u->fetch()) {
        return 'That account was not found, is not a student account, or is deactivated.';
    }

    $p = $pdo->prepare('SELECT id FROM scholarship_programs WHERE id = ? AND is_active = 1');
    $p->execute([$programId]);
    if (!$p->fetch()) {
        return 'That program was not found or is no longer active.';
    }

    $existing = $pdo->prepare('SELECT id FROM scholars WHERE user_id = ?');
    $existing->execute([$userId]);
    if ($existing->fetch()) {
        return 'That student already has a scholar record.';
    }

    try {
        $pdo->prepare(
            "INSERT INTO scholars (user_id, program_id, student_number, status) VALUES (?, ?, ?, 'Pending')"
        )->execute([$userId, $programId, $number]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That student number is already in use.';
        }
        throw $e;
    }

    auditLog($adminId, 'scholar_added', "user $userId, program $programId, number $number");
    return null;
}

// Moves a Pending scholar to Active and records who verified them. Returns an error message, or null on success.
function verifyScholar(int $adminId, int $scholarId): ?string
{
    global $pdo;

    $stmt = $pdo->prepare(
        "UPDATE scholars SET status = 'Active', verified_by = ?, verified_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'Pending'"
    );
    $stmt->execute([$adminId, $scholarId]);
    if ($stmt->rowCount() !== 1) {
        return 'That scholar is not waiting for verification.';
    }

    // A grade may already be on file (re-added scholar); this can move them straight to AtRisk/Ineligible,
    // so the notification must say what the status became, not assume it is still Active.
    $newStatus = refreshScholarStatus($scholarId, $adminId);
    $message = match ($newStatus) {
        'AtRisk'     => 'Your scholarship has been verified. Your current grade is close to the minimum, so keep it up.',
        'Ineligible' => 'Your scholarship has been verified, but your current grade is below the minimum required.',
        default      => 'Your scholarship has been verified and is now active.',
    };

    $pdo->prepare(
        "INSERT INTO notifications (user_id, message, dedupe_key)
         SELECT user_id, ?, CONCAT('verified_', id)
         FROM scholars WHERE id = ?
         ON DUPLICATE KEY UPDATE message = VALUES(message)"
    )->execute([$message, $scholarId]);

    auditLog($adminId, 'scholar_verified', "scholar $scholarId, now $newStatus");
    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $error = addScholar(
            $user['id'],
            (int) ($_POST['user_id'] ?? 0),
            (int) ($_POST['program_id'] ?? 0),
            (string) ($_POST['student_number'] ?? '')
        );
        $_SESSION['flash'] = $error
            ? ['error', $error]
            : ['success', 'Scholar added and is now awaiting verification.'];
    } elseif ($action === 'verify') {
        $error = verifyScholar($user['id'], (int) ($_POST['scholar_id'] ?? 0));
        $_SESSION['flash'] = $error
            ? ['error', $error]
            : ['success', 'Scholar verified.'];
    }
    redirect('admin/scholars.php');
}

// Active User-role accounts that are not already a scholar.
$unassignedUsers = $pdo->query(
    "SELECT id, username, full_name FROM users
     WHERE role = 'User' AND is_active = 1
       AND NOT EXISTS (SELECT 1 FROM scholars s WHERE s.user_id = users.id)
     ORDER BY full_name"
)->fetchAll();

$activePrograms = $pdo->query(
    'SELECT id, name FROM scholarship_programs WHERE is_active = 1 ORDER BY name'
)->fetchAll();

$allScholars = $pdo->query(
    "SELECT sc.id, sc.student_number, sc.status, u.full_name, p.name AS program_name
     FROM scholars sc
     JOIN users u ON u.id = sc.user_id
     JOIN scholarship_programs p ON p.id = sc.program_id
     ORDER BY FIELD(sc.status, 'Pending', 'AtRisk', 'Ineligible', 'Active', 'Ended'), u.full_name"
)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header"><div><h2>Add a scholar</h2><p>Student accounts that are not a scholar yet</p></div></div>
    <?php if (!$unassignedUsers): ?>
        <p>Every active student account already has a scholar record.</p>
    <?php elseif (!$activePrograms): ?>
        <p>There are no active scholarship programs to assign.</p>
    <?php else: ?>
        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <label for="user_id">Student account</label>
            <select name="user_id" id="user_id" required>
                <option value="">Choose a student…</option>
                <?php foreach ($unassignedUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
                <?php endforeach; ?>
            </select>

            <label for="program_id">Program</label>
            <select name="program_id" id="program_id" required>
                <option value="">Choose a program…</option>
                <?php foreach ($activePrograms as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="student_number">Student number</label>
            <input type="text" name="student_number" id="student_number" required maxlength="30"
                   pattern="[A-Za-z0-9\-]{3,30}" title="3 to 30 letters, digits or dashes">

            <button type="submit" class="btn">Add scholar</button>
        </form>
    <?php endif; ?>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>All scholars</h2><p>Pending first</p></div></div>
    <?php if (!$allScholars): ?>
        <p>No scholars yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Student</th><th>Student #</th><th>Program</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allScholars as $row): ?>
                    <tr>
                        <td><a href="scholar.php?id=<?= (int) $row['id'] ?>" class="card-link"><?= e($row['full_name']) ?></a></td>
                        <td><?= e($row['student_number']) ?></td>
                        <td><?= e($row['program_name']) ?></td>
                        <td><span class="req-status <?= match ($row['status']) { 'Active' => 'is-done', 'Ineligible' => 'is-missing', default => 'is-pending' } ?>"><?= $row['status'] === 'AtRisk' ? 'At Risk' : e($row['status']) ?></span></td>
                        <td>
                            <?php if ($row['status'] === 'Pending'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="scholar_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-small">Verify</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>