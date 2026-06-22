<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'pin') {
        app_comment_set_pinned((int)$_POST['id'], true);
        app_audit_log('comment_pin', 'comment', (string)$_POST['id']);
    } elseif ($action === 'unpin') {
        app_comment_set_pinned((int)$_POST['id'], false);
        app_audit_log('comment_unpin', 'comment', (string)$_POST['id']);
    } elseif ($action === 'hide') {
        app_comment_set_hidden((int)$_POST['id'], true);
        app_audit_log('comment_hide', 'comment', (string)$_POST['id']);
    } elseif ($action === 'unhide') {
        app_comment_set_hidden((int)$_POST['id'], false);
        app_audit_log('comment_unhide', 'comment', (string)$_POST['id']);
    } elseif ($action === 'delete') {
        app_comment_delete((int)$_POST['id'], (int)$admin['id'], true);
        app_audit_log('comment_delete', 'comment', (string)$_POST['id']);
    } elseif ($action === 'resolve_report') {
        app_report_resolve((int)$_POST['id'], (int)$admin['id'], 'resolved');
        app_audit_log('report_resolve', 'report', (string)$_POST['id']);
    } elseif ($action === 'dismiss_report') {
        app_report_resolve((int)$_POST['id'], (int)$admin['id'], 'dismissed');
        app_audit_log('report_dismiss', 'report', (string)$_POST['id']);
    }
}

$tab = ($_GET['tab'] ?? '') === 'reports' ? 'reports' : 'comments';
$comments = $tab === 'comments' ? app_comment_recent_for_moderation(80) : [];
$reports = $tab === 'reports' ? app_report_list('pending', 100) : [];

$adminPageTitle = 'Community Moderation';
$adminActive = $tab === 'reports' ? 'reports' : 'comments';
include __DIR__ . '/_layout_top.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;">
  <a class="ak-abtn <?=$tab==='comments'?'primary':''?>" href="?tab=comments"><i class="fas fa-comments"></i> Recent Comments</a>
  <a class="ak-abtn <?=$tab==='reports'?'primary':''?>" href="?tab=reports"><i class="fas fa-flag"></i> Reports Queue<?=$adminPendingReports>0?' ('.$adminPendingReports.')':''?></a>
</div>

<?php if ($tab === 'comments'): ?>
<div class="ak-admin-panel">
  <?php if ($comments): ?>
  <table class="ak-admin-table">
    <tr><th>User</th><th>Anime / Ep</th><th>Comment</th><th>Likes</th><th>Reports</th><th>Posted</th><th></th></tr>
    <?php foreach ($comments as $c): ?>
    <tr>
      <td><?=app_e($c['username'])?></td>
      <td><?=app_e(legacy_unslug((string)$c['anime_id']))?><?=$c['episode'] !== null ? ' EP'.(int)$c['episode'] : ''?></td>
      <td style="max-width:320px;<?=$c['is_hidden']?'opacity:.5;text-decoration:line-through;':''?>">
        <?php if ($c['is_spoiler']): ?><span class="ak-badge status-suspended">SPOILER</span><?php endif; ?>
        <?php if ($c['is_pinned']): ?><span class="ak-badge status-active">PINNED</span><?php endif; ?>
        <?=app_e(mb_substr($c['body'], 0, 140))?>
      </td>
      <td><?=(int)app_db()->query('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ' . (int)$c['id'])->fetchColumn()?></td>
      <td><?=$c['report_count'] > 0 ? '<span style="color:#ef4444;font-weight:700;">'.(int)$c['report_count'].'</span>' : '0'?></td>
      <td><?=app_e(date('M j, H:i', strtotime($c['created_at'])))?></td>
      <td style="display:flex;gap:4px;">
        <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><input type="hidden" name="action" value="<?=$c['is_pinned']?'unpin':'pin'?>"><button class="ak-abtn sm" title="Pin"><i class="fas fa-thumbtack"></i></button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><input type="hidden" name="action" value="<?=$c['is_hidden']?'unhide':'hide'?>"><button class="ak-abtn warn sm" title="Hide"><i class="fas fa-eye-slash"></i></button></form>
        <form method="post" onsubmit="return confirm('Delete this comment permanently?');"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><input type="hidden" name="action" value="delete"><button class="ak-abtn danger sm" title="Delete"><i class="fas fa-trash"></i></button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><div class="ak-admin-empty">No comments yet.</div><?php endif; ?>
</div>

<?php else: ?>
<div class="ak-admin-panel">
  <?php if ($reports): ?>
  <table class="ak-admin-table">
    <tr><th>Type</th><th>Target</th><th>Reason</th><th>Details</th><th>Reporter</th><th>When</th><th></th></tr>
    <?php foreach ($reports as $r): ?>
    <tr>
      <td><?=app_e($r['target_type'])?></td>
      <td><?=app_e($r['target_id'])?></td>
      <td><span class="ak-badge status-suspended"><?=app_e(str_replace('_',' ',$r['reason']))?></span></td>
      <td style="max-width:240px;"><?=app_e($r['details'] ?: '—')?></td>
      <td><?=app_e($r['reporter_username'])?></td>
      <td><?=app_e(date('M j, H:i', strtotime($r['created_at'])))?></td>
      <td style="display:flex;gap:4px;">
        <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="resolve_report"><button class="ak-abtn sm primary" title="Resolved"><i class="fas fa-check"></i></button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="dismiss_report"><button class="ak-abtn sm" title="Dismiss"><i class="fas fa-xmark"></i></button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><div class="ak-admin-empty">No pending reports.</div><?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
