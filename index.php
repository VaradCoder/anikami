<?php
require('./_config.php');
$indexDescription = app_meta_excerpt(
    $websiteTitle . ' is a free anime streaming website where you can watch subbed and dubbed anime online in HD quality without ads.',
    160,
    $websiteTitle . ' anime streaming.'
);

// "Popular Anime" sidebar — was a hardcoded list of 5 titles that would go
// stale immediately after launch. Pulls the same live catalog data the
// /popular page uses instead.
$popularPayload = app_api_get('/api/catalog.php', ['section' => 'popular', 'page' => 1]);
$popularAnime = (!empty($popularPayload['ok']) && !empty($popularPayload['data']['items']) && is_array($popularPayload['data']['items']))
    ? array_slice($popularPayload['data']['items'], 0, 5)
    : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= $websiteTitle ?> - Watch Anime Online in HD for Free</title>

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <link rel="canonical" href="<?= app_e($websiteUrl) ?>/">

    <meta name="title"
        content="<?= $websiteTitle ?> - Watch Anime Online in HD for Free">

    <meta name="description"
        content="<?= app_e($indexDescription) ?>">

    <meta name="keywords"
        content="anime, anime streaming, watch anime online, free anime, dubbed anime, subbed anime, anime hd">

    <!-- THEME -->

    <meta name="theme-color" content="#0b0b0f">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- OPEN GRAPH -->

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $websiteUrl ?>">
    <meta property="og:title"
        content="<?= $websiteTitle ?> - Watch Anime Online in HD for Free">

    <meta property="og:description"
        content="Stream anime online in HD quality with subbed and dubbed episodes for free on <?= app_e($websiteTitle) ?>.">

    <meta property="og:image" content="<?= $banner ?>">

    <!-- TWITTER -->

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title"
        content="<?= $websiteTitle ?> - Watch Anime Online">

    <meta name="twitter:description"
        content="Watch anime online in HD quality for free.">

    <meta name="twitter:image" content="<?= $banner ?>">

    <!-- ICON -->

    <link rel="icon"
        type="image/x-icon"
        href="<?= $websiteUrl ?>/favicon.ico?v=<?= $version ?>">

    <link rel="apple-touch-icon"
        href="<?= $websiteLogo ?>">

    <!-- CSS -->

    <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">

    <link rel="stylesheet"
        href="https://use.fontawesome.com/releases/v5.3.1/css/all.css">

    <link rel="stylesheet"
        href="<?= $websiteUrl ?>/files/css/home.css?v=<?= $version ?>">

    <link rel="stylesheet"
        href="<?= $websiteUrl ?>/files/css/index.css?v=<?= $version ?>">

    <link rel="manifest"
        href="<?= $websiteUrl ?>/manifest.json">

</head>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js');
};
</script>

<body>
    <div id="wrapper">
        <!--Begin: Header-->
        <div id="xheader">
            <div class="container">
                <div id="xheader_browser">
                    <div class="header-btn"><i class="fas fa-bars mr-2"></i>Menu</div>
                </div>
                <div id="xheader_menu">
                    <ul class="nav header_menu-list">
                        <li class="nav-item"><a href="<?=$websiteUrl?>/home" title="Home">Home</a></li>
                        <li class="nav-item"><a href="<?=$websiteUrl?>/type/movies" title="Movies">Movies</a></li>
                        <li class="nav-item"><a href="<?=$websiteUrl?>/type/tv-series" title="TV Series">TV Series</a>
                        </li>
                        <li class="nav-item"><a href="<?=$websiteUrl?>/popular" title="Most Popular">Most Popular</a>
                        </li>
                        <li class="nav-item"><a href="<?=$websiteUrl?>/new-season" title="New Season">New Season</a>
                        </li>
                    </ul>
                    <div class="clearfix"></div>
                </div>
                <div class="clearfix"></div>
            </div>
        </div>
        <!--End: Header-->
        <!--Begin: Main-->
        <div id="xmain-wrapper">
            <div id="mw-top">
                <div class="container" >
                    <div class="mwt-content">
                        <div id="xsearch" class="home-search">
                            <div class="search-content">
                                <form action="<?=$websiteUrl?>/search" autocomplete="off" id="search-form">
                                    <div class="search-submit">
                                        <div class="search-icon btn-search"><i class="fa fa-search"></i></div>
                                    </div>
                                    <input type="text" class="form-control search-input" name="keyword"
                                        placeholder="Search anime..." required>
                                </form>
                            </div>
                            <div class="xhashtag">
                                <span class="title">Top search:</span>

                                <a href="<?=$websiteUrl?>/search?keyword=One%20Piece" class="item">One Piece</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Naruto%3A%20Shippuden" class="item">Naruto:
                                    Shippuden</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Naruto" class="item">Naruto</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Jujutsu%20Kaisen%200%20Movie"
                                    class="item">Jujutsu Kaisen 0
                                    Movie</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Bleach" class="item">Bleach</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Jujutsu%20Kaisen%20(TV)" class="item">Jujutsu
                                    Kaisen (TV)</a>

                                <a href="<?=$websiteUrl?>/search?keyword=The%20Eminence%20in%20Shadow" class="item">The
                                    Eminence in
                                    Shadow</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Mob%20Psycho%20100%20III" class="item">Mob
                                    Psycho 100 III</a>

                                <a href="<?=$websiteUrl?>/search?keyword=Boruto%3A%20Naruto%20Next%20Generations"
                                    class="item">Boruto:
                                    Naruto Next Generations</a>

                            </div>
                            <div class="clearfix"></div>

                            <div id="action-button">
                                <a href="<?=$websiteUrl?>/home" class="btn btn-lg btn-radius btn-primary">
                                    View Full Site <i class="fas fa-arrow-circle-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>

            <div class="mw-body">
                <div class="container">
<script type="text/javascript"
                        src="//s7.addthis.com/js/300/addthis_widget.js?v=<?=$version?>#pubid=ra-63430163bc99824a">
                    </script>
                    <div class="share-buttons share-buttons-detail">
                        <div class="share-buttons-block">
                            <div class="share-icon"></div>
                            <div class="sbb-title">
                                <span>Share <?=$websiteTitle?></span>
                                <p class="mb-0">to your friends</p>
                            </div>
                            <div class="sbb-social">
                                <div class="addthis_inline_share_toolbox"></div>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                    
                    <div class="mwb-2col">
                        <div class="mwb-left">
                            <h1 class="mw-heading"><?=$websiteTitle?> - The best site to watch anime online for Free
                            </h1>
                            <p>Do you know that according to Google, the monthly search volume for anime related topics
                                is
                                up to over 1 Billion times? Anime is famous worldwide and it is no wonder we've seen a
                                sharp
                                rise in the number of free anime streaming sites.</p>
                            <p>Just like free online movie streaming sites, anime watching sites are not created
                                equally,
                                some are better than the rest, so we've decided to build <?=$websiteTitle?> to be one of
                                the best
                                free
                                anime streaming site for all anime fans on the world.</p>
                            <h2>1/ What is <?=$websiteTitle?>?</h2>
                            <p><?=$websiteTitle?> is a free site to watch anime and you can even download subbed or
                                dubbed anime in
                                ultra HD quality without any registration or payment. By having No Ads in all kinds, we
                                are
                                trying to make it the safest site for free anime.</p>
                            <h2>2/ Is <?=$websiteTitle?> safe?</h2>
                            <p>Yes we are, we do have only one Ads to cover the server cost and we keep scanning the ads
                                24/7 to make sure all are clean, If you find any ads that is suspicious, please forward
                                us
                                the info and we will remove it.</p>
                            <h2>3/ So what make <?=$websiteTitle?> the best site to watch anime free online?</h2>
                            <p>Before building <?=$websiteTitle?>, we've checked many other free anime sites, and learnt
                                from them.
                                We
                                only keep the good things and remove all the bad things from all the competitors, to put
                                it
                                in our <?=$websiteTitle?> website. Let's see how we're so confident about being the best
                                site for
                                anime
                                streaming:</p>
                            <ul>
                                <li><strong>Safety:</strong> We try our best to not having harmful ads on
                                    <?=$websiteTitle?>.
                                </li>
                                <li><strong>Content library:</strong> Our main focus is anime. You can find here
                                    popular,
                                    classic, as well as current titles from all genres such as action, drama, kids,
                                    fantasy,
                                    horror, mystery, police, romance, school, comedy, music, game and many more. All
                                    these
                                    titles come with English subtitles or are dubbed in many languages.
                                </li>
                                <li><strong>Quality/Resolution:</strong> All titles are in excellent resolution, the
                                    best
                                    quality possible. <?=$websiteTitle?> also has a quality setting function to make
                                    sure our users
                                    can
                                    enjoy streaming no matter how fast your Internet speed is. You can stream the anime
                                    at
                                    360p if your Internet is being ridiculous, Or if it is good, you can go with 720p or
                                    even 1080p anime.
                                </li>
                                <li><strong>Streaming experience:</strong> Compared to other anime streaming sites, the
                                    loading speed at <?=$websiteTitle?> is faster. Downloading is just as easy as
                                    streaming, you
                                    won't
                                    have any problem saving the videos to watch offline later.
                                </li>
                                <li><strong>Updates:</strong> We updates new titles as well as fulfill the requests on a
                                    daily basis so be warned, you will never run out of what to watch on
                                    <?=$websiteTitle?>.
                                </li>
                                <li><strong>User interface:</strong> Our UI and UX makes it easy for anyone, no matter
                                    how
                                    old you are, how long have you been on the Internet. Literally, you can figure out
                                    how
                                    to navigate our site after a quick look. If you want to watch a specific title,
                                    search
                                    for it via the search box. If you want to look for suggestions, you can use the
                                    site's
                                    categories or simply scroll down for new releases.
                                </li>
                                <li><strong>Device compatibility:</strong> <?=$websiteTitle?> works alright on both your
                                    mobile and
                                    desktop. However, we'd recommend you use your desktop for a smoother streaming
                                    experience.
                                </li>
                                <li><strong>Customer care:</strong> We are in active mode 24/7. You can always contact
                                    us
                                    for any help, query, or business-related inquiry. On our previous projects, we were
                                    known for our great customer service as we were quick to fix broken links or upload
                                    requested content.
                                </li>
                            </ul>
                            <p>So if you're looking for a trustworthy and safe site for your Anime streaming, let's give
                                <?=$websiteTitle?> a try. And if you like us, please help us to spread the words and do
                                not forget
                                to
                                bookmark our site.</p>
                            <p>Thank you!</p>
                            <p>&nbsp;</p>
                        </div>
                        <div class="mwb-right">
                            <div class="zr-news zr-news-list">
                                <h2 class="heading-news">Popular Anime</h2>
                                <?php foreach ($popularAnime as $pa):
                                    $paId    = (string)($pa["animeId"] ?? "");
                                    $paTitle = (string)($pa["animeTitle"] ?? "Unknown");
                                    $paImg   = (string)($pa["animeImg"] ?? $pa["imgUrl"] ?? "");
                                    $paMeta  = trim(($pa["type"] ?? "") . (!empty($pa["releasedDate"]) ? " · " . $pa["releasedDate"] : ""));
                                    $paHref  = $websiteUrl . "/anime/" . rawurlencode($paId);
                                ?>
                                <div class="item">
                                    <a href="<?=$paHref?>" class="zr-news-thumb"><img
                                            src="<?=app_e($paImg)?>" alt="<?=app_e($paTitle)?>" class="zrn-image"></a>
                                    <div class="zr-news-infor">
                                        <a href="<?=$paHref?>" class="zrn-title">
                                            <h4 class="news-title"><?=app_e($paTitle)?></h4>
                                        </a>
                                        <?php if ($paMeta): ?><div class="description"><?=app_e($paMeta)?></div><?php endif; ?>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (!$popularAnime): ?>
                                <div class="item"><div class="description">Popular anime will appear here once available.</div></div>
                                <?php endif; ?>
                                <div class="item item-more">
                                    <a href="<?=$websiteUrl?>/popular" class="btn btn-sm btn-block">Show more.</a>
                                </div>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>
            </div>
        </div>
        <!--End: Main-->
        <!--Begin: Footer-->
        <div id="xfooter-about">
            <div class="container">
                <p class="copyright">Â©
                    <?php echo date("Y"); ?> <a href="<?=$websiteUrl?>"><?=$websiteTitle?></a>. All rights reserved.
                </p>
            </div>
        </div>
        <!--End: Footer-->
    </div>
    <script type="text/javascript"
        src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js?v=<?=$version?>"></script>
    <script type="text/javascript"
        src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js?v=<?=$version?>"></script>
    <script>
    $(document).ready(function() {
        $("#xheader_browser").click(function(e) {
            $("#xheader_menu, #xheader_browser").toggleClass("active");
        });
        $('.btn-search').click(function() {
            if ($('.search-input').val().trim() !== "") {
                $('#search-form').submit();
            }
        });
    });
    </script>

<script>
window.addEventListener("scroll", function () {
    const header = document.getElementById("xheader");

    if(window.scrollY > 40){
        header.classList.add("scrolled");
    } else {
        header.classList.remove("scrolled");
    }
});
</script>

</body>

</html>
<style>
    /* =========================================
   GLOBAL
========================================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html,
body{
    background:#0b0b0f;
    color:#fff;
    font-family:Arial,Helvetica,sans-serif;
    overflow-x:hidden;
}

a{
    text-decoration:none!important;
}

img{
    max-width:100%;
    height:auto;
}

.container{
    width:100%;
    max-width:1400px;
    padding-left:16px;
    padding-right:16px;
}

/* =========================================
   HEADER
========================================= */

#xheader{
    position:fixed;
    top:0;
    left:0;
    right:0;
    z-index:999;
    background:transparent;
    transition:0.3s ease;
    padding:12px 0;
}

#xheader.scrolled{
    background:rgba(8,8,12,.55);
    backdrop-filter:blur(18px);
    border-bottom:1px solid rgba(255,255,255,.08);
}

#xheader .container{
    display:flex;
    align-items:center;
    justify-content:space-between;
    min-height:72px;
}

#xheader_browser{
    display:flex;
    align-items:center;
}

.header-btn{
    height:44px;
    padding:0 18px;
    border-radius:12px;
    background:#1a1a25;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
}

#xheader_menu{
    display:flex;
    align-items:center;
}

.header_menu-list{
    display:flex;
    align-items:center;
    gap:10px;
    list-style:none;
    margin:0;
}

.header_menu-list .nav-item a{
    color:#fff;
    font-size:14px;
    font-weight:600;
    padding:10px 14px;
    border-radius:10px;
    transition:.2s;
}

.header_menu-list .nav-item a:hover{
    background:#1a1a25;
}

/* =========================================
   MAIN WRAPPER
========================================= */

#xmain-wrapper{
    padding-top:0;
}

/* =========================================
   HERO SECTION
========================================= */

#mw-top{
    position:relative;
    min-height:100vh;
    display:flex;
    align-items:center;
    overflow:hidden;
    margin-top:0;
    padding-top:0;
    background:
    linear-gradient(
        to bottom,
        rgba(0,0,0,.35),
        rgba(0,0,0,.78)
    ),
    url("files/images/riasBanner.png") no-repeat center center/cover;
}

#mw-top:before{
    content:"";
    position:absolute;
    inset:0;
    background:
    radial-gradient(circle at top right,#ff174420,transparent 30%),
    radial-gradient(circle at bottom left,#ff174410,transparent 30%);
}

.mwt-content{
    position:relative;
    z-index:2;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
    width:100%;
    min-height:100vh;
    padding-top:100px;
    padding-bottom:40px;
}

.mwt-icon{
    width:240px;
    margin-bottom:24px;
}

.mwh-logo{
    margin-bottom:28px;
}

.mwh-logo img{
    max-width:260px;
}

.home-search{
    width:100%;
    max-width:720px;
}

.search-content{
    position:relative;
}

.search-input{
    width:100%!important;
    height:58px!important;
    border-radius:16px!important;
    background:rgba(20,20,28,.78)!important;
    backdrop-filter:blur(12px);
    border:1px solid rgba(255,255,255,.08)!important;
    color:#fff!important;
    padding:0 60px 0 20px!important;
    font-size:15px!important;
}

.search-input::placeholder{
    color:#999;
}

.search-submit{
    position:absolute;
    top:0;
    right:0;
    width:58px;
    height:58px;
    z-index:3;
}

.search-icon{
    width:58px;
    height:58px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    cursor:pointer;
}

.xhashtag{
    margin-top:18px;
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:10px;
}

.xhashtag .title{
    color:#999;
}

.xhashtag .item{
    padding:8px 14px;
    border-radius:999px;
    background:#181824;
    color:#fff;
    font-size:13px;
}

/* =========================================
   ACTION BUTTON
========================================= */

#action-button{
    display:flex;
    justify-content:center;
    margin:20px 0 40px;
}

#action-button .btn{
    min-height:54px;
    padding:0 26px;
    border-radius:14px;
    background:#ff1744;
    border:none;
    display:flex;
    align-items:center;
    font-weight:700;
}

/* =========================================
   SHARE SECTION
========================================= */

.share-buttons{
    margin-bottom:40px;
}

.share-buttons-block{
    background:#12121a;
    border-radius:18px;
    padding:20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
}

.sbb-title span{
    font-size:18px;
    font-weight:700;
}

/* =========================================
   CONTENT LAYOUT
========================================= */

.mwb-2col{
    display:grid;
    grid-template-columns:minmax(0,1fr) 360px;
    gap:30px;
}

.mwb-left{
    min-width:0;
}

.mwb-left h1,
.mwb-left h2{
    margin-bottom:18px;
    font-weight:700;
}

.mwb-left p,
.mwb-left li{
    color:#cfcfcf;
    line-height:1.8;
    margin-bottom:18px;
}

.mwb-left ul{
    padding-left:20px;
}

/* =========================================
   SIDEBAR
========================================= */

.zr-news{
    background:#12121a;
    border-radius:20px;
    padding:20px;
}

.heading-news{
    margin-bottom:20px;
    font-size:24px;
    font-weight:700;
}

.zr-news .item{
    display:flex;
    gap:14px;
    margin-bottom:18px;
}

.zr-news-thumb{
    width:100px;
    min-width:100px;
    border-radius:12px;
    overflow:hidden;
}

.zrn-image{
    width:100%;
    height:140px;
    object-fit:cover;
}

.zrn-title{
    color:#fff!important;
}

.news-title{
    font-size:16px;
    margin-bottom:10px;
}

.description{
    color:#aaa;
    font-size:13px;
    line-height:1.6;
    display:-webkit-box;
    -webkit-line-clamp:4;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

.item-more .btn{
    background:#ff1744;
    border:none;
    border-radius:12px;
    height:46px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:600;
}

/* =========================================
   FOOTER
========================================= */

#xfooter-about{
    margin-top:60px;
    padding:30px 0;
    border-top:1px solid rgba(255,255,255,.06);
}

.copyright{
    text-align:center;
    color:#999;
    margin:0;
}

.copyright a{
    color:#fff;
}


/* =========================================
   RESPONSIVE IMPROVEMENTS
========================================= */

@media(max-width:992px){

    #xheader .container{
        flex-direction:column;
        justify-content:center;
        gap:14px;
    }

    #xheader_menu{
        width:100%;
        justify-content:center;
    }

    .header_menu-list{
        flex-wrap:wrap;
        justify-content:center;
    }

    .mwt-content{
        padding-top:140px;
        padding-left:10px;
        padding-right:10px;
    }

    .home-search{
        max-width:100%;
    }

    .xhashtag{
        justify-content:center;
    }

    .mwb-2col{
        grid-template-columns:1fr;
    }

    .mwb-right{
        margin-top:20px;
    }
}

@media(max-width:768px){

    #mw-top{
        min-height:auto;
        padding:120px 0 60px;
        background-position:center right;
    }

    .mwt-content{
        min-height:auto;
        padding-top:60px;
        padding-bottom:60px;
    }

    .search-input{
        height:54px!important;
        font-size:14px!important;
        padding:0 54px 0 16px!important;
    }

    .search-submit,
    .search-icon{
        width:54px;
        height:54px;
    }

    .xhashtag{
        gap:8px;
    }

    .xhashtag .title{
        width:100%;
        text-align:center;
        margin-bottom:4px;
    }

    .xhashtag .item{
        font-size:12px;
        padding:8px 12px;
    }

    #action-button{
        margin-top:28px;
    }

    #action-button .btn{
        width:100%;
        max-width:280px;
        height:54px;
        font-size:14px;
        border-radius:16px;
    }

    .zr-news .item{
        flex-direction:column;
    }

    .zr-news-thumb{
        width:100%;
    }

    .zrn-image{
        height:240px;
    }
}

@media(max-width:480px){

    #xheader{
        padding:10px 0;
    }

    .header-btn{
        width:100%;
        justify-content:center;
    }

    .header_menu-list{
        gap:6px;
    }

    .header_menu-list .nav-item a{
        font-size:12px;
        padding:8px 10px;
    }

    .search-input{
        height:50px!important;
        border-radius:14px!important;
    }

    .search-submit,
    .search-icon{
        width:50px;
        height:50px;
    }

    .xhashtag{
        justify-content:center;
    }

    .xhashtag .item{
        font-size:11px;
        padding:7px 10px;
    }

    .zr-news{
        padding:16px;
    }

    .heading-news{
        font-size:20px;
    }

    .description{
        font-size:12px;
    }
}

</style>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- Popper -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>

<!-- Bootstrap 4 JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
