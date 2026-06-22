<?php
require_once __DIR__ . '/../../services/CacheService.php';

/**
 * OOP facade over AniList. Existing capabilities (search, trending,
 * seasonal/popular lists) delegate straight to the existing
 * legacy_anilist_search_rows()/legacy_anilist_media_list() functions —
 * zero behavior change, same transport (legacy_anilist_graphql(), which
 * already caches for 1h and handles malformed responses).
 *
 * getAnime()/getCharacters()/getRecommendations()/getSchedule() are NEW
 * capabilities — nothing in the codebase calls these AniList query shapes
 * today, so they're added here with their own GraphQL queries on the same
 * transport, wrapped in CacheService with the TTLs the architecture spec
 * calls for. Nothing calls these methods yet; they're inert until Phase 3
 * wires a service to them.
 *
 * Jikan stays the primary metadata source site-wide; this class does not
 * change that — it's the fallback target fetchAPI() already uses.
 */
class AniListProvider
{
    public function searchAnime(string $query, int $limit = 12): array
    {
        return legacy_anilist_search_rows($query, $limit);
    }

    public function getTrending(int $page = 1, int $perPage = 25): array
    {
        return legacy_anilist_media_list('trending', $page, $perPage);
    }

    public function getSeasonal(int $page = 1, int $perPage = 25): array
    {
        return legacy_anilist_media_list('seasonal', $page, $perPage);
    }

    public function getAnime(int $anilistId): array
    {
        return CacheService::remember('anilist:media:' . $anilistId, CacheService::TTL_ANIME_DETAILS, function () use ($anilistId) {
            $query = <<<'GQL'
query ($id: Int) {
  Media(id: $id, type: ANIME) {
    id idMal format status seasonYear episodes duration
    coverImage { extraLarge large medium }
    bannerImage
    title { romaji english native }
    description
    genres
    studios { nodes { id name } }
    relations {
      edges {
        relationType
        node { id idMal title { romaji english } format }
      }
    }
  }
}
GQL;
            $resp = legacy_anilist_graphql($query, ['id' => $anilistId]);
            return $resp['data']['Media'] ?? null;
        }) ?: [];
    }

    public function getCharacters(int $anilistId): array
    {
        return CacheService::remember('anilist:characters:' . $anilistId, CacheService::TTL_CHARACTERS, function () use ($anilistId) {
            $query = <<<'GQL'
query ($id: Int) {
  Media(id: $id, type: ANIME) {
    characters(sort: ROLE, perPage: 25) {
      edges {
        role
        node { id name { full } image { large medium } }
      }
    }
  }
}
GQL;
            $resp = legacy_anilist_graphql($query, ['id' => $anilistId]);
            return $resp['data']['Media']['characters']['edges'] ?? [];
        }) ?: [];
    }

    public function getRecommendations(int $anilistId): array
    {
        return CacheService::remember('anilist:recommendations:' . $anilistId, CacheService::TTL_RECOMMENDATIONS, function () use ($anilistId) {
            $query = <<<'GQL'
query ($id: Int) {
  Media(id: $id, type: ANIME) {
    recommendations(sort: RATING_DESC, perPage: 12) {
      nodes {
        mediaRecommendation {
          id idMal format
          coverImage { extraLarge large medium }
          title { romaji english native }
        }
      }
    }
  }
}
GQL;
            $resp = legacy_anilist_graphql($query, ['id' => $anilistId]);
            return $resp['data']['Media']['recommendations']['nodes'] ?? [];
        }) ?: [];
    }

    public function getSchedule(int $page = 1, int $perPage = 25): array
    {
        return CacheService::remember('anilist:schedule:p' . $page, CacheService::TTL_SCHEDULE, function () use ($page, $perPage) {
            $now = time();
            $query = <<<'GQL'
query ($page: Int, $perPage: Int, $airingAtGreater: Int) {
  Page(page: $page, perPage: $perPage) {
    pageInfo { currentPage lastPage hasNextPage }
    airingSchedules(airingAt_greater: $airingAtGreater, sort: TIME) {
      id airingAt episode
      media {
        id idMal format
        coverImage { extraLarge large medium }
        title { romaji english native }
      }
    }
  }
}
GQL;
            $resp = legacy_anilist_graphql($query, ['page' => $page, 'perPage' => $perPage, 'airingAtGreater' => $now]);
            return $resp['data']['Page']['airingSchedules'] ?? [];
        }) ?: [];
    }
}
