-- =============================================================
-- Skrip Skema Database SQLite untuk Moduref PHP Demo
-- Siap diimpor langsung via sqlite3 CLI, DB Browser for SQLite, dll.
-- =============================================================

CREATE TABLE IF NOT EXISTS users (
    uuid TEXT PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    referral_poin INTEGER NOT NULL DEFAULT 0,
    referral_attribution TEXT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_users_referral_attribution ON users (referral_attribution);
