<?php
// Settings: the few numbers a Super Admin can tune. Every change is written to the audit log.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['SuperAdmin']);

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $errors = [];
    foreach (SETTINGS as $name => $info) {
        $text = (string) ($_POST[$name] ?? '');
        if ($text !== (string) setting($name) && ($error = saveSetting($user['id'], $name, $text))) {
            $errors[] = $error;
        }
    }
    $_SESSION['flash'] = $errors ? ['error', implode(' ', $errors)] : ['success', 'Settings saved.'];
    redirect('super_admin/settings.php');
}

$pageTitle = 'Settings';
$pageIntro = 'Change how the system behaves. A change applies right away. The default is shown under each setting.';
require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header"><div><h2>System settings</h2><p>Whole numbers only, within the range shown</p></div></div>
    <form method="post" class="stack-form">
        <?= csrf_field() ?>
        <?php foreach (SETTINGS as $name => [$default, $low, $high, $label, $unit, $help]): ?>
        <label for="<?= e($name) ?>"><?= e($label) ?> (<?= e($unit) ?>)</label>
        <input type="number" name="<?= e($name) ?>" id="<?= e($name) ?>" required min="<?= $low ?>" max="<?= $high ?>" step="1" value="<?= setting($name) ?>">
        <p class="panel-note"><?= e($help) ?> Default: <?= $default ?>. Allowed: <?= $low ?> to <?= $high ?>.</p>
        <?php endforeach; ?>
        <button type="submit" class="btn">Save settings</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>