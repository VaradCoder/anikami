<?php
require_once __DIR__ . '/../_config.php';
app_require_admin();
header('Content-Type: application/json; charset=utf-8');

// Polling, not push — this app has no websocket/SSE infra. Refreshing every
// ~15s from the client is an honest "real-time-ish" feed on this stack.
$events = [];

$regs = app_db()->query("SELECT username, created_at AS ts FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();
foreach ($regs as $r) {
    $events[] = ['type' => 'register', 'text' => '<b>' . app_e($r['username']) . '</b> registered', 'ts' => $r['ts'], 'icon' => 'fa-user-plus'];
}

$views = app_db()->query("SELECT anime_id, user_id, created_at AS ts FROM metrics_events WHERE event_type = 'episode_view' ORDER BY created_at DESC LIMIT 8")->fetchAll();
foreach ($views as $v) {
    $title = function_exists('legacy_unslug') ? legacy_unslug((string)$v['anime_id']) : (string)$v['anime_id'];
    $events[] = ['type' => 'watch', 'text' => 'Episode watched: <b>' . app_e($title) . '</b>', 'ts' => $v['ts'], 'icon' => 'fa-play'];
}

usort($events, function ($a, $b) { return strcmp($b['ts'], $a['ts']); });
$events = array_slice($events, 0, 15);

app_json_response(['ok' => true, 'items' => $events]);
