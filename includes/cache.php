<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/..');
}

if (!is_dir(APP_ROOT . '/cache')) {
    mkdir(APP_ROOT . '/cache', 0755, true);
}
if (!is_writable(APP_ROOT . '/cache')) {
    error_log('[cache] WARNING: cache directory is not writable');
}

function app_cache_dir(): string
{
    $dir = APP_ROOT . '/cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function app_cache_path(string $key): string
{
    return app_cache_dir() . '/' . sha1($key) . '.cache.json';
}

function getCache(string $key)
{
    $path = app_cache_path($key);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload) || !isset($payload['expires_at'])) {
        return null;
    }

    if ((int)$payload['expires_at'] < time()) {
        @unlink($path);
        return null;
    }

    return $payload['data'] ?? null;
}

function setCache(string $key, $data, int $ttl = 3600): void
{
    $path = app_cache_path($key);
    $payload = [
        'expires_at' => time() + max(30, $ttl),
        'data' => $data,
    ];
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function app_delete_cache(string $key): void
{
    $path = app_cache_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

function app_clear_all_cache(): void
{
    $files = glob(app_cache_dir() . '/*.cache.json');
    if (!is_array($files)) {
        return;
    }
    foreach ($files as $file) {
        @unlink($file);
    }
}
