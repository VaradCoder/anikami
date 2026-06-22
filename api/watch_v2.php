<?php
require_once __DIR__ . '/../_config.php';
header('Content-Type: application/json; charset=utf-8');

$episodeId = preg_replace('/[^a-zA-Z0-9\-_.]/', '', trim((string)($_GET['episodeId'] ?? '')));
if ($episodeId === '') {
    app_json_response(['ok' => false, 'error' => 'episodeId required'], 400);
    exit;
}

if (preg_match('/^(.*)-episode-([0-9]+)$/', $episodeId, $m)) {
    $animeSlug = $m[1];
    $episodeNum = max(1, (int)$m[2]);
} else {
    $animeSlug = $episodeId;
    $episodeNum = 1;
}

$servers = [];
$pri = 1;

// Cache the full response for 30 minutes to avoid re-resolving AniList/MAL ids on every page load
$_cacheKey = 'watch_v2:' . $episodeId . ':p' . app_stream_providers_version();
$_cached   = getCache($_cacheKey);
if (is_array($_cached) && !empty($_cached['servers'])) {
    app_json_response(['ok' => true, 'data' => ['episodeId' => $episodeId, 'servers' => $_cached['servers']]]);
    exit;
}

// NOTE (verified Jun 2026): api.anify.tv is unreachable (connection times out
// after 8s on every request) and api.consumet.org has shut down (redirects to
// its own "discontinued" GitHub readme). Both direct-source lookups were
// previously tried here across up to 6 sequential requests — costing 30-60s
// of dead-API waiting on every episode load before the working iframe
// fallbacks below ever got a chance to render. Removed entirely rather than
// just lowering timeouts, since neither service returns usable data anymore.

// ── 1. Iframe embeds — these are the working sources ──────────────
// Provider notes (verified Jun 2026, see below for how each was checked):
//  • VidNest / VidNest Dub / VidLink use the ANILIST id (NOT MAL). Confirmed
//    working: HTTP 200, no X-Frame-Options/CSP frame-ancestors restriction.
//  • VidSrc.cc removed — Cloudflare returns a hard 403 to every request AND
//    sets `X-Frame-Options: SAMEORIGIN`, which blocks iframe embedding even
//    if the request succeeded.
//  • 2Anime removed — 2anime.xyz domain itself is dead; even the root domain
//    returns HTTP 403 "The content of the page cannot be displayed".
$anime = legacy_resolve_anime_by_slug($animeSlug);
$aniId = (int)($anime['anilist_id'] ?? 0);
$iframePri = max($pri, 10); // put iframes after any direct streams

// All provider URL-building, ordering, and enable/disable state now lives
// in StreamProviderManager — this used to be a hardcoded array here that
// had drifted out of sync with streaming.php's own separate copy.
require_once __DIR__ . '/../services/StreamProviderManager.php';
if ($aniId > 0) {
    $manager = new StreamProviderManager();
    foreach ($manager->getEpisodeServers($aniId, $episodeNum, $iframePri) as $server) {
        $servers[] = $server;
    }
}

// Cache the resolved server list so the next visit for the same episode is instant
if (!empty($servers)) {
    setCache($_cacheKey, ['servers' => $servers], 1800); // 30-min cache
}

// NOTE: legacy_get_episode_payload() was called here previously, but its
// result (the "payload" field) was never read by the frontend JS in
// streaming.php — only data.data.servers is consumed. That call made a real
// HTTP scrape against anitaku.to (~0.6-0.8s) on every cache miss for data
// nobody used. Removed; legacy_get_episode_payload() is still used by the
// v1 legacy_api_array() getEpisode endpoint, untouched here.
app_json_response([
    'ok' => true,
    'data' => [
        'episodeId' => $episodeId,
        'servers' => $servers,
    ],
]);
