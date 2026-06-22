<?php
require_once __DIR__ . '/../providers/metadata/JikanProvider.php';
require_once __DIR__ . '/../providers/metadata/AniListProvider.php';

/**
 * Single entry point pages use for anime metadata/listing instead of
 * calling fetchAPI()/JikanProvider directly. Each method below is a 1:1,
 * byte-identical wrap of an endpoint string that used to be hardcoded in
 * a page file (home.php, genre/id.php, az-list/id.php, etc.) — this pass
 * is purely "move the call, don't change what it returns."
 */
class AnimeService
{
    private JikanProvider $jikan;
    private AniListProvider $anilist;

    public function __construct()
    {
        $this->jikan = new JikanProvider();
        $this->anilist = new AniListProvider();
    }

    public function getAiringList(int $limit, int $page = 1): array
    {
        return fetchAPI('top/anime?filter=airing&limit=' . $limit . '&page=' . $page);
    }

    public function getPopularList(int $limit, int $page = 1): array
    {
        return fetchAPI('top/anime?filter=bypopularity&limit=' . $limit . '&page=' . $page);
    }

    public function getSeasonalList(int $limit, int $page = 1): array
    {
        return fetchAPI('seasons/now?limit=' . $limit . '&page=' . $page);
    }

    /**
     * Airing + popular + seasonal lists in one concurrent batch instead of
     * 3 sequential fetchAPI() calls — home.php and api/home.php both need
     * exactly these three on every cache miss. On CPU-time-metered hosting
     * this cuts both wall-clock and billed CPU-seconds roughly 3x for the
     * single most-hit page on the site.
     *
     * @return array{airing: array, popular: array, seasonal: array}
     */
    public function getHomeLists(int $airingLimit, int $popularLimit, int $seasonalLimit, int $page = 1): array
    {
        $endpoints = [
            'airing' => 'top/anime?filter=airing&limit=' . $airingLimit . '&page=' . $page,
            'popular' => 'top/anime?filter=bypopularity&limit=' . $popularLimit . '&page=' . $page,
            'seasonal' => 'seasons/now?limit=' . $seasonalLimit . '&page=' . $page,
        ];
        $resultsByEndpoint = fetchAPIMulti(array_values($endpoints));
        $out = [];
        foreach ($endpoints as $label => $endpoint) {
            $out[$label] = $resultsByEndpoint[ltrim($endpoint, '/')] ?? ['data' => [], 'pagination' => []];
        }
        return $out;
    }

    public function getFullByMalId(int $malId): array
    {
        return $malId > 0 ? $this->jikan->getAnime($malId) : [];
    }

    public function getByGenre(int $genreId, int $page, int $limit = 24): array
    {
        return fetchAPI('anime?genres=' . $genreId . '&page=' . $page . '&limit=' . $limit);
    }

    public function getByLetter(string $letter, int $page, int $limit = 24): array
    {
        return fetchAPI('anime?letter=' . rawurlencode($letter) . '&page=' . $page . '&limit=' . $limit . '&order_by=title&sort=asc');
    }

    public function getByProducer(int $producerId, int $page, int $limit, string $orderBy, string $sort): array
    {
        return fetchAPI('anime?producers=' . $producerId . '&page=' . $page . '&limit=' . $limit . '&order_by=' . $orderBy . '&sort=' . $sort);
    }

    public function getByType(string $type, int $page, int $limit): array
    {
        return fetchAPI('top/anime?type=' . rawurlencode($type) . '&page=' . $page . '&limit=' . $limit);
    }

    public function getRandom(): array
    {
        return fetchAPI('random/anime');
    }

    /** New capability (Phase 2's AniListProvider) — not wired into any page yet. */
    public function getCharacters(int $anilistId): array
    {
        return $this->anilist->getCharacters($anilistId);
    }

    /** New capability — not wired into any page yet. */
    public function getRecommendations(int $anilistId): array
    {
        return $this->anilist->getRecommendations($anilistId);
    }
}
