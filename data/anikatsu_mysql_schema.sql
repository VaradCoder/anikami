-- =====================================================================
-- Anikatsu — MySQL/MariaDB reference schema (XAMPP)
-- =====================================================================
-- REFERENCE / DOCUMENTATION ONLY. The live app currently runs on SQLite
-- via includes/db.php (app_db() opens data/app.sqlite directly) and does
-- NOT read this file. Running this script does not change app behavior.
--
-- This is a straight translation of the actual live SQLite schema
-- (dumped from data/app.sqlite on 2026-06-20) into MySQL syntax, for:
--   - documentation of the real schema in a more portable format
--   - a starting point if/when a real migration to MySQL is decided
-- Column types/constraints mirror the SQLite source as closely as MySQL
-- allows (e.g. SQLite's dynamic TEXT timestamps -> MySQL DATETIME,
-- SQLite's loose "0/1 as boolean" INTEGER -> MySQL TINYINT(1)).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS anikatsu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE anikatsu;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(255) NOT NULL,
  username        VARCHAR(32)  NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  role            VARCHAR(20)  NOT NULL DEFAULT 'user',
  is_banned       TINYINT(1)   NOT NULL DEFAULT 0,
  avatar          VARCHAR(255) NULL,
  email_verified  TINYINT(1)   NOT NULL DEFAULT 0,
  last_login      DATETIME     NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- login_attempts — rate limiting (10/min per IP, 5/15min per account)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip          VARCHAR(64)  NOT NULL,
  email       VARCHAR(255) NULL,
  success     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  KEY idx_login_attempts_ip_time (ip, created_at),
  KEY idx_login_attempts_email_time (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- email_verifications — 24h-expiry tokens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_verifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(64)  NOT NULL,
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_email_verifications_token (token),
  CONSTRAINT fk_email_verifications_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- password_resets — 1h-expiry, single-use tokens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(64)  NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used        TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_password_resets_token (token),
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- user_sessions — device tracking + remember-me (one row per login)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_sessions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  token_hash    VARCHAR(64)  NOT NULL,   -- sha256 of the remember-me token; raw token never stored
  device_name   VARCHAR(100) NULL,       -- e.g. "Chrome on Windows" — parsed from User-Agent
  ip_address    VARCHAR(64)  NULL,
  location      VARCHAR(100) NULL,       -- NULL unless a geo-IP provider is wired in
  is_remember   TINYINT(1)   NOT NULL DEFAULT 0,
  expires_at    DATETIME     NOT NULL,
  created_at    DATETIME     NOT NULL,
  last_active   DATETIME     NOT NULL,
  UNIQUE KEY uq_user_sessions_token_hash (token_hash),
  KEY idx_user_sessions_user (user_id),
  CONSTRAINT fk_user_sessions_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- watch_history — one row per (user, anime, episode) actually watched
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS watch_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  anime_id    VARCHAR(191) NOT NULL,     -- anime slug, e.g. "one-piece"
  episode     INT UNSIGNED NOT NULL,
  watched_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_watch_history_user_anime_ep (user_id, anime_id, episode),
  CONSTRAINT fk_watch_history_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- continue_watching — ONE row per (user, anime): last episode + position
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS continue_watching (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  anime_id          VARCHAR(191) NOT NULL,
  episode           INT UNSIGNED NOT NULL,
  position_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at        DATETIME     NOT NULL,
  UNIQUE KEY uq_continue_watching_user_anime (user_id, anime_id),
  CONSTRAINT fk_continue_watching_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- watchlist
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS watchlist (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  anime_id    VARCHAR(191) NOT NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_watchlist_user_anime (user_id, anime_id),
  CONSTRAINT fk_watchlist_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- slug_overrides — admin-managed slug -> anime mapping fixes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS slug_overrides (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anime_key   VARCHAR(191) NOT NULL,
  slug_value  VARCHAR(191) NOT NULL,
  updated_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_slug_overrides_anime_key (anime_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- featured_anime — admin-curated homepage spotlight
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS featured_anime (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anime_id    VARCHAR(191) NOT NULL,
  title       VARCHAR(255) NULL,
  image_url   VARCHAR(500) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  updated_at  DATETIME NOT NULL,
  UNIQUE KEY uq_featured_anime_anime_id (anime_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- metrics_events — lightweight analytics/event log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metrics_events (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type  VARCHAR(64)  NOT NULL,
  anime_id    VARCHAR(191) NULL,
  user_id     INT UNSIGNED NULL,
  ip          VARCHAR(64)  NULL,
  meta_json   TEXT NULL,
  created_at  DATETIME NOT NULL,
  KEY idx_metrics_events_type_time (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- app_settings — simple key/value store
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
  key_name    VARCHAR(100) PRIMARY KEY,
  value_text  TEXT NULL,
  updated_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Seed data — matches what includes/db.php creates automatically on
-- first run (default admin user + site_views counter). Change the
-- password immediately after first login if you ever actually use this.
-- ---------------------------------------------------------------------
INSERT INTO app_settings (key_name, value_text, updated_at)
VALUES ('site_views', '0', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE key_name = key_name;

-- password_hash('ChangeMe123!', PASSWORD_BCRYPT) — generate your own with:
--   php -r "echo password_hash('YourPasswordHere', PASSWORD_BCRYPT);"
INSERT INTO users (email, username, password_hash, role, is_banned, created_at, updated_at)
SELECT 'admin@anikatsu.local', 'admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin');
