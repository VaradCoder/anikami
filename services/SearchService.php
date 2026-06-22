<?php
require_once __DIR__ . '/../providers/metadata/JikanProvider.php';

/**
 * Wraps search.php's exact existing logic (own 1h cache keyed by
 * keyword+page, fetchAPI call, legacy_normalize_item) so the page no
 * longer builds a Jikan query string itself. searchRaw() exists for the
 * "search disguised as a listing" pages (latest/dubbed.php searches for
 * "(Dub)" in the title) — same transport, no page-level cache of its own.
 */
class SearchService
{
    private JikanProvider $jikan;

    public function __construct()
    {
        $this->jikan = new JikanProvider();
    }

    /** @return array{results: array, lastPage: int, total: int} */
    public function searchAnime(string $keyword, int $page = 1): array
    {
        $cacheKey = 'search:' . md5($keyword . ':' . $page);
        $cached = getCache($cacheKey);
        if ($cached && is_array($cached)) {
            return ['results' => $cached['results'] ?? [], 'lastPage' => $cached['lastPage'] ?? 1, 'total' => $cached['total'] ?? 0];
        }

        $resp = $this->jikan->searchAnime($keyword, 24, $page);
        $results = [];
        $lastPage = 1;
        $total = 0;
        if (!empty($resp['data']) && is_array($resp['data'])) {
            foreach ($resp['data'] as $row) {
                $results[] = legacy_normalize_item($row);
            }
            $lastPage = (int)($resp['pagination']['last_visible_page'] ?? 1);
            $total = (int)($resp['pagination']['items']['total'] ?? count($results));
            setCache($cacheKey, ['results' => $results, 'lastPage' => $lastPage, 'total' => $total], 3600);
        }
        return ['results' => $results, 'lastPage' => $lastPage, 'total' => $total];
    }

    /** Raw title-fragment search, no page-level cache — caller owns caching. */
    public function searchRaw(string $rawQuery, int $limit = 24, int $page = 1): array
    {
        return fetchAPI('anime?q=' . $rawQuery . '&limit=' . $limit . '&page=' . $page);
    }
}
