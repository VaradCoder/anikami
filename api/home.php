<?php
require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../services/AnimeService.php';

header('Content-Type: application/json; charset=utf-8');

$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$cacheKey = 'api_home_v2:' . $page;
$cached   = getCache($cacheKey);
if (is_array($cached)) {
    app_api_success($cached['data'] ?? [], $cached['meta'] ?? ['page' => $page]);
    exit;
}

// Three distinct lists, fetched concurrently via AnimeService
$animeService = new AnimeService();
$_homeLists  = $animeService->getHomeLists(24, 24, 24, $page);
$airingResp  = $_homeLists['airing'];
$popularResp = $_homeLists['popular'];
$seasonResp  = $_homeLists['seasonal'];

$normalizeAnimeList = static function (array $resp): array {
    $rows = is_array($resp['data'] ?? null) ? $resp['data'] : [];
    $out  = [];
    foreach ($rows as $row) {
        $out[] = legacy_normalize_item($row);
    }
    return $out;
};

$buildRecentList = static function (array $items, string $label): array {
    $out = [];
    foreach ($items as $item) {
        $out[] = [
            'episodeId'  => $item['animeId'] . '-episode-1',
            'episodeNum' => '1',
            'subOrDub'   => $label,
            'imgUrl'     => $item['animeImg'],
            'name'       => $item['animeTitle'],
            'animeId'    => $item['animeId'],
        ];
    }
    return $out;
};

$airingItems  = $normalizeAnimeList($airingResp);
$popularItems = $normalizeAnimeList($popularResp);
$seasonItems  = $normalizeAnimeList($seasonResp);

$responseData = [
    // Separate datasets — no duplicates
    'featured'     => array_slice($airingItems, 0, 12),
    'top_airing'   => $airingItems,
    'popular'      => $popularItems,         // ← now uses different endpoint
    'new_season'   => $seasonItems,
    'recent_subbed'=> $buildRecentList($seasonItems, 'SUB'),
    'recent_dubbed'=> $buildRecentList(array_filter($airingItems, function($i){
        return stripos($i['animeTitle'] ?? '', '(Dub)') !== false;
    }), 'DUB'),
];

// Legacy aliases for any code that uses old key names
$responseData['topAiring']      = $responseData['top_airing'];
$responseData['featuredAiring'] = $responseData['featured'];
$responseData['mostPopular']    = $responseData['popular'];
$responseData['recentReleases'] = $responseData['new_season'];
$responseData['recentSubbed']   = $responseData['recent_subbed'];
$responseData['recentDubbed']   = $responseData['recent_dubbed'];
$responseData['featured_items'] = $responseData['featured'];
$responseData['latest_subbed']  = $responseData['recent_subbed'];
$responseData['latest_dubbed']  = $responseData['recent_dubbed'];
$responseData['trending']       = array_slice($popularItems, 0, 12);

$responseMeta = ['page' => $page];
setCache($cacheKey, ['data' => $responseData, 'meta' => $responseMeta], 600);

app_api_success($responseData, $responseMeta);
