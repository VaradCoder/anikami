<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/..');
}

function app_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_json_response(array $payload, int $statusCode = 200): void
{
    if (!empty($GLOBALS['app_internal_api_capture'])) {
        $GLOBALS['app_internal_api_capture_response'] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        throw new RuntimeException('__APP_INTERNAL_API_CAPTURE__');
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function app_api_success(array $data = [], array $meta = []): void
{
    app_json_response([
        'ok' => true,
        'data' => $data,
        'meta' => $meta,
        'error' => null,
    ]);
}

function app_api_error(string $message, int $statusCode = 400, array $meta = [], $detail = null): void
{
    $error = [
        'message' => $message,
        'code' => $statusCode,
    ];

    if ($detail !== null) {
        $error['detail'] = $detail;
    }

    app_json_response([
        'ok' => false,
        'data' => [],
        'meta' => $meta,
        'error' => $error,
    ], $statusCode);
}

function app_redirect(string $path): void
{
    // Every other internal link in the codebase is built as $websiteUrl . '/x',
    // which already accounts for the site living under a subpath (e.g. local
    // XAMPP serving from /anikatsu/ instead of domain root). app_redirect()
    // was the one place that didn't, sending root-relative Location headers
    // straight past the subpath — e.g. admin login/logout would bounce to
    // /admin/index.php instead of /anikatsu/admin/index.php and 404.
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }
    $basePath = (string)($GLOBALS['basePath'] ?? '');
    if ($basePath !== '' && strpos($path, $basePath . '/') !== 0 && $path !== $basePath) {
        $path = $basePath . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

function app_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function app_validate_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function app_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        app_json_response(['ok' => false, 'error' => 'Method Not Allowed'], 405);
    }
}

function app_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == '443');
}

function app_secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 14,
        'path' => $cookieParams['path'] ?: '/',
        'domain' => $cookieParams['domain'] ?: '',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('anikatsu_session');
    session_start();
}
