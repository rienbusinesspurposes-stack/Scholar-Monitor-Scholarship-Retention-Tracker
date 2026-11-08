<?php
// Scholarship programs: the grade rule each one asks for, and whether new scholars can still be added to it.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['Admin', 'SuperAdmin']);
$pageTitle = 'Programs';
$pageIntro = 'The minimum average and warning margin that decide each scholar\'s status. Changing them recalculates everyone in the program.';

// Adds a program ($id 0) or changes one. Returns an error message, or null on success.
function saveProgram(int $adminId, int $id, string $name, string $description, string $minGrade, string $margin): ?string
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
    foreach ([$minGrade, $margin] as $number) {
        if (!is_numeric($number) || (float) $number < 0 || (float) $number > 100) {
            return 'The minimum average and the warning margin must be numbers from 0 to 100.';
        }
    }
    $minGrade = number_format((float) $minGrade, 2, '.', '');
    $margin   = number_format((float) $margin, 2, '.', '');

    try {
        if ($id === 0) {
            $pdo->prepare('INSERT INTO scholarship_programs (name, description, min_grade, warning_margin) VALUES (?, ?, ?, ?)')
                ->execute([$name, $description === '' ? null : $description, $minGrade, $margin]);
            auditLog($adminId, 'program_added', "program {$pdo->lastInsertId()}: $name");
            return null;
        }

        $old = $pdo->prepare('SELECT min_grade, warning_margin FROM scholarship_programs WHERE id = ?');
        $old->execute([$id]);
        $before = $old->fetch();
        if (!$before) {
            return 'That program was not found.';
        }
        $pdo->prepare('UPDATE scholarship_programs SET name = ?, description = ?, min_grade = ?, warning_margin = ? WHERE id = ?')
            ->execute([$name, $description === '' ? null : $description, $minGrade, $margin, $id]);
        auditLog($adminId, 'program_changed', "program $id: $name, minimum $minGrade, margin $margin");

        if ($before['min_grade'] !== $minGrade || $before['warning_margin'] !== $margin) {
            // shortcut: one status check per scholar, upgrade to a single UPDATE if a program ever holds thousands.
            $ids = $pdo->prepare('SELECT id FROM scholars WHERE program_id = ?');
            $ids->execute([$id]);
            foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $scholarId) {
                refreshScholarStatus((int) $scholarId, $adminId);
            }
        }
        return null;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'A program with that name already exists.';
        }
        throw $e;
    }
}

// Switches a program on or off. Off means no new scholars can be added; the scholars already in it are untouched.
function setProgramActive(int $adminId, int $id, bool $active): ?string
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE scholarship_programs SET is_active = ? WHERE id = ? AND is_active <> ?');
    $stmt->execute([(int) $active, $id, (int) $active]);
    if ($stmt->rowCount() !== 1) {
        return 'That program was not found or is already ' . ($active ? 'active' : 'switched off') . '.';
    }
    auditLog($adminId, $active ? 'program_activated' : 'program_deactivated', "program $id");
    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (($_POST['action'] ?? '') === 'save') {
        $error = saveProgram(
            $user['id'],
            (int) ($_POST['program_id'] ?? 0),
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['description'] ?? ''),
            (string) ($_POST['min_grade'] ?? ''),
            (string) ($_POST['warning_margin'] ?? '')
        );
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Program saved.'];
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $error = setProgramActive($user['id'], (int) ($_POST['program_id'] ?? 0), ($_POST['active'] ?? '') === '1');
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Program updated.'];
    }
    redirect('admin/programs.php');
}

$programs = $pdo->query(
    'SELECT p.id, p.name, p.description, p.min_grade, p.warning_margin, p.is_active,
            (SELECT COUNT(*) FROM scholars s WHERE s.program_id = p.id) AS scholar_count
     FROM scholarship_programs p
     ORDER BY p.is_active DESC, p.name'
)->fetchAll();

$editing = null;
foreach ($programs as $p) {
    if ((int) $p['id'] === (int) ($_GET['edit'] ?? 0)) {
        $editing = $p;
    }
}

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header"><div><h2><?= $editing ? 'Edit program' : 'Add a program' ?></h2><p>Grades are averages from 0 to 100; a scholar is At Risk when within the margin above the minimum</p></div></div>
    <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="program_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

        <label for="name">Name</label>
        <input type="text" name="name" id="name" required maxlength="150" value="<?= e($editing['name'] ?? '') ?>">

        <label for="description">Description (optional)</label>
        <input type="text" name="description" id="description" maxlength="500" value="<?= e($editing['description'] ?? '') ?>">

        <label for="min_grade">Minimum average to keep</label>
        <input type="number" name="min_grade" id="min_grade" required min="0" max="100" step="0.01" value="<?= e($editing['min_grade'] ?? '') ?>">

        <label for="warning_margin">Warning margin (points above the minimum)</label>
        <input type="number" name="warning_margin" id="warning_margin" required min="0" max="100" step="0.01" value="<?= e($editing['warning_margin'] ?? '3.00') ?>">

        <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add program' ?></button>
        <?php if ($editing): ?><a href="programs.php" class="card-link">Cancel</a><?php endif; ?>
    </form>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>All programs</h2><p>Switched-off programs cannot get new scholars</p></div></div>
    <?php if (!$programs): ?>
        <p>No programs yet. Add one above.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Program</th><th>Minimum</th><th>Margin</th><th>Scholars</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['min_grade']) ?></td>
                        <td><?= e($p['warning_margin']) ?></td>
                        <td><?= (int) $p['scholar_count'] ?></td>
                        <td><span class="req-status <?= $p['is_active'] ? 'is-done' : 'is-pending' ?>"><?= $p['is_active'] ? 'Active' : 'Switched off' ?></span></td>
                        <td>
                            <a href="programs.php?edit=<?= (int) $p['id'] ?>" class="card-link">Edit</a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="active" value="<?= $p['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-small"><?= $p['is_active'] ? 'Switch off' : 'Switch on' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>