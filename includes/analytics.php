<?php
require_once __DIR__ . '/db.php';

// ── User Analytics ──────────────────────────────────────────────────
// DAU/WAU/MAU count distinct user_id in metrics_events — i.e. logged-in
// activity only. Anonymous (logged-out) traffic isn't attributable to a
// user, so it's excluded here rather than guessed at.
function app_analytics_active_users(): array
{
    $pdo = app_db();
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM metrics_events WHERE user_id IS NOT NULL AND created_at >= ?");
    $stmt->execute([gmdate('c', time() - 86400)]); $dau = (int)$stmt->fetchColumn();
    $stmt->execute([gmdate('c', time() - 7 * 86400)]); $wau = (int)$stmt->fetchColumn();
    $stmt->execute([gmdate('c', time() - 30 * 86400)]); $mau = (int)$stmt->fetchColumn();
    return ['dau' => $dau, 'wau' => $wau, 'mau' => $mau];
}

// Rough approximation, not true cohort retention: of the users active in
// the last 7 days, what share had an account older than 7 days (i.e. are
// coming back rather than brand new)?
function app_analytics_returning_rate(): array
{
    $pdo = app_db();
    $weekAgo = gmdate('c', time() - 7 * 86400);
    $activeUserIds = array_column(
        $pdo->query("SELECT DISTINCT user_id FROM metrics_events WHERE user_id IS NOT NULL AND created_at >= '$weekAgo'")->fetchAll(),
        'user_id'
    );
    if (!$activeUserIds) return ['active' => 0, 'returning' => 0, 'rate' => null];
    $ids = implode(',', array_map('intval', $activeUserIds));
    $returning = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id IN ($ids) AND created_at < '$weekAgo'")->fetchColumn();
    return [
        'active' => count($activeUserIds),
        'returning' => $returning,
        'rate' => round(($returning / count($activeUserIds)) * 100, 1),
    ];
}

function app_analytics_registrations_trend(int $days = 30): array
{
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = gmdate('Y-m-d', time() - $i * 86400);
        $stmt = app_db()->prepare("SELECT COUNT(*) FROM users WHERE created_at LIKE ?");
        $stmt->execute([$day . '%']);
        $out[$day] = (int)$stmt->fetchColumn();
    }
    return $out;
}

// ── Anime Analytics ─────────────────────────────────────────────────
function app_analytics_most_watched(int $limit = 10, int $days = 30): array
{
    $since = gmdate('c', time() - $days * 86400);
    $stmt = app_db()->prepare(
        "SELECT anime_id, COUNT(*) AS views FROM metrics_events
         WHERE event_type = 'episode_view' AND created_at >= ?
         GROUP BY anime_id ORDER BY views DESC LIMIT ?"
    );
    $stmt->bindValue(1, $since);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_analytics_most_favorited(int $limit = 10): array
{
    $stmt = app_db()->prepare(
        "SELECT anime_id, COUNT(*) AS list_count FROM user_lists GROUP BY anime_id ORDER BY list_count DESC LIMIT ?"
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_analytics_highest_rated(int $limit = 10, int $minReviews = 1): array
{
    $stmt = app_db()->prepare(
        "SELECT anime_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS avg_rating
         FROM reviews WHERE is_hidden = 0 GROUP BY anime_id HAVING COUNT(*) >= ?
         ORDER BY avg_rating DESC, review_count DESC LIMIT ?"
    );
    $stmt->bindValue(1, max(1, $minReviews), PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Episode Analytics ───────────────────────────────────────────────
// Distinct-viewer count per episode, normalized against episode 1's count.
// This is a real drop-off curve, not modeled — built from actual
// watch_history rows.
function app_analytics_episode_dropoff(string $animeId, int $maxEpisodes = 20): array
{
    $stmt = app_db()->prepare(
        "SELECT episode, COUNT(DISTINCT user_id) AS viewers FROM watch_history
         WHERE anime_id = ? GROUP BY episode ORDER BY episode ASC LIMIT ?"
    );
    $stmt->bindValue(1, $animeId);
    $stmt->bindValue(2, max(1, $maxEpisodes), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (!$rows) return [];
    $base = (int)$rows[0]['viewers'];
    foreach ($rows as &$r) {
        $r['viewers'] = (int)$r['viewers'];
        $r['pct'] = $base > 0 ? round(($r['viewers'] / $base) * 100, 1) : 0;
    }
    return $rows;
}

// Anime with the most watch_history rows — used to populate the episode
// drop-off selector with anime that actually have enough data to chart.
function app_analytics_anime_with_history(int $limit = 30): array
{
    $stmt = app_db()->prepare(
        "SELECT anime_id, COUNT(*) AS rows_count FROM watch_history GROUP BY anime_id ORDER BY rows_count DESC LIMIT ?"
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Device Analytics ────────────────────────────────────────────────
function app_analytics_device_breakdown(int $days = 30): array
{
    $since = gmdate('c', time() - $days * 86400);
    $stmt = app_db()->prepare(
        "SELECT device_os, COUNT(*) AS c FROM metrics_events
         WHERE device_os IS NOT NULL AND created_at >= ?
         GROUP BY device_os ORDER BY c DESC"
    );
    $stmt->execute([$since]);
    return $stmt->fetchAll();
}

// ── Search Analytics ────────────────────────────────────────────────
function app_analytics_top_searches(int $limit = 15, int $days = 30): array
{
    $since = gmdate('c', time() - $days * 86400);
    $stmt = app_db()->prepare(
        "SELECT json_extract(meta_json, '\$.query') AS query, COUNT(*) AS searches
         FROM metrics_events WHERE event_type = 'search' AND created_at >= ?
         GROUP BY query HAVING query IS NOT NULL AND query != '' ORDER BY searches DESC LIMIT ?"
    );
    $stmt->bindValue(1, $since);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_analytics_failed_searches(int $limit = 15, int $days = 30): array
{
    $since = gmdate('c', time() - $days * 86400);
    $stmt = app_db()->prepare(
        "SELECT json_extract(meta_json, '\$.query') AS query, COUNT(*) AS searches
         FROM metrics_events WHERE event_type = 'search' AND created_at >= ?
           AND CAST(json_extract(meta_json, '\$.result_count') AS INTEGER) = 0
         GROUP BY query HAVING query IS NOT NULL AND query != '' ORDER BY searches DESC LIMIT ?"
    );
    $stmt->bindValue(1, $since);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
