<?php

/**
 * Internal API client helper.
 * UI pages should use only internal endpoints.
 */

require_once __DIR__ . '/../../_config.php';

// Ensure legacy helper exists for HTTP GETs (some environments may not load it yet).
if (!function_exists('legacy_http_get')) {
    function legacy_http_get($url)
    {
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json,text/html,*/*\r\n",
                "timeout" => 8,
                "ignore_errors" => true,
            ],
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        return $result === false ? "" : (string)$result;
    }
}



function app_http_request(string $url, int $timeoutSeconds): array
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json,text/html,*/*\r\n",
            'timeout' => max(1, (int)$timeoutSeconds),
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return ['ok' => false, 'body' => '', 'error' => 'request_failed'];
    }

    return ['ok' => true, 'body' => (string)$result];
}

function app_api_get(string $path, array $query = [], int $timeoutSeconds = 8, int $retries = 2): array
{
    global $websiteUrl;

    $path = '/' . ltrim($path, '/');
    $projectRoot = realpath(__DIR__ . '/../../');
    $localFile = realpath(__DIR__ . '/../../' . ltrim($path, '/'));

    // IMPORTANT: internal API files MUST NOT be included directly.
    // The previous implementation used filesystem include + output buffering capture,
    // which caused malformed JSON / header-only responses.
    //
    // Compatibility: app_api_get() signature & return schema remain the same.
    // Transport: we always use upstream HTTP GET to /api/... endpoints.
    // NOTE: we intentionally do NOT do “include local file” anymore.


    $url = rtrim($websiteUrl, '/') . $path;

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $attempt = 0;
    $lastError = null;

    while ($attempt <= $retries) {
        $attempt++;

        $raw = app_http_get_json_raw($url, $timeoutSeconds);
        if ($raw === '') {
            $lastError = 'empty_response';
            continue;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // If already normalized, keep it.
            if (array_key_exists('ok', $decoded)) {
                if (isset($decoded['meta']) || isset($decoded['data']) || isset($decoded['error'])) {
                    return $decoded;
                }
                // If third-party / legacy returns other shape, normalize lightly.
                return [
                    'ok' => (bool)$decoded['ok'],
                    'data' => $decoded['data'] ?? $decoded,
                    'error' => $decoded['ok'] ? null : ($decoded['error'] ?? ['message' => 'upstream_error', 'code' => 502]),
                    'meta' => $decoded['meta'] ?? [],
                ];
            }

            // Some endpoints may return bare data arrays or other shapes.
            return [
                'ok' => true,
                'data' => $decoded,
                'error' => null,
                'meta' => [],
            ];
        }

        $lastError = 'invalid_json';
        error_log('[app_api_get] invalid_json url=' . $url . ' attempt=' . $attempt);
    }


    return [
        'ok' => false,
        'error' => [
            'message' => 'internal_api_failed',
            'code' => 502,
            'detail' => $lastError,
        ],
    ];
}

function app_http_get_json_raw(string $url, int $timeoutSeconds): string
{
    // Uses legacy_http_get implementation style but enforces timeout by stream context.
    // We do not depend on third-party APIs directly here.
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json,text/html,*/*\r\n",
            'timeout' => max(1, (int)$timeoutSeconds),
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);
    return ($result === false) ? '' : (string)$result;
}


