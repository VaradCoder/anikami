<?php
require_once('./_config.php');

// ── Resolve slug from URL ─────────────────────────────────────────
$parts    = parse_url($_SERVER['REQUEST_URI'] ?? '/');
$page_url = explode('/', (string)($parts['path'] ?? ''));
$url      = preg_replace('/[^a-zA-Z0-9\-_]/', '', end($page_url));

app_track_event('anime_view', $url);

// ── Fetch anime data (direct call, with internal API fallback) ────
$getAnime = legacy_get_anime_payload($url);
if (empty($getAnime['name'])) {
    // Also try via internal API for richer data
    $apiAnime = app_api_get('/api/anime.php', ['slug' => $url]);
    if (is_array($apiAnime['data'] ?? null) && !empty($apiAnime['data']['name'])) {
        $getAnime = $apiAnime['data'];
    }
}

$getAnime = array_merge([
    'name'       => '',
    'othername'  => '',
    'synopsis'   => '',
    'imageUrl'   => '',
    'status'     => 'Unknown',
    'released'   => '',
    'type'       => 'TV',
    'genres'     => [],
    'episode_id' => [],
    'score'      => '',
    'totalEpisodes' => '',
    'studios'    => '',
], is_array($getAnime) ? $getAnime : []);

$animeName  = $getAnime['name'];
$animeCover = app_safe_image($getAnime['imageUrl'] ?? '');
$animeDesc  = $getAnime['synopsis'] ?? '';
$animeDescExcerpt = app_meta_excerpt($animeDesc, 160);
$isDub      = legacy_title_is_dub($animeName);

// ── Episodes ──────────────────────────────────────────────────────
$apiEpisodes = app_api_get('/api/episodes.php', ['slug' => $url]);
$episodelist = [];
if (is_array($apiEpisodes['data']['episodes'] ?? null)) {
    $episodelist = $apiEpisodes['data']['episodes'];
}
if (empty($episodelist)) {
    $episodelist = is_array($getAnime['episode_id']) ? $getAnime['episode_id'] : [];
}
$totalEps = count($episodelist);

// First episode for Watch Now button
$firstEpId = '';
foreach (array_slice($episodelist, 0, 1) as $ep) {
    $firstEpId = (string)($ep['episodeId'] ?? '');
}

// ── Recommendations ───────────────────────────────────────────────
$recommendations = app_recommend_anime($getAnime, 12);

// ── Watchlist check ───────────────────────────────────────────────
$viewer      = app_current_user();
$inWatchlist = false;
if ($viewer) {
    $inWatchlist = app_watchlist_has((int)$viewer['id'], $url);
}

// ── Page head vars ────────────────────────────────────────────────
$pageTitle    = 'Watch ' . htmlspecialchars($animeName, ENT_QUOTES, 'UTF-8') . ' - ' . $websiteTitle;
$pageDesc     = $animeDescExcerpt ?: "Watch $animeName online in HD quality for free on $websiteTitle.";
$pageKeywords = "$animeName, {$getAnime['othername']}, $websiteTitle, watch anime online, free anime";
$pageCanonical= "$websiteUrl/anime/$url";
$pageImage    = $animeCover;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title><?=$pageTitle?></title>
  <?php include('./_php/ak_page_head.php'); ?>
</head>
<body>
<?php include('./_php/ak_header.php'); ?>

<div id="ak-main-wrap">
<div id="ak-content">

  <!-- ── COVER / HERO ──────────────────────────────────────────── -->
  <div class="ak-detail-cover">
    <div class="ak-detail-cover-bg" style="background-image:url('<?=app_e($animeCover)?>')"></div>
    <div class="ak-detail-cover-overlay"></div>
    <div class="ak-detail-inner">

      <!-- Poster -->
      <div class="ak-detail-poster">
        <img src="<?=app_e($animeCover ?: ($websiteUrl.'/files/images/no_poster.jpg'))?>"
             alt="<?=app_e($animeName)?>"
             onerror="this.src='<?=$websiteUrl?>/files/images/no_poster.jpg'">
      </div>

      <!-- Info -->
      <div class="ak-detail-info">
        <!-- Breadcrumb -->
        <div class="ak-breadcrumb">
          <a href="<?=$websiteUrl?>/home">Home</a>
          <span class="sep"><i class="fas fa-angle-right"></i></span>
          <a href="<?=$websiteUrl?>/anime">Anime</a>
          <span class="sep"><i class="fas fa-angle-right"></i></span>
          <span class="current"><?=app_e($animeName)?></span>
        </div>

        <h1 class="ak-detail-title"><?=app_e($animeName)?></h1>

        <div class="ak-detail-badges">
          <span class="ak-badge quality">HD</span>
          <?php if ($isDub): ?>
            <span class="ak-badge dub">DUB</span>
          <?php else: ?>
            <span class="ak-badge sub">SUB</span>
          <?php endif; ?>
          <?php if ($getAnime['type']): ?>
            <span class="ak-badge type"><?=app_e($getAnime['type'])?></span>
          <?php endif; ?>
          <?php if ($getAnime['released']): ?>
            <span class="ak-badge year"><?=app_e($getAnime['released'])?></span>
          <?php endif; ?>
          <?php
            $statusClass = strtolower($getAnime['status']) === 'currently airing' || strtolower($getAnime['status']) === 'airing' ? 'status-airing' : 'status-completed';
          ?>
          <?php if ($getAnime['status'] && $getAnime['status'] !== 'Unknown'): ?>
            <span class="ak-badge <?=$statusClass?>"><?=app_e($getAnime['status'])?></span>
          <?php endif; ?>
          <?php if ($totalEps > 0): ?>
            <span class="ak-badge" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2)"><?=$totalEps?> Eps</span>
          <?php endif; ?>
        </div>

        <?php if ($animeDesc): ?>
        <p class="ak-detail-desc" id="akDetailDesc"><?=app_e($animeDesc)?></p>
        <button class="ak-btn-expand" id="akExpandDesc"><i class="fas fa-chevron-down"></i> Read More</button>
        <?php endif; ?>

        <div class="ak-detail-actions" style="margin-top:16px">
          <?php if ($firstEpId): ?>
          <a href="<?=$websiteUrl?>/watch/<?=app_e($firstEpId)?>" class="ak-btn-watch">
            <i class="fas fa-play"></i> Watch Now
          </a>
          <?php endif; ?>
          <?php if ($viewer): ?>
          <button class="ak-btn-bookmark <?=$inWatchlist?'active':''?>" id="akWatchlistBtn" data-anime="<?=app_e($url)?>">
            <i class="fas fa-bookmark"></i>
            <?=$inWatchlist ? 'In Watchlist' : 'Add to Watchlist'?>
          </button>
          <?php else: ?>
          <a href="<?=$websiteUrl?>/login.php?next=<?=urlencode($websiteUrl.'/anime/'.$url)?>" class="ak-btn-bookmark">
            <i class="fas fa-bookmark"></i> Add to Watchlist
          </a>
          <?php endif; ?>
          <a href="<?=$websiteUrl?>/anime" class="ak-btn-info" style="margin-left:4px">
            <i class="fas fa-list"></i> Browse
          </a>
        </div>
      </div>
    </div>
  </div><!-- /ak-detail-cover -->

  <!-- ── DETAIL BODY ───────────────────────────────────────────── -->
  <div class="ak-detail-body">
    <div class="ak-detail-main">

      <!-- Episodes -->
      <?php if ($totalEps > 0): ?>
      <div class="ak-eps-section">
        <div class="ak-eps-header">
          <div class="ak-eps-title"><i class="fas fa-list"></i> Episodes</div>
          <span class="ak-eps-count"><?=$totalEps?> total</span>
        </div>
        <div class="ak-eps-grid" id="akEpsGrid">
          <?php
          $showEps = min($totalEps, 60);
          foreach (array_slice($episodelist, 0, $showEps) as $ep):
            $epId  = app_e($ep['episodeId'] ?? '');
            $epNum = app_e((string)($ep['episodeNum'] ?? ($ep['number'] ?? '?')));
            $epTitle = app_e($ep['title'] ?? ('Episode ' . $epNum));
            $isFiller = !empty($ep['filler']);
          ?>
          <a class="ak-ep-btn <?=$isFiller?'filler':''?>" href="<?=$websiteUrl?>/watch/<?=$epId?>" title="<?=$epTitle?>">
            <?=$epNum?>
            <?php if ($isFiller): ?><span style="font-size:8px;opacity:.7"> F</span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if ($totalEps > 60): ?>
        <button class="ak-eps-show-all" id="akShowAllEps" data-total="<?=$totalEps?>" data-shown="60">
          <i class="fas fa-angle-down"></i> Show All <?=$totalEps?> Episodes
        </button>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Info table -->
      <div class="ak-meta-table">
        <div class="ak-meta-row"><span class="ak-meta-label">Japanese:</span><span class="ak-meta-value"><?=app_e($getAnime['othername'] ?: '—')?></span></div>
        <div class="ak-meta-row"><span class="ak-meta-label">Status:</span><span class="ak-meta-value"><a href="<?=$websiteUrl?>/status/<?=strtolower($getAnime['status']) === 'currently airing' ? 'ongoing' : 'completed'?>"><?=app_e($getAnime['status'])?></a></span></div>
        <div class="ak-meta-row"><span class="ak-meta-label">Type:</span><span class="ak-meta-value"><a href="<?=$websiteUrl?>/type/<?=app_e(strtolower($getAnime['type']))?>"><?=app_e($getAnime['type'] ?: '—')?></a></span></div>
        <div class="ak-meta-row"><span class="ak-meta-label">Released:</span><span class="ak-meta-value"><?=app_e($getAnime['released'] ?: '—')?></span></div>
        <div class="ak-meta-row"><span class="ak-meta-label">Episodes:</span><span class="ak-meta-value"><?=$totalEps ?: '—'?></span></div>
        <?php if (!empty($getAnime['score'])): ?>
        <div class="ak-meta-row"><span class="ak-meta-label">Score:</span><span class="ak-meta-value"><i class="fas fa-star" style="color:#fbbf24;margin-right:4px"></i><?=app_e($getAnime['score'])?></span></div>
        <?php endif; ?>
        <?php if (!empty($getAnime['studios'])): ?>
        <div class="ak-meta-row"><span class="ak-meta-label">Studio:</span><span class="ak-meta-value"><?=app_e(is_array($getAnime['studios']) ? implode(', ', $getAnime['studios']) : $getAnime['studios'])?></span></div>
        <?php endif; ?>
        <?php if (!empty($getAnime['genres']) && is_array($getAnime['genres'])): ?>
        <div class="ak-meta-row">
          <span class="ak-meta-label">Genres:</span>
          <span class="ak-meta-value" style="display:flex;flex-wrap:wrap;gap:5px">
            <?php foreach ($getAnime['genres'] as $g):
              $gName = is_array($g) ? ($g['name'] ?? $g[0] ?? '') : (string)$g;
              $gSlug = strtolower(str_replace(' ', '-', $gName));
            ?>
            <a href="<?=$websiteUrl?>/genre/<?=app_e($gSlug)?>" style="padding:2px 8px;border-radius:4px;background:rgba(200,16,46,.15);border:1px solid rgba(200,16,46,.25);color:var(--accent);font-size:11px"><?=app_e($gName)?></a>
            <?php endforeach; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Recommendations -->
      <?php if (!empty($recommendations)): ?>
      <div class="ak-section" style="padding:0 0 0">
        <div class="ak-section-head">
          <div class="ak-section-title"><i class="fas fa-thumbs-up"></i> You May Also Like</div>
        </div>
        <div class="ak-anime-grid">
          <?php foreach ($recommendations as $rec):
            $rId    = app_e($rec['animeId'] ?? '');
            $rTitle = app_e($rec['animeTitle'] ?? 'Unknown');
            $rImg   = app_e(app_safe_image($rec['animeImg'] ?? ''));
            $rYear  = app_e($rec['releasedDate'] ?? '');
            if (!$rId) continue;
          ?>
          <a class="ak-anime-card" href="<?=$websiteUrl?>/anime/<?=$rId?>" title="<?=$rTitle?>">
            <div class="ak-anime-poster">
              <img src="<?=$rImg ?: ($websiteUrl.'/files/images/no_poster.jpg')?>" alt="<?=$rTitle?>" loading="lazy"
                   onerror="this.src='<?=$websiteUrl?>/files/images/no_poster.jpg'">
              <div class="ak-anime-play"><i class="fas fa-play"></i></div>
              <?php if ($rYear): ?><span class="ak-badge-year"><?=$rYear?></span><?php endif; ?>
              <span class="ak-badge-sub">SUB</span>
            </div>
            <div class="ak-anime-info">
              <div class="ak-anime-title"><?=$rTitle?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div><!-- /ak-detail-main -->

    <!-- Right aside: genre + related -->
    <div class="ak-detail-aside">
      <div class="ak-sidebar-section">
        <div class="ak-sidebar-title"><i class="fas fa-tags"></i> Genres</div>
        <div class="ak-genre-grid">
          <?php
          $sideGenres = ['Action','Adventure','Comedy','Drama','Fantasy','Magic','Mecha','Music','Mystery','Romance','School','Sci-Fi','Shounen','Slice of Life','Sports','Supernatural'];
          foreach ($sideGenres as $sg): ?>
          <a href="<?=$websiteUrl?>/genre/<?=app_e(strtolower(str_replace(' ','-',$sg)))?>" class="ak-genre-tag"><?=app_e($sg)?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="ak-sidebar-section">
        <div class="ak-sidebar-title"><i class="fas fa-link"></i> Quick Links</div>
        <div class="ak-ql-list">
          <a class="ak-ql-item" href="<?=$websiteUrl?>/home"><i class="fas fa-home"></i> Home</a>
          <a class="ak-ql-item" href="<?=$websiteUrl?>/popular"><i class="fas fa-fire"></i> Popular</a>
          <a class="ak-ql-item" href="<?=$websiteUrl?>/new-season"><i class="fas fa-star"></i> New Season</a>
          <a class="ak-ql-item" href="<?=$websiteUrl?>/type/movies"><i class="fas fa-film"></i> Movies</a>
          <a class="ak-ql-item" href="<?=$websiteUrl?>/random" rel="nofollow"><i class="fas fa-random"></i> Random</a>
        </div>
      </div>
    </div>
  </div><!-- /ak-detail-body -->

  <?php
  $reviewsAnimeId = $url;
  include('./_php/ak_reviews.php');

  $commentsAnimeId = $url;
  $commentsEpisode = null;
  include('./_php/ak_comments.php');
  ?>

</div><!-- /#ak-content -->
</div><!-- /#ak-main-wrap -->

<?php include('./_php/ak_footer.php'); ?>

<script>
(function(){
  // Expand synopsis
  var descEl  = document.getElementById('akDetailDesc');
  var expandBtn = document.getElementById('akExpandDesc');
  if (descEl && expandBtn) {
    expandBtn.addEventListener('click', function() {
      descEl.classList.toggle('expanded');
      expandBtn.innerHTML = descEl.classList.contains('expanded')
        ? '<i class="fas fa-chevron-up"></i> Show Less'
        : '<i class="fas fa-chevron-down"></i> Read More';
    });
  }

  // Show all episodes
  var showAllBtn = document.getElementById('akShowAllEps');
  if (showAllBtn) {
    showAllBtn.addEventListener('click', function() {
      var grid   = document.getElementById('akEpsGrid');
      var total  = parseInt(showAllBtn.dataset.total);
      var shown  = parseInt(showAllBtn.dataset.shown);
      if (shown >= total) return;
      var allEps = <?=json_encode(array_map(function($e) use ($websiteUrl, $url) {
        return [
          'id'     => $e['episodeId'] ?? ($url . '-episode-' . ($e['episodeNum'] ?? 1)),
          'num'    => (string)($e['episodeNum'] ?? $e['number'] ?? '?'),
          'title'  => $e['title'] ?? '',
          'filler' => !empty($e['filler']),
        ];
      }, $episodelist), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
      var wUrl = '<?=$websiteUrl?>';
      grid.innerHTML = '';
      allEps.forEach(function(ep) {
        var a = document.createElement('a');
        a.className = 'ak-ep-btn' + (ep.filler ? ' filler' : '');
        a.href = wUrl + '/watch/' + encodeURIComponent(ep.id);
        a.title = ep.title || ('Episode ' + ep.num);
        a.textContent = ep.num;
        grid.appendChild(a);
      });
      grid.style.maxHeight = 'none';
      showAllBtn.style.display = 'none';
    });
  }

  // Watchlist toggle
  var wlBtn = document.getElementById('akWatchlistBtn');
  if (wlBtn) {
    wlBtn.addEventListener('click', function() {
      var csrf = '<?=app_e(app_csrf_token())?>';
      fetch('<?=$websiteUrl?>/api/user.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'watchlist_toggle', csrf:csrf, anime_id:'<?=app_e($url)?>'}).toString()
      }).then(function(r){return r.json();}).then(function(data){
        if (!data.ok) return;
        if (data.in_watchlist) {
          wlBtn.classList.add('active');
          wlBtn.innerHTML = '<i class="fas fa-bookmark"></i> In Watchlist';
        } else {
          wlBtn.classList.remove('active');
          wlBtn.innerHTML = '<i class="fas fa-bookmark"></i> Add to Watchlist';
        }
      }).catch(function(){});
    });
  }
})();
</script>
</body>
</html>
