<?php
require('./_config.php');
require_once __DIR__ . '/services/SearchService.php';

$keyword  = trim((string)($_GET['keyword'] ?? ($_GET['q'] ?? '')));
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$results  = [];
$lastPage = 1;
$totalResults = 0;

if ($keyword !== '') {
    $searchResult = (new SearchService())->searchAnime($keyword, $page);
    $results      = $searchResult['results'];
    $lastPage     = $searchResult['lastPage'];
    $totalResults = $searchResult['total'];

    // Tracked on the submitted search page only (not the header's live
    // typeahead) and only page 1, so "Most Searched"/"Failed Searches"
    // reflect deliberate searches, not pagination clicks or partial typing.
    if ($page === 1) {
        app_track_event('search', null, ['query' => $keyword, 'result_count' => $totalResults]);
    }
}

$pageTitle   = $keyword !== '' ? 'Search: ' . app_e($keyword) . ' — ' . $websiteTitle : 'Search Anime — ' . $websiteTitle;
$pageDesc    = $keyword !== '' ? 'Search results for "' . $keyword . '" on ' . $websiteTitle . '. Watch anime online in HD.' : 'Search for your favourite anime on ' . $websiteTitle;
$pageRobots  = 'noindex, follow';
$pageCanonical = $websiteUrl . '/search?keyword=' . urlencode($keyword) . ($page > 1 ? '&page=' . $page : '');
$paginationHtml = legacy_pagination_html($page, $lastPage, ['keyword' => $keyword]);

$genreList = [
    'Action','Adventure','Comedy','Drama','Fantasy','Horror','Magic','Mecha','Music',
    'Mystery','Psychological','Romance','School','Sci-Fi','Slice of Life','Sports',
    'Supernatural','Thriller','Vampire','Harem','Ecchi','Historical','Military',
    'Samurai','Shounen','Seinen','Josei','Shoujo'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <div class="ak-catalog-wrap">

    <!-- MAIN -->
    <main class="ak-catalog-main">

      <!-- Breadcrumb -->
      <nav class="ak-breadcrumb">
        <a href="<?=$websiteUrl?>"><i class="fas fa-home"></i></a>
        <span class="sep">/</span>
        <span class="current">Search<?= $keyword !== '' ? ': ' . app_e($keyword) : '' ?></span>
      </nav>

      <!-- Search bar at top of results -->
      <form class="ak-search-page-bar" method="get" action="<?=$websiteUrl?>/search">
        <input type="text" name="keyword" placeholder="Search anime title…"
               value="<?=app_e($keyword)?>" autocomplete="off" aria-label="Search">
        <button type="submit"><i class="fas fa-search"></i></button>
      </form>

      <?php if ($keyword !== ''): ?>
      <div class="ak-catalog-header">
        <h1 class="ak-catalog-title">
          <i class="fas fa-search"></i>
          Results for <span class="ak-search-kw">&ldquo;<?=app_e($keyword)?>&rdquo;</span>
        </h1>
        <?php if ($totalResults > 0): ?>
        <span class="ak-catalog-count"><?=number_format($totalResults)?> anime found</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($results)): ?>
      <div class="ak-anime-grid">
        <?php foreach ($results as $item):
          $name    = (string)($item['animeTitle'] ?? 'Unknown');
          $aid     = (string)($item['animeId']    ?? '');
          $status  = (string)($item['status']     ?? '');
          $type    = (string)($item['type']        ?? '');
          $year    = (string)($item['releasedDate'] ?? '');
          $img     = app_safe_image($item['animeImg'] ?? '');
          $isDub   = legacy_title_is_dub($name);
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
        <i class="fas fa-ghost"></i>
        <h3>No results for &ldquo;<?=app_e($keyword)?>&rdquo;</h3>
        <p>Try a different spelling or search with a shorter keyword.</p>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <!-- No keyword yet -->
      <div class="ak-search-empty" style="padding-top:80px;">
        <i class="fas fa-search"></i>
        <h3>Find your next anime</h3>
        <p>Type a title above and press Enter or click Search.</p>
      </div>
      <?php endif; ?>

    </main>

    <!-- SIDEBAR -->
    <aside class="ak-catalog-sidebar">
      <div class="ak-sidebar-box">
        <h4 class="ak-sidebar-heading"><i class="fas fa-tags"></i> Browse by Genre</h4>
        <div class="ak-genre-cloud">
          <?php foreach ($genreList as $g):
            $slug = strtolower(str_replace(' ', '-', $g));
          ?>
          <a href="<?=$websiteUrl?>/genre/<?=$slug?>" class="ak-genre-tag"><?=$g?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ak-sidebar-box" style="margin-top:18px;">
        <h4 class="ak-sidebar-heading"><i class="fas fa-compass"></i> Quick Browse</h4>
        <ul class="ak-sidebar-links">
          <li><a href="<?=$websiteUrl?>/popular"><i class="fas fa-fire-alt"></i> Most Popular</a></li>
          <li><a href="<?=$websiteUrl?>/new-season"><i class="fas fa-leaf"></i> New Season</a></li>
          <li><a href="<?=$websiteUrl?>/type?type=movies"><i class="fas fa-film"></i> Movies</a></li>
          <li><a href="<?=$websiteUrl?>/type?type=tv-series"><i class="fas fa-tv"></i> TV Series</a></li>
          <li><a href="<?=$websiteUrl?>/status?status=ongoing"><i class="fas fa-circle" style="color:#22c55e;font-size:9px"></i> Ongoing</a></li>
          <li><a href="<?=$websiteUrl?>/status?status=completed"><i class="fas fa-check-circle"></i> Completed</a></li>
        </ul>
      </div>
    </aside>

  </div><!-- /.ak-catalog-wrap -->
</div><!-- /.ak-page-wrap -->

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
