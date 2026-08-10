<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/..');
}

function app_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = APP_ROOT . '/data/app.sqlite';
    $needInit = !is_file($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($needInit) {
        app_db_migrate($pdo);
    } else {
        // Ensure schema stays current if file already exists.
        app_db_migrate($pdo);
    }

    return $pdo;
}

function app_db_migrate(PDO $pdo): void
{
    $schema = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "user",
            is_banned INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            email TEXT,
            success INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS watch_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            anime_id TEXT NOT NULL,
            episode INTEGER NOT NULL,
            watched_at TEXT NOT NULL,
            UNIQUE(user_id, anime_id, episode),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS continue_watching (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            anime_id TEXT NOT NULL,
            episode INTEGER NOT NULL,
            position_seconds INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL,
            UNIQUE(user_id, anime_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS watchlist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            anime_id TEXT NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(user_id, anime_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS slug_overrides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            anime_key TEXT NOT NULL UNIQUE,
            slug_value TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS featured_anime (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            anime_id TEXT NOT NULL UNIQUE,
            title TEXT,
            image_url TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS metrics_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            anime_id TEXT,
            user_id INTEGER,
            ip TEXT,
            meta_json TEXT,
            created_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS app_settings (
            key_name TEXT PRIMARY KEY,
            value_text TEXT,
            updated_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS email_verifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            device_name TEXT,
            ip_address TEXT,
            location TEXT,
            is_remember INTEGER NOT NULL DEFAULT 0,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            last_active TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS user_lists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            anime_id TEXT NOT NULL,
            status TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(user_id, anime_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        // Append-only. Never delete rows from this table — the admin panel
        // depends on it being a permanent record of who-did-what.
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            admin_username TEXT,
            action TEXT NOT NULL,
            target_type TEXT,
            target_id TEXT,
            meta_json TEXT,
            ip_address TEXT,
            created_at TEXT NOT NULL
        )',
        // Latest provider-ping result only (one row per provider, overwritten
        // each check) — this is a live status board, not a historical log.
        'CREATE TABLE IF NOT EXISTS stream_health (
            provider TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            response_ms INTEGER,
            last_error TEXT,
            checked_at TEXT NOT NULL
        )',
        // Append-only history behind the stream_health snapshot — lets the
        // admin panel show failures-today/success-rate/uptime% instead of
        // just "current status."
        'CREATE TABLE IF NOT EXISTS stream_health_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT NOT NULL,
            status TEXT NOT NULL,
            response_ms INTEGER,
            http_code INTEGER,
            checked_at TEXT NOT NULL
        )',
        // DB-backed failover order/enable state — was a hardcoded array in
        // api/watch_v2.php before. Seeded with the 3 current providers on
        // first migration only (INSERT OR IGNORE), so re-running migrate()
        // never resets an admin's reordering.
        'CREATE TABLE IF NOT EXISTS stream_providers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider_key TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            priority INTEGER NOT NULL DEFAULT 0,
            enabled INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT NOT NULL
        )',
        // episode IS NULL means an anime-level comment; otherwise it is
        // scoped to that specific episode. parent_id NULL = top-level.
        'CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            anime_id TEXT NOT NULL,
            episode INTEGER,
            user_id INTEGER NOT NULL,
            parent_id INTEGER,
            body TEXT NOT NULL,
            is_spoiler INTEGER NOT NULL DEFAULT 0,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            is_hidden INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            edited_at TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(parent_id) REFERENCES comments(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS comment_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            comment_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(comment_id, user_id),
            FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        // Generic reports table — reused by Reviews/Streams later (target_type
        // distinguishes them), built now so Phase 2's "Reports" item shares
        // one schema instead of three near-identical tables.
        'CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target_type TEXT NOT NULL,
            target_id TEXT NOT NULL,
            reporter_id INTEGER NOT NULL,
            reason TEXT NOT NULL,
            details TEXT,
            status TEXT NOT NULL DEFAULT "pending",
            created_at TEXT NOT NULL,
            resolved_at TEXT,
            resolved_by INTEGER,
            FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        // One review per user per anime (UNIQUE) — editing replaces it
        // rather than allowing duplicates.
        'CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            anime_id TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            rating INTEGER NOT NULL,
            body TEXT NOT NULL,
            has_spoilers INTEGER NOT NULL DEFAULT 0,
            is_featured INTEGER NOT NULL DEFAULT 0,
            is_hidden INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            edited_at TEXT,
            UNIQUE(anime_id, user_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS review_helpful_votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            review_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(review_id, user_id),
            FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        // Community board — general discussion, not tied to a specific anime
        // (that's what `comments` is for). Mirrors the comments table's shape
        // (pin/hide/reports reuse the same conventions) so admin moderation
        // tooling generalizes instead of needing a parallel code path.
        'CREATE TABLE IF NOT EXISTS community_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category TEXT NOT NULL DEFAULT "general",
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            is_hidden INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            edited_at TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS community_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            parent_id INTEGER,
            body TEXT NOT NULL,
            is_hidden INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            edited_at TEXT,
            FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(parent_id) REFERENCES community_replies(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS community_post_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(post_id, user_id),
            FOREIGN KEY(post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS community_reply_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reply_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(reply_id, user_id),
            FOREIGN KEY(reply_id) REFERENCES community_replies(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE INDEX IF NOT EXISTS idx_community_posts_board ON community_posts(is_hidden, is_pinned, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_community_posts_category ON community_posts(category, is_hidden, created_at)',
        'CREATE INDEX IF NOT EXISTS idx_community_replies_post ON community_replies(post_id, created_at)',
    ];

    foreach ($schema as $sql) {
        $pdo->exec($sql);
    }

    // Add columns to users introduced after initial release. SQLite has no
    // "ADD COLUMN IF NOT EXISTS", so check pragma table_info first.
    $existingCols = [];
    foreach ($pdo->query('PRAGMA table_info(users)') as $col) {
        $existingCols[] = $col['name'];
    }
    $newCols = [
        'avatar' => "ALTER TABLE users ADD COLUMN avatar TEXT",
        'email_verified' => "ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0",
        'last_login' => "ALTER TABLE users ADD COLUMN last_login TEXT",
        'suspended_until' => "ALTER TABLE users ADD COLUMN suspended_until TEXT",
    ];
    foreach ($newCols as $col => $alterSql) {
        if (!in_array($col, $existingCols, true)) {
            $pdo->exec($alterSql);
        }
    }

    // device_os: added for Phase 3 analytics. Backfills as NULL for old
    // rows — "Device Analytics" only covers events recorded after this
    // column existed, which is shown honestly in the admin UI.
    $metricsCols = [];
    foreach ($pdo->query('PRAGMA table_info(metrics_events)') as $col) {
        $metricsCols[] = $col['name'];
    }
    if (!in_array('device_os', $metricsCols, true)) {
        $pdo->exec('ALTER TABLE metrics_events ADD COLUMN device_os TEXT');
    }

    $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO app_settings(key_name, value_text, updated_at) VALUES (?, ?, ?)');
    $stmt->execute(['site_views', '0', $now]);

    $adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminExists === 0) {
        $adminEmail = 'admin@anikatsu.local';
        $adminUser = 'admin';
        $adminPass = password_hash('ChangeMe123!', PASSWORD_BCRYPT);
        $insert = $pdo->prepare('INSERT INTO users(email, username, password_hash, role, is_banned, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?)');
        $insert->execute([$adminEmail, $adminUser, $adminPass, 'admin', $now, $now]);
    }

    // VidLink first: it's the only provider we've wired real postMessage
    // player events for (resume tracking, auto-next through the iframe —
    // see streaming.php). VidNest still works fine as sub/dub fallback.
    $defaultProviders = [
        ['vidlink', 'VidLink', 0],
        ['vidnest', 'VidNest (Sub)', 1],
        ['vidnest-dub', 'VidNest (Dub)', 2],
    ];
    $seedStmt = $pdo->prepare('INSERT OR IGNORE INTO stream_providers(provider_key, name, priority, enabled, updated_at) VALUES (?, ?, ?, 1, ?)');
    foreach ($defaultProviders as [$key, $name, $priority]) {
        $seedStmt->execute([$key, $name, $priority, $now]);
    }
}

function app_setting_get(string $key, string $default = ''): string
{
    $stmt = app_db()->prepare('SELECT value_text FROM app_settings WHERE key_name = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
}

function app_setting_set(string $key, string $value): void
{
    $stmt = app_db()->prepare('INSERT INTO app_settings(key_name, value_text, updated_at) VALUES (?, ?, ?) ON CONFLICT(key_name) DO UPDATE SET value_text=excluded.value_text, updated_at=excluded.updated_at');
    $stmt->execute([$key, $value, gmdate('c')]);
}
