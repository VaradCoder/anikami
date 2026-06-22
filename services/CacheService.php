<?php
require_once __DIR__ . '/../includes/cache.php';

/**
 * Thin wrapper over the existing file-based cache (includes/cache.php) —
 * deliberately not a new cache backend. The value this adds is a single
 * place that names TTL policy instead of every call site picking its own
 * number, plus a remember() helper so future service classes read like
 * "cache, or compute and cache" instead of manual getCache/setCache pairs.
 */
class CacheService
{
    public const TTL_ANIME_DETAILS = 86400;      // 24h
    public const TTL_CHARACTERS = 86400;          // 24h
    public const TTL_RECOMMENDATIONS = 43200;     // 12h
    public const TTL_TRENDING = 21600;            // 6h
    public const TTL_SEASONAL = 21600;            // 6h
    public const TTL_SCHEDULE = 21600;            // 6h

    /**
     * @param string $key
     * @param int $ttl one of the TTL_* constants above
     * @param callable $compute called only on a cache miss
     */
    public static function remember(string $key, int $ttl, callable $compute)
    {
        $cached = getCache($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $compute();
        if ($value !== null) {
            setCache($key, $value, $ttl);
        }
        return $value;
    }

    public static function forget(string $key): void
    {
        app_delete_cache($key);
    }
}
