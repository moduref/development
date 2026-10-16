-- =============================================================
-- Skrip Skema Database PostgreSQL untuk Moduref Demo
-- Siap diimpor langsung via psql, pgAdmin, DBeaver, dll.
-- =============================================================

CREATE TABLE IF NOT EXISTS users (
    uuid CHAR(36) NOT NULL PRIMARY KEY,
    username VARCHAR(120) NOT NULL UNIQUE,
    referral_poin INTEGER NOT NULL DEFAULT 0,
    referral_attribution VARCHAR(120) NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_referral_attribution ON users (referral_attribution);
