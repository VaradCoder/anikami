<?php
require_once __DIR__ . '/db.php';

function app_slug_override_get(string $animeKey): ?string
{
    $stmt = app_db()->prepare('SELECT slug_value FROM slug_overrides WHERE anime_key = ? LIMIT 1');
    $stmt->execute([strtolower(trim($animeKey))]);
    $slug = $stmt->fetchColumn();
    return $slug !== false ? (string)$slug : null;
}

function app_slug_override_set(string $animeKey, string $slugValue): void
{
    $stmt = app_db()->prepare('INSERT INTO slug_overrides(anime_key, slug_value, updated_at) VALUES (?, ?, ?) ON CONFLICT(anime_key) DO UPDATE SET slug_value=excluded.slug_value, updated_at=excluded.updated_at');
    $stmt->execute([strtolower(trim($animeKey)), trim($slugValue), gmdate('c')]);
}

function app_featured_set(string $animeId, string $title = '', string $imageUrl = '', int $sortOrder = 0): void
{
    $stmt = app_db()->prepare('INSERT INTO featured_anime(anime_id, title, image_url, sort_order, updated_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(anime_id) DO UPDATE SET title=excluded.title, image_url=excluded.image_url, sort_order=excluded.sort_order, updated_at=excluded.updated_at');
    $stmt->execute([$animeId, $title, $imageUrl, $sortOrder, gmdate('c')]);
}

function app_featured_list(int $limit = 10): array
{
    $stmt = app_db()->prepare('SELECT anime_id, title, image_url, sort_order FROM featured_anime ORDER BY sort_order ASC, updated_at DESC LIMIT ?');
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_featured_remove(string $animeId): void
{
    $stmt = app_db()->prepare('DELETE FROM featured_anime WHERE anime_id = ?');
    $stmt->execute([$animeId]);
}

// Append-only audit trail for admin actions. Call this from every mutating
// admin endpoint — bans, deletes, settings changes, featured-content edits.
function app_audit_log(string $action, ?string $targetType = null, ?string $targetId = null, array $meta = []): void
{
    $admin = app_current_user();
    $stmt = app_db()->prepare(
        'INSERT INTO audit_logs(admin_id, admin_username, action, target_type, target_id, meta_json, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $admin['id'] ?? null,
        $admin['username'] ?? null,
        $action,
        $targetType,
        $targetId,
        $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        function_exists('app_client_ip') ? app_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null),
        gmdate('c'),
    ]);
}

function app_audit_list(int $limit = 100): array
{
    $stmt = app_db()->prepare('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_audit_list_filtered(string $adminUsername = '', string $action = '', string $dateFrom = '', string $dateTo = '', int $limit = 200): array
{
    $where = [];
    $params = [];
    if ($adminUsername !== '') { $where[] = 'admin_username = ?'; $params[] = $adminUsername; }
    if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
    if ($dateFrom !== '') { $where[] = 'created_at >= ?'; $params[] = $dateFrom . 'T00:00:00+00:00'; }
    if ($dateTo !== '') { $where[] = 'created_at <= ?'; $params[] = $dateTo . 'T23:59:59+00:00'; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $stmt = app_db()->prepare("SELECT * FROM audit_logs $whereSql ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function app_audit_distinct_admins(): array
{
    return array_column(app_db()->query('SELECT DISTINCT admin_username FROM audit_logs WHERE admin_username IS NOT NULL ORDER BY admin_username')->fetchAll(), 'admin_username');
}

function app_audit_distinct_actions(): array
{
    return array_column(app_db()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(), 'action');
}

// Kept as a thin backward-compatible delegate — the actual per-provider
// health-check logic now lives in each StreamProvider class (called via
// StreamProviderManager::runHealthChecks()), not hardcoded here. Anything
// still calling app_check_stream_health() directly keeps working unchanged.
function app_check_stream_health(): array
{
    require_once __DIR__ . '/../services/StreamProviderManager.php';
    return (new StreamProviderManager())->runHealthChecks();
}

function app_stream_health_list(): array
{
    return app_db()->query('SELECT * FROM stream_health ORDER BY provider')->fetchAll();
}

// Failures today, success rate today, and a 7-day uptime % — computed from
// the append-only stream_health_log, not estimated.
function app_stream_health_stats(string $provider): array
{
    $dayAgo = gmdate('c', time() - 86400);
    $weekAgo = gmdate('c', time() - 7 * 86400);

    $stmt = app_db()->prepare("SELECT COUNT(*) FROM stream_health_log WHERE provider = ? AND checked_at >= ? AND status != 'healthy'");
    $stmt->execute([$provider, $dayAgo]);
    $failuresToday = (int)$stmt->fetchColumn();

    $stmt = app_db()->prepare("SELECT COUNT(*) FROM stream_health_log WHERE provider = ? AND checked_at >= ?");
    $stmt->execute([$provider, $dayAgo]);
    $totalToday = (int)$stmt->fetchColumn();

    $stmt = app_db()->prepare("SELECT COUNT(*) FROM stream_health_log WHERE provider = ? AND checked_at >= ? AND status = 'healthy'");
    $stmt->execute([$provider, $weekAgo]);
    $healthyWeek = (int)$stmt->fetchColumn();

    $stmt = app_db()->prepare("SELECT COUNT(*) FROM stream_health_log WHERE provider = ? AND checked_at >= ?");
    $stmt->execute([$provider, $weekAgo]);
    $totalWeek = (int)$stmt->fetchColumn();

    $stmt = app_db()->prepare("SELECT MAX(checked_at) FROM stream_health_log WHERE provider = ? AND status = 'healthy'");
    $stmt->execute([$provider]);
    $lastSuccess = $stmt->fetchColumn();

    return [
        'failures_today' => $failuresToday,
        'success_rate_today' => $totalToday > 0 ? round((($totalToday - $failuresToday) / $totalToday) * 100, 1) : null,
        'uptime_pct_7d' => $totalWeek > 0 ? round(($healthyWeek / $totalWeek) * 100, 1) : null,
        'last_success' => $lastSuccess ?: null,
        'checks_7d' => $totalWeek,
    ];
}

// ── Stream provider failover config — was a hardcoded array in
// api/watch_v2.php before. Order = priority ASC; disabled providers are
// skipped entirely by the watch page. ──
function app_stream_provider_list(): array
{
    return app_db()->query('SELECT * FROM stream_providers ORDER BY priority ASC')->fetchAll();
}

function app_stream_provider_reorder(array $orderedKeys): void
{
    $pdo = app_db();
    $stmt = $pdo->prepare('UPDATE stream_providers SET priority = ?, updated_at = ? WHERE provider_key = ?');
    foreach (array_values($orderedKeys) as $i => $key) {
        $stmt->execute([$i, gmdate('c'), $key]);
    }
    app_stream_providers_bump_version();
}

function app_stream_provider_set_enabled(string $key, bool $enabled): void
{
    app_db()->prepare('UPDATE stream_providers SET enabled = ?, updated_at = ? WHERE provider_key = ?')
        ->execute([$enabled ? 1 : 0, gmdate('c'), $key]);
    app_stream_providers_bump_version();
}

// watch_v2.php caches its resolved server list per episode for 30 minutes
// (avoids rebuilding it on every page load). That cache key includes this
// version so an admin's reorder/enable/disable takes effect immediately
// instead of waiting out the TTL on every already-cached episode.
function app_stream_providers_version(): string
{
    return app_setting_get('stream_providers_version', '1');
}

function app_stream_providers_bump_version(): void
{
    app_setting_set('stream_providers_version', (string)(((int)app_stream_providers_version()) + 1));
}
