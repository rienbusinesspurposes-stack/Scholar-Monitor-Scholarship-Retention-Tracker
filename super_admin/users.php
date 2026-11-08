<?php
// Accounts: create staff and student accounts, change roles, switch accounts on or off. Super Admin only.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['SuperAdmin']);
$pageTitle = 'Users';
$pageIntro = 'Create accounts, change roles and switch accounts on or off. A switched-off account is logged out on its next click.';

const ROLE_CHOICES = ['User', 'Admin', 'SuperAdmin'];

// Returns an error message, or null when the account was created.
function createAccount(int $actorId, string $username, string $email, string $fullName, string $password, string $role): ?string
{
    global $pdo;

    $username = trim($username);
    $email = trim($email);
    $fullName = trim($fullName);
    if (!isValidUsername($username)) {
        return 'The username must be 3 to 50 letters, digits, _ or . only.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        return 'Enter a valid email address.';
    }
    if ($fullName === '' || mb_strlen($fullName) > 150 || !mb_check_encoding($fullName, 'UTF-8') || preg_match('/\p{C}/u', $fullName)) {
        return 'Enter the full name (up to 150 characters).';
    }
    if ($problem = passwordProblem($password)) {
        return $problem;
    }
    if (!in_array($role, ROLE_CHOICES, true)) {
        return 'Choose a role.';
    }

    try {
        $pdo->prepare('INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $role]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That username or email is already in use.';
        }
        throw $e;
    }
    auditLog($actorId, 'account_created', "user {$pdo->lastInsertId()} ($username) as $role");
    return null;
}

// You cannot change your own role or switch yourself off, so there is always one active Super Admin left.
// Returns an error message, or null on success.
function changeRole(int $actorId, int $userId, string $role): ?string
{
    global $pdo;
    if ($userId === $actorId) {
        return 'You cannot change your own role.';
    }
    if (!in_array($role, ROLE_CHOICES, true)) {
        return 'Choose a role.';
    }
    $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ? AND role <> ?');
    $stmt->execute([$role, $userId, $role]);
    if ($stmt->rowCount() !== 1) {
        return 'That account was not found or already has that role.';
    }
    auditLog($actorId, 'role_changed', "user $userId now $role");
    return null;
}

function setAccountActive(int $actorId, int $userId, bool $active): ?string
{
    global $pdo;
    if ($userId === $actorId) {
        return 'You cannot switch off your own account.';
    }
    $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ? AND is_active <> ?');
    $stmt->execute([(int) $active, $userId, (int) $active]);
    if ($stmt->rowCount() !== 1) {
        return 'That account was not found or is already ' . ($active ? 'active' : 'switched off') . '.';
    }
    auditLog($actorId, $active ? 'account_activated' : 'account_deactivated', "user $userId");
    return null;
}

enforceCsrfOnPost();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $error = createAccount(
            $user['id'],
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['full_name'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['role'] ?? '')
        );
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Account created. Give the person the password in person; they can change it with Forgot password.'];
    } elseif ($action === 'role') {
        $error = changeRole($user['id'], (int) ($_POST['user_id'] ?? 0), (string) ($_POST['role'] ?? ''));
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Role changed.'];
    } elseif ($action === 'toggle') {
        $error = setAccountActive($user['id'], (int) ($_POST['user_id'] ?? 0), ($_POST['active'] ?? '') === '1');
        $_SESSION['flash'] = $error ? ['error', $error] : ['success', 'Account updated.'];
    }
    redirect('super_admin/users.php');
}

$accounts = $pdo->query(
    "SELECT id, username, email, full_name, role, is_active FROM users
     ORDER BY FIELD(role, 'SuperAdmin', 'Admin', 'User'), full_name LIMIT 300"
)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<section class="content-panel">
    <div class="panel-header"><div><h2>Create an account</h2><p>Students can also sign up themselves on the register page</p></div></div>
    <form method="post" class="stack-form" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <label for="full_name">Full name</label>
        <input type="text" name="full_name" id="full_name" required maxlength="150">

        <label for="username">Username</label>
        <input type="text" name="username" id="username" required maxlength="50" pattern="[A-Za-z0-9_.]{3,50}" title="3 to 50 letters, digits, _ or .">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required maxlength="255">

        <label for="password">Password (10 to 72 characters)</label>
        <input type="password" name="password" id="password" required minlength="10" maxlength="72" autocomplete="new-password">

        <label for="role">Role</label>
        <select name="role" id="role" required>
            <?php foreach (ROLE_CHOICES as $r): ?>
                <option value="<?= $r ?>"><?= $r === 'SuperAdmin' ? 'Super Admin' : $r ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn">Create account</button>
    </form>
</section>

<section class="content-panel panel-gap">
    <div class="panel-header"><div><h2>All accounts</h2><p>Super Admins first, then Admins, then students</p></div></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): $mine = (int) $a['id'] === (int) $user['id']; ?>
                <tr>
                    <td><?= e($a['full_name']) ?><?= $mine ? ' (you)' : '' ?></td>
                    <td><?= e($a['username']) ?></td>
                    <td><?= e($a['email']) ?></td>
                    <td>
                        <?php if ($mine): ?>
                            <?= $a['role'] === 'SuperAdmin' ? 'Super Admin' : e($a['role']) ?>
                        <?php else: ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                            <label class="sr-only" for="role-<?= (int) $a['id'] ?>">Role for <?= e($a['username']) ?></label>
                            <select name="role" id="role-<?= (int) $a['id'] ?>">
                                <?php foreach (ROLE_CHOICES as $r): ?>
                                    <option value="<?= $r ?>"<?= $r === $a['role'] ? ' selected' : '' ?>><?= $r === 'SuperAdmin' ? 'Super Admin' : $r ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-small">Set</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td><span class="req-status <?= $a['is_active'] ? 'is-done' : 'is-pending' ?>"><?= $a['is_active'] ? 'Active' : 'Switched off' ?></span></td>
                    <td>
                        <?php if (!$mine): ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                            <input type="hidden" name="active" value="<?= $a['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="btn btn-small"><?= $a['is_active'] ? 'Switch off' : 'Switch on' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>