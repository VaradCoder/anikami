<?php
require_once __DIR__ . '/../_config.php';
$user = app_require_admin();

$action = $_REQUEST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !app_validate_csrf($_POST['csrf'] ?? '')) {
    app_json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
}

if ($action === 'clear_cache') {
    app_clear_all_cache();
    app_json_response(['ok' => true]);
}

if ($action === 'set_slug_override') {
    $animeKey = trim((string)($_POST['anime_key'] ?? ''));
    $slug = trim((string)($_POST['slug_value'] ?? ''));
    if ($animeKey === '' || $slug === '') {
        app_json_response(['ok' => false, 'error' => 'anime_key and slug_value are required'], 400);
    }
    app_slug_override_set($animeKey, $slug);
    app_json_response(['ok' => true]);
}

if ($action === 'set_featured') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $image = trim((string)($_POST['image_url'] ?? ''));
    $sort = (int)($_POST['sort_order'] ?? 0);
    if ($animeId === '') {
        app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    }
    app_featured_set($animeId, $title, $image, $sort);
    app_json_response(['ok' => true]);
}

if ($action === 'remove_featured') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    if ($animeId === '') {
        app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    }
    app_featured_remove($animeId);
    app_json_response(['ok' => true]);
}

if ($action === 'ban_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id <= 0) {
        app_json_response(['ok' => false, 'error' => 'user_id required'], 400);
    }
    $stmt = app_db()->prepare('UPDATE users SET is_banned = 1, updated_at = ? WHERE id = ?');
    $stmt->execute([gmdate('c'), $id]);
    app_json_response(['ok' => true]);
}

if ($action === 'unban_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id <= 0) {
        app_json_response(['ok' => false, 'error' => 'user_id required'], 400);
    }
    $stmt = app_db()->prepare('UPDATE users SET is_banned = 0, updated_at = ? WHERE id = ?');
    $stmt->execute([gmdate('c'), $id]);
    app_json_response(['ok' => true]);
}

if ($action === 'delete_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    if ($id <= 0) {
        app_json_response(['ok' => false, 'error' => 'user_id required'], 400);
    }
    $stmt = app_db()->prepare('DELETE FROM users WHERE id = ? AND role != "admin"');
    $stmt->execute([$id]);
    app_json_response(['ok' => true]);
}

app_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
