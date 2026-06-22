<?php
require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');

$keyword = trim((string)($_GET['keyword'] ?? ''));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if ($keyword === '') {
    app_api_error('keyword_required', 400);
}

$cacheKey = 'search:' . md5($keyword . ':' . $page);
$cached = getCache($cacheKey);
if (is_array($cached)) {
    app_api_success($cached['data'] ?? [], $cached['meta'] ?? ['page' => $page]);
    exit;
}

[$results, $lastPage] = legacy_search_payload($keyword, $page);
$paginationHtml = legacy_pagination_html($page, $lastPage, ['keyword' => $keyword], '/search');

$responseData = [
    'keyword' => $keyword,
    'results' => is_array($results) ? $results : [],
    'pagination_html' => $paginationHtml,
];
$responseMeta = ['page' => $page];

setCache($cacheKey, ['data' => $responseData, 'meta' => $responseMeta], 3600);

app_api_success($responseData, $responseMeta);
