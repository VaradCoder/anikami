<?php
require('../_config.php');

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'tv-series';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$typeMap = [
    'movies'    => ['jikan' => 'top/anime?type=movie',   'label' => 'Anime Movies',   'icon' => 'fa-film'],
    'tv-series' => ['jikan' => 'top/anime?type=tv',      'label' => 'TV Series',       'icon' => 'fa-tv'],
    'ova'       => ['jikan' => 'top/anime?type=ova',     'label' => 'OVA',             'icon' => 'fa-compact-disc'],
    'ona'       => ['jikan' => 'top/anime?type=ona',     'label' => 'ONA',             'icon' => 'fa-play-circle'],
    'special'   => ['jikan' => 'top/anime?type=special', 'label' => 'Special',         'icon' => 'fa-star'],
    'music'     => ['jikan' => 'top/anime?type=music',   'label' => 'Music Anime',     'icon' => 'fa-music'],
];

if (!isset($typeMap[$type])) $type = 'tv-series';

$jikanEndpoint = $typeMap[$type]['jikan'];
$pageLabel     = $typeMap[$type]['label'];
$pageIcon      = $typeMap[$type]['icon'];

$cacheKey = 'type_' . $type . '_page:' . $page;
$cached   = getCache($cacheKey);
if ($cached && is_array($cached)) {
    $items    = $cached['items']    ?? [];
    $lastPage = $cached['lastPage'] ?? 1;
} else {
    [$items, $lastPage] = legacy_json_list_from_jikan($jikanEndpoint, $page);
    setCache($cacheKey, ['items' => $items, 'lastPage' => $lastPage], 14400);
}

$pageTitle    = app_e($pageLabel) . ' — ' . $websiteTitle;
$pageDesc     = 'Watch ' . $pageLabel . ' online in HD on ' . $websiteTitle . '. Free anime streaming.';
$pageCanonical = $websiteUrl . '/type?type=' . $type . ($page > 1 ? '&page=' . $page : '');
$paginationHtml = legacy_pagination_html($page, $lastPage, ['type' => $type]);

$genreList = [
    'Action','Adventure','Comedy','Drama','Fantasy','Horror','Mecha','Mystery',
    'Psychological','Romance','School','Sci-Fi','Slice of Life','Sports','Supernatural',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('../_php/ak_page_head.php'); ?>
</head>
<body class="ak-body">
<?php include('../_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <div class="ak-catalog-wrap">

    <!-- MAIN -->
    <main class="ak-catalog-main">

      <nav class="ak-breadcrumb">
        <a href="<?=$websiteUrl?>"><i class="fas fa-home"></i></a>
        <span class="sep">/</span>
        <span class="current"><?=app_e($pageLabel)?></span>
      </nav>

      <!-- Type filter tabs -->
      <div class="ak-catalog-header" style="flex-direction:column;align-items:flex-start;gap:12px;">
        <h1 class="ak-catalog-title"><i class="fas <?=$pageIcon?>"></i> <?=app_e($pageLabel)?></h1>
        <div class="ak-filters">
          <span class="ak-filter-label">Type:</span>
          <?php foreach ($typeMap as $tk => $tv): ?>
          <a href="<?=$websiteUrl?>/type?type=<?=$tk?>"
             class="ak-filter-btn<?= $tk === $type ? ' active' : '' ?>"><?=app_e($tv['label'])?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!empty($items)): ?>
      <div class="ak-anime-grid">
        <?php foreach ($items as $item):
          $name   = (string)($item['animeTitle'] ?? 'Unknown');
          $aid    = (string)($item['animeId']    ?? '');
          $status = (string)($item['status']     ?? '');
          $itype  = (string)($item['type']        ?? '');
          $year   = (string)($item['releasedDate'] ?? '');
          $img    = app_safe_image($item['animeImg'] ?? $item['imgUrl'] ?? '');
          $isDub  = legacy_title_is_dub($name);
          if ($aid === '') continue;
        ?>
        <div class="ak-anime-card">
          <a href="<?=$websiteUrl?>/anime/<?=app_e($aid)?>" class="ak-card-poster-link">
            <div class="ak-card-poster">
              <img src="<?=$websiteUrl?>/files/images/no_poster.jpg"
                   data-src="<?=app_e($img)?>"
                   alt="<?=app_e($name)?>" class="lazyload" loading="lazy">
              <div class="ak-card-overlay"><i class="fas fa-play"></i></div>
              <div class="ak-card-badges">
                <span class="ak-card-badge ak-badge-<?=$isDub ? 'dub' : 'sub'?>"><?=$isDub ? 'DUB' : 'SUB'?></span>
                <?php if ($itype): ?><span class="ak-card-badge ak-badge-type"><?=app_e(strtoupper($itype))?></span><?php endif; ?>
              </div>
            </div>
          </a>
          <div class="ak-card-info">
            <h3 class="ak-card-title">
              <a href="<?=$websiteUrl?>/anime/<?=app_e($aid)?>" title="<?=app_e($name)?>"><?=app_e($name)?></a>
            </h3>
            <div class="ak-card-meta">
              <?php if ($year): ?><span><?=app_e($year)?></span><?php endif; ?>
              <?php if ($status): ?><span class="ak-dot">·</span><span><?=app_e($status)?></span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($lastPage > 1): ?>
      <div class="ak-pagination-wrap">
        <ul><?=$paginationHtml?></ul>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="ak-search-empty">
        <i class="fas fa-satellite-dish"></i>
        <h3>Data temporarily unavailable</h3>
        <p>Please try refreshing the page in a moment.</p>
      </div>
      <?php endif; ?>

    </main>

    <!-- SIDEBAR -->
    <aside class="ak-catalog-sidebar">
      <div class="ak-sidebar-box">
        <h4 class="ak-sidebar-heading"><i class="fas fa-tags"></i> Browse by Genre</h4>
        <div class="ak-genre-cloud">
          <?php foreach ($genreList as $g):
            $gs = strtolower(str_replace(' ', '-', $g));
          ?>
          <a href="<?=$websiteUrl?>/genre/<?=$gs?>" class="ak-genre-tag"><?=$g?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ak-sidebar-box" style="margin-top:18px;">
        <h4 class="ak-sidebar-heading"><i class="fas fa-compass"></i> Quick Browse</h4>
        <ul class="ak-sidebar-links">
          <li><a href="<?=$websiteUrl?>/popular"><i class="fas fa-fire-alt"></i> Most Popular</a></li>
          <li><a href="<?=$websiteUrl?>/new-season"><i class="fas fa-leaf"></i> New Season</a></li>
          <li><a href="<?=$websiteUrl?>/status?status=ongoing"><i class="fas fa-circle" style="color:#22c55e;font-size:9px"></i> Ongoing</a></li>
          <li><a href="<?=$websiteUrl?>/status?status=completed"><i class="fas fa-check-circle"></i> Completed</a></li>
        </ul>
      </div>
    </aside>

  </div>
</div>

<?php include('../_php/ak_footer.php'); ?>
</body>
</html>
