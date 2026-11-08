<?php
// Student dashboard: status, latest grade, missing documents, next deadlines and a profile summary for the logged-in User.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scholar_status.php';

$user = requireRole(['User']);
$pageTitle = 'Student Dashboard';
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

$program = null;
if ($scholar) {
    $stmt = $pdo->prepare('SELECT name FROM scholarship_programs WHERE id = ?');
    $stmt->execute([$scholar['program_id']]);
    $program = $stmt->fetchColumn() ?: null;
}
// Academic year runs June to May, e.g. November 2026 is 2026–2027.
$year    = (int) date('Y') - ((int) date('n') < 6 ? 1 : 0);
$parts   = preg_split('/\s+/', trim((string) $user['full_name']));
$initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
$firstName = $parts[0];

?>

                <!-- Welcome banner -->
                <section class="welcome-banner">
                    <div>
                        <p class="section-label">STUDENT DASHBOARD</p>
                        <h1 id="welcome-message" data-name="<?= e($firstName) ?>">Welcome!</h1>
                        <p class="welcome-tagline">Ing abante mu, ing bukas mu.</p>
                    </div>
                </section>

                <!-- Profile and overview -->
                <div class="wire-grid">

                    <div class="wire-col">
                        <div class="wire-pill">ACADEMIC YEAR (<?= $year ?>–<?= $year + 1 ?>)</div>

                        <section class="card profile-card wire-profile" aria-label="Student profile summary">
                            <div class="profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
                            <h2 class="profile-name"><?= e($user['full_name']) ?></h2>
                            <p class="profile-meta">Student No.: <?= e($scholar['student_number'] ?? '—') ?></p>
                            <p class="profile-meta"><?= $program ? e($program) : 'No scholarship program yet' ?></p>
                            <span class="profile-badge"><?= e(STATUS_LABEL[$status]) ?></span>
                        </section>

                        <section class="card office-card" aria-label="School office hours">
                            <h2 class="card-title">School Office Hours</h2>
                            <div class="hours-row"><strong>Registrar</strong><span>Mon–Fri, 8:00 AM – 5:00 PM</span></div>
                            <div class="hours-row"><strong>Finance</strong><span>Mon–Fri, 8:00 AM – 4:00 PM</span></div>
                            <div class="hours-row"><strong>Scholarship Office</strong><span>Mon–Fri, 9:00 AM – 4:00 PM</span></div>
                        </section>
                    </div>

                    <div class="wire-col">
                        <div class="wire-pill">OVERVIEW</div>

                        <article class="summary-card wire-card">
                            <span class="card-label">Scholarship Status</span>
                            <h3><span class="<?= $status === 'Active' ? 'status-active' : ($status === 'Ineligible' ? 'status-danger' : 'status-warning') ?>"><?= e(STATUS_LABEL[$status]) ?></span></h3>
                            <p><?= e(STATUS_TEXT[$status]) ?></p>
                        </article>

                        <article class="summary-card wire-card">
                            <span class="card-label">Current GWA</span>
                            <h3><?= $gwa === false || $gwa === null ? '—' : number_format((float) $gwa, 2) ?></h3>
                            <p>Latest recorded academic performance.</p>
                        </article>

                        <article class="summary-card wire-card">
                            <span class="card-label">Missing Documents</span>
                            <h3><span class="<?= $missing ? 'status-danger' : 'status-active' ?>"><?= count($missing) ?></span></h3>
                            <p>Document requiring your attention.</p>
                            <a href="documents.php" class="card-link">View documents →</a>
                        </article>

                        <article class="summary-card wire-card">
                            <span class="card-label">Upcoming Deadlines</span>
                            <h3><?= count($dated) ?></h3>
                            <p>Requirements with upcoming due dates.</p>
                            <a href="deadlines.php" class="card-link">View deadlines →</a>
                        </article>
                    </div>

                </div>

<?php require __DIR__ . '/../includes/user_footer.php'; ?>