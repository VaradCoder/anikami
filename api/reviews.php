<?php
require_once __DIR__ . '/../_config.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $animeId = trim((string)($_GET['anime_id'] ?? ''));
    if ($animeId === '') app_json_response(['ok' => false, 'error' => 'anime_id required'], 400);
    $sort = ($_GET['sort'] ?? '') === 'recent' ? 'recent' : 'helpful';
    $viewer = app_current_user();
    $items = app_review_list($animeId, $viewer ? (int)$viewer['id'] : null, false, $sort);
    $summary = app_review_rating_summary($animeId);
    $myReview = $viewer ? app_review_get_by_user((int)$viewer['id'], $animeId) : null;
    app_json_response(['ok' => true, 'items' => $items, 'summary' => $summary, 'my_review' => $myReview]);
}

$user = app_current_user();
if (!$user) {
    app_json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !app_validate_csrf($_POST['csrf'] ?? '')) {
    app_json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
}
$isAdmin = ($user['role'] ?? '') === 'admin';

if ($action === 'upsert') {
    $animeId = trim((string)($_POST['anime_id'] ?? ''));
    $rating = (int)($_POST['rating'] ?? 0);
    $body = (string)($_POST['body'] ?? '');
    $hasSpoilers = !empty($_POST['has_spoilers']);
    if ($animeId === '' || $rating < 1 || $rating > 10) {
        app_json_response(['ok' => false, 'error' => 'anime_id and a rating 1-10 are required'], 400);
    }
    $id = app_review_upsert((int)$user['id'], $animeId, $rating, $body, $hasSpoilers);
    if ($id === false) app_json_response(['ok' => false, 'error' => 'Invalid review body'], 400);
    app_track_event('review_create', $animeId, ['review_id' => $id, 'rating' => $rating]);
    app_json_response(['ok' => true, 'id' => $id]);
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $ok = app_review_delete($id, (int)$user['id'], $isAdmin);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Not found or not yours'], 400);
    if ($isAdmin) app_audit_log('review_delete', 'review', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'helpful') {
    $id = (int)($_POST['id'] ?? 0);
    if (!app_review_get($id)) app_json_response(['ok' => false, 'error' => 'Not found'], 404);
    $voted = app_review_toggle_helpful($id, (int)$user['id']);
    app_json_response(['ok' => true, 'voted' => $voted]);
}

if ($action === 'feature' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $featured = !empty($_POST['featured']);
    app_review_set_featured($id, $featured);
    app_audit_log($featured ? 'review_feature' : 'review_unfeature', 'review', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'hide' && $isAdmin) {
    $id = (int)($_POST['id'] ?? 0);
    $hidden = !empty($_POST['hidden']);
    app_review_set_hidden($id, $hidden);
    app_audit_log($hidden ? 'review_hide' : 'review_unhide', 'review', (string)$id);
    app_json_response(['ok' => true]);
}

if ($action === 'report') {
    $targetId = (string)($_POST['target_id'] ?? '');
    $reason = (string)($_POST['reason'] ?? '');
    $details = (string)($_POST['details'] ?? '');
    if ($targetId === '') app_json_response(['ok' => false, 'error' => 'target_id required'], 400);
    $ok = app_report_create('review', $targetId, (int)$user['id'], $reason, $details);
    if (!$ok) app_json_response(['ok' => false, 'error' => 'Invalid reason'], 400);
    app_json_response(['ok' => true]);
}

app_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
