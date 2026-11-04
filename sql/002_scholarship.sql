-- Scholarship tables: programs and their grade rule, scholars, grades, required documents, notifications.
-- Grades are averages from 0 to 100, higher is better. Safe to run more than once.
USE scholar_monitor;

CREATE TABLE IF NOT EXISTS scholarship_programs (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(150) NOT NULL,
    description    TEXT NULL,
    min_grade      DECIMAL(5,2) NOT NULL,                 -- the average a scholar must keep
    warning_margin DECIMAL(5,2) NOT NULL DEFAULT 3.00,    -- within this many points above min_grade counts as near losing it
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_programs_name (name),
    CONSTRAINT chk_programs_min_grade CHECK (min_grade BETWEEN 0 AND 100),
    CONSTRAINT chk_programs_margin CHECK (warning_margin BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scholars (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    program_id     INT UNSIGNED NOT NULL,
    student_number VARCHAR(30)  NOT NULL,
    status         ENUM('Pending','Active','AtRisk','Ineligible','Ended') NOT NULL DEFAULT 'Pending',
    verified_by    INT UNSIGNED NULL,
    verified_at    TIMESTAMP    NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scholars_user (user_id),
    UNIQUE KEY uq_scholars_student_number (student_number),
    KEY idx_scholars_program (program_id, status),
    CONSTRAINT fk_scholars_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_scholars_program  FOREIGN KEY (program_id)  REFERENCES scholarship_programs (id),
    CONSTRAINT fk_scholars_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grade_records (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scholar_id  INT UNSIGNED NOT NULL,
    term        VARCHAR(30)  NOT NULL,                     -- e.g. '2026-2027 1st Sem'
    average     DECIMAL(5,2) NOT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grades_scholar_term (scholar_id, term),
    CONSTRAINT chk_grades_average CHECK (average BETWEEN 0 AND 100),
    CONSTRAINT fk_grades_scholar  FOREIGN KEY (scholar_id)  REFERENCES scholars (id) ON DELETE CASCADE,
    CONSTRAINT fk_grades_recorder FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_requirements (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id  INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    due_date    DATE NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_requirements_program_name (program_id, name),
    CONSTRAINT fk_requirements_program FOREIGN KEY (program_id) REFERENCES scholarship_programs (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scholar_documents (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scholar_id     INT UNSIGNED NOT NULL,
    requirement_id INT UNSIGNED NOT NULL,
    stored_name    VARCHAR(100) NOT NULL,                  -- random file name inside uploads/documents
    original_name  VARCHAR(255) NOT NULL,
    status         ENUM('Submitted','Approved','Rejected') NOT NULL DEFAULT 'Submitted',
    note           VARCHAR(500) NULL,
    reviewed_by    INT UNSIGNED NULL,
    reviewed_at    TIMESTAMP    NULL,
    submitted_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_scholar_requirement (scholar_id, requirement_id),
    CONSTRAINT fk_documents_scholar     FOREIGN KEY (scholar_id)     REFERENCES scholars (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_requirement FOREIGN KEY (requirement_id) REFERENCES document_requirements (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_reviewer    FOREIGN KEY (reviewed_by)    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    message    VARCHAR(500) NOT NULL,
    dedupe_key VARCHAR(100) NULL,                          -- the alert job uses it so one problem is reported once
    read_at    TIMESTAMP    NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifications_dedupe (user_id, dedupe_key),
    KEY idx_notifications_user (user_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;