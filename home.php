<?php
require('./_config.php');
require_once __DIR__ . '/services/AnimeService.php';

/* =====================================================================
   HOME PAGE — Anikami HiAnime.to-style UI
   All anime data goes through AnimeService — no page-level Jikan calls.
   ===================================================================== */

// ── 1. Fetch data ──────────────────────────────────────────────────────
// Cache the merged home payload so sub-requests share it.
$_homeCacheKey = 'home_page_v2:1';
$_homeCached   = getCache($_homeCacheKey);

if (is_array($_homeCached)) {
    $airingItems  = $_homeCached['airing']  ?? [];
    $popularItems = $_homeCached['popular'] ?? [];
    $seasonItems  = $_homeCached['season']  ?? [];
} else {
    $_animeService = new AnimeService();
    $_homeLists  = $_animeService->getHomeLists(15, 12, 12);
    $airingResp  = $_homeLists['airing'];
    $popularResp = $_homeLists['popular'];
    $seasonResp  = $_homeLists['seasonal'];

    $normalizeList = static function(array $resp): array {
        $items = is_array($resp['data'] ?? null) ? $resp['data'] : [];
        $out   = [];
        foreach ($items as $row) {
            $out[] = legacy_normalize_item($row);
        }
        return $out;
    };

    $airingItems  = $normalizeList($airingResp);
    $popularItems = $normalizeList($popularResp);
    $seasonItems  = $normalizeList($seasonResp);

    setCache($_homeCacheKey, [
        'airing'  => $airingItems,
        'popular' => $popularItems,
        'season'  => $seasonItems,
    ], 600);
}

// ── 2. Trending — from site DB, supplemented by cached Jikan data ──
// app_trending_anime returns [{anime_id, score}, ...] — no images/titles.
// We resolve titles/images from our already-fetched lists before falling
// back to legacy_get_anime_payload() (which is also cached).
$_trendingDb = app_trending_anime('day', 10);
$trendingRows = [];
if (!empty($_trendingDb)) {
    // Build lookup maps from already-fetched API data
    $_lookupMap = [];
    foreach (array_merge($airingItems, $popularItems, $seasonItems) as $_a) {
        if (!empty($_a['animeId'])) {
            $_lookupMap[$_a['animeId']] = $_a;
        }
    }
    foreach ($_trendingDb as $_t) {
        $_aid = (string)($_t['anime_id'] ?? '');
        if ($_aid === '') continue;
        if (isset($_lookupMap[$_aid])) {
            $trendingRows[] = $_lookupMap[$_aid];
        } else {
            // Cache-backed payload lookup — no extra HTTP calls
            $_p = legacy_get_anime_payload($_aid);
            if (!empty($_p['name'])) {
                $trendingRows[] = [
                    'animeId'     => $_aid,
                    'animeTitle'  => $_p['name'],
                    'animeImg'    => $_p['imageUrl'] ?? '',
                    'imgUrl'      => $_p['imageUrl'] ?? '',
                    'type'        => $_p['type'] ?? 'TV',
                    'releasedDate'=> $_p['released'] ?? '',
                    'status'      => $_p['status'] ?? '',
                ];
            }
        }
    }
}
// Fall back to top airing if site has no view history yet
if (empty($trendingRows)) {
    $trendingRows = array_slice($airingItems, 0, 10);
}

// ── 3. Hero slides (first 5 of airing) ───────────────────────────────
$heroSlides = array_slice($airingItems, 0, 5);

// ── 4. Sections ───────────────────────────────────────────────────────
$topAiringSidebar = array_slice($airingItems, 0, 10);
$recentlyAdded    = array_slice($seasonItems,  0, 12);
$newSeasonItems   = array_slice($seasonItems,  0, 10);
$popularSection   = array_slice($popularItems, 0, 12);

// ── 5. Continue Watching (logged-in users) ───────────────────────────
$continueCards = [];
$homeUser = app_current_user();
if ($homeUser) {
    $cwRows = app_continue_watching_list((int)$homeUser['id'], 6);
    foreach ($cwRows as $row) {
        $aid = (string)($row['anime_id'] ?? '');
        if ($aid === '') continue;
        $payload = legacy_get_anime_payload($aid);
        $continueCards[] = [
            'animeId'  => $aid,
            'title'    => $payload['name']    ?? legacy_unslug($aid),
            'image'    => app_safe_image($payload['imageUrl'] ?? ''),
            'episode'  => (int)($row['episode'] ?? 1),
            'position' => (int)($row['position_seconds'] ?? 0),
            'duration' => (int)($row['duration_seconds']  ?? 1440),
        ];
    }
}

// ── 6. Admin featured ────────────────────────────────────────────────
$featuredRows = app_featured_list(8);

// ── 7. Genres list ───────────────────────────────────────────────────
$genres = [
    'Action','Adventure','Comedy','Drama','Ecchi','Fantasy','Horror',
    'Magic','Mecha','Music','Mystery','Romance','School','Sci-Fi',
    'Seinen','Shoujo','Shounen','Slice of Life','Sports','Supernatural','Thriller',
];

// Helper: render a single anime card
function ak_card(array $item, string $websiteUrl, string $fallback): string {
    $animeId = app_e($item['animeId'] ?? '');
    $title   = app_e($item['animeTitle'] ?? ($item['title'] ?? 'Unknown'));
    $img     = app_e(app_safe_image($item['animeImg'] ?? ($item['image'] ?? '')));
    $year    = app_e($item['releasedDate'] ?? '');
    $type    = app_e($item['type'] ?? 'TV');
    $isDub   = stripos($item['animeTitle'] ?? '', '(Dub)') !== false;
    $badge   = $isDub ? '<span class="ak-badge-dub">DUB</span>' : '<span class="ak-badge-sub">SUB</span>';
    $href    = $websiteUrl . '/anime/' . $animeId;
    $imgSrc  = $img ?: $fallback;
    return '<a class="ak-anime-card" href="' . $href . '" title="' . $title . '">'
         .   '<div class="ak-anime-poster">'
         .     '<img src="' . $imgSrc . '" alt="' . $title . '" loading="lazy" onerror="this.src=\'' . $fallback . '\'">'
         .     '<div class="ak-anime-play"><i class="fas fa-play"></i></div>'
         .     ($year ? '<span class="ak-badge-year">' . $year . '</span>' : '')
         .     $badge
         .   '</div>'
         .   '<div class="ak-anime-info">'
         .     '<div class="ak-anime-title">' . $title . '</div>'
         .     '<div class="ak-anime-meta"><span class="type">' . $type . '</span></div>'
         .   '</div>'
         . '</a>';
}

$_fallbackImg = $websiteUrl . '/files/images/no_poster.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=$websiteTitle?> — Watch Anime Online Free in HD</title>
  <meta name="description" content="Watch anime online in high quality for free on <?=$websiteTitle?>. Latest subbed and dubbed anime episodes, no ads.">
  <meta name="keywords" content="watch anime online, free anime, <?=$websiteTitle?>, anime hd, anime stream, english sub">
  <meta name="robots" content="index, follow">
  <meta property="og:title" content="<?=$websiteTitle?> — Watch Anime Online Free in HD">
  <meta property="og:description" content="Watch anime online in high quality for free. Latest subbed and dubbed anime episodes.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?=$websiteUrl?>/home">
  <meta property="og:image" content="<?=$banner?>">
  <meta name="theme-color" content="#07060d">
  <link rel="shortcut icon" href="<?=$websiteUrl?>/favicon.ico?v=<?=$version?>" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?=$websiteUrl?>/files/css/anikami.css?v=<?=$version?>">
  <script>
    // Register service worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/anikatsu/sw.js', {scope: '/anikatsu/'})
        .catch(function(){});
    }
  </script>
</head>
<body>

<?php include('./_php/ak_header.php'); ?>

<!-- ============================  MAIN WRAP  ============================ -->
<div id="ak-main-wrap">
<div id="ak-content">

  <!-- ── HERO SLIDER ──────────────────────────────────────────────── -->
  <div class="ak-hero" id="akHero">
    <?php foreach ($heroSlides as $i => $slide):
      $sTitle = app_e($slide['animeTitle'] ?? 'Unknown');
      $sImg   = app_e(app_safe_image($slide['animeImg'] ?? ''));
      $sId    = app_e($slide['animeId'] ?? '');
      $sYear  = app_e($slide['releasedDate'] ?? '');
      $sType  = app_e($slide['type'] ?? 'TV');
      $sDesc  = ''; // No synopsis in list endpoint — intentional
    ?>
    <div class="ak-hero-slide <?=$i===0?'active':''?>" data-index="<?=$i?>">
      <div class="ak-hero-bg" style="background-image:url('<?=$sImg ?: ($websiteUrl.'/files/images/banner.png')?>')"></div>
      <div class="ak-hero-overlay"></div>
      <div class="ak-hero-content">
        <div class="ak-hero-title"><?=$sTitle?></div>
        <div class="ak-hero-tags">
          <?php if ($sType): ?><span class="ak-hero-tag"><?=$sType?></span><?php endif; ?>
          <?php if ($sYear): ?><span class="ak-hero-tag red"><?=$sYear?></span><?php endif; ?>
          <span class="ak-hero-tag blue">HD</span>
          <span class="ak-hero-tag">SUB</span>
        </div>
        <div class="ak-hero-actions">
          <?php if ($sId): ?>
          <a href="<?=$websiteUrl?>/watch/<?=$sId?>-episode-1" class="ak-btn-watch">
            <i class="fas fa-play"></i> Watch Now
          </a>
          <a href="<?=$websiteUrl?>/anime/<?=$sId?>" class="ak-btn-info">
            <i class="fas fa-info-circle"></i> More Info
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($heroSlides) < 1): ?>
    <!-- Fallback hero if API is down -->
    <div class="ak-hero-slide active">
      <div class="ak-hero-bg" style="background-image:url('<?=$banner?>')"></div>
      <div class="ak-hero-overlay"></div>
      <div class="ak-hero-content">
        <div class="ak-hero-title"><?=$websiteTitle?></div>
        <div class="ak-hero-tags"><span class="ak-hero-tag blue">HD</span><span class="ak-hero-tag">FREE</span></div>
        <div class="ak-hero-actions">
          <a href="<?=$websiteUrl?>/anime" class="ak-btn-watch"><i class="fas fa-play"></i> Browse Anime</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Controls -->
    <?php if (count($heroSlides) > 1): ?>
    <button class="ak-hero-arrow left" id="akHeroPrev" aria-label="Previous">
      <i class="fas fa-angle-left"></i>
    </button>
    <button class="ak-hero-arrow right" id="akHeroNext" aria-label="Next">
      <i class="fas fa-angle-right"></i>
    </button>
    <div class="ak-hero-dots" id="akHeroDots">
      <?php for ($d = 0; $d < count($heroSlides); $d++): ?>
      <div class="ak-hero-dot <?=$d===0?'active':''?>" data-index="<?=$d?>"></div>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div><!-- /ak-hero -->

  <!-- ── QUICK NAV ─────────────────────────────────────────────────── -->
  <div class="ak-quick-nav">
    <div class="ak-quick-nav-inner">
      <a href="<?=$websiteUrl?>/anime" class="ak-qn-item">
        <div class="ak-qn-icon orange"><i class="fas fa-list-ul"></i></div>
        <div><div class="ak-qn-title">Anime List</div><div class="ak-qn-sub">Browse all anime</div></div>
      </a>
      <a href="<?=$websiteUrl?>/popular" class="ak-qn-item">
        <div class="ak-qn-icon blue"><i class="fas fa-star"></i></div>
        <div><div class="ak-qn-title">Popular Anime</div><div class="ak-qn-sub">Top anime today</div></div>
      </a>
      <a href="<?=$websiteUrl?>/random" class="ak-qn-item" rel="nofollow">
        <div class="ak-qn-icon green"><i class="fas fa-random"></i></div>
        <div><div class="ak-qn-title">Random Anime</div><div class="ak-qn-sub">Surprise me!</div></div>
      </a>
      <a href="<?=$websiteUrl?>/new-season" class="ak-qn-item">
        <div class="ak-qn-icon pink"><i class="fas fa-rocket"></i></div>
        <div><div class="ak-qn-title">New Season</div><div class="ak-qn-sub">Latest releases</div></div>
      </a>
    </div>
  </div>

  <!-- ── COMMUNITY BANNER ─────────────────────────────────────────── -->
  <div class="ak-community-banner">
    <div class="ak-cb-text">
      <div class="ak-cb-title">Join our community!</div>
      <div class="ak-cb-sub">Chat, share and stay updated with anime releases &amp; news</div>
    </div>
    <div class="ak-cb-actions">
      <a href="<?=htmlspecialchars($discord)?>" target="_blank" rel="noopener" class="ak-cb-btn discord"><i class="fab fa-discord"></i> Discord</a>
      <a href="<?=htmlspecialchars($telegram)?>" target="_blank" rel="noopener" class="ak-cb-btn telegram"><i class="fab fa-telegram"></i> Telegram</a>
      <a href="<?=htmlspecialchars($redit)?>" target="_blank" rel="noopener" class="ak-cb-btn reddit"><i class="fab fa-reddit-alien"></i> Reddit</a>
    </div>
  </div>

  <!-- ── TRENDING NOW ───────────────────────────────────────────────── -->
  <div class="ak-section">
    <div class="ak-section-head">
      <div class="ak-section-title"><i class="fas fa-fire"></i> Trending Now</div>
      <div style="display:flex;align-items:center;gap:8px">
        <div class="ak-section-nav">
          <button class="ak-snav-btn" id="trendPrev" aria-label="Previous"><i class="fas fa-angle-left"></i></button>
          <button class="ak-snav-btn" id="trendNext" aria-label="Next"><i class="fas fa-angle-right"></i></button>
        </div>
        <a href="<?=$websiteUrl?>/popular" class="ak-view-all">View All <i class="fas fa-angle-right"></i></a>
      </div>
    </div>
    <div class="ak-trending-wrap" id="akTrendingWrap">
      <div class="ak-trending-row">
        <?php foreach ($trendingRows as $i => $item):
          $tid  = app_e($item['animeId'] ?? ($item['anime_id'] ?? ''));
          $ttitle = app_e($item['animeTitle'] ?? ($item['title'] ?? ($item['name'] ?? 'Unknown')));
          $timg   = app_e(app_safe_image($item['animeImg'] ?? ($item['image'] ?? ($item['imageUrl'] ?? ''))));
          $ttype  = app_e($item['type'] ?? 'TV');
          $tnum   = $i + 1;
          $isTop3 = $tnum <= 3;
        ?>
        <a href="<?=$websiteUrl?>/anime/<?=$tid?>" class="ak-trending-card" title="<?=$ttitle?>">
          <div class="ak-trending-poster">
            <div class="ak-trending-num <?=$isTop3?'top3':''?>"><?=$tnum?></div>
            <img src="<?=$timg ?: $_fallbackImg?>" alt="<?=$ttitle?>" loading="<?=$i<4?'eager':'lazy'?>"
                 onerror="this.src='<?=$_fallbackImg?>'">
          </div>
          <div class="ak-trending-title"><?=$ttitle?></div>
          <div class="ak-trending-meta"><i class="fas fa-tv"></i><?=$ttype?></div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($trendingRows)): ?>
        <div class="ak-empty"><i class="fas fa-spinner fa-spin"></i> Loading trending anime…</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── ADMIN FEATURED (if any) ──────────────────────────────────── -->
  <?php if (!empty($featuredRows)): ?>
  <div class="ak-section">
    <div class="ak-section-head">
      <div class="ak-section-title"><i class="fas fa-crown" style="color:#f59e0b"></i> Featured</div>
      <?php if ($homeUser && ($homeUser['role'] ?? '') === 'admin'): ?>
      <a href="<?=$websiteUrl?>/admin/index.php" class="ak-view-all">Manage <i class="fas fa-angle-right"></i></a>
      <?php endif; ?>
    </div>
    <div class="ak-anime-grid">
      <?php foreach ($featuredRows as $f):
        $fid  = app_e($f['anime_id'] ?? '');
        $ftitle = app_e($f['title'] ?? $fid);
        $fimg   = app_e($f['image_url'] ?? '');
        if (!$fid) continue;
      ?>
      <a class="ak-anime-card" href="<?=$websiteUrl?>/anime/<?=$fid?>" title="<?=$ftitle?>">
        <div class="ak-anime-poster">
          <img src="<?=$fimg ?: $_fallbackImg?>" alt="<?=$ftitle?>" loading="lazy" onerror="this.src='<?=$_fallbackImg?>'">
          <div class="ak-anime-play"><i class="fas fa-play"></i></div>
          <span class="ak-badge-sub">SUB</span>
        </div>
        <div class="ak-anime-info">
          <div class="ak-anime-title"><?=$ftitle?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── CONTINUE WATCHING ────────────────────────────────────────── -->
  <?php if (!empty($continueCards)): ?>
  <div class="ak-section">
    <div class="ak-section-head">
      <div class="ak-section-title"><i class="fas fa-play" style="color:#ef4444"></i> Continue Watching</div>
      <a href="<?=$websiteUrl?>/profile.php" class="ak-view-all">View All <i class="fas fa-angle-right"></i></a>
    </div>
    <div class="ak-cw-grid">
      <?php foreach ($continueCards as $cw):
        $cwId    = app_e($cw['animeId']);
        $cwTitle = app_e($cw['title']);
        $cwImg   = app_e($cw['image'] ?: $_fallbackImg);
        $cwEp    = (int)$cw['episode'];
        $cwDur   = max(1, (int)$cw['duration']);
        $cwPos   = (int)$cw['position'];
        $cwPct   = min(100, (int)round($cwPos / $cwDur * 100));
      ?>
      <a class="ak-cw-card" href="<?=$websiteUrl?>/watch/<?=$cwId?>-episode-<?=$cwEp?>">
        <div class="ak-cw-thumb">
          <img src="<?=$cwImg?>" alt="<?=$cwTitle?>" loading="lazy" onerror="this.src='<?=$_fallbackImg?>'">
          <div class="ak-cw-overlay"><i class="fas fa-play"></i></div>
          <div class="ak-cw-bar"><div class="ak-cw-bar-fill" style="width:<?=$cwPct?>%"></div></div>
        </div>
        <div class="ak-cw-info">
          <div class="ak-cw-title"><?=$cwTitle?></div>
          <div class="ak-cw-ep">Episode <?=$cwEp?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── RECENTLY ADDED (new season) ──────────────────────────────── -->
  <div class="ak-section">
    <div class="ak-section-head">
      <div class="ak-section-title"><i class="fas fa-bolt" style="color:#f59e0b"></i> Recently Added</div>
      <a href="<?=$websiteUrl?>/new-season" class="ak-view-all">View All <i class="fas fa-angle-right"></i></a>
    </div>
    <div class="ak-anime-grid">
      <?php foreach ($recentlyAdded as $item): echo ak_card($item, $websiteUrl, $_fallbackImg); endforeach; ?>
      <?php if (empty($recentlyAdded)): ?>
      <div class="ak-no-results"><i class="fas fa-satellite-dish"></i>Could not load anime. Please try again.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── NEW SEASON ─────────────────────────────────────────────────── -->
  <div class="ak-section">
    <div class="ak-section-head">
      <div class="ak-section-title"><i class="fas fa-star" style="color:#fbbf24"></i> New Season</div>
      <a href="<?=$websiteUrl?>/new-season" class="ak-view-all">View All <i class="fas fa-angle-right"></i></a>
    </div>
    <div class="ak-anime-grid">
      <?php foreach ($newSeasonItems as $item): echo ak_card($item, $websiteUrl, $_fallbackImg); endforeach; ?>
    </div>
  </div>

  <!-- ── POPULAR ANIME ─────────────────────────────────────────────── -->
  <div class="ak-section">
    <div class="ak-section-head">
      <div class="ak-section-title"><i class="fas fa-crown" style="color:#f59e0b"></i> Popular Anime</div>
      <a href="<?=$websiteUrl?>/popular" class="ak-view-all">View All <i class="fas fa-angle-right"></i></a>
    </div>
    <div class="ak-anime-grid">
      <?php foreach ($popularSection as $item): echo ak_card($item, $websiteUrl, $_fallbackImg); endforeach; ?>
    </div>
  </div>

</div><!-- /#ak-content -->

<!-- ── RIGHT SIDEBAR ──────────────────────────────────────────────── -->
<div id="ak-sidebar">

  <!-- Top Airing -->
  <div class="ak-sidebar-section">
    <div class="ak-sidebar-title"><i class="fas fa-bolt"></i> Top Airing</div>
    <div class="ak-tai-list">
      <?php foreach ($topAiringSidebar as $i => $item):
        $sid    = app_e($item['animeId'] ?? '');
        $stitle = app_e($item['animeTitle'] ?? 'Unknown');
        $simg   = app_e(app_safe_image($item['animeImg'] ?? ''));
        $isTop3 = $i < 3;
      ?>
      <a class="ak-tai-item <?=$isTop3?'top3':''?>" href="<?=$websiteUrl?>/anime/<?=$sid?>">
        <div class="ak-tai-num"><?=$i+1?></div>
        <div class="ak-tai-thumb">
          <img src="<?=$simg ?: $_fallbackImg?>" alt="<?=$stitle?>" loading="lazy" onerror="this.src='<?=$_fallbackImg?>'">
        </div>
        <div class="ak-tai-info">
          <div class="ak-tai-title"><?=$stitle?></div>
          <div class="ak-tai-ep">Airing</div>
        </div>
        <span class="ak-tai-badge">SUB</span>
      </a>
      <?php endforeach; ?>
    </div>
    <a href="<?=$websiteUrl?>/schedule" class="ak-btn-schedule">View Full Schedule</a>
  </div>

  <!-- Genres -->
  <div class="ak-sidebar-section">
    <div class="ak-sidebar-title"><i class="fas fa-tags"></i> Genres</div>
    <div class="ak-genre-grid">
      <?php foreach ($genres as $g): ?>
      <a href="<?=$websiteUrl?>/genre/<?=app_e(strtolower(str_replace(' ', '-', $g)))?>" class="ak-genre-tag"><?=app_e($g)?></a>
      <?php endforeach; ?>
    </div>
    <a href="<?=$websiteUrl?>/genre" class="ak-btn-all-genres">View All Genres</a>
  </div>

  <!-- Quick Links -->
  <div class="ak-sidebar-section">
    <div class="ak-sidebar-title"><i class="fas fa-link"></i> Quick Links</div>
    <div class="ak-ql-list">
      <a class="ak-ql-item" href="<?=$websiteUrl?>/anime"><i class="fas fa-list"></i> Anime List</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/popular"><i class="fas fa-star"></i> Popular Anime</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/new-season"><i class="fas fa-rocket"></i> New Season</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/random" rel="nofollow"><i class="fas fa-random"></i> Random Anime</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/type/movies"><i class="fas fa-film"></i> Anime Movies</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/schedule"><i class="fas fa-calendar"></i> Schedule</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/status/ongoing"><i class="fas fa-circle" style="color:#10b981;font-size:9px"></i> Ongoing</a>
      <a class="ak-ql-item" href="<?=$websiteUrl?>/status/completed"><i class="fas fa-check"></i> Completed</a>
    </div>
  </div>

  <!-- Waifu Card -->
  <div class="ak-waifu-card">
    <div class="ak-waifu-title">Worship Your Waifu ❤️</div>
    <div class="ak-waifu-name">Rias Gremory</div>
    <div class="ak-waifu-sub">リアス・グレモリー</div>
    <div class="ak-hearts">
      <i class="fas fa-heart"></i>
      <i class="fas fa-heart"></i>
      <i class="fas fa-heart"></i>
    </div>
    <img class="ak-waifu-img" src="<?=$websiteUrl?>/files/images/rias-sidebar-chibi.png" alt="Rias Gremory"
         onerror="this.style.display='none'">
  </div>

</div><!-- /#ak-sidebar -->

</div><!-- /#ak-main-wrap -->

<?php include('./_php/ak_footer.php'); ?>

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
(function(){

/* ── HERO SLIDER ─────────────────────────────────────────────────── */
var slides   = document.querySelectorAll('.ak-hero-slide');
var dots     = document.querySelectorAll('.ak-hero-dot');
var prevBtn  = document.getElementById('akHeroPrev');
var nextBtn  = document.getElementById('akHeroNext');
var current  = 0;
var heroTimer;

function goTo(n) {
  slides[current].classList.remove('active');
  if (dots[current]) dots[current].classList.remove('active');
  current = (n + slides.length) % slides.length;
  slides[current].classList.add('active');
  if (dots[current]) dots[current].classList.add('active');
}

function startAuto() {
  heroTimer = setInterval(function(){ goTo(current + 1); }, 5500);
}
function stopAuto() {
  clearInterval(heroTimer);
}

if (slides.length > 1) {
  startAuto();
  if (prevBtn) prevBtn.addEventListener('click', function(){ stopAuto(); goTo(current - 1); startAuto(); });
  if (nextBtn) nextBtn.addEventListener('click', function(){ stopAuto(); goTo(current + 1); startAuto(); });
  dots.forEach(function(dot, i){
    dot.addEventListener('click', function(){ stopAuto(); goTo(i); startAuto(); });
  });
}

/* ── TRENDING DRAG SCROLL ────────────────────────────────────────── */
var tw = document.getElementById('akTrendingWrap');
if (tw) {
  var isDown = false, startX, scrollLeft;
  tw.addEventListener('mousedown', function(e){ isDown=true; startX=e.pageX-tw.offsetLeft; scrollLeft=tw.scrollLeft; });
  tw.addEventListener('mouseleave', function(){ isDown=false; });
  tw.addEventListener('mouseup',    function(){ isDown=false; });
  tw.addEventListener('mousemove',  function(e){
    if (!isDown) return; e.preventDefault();
    tw.scrollLeft = scrollLeft - (e.pageX - tw.offsetLeft - startX) * 1.4;
  });

  var prevBtn2 = document.getElementById('trendPrev');
  var nextBtn2 = document.getElementById('trendNext');
  if (prevBtn2) prevBtn2.addEventListener('click', function(){ tw.scrollBy({left:-420,behavior:'smooth'}); });
  if (nextBtn2) nextBtn2.addEventListener('click', function(){ tw.scrollBy({left:420, behavior:'smooth'}); });
}

})();
</script>

</body>
</html>
