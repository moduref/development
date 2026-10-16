-- =============================================================
-- Skrip Skema Database MySQL / MariaDB untuk Moduref Demo
-- Siap diimpor langsung via mysql CLI, phpMyAdmin, MySQL Workbench, dll.
-- =============================================================

CREATE DATABASE IF NOT EXISTS moduref_demo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE moduref_demo;

CREATE TABLE IF NOT EXISTS users (
  uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  username VARCHAR(120) NOT NULL,
  referral_poin INT UNSIGNED NOT NULL DEFAULT 0,
  referral_attribution VARCHAR(120) NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uuid),
  UNIQUE KEY users_username_unique (username),
  UNIQUE KEY users_email_unique (email),
  KEY idx_users_referral_attribution (referral_attribution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
