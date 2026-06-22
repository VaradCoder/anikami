<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

$uid = (int)($_GET['id'] ?? 0);
$stmt = app_db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) {
    app_redirect('/admin/users.php');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'ban') {
        app_db()->prepare('UPDATE users SET is_banned = 1, updated_at = ? WHERE id = ?')->execute([gmdate('c'), $uid]);
        app_audit_log('user_ban', 'user', (string)$uid);
        $msg = 'User banned.';
    } elseif ($action === 'unban') {
        app_db()->prepare('UPDATE users SET is_banned = 0, updated_at = ? WHERE id = ?')->execute([gmdate('c'), $uid]);
        app_audit_log('user_unban', 'user', (string)$uid);
        $msg = 'User unbanned.';
    } elseif ($action === 'suspend') {
        $days = max(1, (int)($_POST['days'] ?? 7));
        $until = gmdate('c', time() + $days * 86400);
        app_db()->prepare('UPDATE users SET suspended_until = ?, updated_at = ? WHERE id = ?')->execute([$until, gmdate('c'), $uid]);
        app_audit_log('user_suspend', 'user', (string)$uid, ['days' => $days]);
        $msg = "User suspended for $days days.";
    } elseif ($action === 'unsuspend') {
        app_db()->prepare('UPDATE users SET suspended_until = NULL, updated_at = ? WHERE id = ?')->execute([gmdate('c'), $uid]);
        app_audit_log('user_unsuspend', 'user', (string)$uid);
        $msg = 'Suspension lifted.';
    } elseif ($action === 'verify') {
        app_db()->prepare('UPDATE users SET email_verified = 1, updated_at = ? WHERE id = ?')->execute([gmdate('c'), $uid]);
        app_audit_log('user_verify', 'user', (string)$uid);
        $msg = 'Email marked verified.';
    } elseif ($action === 'reset_password') {
        $newPass = bin2hex(random_bytes(6));
        app_db()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')->execute([password_hash($newPass, PASSWORD_BCRYPT), gmdate('c'), $uid]);
        app_revoke_all_sessions($uid);
        app_audit_log('user_reset_password', 'user', (string)$uid);
        $msg = "Password reset. Temporary password: <code>" . app_e($newPass) . "</code> — share this with the user directly, it will not be shown again.";
    } elseif ($action === 'logout_all') {
        app_revoke_all_sessions($uid);
        app_audit_log('user_logout_all', 'user', (string)$uid);
        $msg = 'All sessions revoked.';
    } elseif ($action === 'delete' && $user['role'] !== 'admin') {
        app_db()->prepare('DELETE FROM users WHERE id = ? AND role != "admin"')->execute([$uid]);
        app_audit_log('user_delete', 'user', (string)$uid);
        app_redirect('/admin/users.php');
    }
    if ($action !== 'reset_password') {
        $stmt = app_db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
    }
}

$history = app_watch_history_list($uid, 30);
$continueList = app_continue_watching_list($uid, 10);
$lists = app_get_user_lists_grouped($uid);
$sessions = app_list_user_sessions($uid);
$stmt = app_db()->prepare('SELECT * FROM login_attempts WHERE email = ? ORDER BY created_at DESC LIMIT 20');
$stmt->execute([$user['email']]);
$securityEvents = $stmt->fetchAll();

$isBanned = (int)$user['is_banned'] === 1;
$isSuspended = !empty($user['suspended_until']) && strtotime($user['suspended_until']) > time();

// Total watch time is estimated the same honest way as the dashboard KPI
// (24min/episode average) — watch_history doesn't track actual duration.
$stmt = app_db()->prepare('SELECT COUNT(*) FROM watch_history WHERE user_id = ?');
$stmt->execute([$uid]);
$totalEpisodesWatched = (int)$stmt->fetchColumn();
$estWatchHours = round($totalEpisodesWatched * 24 / 60, 1);

$stmt = app_db()->prepare('SELECT anime_id, COUNT(*) AS c FROM watch_history WHERE user_id = ? GROUP BY anime_id ORDER BY c DESC LIMIT 3');
$stmt->execute([$uid]);
$favoriteAnime = $stmt->fetchAll();

// Ban/suspend history — derived from the audit log rather than a separate
// table, since every ban/unban/suspend action already lands there.
$stmt = app_db()->prepare(
    "SELECT * FROM audit_logs WHERE target_type = 'user' AND target_id = ? AND action IN ('user_ban','user_unban','user_suspend','user_unsuspend') ORDER BY created_at DESC LIMIT 20"
);
$stmt->execute([(string)$uid]);
$banHistory = $stmt->fetchAll();

// Unified activity timeline — merges watch history, comments, reviews, and
// ban/moderation events into one real chronological feed for this user.
$timeline = [];
foreach (array_slice($history, 0, 10) as $h) {
    $timeline[] = ['ts' => $h['watched_at'], 'icon' => 'fa-play', 'text' => 'Watched ' . legacy_unslug((string)$h['anime_id']) . ' EP' . (int)$h['episode']];
}
$stmt = app_db()->prepare('SELECT anime_id, episode, created_at FROM comments WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $c) {
    $timeline[] = ['ts' => $c['created_at'], 'icon' => 'fa-comment', 'text' => 'Commented on ' . legacy_unslug((string)$c['anime_id']) . ($c['episode'] !== null ? ' EP' . (int)$c['episode'] : '')];
}
$stmt = app_db()->prepare('SELECT anime_id, rating, created_at FROM reviews WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $r) {
    $timeline[] = ['ts' => $r['created_at'], 'icon' => 'fa-star', 'text' => 'Reviewed ' . legacy_unslug((string)$r['anime_id']) . ' (' . (int)$r['rating'] . '/10)'];
}
foreach ($banHistory as $b) {
    $timeline[] = ['ts' => $b['created_at'], 'icon' => 'fa-gavel', 'text' => 'Moderation: ' . str_replace('_', ' ', $b['action'])];
}
usort($timeline, fn($a, $b) => strcmp($b['ts'], $a['ts']));
$timeline = array_slice($timeline, 0, 20);

$adminPageTitle = 'User: ' . $user['username'];
$adminActive = 'users';
include __DIR__ . '/_layout_top.php';
?>

<?php if ($msg): ?><div class="ak-admin-panel" style="border-color:rgba(34,197,94,.3);color:#86efac;"><?=$msg?></div><?php endif; ?>

<div class="ak-admin-panel">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#8b0000);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fff;">
      <?=mb_strtoupper(mb_substr($user['username'], 0, 1))?>
    </div>
    <div style="flex:1;min-width:200px;">
      <div style="font-size:17px;font-weight:700;color:var(--text-main);"><?=app_e($user['username'])?>
        <span class="ak-badge role-<?=$user['role']==='admin'?'admin':'user'?>"><?=app_e($user['role'])?></span>
        <?php if ($isBanned): ?><span class="ak-badge status-banned">Banned</span>
        <?php elseif ($isSuspended): ?><span class="ak-badge status-suspended">Suspended until <?=app_e(date('M j', strtotime($user['suspended_until'])))?></span>
        <?php else: ?><span class="ak-badge status-active">Active</span><?php endif; ?>
        <?php if (!empty($user['email_verified'])): ?><span class="ak-badge status-active"><i class="fas fa-check"></i> Verified</span><?php endif; ?>
      </div>
      <div style="font-size:12.5px;color:var(--text-muted);">
        <?=app_e($user['email'])?> · Joined <?=app_e(date('M j, Y', strtotime($user['created_at'])))?>
        · Last login <?=!empty($user['last_login']) ? app_e(date('M j, H:i', strtotime($user['last_login']))) : 'never'?>
      </div>
      <div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
        <?=$totalEpisodesWatched?> episodes watched · ~<?=$estWatchHours?>h watch time (est.)
        <?php if ($favoriteAnime): ?> · Favorite: <strong style="color:var(--text-main);"><?=app_e(legacy_unslug((string)$favoriteAnime[0]['anime_id']))?></strong> (<?=(int)$favoriteAnime[0]['c']?> eps)<?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($user['role'] !== 'admin'): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;border-top:1px solid rgba(255,255,255,.06);padding-top:16px;">
    <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="<?=$isBanned?'unban':'ban'?>"><button class="ak-abtn <?=$isBanned?'':'danger'?>"><i class="fas fa-ban"></i> <?=$isBanned?'Unban':'Ban'?></button></form>
    <?php if ($isSuspended): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="unsuspend"><button class="ak-abtn warn"><i class="fas fa-play"></i> Lift Suspension</button></form>
    <?php else: ?>
    <form method="post" style="display:flex;gap:6px;"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="suspend"><input class="ak-admin-input" name="days" type="number" value="7" style="width:70px;"><button class="ak-abtn warn"><i class="fas fa-pause"></i> Suspend (days)</button></form>
    <?php endif; ?>
    <?php if (empty($user['email_verified'])): ?>
    <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="verify"><button class="ak-abtn"><i class="fas fa-check"></i> Verify Email</button></form>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('Generate a new temporary password for this user?');"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="reset_password"><button class="ak-abtn"><i class="fas fa-key"></i> Reset Password</button></form>
    <form method="post" onsubmit="return confirm('Log this user out of every device?');"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="logout_all"><button class="ak-abtn"><i class="fas fa-right-from-bracket"></i> Logout All Sessions</button></form>
    <form method="post" onsubmit="return confirm('Permanently delete this user? This cannot be undone.');"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="delete"><button class="ak-abtn danger"><i class="fas fa-trash"></i> Delete User</button></form>
  </div>
  <?php endif; ?>
</div>

<div class="ak-admin-grid-2">
  <div class="ak-admin-panel">
    <h3><i class="fas fa-list-check"></i> Anime Lists</h3>
    <?php $any = false; foreach ($lists as $status => $rows): if (!$rows) continue; $any = true; ?>
      <div style="font-size:11.5px;color:var(--text-muted);margin:10px 0 4px;text-transform:uppercase;"><?=app_e(str_replace('_',' ',ucfirst($status)))?> (<?=count($rows)?>)</div>
      <?php foreach (array_slice($rows, 0, 5) as $r): ?>
        <div style="font-size:12.5px;color:var(--text-secondary);"><?=app_e(legacy_unslug((string)$r['anime_id']))?></div>
      <?php endforeach; ?>
    <?php endforeach; if (!$any): ?><div class="ak-admin-empty">No list entries.</div><?php endif; ?>
  </div>

  <div class="ak-admin-panel">
    <h3><i class="fas fa-shield-halved"></i> Security Events (by email)</h3>
    <?php if ($securityEvents): ?>
    <table class="ak-admin-table">
      <tr><th>IP</th><th>Result</th><th>When</th></tr>
      <?php foreach ($securityEvents as $e): ?>
      <tr><td><?=app_e($e['ip'])?></td><td><?=(int)$e['success']?'<span style="color:#22c55e">Success</span>':'<span style="color:#ef4444">Failed</span>'?></td><td><?=app_e(date('M j, H:i', strtotime($e['created_at'])))?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><div class="ak-admin-empty">No login attempts recorded.</div><?php endif; ?>
  </div>
</div>

<div class="ak-admin-grid-2">
  <div class="ak-admin-panel">
    <h3><i class="fas fa-laptop"></i> Active Sessions</h3>
    <?php if ($sessions): ?>
    <table class="ak-admin-table">
      <tr><th>Device</th><th>IP</th><th>Last Active</th></tr>
      <?php foreach ($sessions as $i => $s): ?>
      <tr><td><?=app_e($s['device_name'] ?? 'Unknown')?><?=$i===0?' <span class="ak-badge status-active" style="font-size:9px;">MOST RECENT</span>':''?></td><td><?=app_e($s['ip_address'] ?? '—')?></td><td><?=app_e(date('M j, H:i', strtotime($s['last_active'])))?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><div class="ak-admin-empty">No active sessions.</div><?php endif; ?>
  </div>

  <div class="ak-admin-panel">
    <h3><i class="fas fa-history"></i> Recent Watch History</h3>
    <?php if ($history): ?>
    <table class="ak-admin-table">
      <tr><th>Anime</th><th>Ep</th><th>Watched</th></tr>
      <?php foreach (array_slice($history, 0, 12) as $h): ?>
      <tr><td><?=app_e(legacy_unslug((string)$h['anime_id']))?></td><td><?=(int)$h['episode']?></td><td><?=app_e(date('M j, H:i', strtotime($h['watched_at'])))?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><div class="ak-admin-empty">No watch history.</div><?php endif; ?>
  </div>
</div>

<div class="ak-admin-grid-2">
  <div class="ak-admin-panel">
    <h3><i class="fas fa-gavel"></i> Ban / Suspension History</h3>
    <?php if ($banHistory): ?>
    <table class="ak-admin-table">
      <tr><th>Action</th><th>By</th><th>When</th></tr>
      <?php foreach ($banHistory as $b): ?>
      <tr><td><span class="ak-badge role-user"><?=app_e(str_replace('_',' ',$b['action']))?></span></td><td><?=app_e($b['admin_username'] ?? '—')?></td><td><?=app_e(date('M j, H:i', strtotime($b['created_at'])))?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><div class="ak-admin-empty">No moderation history.</div><?php endif; ?>
  </div>

  <div class="ak-admin-panel">
    <h3><i class="fas fa-timeline"></i> Activity Timeline</h3>
    <?php if ($timeline): ?>
    <ul class="ak-feed">
      <?php foreach ($timeline as $t): ?>
      <li><div class="ak-feed-icon"><i class="fas <?=app_e($t['icon'])?>"></i></div><div class="ak-feed-text"><?=app_e($t['text'])?></div><div class="ak-feed-time"><?=app_e(date('M j, H:i', strtotime($t['ts'])))?></div></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><div class="ak-admin-empty">No recorded activity.</div><?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
