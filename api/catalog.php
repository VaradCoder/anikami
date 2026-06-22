<?php
require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../services/AnimeService.php';

header('Content-Type: application/json; charset=utf-8');

$section = trim((string)($_GET['section'] ?? ''));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$animeService = new AnimeService();

$catalogMap = [
    'popular' => [
        'list_fn' => fn() => $animeService->getPopularList(24, $page),
        'item_kind' => 'anime',
        'public_path' => '/popular',
    ],
    'new-season' => [
        'list_fn' => fn() => $animeService->getSeasonalList(24, $page),
        'item_kind' => 'anime',
        'public_path' => '/new-season',
    ],
    'latest-subbed' => ['item_kind' => 'episode', 'public_path' => '/latest/subbed'],
    'latest-dubbed' => ['item_kind' => 'episode', 'public_path' => '/latest/dubbed'],
    'latest-chinese' => ['item_kind' => 'episode', 'public_path' => '/latest/chinese'],
];

if ($section === '' || !isset($catalogMap[$section])) {
    app_api_error('invalid_catalog_section', 400, ['page' => $page], $section);
}

if (in_array($section, ['latest-subbed', 'latest-dubbed', 'latest-chinese'], true)) {
    $resp = $animeService->getAiringList(24, $page);
    $items = [];
    $label = ($section === 'latest-dubbed') ? 'DUB' : (($section === 'latest-chinese') ? 'CHINESE' : 'SUB');
    foreach ($resp['data'] ?? [] as $row) {
        $item = legacy_normalize_item($row);
        $items[] = [
            'episodeId' => $item['animeId'] . '-episode-1',
            'episodeNum' => '1',
            'subOrDub' => $label,
            'imgUrl' => $item['animeImg'],
            'name' => $item['animeTitle'],
            'animeId' => $item['animeId'],
        ];
    }
    $lastPage = (int)($resp['pagination']['last_visible_page'] ?? 1);
    app_api_success([
        'section' => $section,
        'items' => $items,
        'pagination_html' => legacy_pagination_html($page, $lastPage, [], $catalogMap[$section]['public_path']),
    ], [
        'page' => $page,
        'item_kind' => 'episode',
        'count' => count($items),
    ]);
    exit;
}

$config = $catalogMap[$section];
$resp = $config['list_fn']();
$items = [];
foreach ($resp['data'] ?? [] as $row) {
    $items[] = legacy_normalize_item($row);
}
$lastPage = (int)($resp['pagination']['last_visible_page'] ?? 1);
$paginationHtml = legacy_pagination_html($page, $lastPage, [], $config['public_path']);

app_api_success([
    'section' => $section,
    'items' => $items,
    'pagination_html' => $paginationHtml,
], [
    'page' => $page,
    'item_kind' => $config['item_kind'],
    'count' => count($items),
]);
