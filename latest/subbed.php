<?php
require('../_config.php');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$cacheKey = 'latest_sub_page:' . $page;
$cached   = getCache($cacheKey);
if ($cached && is_array($cached)) {
    $items    = $cached['items']    ?? [];
    $lastPage = $cached['lastPage'] ?? 1;
} else {
    [$rawItems, $lastPage] = legacy_json_list_from_jikan('top/anime?filter=airing', $page);
    // Filter out dub titles to keep only subbed
    $items = array_values(array_filter($rawItems, function($i) {
        return !legacy_title_is_dub($i['animeTitle'] ?? '');
    }));
    setCache($cacheKey, ['items' => $items, 'lastPage' => $lastPage], 7200);
}

$pageTitle    = 'Latest Subbed Anime — ' . $websiteTitle;
$pageDesc     = 'Watch the latest subbed anime online in HD on ' . $websiteTitle . '. Free streaming, no ads.';
$pageCanonical = $websiteUrl . '/latest/subbed' . ($page > 1 ? '?page=' . $page : '');
$paginationHtml = legacy_pagination_html($page, $lastPage);

$genreList = [
    'Action','Adventure','Comedy','Drama','Fantasy','Romance','School',
    'Sci-Fi','Slice of Life','Sports','Supernatural','Thriller',
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

    <main class="ak-catalog-main">

      <nav class="ak-breadcrumb">
        <a href="<?=$websiteUrl?>"><i class="fas fa-home"></i></a>
        <span class="sep">/</span>
        <span class="current">Latest Subbed</span>
      </nav>

      <div class="ak-catalog-header">
        <h1 class="ak-catalog-title"><i class="fas fa-closed-captioning"></i> Latest Subbed Anime</h1>
        <span class="ak-catalog-count">Airing now</span>
      </div>

      <?php if (!empty($items)): ?>
      <div class="ak-anime-grid">
        <?php foreach ($items as $item):
          $name   = (string)($item['animeTitle'] ?? 'Unknown');
          $aid    = (string)($item['animeId']    ?? '');
          $status = (string)($item['status']     ?? '');
          $type   = (string)($item['type']        ?? '');
          $year   = (string)($item['releasedDate'] ?? '');
          $img    = app_safe_image($item['animeImg'] ?? $item['imgUrl'] ?? '');
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
                <span class="ak-card-badge ak-badge-sub">SUB</span>
                <?php if ($type): ?><span class="ak-card-badge ak-badge-type"><?=app_e(strtoupper($type))?></span><?php endif; ?>
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
        <p>Please try refreshing in a moment.</p>
      </div>
      <?php endif; ?>

    </main>

    <aside class="ak-catalog-sidebar">
      <div class="ak-sidebar-box">
        <h4 class="ak-sidebar-heading"><i class="fas fa-compass"></i> Quick Browse</h4>
        <ul class="ak-sidebar-links">
          <li><a href="<?=$websiteUrl?>/latest/dubbed"><i class="fas fa-microphone"></i> Latest Dubbed</a></li>
          <li><a href="<?=$websiteUrl?>/popular"><i class="fas fa-fire-alt"></i> Most Popular</a></li>
          <li><a href="<?=$websiteUrl?>/new-season"><i class="fas fa-leaf"></i> New Season</a></li>
          <li><a href="<?=$websiteUrl?>/type?type=movies"><i class="fas fa-film"></i> Movies</a></li>
          <li><a href="<?=$websiteUrl?>/status?status=ongoing"><i class="fas fa-circle" style="color:#22c55e;font-size:9px"></i> Ongoing</a></li>
          <li><a href="<?=$websiteUrl?>/status?status=completed"><i class="fas fa-check-circle"></i> Completed</a></li>
        </ul>
      </div>
      <div class="ak-sidebar-box" style="margin-top:18px;">
        <h4 class="ak-sidebar-heading"><i class="fas fa-tags"></i> Browse by Genre</h4>
        <div class="ak-genre-cloud">
          <?php foreach ($genreList as $g):
            $slug = strtolower(str_replace(' ', '-', $g));
          ?>
          <a href="<?=$websiteUrl?>/genre/<?=$slug?>" class="ak-genre-tag"><?=$g?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

  </div>
</div>

<?php include('../_php/ak_footer.php'); ?>
</body>
</html>
