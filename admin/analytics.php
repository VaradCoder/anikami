<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

$tab = $_GET['tab'] ?? 'users';
$validTabs = ['users', 'anime', 'episodes', 'devices', 'search'];
if (!in_array($tab, $validTabs, true)) $tab = 'users';

$adminPageTitle = 'Analytics Center';
$adminActive = 'analytics';
include __DIR__ . '/_layout_top.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
  <a class="ak-abtn <?=$tab==='users'?'primary':''?>" href="?tab=users"><i class="fas fa-users"></i> Users</a>
  <a class="ak-abtn <?=$tab==='anime'?'primary':''?>" href="?tab=anime"><i class="fas fa-fire"></i> Anime</a>
  <a class="ak-abtn <?=$tab==='episodes'?'primary':''?>" href="?tab=episodes"><i class="fas fa-chart-line"></i> Episodes</a>
  <a class="ak-abtn <?=$tab==='devices'?'primary':''?>" href="?tab=devices"><i class="fas fa-mobile-screen"></i> Devices</a>
  <a class="ak-abtn <?=$tab==='search'?'primary':''?>" href="?tab=search"><i class="fas fa-magnifying-glass"></i> Search</a>
</div>

<?php if ($tab === 'users'): ?>
  <?php
  $active = app_analytics_active_users();
  $returning = app_analytics_returning_rate();
  $regTrend = app_analytics_registrations_trend(30);
  $maxReg = max(1, max($regTrend));
  ?>
  <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">DAU/WAU/MAU count distinct logged-in activity only — anonymous/logged-out visits aren't attributable to a user, so they're excluded rather than estimated.</p>
  <div class="ak-kpi-grid">
    <div class="ak-kpi-card"><div class="ak-kpi-label">Daily Active Users</div><div class="ak-kpi-value"><?=$active['dau']?></div></div>
    <div class="ak-kpi-card"><div class="ak-kpi-label">Weekly Active Users</div><div class="ak-kpi-value"><?=$active['wau']?></div></div>
    <div class="ak-kpi-card"><div class="ak-kpi-label">Monthly Active Users</div><div class="ak-kpi-value"><?=$active['mau']?></div></div>
    <div class="ak-kpi-card">
      <div class="ak-kpi-label">Returning Rate (7d)</div>
      <div class="ak-kpi-value"><?=$returning['rate'] !== null ? $returning['rate'].'%' : '—'?></div>
      <div class="ak-kpi-sub"><?=$returning['returning']?>/<?=$returning['active']?> active users joined &gt;7d ago</div>
    </div>
  </div>
  <div class="ak-admin-panel">
    <h3><i class="fas fa-chart-column"></i> Registrations — Last 30 Days</h3>
    <div class="ak-spark">
      <?php foreach ($regTrend as $day => $count): $h = max(3, round(($count / $maxReg) * 56)); ?>
      <div class="ak-spark-bar" style="height:<?=$h?>px" title="<?=app_e(date('M j', strtotime($day)))?>: <?=$count?>"></div>
      <?php endforeach; ?>
    </div>
  </div>

<?php elseif ($tab === 'anime'): ?>
  <?php
  $mostWatched = app_analytics_most_watched(10, 30);
  $mostFavorited = app_analytics_most_favorited(10);
  $highestRated = app_analytics_highest_rated(10, 1);
  ?>
  <div class="ak-admin-grid-2">
    <div class="ak-admin-panel">
      <h3><i class="fas fa-play"></i> Most Watched (episode views, 30d)</h3>
      <?php if ($mostWatched): ?>
      <table class="ak-admin-table"><?php foreach ($mostWatched as $i => $r): ?>
        <tr><td style="width:24px;color:var(--text-muted);"><?=$i+1?></td><td><?=app_e(legacy_unslug((string)$r['anime_id']))?></td><td style="text-align:right;width:60px;"><?=(int)$r['views']?></td></tr>
      <?php endforeach; ?></table>
      <?php else: ?><div class="ak-admin-empty">No episode views recorded yet.</div><?php endif; ?>
    </div>
    <div class="ak-admin-panel">
      <h3><i class="fas fa-heart"></i> Most Favorited / Listed</h3>
      <?php if ($mostFavorited): ?>
      <table class="ak-admin-table"><?php foreach ($mostFavorited as $i => $r): ?>
        <tr><td style="width:24px;color:var(--text-muted);"><?=$i+1?></td><td><?=app_e(legacy_unslug((string)$r['anime_id']))?></td><td style="text-align:right;width:60px;"><?=(int)$r['list_count']?></td></tr>
      <?php endforeach; ?></table>
      <?php else: ?><div class="ak-admin-empty">No anime lists entries yet.</div><?php endif; ?>
    </div>
  </div>
  <div class="ak-admin-panel">
    <h3><i class="fas fa-star"></i> Highest Rated (min. 1 review)</h3>
    <?php if ($highestRated): ?>
    <table class="ak-admin-table"><tr><th>Anime</th><th>Avg Rating</th><th>Reviews</th></tr><?php foreach ($highestRated as $r): ?>
      <tr><td><?=app_e(legacy_unslug((string)$r['anime_id']))?></td><td><strong style="color:var(--accent);"><?=$r['avg_rating']?>/10</strong></td><td><?=(int)$r['review_count']?></td></tr>
    <?php endforeach; ?></table>
    <?php else: ?><div class="ak-admin-empty">No reviews yet.</div><?php endif; ?>
  </div>

<?php elseif ($tab === 'episodes'): ?>
  <?php
  $candidates = app_analytics_anime_with_history(30);
  $selected = (string)($_GET['anime'] ?? ($candidates[0]['anime_id'] ?? ''));
  $dropoff = $selected !== '' ? app_analytics_episode_dropoff($selected, 20) : [];
  ?>
  <div class="ak-admin-panel">
    <h3><i class="fas fa-chart-line"></i> Episode Completion / Drop-Off</h3>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">Distinct viewers per episode from real watch_history rows, normalized against Episode 1 = 100%. Only anime with recorded watch history appear below.</p>
    <?php if ($candidates): ?>
    <form method="get" style="margin-bottom:16px;">
      <input type="hidden" name="tab" value="episodes">
      <select class="ak-admin-input" name="anime" onchange="this.form.submit()">
        <?php foreach ($candidates as $c): ?>
        <option value="<?=app_e($c['anime_id'])?>" <?=$c['anime_id']===$selected?'selected':''?>><?=app_e(legacy_unslug((string)$c['anime_id']))?> (<?=(int)$c['rows_count']?> records)</option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if ($dropoff): ?>
    <table class="ak-admin-table">
      <tr><th>Episode</th><th>Viewers</th><th>Retention</th></tr>
      <?php foreach ($dropoff as $d): ?>
      <tr>
        <td>EP <?=(int)$d['episode']?></td>
        <td><?=$d['viewers']?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="flex:1;background:rgba(255,255,255,.06);border-radius:4px;height:8px;overflow:hidden;"><div style="height:100%;background:var(--accent);width:<?=$d['pct']?>%;"></div></div>
            <span style="font-size:11.5px;color:var(--text-muted);width:42px;"><?=$d['pct']?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php else: ?><div class="ak-admin-empty">No watch history recorded yet.</div><?php endif; ?>
  </div>

<?php elseif ($tab === 'devices'): ?>
  <?php $devices = app_analytics_device_breakdown(30); $totalDevEvents = array_sum(array_column($devices, 'c')); ?>
  <div class="ak-admin-panel">
    <h3><i class="fas fa-mobile-screen"></i> Device / OS Breakdown (30d)</h3>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">Parsed from the User-Agent header on tracked events. Only events recorded after this column was added are included — older history shows as untracked.</p>
    <?php if ($devices): ?>
    <table class="ak-admin-table"><tr><th>Device</th><th>Events</th><th>Share</th></tr>
      <?php foreach ($devices as $d): $pct = $totalDevEvents > 0 ? round(((int)$d['c'] / $totalDevEvents) * 100, 1) : 0; ?>
      <tr>
        <td><?=app_e($d['device_os'])?></td>
        <td><?=(int)$d['c']?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="flex:1;background:rgba(255,255,255,.06);border-radius:4px;height:8px;overflow:hidden;max-width:160px;"><div style="height:100%;background:var(--accent);width:<?=$pct?>%;"></div></div>
            <span style="font-size:11.5px;color:var(--text-muted);"><?=$pct?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><div class="ak-admin-empty">No device data recorded yet.</div><?php endif; ?>
  </div>

<?php elseif ($tab === 'search'): ?>
  <?php
  $topSearches = app_analytics_top_searches(15, 30);
  $failedSearches = app_analytics_failed_searches(15, 30);
  $trending = app_analytics_top_searches(15, 7);
  ?>
  <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Tracked from the full search-results page only (not the header's live-typeahead dropdown), so this reflects deliberate searches, not partial keystrokes.</p>
  <div class="ak-admin-grid-2">
    <div class="ak-admin-panel">
      <h3><i class="fas fa-magnifying-glass"></i> Most Searched (30d)</h3>
      <?php if ($topSearches): ?>
      <table class="ak-admin-table"><?php foreach ($topSearches as $s): ?><tr><td><?=app_e($s['query'])?></td><td style="text-align:right;width:60px;"><?=(int)$s['searches']?></td></tr><?php endforeach; ?></table>
      <?php else: ?><div class="ak-admin-empty">No searches recorded yet.</div><?php endif; ?>
    </div>
    <div class="ak-admin-panel">
      <h3><i class="fas fa-arrow-trend-up"></i> Trending Queries (7d)</h3>
      <?php if ($trending): ?>
      <table class="ak-admin-table"><?php foreach ($trending as $s): ?><tr><td><?=app_e($s['query'])?></td><td style="text-align:right;width:60px;"><?=(int)$s['searches']?></td></tr><?php endforeach; ?></table>
      <?php else: ?><div class="ak-admin-empty">No searches in the last 7 days.</div><?php endif; ?>
    </div>
  </div>
  <div class="ak-admin-panel">
    <h3><i class="fas fa-triangle-exclamation"></i> Failed Searches — Zero Results (30d)</h3>
    <?php if ($failedSearches): ?>
    <table class="ak-admin-table"><?php foreach ($failedSearches as $s): ?><tr><td><?=app_e($s['query'])?></td><td style="text-align:right;width:60px;"><?=(int)$s['searches']?></td></tr><?php endforeach; ?></table>
    <?php else: ?><div class="ak-admin-empty">No zero-result searches recorded.</div><?php endif; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
