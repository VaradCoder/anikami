<?php
require_once __DIR__ . '/../_config.php';
app_require_method('POST');

$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf'] ?? '';
if (!app_validate_csrf($csrf)) {
    app_json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
}

if ($action === 'register') {
    $result = app_auth_register($_POST['email'] ?? '', $_POST['username'] ?? '', $_POST['password'] ?? '');
    if (!$result['ok']) {
        app_json_response($result, 400);
    }
    $login = app_auth_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    app_json_response($login, $login['ok'] ? 200 : 400);
}

if ($action === 'login') {
    $result = app_auth_login($_POST['identity'] ?? '', $_POST['password'] ?? '');
    app_json_response($result, $result['ok'] ? 200 : 400);
}

if ($action === 'logout') {
    app_auth_logout();
    app_json_response(['ok' => true]);
}

app_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
