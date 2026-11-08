<?php
// Deadlines: every required document that has a due date, with where the student stands on it.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = requireRole(['User']);
$pageTitle = 'Deadlines';
require __DIR__ . '/../includes/user_header.php'; // also sets $scholar

$rows = [];
if ($scholar) {
    // Documents still to do come first, finished ones (submitted or approved) after, each group by due date.
    $stmt = $pdo->prepare(
        "SELECT r.name, r.due_date, d.status
         FROM document_requirements r
         LEFT JOIN scholar_documents d ON d.requirement_id = r.id AND d.scholar_id = ?
         WHERE r.program_id = ? AND r.is_active = 1 AND r.due_date IS NOT NULL
         ORDER BY COALESCE(d.status IN ('Submitted', 'Approved'), 0), r.due_date, r.name"
    );
    $stmt->execute([$scholar['id'], $scholar['program_id']]);
    $rows = $stmt->fetchAll();
}
$today = new DateTimeImmutable('today');
?>

                <div class="page-header">
                    <p class="section-label">BALUQ: Student Portal</p>
                    <h1>Deadlines</h1>
                    <p>Upcoming scholarship submission dates.</p>
                </div>

                <section class="content-panel">

                    <?php foreach ($rows as $row):
                        $due  = new DateTimeImmutable($row['due_date']);
                        $days = (int) $today->diff($due)->format('%r%a');
                        if ($row['status'] === 'Approved') {
                            [$tag, $tagClass] = ['Approved', 'is-done'];
                        } elseif ($row['status'] === 'Submitted') {
                            [$tag, $tagClass] = ['Submitted, waiting for review', 'is-done'];
                        } elseif ($row['status'] === 'Rejected') {
                            [$tag, $tagClass] = ['Rejected, upload again', 'is-urgent'];
                        } elseif ($days < 0) {
                            [$tag, $tagClass] = ['Overdue by ' . -$days . ($days === -1 ? ' day' : ' days'), 'is-urgent'];
                        } elseif ($days === 0) {
                            [$tag, $tagClass] = ['Due today', 'is-urgent'];
                        } else {
                            [$tag, $tagClass] = ['Due in ' . $days . ($days === 1 ? ' day' : ' days'), $days <= 7 ? 'is-urgent' : ''];
                        }
                    ?>
                    <article class="deadline-item">
                        <div class="deadline-date">
                            <strong><?= $due->format('d') ?></strong>
                            <span><?= strtoupper($due->format('M')) ?></span>
                        </div>
                        <div class="deadline-details">
                            <h3><?= e($row['name']) ?></h3>
                            <p>Required document · due <?= e($due->format('F j, Y')) ?></p>
                        </div>
                        <span class="deadline-days <?= $tagClass ?>"><?= $tag ?></span>
                    </article>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><p>No deadlines right now.</p><?php endif; ?>

                </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>