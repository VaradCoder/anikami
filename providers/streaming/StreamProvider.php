<?php
/**
 * Contract every streaming provider must implement. Keeps watch_v2.php and
 * streaming.php from building provider-specific URL logic themselves —
 * that was the actual bug Phase 1 exists to fix: the two files had drifted
 * into two separate hardcoded copies of the same 3 providers.
 */
interface StreamProvider
{
    public function getKey(): string;
    public function getName(): string;

    /**
     * @param int $anilistId
     * @param int $episodeNum
     * @return array Shape matches what streaming.php/watch_v2.php already
     *   emit to the frontend (serverId/name/providerId/playbackUrl/type/
     *   lang/subtitles/headers/metadata) — unchanged on purpose, so the
     *   player JS needs zero changes for this refactor.
     */
    public function getEpisodeUrl(int $anilistId, int $episodeNum): array;

    /**
     * Pings the provider's own domain (not a specific episode — we can't
     * render their embedded player from PHP). Returns the same shape
     * app_check_stream_health() already produces, so admin/streams.php
     * doesn't need to change.
     */
    public function healthCheck(): array;
}
