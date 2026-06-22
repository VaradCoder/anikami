<?php
require_once __DIR__ . '/db.php';

function app_watchlist_add(int $userId, string $animeId): void
{
    $stmt = app_db()->prepare('INSERT OR IGNORE INTO watchlist(user_id, anime_id, created_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $animeId, gmdate('c')]);
}

function app_watchlist_remove(int $userId, string $animeId): void
{
    $stmt = app_db()->prepare('DELETE FROM watchlist WHERE user_id = ? AND anime_id = ?');
    $stmt->execute([$userId, $animeId]);
}

function app_watchlist_has(int $userId, string $animeId): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM watchlist WHERE user_id = ? AND anime_id = ? LIMIT 1');
    $stmt->execute([$userId, $animeId]);
    return (bool)$stmt->fetchColumn();
}

function app_watchlist_list(int $userId, int $limit = 50): array
{
    $stmt = app_db()->prepare('SELECT anime_id, created_at FROM watchlist WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

const APP_LIST_STATUSES = ['watching', 'completed', 'on_hold', 'dropped', 'plan_to_watch'];

function app_set_anime_list_status(int $userId, string $animeId, string $status): bool
{
    if (!in_array($status, APP_LIST_STATUSES, true)) {
        return false;
    }
    $stmt = app_db()->prepare(
        'INSERT INTO user_lists(user_id, anime_id, status, updated_at) VALUES (?, ?, ?, ?)
         ON CONFLICT(user_id, anime_id) DO UPDATE SET status=excluded.status, updated_at=excluded.updated_at'
    );
    $stmt->execute([$userId, $animeId, $status, gmdate('c')]);
    return true;
}

function app_remove_anime_list_status(int $userId, string $animeId): void
{
    app_db()->prepare('DELETE FROM user_lists WHERE user_id = ? AND anime_id = ?')->execute([$userId, $animeId]);
}

function app_get_anime_list_status(int $userId, string $animeId): ?string
{
    $stmt = app_db()->prepare('SELECT status FROM user_lists WHERE user_id = ? AND anime_id = ? LIMIT 1');
    $stmt->execute([$userId, $animeId]);
    $status = $stmt->fetchColumn();
    return $status !== false ? (string)$status : null;
}

// Grouped by status, e.g. ['watching' => [...rows], 'completed' => [...rows], ...]
function app_get_user_lists_grouped(int $userId): array
{
    $grouped = array_fill_keys(APP_LIST_STATUSES, []);
    $stmt = app_db()->prepare('SELECT anime_id, status, updated_at FROM user_lists WHERE user_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $status = (string)$row['status'];
        if (isset($grouped[$status])) {
            $grouped[$status][] = $row;
        }
    }
    return $grouped;
}

function app_save_continue_watching(int $userId, string $animeId, int $episode, int $positionSeconds): void
{
    // NOTE: this used to also INSERT OR IGNORE into watch_history on every
    // call — which meant merely loading an episode page (position 0, zero
    // actual playback) permanently marked it "watched". That broke any
    // watched/in-progress distinction in the UI. watch_history is now only
    // written by app_mark_episode_watched(), called from api/user.php's
    // periodic progress-save (fires every 20s of actual playback + on
    // unload) — so "watched" means the user actually spent time on it.
    $stmt = app_db()->prepare('INSERT INTO continue_watching(user_id, anime_id, episode, position_seconds, updated_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(user_id, anime_id) DO UPDATE SET episode=excluded.episode, position_seconds=excluded.position_seconds, updated_at=excluded.updated_at');
    $stmt->execute([$userId, $animeId, max(1, $episode), max(0, $positionSeconds), gmdate('c')]);
}

function app_get_watched_episodes(int $userId, string $animeId): array
{
    $stmt = app_db()->prepare('SELECT episode FROM watch_history WHERE user_id = ? AND anime_id = ?');
    $stmt->execute([$userId, $animeId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Most recently watched episodes of THIS anime, newest first — for the
// sidebar's "Recently Watched" quick-access row.
function app_get_recent_watched_episodes(int $userId, string $animeId, int $limit = 5): array
{
    $stmt = app_db()->prepare('SELECT episode, watched_at FROM watch_history WHERE user_id = ? AND anime_id = ? ORDER BY watched_at DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $animeId, PDO::PARAM_STR);
    $stmt->bindValue(3, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_get_continue_watching_position(int $userId, string $animeId, int $episode): int
{
    $stmt = app_db()->prepare('SELECT position_seconds FROM continue_watching WHERE user_id = ? AND anime_id = ? AND episode = ?');
    $stmt->execute([$userId, $animeId, $episode]);
    $pos = $stmt->fetchColumn();
    return $pos !== false ? max(0, (int)$pos) : 0;
}

function app_continue_watching_list(int $userId, int $limit = 12): array
{
    $stmt = app_db()->prepare('SELECT anime_id, episode, position_seconds, updated_at FROM continue_watching WHERE user_id = ? ORDER BY updated_at DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_watch_history_list(int $userId, int $limit = 100): array
{
    $stmt = app_db()->prepare('SELECT anime_id, episode, watched_at FROM watch_history WHERE user_id = ? ORDER BY watched_at DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_mark_episode_watched(int $userId, string $animeId, int $episode): void
{
    $stmt = app_db()->prepare('INSERT OR IGNORE INTO watch_history(user_id, anime_id, episode, watched_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $animeId, max(1, $episode), gmdate('c')]);
}
