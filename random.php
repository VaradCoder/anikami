<?php
require('./_config.php');
require_once __DIR__ . '/services/AnimeService.php';

$resp = (new AnimeService())->getRandom();
$animeId = '';

if (!empty($resp['data']['mal_id'])) {
    // Build slug from title + mal_id
    $title = $resp['data']['title'] ?? '';
    $slug  = legacy_slugify($title);
    if ($slug !== '') {
        $animeId = $slug;
    } else {
        $animeId = (string)$resp['data']['mal_id'];
    }
}

if ($animeId !== '') {
    header('Location: ' . $websiteUrl . '/anime/' . rawurlencode($animeId));
    exit;
}

// Fallback: redirect to popular
header('Location: ' . $websiteUrl . '/popular');
exit;
