<?php
require_once __DIR__ . '/db.php';

const APP_REMEMBER_COOKIE = 'anikatsu_remember';
const APP_REMEMBER_DAYS = 30;

/* ── Device name parsing (lightweight, not a full UA library) ── */
function app_parse_device_name(string $userAgent): string
{
    $ua = $userAgent;
    $browser = 'Unknown Browser';
    if (preg_match('/Edg\//', $ua)) $browser = 'Edge';
    elseif (preg_match('/OPR\//', $ua)) $browser = 'Opera';
    elseif (preg_match('/Chrome\//', $ua) && !preg_match('/Chromium/', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\//', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Safari\//', $ua) && !preg_match('/Chrome/', $ua)) $browser = 'Safari';

    $os = 'Unknown OS';
    if (preg_match('/Windows/', $ua)) $os = 'Windows';
    elseif (preg_match('/Android/', $ua)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad/', $ua)) $os = 'iOS';
    elseif (preg_match('/Mac OS X/', $ua)) $os = 'macOS';
    elseif (preg_match('/Linux/', $ua)) $os = 'Linux';

    return $browser . ' on ' . $os;
}

/* ── Device sessions (covers both "Phase 2 device tracking" and
   "remember me" — a single table backs both: every login creates a row;
   if "remember me" was checked, a long-lived signed cookie points at it
   so the session can be re-established on a future visit). ──
   NOTE: "location" is left null — real geolocation needs a 3rd-party IP
   lookup API (MaxMind, ipinfo.io, etc.) that isn't configured here. Wire
   one in if you want city/country shown instead of just the IP. */
function app_create_user_session(int $userId, bool $remember = false): void
{
    $rawToken = bin2hex(random_bytes(32));
    $hash = hash('sha256', $rawToken);
    $now = gmdate('c');
    $days = $remember ? APP_REMEMBER_DAYS : 1;
    $expiresAt = gmdate('c', time() + $days * 86400);

    $stmt = app_db()->prepare(
        'INSERT INTO user_sessions(user_id, token_hash, device_name, ip_address, location, is_remember, expires_at, created_at, last_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $hash,
        app_parse_device_name((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
        app_client_ip(),
        null,
        $remember ? 1 : 0,
        $expiresAt,
        $now,
        $now,
    ]);
    $sessionRowId = (int)app_db()->lastInsertId();
    $_SESSION['session_row_id'] = $sessionRowId;

    if ($remember) {
        setcookie(APP_REMEMBER_COOKIE, $sessionRowId . ':' . $rawToken, [
            'expires' => time() + APP_REMEMBER_DAYS * 86400,
            'path' => '/',
            'secure' => app_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/* ── Resume login from the remember-me cookie when there's no active
   PHP session (e.g. browser restarted). Called once per request before
   app_current_user() needs the result. ── */
function app_try_resume_from_remember_cookie(): void
{
    if (!empty($_SESSION['user_id'])) {
        return; // already logged in via normal session
    }
    $cookie = $_COOKIE[APP_REMEMBER_COOKIE] ?? '';
    if (!is_string($cookie) || strpos($cookie, ':') === false) {
        return;
    }
    [$rowId, $rawToken] = explode(':', $cookie, 2);
    $rowId = (int)$rowId;
    if ($rowId <= 0 || $rawToken === '') {
        return;
    }

    $stmt = app_db()->prepare('SELECT * FROM user_sessions WHERE id = ? AND is_remember = 1 LIMIT 1');
    $stmt->execute([$rowId]);
    $row = $stmt->fetch();
    if (!$row || !hash_equals((string)$row['token_hash'], hash('sha256', $rawToken))) {
        return; // invalid token — do not touch the cookie, just don't log in
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        app_db()->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([$rowId]);
        return;
    }

    $userStmt = app_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([(int)$row['user_id']]);
    $user = $userStmt->fetch();
    if (!$user || (int)$user['is_banned'] === 1) {
        return;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = (string)$user['role'];
    $_SESSION['user_name'] = (string)$user['username'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['last_regen'] = time();
    $_SESSION['session_row_id'] = $rowId;
    session_regenerate_id(true);

    app_db()->prepare('UPDATE user_sessions SET last_active = ? WHERE id = ?')->execute([gmdate('c'), $rowId]);
}

function app_list_user_sessions(int $userId): array
{
    $stmt = app_db()->prepare('SELECT id, device_name, ip_address, location, is_remember, last_active, created_at FROM user_sessions WHERE user_id = ? AND expires_at > ? ORDER BY last_active DESC');
    $stmt->execute([$userId, gmdate('c')]);
    return $stmt->fetchAll();
}

function app_revoke_session(int $userId, int $sessionRowId): void
{
    $stmt = app_db()->prepare('DELETE FROM user_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionRowId, $userId]);
}

function app_revoke_all_sessions(int $userId, ?int $exceptRowId = null): void
{
    if ($exceptRowId !== null) {
        $stmt = app_db()->prepare('DELETE FROM user_sessions WHERE user_id = ? AND id != ?');
        $stmt->execute([$userId, $exceptRowId]);
    } else {
        $stmt = app_db()->prepare('DELETE FROM user_sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}

/* ── Email verification ──
   No outgoing-mail provider is configured yet, so this returns the raw
   verification link instead of emailing it. Display it to the user (or
   read it from the email_verifications table) until SMTP/an email API is
   wired in — swap app_create_email_verification's caller to send mail
   instead of displaying the link once that's available. */
function app_create_email_verification(int $userId): string
{
    $token = bin2hex(random_bytes(24));
    $stmt = app_db()->prepare('INSERT INTO email_verifications(user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $token, gmdate('c', time() + 86400), gmdate('c')]);
    return $token;
}

function app_verify_email_token(string $token): bool
{
    $stmt = app_db()->prepare('SELECT * FROM email_verifications WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row || strtotime((string)$row['expires_at']) < time()) {
        return false;
    }
    app_db()->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([(int)$row['user_id']]);
    app_db()->prepare('DELETE FROM email_verifications WHERE id = ?')->execute([(int)$row['id']]);
    return true;
}

/* ── Password reset ── */
function app_create_password_reset(string $emailOrUsername): ?string
{
    $stmt = app_db()->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([strtolower(trim($emailOrUsername)), trim($emailOrUsername)]);
    $userId = $stmt->fetchColumn();
    if (!$userId) {
        return null; // caller should show a generic "if that account exists..." message either way
    }

    $token = bin2hex(random_bytes(24));
    $insert = app_db()->prepare('INSERT INTO password_resets(user_id, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, ?)');
    $insert->execute([(int)$userId, $token, gmdate('c', time() + 3600), gmdate('c')]);
    return $token;
}

function app_get_password_reset_user(string $token): ?int
{
    $stmt = app_db()->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row || strtotime((string)$row['expires_at']) < time()) {
        return null;
    }
    return (int)$row['user_id'];
}

function app_consume_password_reset(string $token, string $newPassword): array
{
    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters'];
    }
    $userId = app_get_password_reset_user($token);
    if (!$userId) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired'];
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    app_db()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
        ->execute([$hash, gmdate('c'), $userId]);
    app_db()->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')->execute([$token]);

    // Resetting the password invalidates every existing session/remember
    // token — if someone else had access to the account, this locks them out.
    app_revoke_all_sessions($userId);

    return ['ok' => true, 'user_id' => $userId];
}
