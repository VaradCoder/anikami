<?php
// Shared admin shell — sidebar + topbar open. Every admin page (except
// login.php) includes this, sets $adminPageTitle / $adminActive before
// including it, then closes with _layout_bottom.php.
// $admin must already be set via app_require_admin() before this include.

$adminPendingReports = function_exists('app_report_count_pending') ? app_report_count_pending() : 0;
$adminEnv = (string)(getenv('APP_ENV') ?: 'production');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?=app_e($adminPageTitle ?? 'Admin')?> - <?=app_e($websiteTitle)?></title>
  <?php include __DIR__ . '/../_php/ak_page_head.php'; ?>
  <link rel="stylesheet" href="<?=$websiteUrl?>/files/css/admin.css?v=<?=$version?>">
</head>
<body class="ak-body">
<div class="ak-admin-body">

  <aside class="ak-admin-sidebar" id="akAdminSidebar">
    <div class="ak-admin-brand">
      <div style="display:flex;align-items:center;gap:9px;"><i class="fas fa-shield-halved"></i> ANIKATSU</div>
      <div class="ak-admin-brand-sub">Admin Control Center</div>
      <div class="ak-admin-brand-meta">
        <span title="Site version"><i class="fas fa-code-branch"></i> v<?=app_e($version)?></span>
        <span title="Environment" class="<?=$adminEnv==='production'?'prod':'dev'?>"><i class="fas fa-server"></i> <?=app_e($adminEnv)?></span>
        <span title="Admin panel is responding — this isn't a deep health check, just confirmation the request reached PHP"><i class="fas fa-circle" style="color:#22c55e;font-size:7px;"></i> Operational</span>
      </div>
    </div>
    <nav class="ak-admin-nav">

      <div class="ak-admin-group">
        <a href="<?=$websiteUrl?>/admin/index.php" class="ak-admin-link <?=($adminActive ?? '')==='dashboard'?'active':''?>"><i class="fas fa-house"></i> Dashboard</a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">Users</div>
        <a href="<?=$websiteUrl?>/admin/users.php" class="ak-admin-link <?=($adminActive ?? '')==='users'?'active':''?>"><i class="fas fa-users"></i> User List</a>
        <a href="<?=$websiteUrl?>/admin/users.php?status=banned" class="ak-admin-link <?=($adminActive ?? '')==='bans'?'active':''?>"><i class="fas fa-ban"></i> Bans</a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">Streams</div>
        <a href="<?=$websiteUrl?>/admin/streams.php" class="ak-admin-link <?=($adminActive ?? '')==='streams'?'active':''?>"><i class="fas fa-tower-broadcast"></i> Provider Health</a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">Insights</div>
        <a href="<?=$websiteUrl?>/admin/analytics.php" class="ak-admin-link <?=($adminActive ?? '')==='analytics'?'active':''?>"><i class="fas fa-chart-line"></i> Analytics Center</a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">Content</div>
        <a href="<?=$websiteUrl?>/admin/settings.php" class="ak-admin-link <?=($adminActive ?? '')==='settings'?'active':''?>"><i class="fas fa-star"></i> Featured & Slugs</a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">Community</div>
        <a href="<?=$websiteUrl?>/admin/comments.php" class="ak-admin-link <?=($adminActive ?? '')==='comments'?'active':''?>"><i class="fas fa-comments"></i> Comments</a>
        <a href="<?=$websiteUrl?>/admin/reviews.php" class="ak-admin-link <?=($adminActive ?? '')==='reviews'?'active':''?>"><i class="fas fa-star-half-stroke"></i> Reviews</a>
        <a href="<?=$websiteUrl?>/admin/comments.php?tab=reports" class="ak-admin-link <?=($adminActive ?? '')==='reports'?'active':''?>">
          <i class="fas fa-flag"></i> Reports
          <?php if ($adminPendingReports > 0): ?><span class="ak-admin-soon" style="background:rgba(239,68,68,.25);color:#fca5a5;"><?=$adminPendingReports?></span><?php endif; ?>
        </a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">System</div>
        <a href="<?=$websiteUrl?>/admin/audit-log.php" class="ak-admin-link <?=($adminActive ?? '')==='audit'?'active':''?>"><i class="fas fa-clipboard-list"></i> Audit Logs</a>
        <a href="<?=$websiteUrl?>/admin/settings.php#system" class="ak-admin-link <?=($adminActive ?? '')==='settings'?'active':''?>"><i class="fas fa-gear"></i> Settings</a>
      </div>

      <div class="ak-admin-group">
        <div class="ak-admin-group-label">Planned (not built yet)</div>
        <?php foreach ([
            ['fa-tags', 'Anime Library'],
            ['fa-bell', 'Notifications'],
            ['fa-photo-film', 'Media Manager'],
            ['fa-user-shield', 'Roles & Permissions'],
            ['fa-robot', 'AI Insights'],
        ] as $p): ?>
        <span class="ak-admin-link planned"><i class="fas <?=$p[0]?>"></i> <?=$p[1]?> <span class="ak-admin-soon">SOON</span></span>
        <?php endforeach; ?>
      </div>

    </nav>
  </aside>

  <div class="ak-admin-main">
    <div class="ak-admin-topbar">
      <div style="display:flex;align-items:center;gap:10px;">
        <button class="ak-abtn sm" id="akAdminSidebarToggle" style="display:none;" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
        <h1><?=app_e($adminPageTitle ?? 'Admin')?></h1>
      </div>
      <div class="ak-admin-topbar-meta">
        <span><i class="fas fa-user-shield"></i> <?=app_e($admin['username'] ?? '')?></span>
        <a href="<?=$websiteUrl?>/"><i class="fas fa-arrow-left"></i> Site</a>
        <a href="<?=$websiteUrl?>/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
    <div class="ak-admin-content">
