<?php
/**
 * Thin OOP facade over the existing fetchAPI() transport (_config.php).
 * Deliberately does NOT reimplement HTTP/caching/429-retry/AniList-fallback
 * logic — that's already battle-tested in fetchAPI()/legacy_http_get(), and
 * re-doing it here would risk silently changing behavior across the 31
 * call sites that depend on it. This class exists so Phase 3's service
 * layer (AnimeService/EpisodeService/SearchService) has a clean object to
 * call instead of a free function, with zero change to what actually
 * happens on the wire today.
 *
 * Jikan remains the primary metadata source; fetchAPI() already falls back
 * to AniList internally when Jikan is unusable.
 */
class JikanProvider
{
    public function searchAnime(string $query, int $limit = 24, int $page = 1): array
    {
        $endpoint = 'anime?q=' . rawurlencode($query) . '&limit=' . max(1, min(25, $limit)) . '&page=' . max(1, $page) . '&sfw=false';
        return fetchAPI($endpoint);
    }

    public function getAnime(int $malId): array
    {
        return fetchAPI('anime/' . $malId . '/full');
    }

    public function getEpisodes(int $malId, int $page = 1): array
    {
        return fetchAPI('anime/' . $malId . '/episodes?page=' . max(1, $page));
    }

    public function getCharacters(int $malId): array
    {
        return fetchAPI('anime/' . $malId . '/characters');
    }
}
