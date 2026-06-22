<?php
require('../_config.php');
require_once __DIR__ . '/../services/AnimeService.php';

$parts    = parse_url($_SERVER['REQUEST_URI']);
$page_url = explode('/', $parts['path']);
$letter   = strtoupper(trim((string)($page_url[count($page_url) - 1] ?? 'A')));
$letter   = preg_match('/^[A-Z0-9]$/', $letter) ? $letter : 'A';
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Fetch from Jikan
$cacheKey = 'az_' . $letter . '_page:' . $page;
$cached   = getCache($cacheKey);
if ($cached && is_array($cached)) {
    $items    = $cached['items']    ?? [];
    $lastPage = $cached['lastPage'] ?? 1;
} else {
    $resp  = (new AnimeService())->getByLetter($letter, $page, 24);
    $items = [];
    foreach (($resp['data'] ?? []) as $row) {
        $items[] = legacy_normalize_item($row);
    }
    $lastPage = (int)($resp['pagination']['last_visible_page'] ?? 1);
    setCache($cacheKey, ['items' => $items, 'lastPage' => $lastPage], 3600);
}
$paginationHtml = legacy_pagination_html($page, $lastPage, ['letter' => $letter]);

$pageTitle    = 'Anime List — ' . $letter . ' — ' . $websiteTitle;
$pageDesc     = 'Browse anime starting with ' . $letter . ' on ' . $websiteTitle . '. Full anime directory.';
$pageCanonical = $websiteUrl . '/az/' . $letter . ($page > 1 ? '?page=' . $page : '');

$alphabet = array_merge(range('A', 'Z'), ['0-9']);
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
        <a href="<?=$websiteUrl?>/az/A">A-Z List</a>
        <span class="sep">/</span>
        <span class="current"><?=app_e($letter)?></span>
      </nav>

      <div class="ak-catalog-header" style="flex-direction:column;align-items:flex-start;gap:12px;">
        <h1 class="ak-catalog-title"><i class="fas fa-sort-alpha-down"></i> Anime List — <?=app_e($letter)?></h1>

        <!-- A-Z nav -->
        <div class="ak-az-nav">
          <?php foreach ($alphabet as $l):
            $href = $l === '0-9' ? $websiteUrl . '/az/0' : $websiteUrl . '/az/' . $l;
            $active = ($l === $letter || ($l === '0-9' && ctype_digit($letter)));
          ?>
          <a href="<?=$href?>" class="ak-az-btn<?= $active ? ' active' : '' ?>"><?=$l?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!empty($items)): ?>
      <div class="ak-azcat-list">
        <?php foreach ($items as $idx => $item):
          $name  = (string)($item['animeTitle'] ?? 'Unknown');
          $aid   = (string)($item['animeId']    ?? '');
          $isDub = legacy_title_is_dub($name);
          if ($aid === '') continue;
          $num   = (($page - 1) * 24) + $idx + 1;
        ?>
        <div class="ak-az-item">
          <span class="ak-az-num"><?=$num?></span>
          <a href="<?=$websiteUrl?>/anime/<?=app_e($aid)?>" class="ak-az-title"><?=app_e($name)?></a>
          <span class="ak-card-badge ak-badge-<?=$isDub ? 'dub' : 'sub'?>" style="font-size:10px;padding:2px 6px;"><?=$isDub ? 'DUB' : 'SUB'?></span>
        </div>
        <?php endforeach; ?>
      </div><!-- /.ak-azcat-list -->

      <?php if ($lastPage > 1): ?>
      <div class="ak-pagination-wrap">
        <ul><?=$paginationHtml?></ul>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="ak-search-empty">
        <i class="fas fa-ghost"></i>
        <h3>No anime found for &ldquo;<?=app_e($letter)?>&rdquo;</h3>
        <p>Try another letter.</p>
      </div>
      <?php endif; ?>

    </main>

    <!-- SIDEBAR -->
    <aside class="ak-catalog-sidebar">
      <div class="ak-sidebar-box">
        <h4 class="ak-sidebar-heading"><i class="fas fa-sort-alpha-down"></i> Jump to Letter</h4>
        <div class="ak-az-sidebar-grid">
          <?php foreach ($alphabet as $l):
            $href = $l === '0-9' ? $websiteUrl . '/az/0' : $websiteUrl . '/az/' . $l;
            $active = ($l === $letter || ($l === '0-9' && ctype_digit($letter)));
          ?>
          <a href="<?=$href?>" class="ak-az-sidebar-btn<?= $active ? ' active' : '' ?>"><?=$l?></a>
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
        </ul>
      </div>
    </aside>

  </div>
</div>

<?php include('../_php/ak_footer.php'); ?>
</body>
</html>
