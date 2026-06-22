<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'feature') {
        app_review_set_featured($id, true);
        app_audit_log('review_feature', 'review', (string)$id);
    } elseif ($action === 'unfeature') {
        app_review_set_featured($id, false);
        app_audit_log('review_unfeature', 'review', (string)$id);
    } elseif ($action === 'hide') {
        app_review_set_hidden($id, true);
        app_audit_log('review_hide', 'review', (string)$id);
    } elseif ($action === 'unhide') {
        app_review_set_hidden($id, false);
        app_audit_log('review_unhide', 'review', (string)$id);
    } elseif ($action === 'delete') {
        app_review_delete($id, (int)$admin['id'], true);
        app_audit_log('review_delete', 'review', (string)$id);
    }
}

$reviews = app_review_recent_for_moderation(80);

$adminPageTitle = 'Reviews';
$adminActive = 'reviews';
include __DIR__ . '/_layout_top.php';
?>

<div class="ak-admin-panel">
  <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">Review-specific reports (spoiler/spam/abuse flags) land in the shared <a href="<?=$websiteUrl?>/admin/comments.php?tab=reports" style="color:var(--accent);">Reports Queue</a>.</p>
  <?php if ($reviews): ?>
  <table class="ak-admin-table">
    <tr><th>User</th><th>Anime</th><th>Rating</th><th>Review</th><th>Helpful</th><th>Reports</th><th>Posted</th><th></th></tr>
    <?php foreach ($reviews as $r): ?>
    <tr>
      <td><?=app_e($r['username'])?></td>
      <td><?=app_e(legacy_unslug((string)$r['anime_id']))?></td>
      <td><strong style="color:var(--accent);"><?=(int)$r['rating']?>/10</strong></td>
      <td style="max-width:300px;<?=$r['is_hidden']?'opacity:.5;text-decoration:line-through;':''?>">
        <?php if ($r['is_featured']): ?><span class="ak-badge status-active">FEATURED</span><?php endif; ?>
        <?php if ($r['has_spoilers']): ?><span class="ak-badge status-suspended">SPOILER</span><?php endif; ?>
        <?=app_e(mb_substr($r['body'], 0, 140))?>
      </td>
      <td><?=(int)app_db()->query('SELECT COUNT(*) FROM review_helpful_votes WHERE review_id = ' . (int)$r['id'])->fetchColumn()?></td>
      <td><?=$r['report_count'] > 0 ? '<span style="color:#ef4444;font-weight:700;">'.(int)$r['report_count'].'</span>' : '0'?></td>
      <td><?=app_e(date('M j, H:i', strtotime($r['created_at'])))?></td>
      <td style="display:flex;gap:4px;">
        <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="<?=$r['is_featured']?'unfeature':'feature'?>"><button class="ak-abtn sm" title="Feature"><i class="fas fa-star"></i></button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="<?=$r['is_hidden']?'unhide':'hide'?>"><button class="ak-abtn warn sm" title="Hide"><i class="fas fa-eye-slash"></i></button></form>
        <form method="post" onsubmit="return confirm('Delete this review permanently?');"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="delete"><button class="ak-abtn danger sm" title="Delete"><i class="fas fa-trash"></i></button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><div class="ak-admin-empty">No reviews yet.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
