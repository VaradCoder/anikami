<?php
/* Anikami – new HiAnime.to-style header partial.
   Expects $websiteUrl, $websiteTitle, and app_current_user() to be available. */
$_akUser = app_current_user();
$_akIsAdmin = ($_akUser && ($_akUser['role'] ?? '') === 'admin');
// Detect active page for nav highlighting
$_akReqPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$_akIsHome    = in_array(basename($_akReqPath), ['', 'home.php']) || $_akReqPath === $basePath . '/home' || $_akReqPath === $basePath . '/';
$_akIsPopular = strpos($_akReqPath, '/popular') !== false;
$_akIsMovies  = strpos($_akReqPath, '/type/movies') !== false;
$_akIsNew     = strpos($_akReqPath, '/new-season') !== false || strpos($_akReqPath, '/season/') !== false;
?>
<!-- Navigation progress bar -->
<div id="ak-nprog"><div id="ak-nprog-bar"></div></div>

<!-- Page-transition skeleton overlay -->
<div id="ak-nav-overlay" aria-hidden="true">
  <div class="ak-nav-skel-grid">
    <?php for ($_i = 0; $_i < 18; $_i++): ?>
    <div class="ak-skel-card">
      <div class="ak-skel-poster"></div>
      <div class="ak-skel-info">
        <div class="ak-skel-line w80"></div>
        <div class="ak-skel-line w55"></div>
        <div class="ak-skel-line w35"></div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- ============================  HEADER  ============================ -->
<div id="ak-header">
  <div class="ak-header-inner">
    <a href="<?=$websiteUrl?>/home" class="ak-brand">
      ❤Anikami<sub>アニカミ</sub>
    </a>

    <!-- Search -->
    <div class="ak-search-wrap" id="akSearchWrap">
      <input type="text" id="akSearchInput" placeholder="Search anime..." autocomplete="off" spellcheck="false">
      <button id="akSearchBtn" aria-label="Search"><i class="fas fa-search"></i></button>
      <div class="ak-search-dropdown" id="akSearchDropdown"></div>
    </div>

    <!-- Desktop nav -->
    <nav class="ak-header-nav">
      <a href="<?=$websiteUrl?>/home"          class="<?=$_akIsHome    ? 'active' : ''?>">Home</a>
      <a href="<?=$websiteUrl?>/anime"          class="">Anime</a>
      <a href="<?=$websiteUrl?>/popular"        class="<?=$_akIsPopular ? 'active' : ''?>">Popular</a>
      <a href="<?=$websiteUrl?>/new-season"     class="<?=$_akIsNew     ? 'active' : ''?>">New Season</a>
      <a href="<?=$websiteUrl?>/type/movies"    class="<?=$_akIsMovies  ? 'active' : ''?>">Movies</a>
      <a href="<?=$websiteUrl?>/random" rel="nofollow">Random</a>
    </nav>

    <div class="ak-header-actions">
      <a href="<?=htmlspecialchars($discord)?>" target="_blank" rel="noopener" class="ak-btn-discord">
        <i class="fab fa-discord"></i> Discord
      </a>
      <a href="<?=htmlspecialchars($telegram)?>" target="_blank" rel="noopener" class="ak-btn-telegram">
        <i class="fab fa-instagram"></i> instagram
      </a>

      <?php if ($_akUser): ?>
        <?php if ($_akUser): ?>
        <a href="<?=$websiteUrl?>/profile.php" class="ak-icon-btn" title="Watchlist" aria-label="My Watchlist"><i class="fas fa-bookmark" aria-hidden="true"></i></a>
        <?php endif; ?>
        <div class="ak-user-menu">
          <div class="ak-user-avatar" id="akUserAvatarBtn" title="<?=htmlspecialchars($_akUser['username'] ?? 'User')?>">
            <?=strtoupper(substr($_akUser['username'] ?? 'U', 0, 1))?>
          </div>
          <div class="ak-user-drop" id="akUserDrop">
            <a href="<?=$websiteUrl?>/profile.php"><i class="fas fa-user"></i> Profile</a>
            <?php if ($_akIsAdmin): ?>
            <a href="<?=$websiteUrl?>/admin/index.php"><i class="fas fa-cog"></i> Admin Panel</a>
            <?php endif; ?>
            <form method="post" action="<?=$websiteUrl?>/api/auth.php" style="display:contents">
              <input type="hidden" name="action" value="logout">
              <input type="hidden" name="csrf" value="<?=htmlspecialchars(app_csrf_token())?>">
              <button type="submit"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?=$websiteUrl?>/login.php" class="ak-btn-login">Login</a>
      <?php endif; ?>

      <!-- Mobile hamburger -->
      <button class="ak-hamburger" id="akHamburger" aria-label="Menu">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</div>

<!-- Mobile nav overlay -->
<div class="ak-mobile-nav" id="akMobileNav">
  <a class="ak-lnav-item <?=$_akIsHome?'active':''?>" href="<?=$websiteUrl?>/home"><i class="fas fa-home"></i>Home</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/anime"><i class="fas fa-tv"></i>Anime List</a>
  <a class="ak-lnav-item <?=$_akIsPopular?'active':''?>" href="<?=$websiteUrl?>/popular"><i class="fas fa-fire"></i>Popular</a>
  <a class="ak-lnav-item <?=$_akIsNew?'active':''?>" href="<?=$websiteUrl?>/new-season"><i class="fas fa-star"></i>New Season</a>
  <a class="ak-lnav-item <?=$_akIsMovies?'active':''?>" href="<?=$websiteUrl?>/type/movies"><i class="fas fa-film"></i>Movies</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/random" rel="nofollow"><i class="fas fa-random"></i>Random</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/schedule"><i class="fas fa-calendar"></i>Schedule</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/genre"><i class="fas fa-tags"></i>Genres</a>
</div>

<!-- Left sidebar nav (desktop) -->
<nav id="ak-leftnav">
  <a class="ak-lnav-item <?=$_akIsHome?'active':''?>" href="<?=$websiteUrl?>/home"><i class="fas fa-home"></i>Home</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/anime"><i class="fas fa-tv"></i>Anime</a>
  <a class="ak-lnav-item <?=$_akIsPopular?'active':''?>" href="<?=$websiteUrl?>/popular"><i class="fas fa-fire"></i>Popular</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/random" rel="nofollow"><i class="fas fa-random"></i>Random</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/schedule"><i class="fas fa-calendar-alt"></i>Schedule</a>
  <a class="ak-lnav-item" href="<?=$websiteUrl?>/genre"><i class="fas fa-tags"></i>Genres</a>
  <a class="ak-lnav-item <?=$_akIsMovies?'active':''?>" href="<?=$websiteUrl?>/type/movies"><i class="fas fa-film"></i>Movies</a>
</nav>
