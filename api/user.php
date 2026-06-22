<?php
require_once __DIR__ . '/../_config.php';

$action = $_REQUEST['action'] ?? '';
$user = app_current_user();
if (!$user) {
    app_json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !app_validate_csrf($_POST['csrf'] ?? '')) {
    app_json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
}

if ($action === 'progress') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    $episode = (int)($_POST['episode'] ?? 1);
    $position = (int)($_POST['position_seconds'] ?? 0);
    if ($animeId === '') {
        app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    }
    app_save_continue_watching((int)$user['id'], $animeId, $episode, $position);
    app_mark_episode_watched((int)$user['id'], $animeId, $episode);
    app_track_event('episode_view', $animeId, ['episode' => $episode, 'position_seconds' => $position]);
    app_json_response(['ok' => true]);
}

if ($action === 'watchlist_toggle') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    if ($animeId === '') {
        app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    }

    if (app_watchlist_has((int)$user['id'], $animeId)) {
        app_watchlist_remove((int)$user['id'], $animeId);
        app_json_response(['ok' => true, 'in_watchlist' => false]);
    }

    app_watchlist_add((int)$user['id'], $animeId);
    app_json_response(['ok' => true, 'in_watchlist' => true]);
}

if ($action === 'list_status_set') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    $status = trim((string)($_POST['status'] ?? ''));
    if ($animeId === '') {
        app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    }
    if ($status === 'remove') {
        app_remove_anime_list_status((int)$user['id'], $animeId);
        app_json_response(['ok' => true, 'status' => null]);
    }
    if (!app_set_anime_list_status((int)$user['id'], $animeId, $status)) {
        app_json_response(['ok' => false, 'error' => 'Invalid status'], 400);
    }
    app_json_response(['ok' => true, 'status' => $status]);
}

if ($action === 'continue_list') {
    app_json_response(['ok' => true, 'items' => app_continue_watching_list((int)$user['id'], 12)]);
}

if ($action === 'watchlist_list') {
    app_json_response(['ok' => true, 'items' => app_watchlist_list((int)$user['id'], 100)]);
}

if ($action === 'history_list') {
    app_json_response(['ok' => true, 'items' => app_watch_history_list((int)$user['id'], 100)]);
}

app_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
