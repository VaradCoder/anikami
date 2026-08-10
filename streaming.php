<?php
// Disable output buffering so browser gets HTML as soon as PHP starts writing
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (@ob_end_flush()) {} // flush all existing buffers
ob_implicit_flush(true);

require('./_config.php');

// ── URL parse ─────────────────────────────────────────────────────
$parts    = parse_url($_SERVER['REQUEST_URI'] ?? '/');
$page_url = explode('/', (string)($parts['path'] ?? ''));
$url      = end($page_url);

$epParts = explode('-episode-', $url);
$slug    = preg_replace('/[^a-zA-Z0-9\-_]/', '', $epParts[0] ?? '');
$epNum   = isset($epParts[1]) ? max(1, (int)$epParts[1]) : 1;

if (empty($slug)) {
    http_response_code(404);
    include('./_php/ak_header.php');
    echo '<div style="padding:120px 20px;text-align:center;color:var(--text-secondary)"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:var(--accent);display:block;margin-bottom:14px"></i><h2>Invalid Episode URL</h2><a href="'.$websiteUrl.'/home" style="color:var(--accent)">← Go Home</a></div>';
    include('./_php/ak_footer.php');
    exit;
}

app_track_event('episode_view', $slug, ['episode' => $epNum]);

// ── Load anime + episode data ─────────────────────────────────────
$watchContext  = legacy_get_watch_context($slug);
$animeData     = $watchContext['anime']     ?? [];
$episodelist   = $watchContext['episodes']  ?? [];
$sourceSlug    = $watchContext['sourceSlug'] ?? $slug;
$totalEpisodes = count($episodelist);

if (empty($animeData)) {
    $animeData = legacy_resolve_anime_by_slug($slug) ?: [];
}

// ── Build instant iframe fallbacks from already-loaded anime data ──
// NO external HTTP calls here — everything uses $animeData which is cached.
// HLS/direct streams are loaded asynchronously by JavaScript via api/watch_v2.php.
$episodeIdForApi = $slug . '-episode-' . $epNum;
$_fbMal = (int)($animeData['mal_id'] ?? 0);
$_fbAni = (int)($animeData['anilist_id'] ?? 0);

// Provider notes (verified Jun 2026):
//  • VidNest / VidNest Dub / VidLink use the ANILIST id (NOT MAL). Confirmed
//    working: HTTP 200, no X-Frame-Options/CSP frame-ancestors restriction.
//  • VidSrc.cc removed — Cloudflare returns a hard 403 AND sets
//    `X-Frame-Options: SAMEORIGIN`, blocking iframe embedding outright.
//  • 2Anime removed — 2anime.xyz domain is dead (root domain itself returns
//    HTTP 403 "The content of the page cannot be displayed").
// NOTE: if AniList resolution fails to find an anilist_id for this title,
// $serverList is empty here; api/watch_v2.php async fetch hits the same
// limitation since both need $aniId. There is currently no working
// fallback provider for that case (do not add unofficial scraping APIs).
// Built via StreamProviderManager (same source admin/streams.php and
// api/watch_v2.php read from) instead of a hardcoded copy of the 3
// providers — this file and watch_v2.php used to each hardcode their own
// list independently, which could (and did) drift out of sync with the
// admin-configured order/enabled state.
require_once __DIR__ . '/services/StreamProviderManager.php';
$serverList = [];
if ($_fbAni > 0) {
    $manager = new StreamProviderManager();
    foreach ($manager->getEpisodeServers($_fbAni, $epNum) as $s) {
        $serverList[] = [
            'name' => $s['name'], 'url' => $s['playbackUrl'], 'type' => $s['type'],
            'quality' => $s['quality'] ?? '', 'lang' => $s['lang'], 'providerId' => $s['providerId'],
            'subtitles' => $s['subtitles'],
        ];
    }
}

$streamUrl   = $serverList[0]['url'] ?? '';
$downloadUrl = $streamUrl;

// ── Prev/Next ─────────────────────────────────────────────────────
$epNums  = array_column($episodelist, 'episodeNum');
$hasPrev = in_array($epNum - 1, $epNums, true);
$hasNext = in_array($epNum + 1, $epNums, true);
$prevUrl = $hasPrev ? ($websiteUrl.'/watch/'.$slug.'-episode-'.($epNum-1)) : '';
$nextUrl2 = $hasNext ? ($websiteUrl.'/watch/'.$slug.'-episode-'.($epNum+1)) : '';

// ── Episode range grouping ──────────────────────────────────────────
// Chunk size scales with total episode count so the range selector itself
// stays navigable. Fixed 12-26 chunking meant a 1000+ episode show (One
// Piece, Detective Conan, etc.) produced 40-90+ dropdown entries — worse
// than not having a dropdown at all. These are honestly labeled "Episodes
// X-Y" ranges, not real season boundaries (we don't have real season data).
function _detect_range_size(int $total): int {
    if ($total <= 26)   return $total ?: 1;
    if ($total <= 52)   return 26;
    if ($total <= 300)  return 50;
    return 100;
}
$_seasonSize = _detect_range_size($totalEpisodes);
$seasons = [];
if ($totalEpisodes > 0) {
    $chunks = array_chunk($episodelist, $_seasonSize);
    foreach ($chunks as $_si => $_chunk) {
        $firstEp = (int)($_chunk[0]['episodeNum'] ?? 1);
        $lastEp  = (int)(end($_chunk)['episodeNum'] ?? $firstEp);
        $seasons[] = [
            'label'    => $firstEp === $lastEp ? ('Episode ' . $firstEp) : ('Episodes ' . $firstEp . '–' . $lastEp),
            'episodes' => $_chunk,
            'from'     => $firstEp,
            'to'       => $lastEp,
            'active'   => ($epNum >= $firstEp && $epNum <= $lastEp),
        ];
    }
}
// Active season index
$activeSeasonIdx = 0;
foreach ($seasons as $_si => $_s) { if ($_s['active']) { $activeSeasonIdx = $_si; break; } }
// Show only ep numbers when count > 10
$showEpTitles = $totalEpisodes <= 10;

// ── Visible episode window (max 120 around current) ───────────────
$visibleEps = $episodelist;
if ($totalEpisodes > 120) {
    $ci = array_search($epNum, $epNums, true);
    if ($ci !== false) {
        $visibleEps = array_slice($episodelist, max(0, $ci - 59), 120);
    }
}

// ── Continue watching + watchlist ─────────────────────────────────
$viewer = app_current_user();
$resumeSeconds = 0;
$watchedEpisodes = [];
if ($viewer) {
    // Read any saved position for this exact episode BEFORE writing, so a
    // fresh page load doesn't clobber the resume point with 0.
    $resumeSeconds = app_get_continue_watching_position((int)$viewer['id'], $slug, $epNum);
    app_save_continue_watching((int)$viewer['id'], $slug, $epNum, $resumeSeconds);
    $watchedEpisodes = app_get_watched_episodes((int)$viewer['id'], $slug);
    $recentWatched = app_get_recent_watched_episodes((int)$viewer['id'], $slug, 5);
}
$recentWatched = $recentWatched ?? [];
$inWatchlist = $viewer ? app_watchlist_has((int)$viewer['id'], $slug) : false;
$listStatus  = $viewer ? app_get_anime_list_status((int)$viewer['id'], $slug) : null;
$listStatusLabels = [
    'watching'      => ['label' => 'Watching',      'icon' => 'fa-eye'],
    'completed'     => ['label' => 'Completed',     'icon' => 'fa-circle-check'],
    'on_hold'       => ['label' => 'On Hold',        'icon' => 'fa-pause'],
    'dropped'       => ['label' => 'Dropped',        'icon' => 'fa-circle-xmark'],
    'plan_to_watch' => ['label' => 'Plan to Watch',  'icon' => 'fa-clock'],
];

// ── Recommendations ───────────────────────────────────────────────
$animeLike = [
    'name'   => $animeData['title'] ?? '',
    'genres' => [],
];
if (!empty($animeData['genres']) && is_array($animeData['genres'])) {
    foreach ($animeData['genres'] as $g) {
        $animeLike['genres'][] = is_array($g) ? ($g['name'] ?? '') : (string)$g;
    }
}
$recommendations = app_recommend_anime($animeLike, 10);

$animeName = (string)($animeData['title'] ?? (isset($animeData['name']) ? $animeData['name'] : legacy_unslug($slug)));
$animeImg  = app_safe_image($animeData['images']['jpg']['large_image_url'] ?? ($animeData['imageUrl'] ?? ''));
$animeType = (string)($animeData['type'] ?? 'TV');
$animeYear = (string)($animeData['year'] ?? ($animeData['released'] ?? ''));
$synopsis  = (string)($animeData['synopsis'] ?? ($animeData['description'] ?? ''));

$playerConfig = [
    'servers'  => $serverList,
    'nextUrl'  => $hasNext ? ($websiteUrl . '/watch/' . $slug . '-episode-' . ($epNum+1)) : '',
    'autoNext' => true,
];

// ── Extra data for new layout ─────────────────────────────────────
$animeScore  = (string)($animeData['score'] ?? ($animeData['rating'] ?? ''));
$animeStatus = (string)($animeData['status'] ?? '');
$animeGenres = [];
if (!empty($animeData['genres']) && is_array($animeData['genres'])) {
    foreach ($animeData['genres'] as $g) {
        $animeGenres[] = is_array($g) ? ($g['name'] ?? '') : (string)$g;
    }
}
$animeStudios = [];
if (!empty($animeData['studios']) && is_array($animeData['studios'])) {
    foreach ($animeData['studios'] as $s) {
        $animeStudios[] = is_array($s) ? ($s['name'] ?? '') : (string)$s;
    }
}
$isDubbed   = legacy_title_is_dub($animeName);
// Current episode title
$currentEpTitle = '';
foreach ($episodelist as $ep) {
    if ((int)($ep['episodeNum'] ?? 0) === (int)$epNum && !empty($ep['title'])) {
        $currentEpTitle = $ep['title'];
        break;
    }
}

$pageTitle    = 'Watch ' . htmlspecialchars("$animeName Episode $epNum", ENT_QUOTES) . ' on ' . $websiteTitle;
$pageDesc     = "Watch $animeName Episode $epNum in HD quality for free on $websiteTitle." . ($synopsis ? ' ' . substr(strip_tags($synopsis),0,100).'…' : '');
$pageCanonical= "$websiteUrl/watch/$slug-episode-$epNum";
$pageImage    = $animeImg;
$pageOgType   = 'video.other';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title><?=$pageTitle?></title>
  <?php include('./_php/ak_page_head.php'); ?>
  <?php
  // ── SEO: structured data (helps Google show video rich results) ───
  $ldDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string)($synopsis !== '' ? $synopsis : $pageDesc))));
  if (mb_strlen($ldDesc) > 300) { $ldDesc = mb_substr($ldDesc, 0, 297) . '…'; }
  $ldDate = '';
  if (!empty($animeData['aired']['from'])) {
      $ldDate = date('Y-m-d', strtotime((string)$animeData['aired']['from']));
  } elseif ($animeYear !== '' && ctype_digit((string)$animeYear)) {
      $ldDate = $animeYear . '-01-01';
  }
  $videoLd = [
      '@context'     => 'https://schema.org',
      '@type'        => 'VideoObject',
      'name'         => "$animeName Episode $epNum" . ($currentEpTitle ? " - $currentEpTitle" : ''),
      'description'  => $ldDesc !== '' ? $ldDesc : "Watch $animeName Episode $epNum online in HD on $websiteTitle.",
      'thumbnailUrl' => $animeImg ? [$animeImg] : [],
      'embedUrl'     => $pageCanonical,
      'url'          => $pageCanonical,
      'publisher'    => [
          '@type' => 'Organization',
          'name'  => $websiteTitle,
          'logo'  => ['@type' => 'ImageObject', 'url' => $websiteLogo],
      ],
  ];
  if ($ldDate !== '') { $videoLd['uploadDate'] = $ldDate; }
  $breadcrumbLd = [
      '@context'        => 'https://schema.org',
      '@type'           => 'BreadcrumbList',
      'itemListElement' => [
          ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>"$websiteUrl/home"],
          ['@type'=>'ListItem','position'=>2,'name'=>$animeName,'item'=>"$websiteUrl/anime/$slug"],
          ['@type'=>'ListItem','position'=>3,'name'=>"Episode $epNum",'item'=>$pageCanonical],
      ],
  ];
  $_ldFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
  ?>
  <script type="application/ld+json"><?=json_encode($videoLd, $_ldFlags)?></script>
  <script type="application/ld+json"><?=json_encode($breadcrumbLd, $_ldFlags)?></script>
  <?php if ($prevUrl): ?><link rel="prev" href="<?=app_e($prevUrl)?>"><?php endif; ?>
  <?php if ($nextUrl2): ?><link rel="next" href="<?=app_e($nextUrl2)?>"><?php endif; ?>
  <style>
  /* ── Watch page layout ─────────────────────────────── */
  .ak-watch-page { max-width: 1440px; margin: 0 auto; padding: 10px 16px 60px; }
  .ak-watch-layout { display: flex; gap: 20px; align-items: flex-start; }
  .ak-watch-main  { flex: 1; min-width: 0; }
  .ak-watch-side  { width: 380px; flex-shrink: 0; position: sticky; top: calc(var(--header-h) + 8px); max-height: calc(100vh - var(--header-h) - 20px); display: flex; flex-direction: column; gap: 0; }

  /* Title bar */
  .ak-wt-bar { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
  .ak-wt-left h1 { font-size: 22px; font-weight: 700; color: #fff; margin: 0 0 4px; font-family: 'Cinzel',serif; letter-spacing: .3px; }
  .ak-wt-ep-label { font-size: 14px; color: var(--text-secondary); margin-bottom: 6px; }
  .ak-wt-ep-label span { color: var(--accent); font-weight: 600; }
  .ak-wt-badges { display: flex; gap: 6px; flex-wrap: wrap; }
  .ak-wt-badge { padding: 2px 9px; border-radius: 4px; font-size: 11px; font-weight: 600; background: rgba(255,255,255,.08); color: var(--text-secondary); border: 1px solid rgba(255,255,255,.1); }
  .ak-wt-badge.red { background: rgba(200,16,46,.2); color: #f87171; border-color: rgba(200,16,46,.3); }
  .ak-wt-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; align-items: center; }
  .ak-wt-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.06); color: var(--text-secondary); transition: all .2s; text-decoration: none; }
  .ak-wt-btn:hover { background: rgba(255,255,255,.12); color: #fff; }
  .ak-wt-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
  .ak-wt-btn.primary:hover { background: var(--accent-hover); }
  .ak-wt-btn.saved { background: rgba(200,16,46,.2); border-color: var(--accent); color: var(--accent); }

  /* Server bar */
  .ak-sbar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; margin-bottom: 2px; flex-wrap: wrap; }
  .ak-sbar-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; }
  .ak-sbar-btns { display: flex; gap: 6px; flex-wrap: wrap; flex: 1; min-height: 32px; align-content: flex-start; }
  .ak-sbar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 7px; font-size: 12px; font-weight: 600; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.05); color: var(--text-secondary); cursor: pointer; transition: all .18s; }
  .ak-sbar-btn i { font-size: 10px; }
  .ak-sbar-btn:hover { background: rgba(255,255,255,.1); color: #fff; }
  .ak-sbar-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  .ak-sbar-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
  .ak-sbar-light { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 6px; font-size: 11px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); color: var(--text-muted); cursor: pointer; }
  .ak-sbar-light:hover, .ak-sbar-light.active { background: rgba(255,255,255,.1); color: #fff; }

  /* Player box */
  .ak-player-box { position: relative; width: 100%; background: #000; border-radius: 10px; overflow: hidden; }
  .ak-player-ratio { position: relative; padding-bottom: 56.25%; }
  .ak-player-ratio iframe, .ak-player-ratio video { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }
  .ak-player-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #000; z-index: 15; }

  /* Auto-next countdown overlay */
  .ak-vp-next { position: absolute; right: 16px; bottom: 70px; z-index: 20; display: flex; gap: 10px; background: rgba(15,15,20,.92); border: 1px solid rgba(255,255,255,.1); border-radius: 10px; padding: 10px; max-width: 320px; box-shadow: 0 8px 24px rgba(0,0,0,.5); }
  .ak-vp-next img { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; flex-shrink: 0; }
  .ak-vp-next-label { font-size: 11px; color: var(--text-muted); }
  .ak-vp-next-title { font-size: 14px; font-weight: 600; color: #fff; margin: 2px 0 8px; }
  .ak-vp-next-actions { display: flex; gap: 8px; }

  /* Anikatsu corner controls — top-left, small, single control system at
     every screen size (no separate desktop/mobile design). Deliberately
     NOT a bottom bar: that region belongs to the provider's own UI.
     Revealed via hover OR a `.touch-show` class (added by JS on tap, since
     touch devices have no :hover) — same markup, same behavior, both
     input types. Auto-hides after a few seconds, same as the keyboard hints. */
  .ak-corner-ctl { position: absolute; top: 8px; left: 8px; z-index: 12; display: flex; gap: 5px; opacity: 0; transition: opacity .2s; pointer-events: none; }
  .ak-player-box:hover .ak-corner-ctl,
  .ak-corner-ctl.touch-show { opacity: 1; pointer-events: auto; }
  .ak-corner-btn { background: rgba(0,0,0,.55); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.18); border-radius: 6px; color: #fff; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; text-decoration: none; transition: background .15s; }
  .ak-corner-btn:hover { background: var(--accent); border-color: var(--accent); }
  #pcTheaterBtn.active { background: var(--accent); border-color: var(--accent); }

  /* Theater mode — player gets the full row width, sidebar drops below it
     (still visible, per the "comments/episode list stay visible" goal),
     rather than the normal side-by-side layout. */
  .ak-watch-layout.theater-mode { flex-direction: column; }
  .ak-watch-layout.theater-mode .ak-watch-side { width: 100%; position: static; max-height: none; }
  .ak-watch-page.theater-mode-page { max-width: 1800px; }
  .ak-pc-group { display: flex; align-items: center; gap: 6px; }
  .ak-pc-btn { background: rgba(255,255,255,.14); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,.15); border-radius: 7px; color: #fff; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: background .15s; }
  .ak-pc-btn:hover { background: rgba(255,255,255,.28); }
  .ak-pc-btn.wide { width: auto; padding: 0 12px; gap: 6px; font-size: 11px; font-weight: 600; }
  .ak-pc-kbd { display: inline-block; background: rgba(255,255,255,.1); border-radius: 3px; padding: 1px 5px; font-size: 10px; font-family: monospace; color: rgba(255,255,255,.6); margin-left: 4px; }

  /* Keyboard shortcuts — on-demand only via the "?" trigger, not a passive
     hover overlay (that competed visually with the provider's own
     top-corner UI, e.g. quality/settings icons many embeds place there). */
  .ak-kbd-trigger { position: absolute; top: 8px; right: 8px; z-index: 12; width: 22px; height: 22px; border-radius: 50%; background: rgba(0,0,0,.5); border: 1px solid rgba(255,255,255,.18); color: rgba(255,255,255,.7); font-size: 11px; font-weight: 700; cursor: pointer; opacity: 0; transition: opacity .2s; }
  .ak-player-box:hover .ak-kbd-trigger,
  .ak-kbd-trigger.touch-show { opacity: 1; }
  .ak-kbd-hints { position: absolute; top: 36px; right: 8px; z-index: 13; opacity: 0; pointer-events: none; transition: opacity .15s; background: rgba(0,0,0,.75); backdrop-filter: blur(8px); border-radius: 8px; padding: 8px 10px; }
  .ak-kbd-hints.open { opacity: 1; pointer-events: auto; }
  .ak-kbd-hint-row { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-size: 10px; color: rgba(255,255,255,.55); }
  .ak-kbd-hint-row kbd { background: rgba(255,255,255,.12); border-radius: 3px; padding: 1px 6px; font-family: monospace; font-size: 10px; }
  .ak-player-empty { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); gap: 10px; }
  .ak-player-empty i { font-size: 36px; color: var(--accent); }
  .ak-spinner { width: 40px; height: 40px; border: 3px solid rgba(255,255,255,.1); border-top-color: var(--accent); border-radius: 50%; animation: spin .8s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* Player notice */
  .ak-player-notice { display: flex; align-items: center; justify-content: space-between; padding: 9px 14px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.06); border-radius: 0 0 8px 8px; font-size: 12px; color: var(--text-muted); gap: 10px; }
  .ak-player-notice i { color: #f59e0b; }
  .ak-player-notice a { color: var(--accent); text-decoration: none; margin-left: auto; white-space: nowrap; }

  /* Anime info card below player */
  .ak-wac { display: flex; gap: 14px; align-items: flex-start; padding: 16px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; margin-top: 10px; }
  .ak-wac-img { width: 80px; height: 110px; object-fit: cover; border-radius: 7px; flex-shrink: 0; }
  .ak-wac-body { flex: 1; min-width: 0; }
  .ak-wac-top { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
  .ak-wac-title { font-size: 16px; font-weight: 700; color: #fff; flex: 1; min-width: 0; }
  .ak-wac-score { display: flex; align-items: center; gap: 4px; font-size: 14px; font-weight: 700; color: #f59e0b; white-space: nowrap; }
  .ak-wac-meta { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
  .ak-wac-meta-tag { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: rgba(255,255,255,.07); color: var(--text-secondary); border: 1px solid rgba(255,255,255,.09); }
  .ak-wac-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .ak-wac-action { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px 12px; background: none; border: none; color: var(--text-muted); font-size: 11px; cursor: pointer; border-radius: 7px; transition: all .2s; text-decoration: none; }
  .ak-wac-action i { font-size: 16px; }
  .ak-wac-action:hover { color: #fff; background: rgba(255,255,255,.06); }
  .ak-wac-action.active { color: var(--accent); }

  /* Synopsis */
  .ak-synopsis { margin-top: 14px; padding: 14px 16px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; }
  .ak-synopsis h2 { font-size: 14px; font-weight: 700; color: var(--accent); margin: 0 0 8px; text-transform: uppercase; letter-spacing: .5px; }
  .ak-synopsis-text { font-size: 13px; line-height: 1.7; color: var(--text-secondary); margin: 0; }
  .ak-synopsis-text.collapsed { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .ak-read-more { display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; background: none; border: none; color: var(--accent); font-size: 12px; font-weight: 600; cursor: pointer; padding: 0; }

  /* Episode nav bottom */
  .ak-ep-nav-bar { display: flex; gap: 10px; margin-top: 14px; }
  .ak-ep-nav-link { display: flex; align-items: center; gap: 10px; flex: 1; padding: 12px 14px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; text-decoration: none; transition: border-color .2s; }
  .ak-ep-nav-link:hover { border-color: rgba(200,16,46,.4); }
  .ak-ep-nav-link img { width: 54px; height: 38px; object-fit: cover; border-radius: 5px; flex-shrink: 0; }
  .ak-ep-nav-link .nav-label { font-size: 11px; color: var(--text-muted); margin-bottom: 2px; }
  .ak-ep-nav-link .nav-ep { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
  .ak-ep-nav-link.prev { flex-direction: row; }
  .ak-ep-nav-link.next { flex-direction: row-reverse; text-align: right; }
  .ak-ep-nav-link i { color: var(--accent); font-size: 18px; }

  /* Right sidebar – Episodes */
  .ak-watch-side-box { background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; overflow: hidden; }
  .ak-watch-tabs { display: flex; border-bottom: 1px solid rgba(255,255,255,.07); }
  .ak-watch-tab-btn { flex: 1; padding: 12px; font-size: 13px; font-weight: 600; color: var(--text-muted); background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all .2s; }
  .ak-watch-tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
  .ak-eps-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.06); }
  .ak-season-sel { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); cursor: pointer; }
  .ak-eps-count-badge { font-size: 11px; color: var(--text-muted); }
  .ak-eps-tools { display: flex; flex-direction: column; gap: 6px; padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.06); }
  .ak-eps-jump, .ak-eps-filter { display: flex; align-items: center; gap: 7px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 7px; padding: 6px 10px; }
  .ak-eps-jump input, .ak-eps-filter input { flex: 1; min-width: 0; background: none; border: none; outline: none; color: var(--text-primary); font-size: 12px; }
  .ak-eps-jump input::placeholder, .ak-eps-filter input::placeholder { color: var(--text-muted); }
  .ak-eps-jump button { background: none; border: none; color: var(--accent); cursor: pointer; font-size: 12px; padding: 0 2px; }
  .ak-eps-jump input[type=number]::-webkit-outer-spin-button, .ak-eps-jump input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .ak-ep-item.ak-ep-hidden-filter { display: none !important; }
  .ak-recent-watched { padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.06); }
  .ak-recent-watched-label { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
  .ak-recent-watched-row { display: flex; gap: 6px; flex-wrap: wrap; }
  .ak-recent-watched-chip { font-size: 11px; font-weight: 600; padding: 5px 11px; border-radius: 999px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: var(--text-secondary); text-decoration: none; transition: all .15s; }
  .ak-recent-watched-chip:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.ak-eps-scroll { overflow-y: auto; max-height: 420px; padding-right: 6px; }
  /* simple visible scrollbar */
  .ak-eps-scroll::-webkit-scrollbar { width: 6px; }
  .ak-eps-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.03); border-radius: 999px; }
  .ak-eps-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.14); border-radius: 999px; border: 1px solid rgba(0,0,0,.15); }
  .ak-eps-scroll { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.22) rgba(255,255,255,.03); }

  .ak-ep-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.04); border-left: 3px solid transparent; text-decoration: none; transition: background .15s, border-color .15s; }
  .ak-ep-item:hover { background: rgba(255,255,255,.04); }
  .ak-ep-item.active { background: rgba(200,16,46,.14); border-left-color: var(--accent); box-shadow: inset 0 0 16px rgba(200,16,46,.18); }
  .ak-ep-item.watched:not(.active) { opacity: .62; }
  .ak-ep-play { width: 28px; height: 28px; border-radius: 50%; border: 1px solid rgba(255,255,255,.12); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--text-muted); font-size: 9px; transition: all .2s; }
  .ak-ep-item:hover .ak-ep-play, .ak-ep-item.active .ak-ep-play { background: var(--accent); border-color: var(--accent); color: #fff; }
  .ak-ep-item.active .ak-ep-play { box-shadow: 0 0 10px rgba(200,16,46,.6); }
  .ak-ep-num { font-size: 12px; font-weight: 700; color: var(--text-secondary); width: 44px; text-align: left; flex-shrink: 0; letter-spacing: .3px; }
  .ak-ep-item.active .ak-ep-num { color: var(--accent); }
  .ak-ep-info { flex: 1; min-width: 0; }
  .ak-ep-title { font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; }
  .ak-ep-item.active .ak-ep-title { color: #fff; font-weight: 600; }
  .ak-ep-dur { font-size: 11px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }
  .ak-ep-active-icon { color: var(--accent); font-size: 12px; }
  .ak-filler-dot { width: 6px; height: 6px; border-radius: 50%; background: #f59e0b; flex-shrink: 0; }
  .ak-ep-progress-icon { font-size: 11px; flex-shrink: 0; }
  .ak-ep-progress-watched { color: #22c55e; }
  .ak-ep-progress-progress { color: #f59e0b; animation: ak-ep-spin 1.4s linear infinite; }
  .ak-ep-progress-none { color: rgba(255,255,255,.15); font-size: 8px; }
  @keyframes ak-ep-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

  /* Info tab */
  .ak-info-panel { padding: 14px; }
  .ak-info-row { display: flex; gap: 10px; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,.05); font-size: 13px; }
  .ak-info-row:last-child { border-bottom: none; }
  .ak-info-label { color: var(--text-muted); min-width: 80px; flex-shrink: 0; }
  .ak-info-val { color: var(--text-secondary); }

  /* Show comments */
  .ak-show-comments { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; margin-top: 10px; cursor: pointer; }
  .ak-show-comments-left { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
  .ak-show-comments-left i { color: var(--accent); }
  .ak-show-comments-count { font-size: 13px; font-weight: 700; color: var(--text-main); }

  /* You might also like */
  .ak-ymal { margin-top: 10px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 14px; overflow: hidden; }
  .ak-ymal h4 { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px; }
  .ak-ymal-scroll { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
  .ak-ymal-scroll::-webkit-scrollbar { height: 3px; }
  .ak-ymal-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
  .ak-ymal-card { flex-shrink: 0; width: 90px; text-decoration: none; }
  .ak-ymal-card img { width: 90px; height: 126px; object-fit: cover; border-radius: 7px; display: block; }
  .ak-ymal-card-title { font-size: 10px; color: var(--text-muted); margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }

  /* Switch message */
  #ak-switch-msg { display: none; padding: 8px 14px; font-size: 12px; color: #f59e0b; background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.18); border-radius: 6px; margin-top: 6px; align-items: center; }
  #ak-switch-msg.visible { display: flex; }
  #ak-switch-msg[data-err] { color: #f87171; background: rgba(248,113,113,.08); border-color: rgba(248,113,113,.2); }

  /* ── Custom Video Player (ak-vp) ──────────────────────────────── */
  .ak-vp { position:absolute; inset:0; z-index:13; display:flex; flex-direction:column; justify-content:flex-end; }
  .ak-vp.hide-cursor { cursor:none; }

  /* Subtitle display */
  .ak-vp-subs { position:absolute; bottom:72px; left:0; right:0; text-align:center; pointer-events:none; z-index:20; padding:0 8%; }
  .ak-vp-subs span { display:inline; background:rgba(0,0,0,.78); color:#fff; font-size:15px; line-height:1.7; padding:2px 8px; border-radius:3px; box-decoration-break:clone; -webkit-box-decoration-break:clone; }

  /* Skip flash indicators */
  .ak-vp-skip-ind { position:absolute; top:50%; transform:translateY(-50%); width:64px; height:64px; background:rgba(0,0,0,.55); border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; font-size:10px; font-weight:700; opacity:0; pointer-events:none; z-index:20; transition:opacity .18s; }
  .ak-vp-skip-ind i { font-size:18px; margin-bottom:2px; display:block; }
  .ak-vp-skip-ind.show { opacity:1; }
  #akVpSkipBackInd { left:14%; }
  #akVpSkipFwdInd  { right:14%; }

  /* Center play/pause flash */
  .ak-vp-center-play { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%) scale(0); width:60px; height:60px; border-radius:50%; background:rgba(200,16,46,.85); display:flex; align-items:center; justify-content:center; color:#fff; font-size:22px; opacity:0; pointer-events:none; z-index:20; transition:transform .12s ease,opacity .25s ease; }
  .ak-vp-center-play.pop { transform:translate(-50%,-50%) scale(1.05); opacity:1; }

  /* Gradient overlay + controls bar */
  .ak-vp-controls { position:relative; padding:0 12px 10px; background:linear-gradient(to top,rgba(0,0,0,.9) 0%,transparent 100%); opacity:0; transition:opacity .22s; pointer-events:none; }
  .ak-vp.show-ctl .ak-vp-controls,
  .ak-player-box:hover .ak-vp .ak-vp-controls { opacity:1; pointer-events:auto; }

  /* Seek / progress bar */
  .ak-vp-progress { position:relative; height:4px; margin-bottom:8px; background:rgba(255,255,255,.22); border-radius:2px; cursor:pointer; transition:height .15s; }
  .ak-vp.show-ctl .ak-vp-progress:hover,
  .ak-player-box:hover .ak-vp .ak-vp-progress:hover { height:7px; }
  .ak-vp-buf  { position:absolute; top:0; left:0; height:100%; width:0; background:rgba(255,255,255,.32); border-radius:2px; pointer-events:none; }
  .ak-vp-fill { position:absolute; top:0; left:0; height:100%; width:0; background:var(--accent); border-radius:2px; pointer-events:none; }
  .ak-vp-handle { position:absolute; top:50%; width:14px; height:14px; border-radius:50%; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.4); transform:translate(-50%,-50%) scale(0); transition:transform .15s; pointer-events:none; }
  .ak-vp-progress:hover .ak-vp-handle { transform:translate(-50%,-50%) scale(1); }

  /* Bottom row */
  .ak-vp-row { display:flex; align-items:center; justify-content:space-between; gap:4px; }
  .ak-vp-grp { display:flex; align-items:center; gap:2px; }

  /* Control buttons */
  .ak-vp-btn { background:none; border:none; color:#fff; width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; border-radius:5px; font-size:14px; opacity:.85; transition:opacity .15s,background .15s; flex-shrink:0; padding:0; }
  .ak-vp-btn:hover { opacity:1; background:rgba(255,255,255,.13); }
  .ak-vp-btn.on { color:var(--accent); opacity:1; }
  .ak-vp-skip-btn { font-size:13px; }

  /* Volume */
  .ak-vp-vol { display:flex; align-items:center; gap:2px; }
  .ak-vp-vol-slider { -webkit-appearance:none; appearance:none; width:60px; height:3px; background:rgba(255,255,255,.3); border-radius:2px; outline:none; cursor:pointer; flex-shrink:0; }
  .ak-vp-vol-slider::-webkit-slider-thumb { -webkit-appearance:none; width:12px; height:12px; border-radius:50%; background:#fff; cursor:pointer; }
  .ak-vp-vol-slider::-moz-range-thumb { width:12px; height:12px; border-radius:50%; background:#fff; border:none; cursor:pointer; }

  /* Time */
  .ak-vp-time { font-size:11px; color:rgba(255,255,255,.82); white-space:nowrap; padding:0 6px; font-variant-numeric:tabular-nums; }

  /* Settings / CC panels */
  .ak-vp-panel { position:absolute; bottom:60px; right:10px; z-index:25; background:rgba(10,8,20,.96); border:1px solid rgba(255,255,255,.12); border-radius:10px; padding:10px; min-width:168px; backdrop-filter:blur(12px); }
  .ak-vp-panel-hd { font-size:10px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin:0 0 6px; padding-bottom:5px; border-bottom:1px solid rgba(255,255,255,.07); }
  .ak-vp-panel-hd.mt { margin-top:10px; }
  .ak-vp-panel-opts { display:flex; flex-direction:column; gap:1px; }
  .ak-vp-popt { background:none; border:none; color:var(--text-secondary); font-size:12px; font-weight:500; text-align:left; padding:6px 10px; border-radius:6px; cursor:pointer; transition:background .15s,color .15s; width:100%; }
  .ak-vp-popt:hover { background:rgba(255,255,255,.07); color:#fff; }
  .ak-vp-popt.active { background:rgba(200,16,46,.18); color:var(--accent); font-weight:700; }

  @media (max-width: 900px) {
    .ak-watch-layout { flex-direction: column; }
    .ak-watch-side { width: 100%; position: static; max-height: none; }
    .ak-eps-scroll { max-height: 300px; }
  }
  @media (max-width: 600px) {
    .ak-wt-bar { flex-direction: column; gap: 10px; }
    .ak-wac { flex-wrap: wrap; }
  }
  </style>
</head>
<?php
// Flush the <head> to the browser immediately so CSS/fonts start loading
// while the rest of the PHP (episode list rendering) continues
flush();
?>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap" style="padding-top:12px">
<main id="ak-main-content">
<div class="ak-watch-page" id="akWatchPage">

  <!-- Breadcrumb -->
  <nav class="ak-breadcrumb" aria-label="Breadcrumb">
    <a href="<?=$websiteUrl?>" aria-label="Home"><i class="fas fa-home" aria-hidden="true"></i></a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="<?=$websiteUrl?>/anime">Anime</a>
    <span class="sep" aria-hidden="true">/</span>
    <a href="<?=$websiteUrl?>/anime/<?=app_e($slug)?>"><?=app_e($animeName)?></a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="current" aria-current="page">Episode <?=(int)$epNum?></span>
  </nav>

  <div class="ak-watch-layout" id="akWatchLayout">

    <!-- ══ LEFT — main player column ══════════════════════════════ -->
    <div class="ak-watch-main">

      <!-- Title bar -->
      <div class="ak-wt-bar">
        <div class="ak-wt-left">
          <h1><?=app_e($animeName)?></h1>
          <div class="ak-wt-ep-label">
            Episode <span><?=(int)$epNum?></span><?php if ($currentEpTitle): ?>: <?=app_e($currentEpTitle)?><?php endif; ?>
          </div>
          <div class="ak-wt-badges">
            <?php if ($animeType): ?><span class="ak-wt-badge"><?=app_e($animeType)?></span><?php endif; ?>
            <?php if ($animeYear): ?><span class="ak-wt-badge"><?=app_e($animeYear)?></span><?php endif; ?>
            <span class="ak-wt-badge">HD</span>
            <span class="ak-wt-badge"><?=$isDubbed?'DUB':'SUB'?></span>
          </div>
        </div>
        <div class="ak-wt-actions">
          <?php if ($viewer): ?>
          <button class="ak-wt-btn <?=$inWatchlist?'saved':''?>" id="watchlistBtn">
            <i class="fas fa-<?=$inWatchlist?'bookmark':'plus'?>"></i>
            <?=$inWatchlist?'Saved':'Add to List'?>
          </button>
          <div class="ak-list-status-sel" id="listStatusBtn" style="position:relative;">
            <button class="ak-wt-btn <?=$listStatus?'saved':''?>" id="listStatusToggle" type="button">
              <i class="fas <?=$listStatus?$listStatusLabels[$listStatus]['icon']:'fa-list-ul'?>"></i>
              <?=$listStatus?app_e($listStatusLabels[$listStatus]['label']):'Track Progress'?>
              <i class="fas fa-chevron-down" style="font-size:9px;opacity:.6;margin-left:2px"></i>
            </button>
            <div id="listStatusMenu" style="display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:200;background:var(--bg-card);border:1px solid rgba(255,255,255,.1);border-radius:8px;min-width:170px;box-shadow:0 8px 24px rgba(0,0,0,.6);overflow:hidden">
              <?php foreach ($listStatusLabels as $statusKey => $meta): ?>
              <div class="ak-list-status-opt" data-status="<?=app_e($statusKey)?>"
                   style="padding:9px 14px;font-size:13px;font-weight:600;color:<?=$listStatus===$statusKey?'var(--accent)':'var(--text-secondary)'?>;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .15s"
                   onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background=''">
                <i class="fas <?=$meta['icon']?>" style="width:14px"></i> <?=app_e($meta['label'])?>
              </div>
              <?php endforeach; ?>
              <?php if ($listStatus): ?>
              <div class="ak-list-status-opt" data-status="remove"
                   style="padding:9px 14px;font-size:12px;color:var(--text-muted);cursor:pointer;border-top:1px solid rgba(255,255,255,.06)"
                   onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background=''">
                Remove from list
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php else: ?>
          <a class="ak-wt-btn" href="<?=$websiteUrl?>/login?next=<?=urlencode($_SERVER['REQUEST_URI']??'')?>">
            <i class="fas fa-plus"></i> Add to List
          </a>
          <?php endif; ?>
          <?php if ($hasNext): ?>
          <a class="ak-wt-btn primary" href="<?=$websiteUrl?>/watch/<?=app_e($slug)?>-episode-<?=$epNum+1?>">
            Next Episode <i class="fas fa-arrow-right"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Server selector bar (ABOVE player) -->
      <div class="ak-sbar">
        <span class="ak-sbar-label" aria-label="Select streaming server">Select Server</span>
        <div class="ak-sbar-btns" id="serverBtns">
          <?php foreach ($serverList as $idx => $srv): ?>
          <button class="ak-sbar-btn <?=$idx===0?'active':''?>"
                  data-idx="<?=(int)$idx?>"
                  data-url="<?=app_e($srv['url'])?>"
                  data-type="<?=app_e($srv['type'])?>"
                  data-provider="<?=app_e($srv['providerId'])?>">
            <i class="fas fa-circle-play"></i>
            <?=app_e($srv['name'])?>
            <?php if (!empty($srv['lang'])): ?>
            <span style="opacity:.7;font-size:10px"><?=strtoupper(app_e($srv['lang']))?></span>
            <?php endif; ?>
          </button>
          <?php endforeach; ?>
          <?php if (empty($serverList)): ?>
          <span style="font-size:12px;color:var(--text-muted)">No servers available</span>
          <?php endif; ?>
        </div>
        <div class="ak-sbar-right">
          <button class="ak-sbar-light" id="autoPlayToggle"><i class="fas fa-play"></i> Auto Play</button>
          <button class="ak-sbar-light" id="autoNextToggle"><i class="fas fa-forward"></i> Auto Next</button>
          <button class="ak-sbar-light" id="lightToggle"><i class="fas fa-lightbulb"></i> Light</button>
        </div>
      </div>
      <div id="ak-switch-msg"></div>

      <!-- Player -->
      <div class="ak-player-box" id="akPlayerShell">
        <?php if (!empty($serverList)): ?>
        <div class="ak-player-ratio">
          <div class="ak-player-loading" id="embed-loading"><div class="ak-spinner"></div></div>
          <video id="anime-player-video" playsinline preload="auto"
                 style="display:none;position:absolute;inset:0;width:100%;height:100%;background:#000"></video>

          <!-- Custom Video Player overlay (HLS/MP4 direct streams only) -->
          <div class="ak-vp" id="akVp" style="display:none">
            <!-- Subtitle cue display -->
            <div class="ak-vp-subs" id="akVpSubs"></div>
            <!-- Skip indicators -->
            <div class="ak-vp-skip-ind" id="akVpSkipBackInd"><i class="fas fa-rotate-left"></i><span>10s</span></div>
            <div class="ak-vp-skip-ind" id="akVpSkipFwdInd"><i class="fas fa-rotate-right"></i><span>10s</span></div>
            <!-- Center play/pause ripple -->
            <div class="ak-vp-center-play" id="akVpCenterPlay"><i class="fas fa-play"></i></div>
            <!-- Settings panel (speed + quality) -->
            <div class="ak-vp-panel" id="akVpSetPanel" style="display:none">
              <div class="ak-vp-panel-hd">Playback Speed</div>
              <div class="ak-vp-panel-opts" id="akVpSpeedOpts">
                <button class="ak-vp-popt" data-speed="0.25">0.25×</button>
                <button class="ak-vp-popt" data-speed="0.5">0.5×</button>
                <button class="ak-vp-popt" data-speed="0.75">0.75×</button>
                <button class="ak-vp-popt active" data-speed="1">Normal (1×)</button>
                <button class="ak-vp-popt" data-speed="1.25">1.25×</button>
                <button class="ak-vp-popt" data-speed="1.5">1.5×</button>
                <button class="ak-vp-popt" data-speed="2">2×</button>
              </div>
              <div class="ak-vp-panel-hd mt" id="akVpQualHd" style="display:none">Quality</div>
              <div class="ak-vp-panel-opts" id="akVpQualOpts" style="display:none"></div>
            </div>
            <!-- CC / Subtitles panel -->
            <div class="ak-vp-panel" id="akVpCCPanel" style="display:none">
              <div class="ak-vp-panel-hd">Subtitles / CC</div>
              <div class="ak-vp-panel-opts" id="akVpCCOpts">
                <button class="ak-vp-popt active" data-track="-1">Off</button>
              </div>
            </div>
            <!-- Controls bar -->
            <div class="ak-vp-controls" id="akVpControls">
              <div class="ak-vp-progress" id="akVpProgress">
                <div class="ak-vp-buf" id="akVpBuf"></div>
                <div class="ak-vp-fill" id="akVpFill"></div>
                <div class="ak-vp-handle" id="akVpHandle"></div>
              </div>
              <div class="ak-vp-row">
                <div class="ak-vp-grp">
                  <button class="ak-vp-btn" id="akVpPlayBtn" title="Play / Pause (Space)"><i class="fas fa-play"></i></button>
                  <button class="ak-vp-btn ak-vp-skip-btn" id="akVpSkipBBtn" title="Rewind 10s (J)"><i class="fas fa-rotate-left"></i></button>
                  <button class="ak-vp-btn ak-vp-skip-btn" id="akVpSkipFBtn" title="Skip 10s (L)"><i class="fas fa-rotate-right"></i></button>
                  <div class="ak-vp-vol">
                    <button class="ak-vp-btn" id="akVpMuteBtn" title="Mute / Unmute (M)"><i class="fas fa-volume-high"></i></button>
                    <input type="range" class="ak-vp-vol-slider" id="akVpVolSlider" min="0" max="1" step="0.05" value="1" aria-label="Volume">
                  </div>
                  <span class="ak-vp-time" id="akVpTime">0:00 / 0:00</span>
                </div>
                <div class="ak-vp-grp">
                  <button class="ak-vp-btn" id="akVpCCBtn" title="Subtitles / CC"><i class="fas fa-closed-captioning"></i></button>
                  <button class="ak-vp-btn" id="akVpSetBtn" title="Settings"><i class="fas fa-gear"></i></button>
                  <button class="ak-vp-btn" id="akVpPipBtn" title="Picture-in-Picture"><i class="fas fa-clone"></i></button>
                  <button class="ak-vp-btn" id="akVpFsBtn" title="Fullscreen (F)"><i class="fas fa-expand"></i></button>
                </div>
              </div>
            </div>

            <!-- Auto-next countdown (direct video streams only — last 30s) -->
            <?php if ($hasNext): ?>
            <div class="ak-vp-next" id="akVpNextOverlay" style="display:none">
              <img src="<?=app_e($animeImg)?>" alt="" onerror="this.style.display='none'">
              <div class="ak-vp-next-body">
                <div class="ak-vp-next-label">Next Episode in <span id="akVpNextCount">5</span>s</div>
                <div class="ak-vp-next-title">Episode <?=(int)($epNum+1)?></div>
                <div class="ak-vp-next-actions">
                  <button type="button" id="akVpNextCancel" class="ak-pc-btn">Cancel</button>
                  <a href="<?=app_e($nextUrl2)?>" class="ak-pc-btn wide" id="akVpNextNow">Play Now <i class="fas fa-forward-step"></i></a>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <iframe id="anime-player-iframe"
                  title="Video Player"
                  width="100%" height="100%"
                  allow="autoplay; fullscreen; encrypted-media; picture-in-picture"
                  referrerpolicy="no-referrer"
                  style="display:none;position:absolute;inset:0;width:100%;height:100%;border:none"></iframe>

          <!-- Anikatsu corner controls — top-left, small, low-profile.
               Deliberately NOT a bottom bar: the provider (VidNest/VidLink)
               renders its own Play/Settings/Episodes/Fullscreen bar at the
               bottom of the iframe. A full-width Anikatsu bar in that same
               region duplicated those controls and, worse, became
               click-interactive on hover/fullscreen — physically blocking
               taps meant for the provider's own UI. Only the things the
               provider genuinely cannot offer survive here: episode
               navigation, server-reload, and Anikatsu's theater-mode toggle.
               Same single control set on every screen size — no separate
               desktop/mobile version — shown via hover (mouse) or tap
               (touch), handled identically by JS below. -->
          <div class="ak-corner-ctl" id="akCornerCtl">
            <?php if ($hasPrev): ?>
            <a href="<?=app_e($prevUrl)?>" class="ak-corner-btn" title="Previous Episode (←)">
              <i class="fas fa-backward-step"></i>
            </a>
            <?php endif; ?>
            <?php if ($hasNext): ?>
            <a href="<?=app_e($nextUrl2)?>" class="ak-corner-btn" title="Next Episode (→ / N)">
              <i class="fas fa-forward-step"></i>
            </a>
            <?php endif; ?>
            <button class="ak-corner-btn" id="pcReloadBtn" title="Reload server (R)">
              <i class="fas fa-rotate-right"></i>
            </button>
            <button class="ak-corner-btn" id="pcTheaterBtn" title="Theater Mode">
              <i class="fas fa-tv"></i>
            </button>
          </div>

          <!-- Keyboard shortcuts — on-demand only (small "?" trigger),
               not a passive hover overlay competing with the provider's
               own top-corner UI (quality/settings icons, on many embeds). -->
          <button class="ak-kbd-trigger" id="akKbdTrigger" title="Keyboard shortcuts" aria-label="Show keyboard shortcuts">?</button>
          <div class="ak-kbd-hints" id="akKbdHints" aria-hidden="true">
            <div class="ak-kbd-hint-row" id="kbdVidHints" style="display:none"><kbd>Space</kbd> Play/Pause</div>
            <div class="ak-kbd-hint-row" id="kbdVidSkip"  style="display:none"><kbd>J</kbd><kbd>L</kbd> Skip ±10s</div>
            <div class="ak-kbd-hint-row" id="kbdVidArrows" style="display:none"><kbd>←</kbd><kbd>→</kbd> Seek ±5s</div>
            <div class="ak-kbd-hint-row" id="kbdVidMute"  style="display:none"><kbd>M</kbd> Mute</div>
            <div class="ak-kbd-hint-row"><kbd>F</kbd> Fullscreen</div>
            <div class="ak-kbd-hint-row"><kbd>R</kbd> Reload</div>
            <div class="ak-kbd-hint-row"><kbd>N</kbd> Next Episode</div>
            <div class="ak-kbd-hint-row" id="kbdIframeArrows"><kbd>←</kbd><kbd>→</kbd> Episodes</div>
          </div>
        </div>
        <?php else: ?>
        <div class="ak-player-ratio">
          <div class="ak-player-empty">
            <i class="fas fa-satellite-dish"></i>
            <p>No streams available for this episode.</p>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Player notice -->
      <div class="ak-player-notice">
        <i class="fas fa-gift"></i>
        If the player is not working, try changing the server or quality.
        <a href="#">Report Issue</a>
      </div>

      <!-- Anime info card below player -->
      <div class="ak-wac">
        <img class="ak-wac-img" src="<?=app_e($animeImg)?>" alt="<?=app_e($animeName)?>"
             onerror="this.src='<?=$websiteUrl?>/files/images/no_poster.jpg'">
        <div class="ak-wac-body">
          <div class="ak-wac-top">
            <div class="ak-wac-title"><?=app_e($animeName)?></div>
            <?php if ($animeScore): ?>
            <div class="ak-wac-score"><i class="fas fa-star" style="font-size:12px"></i> <?=app_e($animeScore)?></div>
            <?php endif; ?>
          </div>
          <div class="ak-wac-meta">
            <?php if ($animeType): ?><span class="ak-wac-meta-tag"><?=app_e($animeType)?></span><?php endif; ?>
            <?php if ($animeYear): ?><span class="ak-wac-meta-tag"><?=app_e($animeYear)?></span><?php endif; ?>
            <span class="ak-wac-meta-tag">HD</span>
            <span class="ak-wac-meta-tag"><?=$isDubbed?'DUB':'SUB'?></span>
          </div>
          <div class="ak-wac-actions">
            <button class="ak-wac-action" id="likeBtn"><i class="fas fa-heart"></i> Like</button>
            <button class="ak-wac-action" id="dislikeBtn"><i class="fas fa-thumbs-down"></i> Dislike</button>
            <?php if ($viewer): ?>
            <button class="ak-wac-action <?=$inWatchlist?'active':''?>" id="watchlistBtn2">
              <i class="fas fa-bookmark"></i> <?=$inWatchlist?'Saved':'Add to List'?>
            </button>
            <button class="ak-wac-action"><i class="fas fa-clock"></i> Watch Later</button>
            <?php endif; ?>
            <button class="ak-wac-action" id="shareBtn"><i class="fas fa-share-alt"></i> Share</button>
          </div>
        </div>
      </div>

      <!-- Synopsis -->
      <?php if ($synopsis): ?>
      <div class="ak-synopsis">
        <h2>Synopsis</h2>
        <p class="ak-synopsis-text collapsed" id="synopsisText"><?=app_e(strip_tags($synopsis))?></p>
        <button class="ak-read-more" id="readMoreBtn">Read More <i class="fas fa-chevron-down"></i></button>
      </div>
      <?php endif; ?>

      <!-- Prev / Next episode nav -->
      <div class="ak-ep-nav-bar">
        <?php if ($hasPrev): ?>
        <a class="ak-ep-nav-link prev" href="<?=$websiteUrl?>/watch/<?=app_e($slug)?>-episode-<?=$epNum-1?>">
          <i class="fas fa-chevron-left"></i>
          <div>
            <div class="nav-label">Previous Episode</div>
            <div class="nav-ep">Episode <?=$epNum-1?></div>
          </div>
        </a>
        <?php endif; ?>
        <?php if ($hasNext): ?>
        <a class="ak-ep-nav-link next" href="<?=$websiteUrl?>/watch/<?=app_e($slug)?>-episode-<?=$epNum+1?>">
          <div>
            <div class="nav-label">Next Episode</div>
            <div class="nav-ep">Episode <?=$epNum+1?></div>
          </div>
          <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
      </div>

      <?php
      $commentsAnimeId = $slug;
      $commentsEpisode = $epNum;
      include('./_php/ak_comments.php');
      ?>

    </div><!-- /ak-watch-main -->

    <!-- ══ RIGHT — sidebar ════════════════════════════════════════ -->
    <div class="ak-watch-side">

      <div class="ak-watch-side-box">
        <!-- Tabs -->
        <div class="ak-watch-tabs">
          <button class="ak-watch-tab-btn active" data-tab="episodes">Episodes</button>
          <button class="ak-watch-tab-btn" data-tab="info">Info</button>
        </div>

        <!-- Episodes tab -->
        <div id="tab-episodes" class="ak-watch-tab-panel">
          <div class="ak-eps-header">
            <!-- Season dropdown -->
            <?php if (count($seasons) > 1): ?>
            <div class="ak-season-sel" id="seasonDropBtn" style="position:relative;cursor:pointer;user-select:none">
              <i class="fas fa-layer-group" style="font-size:11px;color:var(--accent)"></i>
              <span id="seasonDropLabel"><?=app_e($seasons[$activeSeasonIdx]['label'] ?? 'Season 1')?></span>
              <i class="fas fa-chevron-down" style="font-size:10px;opacity:.5"></i>
              <div id="seasonDropMenu" style="display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:200;background:var(--bg-card);border:1px solid rgba(255,255,255,.1);border-radius:8px;min-width:140px;box-shadow:0 8px 24px rgba(0,0,0,.6);overflow:hidden">
                <?php foreach ($seasons as $_si => $_s): ?>
                <div class="ak-season-opt <?=$_si===$activeSeasonIdx?'active':''?>"
                     data-season-idx="<?=(int)$_si?>"
                     style="padding:9px 14px;font-size:13px;font-weight:600;color:<?=$_si===$activeSeasonIdx?'var(--accent)':'var(--text-secondary)'?>;cursor:pointer;transition:background .15s"
                     onmouseover="this.style.background='rgba(255,255,255,.06)'"
                     onmouseout="this.style.background=''">
                  <?=app_e($_s['label'])?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php else: ?>
            <div class="ak-season-sel">
              <i class="fas fa-layer-group" style="font-size:11px;color:var(--accent)"></i>
              <?=app_e($seasons[0]['label'] ?? 'Episodes')?>
            </div>
            <?php endif; ?>
            <div class="ak-eps-count-badge"><?=(int)$epNum?> / <?=$totalEpisodes?></div>
          </div>

          <?php if ($totalEpisodes > 1): ?>
          <!-- Jump to episode + filter — critical for long-running shows -->
          <div class="ak-eps-tools">
            <div class="ak-eps-jump">
              <i class="fas fa-hashtag" style="font-size:11px;opacity:.5"></i>
              <input type="number" id="epJumpInput" min="1" max="<?=(int)$totalEpisodes?>"
                     placeholder="Go to episode #" autocomplete="off">
              <button type="button" id="epJumpBtn" title="Go"><i class="fas fa-arrow-right"></i></button>
            </div>
            <div class="ak-eps-filter">
              <i class="fas fa-search" style="font-size:11px;opacity:.5"></i>
              <input type="text" id="epFilterInput" placeholder="Filter by episode # or title" autocomplete="off">
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($recentWatched)): ?>
          <!-- Recently Watched — quick jump back without scrolling the full list -->
          <div class="ak-recent-watched">
            <div class="ak-recent-watched-label"><i class="fas fa-clock-rotate-left"></i> Recently Watched</div>
            <div class="ak-recent-watched-row">
              <?php foreach ($recentWatched as $rw):
                $rwEp = (int)$rw['episode'];
                if ($rwEp === (int)$epNum) continue; // skip the episode already playing
              ?>
              <a class="ak-recent-watched-chip" href="<?=$websiteUrl?>/watch/<?=app_e($slug)?>-episode-<?=$rwEp?>">
                EP <?=$rwEp?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- One scroll container per season, only active one shown -->
          <?php if (empty($episodelist)): ?>
          <div class="ak-eps-scroll"><div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px">Episode list unavailable</div></div>
          <?php else: ?>
          <?php foreach ($seasons as $_si => $_s): ?>
          <div class="ak-eps-scroll ak-season-panel <?=$_si===$activeSeasonIdx?'':'hidden-season'?>"
               id="season-panel-<?=(int)$_si?>"
               <?=$_si!==$activeSeasonIdx?'style="display:none"':''?>>
            <?php foreach ($_s['episodes'] as $ep):
              $epN    = (string)($ep['episodeNum'] ?? $ep['number'] ?? '?');
              $epId   = (string)($ep['episodeId']  ?? ($slug.'-episode-'.$epN));
              $epTit  = (string)($ep['title']      ?? '');
              $isCur  = (int)$epN === (int)$epNum;
              $isFill = !empty($ep['filler']);
              // Progress state: watched (in watch_history) > in-progress (this
              // is the current episode and has a saved resume position) > not
              // started. NOTE: only the most recently watched episode per
              // anime has a stored position (continue_watching is one row per
              // user+anime, not per episode) — so "in progress" only ever
              // applies to the episode currently open, not a per-episode %.
              $isWatched = in_array((int)$epN, $watchedEpisodes, true);
              $isInProgress = !$isWatched && $isCur && $resumeSeconds > 5;
              $progressIcon = $isWatched ? 'fa-circle-check' : ($isInProgress ? 'fa-spinner' : 'fa-circle');
              $progressTitle = $isWatched ? 'Watched' : ($isInProgress ? 'In progress' : 'Not started');
            ?>
            <a class="ak-ep-item <?=$isCur?'active':''?> <?=$isFill?'filler':''?> <?=$isWatched?'watched':''?>"
               href="<?=$websiteUrl?>/watch/<?=app_e($epId)?>"
               data-epnum="<?=app_e($epN)?>"
               data-eptitle="<?=app_e(strtolower($epTit))?>"
               <?=$isCur?'id="currentEpItem"':''?>>
              <div class="ak-ep-play"><i class="fas fa-play"></i></div>
              <div class="ak-ep-num">EP <?=app_e($epN)?></div>
              <div class="ak-ep-info">
                <?php if ($showEpTitles): ?>
                <div class="ak-ep-title"><?=app_e($epTit ?: 'Episode '.$epN)?></div>
                <?php else: ?>
                <div class="ak-ep-title">Episode <?=app_e($epN)?></div>
                <?php endif; ?>
              </div>
              <?php if ($isFill): ?><div class="ak-filler-dot" title="Filler"></div><?php endif; ?>
              <?php if ($viewer): ?>
              <i class="fas <?=$progressIcon?> ak-ep-progress-icon ak-ep-progress-<?=$isWatched?'watched':($isInProgress?'progress':'none')?>" title="<?=$progressTitle?>"></i>
              <?php endif; ?>
              <?php if ($isCur): ?><i class="fas fa-volume-up ak-ep-active-icon"></i><?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Info tab -->
        <div id="tab-info" class="ak-watch-tab-panel" style="display:none">
          <div class="ak-info-panel">
            <?php if ($animeType): ?>
            <div class="ak-info-row"><span class="ak-info-label">Type</span><span class="ak-info-val"><?=app_e($animeType)?></span></div>
            <?php endif; ?>
            <?php if ($animeYear): ?>
            <div class="ak-info-row"><span class="ak-info-label">Year</span><span class="ak-info-val"><?=app_e($animeYear)?></span></div>
            <?php endif; ?>
            <?php if ($animeStatus): ?>
            <div class="ak-info-row"><span class="ak-info-label">Status</span><span class="ak-info-val"><?=app_e($animeStatus)?></span></div>
            <?php endif; ?>
            <?php if ($animeScore): ?>
            <div class="ak-info-row"><span class="ak-info-label">Score</span><span class="ak-info-val" style="color:#f59e0b">★ <?=app_e($animeScore)?></span></div>
            <?php endif; ?>
            <?php if ($totalEpisodes > 0): ?>
            <div class="ak-info-row"><span class="ak-info-label">Episodes</span><span class="ak-info-val"><?=$totalEpisodes?></span></div>
            <?php endif; ?>
            <?php if (!empty($animeStudios)): ?>
            <div class="ak-info-row"><span class="ak-info-label">Studio</span><span class="ak-info-val"><?=app_e(implode(', ', $animeStudios))?></span></div>
            <?php endif; ?>
            <?php if (!empty($animeGenres)): ?>
            <div class="ak-info-row" style="flex-direction:column;gap:6px">
              <span class="ak-info-label">Genres</span>
              <div style="display:flex;flex-wrap:wrap;gap:5px">
                <?php foreach ($animeGenres as $g): ?>
                <a href="<?=$websiteUrl?>/genre/<?=app_e(strtolower(str_replace(' ','-',$g)))?>"
                   style="padding:2px 8px;border-radius:4px;background:rgba(255,255,255,.06);font-size:11px;color:var(--text-secondary);text-decoration:none;border:1px solid rgba(255,255,255,.09)"><?=app_e($g)?></a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($synopsis): ?>
            <div class="ak-info-row" style="flex-direction:column;gap:6px">
              <span class="ak-info-label">Synopsis</span>
              <span class="ak-info-val" style="font-size:12px;line-height:1.6"><?=app_e(substr(strip_tags($synopsis),0,300))?>…</span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div><!-- /.ak-watch-side-box -->

      <!-- Show Comments -->
      <div class="ak-show-comments" id="toggleComments">
        <div class="ak-show-comments-left">
          <i class="fas fa-comments"></i> Show Comments
        </div>
        <span class="ak-show-comments-count">0</span>
      </div>

      <!-- You Might Also Like -->
      <?php if (!empty($recommendations)): ?>
      <div class="ak-ymal">
        <h4>You Might Also Like</h4>
        <div class="ak-ymal-scroll">
          <?php foreach ($recommendations as $rec):
            $rId  = (string)($rec['animeId']    ?? '');
            $rTit = (string)($rec['animeTitle'] ?? 'Unknown');
            $rImg = app_safe_image($rec['animeImg'] ?? '');
            if (!$rId) continue;
          ?>
          <a class="ak-ymal-card" href="<?=$websiteUrl?>/anime/<?=app_e($rId)?>" title="<?=app_e($rTit)?>">
            <img src="<?=app_e($rImg)?>" alt="<?=app_e($rTit)?>" loading="lazy"
                 onerror="this.src='<?=$websiteUrl?>/files/images/no_poster.jpg'">
            <div class="ak-ymal-card-title"><?=app_e($rTit)?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /ak-watch-side -->

  </div><!-- /ak-watch-layout -->
</div><!-- /ak-watch-page -->
</main>
</div><!-- /ak-page-wrap -->

<?php include('./_php/ak_footer.php'); ?>

<script>
(function(){
  var csrf       = '<?=app_e(app_csrf_token())?>';
  var isLoggedIn = <?=app_is_logged_in()?'true':'false'?>;
  var animeId    = '<?=app_e($slug)?>';
  var epNum      = <?=(int)$epNum?>;
  var nextUrl    = '<?=app_e($nextUrl2)?>';
  var prevUrl    = '<?=app_e($prevUrl)?>';
  var resumeSeconds = <?=(int)$resumeSeconds?>;

  var servers    = <?=json_encode(array_values($serverList), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
  var video      = document.getElementById('anime-player-video');
  var iframe     = document.getElementById('anime-player-iframe');
  var loadingEl  = document.getElementById('embed-loading');
  var switchMsg  = document.getElementById('ak-switch-msg');

  // hide loader on iframe load — also clear recovery timer so it doesn't auto-switch.
  // NOTE: we deliberately do NOT auto-advance iframe servers: providers like VidNest
  // play fine but never emit postMessage, so any "no signal = dead" heuristic would
  // wrongly skip a working stream. The primary server (VidNest) is verified-working;
  // users can switch manually via the server buttons if a specific title is missing.
  var _iframeReloading = false; // guard: ignore 'about:blank' interim load event
  if (iframe) {
    iframe.addEventListener('load', function(){
      if (_iframeReloading) return; // blank-src step during reload — skip
      clearRecoveryTimer();   // ← critical: stops the direct-stream auto-switch cascade
      hideLoader();
      showSwitchMessage('');
    });
  }


  var currentLoadToken = 0;
  var hlsInstance = null;

  function hideLoader() { if (loadingEl) loadingEl.style.display = 'none'; }
  function showLoader() { if (loadingEl) loadingEl.style.display = 'flex'; }

  function showSwitchMessage(msg) {
    if (!switchMsg) return;
    if (!msg) { switchMsg.classList.remove('visible'); switchMsg.innerHTML = ''; return; }
    var isLoading = msg.indexOf('Loading') !== -1 || msg.indexOf('Switching') !== -1 || msg.indexOf('Reloading') !== -1;
    switchMsg.innerHTML = (isLoading
      ? '<i class="fas fa-spinner fa-spin" style="margin-right:6px"></i>'
      : '<i class="fas fa-exclamation-circle" style="margin-right:6px;color:#f87171"></i>') + msg;
    switchMsg.style.color = isLoading ? '#f59e0b' : '#f87171';
    switchMsg.classList.add('visible');
  }

  /* ── Lazy-load hls.js only when an HLS stream is actually needed ── */
  var _hlsScriptLoading = false;
  var _hlsReadyQueue = [];
  var _hlsUrl = '<?=app_e($websiteUrl)?>/files/js/hls.js';
  function ensureHls(callback) {
    if (window.Hls && window.Hls.isSupported && window.Hls.isSupported()) { callback(); return; }
    _hlsReadyQueue.push(callback);
    if (_hlsScriptLoading) return;
    _hlsScriptLoading = true;
    var s = document.createElement('script');
    s.src = _hlsUrl;
    s.onload = function() {
      _hlsReadyQueue.forEach(function(fn){ try { fn(); } catch(e){} });
      _hlsReadyQueue = [];
    };
    s.onerror = function() {
      _hlsReadyQueue.forEach(function(fn){ try { fn(); } catch(e){} });
      _hlsReadyQueue = [];
    };
    document.head.appendChild(s);
  }

  /* ── Streaming health (lightweight HEAD) ── */
  async function isStreamAlive(url) {
    if (!url) return false;
    try {
      var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var timeoutId = controller ? setTimeout(function(){ controller.abort(); }, 3500) : null;
      var r = await fetch(url, { method: 'HEAD', cache: 'no-store', mode: 'no-cors', signal: controller ? controller.signal : undefined });
      if (timeoutId) clearTimeout(timeoutId);
      // opaque responses from no-cors can't be trusted; still treat as reachable
      return (r && (r.ok || r.status === 200));
    } catch (e) {
      return false;
    }
  }

  /* ── Auto Play / Auto Next preferences (persisted, default on) ──
     Mirrors the toggle row providers like Anikoto show above their player.
     Applied to embeds that document query-param support (VidLink) and to
     our own postMessage-driven next-episode overlay for any provider that
     tells us it ended. ── */
  function readPref(key) { var v = localStorage.getItem(key); return v === null ? true : v === '1'; }
  var autoPlayEnabled = readPref('ak_autoplay');
  var autoNextEnabled = readPref('ak_autonext');
  var lastKnownPosition = resumeSeconds > 0 ? resumeSeconds : 0;

  /* ── Build a provider-aware embed URL ──
     VidLink and VidNest both document query params for resume position,
     autoplay, and hiding their own duplicate prev/next-episode controls
     (we already show those via the corner nav) — see vidlink.pro and
     vidnest.fun API docs. Anything else is passed through unmodified. */
  function buildEmbedUrl(srv) {
    var url = srv.url || '';
    try {
      var u = new URL(url);
      if (u.hostname.indexOf('vidlink.pro') !== -1) {
        if (lastKnownPosition > 5) u.searchParams.set('startAt', String(Math.floor(lastKnownPosition)));
        u.searchParams.set('autoplay', autoPlayEnabled ? 'true' : 'false');
        u.searchParams.set('nextbutton', 'false'); // we render our own next-episode overlay
        u.searchParams.set('title', 'false');
        u.searchParams.set('poster', 'false');
        u.searchParams.set('primaryColor', 'c8102e');
        u.searchParams.set('iconColor', 'c8102e');
      } else if (u.hostname.indexOf('vidnest.fun') !== -1) {
        if (lastKnownPosition > 5) u.searchParams.set('startAt', String(Math.floor(lastKnownPosition)));
        u.searchParams.set('prevepisode', 'hide');
        u.searchParams.set('nextepisode', 'hide'); // avoid a second, out-of-sync nav UI
      }
      return u.toString();
    } catch (e) {
      return url; // malformed/relative URL — fall back to the raw value
    }
  }

  /* ── Player logic split ── */
  function loadIframePlayer(srv) {
    if (!iframe) return;
    showLoader();
    if (hlsInstance) { try { hlsInstance.destroy(); } catch (e) {} hlsInstance = null; }
    if (video) { video.pause(); video.removeAttribute('src'); video.style.display = 'none'; }
    vpHide(); // hide custom controls — iframe has its own built-in controls

    iframe.src = buildEmbedUrl(srv);
    iframe.style.display = 'block';

    // iframe load event will hide loader
  }

  function loadVideoPlayer(srv) {
    if (!video) return;
    showLoader();
    if (iframe) { iframe.style.display = 'none'; iframe.src = 'about:blank'; }

    vpInit(srv.subtitles || []); // show custom controls, pass subtitle tracks

    video.style.display = 'block';
    video.pause();
    video.removeAttribute('src');

    // Resume from saved position (once per load) — only meaningful for
    // direct streams; iframe providers give us no access to seek their player.
    var _resumeApplied = false;
    function applyResumeOnce() {
      if (_resumeApplied) return;
      _resumeApplied = true;
      if (resumeSeconds > 5 && video.duration && resumeSeconds < video.duration - 10) {
        try { video.currentTime = resumeSeconds; } catch (e) {}
      }
    }

    // reset previous listeners
    video.oncanplay = function(){ hideLoader(); applyResumeOnce(); };
    video.onerror = function(){
      showSwitchMessage('Stream unavailable. Trying fallback...');
      tryNextServer();
    };

    var type = (srv.type || '').toLowerCase();

    // HLS.js for .m3u8 — load hls.js lazily only when needed
    if (type === 'hls' || String(srv.url || '').toLowerCase().includes('.m3u8')) {
      var _srvUrl = srv.url;
      ensureHls(function() {
        if (window.Hls && window.Hls.isSupported && window.Hls.isSupported()) {
          try {
            if (hlsInstance) { try { hlsInstance.destroy(); } catch (e) {} }
            hlsInstance = new Hls({ enableWorker: false });
            hlsInstance.attachMedia(video);
            hlsInstance.loadSource(_srvUrl);
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, function(){
              var p = video.play();
              if (p && typeof p.catch === 'function') p.catch(function(){});
              setTimeout(hideLoader, 300);
              vpSetupQuality(); // populate quality levels after manifest
            });
            hlsInstance.on(Hls.Events.ERROR, function(event, data){
              if (data && data.fatal) {
                showSwitchMessage('HLS failed. Trying fallback...');
                tryNextServer();
              }
            });
          } catch (e) {
            video.src = _srvUrl;
          }
        } else {
          video.src = _srvUrl;
        }
      });
      return; // ensureHls is async — return early, callback handles playback
    } else {
      // mp4 or unknown direct URL
      video.src = srv.url;
      var p2 = video.play();
      if (p2 && typeof p2.catch === 'function') p2.catch(function(){});
    }
  }

  var serverIndex = 0;
  function setActiveServer(idx) {
    serverIndex = idx;
    document.querySelectorAll('.ak-sbar-btn').forEach(function(b){
      b.classList.toggle('active', parseInt(b.dataset.idx || '0', 10) === idx);
    });
  }

  /* ── Auto-recovery timeout → switch (direct streams only) ── */
  var recoveryTimer = null;
  function startRecoveryTimer() {
    clearTimeout(recoveryTimer);
    // 18 s for HLS/mp4; iframes fire this but the iframe load event clears it
    recoveryTimer = setTimeout(function(){
      showSwitchMessage('Switching server due to loading timeout...');
      tryNextServer();
    }, 18000);
  }

  function clearRecoveryTimer() { clearTimeout(recoveryTimer); recoveryTimer = null; }

  function tryNextServer() {
    clearRecoveryTimer();
    var next = serverIndex + 1;
    if (next >= servers.length) {
      showSwitchMessage('Stream unavailable. Please try another server/episode.');
      hideLoader();
      return;
    }
    setActiveServer(next);
    loadServer(next);
  }

  /* ── Load a server (with health check) ── */
  async function loadServer(idx) {
    var token = ++currentLoadToken;
    var srv = servers[idx];
    if (!srv) return;

    showSwitchMessage(srv && srv.name ? ('Loading ' + srv.name + '...') : 'Loading stream...');
    showLoader();
    clearRecoveryTimer();
    // Only auto-switch on timeout for direct streams; iframes clear the timer themselves on load
    var _srvType = (srv.type || '').toLowerCase();
    if (_srvType !== 'iframe') {
      startRecoveryTimer();
    }

    // health check to skip dead ones
    if (srv.type === 'iframe' || (srv.type || '').toLowerCase() === 'iframe') {
      // iframe providers: skip HEAD (CORS/no-cors) and just try
    } else {
      var alive = await isStreamAlive(srv.url);
      if (token !== currentLoadToken) return;
      if (!alive) {
        showSwitchMessage('Stream unavailable. Trying fallback...');
        return tryNextServer();
      }
    }

    setActiveServer(idx);

    var type = (srv.type || '').toLowerCase();
    if (type === 'hls' || type === 'mp4' || type === 'm3u8') {
      loadVideoPlayer(srv);
    } else {
      loadIframePlayer(srv);
      // loader reset on iframe load
    }

    // hide loader as a safety net (real hide happens on canplay/onerror or iframe load)
    setTimeout(function(){ if (token === currentLoadToken) hideLoader(); }, 15000);
  }

  /* ── Auto-load first iframe server immediately ── */
  (function(){
    if (!servers.length) return;
    setActiveServer(0);
    loadServer(0);
  })();

  /* ── Async: fetch HLS/direct streams from api/watch_v2 and prepend ── */
  function escHtml(s) {
    return String(s||'').replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function rebuildServerButtons() {
    var container = document.getElementById('serverBtns');
    if (!container) return;
    container.innerHTML = '';
    servers.forEach(function(srv, idx) {
      var btn = document.createElement('button');
      btn.className = 'ak-sbar-btn' + (idx === serverIndex ? ' active' : '');
      btn.dataset.idx = String(idx);
      var langHtml = srv.lang ? ' <span style="opacity:.7;font-size:10px">' + escHtml(String(srv.lang).toUpperCase()) + '</span>' : '';
      btn.innerHTML = '<i class="fas fa-circle-play"></i> ' + escHtml(srv.name || 'Server ' + (idx+1)) + langHtml;
      btn.addEventListener('click', function() {
        document.querySelectorAll('.ak-sbar-btn').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        loadServer(parseInt(btn.dataset.idx || '0', 10));
      });
      container.appendChild(btn);
    });
  }

  (function fetchHlsServers(){
    var episodeId = '<?=app_e($episodeIdForApi)?>';
    fetch('<?=$websiteUrl?>/api/watch_v2.php?episodeId=' + encodeURIComponent(episodeId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        var raw = (data && data.data && Array.isArray(data.data.servers)) ? data.data.servers : [];
        // Only prepend HLS/direct (non-iframe) sources
        var direct = raw.filter(function(s){ return s.type === 'hls' || s.type === 'mp4'; });
        if (!direct.length) return;
        var formatted = direct.map(function(s){
          return { name: s.name||'Stream', url: s.playbackUrl||'', type: s.type||'hls',
                   quality: s.quality||'', lang: s.lang||'sub', providerId: s.providerId||'',
                   subtitles: Array.isArray(s.subtitles) ? s.subtitles : [] };
        }).filter(function(s){ return s.url !== ''; });
        if (!formatted.length) return;
        // Prepend HLS servers before existing iframe fallbacks
        servers = formatted.concat(servers);
        // Rebuild buttons & auto-switch to the best stream
        rebuildServerButtons();
        setActiveServer(0);
        loadServer(0);
      })
      .catch(function(){/* silent — iframes still work */});
  })();

  /* ── Server button clicks (initial PHP-rendered buttons) ── */
  document.querySelectorAll('.ak-sbar-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.ak-sbar-btn').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      if (switchMsg) { switchMsg.style.display = 'block'; }
      loadServer(parseInt(btn.dataset.idx || '0', 10));
    });
  });

  /* ── Light toggle ── */
  var lightBtn = document.getElementById('lightToggle');
  var mask = null;
  if (lightBtn) {
    lightBtn.addEventListener('click', function() {
      if (!mask) {
        mask = document.createElement('div');
        mask.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:500;cursor:pointer';
        mask.addEventListener('click', function(){ mask.remove(); mask=null; lightBtn.classList.remove('active'); });
        document.body.appendChild(mask);
        document.getElementById('akPlayerShell').style.position = 'relative';
        document.getElementById('akPlayerShell').style.zIndex = '501';
        lightBtn.classList.add('active');
      } else {
        mask.remove(); mask = null;
        document.getElementById('akPlayerShell').style.zIndex = '';
        lightBtn.classList.remove('active');
      }
    });
  }

  /* ── Player controls: fullscreen, reload, keyboard shortcuts ── */
  var playerShell = document.getElementById('akPlayerShell');

  function toggleFullscreen() {
    if (!playerShell) return;
    if (document.fullscreenElement || document.webkitFullscreenElement) {
      (document.exitFullscreen || document.webkitExitFullscreen || function(){}).call(document);
    } else {
      var req = playerShell.requestFullscreen || playerShell.webkitRequestFullscreen;
      if (req) req.call(playerShell);
    }
  }

  function reloadCurrentServer() {
    clearRecoveryTimer();
    var srv = servers[serverIndex];
    if (!srv) return;
    showSwitchMessage('Reloading ' + (srv.name || 'stream') + '...');
    showLoader();
    var type = (srv.type || '').toLowerCase();
    if (type === 'iframe') {
      if (iframe) {
        var u = iframe.src;
        _iframeReloading = true;
        iframe.src = 'about:blank';
        setTimeout(function(){
          _iframeReloading = false;
          iframe.src = u;
        }, 100);
      }
    } else {
      loadServer(serverIndex);
    }
  }

  // Fullscreen icon swap — only the video-mode button still exists
  // (akVpFsBtn); the iframe-mode corner cluster has no fullscreen button at
  // all now. F key / provider's own fullscreen button cover that case —
  // duplicating it risked the same click-blocking problem the corner
  // cluster was built to avoid.
  function _updateFsIcon() {
    var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
    var cls = isFs ? 'fas fa-compress' : 'fas fa-expand';
    var vpFsBtn = document.getElementById('akVpFsBtn');
    var icon2 = vpFsBtn ? vpFsBtn.querySelector('i') : null;
    if (icon2) icon2.className = cls;
  }
  document.addEventListener('fullscreenchange', _updateFsIcon);
  document.addEventListener('webkitfullscreenchange', _updateFsIcon);

  // Reload button
  var pcReloadBtn = document.getElementById('pcReloadBtn');
  if (pcReloadBtn) {
    pcReloadBtn.addEventListener('click', reloadCurrentServer);
  }

  /* ── Corner controls + keyboard-hint trigger: same reveal mechanism for
     mouse (CSS :hover) and touch (tap toggles .touch-show, auto-hides
     after 3s) — one control system, not a different one per device. ── */
  var akCornerCtl  = document.getElementById('akCornerCtl');
  var akKbdTrigger = document.getElementById('akKbdTrigger');
  var akKbdHints   = document.getElementById('akKbdHints');
  var _touchHideTimer = null;
  if (playerShell && akCornerCtl) {
    playerShell.addEventListener('touchstart', function(e){
      // Don't hijack taps on the controls themselves (e.g. tapping Next
      // immediately after the reveal-tap shouldn't need a second tap).
      if (e.target.closest('.ak-corner-ctl') || e.target.closest('.ak-kbd-trigger') || e.target.closest('.ak-kbd-hints')) return;
      akCornerCtl.classList.add('touch-show');
      if (akKbdTrigger) akKbdTrigger.classList.add('touch-show');
      clearTimeout(_touchHideTimer);
      _touchHideTimer = setTimeout(function(){
        akCornerCtl.classList.remove('touch-show');
        if (akKbdTrigger) akKbdTrigger.classList.remove('touch-show');
      }, 3000);
    }, {passive: true});
  }
  if (akKbdTrigger && akKbdHints) {
    akKbdTrigger.addEventListener('click', function(e){
      e.stopPropagation();
      akKbdHints.classList.toggle('open');
    });
    document.addEventListener('click', function(){ akKbdHints.classList.remove('open'); });
  }

  /* ── Theater mode ── */
  var akWatchPage   = document.getElementById('akWatchPage');
  var akWatchLayout = document.getElementById('akWatchLayout');
  var pcTheaterBtn  = document.getElementById('pcTheaterBtn');
  function setTheaterMode(on) {
    if (akWatchLayout) akWatchLayout.classList.toggle('theater-mode', on);
    if (akWatchPage)   akWatchPage.classList.toggle('theater-mode-page', on);
    if (pcTheaterBtn)  pcTheaterBtn.classList.toggle('active', on);
    try { localStorage.setItem('ak_theater_mode', on ? '1' : '0'); } catch (e) {}
  }
  if (pcTheaterBtn) {
    pcTheaterBtn.addEventListener('click', function(e){
      e.stopPropagation();
      setTheaterMode(!akWatchLayout.classList.contains('theater-mode'));
    });
  }
  try {
    if (localStorage.getItem('ak_theater_mode') === '1') setTheaterMode(true);
  } catch (e) {}

  // Keyboard shortcuts (global + video-mode)
  document.addEventListener('keydown', function(e) {
    var tag = (e.target.tagName || '').toUpperCase();
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    var inVideoMode = vpEl && vpEl.style.display !== 'none';
    switch (e.key) {
      case ' ': case 'k': case 'K':
        if (inVideoMode) { vpTogglePlay(); e.preventDefault(); }
        break;
      case 'j': case 'J':
        if (inVideoMode && video) { video.currentTime = Math.max(0, video.currentTime - 10); vpFlashSkip(-1); e.preventDefault(); }
        break;
      case 'l': case 'L':
        if (inVideoMode && video) { video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 10); vpFlashSkip(1); e.preventDefault(); }
        break;
      case 'm': case 'M':
        if (inVideoMode && video) { video.muted = !video.muted; vpVolSync(); e.preventDefault(); }
        break;
      case 'ArrowUp':
        if (inVideoMode && video) { video.volume = Math.min(1, video.volume + 0.1); video.muted = false; vpVolSync(); e.preventDefault(); }
        break;
      case 'ArrowDown':
        if (inVideoMode && video) { video.volume = Math.max(0, video.volume - 0.1); vpVolSync(); e.preventDefault(); }
        break;
      case 'f': case 'F':
        toggleFullscreen();
        e.preventDefault();
        break;
      case 'r': case 'R':
        if (!inVideoMode) { reloadCurrentServer(); e.preventDefault(); }
        break;
      case 'n': case 'N':
        if (nextUrl) { cancelNextCountdown(); window.location.href = nextUrl; e.preventDefault(); }
        break;
      case 'ArrowRight':
        // In video mode, arrows seek ±5s (no access to seek iframe players).
        if (inVideoMode && video) { video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 5); vpFlashSkip(1); e.preventDefault(); }
        else if (!inVideoMode && nextUrl) { window.location.href = nextUrl; e.preventDefault(); }
        break;
      case 'ArrowLeft':
        if (inVideoMode && video) { video.currentTime = Math.max(0, video.currentTime - 5); vpFlashSkip(-1); e.preventDefault(); }
        else if (!inVideoMode && prevUrl) { window.location.href = prevUrl; e.preventDefault(); }
        break;
      case 'Escape':
        if (document.fullscreenElement || document.webkitFullscreenElement) {
          (document.exitFullscreen || document.webkitExitFullscreen || function(){}).call(document);
        }
        break;
    }
  });

  /* ── Watchlist ── */
  function bindWatchlist(btnId) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function() {
      fetch('<?=$websiteUrl?>/api/user.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'watchlist_toggle', csrf:csrf, anime_id:animeId}).toString()
      }).then(function(r){ return r.json(); }).then(function(d){
        if (!d.ok) return;
        var saved = d.in_watchlist;
        document.querySelectorAll('#watchlistBtn,#watchlistBtn2').forEach(function(b){
          b.classList.toggle('active', saved);
          b.classList.toggle('saved', saved);
          if (b.id === 'watchlistBtn') {
            b.innerHTML = '<i class="fas fa-' + (saved?'bookmark':'plus') + '"></i> ' + (saved?'Saved':'Add to List');
          } else {
            b.innerHTML = '<i class="fas fa-bookmark"></i> ' + (saved?'Saved':'Add to List');
          }
        });
      }).catch(function(){});
    });
  }
  bindWatchlist('watchlistBtn');
  bindWatchlist('watchlistBtn2');

  /* ── Anime list status (Watching/Completed/On Hold/Dropped/Plan to Watch) ── */
  var listStatusMeta = {
    watching:      { label: 'Watching',      icon: 'fa-eye' },
    completed:     { label: 'Completed',     icon: 'fa-circle-check' },
    on_hold:       { label: 'On Hold',        icon: 'fa-pause' },
    dropped:       { label: 'Dropped',        icon: 'fa-circle-xmark' },
    plan_to_watch: { label: 'Plan to Watch',  icon: 'fa-clock' }
  };
  var listStatusToggle = document.getElementById('listStatusToggle');
  var listStatusMenu   = document.getElementById('listStatusMenu');
  if (listStatusToggle && listStatusMenu) {
    listStatusToggle.addEventListener('click', function(e){
      e.stopPropagation();
      listStatusMenu.style.display = listStatusMenu.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', function(){ listStatusMenu.style.display = 'none'; });
    listStatusMenu.querySelectorAll('.ak-list-status-opt').forEach(function(opt){
      opt.addEventListener('click', function(e){
        e.stopPropagation();
        var status = opt.dataset.status;
        fetch('<?=$websiteUrl?>/api/user.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({action:'list_status_set', csrf:csrf, anime_id:animeId, status:status}).toString()
        }).then(function(r){ return r.json(); }).then(function(d){
          if (!d.ok) return;
          listStatusMenu.style.display = 'none';
          var meta = d.status ? listStatusMeta[d.status] : null;
          listStatusToggle.classList.toggle('saved', !!meta);
          listStatusToggle.innerHTML = (meta
            ? '<i class="fas ' + meta.icon + '"></i> ' + meta.label
            : '<i class="fas fa-list-ul"></i> Track Progress'
          ) + ' <i class="fas fa-chevron-down" style="font-size:9px;opacity:.6;margin-left:2px"></i>';
        }).catch(function(){});
      });
    });
  }

  /* ── Read More ── */
  var rmBtn = document.getElementById('readMoreBtn');
  var synTxt = document.getElementById('synopsisText');
  var rmOpen = false;
  if (rmBtn && synTxt) {
    rmBtn.addEventListener('click', function(){
      rmOpen = !rmOpen;
      synTxt.classList.toggle('collapsed', !rmOpen);
      rmBtn.innerHTML = rmOpen
        ? 'Show Less <i class="fas fa-chevron-up"></i>'
        : 'Read More <i class="fas fa-chevron-down"></i>';
    });
  }

  /* ── Tabs (Episodes / Info) ── */
  document.querySelectorAll('.ak-watch-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.ak-watch-tab-btn').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      var tab = btn.dataset.tab;
      document.getElementById('tab-episodes').style.display = tab==='episodes' ? '' : 'none';
      document.getElementById('tab-info').style.display     = tab==='info'     ? '' : 'none';
    });
  });

  /* ── Season dropdown ── */
  var seasonBtn  = document.getElementById('seasonDropBtn');
  var seasonMenu = document.getElementById('seasonDropMenu');
  var seasonLabel= document.getElementById('seasonDropLabel');
  if (seasonBtn && seasonMenu) {
    // Toggle open/close
    seasonBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      var isOpen = seasonMenu.style.display !== 'none';
      seasonMenu.style.display = isOpen ? 'none' : 'block';
    });
    // Click outside closes
    document.addEventListener('click', function() {
      if (seasonMenu) seasonMenu.style.display = 'none';
    });
    // Season option clicks
    seasonMenu.querySelectorAll('.ak-season-opt').forEach(function(opt) {
      opt.addEventListener('click', function(e) {
        e.stopPropagation();
        var idx = parseInt(opt.dataset.seasonIdx || '0', 10);
        // Update label
        if (seasonLabel) seasonLabel.textContent = opt.textContent.split('(')[0].trim();
        // Show/hide panels
        document.querySelectorAll('.ak-season-panel').forEach(function(p) { p.style.display = 'none'; });
        var panel = document.getElementById('season-panel-' + idx);
        if (panel) panel.style.display = '';
        // Update active state
        seasonMenu.querySelectorAll('.ak-season-opt').forEach(function(o) {
          o.style.color = '';
        });
        opt.style.color = 'var(--accent)';
        seasonMenu.style.display = 'none';
      });
    });
  }

  /* ── Jump to episode # ── */
  var epJumpInput = document.getElementById('epJumpInput');
  var epJumpBtn   = document.getElementById('epJumpBtn');
  function goToEpisode() {
    if (!epJumpInput) return;
    var n = parseInt(epJumpInput.value, 10);
    if (!n || n < 1) return;
    window.location.href = '<?=$websiteUrl?>/watch/<?=app_e($slug)?>-episode-' + n;
  }
  if (epJumpBtn)   epJumpBtn.addEventListener('click', goToEpisode);
  if (epJumpInput) epJumpInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') goToEpisode(); });

  /* ── Filter episode list by number or title (client-side, current range only) ── */
  var epFilterInput = document.getElementById('epFilterInput');
  if (epFilterInput) {
    epFilterInput.addEventListener('input', function() {
      var q = epFilterInput.value.trim().toLowerCase();
      document.querySelectorAll('.ak-ep-item').forEach(function(item) {
        if (!q) { item.classList.remove('ak-ep-hidden-filter'); return; }
        var num = (item.dataset.epnum || '').toLowerCase();
        var tit = (item.dataset.eptitle || '').toLowerCase();
        var match = num.indexOf(q) !== -1 || tit.indexOf(q) !== -1;
        item.classList.toggle('ak-ep-hidden-filter', !match);
      });
    });
  }

  /* ── Scroll active episode into view ── */
  var curEpEl = document.getElementById('currentEpItem');
  if (curEpEl) {
    setTimeout(function(){
      curEpEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, 300);
  }

  /* ── Share ── */
  var shareBtn = document.getElementById('shareBtn');
  if (shareBtn) {
    shareBtn.addEventListener('click', function(){
      if (navigator.share) {
        navigator.share({ title: document.title, url: window.location.href }).catch(function(){});
      } else {
        navigator.clipboard.writeText(window.location.href).then(function(){
          shareBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
          setTimeout(function(){ shareBtn.innerHTML = '<i class="fas fa-share-alt"></i> Share'; }, 2000);
        }).catch(function(){});
      }
    });
  }

  /* ══════════════════════════════════════════════════════════════
     Custom Video Player (ak-vp) — play/pause, seek, skip, vol,
     settings (speed + quality), CC/subtitles, auto-hide
  ══════════════════════════════════════════════════════════════ */
  var vpEl         = document.getElementById('akVp');
  var vpPlayBtn    = document.getElementById('akVpPlayBtn');
  var vpMuteBtn    = document.getElementById('akVpMuteBtn');
  var vpVolSlider  = document.getElementById('akVpVolSlider');
  var vpTimeEl     = document.getElementById('akVpTime');
  var vpProgress   = document.getElementById('akVpProgress');
  var vpFill       = document.getElementById('akVpFill');
  var vpBuf        = document.getElementById('akVpBuf');
  var vpHandle     = document.getElementById('akVpHandle');
  var vpSkipBBtn   = document.getElementById('akVpSkipBBtn');
  var vpSkipFBtn   = document.getElementById('akVpSkipFBtn');
  var vpSetBtn     = document.getElementById('akVpSetBtn');
  var vpCCBtn      = document.getElementById('akVpCCBtn');
  var vpSetPanel   = document.getElementById('akVpSetPanel');
  var vpCCPanel    = document.getElementById('akVpCCPanel');
  var vpSkipBackInd= document.getElementById('akVpSkipBackInd');
  var vpSkipFwdInd = document.getElementById('akVpSkipFwdInd');
  var vpCenterPlay = document.getElementById('akVpCenterPlay');
  var vpSubsEl     = document.getElementById('akVpSubs');
  var vpSpeedOpts  = document.getElementById('akVpSpeedOpts');
  var vpQualOpts   = document.getElementById('akVpQualOpts');
  var vpQualHd     = document.getElementById('akVpQualHd');
  var vpCCOpts     = document.getElementById('akVpCCOpts');

  var _vpHideTimer  = null;
  var _vpSeeking    = false;
  var _vpSubtitles  = [];
  var _vpCCTrack    = -1;
  var _vpSubCues    = [];

  /* ── Helpers ── */
  function vpFmt(s) {
    s = Math.floor(s || 0);
    var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    if (h > 0) return h + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
    return m + ':' + String(sec).padStart(2,'0');
  }

  function vpUpdateProgress() {
    if (!video || _vpSeeking) return;
    var dur = video.duration || 0, cur = video.currentTime || 0;
    var pct = dur > 0 ? (cur / dur * 100) : 0;
    if (vpFill)   vpFill.style.width  = pct + '%';
    if (vpHandle) vpHandle.style.left = pct + '%';
    var bufPct = 0;
    if (dur > 0 && video.buffered && video.buffered.length)
      bufPct = video.buffered.end(video.buffered.length - 1) / dur * 100;
    if (vpBuf)   vpBuf.style.width = bufPct + '%';
    if (vpTimeEl) vpTimeEl.textContent = vpFmt(cur) + ' / ' + vpFmt(dur);
  }

  function vpUpdatePlayIcon() {
    if (!vpPlayBtn || !video) return;
    var icon = vpPlayBtn.querySelector('i');
    if (icon) icon.className = video.paused ? 'fas fa-play' : 'fas fa-pause';
  }

  function vpVolSync() {
    if (!video) return;
    if (vpVolSlider) vpVolSlider.value = video.muted ? 0 : video.volume;
    if (vpMuteBtn) {
      var icon = vpMuteBtn.querySelector('i');
      if (!icon) return;
      var v = video.muted ? 0 : video.volume;
      icon.className = v === 0 ? 'fas fa-volume-xmark' : v < 0.4 ? 'fas fa-volume-low' : 'fas fa-volume-high';
    }
  }

  /* ── Control visibility / auto-hide ── */
  function vpShowControls() {
    if (!vpEl) return;
    vpEl.classList.add('show-ctl');
    vpEl.classList.remove('hide-cursor');
    clearTimeout(_vpHideTimer);
    if (video && !video.paused) {
      _vpHideTimer = setTimeout(function(){
        if (video && !video.paused) {
          vpEl.classList.remove('show-ctl');
          vpEl.classList.add('hide-cursor');
        }
      }, 3000);
    }
  }

  /* ── Skip flash indicators ── */
  var _skipBackTimer = null, _skipFwdTimer = null;
  function vpFlashSkip(dir) {
    if (dir < 0) {
      if (vpSkipBackInd) { vpSkipBackInd.classList.add('show'); clearTimeout(_skipBackTimer); _skipBackTimer = setTimeout(function(){ vpSkipBackInd.classList.remove('show'); }, 650); }
    } else {
      if (vpSkipFwdInd)  { vpSkipFwdInd.classList.add('show');  clearTimeout(_skipFwdTimer);  _skipFwdTimer  = setTimeout(function(){ vpSkipFwdInd.classList.remove('show');  }, 650); }
    }
  }

  /* ── Center play/pause flash ── */
  var _centerTimer = null;
  function vpFlashCenter(iconCls) {
    if (!vpCenterPlay) return;
    var i = vpCenterPlay.querySelector('i');
    if (i) i.className = iconCls;
    vpCenterPlay.classList.add('pop');
    clearTimeout(_centerTimer);
    _centerTimer = setTimeout(function(){ vpCenterPlay.classList.remove('pop'); }, 450);
  }

  /* ── Play / Pause ── */
  function vpTogglePlay() {
    if (!video || !vpEl || vpEl.style.display === 'none') return;
    if (video.paused) {
      var p = video.play(); if (p && p.catch) p.catch(function(){});
      vpFlashCenter('fas fa-play');
    } else {
      video.pause();
      vpFlashCenter('fas fa-pause');
      vpShowControls();
    }
  }

  /* ── Seek bar ── */
  function vpSeekPct(pct) {
    if (!video || !video.duration) return;
    video.currentTime = Math.max(0, Math.min(video.duration, pct * video.duration));
    vpUpdateProgress();
  }
  function vpPctFromEvent(e) {
    var rect = vpProgress.getBoundingClientRect();
    var x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
    return Math.max(0, Math.min(1, x / rect.width));
  }
  if (vpProgress) {
    vpProgress.addEventListener('mousedown', function(e){ _vpSeeking = true; vpSeekPct(vpPctFromEvent(e)); e.preventDefault(); });
    document.addEventListener('mousemove', function(e){ if (_vpSeeking) vpSeekPct(vpPctFromEvent(e)); });
    document.addEventListener('mouseup',   function(e){ if (_vpSeeking){ _vpSeeking = false; vpSeekPct(vpPctFromEvent(e)); } });
    vpProgress.addEventListener('touchstart', function(e){ _vpSeeking = true; vpSeekPct(vpPctFromEvent(e)); }, {passive:true});
    vpProgress.addEventListener('touchmove',  function(e){ if (_vpSeeking) vpSeekPct(vpPctFromEvent(e)); }, {passive:true});
    vpProgress.addEventListener('touchend',   function(){ _vpSeeking = false; });
  }

  /* ── Button event bindings ── */
  if (vpPlayBtn)   vpPlayBtn.addEventListener('click',  function(e){ e.stopPropagation(); vpTogglePlay(); });
  if (vpSkipBBtn)  vpSkipBBtn.addEventListener('click', function(e){ e.stopPropagation(); if (video){ video.currentTime = Math.max(0, video.currentTime - 10); vpFlashSkip(-1); } });
  if (vpSkipFBtn)  vpSkipFBtn.addEventListener('click', function(e){ e.stopPropagation(); if (video){ video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 10); vpFlashSkip(1); } });
  if (vpMuteBtn)   vpMuteBtn.addEventListener('click',  function(e){ e.stopPropagation(); if (video){ video.muted = !video.muted; vpVolSync(); } });
  if (vpVolSlider) vpVolSlider.addEventListener('input', function(){ if (video){ video.volume = parseFloat(vpVolSlider.value); video.muted = video.volume === 0; vpVolSync(); } });

  // Fullscreen from ak-vp button
  var _akVpFsBtn = document.getElementById('akVpFsBtn');
  if (_akVpFsBtn) _akVpFsBtn.addEventListener('click', function(e){ e.stopPropagation(); toggleFullscreen(); });

  // Picture-in-Picture (browser-native; only works for the <video> element —
  // not available for iframe-embedded providers, which run their own player).
  var _akVpPipBtn = document.getElementById('akVpPipBtn');
  if (_akVpPipBtn) {
    if (!document.pictureInPictureEnabled || !video || !video.requestPictureInPicture) {
      _akVpPipBtn.style.display = 'none';
    } else {
      _akVpPipBtn.addEventListener('click', function(e){
        e.stopPropagation();
        if (document.pictureInPictureElement) {
          document.exitPictureInPicture().catch(function(){});
        } else if (video) {
          video.requestPictureInPicture().catch(function(){});
        }
      });
    }
  }

  /* ── Auto-next countdown (direct video streams only, last 30s) ── */
  var akVpNextOverlay = document.getElementById('akVpNextOverlay');
  var akVpNextCount   = document.getElementById('akVpNextCount');
  var akVpNextCancel  = document.getElementById('akVpNextCancel');
  var _nextCountdownTimer = null;
  var _nextCountdownShown = false;
  var _nextCountdownCancelled = false;

  function startNextCountdown() {
    if (!akVpNextOverlay || !nextUrl || _nextCountdownShown || _nextCountdownCancelled) return;
    _nextCountdownShown = true;
    akVpNextOverlay.style.display = 'flex';
    var secs = 5;
    if (akVpNextCount) akVpNextCount.textContent = String(secs);
    clearInterval(_nextCountdownTimer);
    _nextCountdownTimer = setInterval(function(){
      secs -= 1;
      if (akVpNextCount) akVpNextCount.textContent = String(Math.max(0, secs));
      if (secs <= 0) {
        clearInterval(_nextCountdownTimer);
        window.location.href = nextUrl;
      }
    }, 1000);
  }
  function cancelNextCountdown() {
    _nextCountdownCancelled = true;
    clearInterval(_nextCountdownTimer);
    if (akVpNextOverlay) akVpNextOverlay.style.display = 'none';
  }
  if (akVpNextCancel) akVpNextCancel.addEventListener('click', function(e){ e.stopPropagation(); cancelNextCountdown(); });
  if (video) {
    video.addEventListener('timeupdate', function(){
      if (!video.duration || _nextCountdownShown || _nextCountdownCancelled) return;
      if (video.duration - video.currentTime <= 30) startNextCountdown();
    });
    video.addEventListener('ended', function(){ if (nextUrl && !_nextCountdownCancelled) window.location.href = nextUrl; });
  }

  // Settings panel toggle
  if (vpSetBtn) vpSetBtn.addEventListener('click', function(e){
    e.stopPropagation();
    if (vpCCPanel)  vpCCPanel.style.display  = 'none';
    if (vpSetPanel) vpSetPanel.style.display = vpSetPanel.style.display === 'none' ? 'block' : 'none';
  });

  // CC panel toggle
  if (vpCCBtn) vpCCBtn.addEventListener('click', function(e){
    e.stopPropagation();
    if (vpSetPanel) vpSetPanel.style.display = 'none';
    if (vpCCPanel)  vpCCPanel.style.display  = vpCCPanel.style.display  === 'none' ? 'block' : 'none';
  });

  // Close panels on outside click
  document.addEventListener('click', function(){
    if (vpSetPanel) vpSetPanel.style.display = 'none';
    if (vpCCPanel)  vpCCPanel.style.display  = 'none';
  });

  // Speed options
  if (vpSpeedOpts) {
    vpSpeedOpts.querySelectorAll('.ak-vp-popt').forEach(function(opt){
      opt.addEventListener('click', function(e){
        e.stopPropagation();
        vpSpeedOpts.querySelectorAll('.ak-vp-popt').forEach(function(o){ o.classList.remove('active'); });
        opt.classList.add('active');
        if (video) video.playbackRate = parseFloat(opt.dataset.speed || '1');
        if (vpSetPanel) vpSetPanel.style.display = 'none';
      });
    });
  }

  /* ── Click on player overlay = toggle play/pause ── */
  if (vpEl) {
    vpEl.addEventListener('click', function(e){
      // only act on clicks on the backdrop, not on control elements
      if (e.target.closest('.ak-vp-controls') || e.target.closest('.ak-vp-panel')) return;
      vpTogglePlay();
    });
    vpEl.addEventListener('mousemove', vpShowControls);
    vpEl.addEventListener('touchstart', vpShowControls, {passive:true});
  }

  /* ── Video events ── */
  if (video) {
    video.addEventListener('timeupdate',    vpUpdateProgress);
    video.addEventListener('progress',      vpUpdateProgress);
    video.addEventListener('durationchange',vpUpdateProgress);
    video.addEventListener('play',          vpUpdatePlayIcon);
    video.addEventListener('pause',         function(){ vpUpdatePlayIcon(); vpShowControls(); });
    video.addEventListener('volumechange',  vpVolSync);
    video.addEventListener('timeupdate',    vpUpdateSubs);
  }

  /* ── Quality levels (called after HLS manifest is parsed) ── */
  function vpSetupQuality() {
    if (!hlsInstance || !vpQualOpts || !vpQualHd) return;
    var levels = hlsInstance.levels || [];
    if (!levels.length) return;
    // Sort levels descending by height
    var sorted = levels.map(function(l,i){ return {h:l.height||0,b:l.bitrate||0,i:i}; }).sort(function(a,b){ return b.h-a.h; });
    vpQualHd.style.display = '';
    vpQualOpts.style.display = '';
    vpQualOpts.innerHTML = '';
    var autoBtn = document.createElement('button');
    autoBtn.className = 'ak-vp-popt active';
    autoBtn.dataset.level = '-1';
    autoBtn.textContent = 'Auto';
    vpQualOpts.appendChild(autoBtn);
    sorted.forEach(function(lvl){
      var btn = document.createElement('button');
      btn.className = 'ak-vp-popt';
      btn.dataset.level = String(lvl.i);
      btn.textContent = (lvl.h ? lvl.h + 'p' : 'Level ' + (lvl.i + 1)) + (lvl.b ? ' (' + Math.round(lvl.b / 1000) + 'k)' : '');
      vpQualOpts.appendChild(btn);
    });
    vpQualOpts.querySelectorAll('.ak-vp-popt').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.stopPropagation();
        vpQualOpts.querySelectorAll('.ak-vp-popt').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        if (hlsInstance) hlsInstance.currentLevel = parseInt(btn.dataset.level, 10);
        if (vpSetPanel) vpSetPanel.style.display = 'none';
      });
    });
  }

  /* ── VTT subtitle parser ── */
  function vpParseVtt(raw) {
    var cues = [], lines = raw.split('\n'), i = 0;
    while (i < lines.length) {
      var ln = lines[i].trim();
      if (ln.indexOf('-->') !== -1) {
        var parts = ln.split('-->');
        var s = vpVttTime(parts[0].trim()), e2 = vpVttTime((parts[1]||'').trim().split(' ')[0]);
        var txt = '', j = i + 1;
        while (j < lines.length && lines[j].trim() !== '') { txt += (txt ? '\n' : '') + lines[j].trim(); j++; }
        if (txt) cues.push({start:s, end:e2, text: txt.replace(/<[^>]+>/g,'')});
        i = j;
      } else { i++; }
    }
    return cues;
  }
  function vpVttTime(s) {
    var p = s.split(':');
    if (p.length === 3) return +p[0]*3600 + +p[1]*60 + parseFloat(p[2]);
    if (p.length === 2) return +p[0]*60 + parseFloat(p[1]);
    return parseFloat(s) || 0;
  }

  function vpUpdateSubs() {
    if (!vpSubsEl) return;
    if (_vpCCTrack < 0 || !_vpSubCues.length || !video) { vpSubsEl.innerHTML = ''; return; }
    var t = video.currentTime || 0;
    var cue = null;
    for (var c = 0; c < _vpSubCues.length; c++) { if (t >= _vpSubCues[c].start && t <= _vpSubCues[c].end) { cue = _vpSubCues[c]; break; } }
    vpSubsEl.innerHTML = cue ? '<span>' + escHtml(cue.text).replace(/\n/g, '<br>') + '</span>' : '';
  }

  function vpLoadVtt(url) {
    _vpSubCues = [];
    if (!url) return;
    fetch(url).then(function(r){ return r.text(); }).then(function(vtt){ _vpSubCues = vpParseVtt(vtt); }).catch(function(){});
  }

  /* ── CC setup (subtitle track list from Anify/Consumet) ── */
  function vpSetupCC(subtitles) {
    _vpSubtitles = subtitles || [];
    _vpCCTrack   = -1;
    _vpSubCues   = [];
    if (!vpCCOpts) return;
    vpCCOpts.innerHTML = '<button class="ak-vp-popt active" data-track="-1">Off</button>';
    _vpSubtitles.forEach(function(sub, i){
      var btn = document.createElement('button');
      btn.className = 'ak-vp-popt';
      btn.dataset.track = String(i);
      btn.textContent = (sub.lang || sub.label || ('Track ' + (i+1))).toUpperCase();
      vpCCOpts.appendChild(btn);
    });
    vpCCOpts.querySelectorAll('.ak-vp-popt').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.stopPropagation();
        vpCCOpts.querySelectorAll('.ak-vp-popt').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        _vpCCTrack = parseInt(btn.dataset.track, 10);
        if (_vpCCTrack >= 0 && _vpSubtitles[_vpCCTrack]) {
          vpLoadVtt(_vpSubtitles[_vpCCTrack].url);
          if (vpCCBtn) vpCCBtn.classList.add('on');
        } else {
          _vpSubCues = [];
          if (vpSubsEl) vpSubsEl.innerHTML = '';
          if (vpCCBtn) vpCCBtn.classList.remove('on');
        }
        if (vpCCPanel) vpCCPanel.style.display = 'none';
      });
    });
  }

  /* ── vpInit / vpHide — called by loadVideoPlayer / loadIframePlayer ── */
  function vpInit(subtitles) {
    if (!vpEl) return;
    vpEl.style.display = 'flex';
    // Show video-mode keyboard hints, hide the iframe-mode arrow hint
    ['kbdVidHints','kbdVidSkip','kbdVidArrows','kbdVidMute'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.display=''; });
    var _kbdIframeArrows = document.getElementById('kbdIframeArrows');
    if (_kbdIframeArrows) _kbdIframeArrows.style.display = 'none';
    // Reset controls state
    vpVolSync();
    vpUpdateProgress();
    vpUpdatePlayIcon();
    vpShowControls();
    // Reset speed selector to 1×
    if (vpSpeedOpts) vpSpeedOpts.querySelectorAll('.ak-vp-popt').forEach(function(o){ o.classList.toggle('active', o.dataset.speed === '1'); });
    // Reset quality panel
    if (vpQualHd)  vpQualHd.style.display  = 'none';
    if (vpQualOpts){ vpQualOpts.style.display = 'none'; vpQualOpts.innerHTML = ''; }
    // Setup CC tracks
    vpSetupCC(subtitles);
  }

  function vpHide() {
    if (!vpEl) return;
    vpEl.style.display = 'none';
    vpEl.classList.remove('show-ctl','hide-cursor');
    clearTimeout(_vpHideTimer);
    // Hide video-mode keyboard hints, restore the iframe-mode arrow hint
    ['kbdVidHints','kbdVidSkip','kbdVidArrows','kbdVidMute'].forEach(function(id){ var el=document.getElementById(id); if(el) el.style.display='none'; });
    var _kbdIframeArrows2 = document.getElementById('kbdIframeArrows');
    if (_kbdIframeArrows2) _kbdIframeArrows2.style.display = '';
    _vpSubCues = []; _vpSubtitles = []; _vpCCTrack = -1;
    if (vpSubsEl) vpSubsEl.innerHTML = '';
    if (vpSetPanel) vpSetPanel.style.display = 'none';
    if (vpCCPanel)  vpCCPanel.style.display  = 'none';
    if (vpCCBtn)    vpCCBtn.classList.remove('on');
  }

  /* ── Save progress every 20s ──
     lastKnownPosition is kept current by the direct-video timeupdate handler
     AND by the VidLink postMessage listener below, so resume/continue-watching
     works for iframe playback too, not just direct HLS/mp4 streams. */
  function saveProgress(pos) {
    if (!isLoggedIn) return;
    fetch('<?=$websiteUrl?>/api/user.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action:'progress', csrf:csrf, anime_id:animeId, episode:String(epNum), position_seconds:String(pos||0)}).toString(),
      keepalive: true
    }).catch(function(){});
  }
  setInterval(function(){
    var pos = video ? (video.currentTime || 0) : lastKnownPosition;
    saveProgress(pos);
  }, 20000);
  window.addEventListener('beforeunload', function(){
    saveProgress(video ? (video.currentTime||0) : lastKnownPosition);
  });

  /* ── VidLink player events via postMessage ──
     Documented at vidlink.pro: PLAYER_EVENT carries play/pause/seeked/ended/
     timeupdate with currentTime+duration. This is what lets Auto Next and
     resume-position tracking work through an iframe we don't otherwise
     control. Other providers (VidNest) don't document a stable postMessage
     contract, so we don't guess at their shape here — same-origin check
     below means unrelated messages are ignored regardless. */
  var _lastProgressSaveAt = 0;
  window.addEventListener('message', function(ev) {
    if (ev.origin !== 'https://vidlink.pro') return;
    var msg = ev.data;
    if (!msg || msg.type !== 'PLAYER_EVENT' || !msg.data) return;
    var pe = msg.data;
    if (typeof pe.currentTime === 'number' && pe.currentTime > 0) {
      lastKnownPosition = pe.currentTime;
    }
    if (pe.event === 'timeupdate') {
      var now = Date.now();
      if (now - _lastProgressSaveAt > 15000) {
        _lastProgressSaveAt = now;
        saveProgress(lastKnownPosition);
      }
      if (autoNextEnabled && pe.duration && (pe.duration - pe.currentTime) <= 30) {
        startNextCountdown();
      }
    } else if (pe.event === 'ended') {
      saveProgress(lastKnownPosition);
      if (autoNextEnabled && nextUrl && !_nextCountdownCancelled) {
        window.location.href = nextUrl;
      }
    }
  });

  /* ── Auto Play / Auto Next toggle buttons ── */
  var autoPlayBtn = document.getElementById('autoPlayToggle');
  var autoNextBtn = document.getElementById('autoNextToggle');
  function syncPrefButton(btn, enabled) { if (btn) btn.classList.toggle('active', enabled); }
  syncPrefButton(autoPlayBtn, autoPlayEnabled);
  syncPrefButton(autoNextBtn, autoNextEnabled);
  if (autoPlayBtn) {
    autoPlayBtn.addEventListener('click', function() {
      autoPlayEnabled = !autoPlayEnabled;
      localStorage.setItem('ak_autoplay', autoPlayEnabled ? '1' : '0');
      syncPrefButton(autoPlayBtn, autoPlayEnabled);
      // Re-apply immediately if the active server is VidLink (it reads autoplay from the URL)
      var srv = servers[serverIndex];
      if (srv && (srv.url || '').indexOf('vidlink.pro') !== -1) loadIframePlayer(srv);
    });
  }
  if (autoNextBtn) {
    autoNextBtn.addEventListener('click', function() {
      autoNextEnabled = !autoNextEnabled;
      localStorage.setItem('ak_autonext', autoNextEnabled ? '1' : '0');
      syncPrefButton(autoNextBtn, autoNextEnabled);
      if (!autoNextEnabled) cancelNextCountdown();
    });
  }
})();
</script>
</body>
</html>
