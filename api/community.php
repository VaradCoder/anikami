<?php
require_once __DIR__ . '/../_config.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $category = trim((string)($_GET['category'] ?? ''));
    if ($category !== '' && !in_array($category, APP_COMMUNITY_CATEGORIES, true)) {
        app_json_response(['ok' => false, 'error' => 'Invalid category'], 400);
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $posts = app_community_post_list($category !== '' ? $category : null, $limit, ($page - 1) * $limit);
    $total = app_community_post_count($category !== '' ? $category : null);
    app_json_response(['ok' => true, 'items' => $posts, 'total' => $total, 'page' => $page, 'lastPage' => max(1, (int)ceil($total / $limit))]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $post = app_community_post_get($id);
    $viewer = app_current_user();
    $viewerIsAdmin = $viewer && ($viewer['role'] ?? '') === 'admin';
    if (!$post || ((int)$post['is_hidden'] === 1 && !$viewerIsAdmin)) {
        app_json_response(['ok' => false, 'error' => 'Not found'], 404);
    }
    $replies = app_community_reply_list($id, $viewer ? (int)$viewer['id'] : null);
    app_json_response(['ok' => true, 'post' => $post, 'replies' => $replies]);
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

if ($action === 'create_post') {
    $category = (string)($_POST['category'] ?? 'general');
    $title = (string)($_POST['title'] ?? '');
    $body = (string)($_POST['body'] ?? '');
    $id = app_community_post_create((int)$user['id'], $category, $title, $body);
    if ($id === false) app_json_response(['ok' => false, 'error' => 'Invalid title or body'], 400);
    app_track_event('community_post_create', null, ['post_id' => $id]);
    app_json_response(['ok' => true, 'id' => $id]);
}

if ($action === 'update_post') {
    $id = (int)($_POST['id'] ?? 0);
    $title = (string)($_POST['title'] ?? '');
    $body = (string)($_POST['body'] ?? '');
    $ok = app_community_post_update($id, (int)$user['id'], $title, $body);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found, not yours, or invalid'], 400);
    app_json_response(['ok' => true]);
}

if ($action === 'delete_post') {
    $id = (int)($_POST['id'] ?? 0);
    $ok = app_community_post_delete($id, (int)$user['id'], $isAdmin);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found or not yours'], 400);
    if ($isAdmin) app_audit_log('community_post_delete', 'community_post', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'like_post') {
    $id = (int)($_POST['id'] ?? 0);
    if (!app_community_post_get($id)) app_json_response(['ok' => false, 'error' => 'Not found'], 404);
    $liked = app_community_post_toggle_like($id, (int)$user['id']);
    app_json_response(['ok' => true, 'liked' => $liked]);
}

if ($action === 'pin_post' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $pinned = !empty($_POST['pinned']);
    app_community_post_set_pinned($id, $pinned);
    app_audit_log($pinned ? 'community_post_pin' : 'community_post_unpin', 'community_post', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'hide_post' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $hidden = !empty($_POST['hidden']);
    app_community_post_set_hidden($id, $hidden);
    app_audit_log($hidden ? 'community_post_hide' : 'community_post_unhide', 'community_post', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'reply') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $body = (string)($_POST['body'] ?? '');
    if (!app_community_post_get($postId)) app_json_response(['ok' => false, 'error' => 'Post not found'], 404);
    $id = app_community_reply_create($postId, (int)$user['id'], $parentId, $body);
    if ($id === false) app_json_response(['ok' => false, 'error' => 'Invalid reply'], 400);
    app_track_event('community_reply_create', null, ['post_id' => $postId, 'reply_id' => $id]);
    app_json_response(['ok' => true, 'id' => $id]);
}

if ($action === 'update_reply') {
    $id = (int)($_POST['id'] ?? 0);
    $body = (string)($_POST['body'] ?? '');
    $ok = app_community_reply_update($id, (int)$user['id'], $body);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found, not yours, or invalid body'], 400);
    app_json_response(['ok' => true]);
}

if ($action === 'delete_reply') {
    $id = (int)($_POST['id'] ?? 0);
    $ok = app_community_reply_delete($id, (int)$user['id'], $isAdmin);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found or not yours'], 400);
    if ($isAdmin) app_audit_log('community_reply_delete', 'community_reply', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'like_reply') {
    $id = (int)($_POST['id'] ?? 0);
    if (!app_community_reply_get($id)) app_json_response(['ok' => false, 'error' => 'Not found'], 404);
    $liked = app_community_reply_toggle_like($id, (int)$user['id']);
    app_json_response(['ok' => true, 'liked' => $liked]);
}

if ($action === 'hide_reply' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $hidden = !empty($_POST['hidden']);
    app_community_reply_set_hidden($id, $hidden);
    app_audit_log($hidden ? 'community_reply_hide' : 'community_reply_unhide', 'community_reply', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'report') {
    $targetType = (string)($_POST['target_type'] ?? 'community_post');
    $targetId = (string)($_POST['target_id'] ?? '');
    $reason = (string)($_POST['reason'] ?? '');
    $details = (string)($_POST['details'] ?? '');
    if ($targetId === '') app_json_response(['ok' => false, 'error' => 'target_id required'], 400);
    $ok = app_report_create($targetType, $targetId, (int)$user['id'], $reason, $details);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Invalid reason'], 400);
    app_json_response(['ok' => true]);
}

app_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
