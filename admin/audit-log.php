<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

$fAdmin = (string)($_GET['admin'] ?? '');
$fAction = (string)($_GET['action_filter'] ?? '');
$fFrom = (string)($_GET['from'] ?? '');
$fTo = (string)($_GET['to'] ?? '');

$logs = app_audit_list_filtered($fAdmin, $fAction, $fFrom, $fTo, 300);
$admins = app_audit_distinct_admins();
$actions = app_audit_distinct_actions();

$adminPageTitle = 'Audit Logs';
$adminActive = 'audit';
include __DIR__ . '/_layout_top.php';
?>

<div class="ak-admin-panel">
  <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">Append-only record of admin actions — actor, action, target, timestamp, and IP. Rows are never deleted or edited, even by this panel.</p>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
    <select class="ak-admin-input" name="admin">
      <option value="">All admins</option>
      <?php foreach ($admins as $a): ?><option value="<?=app_e($a)?>" <?=$fAdmin===$a?'selected':''?>><?=app_e($a)?></option><?php endforeach; ?>
    </select>
    <select class="ak-admin-input" name="action_filter">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?><option value="<?=app_e($a)?>" <?=$fAction===$a?'selected':''?>><?=app_e($a)?></option><?php endforeach; ?>
    </select>
    <input class="ak-admin-input" type="date" name="from" value="<?=app_e($fFrom)?>" title="From date">
    <input class="ak-admin-input" type="date" name="to" value="<?=app_e($fTo)?>" title="To date">
    <button class="ak-abtn primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($fAdmin || $fAction || $fFrom || $fTo): ?><a class="ak-abtn" href="?"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
  </form>
</div>

<div class="ak-admin-panel">
  <?php if ($logs): ?>
  <table class="ak-admin-table">
    <tr><th>Actor</th><th>Action</th><th>Target</th><th>Timestamp</th><th>IP Address</th><th>Detail</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><strong style="color:var(--text-main);"><?=app_e($l['admin_username'] ?? '—')?></strong></td>
      <td><span class="ak-badge role-user"><?=app_e(str_replace('_', ' ', $l['action']))?></span></td>
      <td><?=app_e(trim(($l['target_type'] ?? '') . ' ' . ($l['target_id'] ?? '')))?></td>
      <td><?=app_e(date('j M Y, H:i:s', strtotime($l['created_at'])))?></td>
      <td><?=app_e($l['ip_address'] ?? '—')?></td>
      <td style="font-size:11px;color:var(--text-muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;"><?=app_e($l['meta_json'] ?? '')?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="ak-admin-empty">No admin actions match this filter.</div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
