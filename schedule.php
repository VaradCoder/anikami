<?php
require('./_config.php');

$day = isset($_GET['day']) ? (int)$_GET['day'] : 0;
if ($day < 0 || $day > 6) $day = 0;

$scheduleRows = legacy_anilist_schedule_day($day);

$dayTabs = [];
for ($i = 0; $i < 7; $i++) {
    $ts = strtotime(gmdate('Y-m-d', time() + $i * 86400));
    $dayTabs[] = [
        'offset' => $i,
        'label'  => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : gmdate('D', $ts)),
        'date'   => gmdate('M j', $ts),
    ];
}

$pageTitle     = 'Anime Airing Schedule — ' . $websiteTitle;
$pageDesc      = 'See what anime episodes are airing today and this week on ' . $websiteTitle . '.';
$pageRobots    = 'index, follow';
$pageCanonical = $websiteUrl . '/schedule';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
<style>
  .ak-sched-wrap { max-width: 900px; margin: 0 auto; }
  .ak-sched-head { margin-bottom: 18px; }
  .ak-sched-title { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
  .ak-sched-title i { color: var(--accent); }
  .ak-sched-sub { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
  .ak-sched-tabs { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 18px; }
  .ak-sched-tab { flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 8px 16px; border-radius: 10px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); color: var(--text-secondary); text-decoration: none; min-width: 68px; transition: all .15s; }
  .ak-sched-tab:hover { background: rgba(255,255,255,.06); color: #fff; }
  .ak-sched-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  .ak-sched-tab-day { font-size: 12px; font-weight: 700; text-transform: uppercase; }
  .ak-sched-tab-date { font-size: 10px; opacity: .8; }
  .ak-sched-list { display: flex; flex-direction: column; gap: 8px; }
  .ak-sched-row { display: flex; align-items: center; gap: 14px; padding: 10px 14px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.06); border-radius: 10px; text-decoration: none; color: inherit; transition: background .15s; }
  .ak-sched-row:hover { background: rgba(255,255,255,.05); }
  .ak-sched-time { flex-shrink: 0; width: 60px; font-size: 13px; font-weight: 700; color: var(--accent); text-align: center; }
  .ak-sched-time span { display: block; font-size: 9px; font-weight: 500; color: var(--text-muted); text-transform: uppercase; }
  .ak-sched-thumb { flex-shrink: 0; width: 44px; height: 62px; border-radius: 6px; overflow: hidden; background: var(--bg-card); }
  .ak-sched-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .ak-sched-info { flex: 1; min-width: 0; }
  .ak-sched-anime-title { font-size: 14px; font-weight: 600; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ak-sched-meta { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
  .ak-sched-ep-badge { flex-shrink: 0; padding: 4px 10px; border-radius: 6px; background: rgba(200,16,46,.15); border: 1px solid rgba(200,16,46,.3); color: var(--accent); font-size: 11px; font-weight: 700; white-space: nowrap; }
  .ak-sched-empty { text-align: center; padding: 60px 20px; color: var(--text-muted); }
  .ak-sched-empty i { font-size: 34px; margin-bottom: 12px; display: block; color: var(--accent); opacity: .7; }
</style>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <main class="ak-sched-wrap" style="padding: 20px 0;">

    <nav class="ak-breadcrumb">
      <a href="<?=$websiteUrl?>"><i class="fas fa-home"></i></a>
      <span class="sep">/</span>
      <span class="current">Schedule</span>
    </nav>

    <div class="ak-sched-head">
      <div class="ak-sched-title"><i class="fas fa-calendar-days"></i> Airing Schedule</div>
      <div class="ak-sched-sub">Estimated air times, shown in UTC — updated as soon as new episodes air.</div>
    </div>

    <div class="ak-sched-tabs">
      <?php foreach ($dayTabs as $tab): ?>
      <a class="ak-sched-tab <?=$tab['offset']===$day?'active':''?>" href="<?=$websiteUrl?>/schedule?day=<?=$tab['offset']?>">
        <span class="ak-sched-tab-day"><?=app_e($tab['label'])?></span>
        <span class="ak-sched-tab-date"><?=app_e($tab['date'])?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($scheduleRows)): ?>
    <div class="ak-sched-list">
      <?php foreach ($scheduleRows as $row):
        $sid   = (string)($row['animeId'] ?? '');
        $stit  = (string)($row['animeTitle'] ?? 'Unknown');
        $simg  = app_safe_image($row['animeImg'] ?? '');
        $stype = (string)($row['type'] ?? '');
        $sep   = (int)($row['episodeNum'] ?? 0);
        if ($sid === '') continue;
      ?>
      <a class="ak-sched-row" href="<?=$websiteUrl?>/anime/<?=app_e($sid)?>">
        <div class="ak-sched-time"><?=gmdate('H:i', (int)$row['airingAt'])?><span>UTC</span></div>
        <div class="ak-sched-thumb">
          <img src="<?=app_e($simg)?>" alt="<?=app_e($stit)?>" loading="lazy" onerror="this.src='<?=$websiteUrl?>/files/images/no_poster.jpg'">
        </div>
        <div class="ak-sched-info">
          <div class="ak-sched-anime-title"><?=app_e($stit)?></div>
          <?php if ($stype): ?><div class="ak-sched-meta"><?=app_e($stype)?></div><?php endif; ?>
        </div>
        <?php if ($sep > 0): ?>
        <div class="ak-sched-ep-badge">EP <?=$sep?></div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="ak-sched-empty">
      <i class="fas fa-satellite-dish"></i>
      <h3>No episodes scheduled for this day</h3>
      <p>Check another day, or check back later.</p>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
