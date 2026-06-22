<?php
/**
 * Facade over the existing episode-resolution logic in _config.php
 * (legacy_get_watch_context(), legacy_get_episode_payload()) — both
 * already self-contained, cached, and not duplicated elsewhere, so this
 * wraps rather than reimplements them. Not yet called by any page;
 * streaming.php/watch_v2.php currently call legacy_resolve_anime_by_slug()
 * directly for just the anilist_id, which is a narrower need than full
 * episode-list resolution and is left alone here.
 */
class EpisodeService
{
    public function getWatchContext(string $animeSlug): array
    {
        return legacy_get_watch_context($animeSlug);
    }

    public function getEpisodeCount(string $animeSlug): int
    {
        $context = $this->getWatchContext($animeSlug);
        return count($context['episodes'] ?? []);
    }

    public function getEpisodePayload(string $episodeId): array
    {
        return legacy_get_episode_payload($episodeId);
    }
}
