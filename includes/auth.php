<?php
require_once __DIR__ . '/db.php';

function app_client_ip(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim(explode(',', (string)$_SERVER[$key])[0]);
            return substr($value, 0, 64);
        }
    }
    return '0.0.0.0';
}

function app_auth_too_many_attempts(string $email): array
{
    $ip = app_client_ip();

    // IP-level throttle: blocks brute-forcing many different accounts from
    // one IP. 10 failed attempts/minute regardless of which email was tried.
    $sinceIp = gmdate('c', time() - 60);
    $ipStmt = app_db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE created_at >= ? AND success = 0 AND ip = ?');
    $ipStmt->execute([$sinceIp, $ip]);
    if ((int)$ipStmt->fetchColumn() >= 10) {
        return ['blocked' => true, 'reason' => 'Too many login attempts from this network. Try again in a minute.'];
    }

    // Account-level lock: 5 failed attempts against this specific account in
    // 15 minutes locks it temporarily, even if attempts came from different IPs.
    $sinceAcct = gmdate('c', time() - (15 * 60));
    $acctStmt = app_db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE created_at >= ? AND success = 0 AND email = ?');
    $acctStmt->execute([$sinceAcct, strtolower($email)]);
    if ((int)$acctStmt->fetchColumn() >= 5) {
        return ['blocked' => true, 'reason' => 'Too many failed attempts on this account. Try again in 15 minutes.'];
    }

    return ['blocked' => false, 'reason' => ''];
}

function app_auth_record_attempt(string $email, bool $success): void
{
    $stmt = app_db()->prepare('INSERT INTO login_attempts(ip, email, success, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([app_client_ip(), strtolower($email), $success ? 1 : 0, gmdate('c')]);
}

function app_auth_register(string $email, string $username, string $password): array
{
    $email = strtolower(trim($email));
    $username = trim($username);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }
    if (strlen($username) < 3 || strlen($username) > 24 || !preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        return ['ok' => false, 'error' => 'Username must be 3-24 chars (letters/numbers/_)'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters'];
    }

    $stmt = app_db()->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'Email or username already exists'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $now = gmdate('c');
    $insert = app_db()->prepare('INSERT INTO users(email, username, password_hash, role, is_banned, created_at, updated_at) VALUES (?, ?, ?, "user", 0, ?, ?)');
    $insert->execute([$email, $username, $hash, $now, $now]);

    return ['ok' => true, 'user_id' => (int)app_db()->lastInsertId()];
}

function app_auth_login(string $emailOrUsername, string $password, bool $remember = false): array
{
    $identity = trim($emailOrUsername);

    $throttle = app_auth_too_many_attempts($identity);
    if ($throttle['blocked']) {
        return ['ok' => false, 'error' => $throttle['reason']];
    }

    $stmt = app_db()->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([strtolower($identity), $identity]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        app_auth_record_attempt($identity, false);
        return ['ok' => false, 'error' => 'Invalid credentials'];
    }

    if ((int)$user['is_banned'] === 1) {
        app_auth_record_attempt($identity, false);
        return ['ok' => false, 'error' => 'Your account is banned'];
    }

    app_auth_record_attempt($identity, true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = (string)$user['role'];
    $_SESSION['user_name'] = (string)$user['username'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['last_regen'] = time();
    session_regenerate_id(true);

    app_db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([gmdate('c'), $user['id']]);
    app_create_user_session((int)$user['id'], $remember);

    return ['ok' => true, 'user' => $user];
}

function app_auth_logout(): void
{
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['session_row_id'])) {
        app_revoke_session((int)$_SESSION['user_id'], (int)$_SESSION['session_row_id']);
    }
    if (!empty($_COOKIE[APP_REMEMBER_COOKIE])) {
        setcookie(APP_REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function app_current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!empty($_SESSION['last_regen']) && (time() - (int)$_SESSION['last_regen']) > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }

    $stmt = app_db()->prepare('SELECT id, email, username, role, is_banned, created_at, avatar, email_verified, last_login FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_banned'] === 1) {
        app_auth_logout();
        return null;
    }

    return $user;
}

function app_require_login(): array
{
    $user = app_current_user();
    if (!$user) {
        app_redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $user;
}

function app_require_admin(): array
{
    $user = app_current_user();
    if (!$user) {
        app_redirect('/admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin'));
    }
    if (($user['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function app_is_logged_in(): bool
{
    return app_current_user() !== null;
}
