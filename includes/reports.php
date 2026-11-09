<?php
// The three staff CSV reports: which ones exist, what each contains, and how a cell is made safe to open in Excel.
// Used by admin/reports.php. Load config/database.php and includes/functions.php first.

const REPORTS = [
    'scholars'  => ['Scholars', 'Every scholar with program, status, latest grade and the minimum they must keep.'],
    'grades'    => ['Grades', 'Every grade record, one row per scholar and term.'],
    'documents' => ['Documents', 'Every required document for every scholar who has not ended: missing, submitted, approved or rejected.'],
];

// Excel runs a cell that starts with = + - @ (or a tab or return) as a formula, so a name like "=HYPERLINK(...)" typed by a
// student could act on a staff member's computer. A leading ' turns it back into plain text.
function csvSafe(?string $value): string
{
    $value = (string) $value;
    return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
}

// Returns [header row, data rows] for one report, or null for a name that is not a report.
function reportRows(string $type): ?array
{
    global $pdo;
    $people = 'FROM scholars sc JOIN users u ON u.id = sc.user_id JOIN scholarship_programs p ON p.id = sc.program_id';

    switch ($type) {
        case 'scholars':
            $header = ['Student number', 'Name', 'Email', 'Program', 'Status', 'Latest grade', 'Minimum grade', 'Verified', 'Ended', 'Reason ended'];
            $sql = "SELECT sc.student_number, u.full_name, u.email, p.name, sc.status,
                           (SELECT g.average FROM grade_records g WHERE g.scholar_id = sc.id ORDER BY g.id DESC LIMIT 1),
                           p.min_grade, sc.verified_at, sc.ended_at, sc.ended_reason
                    $people ORDER BY u.full_name";
            break;
        case 'grades':
            $header = ['Student number', 'Name', 'Program', 'Term', 'Average', 'Recorded'];
            $sql = "SELECT sc.student_number, u.full_name, p.name, g.term, g.average, g.created_at
                    FROM grade_records g JOIN scholars sc ON sc.id = g.scholar_id
                    JOIN users u ON u.id = sc.user_id JOIN scholarship_programs p ON p.id = sc.program_id
                    ORDER BY u.full_name, g.id";
            break;
        case 'documents':
            $header = ['Student number', 'Name', 'Program', 'Requirement', 'Due date', 'Status', 'Submitted', 'Reviewed'];
            $sql = "SELECT sc.student_number, u.full_name, p.name, r.name, r.due_date, COALESCE(d.status, 'Missing'), d.submitted_at, d.reviewed_at
                    $people
                    JOIN document_requirements r ON r.program_id = sc.program_id AND r.is_active = 1
                    LEFT JOIN scholar_documents d ON d.scholar_id = sc.id AND d.requirement_id = r.id
                    WHERE sc.status <> 'Ended'
                    ORDER BY u.full_name, r.name";
            break;
        default:
            return null;
    }

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    if ($type === 'scholars') {
        foreach ($rows as &$row) {
            $row[4] = STATUS_LABEL[$row[4]] ?? $row[4]; // "At Risk", the way the pages say it
        }
        unset($row);
    }
    return [$header, $rows];
}

// Writes one report to $out as CSV. The byte order mark at the start makes Excel read names with accents correctly.
function writeCsv($out, array $header, array $rows): void
{
    fwrite($out, "\xEF\xBB\xBF");
    foreach ([$header, ...$rows] as $row) {
        fputcsv($out, array_map('csvSafe', $row), ',', '"', ''); // empty escape character: backslashes are plain text, as the CSV standard says
    }
}