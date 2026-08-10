<?php
require('./_config.php');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Internal API only: use a catalog section that matches the existing UI layout.
// NOTE: existing anime.php showed an A-Z list built from legacy animeList; internally we use popular as best proxy.
$catalogPayload = app_api_get('/api/catalog.php', ['section' => 'popular', 'page' => $page]);
$animeRows = (!empty($catalogPayload['ok']) && !empty($catalogPayload['data']['items']) && is_array($catalogPayload['data']['items']))
    ? $catalogPayload['data']['items']
    : [];
$animePaginationHtml = !empty($catalogPayload['ok']) ? (string)($catalogPayload['data']['pagination_html'] ?? '') : '';

$catalogPayloadDbg = app_debug_api_context('/api/catalog.php', ['section' => 'popular', 'page' => $page], is_array($catalogPayload) ? $catalogPayload : []);
?>

<!DOCTYPE html>
<html prefix="og: http://ogp.me/ns#" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
    <title>Anime List on <?=$websiteTitle?></title>
    <link rel="canonical" href="<?=app_e($websiteUrl)?>/anime<?=$page > 1 ? '?page=' . $page : ''?>">

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="title" content="Anime List on <?=$websiteTitle?>">
    <meta name="description" content="Anime List in HD with No Ads. Watch anime online">
    <meta name="keywords" content="<?=$websiteTitle?>, watch anime online, free anime, anime stream, anime hd, english sub, kissanime, gogoanime, animeultima, 9anime, 123animes, <?=$websiteTitle?>, vidstreaming, gogo-stream, animekisa, zoro.to, gogoanime.run, animefrenzy, animekisa">
    <meta name="charset" content="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="Content-Language" content="en">
    <meta property="og:title" content="Anime List on <?=$websiteTitle?>">
    <meta property="og:description" content="Anime List on <?=$websiteTitle?> in HD with No Ads. Watch anime online">
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?=$websiteTitle?>">
    <meta itemprop="image" content="<?=$banner?>">
    <meta property="og:image" content="<?=$banner?>">
    <meta property="og:image:width" content="650">
    <meta property="og:image:height" content="350">
    <meta property="twitter:card" content="summary">
    <meta name="apple-mobile-web-app-status-bar" content="#202125">
    <meta name="theme-color" content="#202125">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta2/css/all.min.css" type="text/css">
    <link rel="shortcut icon" href="<?=$websiteUrl?>/favicon.ico?v=<?=$version?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?=$websiteUrl?>/favicon.ico?v=<?=$version?>" />
    <link rel="stylesheet" href="<?=$websiteUrl?>/files/css/rias-theme.css?v=<?=$version?>">
    <link rel="stylesheet" href="<?=$websiteUrl?>/files/css/home.css?v=<?=$version?>">
    <link rel="stylesheet" href="<?=$websiteUrl?>/files/css/catalog.css?v=<?=$version?>">
    <script type="text/javascript">
        setTimeout(function () {
            var wpse326013 = document.createElement('link');
            wpse326013.rel = 'stylesheet';
            wpse326013.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta2/css/all.min.css';
            wpse326013.type = 'text/css';
            var godefer = document.getElementsByTagName('link')[0];
            godefer.parentNode.insertBefore(wpse326013, godefer);
            var wpse326013_2 = document.createElement('link');
            wpse326013_2.rel = 'stylesheet';
            wpse326013_2.href = 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css';
            wpse326013_2.type = 'text/css';
            var godefer2 = document.getElementsByTagName('link')[0];
            godefer2.parentNode.insertBefore(wpse326013_2, godefer2);
        }, 500);
    </script>
    <noscript>
        <link rel="stylesheet" type="text/css"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta2/css/all.min.css" />
        <link rel="stylesheet" type="text/css"
            href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css" />
    </noscript>
    <script></script>
</head>

<body data-page="page_anime" class="catalog-page">
    <div id="sidebar_menu_bg"></div>
    <div id="wrapper" data-page="page_home">
        <?php include('./_php/header.php')?>
<?php if (($catalogPayloadDbg['ok'] ?? true) === false) { $dbg = $catalogPayloadDbg; include('./_php/debug_api_block.php'); } ?>

        <div class="clearfix"></div>
        <div id="main-wrapper">
            <div class="container">
                <div id="main-content">
                    <section class="block_area block_area_category">
                        <div class="block_area-header">
                            <div class="float-left bah-heading mr-4">
                                <h2 class="cat-heading">Anime List</h2>
                            </div>
                            <div class="float-right bah-result">
                                <div class="cmb-item">
                                    <div class="nl-item">
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <div class="tab-content">
                            <div class="block_area-content block_area-list film_list film_list-grid film_list-wfeature">
                                <div class="film_list-wrap">

                                <?php foreach ($animeRows as $key => $az) {
                                    $title = (string)($az['animeTitle'] ?? 'Unknown');
                                    $animeId = (string)($az['animeId'] ?? '');
                                    if ($animeId === '') {
                                        continue;
                                    }
                                    $isDub = legacy_title_is_dub($title);
                                ?>
                                    <div class="flw-item">
                                        <div class="film-poster">
                                            <img class="film-poster-img lazyload"
                                                data-src="<?=app_e(app_safe_image($az['animeImg'] ?? ''))?>"
                                                src="<?=$websiteUrl?>/files/images/no_poster.jpg"
                                                alt="<?=app_e($title)?>">
                                            <a class="film-poster-ahref"
                                                href="<?=$websiteUrl?>/anime/<?=app_e($animeId)?>"
                                                title="<?=app_e($title)?>"
                                                data-jname="<?=app_e($title)?>"><i class="fas fa-play"></i></a>
                                        </div>
                                        <div class="film-detail">
                                            <h3 class="film-name">
                                                <a
                                                    href="<?=$websiteUrl?>/anime/<?=app_e($animeId)?>" title="<?=app_e($title)?>"
                                                    data-jname="<?=app_e($title)?>"><?=app_e($title)?></a>
                                            </h3>
                                            <div class="fd-infor">
                                                <span class="fdi-item"># <?php echo (136 * ($page - 1)) + $key+1 ?></span>
                                                <span class="dot"></span>
                                                <span class="fdi-item"><?=$isDub ? 'DUB' : 'SUB'?></span>
                                            </div>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                <?php } ?>
                                <?php if (empty($animeRows)) { ?>
                                    <div class="catalog-empty">
                                        <h3>Anime list is temporarily unavailable.</h3>
                                        <p>Please refresh again in a moment.</p>
                                    </div>
                                <?php } ?>
                                  
                                </div>
                                <div class="clearfix"></div>
                                <div class="pagination">
                                    <nav>
                                        <ul class="ulclear az-list">
                                             <?=$animePaginationHtml; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <div class="clearfix"></div>
                </div>
                <?php include('./_php/sidenav.php'); ?>
                <div class="clearfix"></div>
            </div>
        </div>
        <?php include('./_php/footer.php'); ?>
        <div id="mask-overlay"></div>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        
        <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/js-cookie@rc/dist/js.cookie.min.js"></script>
        <script type="text/javascript" src="<?=$websiteUrl?>/files/js/app.js"></script>
        <script type="text/javascript" src="<?=$websiteUrl?>/files/js/comman.js"></script>
        <script type="text/javascript" src="<?=$websiteUrl?>/files/js/movie.js"></script>
        <link rel="stylesheet" href="<?=$websiteUrl?>/files/css/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script type="text/javascript" src="<?=$websiteUrl?>/files/js/function.js"></script>
    </div>
</body>

</html>
