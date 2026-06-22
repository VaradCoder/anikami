<?php
require('../_config.php');
require_once __DIR__ . '/../services/AnimeService.php';

$parts    = parse_url($_SERVER['REQUEST_URI']);
$page_url = explode('/', rtrim($parts['path'], '/'));
$lastSeg  = strtolower(trim((string)(end($page_url) ?: '')));
$slug     = preg_replace('/[^a-z0-9\-]/', '', $lastSeg);
// "/genre" or "/genre/" with no slug after it — browse-all view, not a
// silent fallback to "action" (that previously made the bare /genre link
// in the header nav render a misleading single-genre page).
$isIndex = ($slug === '' || $slug === 'genre');
if ($isIndex) $slug = '';

$genreLabel = $isIndex ? '' : ucwords(str_replace('-', ' ', $slug));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$items = [];
$lastPage = 1;
$paginationHtml = '';
if (!$isIndex) {
    $genreMap = legacy_get_genre_map();
    $genreId  = $genreMap[$slug] ?? 1;
    $resp     = (new AnimeService())->getByGenre($genreId, $page, 24);
    foreach (($resp['data'] ?? []) as $row) {
        $items[] = legacy_normalize_item($row);
    }
    $lastPage = (int)($resp['pagination']['last_visible_page'] ?? 1);
    $paginationHtml = legacy_pagination_html($page, $lastPage, ['genre' => $slug]);
}

$pageTitle     = $isIndex ? ('Browse Anime by Genre — ' . $websiteTitle) : (app_e($genreLabel) . ' Anime — ' . $websiteTitle);
$pageDesc      = $isIndex ? ('Browse the full list of anime genres on ' . $websiteTitle . '.') : ('Watch ' . $genreLabel . ' anime online in HD on ' . $websiteTitle . '. Free streaming, no ads.');
$pageCanonical = $isIndex ? ($websiteUrl . '/genre') : ($websiteUrl . '/genre/' . $slug . ($page > 1 ? '?page=' . $page : ''));

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
        <?php if ($isIndex): ?>
        <span class="current">Genre</span>
        <?php else: ?>
        <a href="<?=$websiteUrl?>/genre">Genre</a>
        <span class="sep">/</span>
        <span class="current"><?=app_e($genreLabel)?></span>
        <?php endif; ?>
      </nav>

      <div class="ak-catalog-header">
        <?php if ($isIndex): ?>
        <h1 class="ak-catalog-title"><i class="fas fa-tags"></i> Browse by Genre</h1>
        <?php else: ?>
        <h1 class="ak-catalog-title"><i class="fas fa-tag"></i> <?=app_e($genreLabel)?> Anime</h1>
        <?php if ($page > 1): ?>
        <span class="ak-catalog-count">Page <?=$page?></span>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <?php if ($isIndex): ?>
      <div class="ak-genre-cloud" style="margin-top:6px;">
        <?php foreach ($genreList as $g):
          $gs = strtolower(str_replace(' ', '-', $g));
        ?>
        <a href="<?=$websiteUrl?>/genre/<?=$gs?>" class="ak-genre-tag"><?=$g?></a>
        <?php endforeach; ?>
      </div>
      <?php elseif (!empty($items)): ?>
      <div class="ak-anime-grid">
        <?php foreach ($items as $item):
          $name   = (string)($item['animeTitle'] ?? 'Unknown');
          $aid    = (string)($item['animeId']    ?? '');
          $status = (string)($item['status']     ?? '');
          $type   = (string)($item['type']        ?? '');
          $year   = (string)($item['releasedDate'] ?? '');
          $img    = app_safe_image($item['animeImg'] ?? '');
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
        <h3>No <?=app_e($genreLabel)?> anime found</h3>
        <p>Try another genre or check back later.</p>
      </div>
      <?php endif; ?>

    </main>

    <!-- SIDEBAR -->
    <aside class="ak-catalog-sidebar">
      <div class="ak-sidebar-box">
        <h4 class="ak-sidebar-heading"><i class="fas fa-tags"></i> All Genres</h4>
        <div class="ak-genre-cloud">
          <?php foreach ($genreList as $g):
            $gs = strtolower(str_replace(' ', '-', $g));
            $active = ($gs === $slug) ? ' active' : '';
          ?>
          <a href="<?=$websiteUrl?>/genre/<?=$gs?>" class="ak-genre-tag<?=$active?>"><?=$g?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ak-sidebar-box" style="margin-top:18px;">
        <h4 class="ak-sidebar-heading"><i class="fas fa-compass"></i> Quick Browse</h4>
        <ul class="ak-sidebar-links">
          <li><a href="<?=$websiteUrl?>/popular"><i class="fas fa-fire-alt"></i> Most Popular</a></li>
          <li><a href="<?=$websiteUrl?>/new-season"><i class="fas fa-leaf"></i> New Season</a></li>
          <li><a href="<?=$websiteUrl?>/type?type=movies"><i class="fas fa-film"></i> Movies</a></li>
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
