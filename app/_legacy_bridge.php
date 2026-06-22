<?php
// Legacy bridge helpers to allow new modular code to reuse the existing procedural legacy functions.

require_once __DIR__ . '/../_config.php';

/**
 * Minimal wrapper around current scraping logic used by streaming.php.
 *
 * @return array{streams: array<int, array{serverId: string, name: string, playbackUrl: string, type: string, quality?: string, lang?: string, metadata?: array}>}

 */
function legacy_bridge_get_stream_from_anitaku(string $animeSlug, int $episodeNum, string $lang = 'best'): array
{
    $watchContext = legacy_get_watch_context($animeSlug);
    $sourceSlug = $watchContext['sourceSlug'] ?? $animeSlug;

    $episodePageUrl = 'https://anitaku.to/' . $sourceSlug . '-episode-' . $episodeNum;
    $episodePage = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($episodePageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: en-US,en;q=0.9',
                'Referer: https://anitaku.to/',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $episodePage = $raw === false ? '' : (string)$raw;
    }
    if ($episodePage === '') {
        $episodePage = legacy_http_get($episodePageUrl);
    }

    $streams = [];
    if ($episodePage && stripos($episodePage, '<!DOCTYPE') === false && stripos($episodePage, 'cloudflare') === false) {
        $streamUrl = '';
        $maybeWrapM3U8ForIframe = static function (?string $u): string {
            if (empty($u)) return '';
            if (strpos($u, '.m3u8') !== false) {
                return 'https://vidsrc.me/embed?url=' . urlencode($u);
            }
            return $u;
        };

        if (preg_match('/data-video="([^"]+)"/', $episodePage, $m)) {
            $u = html_entity_decode($m[1], ENT_QUOTES);
            $streamUrl = $maybeWrapM3U8ForIframe($u);
        }

        if ($streamUrl === '') {
            if (preg_match('/<iframe[^>]+src=["\']([^"\']*gogoanime[^"\']+)["\']/', $episodePage, $m2)) {
                $streamUrl = (string)$m2[1];
            }
        }

        if ($streamUrl !== '') {
            $streams[] = [
                'serverId' => 'anitaku-main',
                'name' => 'Main',
                'playbackUrl' => $streamUrl,
'type' => (strpos($streamUrl, '.m3u8') !== false ? 'hls' : 'iframe'),
                'lang' => $lang,
                'metadata' => [
                    'sourceSlug' => $sourceSlug,
                    'episodeNum' => $episodeNum,
                ],
            ];
        }
    }

    if (empty($streams)) {
        $anime = legacy_resolve_anime_by_slug($animeSlug);
        $malId = (int)($anime['mal_id'] ?? 0);
        $fallbackUrl = $malId > 0
            ? 'https://vidsrc.xyz/embed/anime?mal=' . $malId . '&episode=' . $episodeNum
            : 'https://vidsrc.to/embed/anime/' . rawurlencode($animeSlug) . '/' . $episodeNum;
        $streams[] = [
            'serverId' => 'vidsrc-fallback',
            'name' => 'VidSrc',
            'playbackUrl' => $fallbackUrl,
            'type' => 'iframe',
            'lang' => $lang,
            'metadata' => [
                'sourceSlug' => $sourceSlug,
                'episodeNum' => $episodeNum,
            ],
        ];
    }

    return ['streams' => $streams];
}

