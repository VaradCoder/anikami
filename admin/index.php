<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

$now = gmdate('c');
$dayAgo  = gmdate('c', time() - 86400);
$weekAgo = gmdate('c', time() - 7 * 86400);
$monthAgo = gmdate('c', time() - 30 * 86400);

$totalUsers   = (int)app_db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalViews   = (int)app_setting_get('site_views', '0');

$stmt = app_db()->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ?"); $stmt->execute([$dayAgo]); $newUsersToday = (int)$stmt->fetchColumn();
$stmt = app_db()->prepare("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_active >= ?"); $stmt->execute([gmdate('c', time() - 300)]); $onlineUsers = (int)$stmt->fetchColumn();
$activeSessions = (int)app_db()->query("SELECT COUNT(*) FROM user_sessions WHERE expires_at > '" . $now . "'")->fetchColumn();

$stmt = app_db()->prepare("SELECT COUNT(*) FROM watch_history WHERE watched_at >= ?"); $stmt->execute([$dayAgo]); $episodesToday = (int)$stmt->fetchColumn();
$stmt = app_db()->prepare("SELECT COUNT(*) FROM watch_history WHERE watched_at >= ?"); $stmt->execute([$monthAgo]); $episodesMonth = (int)$stmt->fetchColumn();
// No per-session duration is tracked (watch_history only marks an episode
// as watched once, not how long for) — these are honest estimates at a
// 24-minute average TV episode runtime, labelled as such in the UI.
$hoursToday = round($episodesToday * 24 / 60, 1);
$hoursMonth = round($episodesMonth * 24 / 60, 1);

$stmt = app_db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at >= ?"); $stmt->execute([$dayAgo]); $failedLoginsDay = (int)$stmt->fetchColumn();
$bannedUsers = (int)app_db()->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn();

$totalComments = (int)app_db()->query("SELECT COUNT(*) FROM comments WHERE is_hidden = 0")->fetchColumn();
$totalReviews = (int)app_db()->query("SELECT COUNT(*) FROM reviews WHERE is_hidden = 0")->fetchColumn();

$streamHealth = app_stream_health_list();
$healthyCount = count(array_filter($streamHealth, fn($r) => $r['status'] === 'healthy'));
$totalProviders = max(1, count($streamHealth) ?: 3);

$trendingWeek = app_trending_anime('week', 10);
$listsByStatus = app_db()->query('SELECT status, COUNT(*) AS c FROM user_lists GROUP BY status')->fetchAll();

// Registrations per day, last 14 days, for the sparkline.
$regsByDay = [];
for ($i = 13; $i >= 0; $i--) {
    $dayStart = gmdate('Y-m-d', time() - $i * 86400);
    $stmt = app_db()->prepare("SELECT COUNT(*) FROM users WHERE created_at LIKE ?");
    $stmt->execute([$dayStart . '%']);
    $regsByDay[$dayStart] = (int)$stmt->fetchColumn();
}
$maxReg = max(1, max($regsByDay));

$adminPageTitle = 'Dashboard';
$adminActive = 'dashboard';
include __DIR__ . '/_layout_top.php';
?>

<div class="ak-kpi-grid">
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-users"></i> Total Users</div><div class="ak-kpi-value"><?=$totalUsers?></div><div class="ak-kpi-sub up">+<?=$newUsersToday?> today</div></div>
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-circle" style="color:#22c55e;font-size:8px"></i> Online Now</div><div class="ak-kpi-value"><?=$onlineUsers?></div><div class="ak-kpi-sub">last 5 min, by session activity</div></div>
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-laptop"></i> Active Sessions</div><div class="ak-kpi-value"><?=$activeSessions?></div></div>
  <div class="ak-kpi-card <?=$failedLoginsDay > 20 ? 'danger' : ''?>"><div class="ak-kpi-label"><i class="fas fa-triangle-exclamation"></i> Failed Logins (24h)</div><div class="ak-kpi-value"><?=$failedLoginsDay?></div></div>

  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-play"></i> Episodes Watched Today</div><div class="ak-kpi-value"><?=$episodesToday?></div></div>
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-clock"></i> Watch Hours Today (est.)</div><div class="ak-kpi-value"><?=$hoursToday?></div><div class="ak-kpi-sub">~24min/ep estimate</div></div>
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-clock"></i> Watch Hours / Month (est.)</div><div class="ak-kpi-value"><?=$hoursMonth?></div></div>
  <div class="ak-kpi-card <?=$healthyCount < $totalProviders ? 'warn' : ''?>"><div class="ak-kpi-label"><i class="fas fa-tower-broadcast"></i> Stream Providers Healthy</div><div class="ak-kpi-value"><?=$healthyCount?>/<?=$totalProviders?></div><a href="<?=$websiteUrl?>/admin/streams.php" class="ak-kpi-sub" style="color:var(--accent);text-decoration:none;">View details →</a></div>
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-comments"></i> Comments</div><div class="ak-kpi-value"><?=$totalComments?></div><a href="<?=$websiteUrl?>/admin/comments.php" class="ak-kpi-sub" style="color:var(--accent);text-decoration:none;">Moderate →</a></div>
  <div class="ak-kpi-card"><div class="ak-kpi-label"><i class="fas fa-star-half-stroke"></i> Reviews</div><div class="ak-kpi-value"><?=$totalReviews?></div><a href="<?=$websiteUrl?>/admin/reviews.php" class="ak-kpi-sub" style="color:var(--accent);text-decoration:none;">Moderate →</a></div>
</div>

<div class="ak-admin-grid-2">

  <div class="ak-admin-panel">
    <h3><i class="fas fa-chart-column"></i> Registrations — Last 14 Days</h3>
    <div class="ak-spark">
      <?php foreach ($regsByDay as $day => $count):
        $h = max(3, round(($count / $maxReg) * 56));
      ?>
      <div class="ak-spark-bar" style="height:<?=$h?>px" title="<?=app_e(date('M j', strtotime($day)))?>: <?=$count?>"></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="ak-admin-panel">
    <h3><i class="fas fa-fire"></i> Most Watched Anime (7 days)</h3>
    <?php if ($trendingWeek): ?>
    <table class="ak-admin-table">
      <?php foreach (array_slice($trendingWeek, 0, 10) as $i => $r): ?>
      <tr><td style="width:24px;color:var(--text-muted);"><?=$i+1?></td><td><?=app_e(legacy_unslug((string)$r['anime_id']))?></td><td style="text-align:right;width:60px;"><?=(int)$r['score']?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="ak-admin-empty">No view data in the last 7 days yet.</div>
    <?php endif; ?>
  </div>

</div>

<div class="ak-admin-grid-2">

  <div class="ak-admin-panel">
    <h3><i class="fas fa-bolt"></i> Live Activity <span style="font-size:10px;color:var(--text-muted);font-weight:400;">(polls every 15s)</span></h3>
    <ul class="ak-feed" id="akActivityFeed"><li class="ak-admin-empty" style="border:none;">Loading…</li></ul>
  </div>

  <div class="ak-admin-panel">
    <h3><i class="fas fa-list-check"></i> Anime Lists Breakdown</h3>
    <?php if ($listsByStatus): ?>
    <table class="ak-admin-table">
      <?php foreach ($listsByStatus as $row): ?>
      <tr><td><?=app_e(str_replace('_', ' ', ucfirst($row['status'])))?></td><td style="text-align:right;"><strong><?=(int)$row['c']?></strong></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="ak-admin-empty">No list entries yet.</div>
    <?php endif; ?>
    <div style="margin-top:10px;font-size:11.5px;color:var(--text-muted);">Banned users: <strong style="color:var(--text-secondary);"><?=$bannedUsers?></strong> · Total site views: <strong style="color:var(--text-secondary);"><?=$totalViews?></strong></div>
  </div>

</div>

<script>
(function(){
  var feedEl = document.getElementById('akActivityFeed');
  function timeAgo(iso) {
    var sec = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
    if (sec < 60) return sec + 's ago';
    if (sec < 3600) return Math.floor(sec/60) + 'm ago';
    if (sec < 86400) return Math.floor(sec/3600) + 'h ago';
    return Math.floor(sec/86400) + 'd ago';
  }
  function render(items) {
    if (!items.length) { feedEl.innerHTML = '<li class="ak-admin-empty" style="border:none;">No recent activity.</li>'; return; }
    feedEl.innerHTML = items.map(function(it){
      return '<li><div class="ak-feed-icon"><i class="fas ' + it.icon + '"></i></div>' +
             '<div class="ak-feed-text">' + it.text + '</div>' +
             '<div class="ak-feed-time">' + timeAgo(it.ts) + '</div></li>';
    }).join('');
  }
  function poll() {
    fetch('<?=$websiteUrl?>/admin/activity-feed.php').then(function(r){return r.json();}).then(function(d){
      if (d.ok) render(d.items);
    }).catch(function(){});
  }
  poll();
  setInterval(poll, 15000);
})();
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
