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
         WHERE r.program_id = ? AND r.due_date IS NOT NULL
         ORDER BY COALESCE(d.status IN ('Submitted', 'Approved'), 0), r.due_date, r.name"
    );
    $stmt->execute([$scholar['id'], $scholar['program_id']]);
    $rows = $stmt->fetchAll();
}
$today = new DateTimeImmutable('today');
?>

            <section class="dashboard-content">

                <section class="content-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Your Deadlines</h2>
                            <p>Required documents with a due date</p>
                        </div>
                        <a href="documents.php">My Documents</a>
                    </div>

                    <?php foreach ($rows as $row):
                        $due  = new DateTimeImmutable($row['due_date']);
                        $days = (int) $today->diff($due)->format('%r%a');
                        if ($row['status'] === 'Approved') {
                            [$tag, $tagClass] = ['Approved', 'deadline-normal'];
                        } elseif ($row['status'] === 'Submitted') {
                            [$tag, $tagClass] = ['Submitted, waiting for review', 'deadline-normal'];
                        } elseif ($row['status'] === 'Rejected') {
                            [$tag, $tagClass] = ['Rejected, please upload again', 'deadline-warning'];
                        } elseif ($days < 0) {
                            [$tag, $tagClass] = ['Overdue', 'deadline-warning'];
                        } elseif ($days <= 7) {
                            [$tag, $tagClass] = ['Due soon', 'deadline-warning'];
                        } else {
                            [$tag, $tagClass] = ['Upcoming', 'deadline-normal'];
                        }
                    ?>
                    <article class="deadline-item">
                        <div class="deadline-date">
                            <strong><?= $due->format('d') ?></strong>
                            <span><?= strtoupper($due->format('M')) ?></span>
                        </div>
                        <div class="deadline-details">
                            <h3><?= e($row['name']) ?></h3>
                            <p>Required document, due <?= e($due->format('F j, Y')) ?></p>
                            <span class="<?= $tagClass ?>"><?= $tag ?></span>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><p>No deadlines right now.</p><?php endif; ?>

                </section>

            </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>