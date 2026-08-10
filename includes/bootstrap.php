<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
}

// Production deploy must never leak stack traces/file paths to visitors.
// Errors still get logged server-side either way.
$appEnv = (string)(getenv('APP_ENV') ?: 'production');
ini_set('log_errors', '1');
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/security.php';
app_secure_session_start();

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auth_security.php';
app_try_resume_from_remember_cookie();
require_once __DIR__ . '/user_data.php';
require_once __DIR__ . '/tracking.php';
require_once __DIR__ . '/admin_tools.php';
require_once __DIR__ . '/comments.php';
require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/community.php';
require_once __DIR__ . '/analytics.php';
