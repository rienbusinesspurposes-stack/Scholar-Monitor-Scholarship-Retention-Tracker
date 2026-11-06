<?php
// Student dashboard: status, latest grade, missing documents, next deadlines and recent alerts for the logged-in User.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['User']);
$pageTitle = 'Student Portal';
require __DIR__ . '/../includes/user_header.php'; // also sets $scholar and $unread

$status  = 'None';
$gwa     = null;
$missing = [];
if ($scholar) {
    $status = refreshScholarStatus((int) $scholar['id'], (int) $user['id']) ?? 'None';

    $stmt = $pdo->prepare('SELECT average FROM grade_records WHERE scholar_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$scholar['id']]);
    $gwa = $stmt->fetchColumn(); // false when no grade is recorded yet

    // A requirement counts as missing until a file is submitted, and again if staff rejected it.
    $stmt = $pdo->prepare(
        "SELECT r.name, r.due_date
         FROM document_requirements r
         LEFT JOIN scholar_documents d ON d.requirement_id = r.id AND d.scholar_id = ?
         WHERE r.program_id = ? AND (d.id IS NULL OR d.status = 'Rejected')
         ORDER BY r.due_date IS NULL, r.due_date"
    );
    $stmt->execute([$scholar['id'], $scholar['program_id']]);
    $missing = $stmt->fetchAll();
}
$dated = array_values(array_filter($missing, fn($row) => $row['due_date'] !== null));

$stmt = $pdo->prepare('SELECT message, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 2');
$stmt->execute([$user['id']]);
$alerts = $stmt->fetchAll();

$statusLabel = ['None' => 'No Record', 'Pending' => 'Pending', 'Active' => 'Active', 'AtRisk' => 'At Risk', 'Ineligible' => 'Ineligible', 'Ended' => 'Ended'];
$statusText  = [
    'None'       => 'No scholarship record is linked to your account yet.',
    'Pending'    => 'Waiting for staff to verify your scholarship.',
    'Active'     => 'Your scholarship is currently active.',
    'AtRisk'     => 'Your grade is close to the minimum. Keep it up.',
    'Ineligible' => 'Your grade is below the minimum for your scholarship.',
    'Ended'      => 'Your scholarship has ended.',
];
$today = new DateTimeImmutable('today');
?>

            <section class="dashboard-content">

                <!-- Welcome -->
                <section class="welcome-section">

                    <div>
                        <p class="section-label">STUDENT DASHBOARD</p>
                        <h1 id="welcome-message"></h1>
                        <p>
                            Here's an overview of your scholarship
                            information and current requirements.
                        </p>
                    </div>

                    <div class="academic-year">
                        <span>ACADEMIC YEAR</span>
                        <strong>2026–2027</strong>
                    </div>

                </section>


                <!-- Summary cards -->
                <section class="summary-section">

                    <h2 class="section-title">Scholarship Overview</h2>

                    <div class="summary-grid">

                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Scholarship Status</span>
                                <span class="card-icon" aria-hidden="true">🎖</span>
                            </div>
                            <h2><span class="<?= $status === 'Active' ? 'status-active' : 'status-warning' ?>"><?= e($statusLabel[$status]) ?></span></h2>
                            <p><?= e($statusText[$status]) ?></p>
                        </article>

                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Current GWA</span>
                                <span class="card-icon" aria-hidden="true">🕮</span>
                            </div>
                            <h3><?= $gwa === false || $gwa === null ? '—' : number_format((float) $gwa, 2) ?></h3>
                            <p>Latest recorded academic performance.</p>
                        </article>

                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Missing Documents</span>
                                <span class="card-icon" aria-hidden="true">🗎</span>
                            </div>
                            <h3><span class="<?= $missing ? 'status-warning' : 'status-active' ?>"><?= count($missing) ?></span></h3>
                            <p>Document requiring your attention.</p>
                            <a href="documents.php" class="card-link">View documents →</a>
                        </article>

                        <article class="summary-card">
                            <div class="card-header">
                                <span class="card-label">Upcoming Deadlines</span>
                                <span class="card-icon" aria-hidden="true">◷</span>
                            </div>
                            <h3><?= count($dated) ?></h3>
                            <p>Requirements with upcoming due dates.</p>
                            <a href="deadlines.php" class="card-link">View deadlines →</a>
                        </article>

                    </div>

                </section>


                <!-- Alerts + deadlines -->
                <div class="dashboard-grid">

                    <section class="content-panel">

                        <div class="panel-header">
                            <div>
                                <h2>Recent Alerts</h2>
                                <p>Important scholarship notifications</p>
                            </div>
                            <a href="notifications.php">View All</a>
                        </div>

                        <?php foreach ($alerts as $alert): ?>
                        <article class="alert-item">
                            <div class="alert-icon info-icon" aria-hidden="true">i</div>
                            <div class="alert-details">
                                <h3>Notification</h3>
                                <p><?= e($alert['message']) ?></p>
                                <span><?= e(date('F j, Y', strtotime($alert['created_at']))) ?></span>
                            </div>
                            <?php if ($alert['read_at'] === null): ?><span class="alert-status new">New</span><?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                        <?php if (!$alerts): ?><p>No notifications yet.</p><?php endif; ?>

                    </section>

                    <section class="content-panel">

                        <div class="panel-header">
                            <div>
                                <h2>Next Deadlines</h2>
                                <p>Upcoming scholarship requirements</p>
                            </div>
                            <a href="deadlines.php">View All</a>
                        </div>

                        <?php foreach (array_slice($dated, 0, 2) as $row):
                            $due  = new DateTimeImmutable($row['due_date']);
                            $days = (int) $today->diff($due)->format('%r%a');
                            [$tag, $tagClass] = $days < 0 ? ['Overdue', 'deadline-warning']
                                : ($days <= 7 ? ['Due soon', 'deadline-warning'] : ['Upcoming', 'deadline-normal']);
                        ?>
                        <article class="deadline-item">
                            <div class="deadline-date">
                                <strong><?= $due->format('d') ?></strong>
                                <span><?= strtoupper($due->format('M')) ?></span>
                            </div>
                            <div class="deadline-details">
                                <h3><?= e($row['name']) ?></h3>
                                <p>Required document</p>
                                <span class="<?= $tagClass ?>"><?= $tag ?></span>
                            </div>
                        </article>
                        <?php endforeach; ?>
                        <?php if (!$dated): ?><p>No deadlines right now.</p><?php endif; ?>

                    </section>

                </div>


                <!-- Reminder -->
                <section class="reminder-panel">

                    <div class="reminder-content">
                        <h2>Scholarship Reminder</h2>
                        <p>
                            Maintain your academic performance and submit all
                            required documents on time to remain eligible for
                            your scholarship.
                        </p>
                    </div>

                    <a href="scholarship.php" class="reminder-link">
                        View Scholarship Details →
                    </a>

                </section>

            </section>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>
