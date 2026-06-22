<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'recheck') {
        app_check_stream_health();
        app_audit_log('stream_health_recheck', 'system', null);
        $msg = 'Providers rechecked.';
    } elseif ($action === 'reorder') {
        $order = array_filter(explode(',', (string)($_POST['order'] ?? '')));
        if ($order) {
            app_stream_provider_reorder($order);
            app_audit_log('stream_provider_reorder', 'system', null, ['order' => $order]);
            $msg = 'Failover order updated.';
        }
    } elseif ($action === 'toggle_enabled') {
        $key = (string)($_POST['provider_key'] ?? '');
        $enabled = !empty($_POST['enabled']);
        app_stream_provider_set_enabled($key, $enabled);
        app_audit_log($enabled ? 'stream_provider_enable' : 'stream_provider_disable', 'provider', $key);
        $msg = ($enabled ? 'Enabled ' : 'Disabled ') . $key . '.';
    }
}

$health = app_stream_health_list();
$byProvider = [];
foreach ($health as $h) { $byProvider[$h['provider']] = $h; }

$providers = app_stream_provider_list();

$healthyCount = 0; $degradedCount = 0; $offlineCount = 0; $lastCheck = null;
foreach ($providers as $p) {
    $h = $byProvider[$p['provider_key']] ?? null;
    if (!$h) continue;
    if ($h['status'] === 'healthy') $healthyCount++;
    elseif ($h['status'] === 'degraded') $degradedCount++;
    else $offlineCount++;
    if ($lastCheck === null || strtotime($h['checked_at']) > strtotime($lastCheck)) $lastCheck = $h['checked_at'];
}

$adminPageTitle = 'Stream Provider Health';
$adminActive = 'streams';
include __DIR__ . '/_layout_top.php';
?>

<?php if ($msg): ?><div class="ak-admin-panel" style="border-color:rgba(34,197,94,.3);color:#86efac;"><?=app_e($msg)?></div><?php endif; ?>

<div class="ak-kpi-grid">
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-circle" style="color:#22c55e;font-size:8px"></i> Healthy Providers</div><div class="ak-kpi-value"><?=$healthyCount?></div></div>
  <div class="ak-kpi-card <?=$degradedCount>0?'warn':''?>"><div class="ak-kpi-label"><i class="fas fa-circle" style="color:#f59e0b;font-size:8px"></i> Degraded Providers</div><div class="ak-kpi-value"><?=$degradedCount?></div></div>
  <div class="ak-kpi-card <?=$offlineCount>0?'danger':''?>"><div class="ak-kpi-label"><i class="fas fa-circle" style="color:#ef4444;font-size:8px"></i> Offline Providers</div><div class="ak-kpi-value"><?=$offlineCount?></div></div>
  <div class="ak-kpi-card">
    <div class="ak-kpi-label">Last Check
      <i class="fas fa-circle-info" title="Pings each provider's site directly (HTTP HEAD, ~8s timeout) using a known-good title. Confirms the domain responds — not that every episode plays, since we can't render their embedded player from PHP." style="cursor:help;color:var(--text-muted);"></i>
    </div>
    <div class="ak-kpi-value" style="font-size:18px;"><?=$lastCheck ? app_e(date('H:i', strtotime($lastCheck))) : '—'?></div>
    <form method="post" style="margin-top:6px;"><input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>"><input type="hidden" name="action" value="recheck"><button class="ak-abtn sm primary"><i class="fas fa-rotate"></i> Recheck Now</button></form>
  </div>
</div>

<div class="ak-kpi-grid">
  <?php foreach ($providers as $p):
    $key = $p['provider_key'];
    $h = $byProvider[$key] ?? null;
    $status = $h['status'] ?? 'unknown';
    $stats = app_stream_health_stats($key);
    $enabled = (int)$p['enabled'] === 1;
  ?>
  <div class="ak-admin-panel" style="margin-bottom:0;<?=$enabled?'':'opacity:.55;'?>">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <strong style="color:var(--text-main);font-size:13.5px;"><?=app_e($p['name'])?></strong>
      <span class="ak-dot <?=app_e($status)?>" title="<?=app_e($status)?>"></span>
    </div>
    <div style="font-size:12px;color:var(--text-secondary);text-transform:capitalize;margin-bottom:8px;"><?=app_e($status)?><?=$enabled?'':' · disabled'?></div>
    <div style="font-size:11.5px;color:var(--text-muted);line-height:1.7;">
      Latency: <?=$h ? (int)$h['response_ms'].'ms' : '—'?><br>
      Uptime (7d): <?=$stats['uptime_pct_7d'] !== null ? $stats['uptime_pct_7d'].'%' : '—'?><br>
      Failures Today: <?=$stats['failures_today']?><br>
      Success Rate Today: <?=$stats['success_rate_today'] !== null ? $stats['success_rate_today'].'%' : '—'?><br>
      Last Success: <?=$stats['last_success'] ? app_e(date('M j, H:i', strtotime($stats['last_success']))) : 'never'?>
      <?php if ($h && $h['last_error']): ?><br><span style="color:#ef4444;"><?=app_e($h['last_error'])?></span><?php endif; ?>
    </div>
    <form method="post" style="margin-top:10px;">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
      <input type="hidden" name="action" value="toggle_enabled">
      <input type="hidden" name="provider_key" value="<?=app_e($key)?>">
      <input type="hidden" name="enabled" value="<?=$enabled?'0':'1'?>">
      <button class="ak-abtn sm <?=$enabled?'warn':''?>"><i class="fas fa-power-off"></i> <?=$enabled?'Disable':'Enable'?></button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<div class="ak-admin-panel">
  <h3><i class="fas fa-list-ol"></i> Failover Order</h3>
  <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">
    Drag to reorder. The watch page tries enabled providers in this order — persisted in the database, no code change needed to reorder or disable a provider.
  </p>
  <ul id="akFailoverList" style="list-style:none;display:flex;flex-direction:column;gap:6px;max-width:380px;">
    <?php foreach ($providers as $p): ?>
    <li draggable="true" data-key="<?=app_e($p['provider_key'])?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg-secondary);border:1px solid rgba(255,255,255,.08);border-radius:8px;cursor:grab;<?=(int)$p['enabled']===1?'':'opacity:.5;'?>">
      <i class="fas fa-grip-vertical" style="color:var(--text-muted);"></i>
      <span style="font-size:13px;color:var(--text-main);"><?=app_e($p['name'])?></span>
      <?php if ((int)$p['enabled'] !== 1): ?><span class="ak-badge status-banned" style="margin-left:auto;">Disabled</span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <form method="post" id="akFailoverForm" style="margin-top:12px;">
    <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
    <input type="hidden" name="action" value="reorder">
    <input type="hidden" name="order" id="akFailoverOrder">
    <button class="ak-abtn primary sm" type="submit"><i class="fas fa-check"></i> Save Order</button>
  </form>
</div>

<script>
(function(){
  var list = document.getElementById('akFailoverList');
  if (!list) return;
  var dragEl = null;
  list.addEventListener('dragstart', function(e){ dragEl = e.target.closest('li'); e.target.style.opacity = '0.4'; });
  list.addEventListener('dragend', function(e){ if (dragEl) dragEl.style.opacity = ''; dragEl = null; });
  list.addEventListener('dragover', function(e){
    e.preventDefault();
    var over = e.target.closest('li');
    if (!over || over === dragEl) return;
    var rect = over.getBoundingClientRect();
    var before = (e.clientY - rect.top) < rect.height / 2;
    list.insertBefore(dragEl, before ? over : over.nextSibling);
  });
  document.getElementById('akFailoverForm').addEventListener('submit', function(){
    var keys = Array.prototype.map.call(list.querySelectorAll('li'), function(li){ return li.dataset.key; });
    document.getElementById('akFailoverOrder').value = keys.join(',');
  });
})();
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
