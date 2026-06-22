<?php
require_once __DIR__ . '/AbstractStreamProvider.php';

class VidNestProvider extends AbstractStreamProvider
{
    public function getKey(): string { return 'vidnest'; }
    public function getName(): string { return 'VidNest'; }

    public function getEpisodeUrl(int $anilistId, int $episodeNum): array
    {
        return [
            'serverId' => 'vidnest', 'name' => 'VidNest', 'providerId' => 'vidnest',
            'playbackUrl' => 'https://vidnest.fun/anime/' . $anilistId . '/' . $episodeNum . '/sub',
            'type' => 'iframe', 'quality' => null, 'lang' => 'sub', 'subtitles' => [], 'headers' => [],
            'metadata' => ['anilistId' => $anilistId],
        ];
    }

    protected function getHealthCheckUrl(): string
    {
        return 'https://vidnest.fun/anime/21/1'; // One Piece — long-running, always has fresh episodes
    }
}
