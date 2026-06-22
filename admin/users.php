<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($uid > 0) {
        if ($action === 'ban') {
            app_db()->prepare('UPDATE users SET is_banned = 1, updated_at = ? WHERE id = ?')->execute([gmdate('c'), $uid]);
            app_audit_log('user_ban', 'user', (string)$uid);
        } elseif ($action === 'unban') {
            app_db()->prepare('UPDATE users SET is_banned = 0, updated_at = ? WHERE id = ?')->execute([gmdate('c'), $uid]);
            app_audit_log('user_unban', 'user', (string)$uid);
        } elseif ($action === 'delete') {
            app_db()->prepare('DELETE FROM users WHERE id = ? AND role != "admin"')->execute([$uid]);
            app_audit_log('user_delete', 'user', (string)$uid);
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(username LIKE ? OR email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($statusFilter === 'banned') { $where[] = 'is_banned = 1'; }
elseif ($statusFilter === 'active') { $where[] = 'is_banned = 0'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = app_db()->prepare("SELECT COUNT(*) FROM users $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, (int)ceil($total / $perPage));

$stmt = app_db()->prepare("SELECT * FROM users $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$stmt->execute($params);
$users = $stmt->fetchAll();

// Episode-watched counts + last-active, batched in two queries rather than N+1.
$ids = array_map(fn($u) => (int)$u['id'], $users);
$epCounts = [];
$lastActive = [];
if ($ids) {
    $in = implode(',', $ids);
    foreach (app_db()->query("SELECT user_id, COUNT(*) AS c FROM watch_history WHERE user_id IN ($in) GROUP BY user_id") as $r) {
        $epCounts[(int)$r['user_id']] = (int)$r['c'];
    }
    foreach (app_db()->query("SELECT user_id, MAX(last_active) AS la FROM user_sessions WHERE user_id IN ($in) GROUP BY user_id") as $r) {
        $lastActive[(int)$r['user_id']] = $r['la'];
    }
}

$adminPageTitle = 'Users';
$adminActive = $statusFilter === 'banned' ? 'bans' : 'users';
include __DIR__ . '/_layout_top.php';
?>

<div class="ak-admin-panel">
  <form method="get" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
    <input class="ak-admin-input" name="q" placeholder="Search username or email…" value="<?=app_e($q)?>" style="flex:1;min-width:200px;">
    <select class="ak-admin-input" name="status">
      <option value="" <?=$statusFilter===''?'selected':''?>>All statuses</option>
      <option value="active" <?=$statusFilter==='active'?'selected':''?>>Active</option>
      <option value="banned" <?=$statusFilter==='banned'?'selected':''?>>Banned</option>
    </select>
    <button class="ak-abtn primary" type="submit"><i class="fas fa-search"></i> Filter</button>
  </form>

  <table class="ak-admin-table">
    <tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Last Active</th><th>Episodes</th><th></th></tr>
    <?php foreach ($users as $u):
      $uid = (int)$u['id'];
      $isBanned = (int)$u['is_banned'] === 1;
    ?>
    <tr>
      <td><a href="<?=$websiteUrl?>/admin/user-view.php?id=<?=$uid?>" class="ak-row-link"><?=app_e($u['username'])?></a></td>
      <td><?=app_e($u['email'])?></td>
      <td><span class="ak-badge role-<?=$u['role']==='admin'?'admin':'user'?>"><?=app_e($u['role'])?></span></td>
      <td><span class="ak-badge status-<?=$isBanned?'banned':'active'?>"><?=$isBanned?'Banned':'Active'?></span></td>
      <td><?=app_e(date('M j, Y', strtotime($u['created_at'])))?></td>
      <td><?=isset($lastActive[$uid]) ? app_e(date('M j, H:i', strtotime($lastActive[$uid]))) : '—'?></td>
      <td><?=$epCounts[$uid] ?? 0?></td>
      <td>
        <?php if ($u['role'] !== 'admin'): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
          <input type="hidden" name="user_id" value="<?=$uid?>">
          <button class="ak-abtn sm <?=$isBanned?'':'warn'?>" name="action" value="<?=$isBanned?'unban':'ban'?>"><?=$isBanned?'Unban':'Ban'?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="8" class="ak-admin-empty">No users match this filter.</td></tr><?php endif; ?>
  </table>

  <?php if ($lastPage > 1): ?>
  <div style="display:flex;gap:6px;margin-top:14px;">
    <?php for ($p = 1; $p <= $lastPage; $p++): ?>
    <a class="ak-abtn sm <?=$p===$page?'primary':''?>" href="?<?=http_build_query(array_merge($_GET, ['page'=>$p]))?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
