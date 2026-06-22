<?php
require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../services/AnimeService.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    $slug = trim((string)($_GET['anime'] ?? ''));
}

if ($slug === '') {
    app_json_response(['ok' => false, 'error' => 'slug required'], 400);
    exit;
}

$anime = legacy_resolve_anime_by_slug($slug);
if (empty($anime)) {
    app_json_response(['ok' => false, 'error' => 'anime_not_found'], 404);
    exit;
}

$malId = (int)($anime['mal_id'] ?? 0);
$full = (new AnimeService())->getFullByMalId($malId);
$data = !empty($full['data']) && is_array($full['data']) ? $full['data'] : $anime;
$normalized = legacy_normalize_item($data, $slug);
$payload = array_merge($normalized, [
    'name' => $data['title_english'] ?? $data['title'] ?? $data['title_japanese'] ?? ($normalized['animeTitle'] ?? legacy_unslug($slug)),
    'othername' => $data['title_japanese'] ?? '',
    'type' => $data['type'] ?? ($normalized['type'] ?? 'TV'),
    'released' => (string)($data['year'] ?? ($data['aired']['prop']['from']['year'] ?? '')),
    'status' => $data['status'] ?? ($normalized['status'] ?? 'Unknown'),
    'genres' => $data['genres'] ?? [],
    'synopsis' => $data['synopsis'] ?? 'No synopsis available.',
    'imageUrl' => $data['images']['jpg']['large_image_url'] ?? $data['images']['jpg']['image_url'] ?? ($normalized['animeImg'] ?? ''),
]);

app_json_response(['ok' => true, 'data' => $payload]);
