<?php
require_once __DIR__ . '/AbstractStreamProvider.php';

class VidLinkProvider extends AbstractStreamProvider
{
    public function getKey(): string { return 'vidlink'; }
    public function getName(): string { return 'VidLink'; }

    public function getEpisodeUrl(int $anilistId, int $episodeNum): array
    {
        return [
            'serverId' => 'vidlink', 'name' => 'VidLink', 'providerId' => 'vidlink',
            'playbackUrl' => 'https://vidlink.pro/anime/' . $anilistId . '/' . $episodeNum . '/sub',
            'type' => 'iframe', 'quality' => null, 'lang' => 'sub', 'subtitles' => [], 'headers' => [],
        ];
    }

    protected function getHealthCheckUrl(): string
    {
        return 'https://vidlink.pro/anime/21/1';
    }
}
