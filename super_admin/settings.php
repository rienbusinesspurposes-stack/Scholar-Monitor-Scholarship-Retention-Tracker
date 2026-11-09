<?php
// Settings a Super Admin can change (super_admin/settings.php). Each one is a whole number with a default and a safe range.
// Needs sql/004_settings.sql; until that is run, or if a stored value is not valid, the default is used so no page ever breaks.
// Load config/database.php first.

// name => [default, lowest, highest, label, unit, help]
const SETTINGS = [
    'alert_due_soon_days'  => [7, 1, 60, 'Document reminders start', 'days before the due date', 'Students are reminded about a missing document this many days before it is due, and again once it is overdue.'],
    'session_idle_minutes' => [30, 5, 240, 'Automatic logout after', 'minutes without activity', 'Anyone who does nothing for this long must log in again. Shorter is safer on shared computers.'],
];

// All stored values by name, read once per request. Pass true to read again after a change.
function settingsStored(bool $reload = false): array
{
    global $pdo;
    static $stored = null;
    if ($stored === null || $reload) {
        try {
            $stored = $pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $stored = []; // the table is not there yet: use the defaults
        }
    }
    return $stored;
}

// Whole-number text inside the allowed range, or null.
function settingValue(string $name, string $text): ?int
{
    [, $low, $high] = SETTINGS[$name];
    $text = trim($text);
    return ctype_digit($text) && strlen($text) <= 4 && (int) $text >= $low && (int) $text <= $high ? (int) $text : null;
}

function setting(string $name): int
{
    return settingValue($name, settingsStored()[$name] ?? '') ?? SETTINGS[$name][0];
}

// Saves one setting. Returns an error message, or null on success.
function saveSetting(int $actorId, string $name, string $text): ?string
{
    global $pdo;
    if (!isset(SETTINGS[$name])) {
        return 'That setting does not exist.';
    }
    [, $low, $high, $label] = SETTINGS[$name];
    $value = settingValue($name, $text);
    if ($value === null) {
        return "$label: enter a whole number from $low to $high.";
    }
    try {
        $pdo->prepare(
            'INSERT INTO settings (name, value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)'
        )->execute([$name, (string) $value, $actorId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') { // table does not exist
            return 'The settings table is missing. Run sql/004_settings.sql first.';
        }
        throw $e;
    }
    settingsStored(true);
    auditLog($actorId, 'setting_changed', "$name = $value");
    return null;
}
