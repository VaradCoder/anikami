<?php
require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');

$episodeId = trim((string)($_GET['episodeId'] ?? ''));
if ($episodeId === '') {
    $episodeId = trim((string)($_GET['episode'] ?? ''));
}

if ($episodeId === '') {
    app_json_response(['ok' => false, 'error' => 'episodeId required'], 400);
}

// Prefer the unified normalized endpoint (watch_v2) for stream resolution.
$watchV2 = app_api_get('/api/watch_v2.php', ['episodeId' => $episodeId]);

if (!is_array($watchV2) || empty($watchV2['ok'])) {
    // Graceful legacy response on failure.
    app_json_response([
        'ok' => true,
        'data' => [
            'episode_id' => $episodeId,
            'anime' => ['slug' => '', 'title' => '', 'image' => ''],
            'episode' => ['number' => 1, 'download' => '', 'prev' => null, 'next' => null],
            'servers' => [],
        ],
    ]);
}

$episodeNum = 1;
$slug = '';
$payload = $watchV2['data']['payload'] ?? [];
if (is_array($payload) && !empty($payload['anime_info'])) {
    $slug = (string)$payload['anime_info'];
}
if (is_array($payload) && isset($payload['ep_num'])) {
    $episodeNum = (int)$payload['ep_num'];
}

$context = $slug !== '' ? legacy_get_watch_context($slug) : [];
$animeData = is_array($context) ? ($context['anime'] ?? []) : [];

$servers = [];
$serversV2 = $watchV2['data']['servers'] ?? [];
if (is_array($serversV2)) {
    foreach ($serversV2 as $srv) {
        $url = (string)($srv['playbackUrl'] ?? '');
        if ($url === '') continue;
        $servers[] = ['name' => (string)($srv['name'] ?? 'Server'), 'url' => $url];
    }
}

// Backward-compatible legacy shape.
$appEpisode = [
    'number' => $episodeNum,
    'download' => $servers[0]['url'] ?? '',
    'prev' => null,
    'next' => null,
];

if (is_array($payload)) {
    $appEpisode['prev'] = !empty($payload['prevEpLink']) ? (string)$payload['prevEpLink'] : null;
    $appEpisode['next'] = !empty($payload['nextEpLink']) ? (string)$payload['nextEpLink'] : null;
}

app_json_response([
    'ok' => true,
    'data' => [
        'episode_id' => $episodeId,
        'anime' => [
            'slug' => $slug,
            'title' => $animeData['title'] ?? ($animeData['title_english'] ?? ''),
            'image' => $animeData['images']['jpg']['large_image_url'] ?? '',
        ],
        'episode' => $appEpisode,
        'servers' => $servers,
    ],
]);


