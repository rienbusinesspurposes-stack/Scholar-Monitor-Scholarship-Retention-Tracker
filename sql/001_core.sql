-- Core tables: accounts, roles, login protection, audit log.
-- Safe to run more than once: it never drops or changes existing tables.
CREATE DATABASE IF NOT EXISTS scholar_monitor
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scholar_monitor;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(150) NOT NULL,
    role          ENUM('SuperAdmin','Admin','User') NOT NULL DEFAULT 'User',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier   VARCHAR(255) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_identifier (identifier, attempted_at),
    KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_confirmations (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    code_hash  CHAR(64)     NOT NULL,
    tries      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_confirm_user (user_id, expires_at),
    CONSTRAINT fk_confirm_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token (token_hash),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_audit_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(60)  NOT NULL,
    details    VARCHAR(500) NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;
