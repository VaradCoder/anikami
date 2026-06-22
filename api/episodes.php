<?php
require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    $slug = trim((string)($_GET['anime'] ?? ''));
}

if ($slug === '') {
    app_json_response(['ok' => false, 'error' => 'slug required'], 400);
}

$context = legacy_get_watch_context($slug);
$episodes = $context['episodes'] ?? [];

app_json_response([
    'ok' => true,
    'data' => [
        'slug' => $slug,
        'episodes' => is_array($episodes) ? $episodes : [],
        'total_episodes' => is_array($episodes) ? count($episodes) : 0,
    ],
]);

