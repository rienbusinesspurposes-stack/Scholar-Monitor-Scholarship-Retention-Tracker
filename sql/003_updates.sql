-- Updates: when and why a scholarship ended, and a switch to retire a document requirement without deleting what students uploaded for it.
-- Safe to run more than once. It avoids "ADD COLUMN IF NOT EXISTS" so it works on both MySQL and MariaDB.
USE scholar_monitor;

SET @step = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scholars' AND COLUMN_NAME = 'ended_at') = 0,
    'ALTER TABLE scholars
         ADD COLUMN ended_at     TIMESTAMP    NULL AFTER verified_at,
         ADD COLUMN ended_reason VARCHAR(300) NULL AFTER ended_at',
    'SELECT 1');
PREPARE run_step FROM @step;
EXECUTE run_step;
DEALLOCATE PREPARE run_step;

SET @step = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_requirements' AND COLUMN_NAME = 'is_active') = 0,
    'ALTER TABLE document_requirements
         ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER due_date',
    'SELECT 1');
PREPARE run_step FROM @step;
EXECUTE run_step;
DEALLOCATE PREPARE run_step;