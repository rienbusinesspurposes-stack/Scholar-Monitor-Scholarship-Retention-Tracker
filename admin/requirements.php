<?php
// Document requirements: what each program asks students to upload, and by when.
// A requirement is switched off, never deleted, so documents students already uploaded for it are kept.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Requirements';
$pageIntro = 'The documents each program asks students to upload. Switched-off requirements disappear from student pages.';

// Adds a requirement ($id 0) or changes one. $due is empty or a date as YYYY-MM-DD. Returns an error message, or null on success.
function saveRequirement(int $adminId, int $programId, int $id, string $name, string $description, string $due): ?string
{
    global $pdo;

    $name = trim($name);
    $description = trim($description);
    if ($name === '' || mb_strlen($name) > 150) {
        return 'The name must be 1 to 150 characters.';
    }
    if (mb_strlen($description) > 500) {
        return 'The description must be 500 characters or fewer.';
    }
    if ($due !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
        if (!$date || $date->format('Y-m-d') !== $due) {
            return 'The due date must be a real date.';
        }
    }

    $stmt = $pdo->prepare('SELECT id FROM scholarship_programs WHERE id = ?');
    $stmt->execute([$programId]);
    if (!$stmt->fetch()) {
        return 'That program was not found.';
    }

    $values = [$name, $description === '' ? null : $description, $due === '' ? null : $due];
    try {
        if ($id === 0) {
            $pdo->prepare('INSERT INTO document_requirements (program_id, name, description, due_date) VALUES (?, ?, ?, ?)')
                ->execute([$programId, ...$values]);
            auditLog($adminId, 'requirement_added', "program $programId: $name");
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM document_requirements WHERE id = ? AND program_id = ?');
        $stmt->execute([$id, $programId]);
        if (!$stmt->fetch()) {
            return 'That requirement was not found in this program.';
        }
        $pdo->prepare('UPDATE document_requirements SET name = ?, description = ?, due_date = ? WHERE id = ?')
            ->execute([...$values, $id]);
        auditLog($adminId, 'requirement_changed', "requirement $id: $name");
        return null;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'This program already has a requirement with that name. It may be switched off in the list below.';
        }
        throw $e;
    }
}

// Switches a requirement on or off for its program. Returns an error message, or null on success.
function setRequirementActive(int $adminId, int $programId, int $id, bool $active): ?string
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE document_requirements SET is_active = ? WHERE id = ? AND program_id = ? AND is_active <> ?');
    $stmt->execute([(int) $active, $id, $programId, (int) $active]);
    if ($stmt->rowCount() !== 1) {
        return 'That requirement was not found or is already ' . ($active ? 'active' : 'switched off') . '.';
    }
    auditLog($adminId, $active ? 'requirement_activated' : 'requirement_deactivated', "requirement $id");
    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $programId = (int) ($_POST['program_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'save') {
        $error = saveRequirement(
            $user['id'],
            $programId,
            (int) ($_POST['requirement_id'] ?? 0),
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['description'] ?? ''),
            (string) ($_POST['due_date'] ?? '')
        );
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Requirement saved.'];
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $error = setRequirementActive($user['id'], $programId, (int) ($_POST['requirement_id'] ?? 0), ($_POST['active'] ?? '') === '1');
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Requirement updated.'];
    }
    redirect('admin/requirements.php?program=' . $programId);
}

$programs = $pdo->query('SELECT id, name FROM scholarship_programs ORDER BY name')->fetchAll();
$programId = (int) ($_GET['program'] ?? 0);
if (!in_array($programId, array_map('intval', array_column($programs, 'id')), true)) {
    $programId = $programs ? (int) $programs[0]['id'] : 0;
}

$requirements = [];
$editing = null;
if ($programId) {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.name, r.description, r.due_date, r.is_active,
                (SELECT COUNT(*) FROM scholar_documents d WHERE d.requirement_id = r.id) AS uploads
         FROM document_requirements r
         WHERE r.program_id = ?
         ORDER BY r.is_active DESC, r.due_date IS NULL, r.due_date, r.name'
    );
    $stmt->execute([$programId]);
    $requirements = $stmt->fetchAll();
    foreach ($requirements as $r) {
        if ((int) $r['id'] === (int) ($_GET['edit'] ?? 0)) {
            $editing = $r;
        }
    }
}

require __DIR__ . '/../includes/admin_header.php';
?>

<?php if (!$programs): ?>
<section class="content-panel">
    <p>There are no programs yet. <a href="programs.php" class="card-link">Add a program first →</a></p>
</section>
<?php else: ?>

<section class="content-panel">
    <div class="panel-header"><div><h2>Program</h2><p>Requirements belong to one program at a time</p></div></div>
    <form method="get" class="stack-form">
        <label for="program">Show the requirements of</label>
        <select name="program" id="program">
            <?php foreach ($programs as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $programId ? ' selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Show</button>
    </form>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2><?= $editing ? 'Edit requirement' : 'Add a requirement' ?></h2><p>The due date is optional</p></div></div>
    <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="program_id" value="<?= $programId ?>">
        <input type="hidden" name="requirement_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

        <label for="name">Name</label>
        <input type="text" name="name" id="name" required maxlength="150" value="<?= e($editing['name'] ?? '') ?>">

        <label for="description">Description (optional)</label>
        <input type="text" name="description" id="description" maxlength="500" value="<?= e($editing['description'] ?? '') ?>">

        <label for="due_date">Due date (optional)</label>
        <input type="date" name="due_date" id="due_date" value="<?= e($editing['due_date'] ?? '') ?>">

        <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add requirement' ?></button>
        <?php if ($editing): ?><a href="requirements.php?program=<?= $programId ?>" class="card-link">Cancel</a><?php endif; ?>
    </form>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>Requirements</h2><p>Switched-off ones are kept with their uploads</p></div></div>
    <?php if (!$requirements): ?>
        <p>This program has no requirements yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Requirement</th><th>Due</th><th>Uploads</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?><?php if ($r['description']): ?><br><span class="deadline-meta"><?= e($r['description']) ?></span><?php endif; ?></td>
                        <td><?= $r['due_date'] ? e(date('M j, Y', strtotime($r['due_date']))) : '—' ?></td>
                        <td><?= (int) $r['uploads'] ?></td>
                        <td><span class="req-status <?= $r['is_active'] ? 'is-done' : 'is-pending' ?>"><?= $r['is_active'] ? 'Active' : 'Switched off' ?></span></td>
                        <td>
                            <a href="requirements.php?program=<?= $programId ?>&amp;edit=<?= (int) $r['id'] ?>" class="card-link">Edit</a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="program_id" value="<?= $programId ?>">
                                <input type="hidden" name="requirement_id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="active" value="<?= $r['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-small"><?= $r['is_active'] ? 'Switch off' : 'Switch on' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>