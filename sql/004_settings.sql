-- Settings: numbers a Super Admin can change from the Settings page. Only changed values are stored; the code holds the defaults.
-- Safe to run more than once. Until it is run, every page works with the default values.
USE scholar_monitor;

CREATE TABLE IF NOT EXISTS settings (
    name       VARCHAR(50)  NOT NULL,
    value      VARCHAR(100) NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;