<?php
require_once __DIR__ . '/../providers/streaming/VidNestProvider.php';
require_once __DIR__ . '/../providers/streaming/VidNestDubProvider.php';
require_once __DIR__ . '/../providers/streaming/VidLinkProvider.php';
require_once __DIR__ . '/../includes/admin_tools.php'; // app_stream_provider_list(), health table writers

/**
 * Single source of truth for "which streaming providers, in what order."
 * Before this existed, streaming.php and api/watch_v2.php each hardcoded
 * their own copy of the same 3 providers — they could (and did, briefly)
 * drift out of sync with the admin-configured order/enabled state.
 *
 * Adding a new provider: write a class implementing StreamProvider, add it
 * to self::PROVIDER_CLASSES below, and add a row to db.php's seed list.
 * Nothing else needs to change.
 */
class StreamProviderManager
{
    private const PROVIDER_CLASSES = [
        'vidnest' => VidNestProvider::class,
        'vidnest-dub' => VidNestDubProvider::class,
        'vidlink' => VidLinkProvider::class,
    ];

    /** @return StreamProvider[] keyed by provider_key, in DB priority order, enabled only */
    public function getEnabledProviders(): array
    {
        $out = [];
        foreach (app_stream_provider_list() as $row) {
            $key = $row['provider_key'];
            if ((int)$row['enabled'] !== 1 || !isset(self::PROVIDER_CLASSES[$key])) continue;
            $class = self::PROVIDER_CLASSES[$key];
            $out[$key] = new $class();
        }
        return $out;
    }

    /**
     * Builds the ordered playback-server list for one episode — this is
     * what streaming.php and api/watch_v2.php both now call instead of
     * hardcoding URLs themselves. $startPriority lets watch_v2.php keep
     * putting iframe servers after any direct-stream entries, same as before.
     */
    public function getEpisodeServers(int $anilistId, int $episodeNum, int $startPriority = 0): array
    {
        if ($anilistId <= 0) return [];
        $servers = [];
        $priority = $startPriority;
        foreach ($this->getEnabledProviders() as $provider) {
            $server = $provider->getEpisodeUrl($anilistId, $episodeNum);
            $server['priority'] = $priority++;
            $servers[] = $server;
        }
        return $servers;
    }

    /** Runs every enabled provider's health check and records it (snapshot + history). */
    public function runHealthChecks(): array
    {
        $results = [];
        $now = gmdate('c');
        foreach (self::PROVIDER_CLASSES as $key => $class) {
            $provider = new $class();
            $result = $provider->healthCheck();

            $stmt = app_db()->prepare(
                'INSERT INTO stream_health(provider, status, response_ms, last_error, checked_at) VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT(provider) DO UPDATE SET status=excluded.status, response_ms=excluded.response_ms, last_error=excluded.last_error, checked_at=excluded.checked_at'
            );
            $stmt->execute([$key, $result['status'], $result['response_ms'], $result['last_error'], $now]);

            app_db()->prepare(
                'INSERT INTO stream_health_log(provider, status, response_ms, http_code, checked_at) VALUES (?, ?, ?, ?, ?)'
            )->execute([$key, $result['status'], $result['response_ms'], $result['http_code'], $now]);

            $results[$key] = $result;
        }
        return $results;
    }

    /** Failover order + enable state, persisted in stream_providers — admin/streams.php drag-and-drop writes here. */
    public function reorder(array $orderedKeys): void
    {
        app_stream_provider_reorder($orderedKeys);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        app_stream_provider_set_enabled($key, $enabled);
    }
}
