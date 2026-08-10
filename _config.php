<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Load internal API helpers early (some UI pages call app_api_get/app_api_post).
require_once __DIR__ . '/app/Helpers/api_client.php';

/**
 * Helper to detect internal API failures in UI pages.
 *
 * This only reads the provided payload; it does not call upstream.
 */
function app_debug_api_context(string $endpoint, array $meta, $apiResponse): array
{
    $ctx = [
        'ok' => true,
        'endpoint' => $endpoint,
        'meta' => $meta,
        'error' => null,
        'raw' => null,
    ];

    if (!is_array($apiResponse)) {
        $ctx['ok'] = false;
        $ctx['error'] = 'invalid_api_response';
        return $ctx;
    }

    // app_api_get returns normalized response with ok/data/error.
    $ok = $apiResponse['ok'] ?? null;
    if ($ok === false) {
        $ctx['ok'] = false;
        $ctx['error'] = $apiResponse['error'] ?? ['message' => 'api_failed'];
        return $ctx;
    }

    // If ok true but data missing/empty, treat as failure for UI.
    $data = $apiResponse['data'] ?? null;
    $ctx['raw'] = $apiResponse;

    if ($data === null) {
        $ctx['ok'] = false;
        $ctx['error'] = ['message' => 'missing_data'];
        return $ctx;
    }

    return $ctx;
}



$websiteTitle = "Anikami"; // Website Name

// Base path for reverse-proxy / subfolder installs (prevents duplicated segments like /anikatsu/api/...)
//
// Anchored on SCRIPT_FILENAME (the actual executing file's real path) vs
// __DIR__ (where this file — the project root — lives), NOT DOCUMENT_ROOT.
// DOCUMENT_ROOT comparisons break on hosts that chroot/symlink the home
// directory (common on shared hosting like InfinityFree): realpath()
// resolves DOCUMENT_ROOT and __DIR__ to non-matching strings even though
// they're the same physical place, which used to silently fall through to
// a dirname(SCRIPT_NAME) fallback that was wrong for any page outside the
// site root (e.g. /admin/index.php got basePath="/admin", breaking every
// asset link on admin pages specifically). SCRIPT_FILENAME has no such
// ambiguity — it's always the file PHP is actually executing.
$projectRoot = realpath(__DIR__);
$scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
$basePath = '';
if ($projectRoot && $scriptFile) {
    $projectNormalized = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $scriptNormalized = str_replace('\\', '/', $scriptFile);
    if (stripos($scriptNormalized, $projectNormalized) === 0) {
        // How many directory levels deep is the executing script vs the
        // project root (e.g. "admin", "api", "" for root-level scripts).
        // dirname() on Windows returns "\" (not "/") when nothing's left
        // but the root, even given a forward-slash input — trim both.
        $scriptRelDir = trim(dirname(substr($scriptNormalized, strlen($projectNormalized))), '/\\');
        $scriptNameDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        if ($scriptRelDir === '') {
            $basePath = $scriptNameDir;
        } else {
            $suffix = '/' . $scriptRelDir;
            if (substr($scriptNameDir, -strlen($suffix)) === $suffix) {
                $basePath = substr($scriptNameDir, 0, -strlen($suffix));
            }
        }
    }
}
if ($basePath === '.' || $basePath === '\\' || $basePath === '/') {
    $basePath = '';
}


$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
$scheme = $isHttps ? 'https' : 'http';
$websiteUrl = $scheme . '://' . $host . $basePath;  // Website URL
$websiteAbsoluteUrl = $websiteUrl; // $websiteUrl already includes the scheme
$websiteLogo = $websiteUrl . "/files/images/logo.png"; // Logo
$contactEmail = "contact@anikami.com"; // Contact Email

$version = "0.8";

//Donate
$donate = "#";

// Socials
$telegram = "https://t.me/#"; // telegram
$discord = "https://discord.gg/T6MKrGhRv"; // Discord
$redit = "#"; // Reddit
$twitter = "#"; // Twitter

$disqus = "https://indianime.disqus.com"; // Disqus

// New APIs (per update.md)
$jikanApi = "https://api.jikan.moe/v4/";
$aniListApi = "https://graphql.anilist.co";
$aniSpaceApi = "https://api.anispace.workers.dev/";
$aniTakuBase = "https://anitaku.to/";

// Keep legacy variable for compatibility (pages now use legacy_api())
$api = $jikanApi;

$banner = $websiteUrl . "/files/images/banner.png";  //Banner
$requestPathForTracking = (string)($_SERVER['REQUEST_URI'] ?? '/');
if (PHP_SAPI !== 'cli'
    && stripos($requestPathForTracking, '/api/') !== 0
    && stripos($requestPathForTracking, '/sitemaps/') !== 0
    && stripos($requestPathForTracking, '/sitemap') !== 0
    && substr($requestPathForTracking, -4) !== '.xml'
) {
    header('Content-Type: text/html; charset=UTF-8');
}
if (PHP_SAPI !== 'cli'
    && stripos($requestPathForTracking, '/api/') !== 0
    && stripos($requestPathForTracking, '/admin/') !== 0
    && stripos($requestPathForTracking, '/sitemaps/') !== 0
    && stripos($requestPathForTracking, '/sitemap') !== 0
) {
    app_track_event('page_view', null, ['path' => $requestPathForTracking]);
}

function app_log_upstream_issue(array $ctx): void
{
    // Sanitized, no cookies/tokens, truncated response previews only.
    try {
        $prefix = '[UPSTREAM_ISSUE]';
        if (!empty($ctx['log_prefix']) && is_string($ctx['log_prefix'])) {
            $prefix = $ctx['log_prefix'];
            unset($ctx['log_prefix']);
        }

        $safe = $ctx;
        if (isset($safe['response_preview']) && is_string($safe['response_preview'])) {
            $safe['response_preview'] = substr($safe['response_preview'], 0, 300);
        }
        if (isset($safe['headers_preview']) && is_string($safe['headers_preview'])) {
            $safe['headers_preview'] = substr($safe['headers_preview'], 0, 800);
        }

        // json_encode keeps it single-line for log search.
        $line = $prefix . ' ' . json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log($line);
    } catch (Throwable $e) {
        // swallow logging errors
    }
}

function app_upstream_is_cloudflare_challenge(string $preview): bool
{
    $p = strtolower($preview);
    return (
        strpos($p, 'cloudflare') !== false ||
        strpos($p, 'just a moment') !== false ||
        strpos($p, 'attention required') !== false ||
        strpos($p, 'cf-browser-verification') !== false ||
        strpos($p, 'captcha') !== false
    );
}


function legacy_http_get($url)
{
    $cacheKey = 'http_raw:' . sha1($url);

    // Transport diagnostics (debug): track protocol-relative URLs early.
    if (is_string($url) && strpos($url, '//') === 0) {
        error_log('[legacy_http_get] WARNING: protocol-relative URL detected, forcing absolute');
    }


    // SSL verification policy
    // Secure by default.
    // In local/dev only, allow optional insecure SSL when explicitly enabled.
    // Expected env vars:
    //   APP_ENV=production
    //   ALLOW_INSECURE_SSL_LOCAL=false|true
    // Behavior:
    //   - If APP_ENV !== 'production' AND ALLOW_INSECURE_SSL_LOCAL=true => insecure mode
    //   - Otherwise => secure SSL verification
    $verifySSL = true;

    $appEnv = (string)(getenv('APP_ENV') ?: 'production');
    $allowInsecureLocal = getenv('ALLOW_INSECURE_SSL_LOCAL');
    $allowInsecureLocal = ($allowInsecureLocal === false) ? null : strtolower(trim((string)$allowInsecureLocal));
    $allowInsecure = $allowInsecureLocal !== null && ($allowInsecureLocal === '1' || $allowInsecureLocal === 'true' || $allowInsecureLocal === 'yes');

    $insecureOverrideActive = false;
    $host = (string)(parse_url((string)$url, PHP_URL_HOST) ?: '');

    if ($appEnv !== 'production' && $allowInsecure) {
        $verifySSL = false;
        $insecureOverrideActive = true;
        error_log('[legacy_http_get] WARNING: insecure SSL verification ENABLED. APP_ENV=' . $appEnv . ' host=' . $host);
    }

    // Cache-first transport (critical for homepage stability + rate-limit safety)
    $cached = getCache($cacheKey);
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }



    /*
    $cached = getCache($cacheKey);
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }
    */


    $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64)";

    if (function_exists('curl_init')) {

        $ch = curl_init();

        $startTs = microtime(true);
        $startIso = gmdate('c');

        // Per-request diagnostic logging (every call, success or not) is
        // dev-only — on shared/free hosting (e.g. InfinityFree) this fires
        // on nearly every page load (Jikan/AniList calls) and burns disk
        // I/O + CPU time against the host's quota for no production
        // benefit. Real failures still get their own dedicated log block
        // below (UPSTREAM_FAILURE), unconditionally, in every environment.
        if ($appEnv !== 'production') {
            app_log_upstream_issue([
                'log_prefix' => '[UPSTREAM_REQUEST]',
                'url' => $url,
                'host' => $host,
                'verify_ssl' => $verifySSL,
                'insecure_local_override' => $insecureOverrideActive,
                'timeout_s' => 20,
                'connect_timeout_s' => 10,
                'started_at' => $startIso,
            ]);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => $ua,
CURLOPT_HTTPHEADER => [
                'Accept: application/json,text/html,*/*',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_ENCODING => '',

            // SSL verification policy
            // Secure by default; only allow insecure SSL in local/dev with explicit env flag.
            CURLOPT_SSL_VERIFYPEER => ($verifySSL ? true : false),
            CURLOPT_SSL_VERIFYHOST => ($verifySSL ? 2 : false),

            // Capture headers for diagnostics (truncated below)
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
        ]);

        $result = curl_exec($ch);
        $totalRequestTimeMs = (int)round((microtime(true) - $startTs) * 1000);

        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $redirectCount = (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        // Extra raw diagnostics BEFORE curl_close — dev-only, see note above.
        if ($appEnv !== 'production') {
            error_log('[legacy_http_get] url=' . $url
                . ' http=' . $httpCode
                . ' errno=' . $errno
                . ' error=' . $err
                . ' effective_url=' . $effectiveUrl
                . ' redirect_count=' . $redirectCount
                . ' content_type=' . $contentType
            );
        }


        // Split header/body preview from CURLOPT_HEADER => true
        $len = strlen((string)$result);
        $head = '';
        $body = '';
        if (is_string($result) && $result !== '') {
            if ($headerSize > 0 && strlen((string)$result) >= $headerSize) {
                $rawHeaders = substr((string)$result, 0, $headerSize);
                $body = substr((string)$result, $headerSize);
                $head = substr((string)$body, 0, 300);
                $headersPreview = substr((string)$rawHeaders, 0, 800);
            } else {
                $head = substr((string)$result, 0, 300);
                $body = (string)$result;
                $headersPreview = substr((string)$result, 0, 800);
            }
        }

        // Build previews AFTER curl_exec, BEFORE curl_close side-effects.
        curl_close($ch);

        if ($result === false) {
            $result = '';
        }

        $responsePreview = (string)$head;
        if (!isset($headersPreview)) {
            $headersPreview = '';
        }

        if ($appEnv !== 'production') {
            error_log('[legacy_http_get] preview_head=' . substr((string)$responsePreview, 0, 500));
        }


        // Heuristics: Cloudflare/anti-bot pages often include common strings.
        $challengeLooksLike = (
            stripos($responsePreview, 'cloudflare') !== false ||
            stripos($responsePreview, 'cf-browser-verification') !== false ||
            stripos($responsePreview, 'captcha') !== false ||
            stripos($responsePreview, 'just a moment') !== false ||
            stripos($responsePreview, 'attention required') !== false
        );

        // Additional detection for known challenge/empty HTML patterns
        $challengeJustAMoment = stripos($responsePreview, 'just a moment') !== false;
        $challengeAttentionRequired = stripos($responsePreview, 'attention required') !== false;
        $challengeCfBrowserVerification = stripos($responsePreview, 'cf-browser-verification') !== false;
        $challengeEmptyHtml = (is_string($responsePreview) && trim($responsePreview) === '');


        if ($errno !== 0 || empty($result) || $challengeLooksLike || in_array($httpCode, [403, 429], true)) {
            app_log_upstream_issue([
                'log_prefix' => '[UPSTREAM_FAILURE]',
                'type' => 'legacy_http_get',
                'url' => $url,
                'host' => $host,
                'verify_ssl' => $verifySSL,
                'insecure_local_override' => $insecureOverrideActive,
                'curl_errno' => $errno,
                'curl_error' => $err,
                'http_code' => $httpCode,
                'total_time_ms' => $totalRequestTimeMs,
                'curl_total_time' => $totalTime,
                'redirect_count' => $redirectCount,
                'effective_url' => $effectiveUrl,
                'content_type' => $contentType,
                'response_len' => $len,
                'challenge_page_suspected' => $challengeLooksLike,
                'challenge_just_a_moment' => $challengeJustAMoment,
                'challenge_attention_required' => $challengeAttentionRequired,
                'challenge_cf_browser_verification' => $challengeCfBrowserVerification,
                'empty_html_body_detected' => $challengeEmptyHtml,
                'curl_err_hint' => (
                    $errno === 60 ? 'Possible CA bundle/issuer verification issue' :
                    ($errno === 28 ? 'Timeout' :
                    ($errno === 6 ? 'DNS failure' :
                    ($errno === 35 ? 'TLS handshake failure' : null)))
                ),

                'curl_cainfo_configured' => (string)(

                    (function_exists('curl_version') ? (string)(curl_version()['ssl_version'] ?? '') : '')
                ),
                'openssl_version' => (string)(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : ''),
                'curl_version' => (function_exists('curl_version') ? (string)(curl_version()['version'] ?? '') : ''),
                'response_preview' => (string)$responsePreview,
                'headers_preview' => (string)$headersPreview,
                'challenge_detected_heuristic' => $challengeLooksLike,
            ]);

        }


        $isHtmlRedirect = (
            stripos((string)$responsePreview, '<!DOCTYPE') !== false ||
            stripos((string)$responsePreview, '<html') !== false ||
            stripos((string)$responsePreview, 'We Have Moved') !== false ||
            stripos((string)$responsePreview, 'AniNeko') !== false
        );

        if ($isHtmlRedirect) {
            app_log_upstream_issue([
                'type' => 'legacy_http_get_redirect_html',
                'url' => $url,
                'host' => $host,
                'verify_ssl' => $verifySSL,
                'insecure_local_override' => $insecureOverrideActive,
                'http_code' => $httpCode,
                'redirect_count' => $redirectCount,
                'effective_url' => $effectiveUrl,
                'response_len' => $len,
                'response_preview' => (string)$responsePreview,
            ]);
            return json_encode([]);
        }


        // Never cache upstream error responses (e.g. Jikan 504 "MyAnimeList down"),
        // otherwise a transient outage would be served from cache for 12 hours and
        // block the AniList fallback from ever running.
        $bodyLooksLikeError = (
            $httpCode >= 400
            || (is_string($body) && preg_match('/"status"\s*:\s*[45]\d\d/', $body))
            || (is_string($body) && (stripos($body, 'BadResponseException') !== false
                || stripos($body, 'UpstreamException') !== false))
        );
        if ($body !== '' && !$bodyLooksLikeError) {
            setCache($cacheKey, $body, 43200); // 12-hour raw HTTP cache
        } elseif ($bodyLooksLikeError) {
            error_log('[legacy_http_get] upstream_error_not_cached http=' . $httpCode . ' url=' . $url);
        }

        return (string)$body;
    }


    // Fallback if curl unavailable
    $opts = [
        "http" => [
            "method" => "GET",
            "header" =>
                "User-Agent: " . $ua . "\r\n" .
                "Accept: application/json,text/html,*/*\r\n",

            "timeout" => 8,
            "ignore_errors" => true,
        ],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ];

    $context = stream_context_create($opts);

    $result = @file_get_contents($url, false, $context);

    $result = $result === false ? "" : (string)$result;

    // TEMP DEBUG: log redirect/response details for fallback path
    $len = strlen($result);
    $head = substr($result, 0, 300);
    $isHtmlRedirect = (
        stripos($head, '<!DOCTYPE') !== false ||
        stripos($head, '<html') !== false ||
        stripos($head, 'We Have Moved') !== false ||
        stripos($head, 'AniNeko') !== false
    );

    error_log('[legacy_http_get/fallback] requested=' . $url);
    error_log('[legacy_http_get/fallback] response_len=' . $len . ' head=' . $head);

    if ($isHtmlRedirect) {
        error_log('[legacy_http_get/fallback] upstream_redirect_html_detected; not caching.');
        return '';
    }

    $resultLooksLikeError = (
        is_string($result) && (
            preg_match('/"status"\s*:\s*[45]\d\d/', $result)
            || stripos($result, 'BadResponseException') !== false
            || stripos($result, 'UpstreamException') !== false
        )
    );
    if ($result !== "" && !$resultLooksLikeError) {
        setCache($cacheKey, $result, 43200); // 12-hour cache
    }

    return (string)$result;
}

/**
 * Concurrent counterpart to legacy_http_get() for multiple URLs at once —
 * same per-URL cache (http_raw:sha1(url), 12h), SSL policy, challenge/
 * error-body detection, and skip-cache-on-error behavior; the only
 * difference is the network round trip happens over one curl_multi pass
 * instead of N sequential curl_exec calls. Falls back to sequential
 * legacy_http_get() per URL when curl isn't available at all.
 *
 * @param string[] $urls
 * @return array<string, string> raw body keyed by the original URL
 */
function legacy_http_get_multi(array $urls): array
{
    $results = [];
    $toFetch = []; // url => cacheKey, for cache misses only

    foreach ($urls as $url) {
        $cacheKey = 'http_raw:' . sha1($url);
        $cached = getCache($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $results[$url] = $cached;
            continue;
        }
        $toFetch[$url] = $cacheKey;
    }

    if (empty($toFetch)) {
        return $results;
    }

    if (!function_exists('curl_multi_init')) {
        // No curl at all — fall back to the single-URL path sequentially.
        foreach (array_keys($toFetch) as $url) {
            $results[$url] = legacy_http_get($url);
        }
        return $results;
    }

    $appEnv = (string)(getenv('APP_ENV') ?: 'production');
    $allowInsecureLocal = getenv('ALLOW_INSECURE_SSL_LOCAL');
    $allowInsecureLocal = ($allowInsecureLocal === false) ? null : strtolower(trim((string)$allowInsecureLocal));
    $allowInsecure = $allowInsecureLocal !== null && in_array($allowInsecureLocal, ['1', 'true', 'yes'], true);
    $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64)";

    $multi = curl_multi_init();
    $handles = [];
    foreach (array_keys($toFetch) as $url) {
        $verifySSL = !($appEnv !== 'production' && $allowInsecure);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json,text/html,*/*',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => $verifySSL,
            CURLOPT_SSL_VERIFYHOST => ($verifySSL ? 2 : false),
            CURLOPT_HEADER => true,
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$url] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running > 0);

    foreach ($handles as $url => $ch) {
        $cacheKey = $toFetch[$url];
        $raw = curl_multi_getcontent($ch);
        $errno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);

        $body = '';
        if (is_string($raw) && $raw !== '') {
            $body = ($headerSize > 0 && strlen($raw) >= $headerSize) ? substr($raw, $headerSize) : $raw;
        }
        $head = substr((string)$body, 0, 300);

        if ($errno !== 0 || $body === '') {
            error_log('[legacy_http_get_multi] failure url=' . $url . ' errno=' . $errno . ' http=' . $httpCode);
            $results[$url] = '';
            continue;
        }

        $challengeLooksLike = (
            stripos($head, 'cloudflare') !== false ||
            stripos($head, 'cf-browser-verification') !== false ||
            stripos($head, 'captcha') !== false ||
            stripos($head, 'just a moment') !== false ||
            stripos($head, 'attention required') !== false ||
            stripos($head, '<!DOCTYPE') !== false ||
            stripos($head, '<html') !== false
        );
        if ($challengeLooksLike || $httpCode >= 400) {
            error_log('[legacy_http_get_multi] rejected url=' . $url . ' http=' . $httpCode . ' challenge=' . ($challengeLooksLike ? '1' : '0'));
            $results[$url] = '';
            continue;
        }

        $bodyLooksLikeError = preg_match('/"status"\s*:\s*[45]\d\d/', $body)
            || stripos($body, 'BadResponseException') !== false
            || stripos($body, 'UpstreamException') !== false;
        if (!$bodyLooksLikeError) {
            setCache($cacheKey, $body, 43200); // 12-hour raw HTTP cache, matches legacy_http_get()
        }

        $results[$url] = $body;
    }

    curl_multi_close($multi);
    return $results;
}

/**
 * AniList search → Jikan-shaped rows. Used as a fallback for fetchAPI("anime?q=...").
 */
function legacy_anilist_search_rows(string $search, int $limit = 12): array
{
    $query = <<<'GQL'
query ($search: String, $perPage: Int) {
  Page(page: 1, perPage: $perPage) {
    media(search: $search, type: ANIME, sort: SEARCH_MATCH) {
      id idMal format status seasonYear episodes
      coverImage { extraLarge large medium }
      title { romaji english native }
      genres
    }
  }
}
GQL;
    $normalized = str_replace('-', ' ', $search);
    $resp = legacy_anilist_graphql($query, [
        'search'  => $normalized,
        'perPage' => max(1, min(50, $limit)),
    ]);
    $rows = $resp['data']['Page']['media'] ?? [];

    if (empty($rows)) {
        $stripped = legacy_strip_type_suffix($normalized);
        if (strtolower($stripped) !== strtolower($normalized)) {
            $resp = legacy_anilist_graphql($query, [
                'search'  => $stripped,
                'perPage' => max(1, min(50, $limit)),
            ]);
            $rows = $resp['data']['Page']['media'] ?? [];
        }
    }

    return is_array($rows) ? $rows : [];
}

// Only genres that exist as first-class AniList genres — AniList's genre
// list is much smaller than Jikan's (many Jikan "genres" are AniList
// "tags" instead, which this can't filter by). Jikan genre id -> AniList
// genre name; slugs not listed here just get no AniList fallback when
// Jikan is down (empty results until Jikan recovers, same as before this
// existed — not worse, just not fixed for that subset).
function legacy_anilist_genre_name(int $genreId): ?string
{
    static $reverseMap = null;
    if ($reverseMap === null) {
        $slugToAnilist = [
            'action' => 'Action', 'adventure' => 'Adventure', 'comedy' => 'Comedy',
            'drama' => 'Drama', 'ecchi' => 'Ecchi', 'fantasy' => 'Fantasy',
            'hentai' => 'Hentai', 'horror' => 'Horror', 'mecha' => 'Mecha',
            'music' => 'Music', 'mystery' => 'Mystery', 'psychological' => 'Psychological',
            'romance' => 'Romance', 'sci-fi' => 'Sci-Fi', 'slice-of-life' => 'Slice of Life',
            'sports' => 'Sports', 'supernatural' => 'Supernatural', 'thriller' => 'Thriller',
        ];
        $reverseMap = [];
        foreach (legacy_get_genre_map() as $slug => $id) {
            if (isset($slugToAnilist[$slug]) && !isset($reverseMap[$id])) {
                $reverseMap[$id] = $slugToAnilist[$slug];
            }
        }
    }
    return $reverseMap[$genreId] ?? null;
}

function legacy_anilist_genre_rows(string $genreName, int $page = 1, int $perPage = 24): array
{
    $query = <<<'GQL'
query ($genre: String, $page: Int, $perPage: Int) {
  Page(page: $page, perPage: $perPage) {
    pageInfo { lastPage }
    media(genre: $genre, type: ANIME, sort: TRENDING_DESC) {
      id idMal format status seasonYear episodes
      coverImage { extraLarge large medium }
      title { romaji english native }
      genres
    }
  }
}
GQL;
    $resp = legacy_anilist_graphql($query, ['genre' => $genreName, 'page' => $page, 'perPage' => max(1, min(50, $perPage))]);
    return [
        'rows' => is_array($resp['data']['Page']['media'] ?? null) ? $resp['data']['Page']['media'] : [],
        'lastPage' => (int)($resp['data']['Page']['pageInfo']['lastPage'] ?? 1),
    ];
}

/**
 * Resilience fallback: when the Jikan API is down/empty, satisfy common list and
 * search endpoints from AniList instead and return them in Jikan's response shape.
 * Returns [] when the endpoint can't be mapped to an AniList equivalent.
 */
function legacy_jikan_anilist_fallback(string $endpoint): array
{
    $limit = 24;
    if (preg_match('/[?&]limit=(\d+)/', $endpoint, $m)) { $limit = max(1, min(50, (int)$m[1])); }
    $page = 1;
    if (preg_match('/[?&]page=(\d+)/', $endpoint, $mp)) { $page = max(1, (int)$mp[1]); }

    $rows = [];
    if (preg_match('#^anime\?#', $endpoint) && preg_match('/[?&]q=([^&]+)/', $endpoint, $mq)) {
        $rows = legacy_anilist_search_rows(urldecode($mq[1]), $limit);
    } elseif (preg_match('#^anime\?#', $endpoint) && preg_match('/[?&]genres=(\d+)/', $endpoint, $mg)) {
        $genreName = legacy_anilist_genre_name((int)$mg[1]);
        if ($genreName !== null) {
            $rows = legacy_anilist_genre_rows($genreName, $page, $limit)['rows'];
        }
    } else {
        $mode = null;
        if (strpos($endpoint, 'filter=airing') !== false)            { $mode = 'airing'; }
        elseif (strpos($endpoint, 'filter=bypopularity') !== false)  { $mode = 'popular'; }
        elseif (strpos($endpoint, 'seasons/now') !== false)          { $mode = 'seasonal'; }
        elseif (strpos($endpoint, 'type=movie') !== false)           { $mode = 'movie'; }
        elseif (strpos($endpoint, 'status=complete') !== false)      { $mode = 'completed'; }
        elseif (strpos($endpoint, 'top/anime') !== false || strpos($endpoint, 'seasons/') !== false) { $mode = 'trending'; }
        if ($mode !== null) {
            $list = legacy_anilist_media_list($mode, $page, $limit);
            $rows = $list['rows'] ?? [];
        }
    }

    if (empty($rows)) { return []; }

    $data = [];
    foreach ($rows as $row) {
        if (!is_array($row)) { continue; }
        $slug = legacy_slugify(legacy_anilist_title($row, 'anime'));
        $data[] = legacy_anilist_to_legacy_payload($row, $slug);
    }
    return [
        'data' => $data,
        'pagination' => ['has_next_page' => false, 'last_visible_page' => 1, '_provider' => 'anilist'],
    ];
}

/**
 * Decode/fallback/cache logic shared by fetchAPI() (single endpoint) and
 * fetchAPIMulti() (concurrent batch) — extracted so both paths produce
 * byte-identical results from a raw Jikan response body.
 */
function _fetchapi_process_raw(string $endpoint, string $cacheKey, string $raw): array
{
    if (strpos($raw, '"status":429') !== false || strpos($raw, 'Too Many Requests') !== false) {
        error_log('[fetchAPI] 429_rate_limited endpoint=' . $endpoint . ' - returning cached/empty to avoid blocking');
        // Non-blocking behavior: do not sleep inside a web request.
        // If the second call succeeds it will be cached by legacy_http_get/fetchAPI TTL.
        $raw = legacy_http_get($GLOBALS['jikanApi'] . $endpoint);
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null) {
        error_log('[fetchAPI] json_decode_failed endpoint=' . $endpoint . ' err=' . json_last_error_msg());
        error_log('[fetchAPI] raw_head=' . substr($raw, 0, 300));
    }

    // Treat null / missing-data / empty-list responses as upstream failure and
    // fall back to AniList so the site keeps working when Jikan/MAL is down.
    $hasUsableData = is_array($decoded)
        && isset($decoded['data'])
        && (!is_array($decoded['data']) || count($decoded['data']) > 0);
    if (!$hasUsableData) {
        error_log('[fetchAPI] jikan_unusable endpoint=' . $endpoint . ' - trying AniList fallback');
        $fallback = legacy_jikan_anilist_fallback($endpoint);
        if (!empty($fallback['data'])) {
            setCache($cacheKey, $fallback, 1800); // 30-min cache for fallback data
            return $fallback;
        }
        return ['data' => [], 'pagination' => ['has_next_page' => false, 'last_visible_page' => 1]];
    }
    $final = is_array($decoded) ? $decoded : ["data" => [], "pagination" => ["has_next_page" => false, "last_visible_page" => 1]];

    // Episode lists can be cached longer than regular list/meta requests.
    $ttl = preg_match('#/episodes#', $endpoint) ? 86400 : 21600; // 24h for episodes, 6h for lists
    if (is_array($decoded)) {
        setCache($cacheKey, $final, $ttl);
    }
    return $final;
}

function fetchAPI($endpoint)
{
    global $jikanApi;
    $endpoint = ltrim($endpoint, '/');
    $cacheKey = 'jikan:' . $endpoint;
    $cached = getCache($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $raw = legacy_http_get($jikanApi . $endpoint);
    return _fetchapi_process_raw($endpoint, $cacheKey, (string)$raw);
}

/**
 * Concurrent batch version of fetchAPI() — same cache keys, same
 * decode/fallback/TTL behavior per endpoint, just fetches every
 * currently-uncached endpoint over one curl_multi round trip instead of
 * one sequential request per endpoint. Built for pages like home.php /
 * api/home.php that fire 3 independent Jikan calls on every cache miss:
 * on a CPU-time-metered host (e.g. InfinityFree), 3 sequential ~1s calls
 * cost roughly 3x the wall-clock and CPU-seconds of 3 concurrent ones.
 *
 * @param string[] $endpoints
 * @return array<string, array> keyed by the original endpoint string
 */
function fetchAPIMulti(array $endpoints): array
{
    global $jikanApi;
    $results = [];
    $toFetch = []; // endpoint => ['cacheKey' => ..., 'url' => ...]

    foreach ($endpoints as $endpoint) {
        $endpoint = ltrim($endpoint, '/');
        $cacheKey = 'jikan:' . $endpoint;
        $cached = getCache($cacheKey);
        if (is_array($cached)) {
            $results[$endpoint] = $cached;
            continue;
        }
        $toFetch[$endpoint] = ['cacheKey' => $cacheKey, 'url' => $jikanApi . $endpoint];
    }

    if (empty($toFetch)) {
        return $results;
    }

    $rawByEndpoint = legacy_http_get_multi(array_map(fn($t) => $t['url'], $toFetch));

    foreach ($toFetch as $endpoint => $meta) {
        $raw = $rawByEndpoint[$meta['url']] ?? '';
        $results[$endpoint] = _fetchapi_process_raw($endpoint, $meta['cacheKey'], $raw);
    }

    return $results;
}

function legacy_anispace_json($endpoint)
{
    global $aniSpaceApi;
    $endpoint = ltrim($endpoint, '/');
    $cacheKey = 'anispace:' . $endpoint;
    $cached = getCache($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $raw = '';
    $url = $aniSpaceApi . $endpoint;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json,text/plain,*/*',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $exec = curl_exec($ch);
        curl_close($ch);
        $raw = $exec === false ? '' : (string)$exec;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json,text/plain,*/*\r\n",
                'timeout' => 12,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $resp = @file_get_contents($url, false, $context);
        $raw = $resp === false ? '' : (string)$resp;
    }

    if (stripos($raw, '<!DOCTYPE') !== false || stripos($raw, 'cloudflare') !== false || stripos($raw, 'just a moment') !== false) {
        error_log('[legacy_anispace_json] cloudflare_block endpoint=' . $endpoint);
        return [];
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null) {
        error_log('[legacy_anispace_json] json_decode_failed endpoint=' . $endpoint . ' err=' . json_last_error_msg());
        error_log('[legacy_anispace_json] raw_head=' . substr((string)$raw, 0, 300));
    }
    $final = is_array($decoded) ? $decoded : [];

    if (!empty($final)) {
        setCache($cacheKey, $final, 21600);
    }
    return $final;
}

function legacy_anilist_graphql(string $query, array $variables = []): array
{
    global $aniListApi;

    $cacheKey = 'anilist:' . sha1($query . '|' . json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $cached = getCache($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $payload = json_encode([
        'query' => $query,
        'variables' => $variables,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($payload) || $payload === '') {
        return [];
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
            'content' => $payload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($opts);
    $raw = @file_get_contents($aniListApi, false, $context);
    $rawStr = ($raw === false ? '' : (string)$raw);
    $decoded = json_decode($rawStr, true);
    if ($decoded === null) {
        error_log('[legacy_anilist_graphql] json_decode_failed err=' . json_last_error_msg());
        error_log('[legacy_anilist_graphql] raw_head=' . substr($rawStr, 0, 300));
    }
    $final = is_array($decoded) ? $decoded : [];


    if (!empty($final['data'])) {
        setCache($cacheKey, $final, 3600);
    }

    return $final;
}

// AniList's `search` is a literal/strict match — unlike Jikan's fuzzy search,
// appending a type descriptor like "Movie"/"TV"/"OVA" (which Jikan titles and
// this site's own catalog links often carry, e.g. "Jujutsu Kaisen 0 Movie")
// makes it return zero rows even though the anime exists. Strip the descriptor
// and retry once before giving up.
function legacy_strip_type_suffix(string $title): string
{
    $stripped = preg_replace('/\s*[\(\[]\s*(?:TV|Movie|OVA|ONA|Special)\s*[\)\]]\s*$/i', '', $title);
    $stripped = preg_replace('/\s+(?:TV|Movie|OVA|ONA|Special)\s*$/i', '', $stripped);
    $stripped = trim((string)$stripped);
    return $stripped !== '' ? $stripped : $title;
}

function legacy_pick_title(...$candidates)
{
    foreach ($candidates as $candidate) {
        $trimmed = trim((string)($candidate ?? ''));
        if ($trimmed !== '') {
            return $trimmed;
        }
    }
    return 'Unknown';
}

function legacy_slugify($text)
{
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
    $text = preg_replace('/[\s_]+/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function legacy_unslug($slug)
{
    $slug = str_replace('-', ' ', $slug);
    $slug = preg_replace('/\s+/', ' ', trim($slug));
    return ucwords($slug);
}

function app_safe_image(?string $url, ?string $fallback = null): string
{
    global $websiteUrl;

    $candidate = trim((string)$url);
    if ($candidate !== '') {
        return $candidate;
    }

    $fallbackUrl = trim((string)($fallback ?? ''));
    if ($fallbackUrl !== '') {
        return $fallbackUrl;
    }

    return $websiteUrl . '/files/images/no_poster.jpg';
}

function app_meta_excerpt(?string $text, int $length = 150, string $fallback = 'No synopsis available.'): string
{
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
    if ($plain === '') {
        $plain = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($plain, 0, max(1, $length));
    }

    return substr($plain, 0, max(1, $length));
}

function legacy_title_is_dub(?string $title): bool
{
    return str_ends_with(trim((string)$title), '(Dub)');
}

function legacy_get_genre_map()
{
    return [
        "action" => 1, "adventure" => 2, "cars" => 3, "comedy" => 4, "dementia" => 5, "demons" => 6,
        "mystery" => 7, "drama" => 8, "ecchi" => 9, "fantasy" => 10, "game" => 11, "hentai" => 12,
        "historical" => 13, "horror" => 14, "kids" => 15, "magic" => 16, "martial-arts" => 17, "mecha" => 18,
        "music" => 19, "parody" => 20, "samurai" => 21, "romance" => 22, "school" => 23, "sci-fi" => 24,
        "shoujo" => 25, "girls-love" => 26, "shounen" => 27, "boys-love" => 28, "space" => 29, "sports" => 30,
        "super-power" => 31, "vampire" => 32, "harem" => 35, "slice-of-life" => 36, "supernatural" => 37,
        "military" => 38, "police" => 39, "psychological" => 40, "suspense" => 41, "seinen" => 42, "josei" => 43,
        "thriller" => 41, "shoujo-ai" => 26, "shounen-ai" => 28, "yaoi" => 28, "yuri" => 26,
    ];
}

function legacy_extract_anispace_slug($payload)
{
    if (!is_array($payload)) {
        return "";
    }
    if (isset($payload["slug"]) && is_string($payload["slug"])) {
        return $payload["slug"];
    }
    if (isset($payload["data"]) && is_array($payload["data"])) {
        foreach (["slug", "id", "animeId"] as $k) {
            if (!empty($payload["data"][$k]) && is_string($payload["data"][$k])) {
                return $payload["data"][$k];
            }
        }
    }
    if (isset($payload[0]) && is_array($payload[0])) {
        foreach (["slug", "id", "animeId"] as $k) {
            if (!empty($payload[0][$k]) && is_string($payload[0][$k])) {
                return $payload[0][$k];
            }
        }
    }
    foreach ($payload as $row) {
        if (is_array($row)) {
            foreach (["slug", "id", "animeId"] as $k) {
                if (!empty($row[$k]) && is_string($row[$k])) {
                    return $row[$k];
                }
            }
        }
    }
    return "";
}

function legacy_resolve_source_slug($title, $hintSlug = "")
{
    static $cache = [];
    $cacheKey = legacy_slugify($title . '|' . $hintSlug);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Highest priority: explicit admin override.
    $overrideTitle = app_slug_override_get($title);
    if (!empty($overrideTitle)) {
        $cache[$cacheKey] = $overrideTitle;
        return $overrideTitle;
    }
    if (!empty($hintSlug)) {
        $overrideSlug = app_slug_override_get($hintSlug);
        if (!empty($overrideSlug)) {
            $cache[$cacheKey] = $overrideSlug;
            return $overrideSlug;
        }
    }

    // AniSpace is unreliable in some deployments; do not hard-depend on it.
    // Prefer deterministic slugs and AniList/Jikan matching.

    $fallback = !empty($hintSlug) ? $hintSlug : legacy_slugify($title);

    // If we have a hint slug, trust it (after admin override handled above).
    if (!empty($hintSlug)) {
        $cache[$cacheKey] = $hintSlug;
        return $hintSlug;
    }

    // AniList: use its title matching for a stable slug.
    $anilist = legacy_resolve_anilist_anime($title);
    if (!empty($anilist)) {
        $cache[$cacheKey] = legacy_slugify(legacy_anilist_title($anilist));
        return $cache[$cacheKey];
    }

    // Jikan fallback: try best-match using title.
    $query = str_replace('-', ' ', $title);
    $resp = fetchAPI("anime?q=" . rawurlencode($query) . "&limit=10");
    $rows = is_array($resp['data'] ?? null) ? $resp['data'] : [];
    $best = legacy_pick_best_jikan_match(legacy_slugify($title), $rows);
    if (is_array($best) && !empty($best['title_english'])) {
        $cache[$cacheKey] = legacy_slugify($best['title_english']);
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = $fallback;

    $cache[$cacheKey] = $fallback;
    return $fallback;
}

function legacy_pick_best_jikan_match($slug, $rows)
{
    $best = null;
    $bestScore = -1;
    foreach ($rows as $row) {
        $titles = [];
        foreach (["title", "title_english", "title_japanese"] as $k) {
            if (!empty($row[$k])) {
                $titles[] = $row[$k];
            }
        }
        $score = 0;
        foreach ($titles as $t) {
            $ts = legacy_slugify($t);
            if ($ts === $slug) {
                $score = max($score, 100);
            } elseif (strpos($ts, $slug) !== false || strpos($slug, $ts) !== false) {
                $score = max($score, 80);
            } else {
                similar_text($slug, $ts, $pct);
                $score = max($score, (int)$pct);
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $row;
        }
    }
    return $best ?: (isset($rows[0]) ? $rows[0] : null);
}

function legacy_pick_best_anilist_match(string $slug, array $rows): ?array
{
    $best = null;
    $bestScore = -1;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $titles = [];
        foreach (['romaji', 'english', 'native'] as $key) {
            if (!empty($row['title'][$key]) && is_string($row['title'][$key])) {
                $titles[] = $row['title'][$key];
            }
        }

        $score = 0;
        foreach ($titles as $title) {
            $candidate = legacy_slugify($title);
            if ($candidate === $slug) {
                $score = max($score, 100);
            } elseif (strpos($candidate, $slug) !== false || strpos($slug, $candidate) !== false) {
                $score = max($score, 85);
            } else {
                similar_text($slug, $candidate, $pct);
                $score = max($score, (int)$pct);
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $row;
        }
    }

    return is_array($best) ? $best : null;
}

function legacy_resolve_anilist_anime(string $animeSlug): array
{
    static $cache = [];
    if (isset($cache[$animeSlug])) {
        return $cache[$animeSlug];
    }

    $query = <<<'GQL'
query ($search: String, $page: Int, $perPage: Int) {
  Page(page: $page, perPage: $perPage) {
    media(search: $search, type: ANIME, sort: SEARCH_MATCH) {
      id
      idMal
      format
      status
      season
      seasonYear
      episodes
      description(asHtml: false)
      bannerImage
      coverImage {
        extraLarge
        large
        medium
      }
      title {
        romaji
        english
        native
      }
      genres
      studios(isMain: true) {
        nodes {
          name
        }
      }
    }
  }
}
GQL;

    // $animeSlug is sometimes used as a title string too; try to normalize input.
    $search = str_replace('-', ' ', $animeSlug);
    $resp = legacy_anilist_graphql($query, [
        'search' => $search,
        'page' => 1,
        'perPage' => 10,
    ]);

    $rows = $resp['data']['Page']['media'] ?? [];

    if (empty($rows)) {
        $stripped = legacy_strip_type_suffix($search);
        if (strtolower($stripped) !== strtolower($search)) {
            $resp = legacy_anilist_graphql($query, [
                'search' => $stripped,
                'page' => 1,
                'perPage' => 10,
            ]);
            $rows = $resp['data']['Page']['media'] ?? [];
        }
    }

    $best = is_array($rows) ? legacy_pick_best_anilist_match(legacy_slugify($animeSlug), $rows) : null;
    $cache[$animeSlug] = is_array($best) ? $best : [];
    return $cache[$animeSlug];
}


function legacy_anilist_title(array $row, string $fallback = ''): string
{
    return legacy_pick_title($row['title']['english'] ?? null, $row['title']['romaji'] ?? null, $row['title']['native'] ?? null, $fallback);
}

function legacy_anilist_to_legacy_payload(array $row, string $animeSlug): array
{
    $title = legacy_anilist_title($row, legacy_unslug($animeSlug));
    $image = (string)($row['coverImage']['extraLarge'] ?? $row['coverImage']['large'] ?? $row['coverImage']['medium'] ?? '');
    $episodes = max(1, (int)($row['episodes'] ?? 0));
    if ($episodes <= 1) {
        $episodes = 24;
    }

    return [
        'mal_id' => (int)($row['idMal'] ?? 0),
        'anilist_id' => (int)($row['id'] ?? 0),
        'title' => $title,
        'title_english' => (string)($row['title']['english'] ?? ''),
        'title_japanese' => (string)($row['title']['native'] ?? ''),
        'type' => (string)($row['format'] ?? 'TV'),
        'status' => (string)($row['status'] ?? 'Unknown'),
        'year' => (int)($row['seasonYear'] ?? 0),
        'episodes' => $episodes,
        'synopsis' => trim(strip_tags((string)($row['description'] ?? ''))),
        'images' => [
            'jpg' => [
                'image_url' => $image,
                'large_image_url' => $image,
            ],
        ],
        'genres' => array_map(static function ($genre) {
            return ['name' => (string)$genre];
        }, is_array($row['genres'] ?? null) ? $row['genres'] : []),
        '_provider' => 'anilist',
        '_raw' => $row,
    ];
}

function legacy_anilist_media_list(string $mode = 'trending', int $page = 1, int $perPage = 25): array
{
    $season = strtolower((string)date('F'));
    $seasonMap = [
        'january' => 'WINTER',
        'february' => 'WINTER',
        'march' => 'WINTER',
        'april' => 'SPRING',
        'may' => 'SPRING',
        'june' => 'SPRING',
        'july' => 'SUMMER',
        'august' => 'SUMMER',
        'september' => 'SUMMER',
        'october' => 'FALL',
        'november' => 'FALL',
        'december' => 'FALL',
    ];
    $currentSeason = $seasonMap[$season] ?? 'FALL';

    $sort = 'TRENDING_DESC';
    $status = null;
    $format = null;
    $seasonArg = null;
    $seasonYear = null;

    switch ($mode) {
        case 'popular':
            $sort = 'POPULARITY_DESC';
            break;
        case 'seasonal':
            $sort = 'POPULARITY_DESC';
            $seasonArg = $currentSeason;
            $seasonYear = (int)date('Y');
            break;
        case 'airing':
            $sort = 'TRENDING_DESC';
            $status = 'RELEASING';
            break;
        case 'completed':
            $sort = 'POPULARITY_DESC';
            $status = 'FINISHED';
            break;
        case 'movie':
            $sort = 'POPULARITY_DESC';
            $format = 'MOVIE';
            break;
        default:
            $sort = 'TRENDING_DESC';
            break;
    }

    // IMPORTANT: AniList treats an explicitly-passed null typed variable (e.g.
    // `format: $format` where $format is null) as "filter where field IS null",
    // which returns 0 results. So we only include a filter argument when it's set.
    $varDecls  = ['$page: Int', '$perPage: Int', '$sort: [MediaSort]'];
    $mediaArgs = ['type: ANIME', 'sort: $sort'];
    $vars      = ['page' => $page, 'perPage' => $perPage, 'sort' => [$sort]];
    if ($status !== null)     { $varDecls[] = '$status: MediaStatus';   $mediaArgs[] = 'status: $status';         $vars['status'] = $status; }
    if ($format !== null)     { $varDecls[] = '$format: MediaFormat';   $mediaArgs[] = 'format: $format';         $vars['format'] = $format; }
    if ($seasonArg !== null)  { $varDecls[] = '$season: MediaSeason';   $mediaArgs[] = 'season: $season';         $vars['season'] = $seasonArg; }
    if ($seasonYear !== null) { $varDecls[] = '$seasonYear: Int';       $mediaArgs[] = 'seasonYear: $seasonYear'; $vars['seasonYear'] = $seasonYear; }

    $query = 'query (' . implode(', ', $varDecls) . ') {
  Page(page: $page, perPage: $perPage) {
    pageInfo { currentPage lastPage hasNextPage }
    media(' . implode(', ', $mediaArgs) . ') {
      id idMal format status seasonYear episodes
      coverImage { extraLarge large medium }
      title { romaji english native }
      genres
    }
  }
}';

    $resp = legacy_anilist_graphql($query, $vars);

    $rows = $resp['data']['Page']['media'] ?? [];
    $pageInfo = $resp['data']['Page']['pageInfo'] ?? [];
    return [
        'rows' => is_array($rows) ? $rows : [],
        'lastPage' => (int)($pageInfo['lastPage'] ?? 1),
    ];
}

/**
 * Airing schedule for one UTC day, built from AniList's airingSchedules —
 * NOT Jikan's /schedules endpoint. Jikan has been observed 504ing/timing
 * out against MAL repeatedly during development (see other legacy_* fallback
 * comments in this file); AniList has been reliable throughout, so it's the
 * primary source here rather than a fallback bolted on afterward.
 * $dayOffset: 0 = today (UTC), 1 = tomorrow, etc.
 */
function legacy_anilist_schedule_day(int $dayOffset = 0): array
{
    $dayStart = strtotime(gmdate('Y-m-d 00:00:00')) + $dayOffset * 86400;
    $dayEnd = $dayStart + 86400 - 1;

    $cacheKey = 'anilist_schedule:' . $dayStart;
    $cached = getCache($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $query = <<<'GQL'
query ($start: Int, $end: Int, $page: Int) {
  Page(page: $page, perPage: 50) {
    pageInfo { hasNextPage }
    airingSchedules(airingAt_greater: $start, airingAt_lesser: $end, sort: TIME) {
      airingAt
      episode
      media {
        id
        idMal
        format
        episodes
        title { romaji english native }
        coverImage { extraLarge large medium }
      }
    }
  }
}
GQL;

    $out = [];
    $page = 1;
    do {
        $resp = legacy_anilist_graphql($query, ['start' => $dayStart, 'end' => $dayEnd, 'page' => $page]);
        $rows = $resp['data']['Page']['airingSchedules'] ?? [];
        if (!is_array($rows) || empty($rows)) break;
        foreach ($rows as $row) {
            $media = $row['media'] ?? null;
            if (!is_array($media)) continue;
            $title = legacy_anilist_title($media, 'Unknown');
            $slug = legacy_slugify($title);
            $item = legacy_normalize_item(legacy_anilist_to_legacy_payload($media, $slug), $slug);
            $item['airingAt'] = (int)($row['airingAt'] ?? 0);
            $item['episodeNum'] = (int)($row['episode'] ?? 0);
            $out[] = $item;
        }
        $hasNext = (bool)($resp['data']['Page']['pageInfo']['hasNextPage'] ?? false);
        $page++;
    } while ($hasNext && $page <= 4); // cap: 200 airings/day is far more than any real day has

    setCache($cacheKey, $out, 1800);
    return $out;
}

function legacy_anilist_id_by_mal_id(int $malId): int
{
    if ($malId <= 0) {
        return 0;
    }
    $query = <<<'GQL'
query ($malId: Int) {
  Media(idMal: $malId, type: ANIME) {
    id
  }
}
GQL;
    $resp = legacy_anilist_graphql($query, ['malId' => $malId]);
    return (int)($resp['data']['Media']['id'] ?? 0);
}

function legacy_resolve_anime_by_slug($animeSlug)
{
    static $cache = [];
    if (isset($cache[$animeSlug])) {
        return $cache[$animeSlug];
    }

    // Persistent cache: avoids a fresh AniList GraphQL round-trip (~0.6-0.8s)
    // on every cache-miss watch_v2.php request for the same slug.
    $persistKey = 'resolve_anime_slug:' . $animeSlug;
    $persisted = getCache($persistKey);
    if (is_array($persisted)) {
        $cache[$animeSlug] = $persisted;
        return $persisted;
    }

    $anilist = legacy_resolve_anilist_anime($animeSlug);
    if (!empty($anilist)) {
        $cache[$animeSlug] = legacy_anilist_to_legacy_payload($anilist, $animeSlug);
        setCache($persistKey, $cache[$animeSlug], 21600);
        return $cache[$animeSlug];
    }

    // AniList's own title search found nothing usable for this slug (common for
    // alternate/abbreviated titles). Fall back to Jikan's title search, then use
    // the MAL id it gives us to look AniList back up by idMal — a 1:1 exact match,
    // not fuzzy title matching. Without this, $best has no anilist_id, streaming.php
    // gets $_fbAni = 0, and the episode player renders with zero servers.
    $query = str_replace('-', ' ', $animeSlug);
    $resp = fetchAPI("anime?q=" . rawurlencode($query) . "&limit=10");
    $data = isset($resp["data"]) && is_array($resp["data"]) ? $resp["data"] : [];
    $best = legacy_pick_best_jikan_match($animeSlug, $data);
    if (is_array($best)) {
        $malId = (int)($best['mal_id'] ?? 0);
        $aniId = legacy_anilist_id_by_mal_id($malId);
        if ($aniId > 0) {
            $best['anilist_id'] = $aniId;
        }
    }
    $cache[$animeSlug] = $best ?: [];
    if (!empty($cache[$animeSlug])) {
        setCache($persistKey, $cache[$animeSlug], 21600);
    }
    return $cache[$animeSlug];
}

function legacy_build_episode_list($sourceSlug, $count)
{
    $out = [];
    $count = max(0, (int)$count);
    for ($i = 1; $i <= $count; $i++) {
        $out[] = ["episodeId" => $sourceSlug . "-episode-" . $i, "episodeNum" => (string)$i];
    }
    return $out;
}

function legacy_extract_episode_count_from_anitaku($sourceSlug)
{
    // NOTE: Skip external anitaku.to HTTP call — it blocks page render for up to 8s.
    // Return 0 so callers fall back to Jikan's own episode count from anime data.
    return 0;

    /* Original anitaku scraping code disabled for performance:
    global $aniTakuBase;
    static $cache = [];
    if (isset($cache[$sourceSlug])) {
        return $cache[$sourceSlug];
    }

    $html = legacy_http_get($aniTakuBase . "category/" . rawurlencode($sourceSlug));
    if ($html === "") {
        $jikanResp = fetchAPI("anime?q=" . rawurlencode(str_replace('-', ' ', $sourceSlug)) . "&limit=5");
        foreach ($jikanResp['data'] ?? [] as $row) {
            if (!empty($row['episodes'])) {
                $cache[$sourceSlug] = (int)$row['episodes'];
                return $cache[$sourceSlug];
            }
        }
        $cache[$sourceSlug] = 24;
        return 24;
    }

    $max = 0;
    if (preg_match_all('/data-value="([0-9]+)-([0-9]+)"/', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $max = max($max, (int)$row[2]);
        }
    }
    if ($max === 0 && preg_match_all('/\/' . preg_quote($sourceSlug, '/') . '-episode-([0-9]+)/', $html, $m2)) {
        foreach ($m2[1] as $n) {
            $max = max($max, (int)$n);
        }
    }

    if ($max === 0) {
        $jikanResp = fetchAPI("anime?q=" . rawurlencode(str_replace('-', ' ', $sourceSlug)) . "&limit=5");
        foreach ($jikanResp['data'] ?? [] as $row) {
            if (!empty($row['episodes'])) {
                $cache[$sourceSlug] = (int)$row['episodes'];
                return $cache[$sourceSlug];
            }
        }
        $max = 24;
    }

    $cache[$sourceSlug] = $max;
    return $max;
    */
}

function legacy_normalize_item($row, $sourceSlug = "")
{
    $title = legacy_pick_title($row["title_english"] ?? null, $row["title"] ?? null, $row["title_japanese"] ?? null);
    $slug = !empty($sourceSlug) ? $sourceSlug : legacy_slugify($title);
    $img = $row["images"]["jpg"]["large_image_url"] ?? $row["images"]["jpg"]["image_url"] ?? "";
    $year = $row["year"] ?? ($row["aired"]["prop"]["from"]["year"] ?? "");
    return [
        "animeId" => $slug,
        "animeTitle" => $title,
        "animeImg" => $img,
        "imgUrl" => $img,
        "status" => $row["status"] ?? "Unknown",
        "releasedDate" => (string)$year,
        "type" => $row["type"] ?? "TV",
    ];
}

function legacy_pagination_html($currentPage, $lastPage, $query = [], $pathOverride = null)
{
    $currentPage = max(1, (int)$currentPage);
    $lastPage = max(1, (int)$lastPage);
    $q = $query;
    $html = '';
    $path = is_string($pathOverride) && $pathOverride !== ''
        ? $pathOverride
        : strtok($_SERVER["REQUEST_URI"] ?? '', '?');
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    if ($currentPage > 1) {
        $q["page"] = $currentPage - 1;
        $html .= '<li><a href="' . $path . '?' . http_build_query($q) . '">Prev</a></li>';
    }

    $start = max(1, $currentPage - 2);
    $end = min($lastPage, $currentPage + 2);
    for ($i = $start; $i <= $end; $i++) {
        $q["page"] = $i;
        $active = $i === $currentPage ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . $path . '?' . http_build_query($q) . '">' . $i . '</a></li>';
    }

    if ($currentPage < $lastPage) {
        $q["page"] = $currentPage + 1;
        $html .= '<li><a href="' . $path . '?' . http_build_query($q) . '">Next</a></li>';
    }
    return $html;
}

function legacy_json_list_from_jikan($endpoint, $page = 1)
{
    $resp = fetchAPI($endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . "page=" . (int)$page);
    $data = isset($resp["data"]) && is_array($resp["data"]) ? $resp["data"] : [];
    $list = [];
    foreach ($data as $row) {
        $list[] = legacy_normalize_item($row);
    }

    if (!empty($list)) {
        return [$list, $resp["pagination"]["last_visible_page"] ?? 1];
    }

    $mode = null;
    if ($endpoint === 'top/anime') {
        $mode = 'trending';
    } elseif ($endpoint === 'top/anime?filter=bypopularity') {
        $mode = 'popular';
    } elseif ($endpoint === 'seasons/now') {
        $mode = 'seasonal';
    } elseif ($endpoint === 'top/anime?type=movie') {
        $mode = 'movie';
    } elseif ($endpoint === 'top/anime?filter=airing') {
        $mode = 'airing';
    } elseif ($endpoint === 'anime?status=complete&order_by=popularity&sort=asc') {
        $mode = 'completed';
    }

    if ($mode === null) {
        return [$list, $resp["pagination"]["last_visible_page"] ?? 1];
    }

    $fallback = legacy_anilist_media_list($mode, (int)$page, 25);
    $fallbackRows = [];
    foreach (($fallback['rows'] ?? []) as $row) {
        $fallbackRows[] = legacy_normalize_item(legacy_anilist_to_legacy_payload($row, legacy_slugify(legacy_anilist_title($row, 'anime'))));
    }

    return [$fallbackRows, (int)($fallback['lastPage'] ?? 1)];
}

function legacy_get_anime_payload($animeSlug)
{
    $anime = legacy_resolve_anime_by_slug($animeSlug);
    if (empty($anime)) {
        return [
            "name" => legacy_unslug($animeSlug),
            "othername" => "",
            "type" => "TV",
            "released" => "",
            "status" => "Unknown",
            "genres" => [],
            "synopsis" => "No synopsis available.",
            "imageUrl" => "",
            "episode_id" => [],
        ];
    }

    $title = legacy_pick_title($anime["title_english"] ?? null, $anime["title"] ?? null, $anime["title_japanese"] ?? null, legacy_unslug($animeSlug));
    $sourceSlug = legacy_resolve_source_slug($title, $animeSlug);

    $full = fetchAPI("anime/" . (int)$anime["mal_id"] . "/full");
    $fullData = !empty($full["data"]) && is_array($full["data"]) ? $full["data"] : $anime;
    $epCount = legacy_extract_episode_count_from_anitaku($sourceSlug);
    if ($epCount <= 0) {
        $epCount = (int)($fullData["episodes"] ?? 0);
    }
    if ($epCount <= 0) {
        $epCount = 24;
    }
    $epCount = min($epCount, 5000);
    $episodeList = legacy_build_episode_list($sourceSlug, $epCount);

    $genres = [];
    if (!empty($fullData["genres"]) && is_array($fullData["genres"])) {
        foreach ($fullData["genres"] as $g) {
            if (!empty($g["name"])) {
                $genres[] = $g["name"];
            }
        }
    }

    return [
        "name" => $title,
        "othername" => $fullData["title_japanese"] ?? "",
        "type" => $fullData["type"] ?? "TV",
        "released" => (string)($fullData["year"] ?? ($fullData["aired"]["prop"]["from"]["year"] ?? "")),
        "status" => $fullData["status"] ?? "Unknown",
        "genres" => $genres,
        "synopsis" => $fullData["synopsis"] ?? "No synopsis available.",
        "imageUrl" => $fullData["images"]["jpg"]["large_image_url"] ?? $fullData["images"]["jpg"]["image_url"] ?? "",
        "episode_id" => $episodeList,
    ];
}

function legacy_get_episode_list_from_jikan(int $malId, string $animeSlug, int $fallbackCount = 0): array
{
    $cacheKey = 'episode_list:' . $malId . ':' . $animeSlug . ':' . (int)$fallbackCount;
    $cached = getCache($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $episodes = [];
    $page = 1;
    $safetyCounter = 0;
    // Fetch every page Jikan has. Long-running series (One Piece, Detective
    // Conan, etc.) can exceed 1000 episodes / 10+ pages — the old 3-page cap
    // (300 episodes) silently truncated the list for exactly those shows.
    // Result is cached for 6h (see setCache below), so the cold-start cost of
    // walking all pages is paid once per anime per cache window, not per request.
    $maxPages = 40; // 40 * 100/page = 4000 episodes ceiling, just a runaway guard

    do {
        if ($malId <= 0) {
            break;
        }

        $resp = fetchAPI("anime/" . $malId . "/episodes?page=" . $page);
        $rows = $resp['data'] ?? [];

        foreach ($rows as $row) {
            $episodeNum = (int)($row['number'] ?? (count($episodes) + 1));
            if ($episodeNum < 1) {
                $episodeNum = count($episodes) + 1;
            }

            $episodes[$episodeNum] = [
                'episodeNum' => $episodeNum,
                'episodeId' => $animeSlug . '-episode-' . $episodeNum,
                'title' => $row['title'] ?? ('Episode ' . $episodeNum),
                'filler' => !empty($row['filler']),
                'recap' => !empty($row['recap']),
            ];
        }

        $page++;
        $safetyCounter++;
        $hasNext = !empty($resp['pagination']['has_next_page']) && $safetyCounter < $maxPages;
        // Jikan rate-limits at ~3 req/sec. Firing pages back-to-back for long
        // series (700+ episodes / 8+ pages) gets throttled partway through and
        // silently truncates the list. This only runs on a cache miss (6h TTL).
        if ($hasNext) {
            usleep(400000);
        }
    } while ($hasNext);

    // If Jikan gave us fewer episodes than the total count, pad with stub entries
    // so the episode list sidebar shows the correct range without extra API calls.
    if ($fallbackCount > count($episodes)) {
        for ($i = count($episodes) + 1; $i <= $fallbackCount; $i++) {
            if (!isset($episodes[$i])) {
                $episodes[$i] = [
                    'episodeNum' => $i,
                    'episodeId'  => $animeSlug . '-episode-' . $i,
                    'title'      => '',
                    'filler'     => false,
                    'recap'      => false,
                ];
            }
        }
    }

    if (empty($episodes) && $fallbackCount > 0) {
        foreach (legacy_build_episode_list($animeSlug, $fallbackCount) as $row) {
            $episodeNum = (int)($row['episodeNum'] ?? 0);
            if ($episodeNum < 1) continue;
            $episodes[$episodeNum] = [
                'episodeNum' => $episodeNum,
                'episodeId'  => $row['episodeId'],
                'title'      => '',
                'filler'     => false,
                'recap'      => false,
            ];
        }
    }

    ksort($episodes, SORT_NUMERIC);
    $final = array_values($episodes);
    setCache($cacheKey, $final, 21600);
    return $final;
}

function legacy_get_watch_context(string $animeSlug): array
{
    $cacheKey = 'watch_context:' . $animeSlug;
    $cached = getCache($cacheKey);
    if (is_array($cached) && !empty($cached['anime'])) {
        return $cached;
    }

    $anime = legacy_resolve_anime_by_slug($animeSlug);
    if (empty($anime)) {
        return [];
    }

    $malId = (int)($anime['mal_id'] ?? 0);
    // Use the basic anime data first; only fetch /full if we don't already have synopsis/genres
    $needsFull = empty($anime['synopsis']) && empty($anime['genres']);
    $full = ($malId > 0 && $needsFull) ? fetchAPI("anime/" . $malId . "/full") : [];
    $animeData = !empty($full['data']) && is_array($full['data']) ? $full['data'] : $anime;
    // Jikan's /full payload has no anilist_id/mal_id keys — carry them over from
    // $anime (the resolved payload) so streaming.php can still find a stream server.
    if (!empty($full['data'])) {
        $animeData['anilist_id'] = (int)($anime['anilist_id'] ?? 0);
        $animeData['mal_id'] = $malId;
    }
    $title = legacy_pick_title($animeData['title_english'] ?? null, $animeData['title'] ?? null, $animeData['title_japanese'] ?? null, legacy_unslug($animeSlug));
    $sourceSlug = legacy_resolve_source_slug($title, $animeSlug);

    // Use Jikan's episode count directly — anitaku scraping is disabled for performance
    $episodeCount = (int)($animeData['episodes'] ?? 0);
    if ($episodeCount <= 0) {
        $episodeCount = 24; // sensible default
    }
    $episodeCount = max(1, $episodeCount);

    $context = [
        'anime' => $animeData,
        'sourceSlug' => $sourceSlug,
        'episodes' => legacy_get_episode_list_from_jikan($malId, $sourceSlug, $episodeCount),
    ];

    setCache($cacheKey, $context, 21600);
    return $context;
}

function legacy_get_episode_payload($episodeId)
{
    global $aniTakuBase;
    $episodeId = trim($episodeId, '/');
    if (!preg_match('/^(.*)-episode-([0-9]+)$/', $episodeId, $m)) {
        $animeSlug = $episodeId;
        $epNum = 1;
    } else {
        $animeSlug = $m[1];
        $epNum = (int)$m[2];
    }

    $animeName = legacy_unslug($animeSlug);
    $maxEp = legacy_extract_episode_count_from_anitaku($animeSlug);
    if ($maxEp <= 0) {
        $maxEp = max($epNum, 24);
    }

    $episodePage = legacy_http_get($aniTakuBase . rawurlencode($episodeId));
    $stream = "";
    if (preg_match('/data-video="([^"]+)"/', $episodePage, $vm)) {
        $stream = html_entity_decode($vm[1], ENT_QUOTES);
    }
    if ($stream === "") {
        $anime = legacy_resolve_anime_by_slug($animeSlug);
        $malId = (int)($anime['mal_id'] ?? 0);
        if ($malId > 0) {
            $stream = "https://vidsrc.xyz/embed/anime?mal=" . $malId . "&episode=" . $epNum;
        } else {
            $stream = "https://vidsrc.to/embed/anime/" . rawurlencode($animeSlug) . "/" . $epNum;
        }
    }

    $prev = $epNum > 1 ? '/' . $animeSlug . '-episode-' . ($epNum - 1) : '';
    $next = $epNum < $maxEp ? '/' . $animeSlug . '-episode-' . ($epNum + 1) : '';

    return [
        "anime_info" => $animeSlug,
        "animeNameWithEP" => $animeName . " Episode " . $epNum,
        "ep_num" => (string)$epNum,
        "ep_download" => $stream !== "" ? $stream : ($aniTakuBase . $episodeId),
        "prevEpText" => $prev !== '' ? 'Prev' : '',
        "prevEpLink" => $prev,
        "nextEpText" => $next !== '' ? 'Next' : '',
        "nextEpLink" => $next,
    ];
}

function legacy_recent_release_payload($type = 1, $page = 1)
{
    [$airingRows, $lastPage] = legacy_json_list_from_jikan("top/anime?filter=airing", (int)$page);
    $out = [];
    $label = "SUB";
    if ((int)$type === 2) {
        $label = "DUB";
    } elseif ((int)$type === 3) {
        $label = "CHINESE";
    }
    foreach ($airingRows as $row) {
        $title = $row["animeTitle"] ?? $row["name"] ?? "Unknown";
        $sourceSlug = legacy_resolve_source_slug($title, (string)($row["animeId"] ?? legacy_slugify($title)));
        $epNum = 1;
        $out[] = [
            "episodeId" => $sourceSlug . "-episode-" . $epNum,
            "episodeNum" => (string)$epNum,
            "subOrDub" => $label,
            "imgUrl" => $row["imgUrl"] ?? $row["animeImg"] ?? "",
            "name" => $title,
        ];
    }
    return [$out, $lastPage];
}

function legacy_search_payload($keyword, $page = 1)
{
    $resp = fetchAPI("anime?q=" . rawurlencode($keyword) . "&limit=25&page=" . (int)$page);
    $data = isset($resp["data"]) && is_array($resp["data"]) ? $resp["data"] : [];
    $out = [];

    foreach ($data as $row) {
        $title = legacy_pick_title($row["title_english"] ?? null, $row["title"] ?? null);
        $slug = legacy_slugify($title);

        $img = $row["images"]["jpg"]["large_image_url"]
            ?? $row["images"]["jpg"]["image_url"]
            ?? '';

        $status = $row["status"] ?? "Unknown";
        $type = $row["type"] ?? "TV";

        // Jikan v4 provides year on some payloads; handle missing gracefully.
        $releasedDate = '';
        if (!empty($row["year"])) {
            $releasedDate = (string)$row["year"];
        } elseif (!empty($row["aired"]["prop"]["from"]["year"])) {
            $releasedDate = (string)$row["aired"]["prop"]["from"]["year"];
        }

        $out[] = [
            "animeId" => $slug,
            "animeTitle" => $title,
            "animeImg" => $img,
            "status" => (string)$status,
            "type" => (string)$type,
            "releasedDate" => (string)$releasedDate,
        ];
    }

    return [$out, $resp["pagination"]["last_visible_page"] ?? 1];
}

function legacy_api_array($endpoint)
{
    $parsed = parse_url("https://legacy.local/" . ltrim($endpoint, '/'));
    $path = ltrim($parsed["path"] ?? "", '/');
    parse_str($parsed["query"] ?? "", $q);
    $page = isset($q["page"]) ? max(1, (int)$q["page"]) : 1;

    if (preg_match('#^getAnime/(.+)$#', $path, $m)) {
        return legacy_get_anime_payload($m[1]);
    }
    if (preg_match('#^getEpisode/(.+)$#', $path, $m)) {
        return legacy_get_episode_payload($m[1]);
    }
    if (preg_match('#^search$#', $path)) {
        return legacy_search_payload($q["keyw"] ?? "", $page)[0];
    }
    if (preg_match('#^searchPage$#', $path)) {
        $keyword = $q["keyw"] ?? "";
        [, $last] = legacy_search_payload($keyword, $page);
        return ["pagination" => legacy_pagination_html($page, $last, ["keyword" => $keyword])];
    }
    if (preg_match('#^animeList$#', $path)) {
        return legacy_json_list_from_jikan("top/anime", $page)[0];
    }
    if (preg_match('#^anime-list-page$#', $path)) {
        [, $last] = legacy_json_list_from_jikan("top/anime", $page);
        return ["pagination" => legacy_pagination_html($page, $last)];
    }
    if (preg_match('#^animeListAZ$#', $path)) {
        $letter = strtoupper($q["aph"] ?? "A");
        $resp = fetchAPI("anime?q=" . rawurlencode($letter) . "&order_by=title&sort=asc&limit=25&page=" . $page);
        $out = [];
        foreach (($resp["data"] ?? []) as $row) {
            $title = legacy_pick_title($row["title_english"] ?? null, $row["title"] ?? null);
            if (strtoupper(substr($title, 0, 1)) !== $letter) {
                continue;
            }
            $slug = legacy_resolve_source_slug($title, legacy_slugify($title));
            $out[] = ["animeId" => $slug, "animeTitle" => $title];
        }
        return $out;
    }
    if (preg_match('#^anime-AZ-page$#', $path)) {
        return ["pagination" => legacy_pagination_html($page, 10, ["aph" => $q["aph"] ?? "A"])];
    }
    if (preg_match('#^popular$#', $path)) {
        return legacy_json_list_from_jikan("top/anime?filter=bypopularity", $page)[0];
    }
    if (preg_match('#^popularPage$#', $path)) {
        [, $last] = legacy_json_list_from_jikan("top/anime?filter=bypopularity", $page);
        return ["pagination" => legacy_pagination_html($page, $last)];
    }
    if (preg_match('#^new-season$#', $path)) {
        return legacy_json_list_from_jikan("seasons/now", $page)[0];
    }
    if (preg_match('#^newSeasonPage$#', $path)) {
        [, $last] = legacy_json_list_from_jikan("seasons/now", $page);
        return ["pagination" => legacy_pagination_html($page, $last)];
    }
    if (preg_match('#^anime-movies$#', $path)) {
        return legacy_json_list_from_jikan("top/anime?type=movie", $page)[0];
    }
    if (preg_match('#^moviePage$#', $path)) {
        [, $last] = legacy_json_list_from_jikan("top/anime?type=movie", $page);
        return ["pagination" => legacy_pagination_html($page, $last)];
    }
    if (preg_match('#^ongoing-anime$#', $path)) {
        return legacy_json_list_from_jikan("top/anime?filter=airing", $page)[0];
    }
    if (preg_match('#^ongoingPage$#', $path)) {
        [, $last] = legacy_json_list_from_jikan("top/anime?filter=airing", $page);
        return ["pagination" => legacy_pagination_html($page, $last)];
    }
    if (preg_match('#^completed-anime$#', $path)) {
        return legacy_json_list_from_jikan("anime?status=complete&order_by=popularity&sort=asc", $page)[0];
    }
    if (preg_match('#^completedPage$#', $path)) {
        [, $last] = legacy_json_list_from_jikan("anime?status=complete&order_by=popularity&sort=asc", $page);
        return ["pagination" => legacy_pagination_html($page, $last)];
    }
    if (preg_match('#^genre/([^/]+)$#', $path, $m)) {
        $genreKey = strtolower($m[1]);
        $map = legacy_get_genre_map();
        $genreId = $map[$genreKey] ?? 1;
        $resp = fetchAPI("anime?genres=" . $genreId . "&page=" . $page);
        $out = [];
        foreach (($resp["data"] ?? []) as $row) {
            $item = legacy_normalize_item($row);
            $out[] = [
                "animeId" => $item["animeId"],
                "animeTitle" => $item["animeTitle"],
                "animeImg" => $item["animeImg"],
                "releasedDate" => $item["releasedDate"],
            ];
        }
        return $out;
    }
    if (preg_match('#^genrePage$#', $path)) {
        $genreKey = strtolower($q["genre"] ?? "action");
        $map = legacy_get_genre_map();
        $genreId = $map[$genreKey] ?? 1;
        $resp = fetchAPI("anime?genres=" . $genreId . "&page=" . $page);
        $last = $resp["pagination"]["last_visible_page"] ?? 1;
        return ["pagination" => legacy_pagination_html($page, $last, ["genre" => $genreKey])];
    }
    if (preg_match('#^season/([^/]+)$#', $path, $m)) {
        $sub = strtolower($m[1]);
        if (in_array($sub, ["ova", "ona", "special", "tv-series"], true)) {
            $type = $sub === "tv-series" ? "tv" : $sub;
            return legacy_json_list_from_jikan("top/anime?type=" . $type, $page)[0];
        }
        if (preg_match('/^(winter|spring|summer|fall)-([0-9]{4})-anime$/', $sub, $sm)) {
            return legacy_json_list_from_jikan("seasons/" . $sm[2] . "/" . $sm[1], $page)[0];
        }
        return legacy_json_list_from_jikan("top/anime", $page)[0];
    }
    if (preg_match('#^subCategoryPage$#', $path)) {
        $sub = strtolower($q["subCategory"] ?? "tv-series");
        if (in_array($sub, ["ova", "ona", "special", "tv-series"], true)) {
            $type = $sub === "tv-series" ? "tv" : $sub;
            [, $last] = legacy_json_list_from_jikan("top/anime?type=" . $type, $page);
            return ["pagination" => legacy_pagination_html($page, $last, ["subCategory" => $sub])];
        }
        if (preg_match('/^(winter|spring|summer|fall)-([0-9]{4})-anime$/', $sub, $sm)) {
            [, $last] = legacy_json_list_from_jikan("seasons/" . $sm[2] . "/" . $sm[1], $page);
            return ["pagination" => legacy_pagination_html($page, $last, ["subCategory" => $sub])];
        }
        return ["pagination" => legacy_pagination_html($page, 1, ["subCategory" => $sub])];
    }
    if (preg_match('#^recent-release$#', $path)) {
        $type = (int)($q["type"] ?? 1);
        return legacy_recent_release_payload($type, $page)[0];
    }
    if (preg_match('#^recent-release-page$#', $path)) {
        $type = (int)($q["type"] ?? 1);
        [, $last] = legacy_recent_release_payload($type, $page);
        return ["pagination" => legacy_pagination_html($page, $last, ["type" => $type])];
    }
    if (preg_match('#^top-airing$#', $path)) {
        $resp = fetchAPI("top/anime?filter=airing&limit=10");
        $out = [];
        foreach (($resp["data"] ?? []) as $row) {
            $item = legacy_normalize_item($row);
            $out[] = [
                "animeId" => $item["animeId"],
                "animeTitle" => $item["animeTitle"],
                "animeImg" => $item["animeImg"],
                "latestEp" => "Episode 1",
            ];
        }
        return $out;
    }
    if (preg_match('#^getOngoingSeries$#', $path)) {
        $resp = fetchAPI("top/anime?filter=airing&limit=25");
        $out = [];
        foreach (($resp["data"] ?? []) as $row) {
            $title = legacy_pick_title($row["title_english"] ?? null, $row["title"] ?? null);
            $slug = legacy_resolve_source_slug($title, legacy_slugify($title));
            $out[] = ["animeId" => "/anime/" . $slug];
        }
        return $out;
    }
    return [];
}

function legacy_api($endpoint)
{
    // Endpoint-level logging and response sanity checks.
    error_log('[legacy_api] endpoint=' . $endpoint);

    // legacy_api_array returns an array (or empty array). We still log the final JSON.
    $res = legacy_api_array($endpoint);

    $json = json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Heuristic: if upstream returned HTML/Cloudflare, json_encode will still succeed but content may be unexpected.
    // This helps distinguish “empty array” vs “transport returned HTML”.
    $preview = substr((string)$json, 0, 500);
    $looksLikeHtml = (stripos($preview, '<!DOCTYPE') !== false
        || stripos($preview, '<html') !== false
        || stripos($preview, 'cloudflare') !== false
        || stripos($preview, 'just a moment') !== false
        || stripos($preview, 'attention required') !== false);

    error_log('[legacy_api] endpoint=' . $endpoint
        . ' res_type=' . (is_array($res) ? 'array' : gettype($res))
        . ' ok=' . (is_array($res) && !empty($res) ? 'true' : 'false')
        . ' json_len=' . strlen((string)$json)
        . ' looks_like_html=' . ($looksLikeHtml ? 'true' : 'false')
        . ' json_preview=' . $preview
    );

    return $json;
}



function app_user()
{
    return app_current_user();
}

function app_logged_in(): bool
{
    return app_is_logged_in();
}

function app_recommend_anime(array $animePayload, int $limit = 8): array

{
    $genres = $animePayload['genres'] ?? [];
    if (!is_array($genres) || empty($genres)) {
        return [];
    }
    $genre = strtolower((string)$genres[0]);
    $map = legacy_get_genre_map();
    $genreId = $map[$genre] ?? null;
    if (!$genreId) {
        return [];
    }

    $resp = fetchAPI('anime?genres=' . $genreId . '&limit=' . max(4, $limit + 2));
    $out = [];
    foreach (($resp['data'] ?? []) as $row) {
        $item = legacy_normalize_item($row);
        if (!empty($animePayload['name']) && strtolower($item['animeTitle']) === strtolower((string)$animePayload['name'])) {
            continue;
        }
        $out[] = $item;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

?>
