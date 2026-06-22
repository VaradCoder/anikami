<?php
require_once __DIR__ . '/AbstractStreamProvider.php';

class VidNestDubProvider extends AbstractStreamProvider
{
    public function getKey(): string { return 'vidnest-dub'; }
    public function getName(): string { return 'VidNest Dub'; }

    public function getEpisodeUrl(int $anilistId, int $episodeNum): array
    {
        return [
            'serverId' => 'vidnest-dub', 'name' => 'VidNest Dub', 'providerId' => 'vidnestd',
            'playbackUrl' => 'https://vidnest.fun/anime/' . $anilistId . '/' . $episodeNum . '/dub',
            'type' => 'iframe', 'quality' => null, 'lang' => 'dub', 'subtitles' => [], 'headers' => [],
        ];
    }

    protected function getHealthCheckUrl(): string
    {
        return 'https://vidnest.fun/anime/21/1/dub';
    }
}
