<?php
require_once __DIR__ . '/../_config.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $animeId = trim((string)($_GET['anime_id'] ?? ''));
    if ($animeId === '') app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    $episodeRaw = $_GET['episode'] ?? null;
    $episode = ($episodeRaw === null || $episodeRaw === '') ? null : (int)$episodeRaw;
    $viewer = app_current_user();
    $tree = app_comment_list($animeId, $episode, $viewer ? (int)$viewer['id'] : null);
    app_json_response(['ok' => true, 'items' => $tree, 'count' => app_comment_count($animeId, $episode)]);
}

// Everything below requires a logged-in user + CSRF.
$user = app_current_user();
if (!$user) {
    app_json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !app_validate_csrf($_POST['csrf'] ?? '')) {
    app_json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
}
$isAdmin = ($user['role'] ?? '') === 'admin';

if ($action === 'create') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    $episodeRaw = $_POST['episode'] ?? null;
    $episode = ($episodeRaw === null || $episodeRaw === '') ? null : (int)$episodeRaw;
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $body = (string)($_POST['body'] ?? '');
    $isSpoiler = !empty($_POST['is_spoiler']);
    if ($animeId === '') app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);

    $id = app_comment_create((int)$user['id'], $animeId, $episode, $parentId, $body, $isSpoiler);
    if ($id === false) app_json_response(['ok' => false, 'error' => 'Invalid comment'], 400);
    app_track_event('comment_create', $animeId, ['episode' => $episode, 'comment_id' => $id]);
    app_json_response(['ok' => true, 'id' => $id]);
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $body = (string)($_POST['body'] ?? '');
    $ok = app_comment_update($id, (int)$user['id'], $body);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found, not yours, or invalid body'], 400);
    app_json_response(['ok' => true]);
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $ok = app_comment_delete($id, (int)$user['id'], $isAdmin);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found or not yours'], 400);
    if ($isAdmin) app_audit_log('comment_delete', 'comment', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'like') {
    $id = (int)($_POST['id'] ?? 0);
    if (!app_comment_get($id)) app_json_response(['ok' => false, 'error' => 'Not found'], 404);
    $liked = app_comment_toggle_like($id, (int)$user['id']);
    app_json_response(['ok' => true, 'liked' => $liked]);
}

if ($action === 'pin' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $pinned = !empty($_POST['pinned']);
    app_comment_set_pinned($id, $pinned);
    app_audit_log($pinned ? 'comment_pin' : 'comment_unpin', 'comment', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'hide' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $hidden = !empty($_POST['hidden']);
    app_comment_set_hidden($id, $hidden);
    app_audit_log($hidden ? 'comment_hide' : 'comment_unhide', 'comment', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'report') {
    $targetType = (string)($_POST['target_type'] ?? 'comment');
    $targetId = (string)($_POST['target_id'] ?? '');
    $reason = (string)($_POST['reason'] ?? '');
    $details = (string)($_POST['details'] ?? '');
    if ($targetId === '') app_json_response(['ok' => false, 'error' => 'target_id required'], 400);
    $ok = app_report_create($targetType, $targetId, (int)$user['id'], $reason, $details);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Invalid reason'], 400);
    app_json_response(['ok' => true]);
}

app_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
