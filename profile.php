<?php
require_once __DIR__ . '/_config.php';
$user     = app_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    if (($_POST['action'] ?? '') === 'revoke_session' && !empty($_POST['session_id'])) {
        app_revoke_session((int)$user['id'], (int)$_POST['session_id']);
    } elseif (($_POST['action'] ?? '') === 'revoke_all_sessions') {
        app_revoke_all_sessions((int)$user['id'], (int)($_SESSION['session_row_id'] ?? 0));
    }
    app_redirect('/profile.php#sessions');
}

$watchlist = app_watchlist_list((int)$user['id'], 60);
$history   = app_watch_history_list((int)$user['id'], 60);
$continue  = app_continue_watching_list((int)$user['id'], 20);
$sessions  = app_list_user_sessions((int)$user['id']);
$currentSessionId = (int)($_SESSION['session_row_id'] ?? 0);
$animeLists = app_get_user_lists_grouped((int)$user['id']);
$listStatusMeta = [
    'watching'      => ['label' => 'Watching',      'icon' => 'fa-eye'],
    'completed'     => ['label' => 'Completed',     'icon' => 'fa-circle-check'],
    'on_hold'       => ['label' => 'On Hold',        'icon' => 'fa-pause'],
    'dropped'       => ['label' => 'Dropped',        'icon' => 'fa-circle-xmark'],
    'plan_to_watch' => ['label' => 'Plan to Watch',  'icon' => 'fa-clock'],
];

$pageTitle    = 'My Profile — ' . $websiteTitle;
$pageDesc     = 'Manage your watchlist, watch history and continue watching on ' . $websiteTitle;
$pageRobots   = 'noindex, nofollow';
$pageCanonical = $websiteUrl . '/profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
<style>
.ak-profile-wrap { max-width: 1000px; margin: 0 auto; padding: 24px 16px 60px; }
.ak-profile-hero { display: flex; align-items: center; gap: 20px; padding: 28px; background: var(--bg-card); border: 1px solid rgba(255,255,255,.07); border-radius: 14px; margin-bottom: 28px; }
.ak-profile-avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #8b0000); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; color: #fff; flex-shrink: 0; font-family: 'Cinzel', serif; }
.ak-profile-info h2 { font-size: 22px; font-weight: 700; color: var(--text-main); margin: 0 0 4px; }
.ak-profile-info p { font-size: 13px; color: var(--text-muted); margin: 0; }
.ak-profile-actions { margin-left: auto; display: flex; gap: 8px; }
.ak-tab-section { margin-bottom: 34px; }
.ak-tab-heading { display: flex; align-items: center; gap: 9px; font-size: 15px; font-weight: 700; color: var(--text-main); margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,.06); }
.ak-tab-heading i { color: var(--accent); }
.ak-list-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.ak-list-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.06); border-radius: 8px; transition: border-color .2s; text-decoration: none; }
.ak-list-item:hover { border-color: rgba(200,16,46,.4); background: rgba(200,16,46,.05); }
.ak-list-item-icon { width: 32px; height: 32px; border-radius: 6px; background: rgba(200,16,46,.15); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 12px; flex-shrink: 0; }
.ak-list-item-text { min-width: 0; }
.ak-list-item-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; line-height: 1.4; }
.ak-list-item-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.ak-empty-section { text-align: center; padding: 36px; color: var(--text-muted); font-size: 13px; border: 1px dashed rgba(255,255,255,.08); border-radius: 10px; }
.ak-empty-section i { display: block; font-size: 32px; margin-bottom: 10px; opacity: .4; }
@media (max-width: 560px) {
  .ak-profile-hero { flex-wrap: wrap; padding: 20px; }
  .ak-profile-actions { margin-left: 0; width: 100%; }
  .ak-list-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <div class="ak-profile-wrap">

    <!-- Profile hero -->
    <div class="ak-profile-hero">
      <div class="ak-profile-avatar"><?=mb_strtoupper(mb_substr($user['username'], 0, 1))?></div>
      <div class="ak-profile-info">
        <h2><?=app_e($user['username'])?></h2>
        <p><i class="fas fa-calendar-alt"></i> Member since <?=date('M Y', strtotime($user['created_at'] ?? 'now'))?>
          <?php if (empty($user['email_verified'])): ?>
            &nbsp;· <span style="color:#f59e0b"><i class="fas fa-triangle-exclamation"></i> Email not verified</span>
          <?php else: ?>
            &nbsp;· <span style="color:#22c55e"><i class="fas fa-check-circle"></i> Verified</span>
          <?php endif; ?>
        </p>
      </div>
      <div class="ak-profile-actions">
        <a href="<?=$websiteUrl?>/logout" class="ak-btn-secondary" style="padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text-secondary);text-decoration:none;transition:all .2s;">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>

    <!-- Continue Watching -->
    <div class="ak-tab-section">
      <h3 class="ak-tab-heading">
        <i class="fas fa-history"></i> Continue Watching
        <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:auto;"><?=count($continue)?> titles</span>
      </h3>
      <?php if (!empty($continue)): ?>
      <div class="ak-list-grid">
        <?php foreach ($continue as $row):
          $title  = app_e(legacy_unslug($row['anime_id']));
          $ep     = (int)($row['episode'] ?? 0);
          $pos    = (int)($row['position_seconds'] ?? 0);
          $mins   = floor($pos / 60);
          $secs   = $pos % 60;
          $href   = $websiteUrl . '/watch/' . rawurlencode($row['anime_id']) . '-episode-' . $ep;
        ?>
        <a href="<?=$href?>" class="ak-list-item">
          <div class="ak-list-item-icon"><i class="fas fa-play"></i></div>
          <div class="ak-list-item-text">
            <span class="ak-list-item-title"><?=$title?></span>
            <span class="ak-list-item-meta">
              Ep <?=$ep?>
              <?php if ($pos > 0): ?>&nbsp;· <?=$mins?>:<?=str_pad($secs,2,'0',STR_PAD_LEFT)?><?php endif; ?>
            </span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="ak-empty-section"><i class="fas fa-play-circle"></i>Nothing in progress yet. Start watching!</div>
      <?php endif; ?>
    </div>

    <!-- My Lists (Watching/Completed/On Hold/Dropped/Plan to Watch) -->
    <div class="ak-tab-section">
      <h3 class="ak-tab-heading">
        <i class="fas fa-list-ul"></i> My Lists
      </h3>
      <div class="ak-mylists-tabs" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">
        <?php foreach ($listStatusMeta as $statusKey => $meta): ?>
        <button class="ak-mylists-tab-btn <?=$statusKey==='watching'?'active':''?>" data-status="<?=app_e($statusKey)?>"
                style="padding:7px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:<?=$statusKey==='watching'?'var(--accent)':'rgba(255,255,255,.05)'?>;color:<?=$statusKey==='watching'?'#fff':'var(--text-secondary)'?>;">
          <i class="fas <?=$meta['icon']?>"></i> <?=app_e($meta['label'])?>
          <span style="opacity:.7;"> (<?=count($animeLists[$statusKey])?>)</span>
        </button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($listStatusMeta as $statusKey => $meta): ?>
      <div class="ak-mylists-panel" data-status-panel="<?=app_e($statusKey)?>" style="<?=$statusKey==='watching'?'':'display:none'?>">
        <?php if (!empty($animeLists[$statusKey])): ?>
        <div class="ak-list-grid">
          <?php foreach ($animeLists[$statusKey] as $row):
            $lTitle = app_e(legacy_unslug($row['anime_id']));
            $lHref  = $websiteUrl . '/anime/' . rawurlencode($row['anime_id']);
            $lDate  = !empty($row['updated_at']) ? date('M j, Y', strtotime($row['updated_at'])) : '';
          ?>
          <a href="<?=$lHref?>" class="ak-list-item">
            <div class="ak-list-item-icon"><i class="fas <?=$meta['icon']?>"></i></div>
            <div class="ak-list-item-text">
              <span class="ak-list-item-title"><?=$lTitle?></span>
              <?php if ($lDate): ?><span class="ak-list-item-meta"><?=$lDate?></span><?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="ak-empty-section"><i class="fas <?=$meta['icon']?>"></i>Nothing in "<?=app_e($meta['label'])?>" yet.</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Watchlist -->
    <div class="ak-tab-section">
      <h3 class="ak-tab-heading">
        <i class="fas fa-bookmark"></i> Watchlist
        <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:auto;"><?=count($watchlist)?> titles</span>
      </h3>
      <?php if (!empty($watchlist)): ?>
      <div class="ak-list-grid">
        <?php foreach ($watchlist as $row):
          $title = app_e(legacy_unslug($row['anime_id']));
          $href  = $websiteUrl . '/anime/' . rawurlencode($row['anime_id']);
          $added = !empty($row['created_at']) ? date('M j, Y', strtotime($row['created_at'])) : '';
        ?>
        <a href="<?=$href?>" class="ak-list-item">
          <div class="ak-list-item-icon"><i class="fas fa-bookmark"></i></div>
          <div class="ak-list-item-text">
            <span class="ak-list-item-title"><?=$title?></span>
            <?php if ($added): ?><span class="ak-list-item-meta"><?=$added?></span><?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="ak-empty-section"><i class="fas fa-bookmark"></i>Your watchlist is empty. Bookmark anime to save them here.</div>
      <?php endif; ?>
    </div>

    <!-- Watch History -->
    <div class="ak-tab-section">
      <h3 class="ak-tab-heading">
        <i class="fas fa-clock"></i> Watch History
        <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:auto;"><?=count($history)?> entries</span>
      </h3>
      <?php if (!empty($history)): ?>
      <div class="ak-list-grid">
        <?php foreach (array_slice($history, 0, 48) as $row):
          $title  = app_e(legacy_unslug($row['anime_id']));
          $ep     = (int)($row['episode'] ?? 0);
          $href   = $websiteUrl . '/watch/' . rawurlencode($row['anime_id']) . '-episode-' . $ep;
          $watched = !empty($row['updated_at']) ? date('M j', strtotime($row['updated_at'])) : '';
        ?>
        <a href="<?=$href?>" class="ak-list-item">
          <div class="ak-list-item-icon"><i class="fas fa-eye"></i></div>
          <div class="ak-list-item-text">
            <span class="ak-list-item-title"><?=$title?></span>
            <span class="ak-list-item-meta">Ep <?=$ep?><?php if ($watched): ?> &nbsp;· <?=$watched?><?php endif; ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="ak-empty-section"><i class="fas fa-clock"></i>No watch history yet. Start watching anime!</div>
      <?php endif; ?>
    </div>

    <!-- Active Sessions / Devices -->
    <div class="ak-tab-section" id="sessions">
      <h3 class="ak-tab-heading">
        <i class="fas fa-shield-halved"></i> Active Sessions
        <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:auto;"><?=count($sessions)?> active</span>
      </h3>
      <?php if (!empty($sessions)): ?>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($sessions as $s):
          $isCurrent = (int)$s['id'] === $currentSessionId;
        ?>
        <div class="ak-list-item" style="cursor:default;">
          <div class="ak-list-item-icon"><i class="fas fa-<?=$s['is_remember']?'mobile-screen':'desktop'?>"></i></div>
          <div class="ak-list-item-text" style="flex:1;">
            <span class="ak-list-item-title">
              <?=app_e($s['device_name'] ?: 'Unknown device')?>
              <?php if ($isCurrent): ?><span style="color:var(--accent);font-weight:700;">· This device</span><?php endif; ?>
            </span>
            <span class="ak-list-item-meta">
              <?=app_e($s['ip_address'] ?: 'Unknown IP')?>
              &nbsp;· Last active <?=date('M j, g:ia', strtotime($s['last_active']))?>
              <?php if (!empty($s['is_remember'])): ?>&nbsp;· Remember me<?php endif; ?>
            </span>
          </div>
          <?php if (!$isCurrent): ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
            <input type="hidden" name="action" value="revoke_session">
            <input type="hidden" name="session_id" value="<?=(int)$s['id']?>">
            <button type="submit" class="ak-wac-action" style="padding:6px 10px;"><i class="fas fa-xmark"></i></button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($sessions) > 1): ?>
      <form method="post" style="margin-top:12px;">
        <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
        <input type="hidden" name="action" value="revoke_all_sessions">
        <button type="submit" class="ak-wac-action"><i class="fas fa-power-off"></i> Logout All Other Devices</button>
      </form>
      <?php endif; ?>
      <?php else: ?>
      <div class="ak-empty-section"><i class="fas fa-shield-halved"></i>No active sessions found.</div>
      <?php endif; ?>
    </div>

  </div><!-- /.ak-profile-wrap -->
</div><!-- /.ak-page-wrap -->

<script>
(function(){
  var tabBtns = document.querySelectorAll('.ak-mylists-tab-btn');
  var panels  = document.querySelectorAll('.ak-mylists-panel');
  tabBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var status = btn.dataset.status;
      tabBtns.forEach(function(b){
        var active = b === btn;
        b.style.background = active ? 'var(--accent)' : 'rgba(255,255,255,.05)';
        b.style.color = active ? '#fff' : 'var(--text-secondary)';
      });
      panels.forEach(function(p){
        p.style.display = (p.dataset.statusPanel === status) ? '' : 'none';
      });
    });
  });
})();
</script>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
