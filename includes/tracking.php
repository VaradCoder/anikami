<?php
require_once __DIR__ . '/db.php';

function app_track_event(string $eventType, ?string $animeId = null, array $meta = []): void
{
    try {
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $stmt = app_db()->prepare('INSERT INTO metrics_events(event_type, anime_id, user_id, ip, meta_json, created_at, device_os) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $eventType,
            $animeId,
            $userId,
            app_client_ip(),
            json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            gmdate('c'),
            app_device_os((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
        ]);

        if ($eventType === 'page_view') {
            $views = (int)app_setting_get('site_views', '0');
            app_setting_set('site_views', (string)($views + 1));
        }
    } catch (Throwable $e) {
        // Intentionally swallow tracking errors to avoid breaking UX.
    }
}

// Coarse OS bucket for device analytics — deliberately simpler than
// app_parse_device_name() (which also identifies the browser, for session
// display), since the dashboard groups by OS/device class only.
function app_device_os(string $userAgent): string
{
    if ($userAgent === '') return 'Unknown';
    if (preg_match('/Android/', $userAgent)) return 'Android';
    if (preg_match('/iPhone|iPod/', $userAgent)) return 'iPhone';
    if (preg_match('/iPad/', $userAgent)) return 'iPad';
    if (preg_match('/Macintosh|Mac OS X/', $userAgent)) return 'Mac';
    if (preg_match('/Windows/', $userAgent)) return 'Windows';
    if (preg_match('/Linux/', $userAgent)) return 'Linux';
    return 'Other';
}

function app_trending_anime(string $window = 'day', int $limit = 10): array
{
    $seconds = $window === 'week' ? 604800 : 86400;
    $since = gmdate('c', time() - $seconds);
    $stmt = app_db()->prepare('SELECT anime_id, COUNT(*) AS score FROM metrics_events WHERE anime_id IS NOT NULL AND created_at >= ? AND event_type IN ("anime_view", "episode_view", "anime_click") GROUP BY anime_id ORDER BY score DESC LIMIT ?');
    $stmt->bindValue(1, $since);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
