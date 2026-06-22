<?php /* Rias theme updated */ ?>
<div id="anime-trending">
    <div class="container">
        <section class="block_area block_area_trending">
            <div class="block_area-header">
                <div class="bah-heading">
                    <h2 class="cat-heading">Trending</h2>
                </div>
                <div class="clearfix"></div>
            </div>
            <div class="block_area-content">
                <div class="trending-list" id="trending-home">
                    <div class="swiper-container swiper-container-initialized swiper-container-horizontal">
                        <div class="swiper-wrapper">

                            <?php
                                $homePayload = $GLOBALS['home_page_api_payload'] ?? null;
                                $trendingRows = [];
                                if (!empty($GLOBALS['home_page_popular']) && is_array($GLOBALS['home_page_popular'])) {
                                    $trendingRows = $GLOBALS['home_page_popular'];
                                } elseif (!empty($GLOBALS['home_page_featured_airing']) && is_array($GLOBALS['home_page_featured_airing'])) {
                                    $trendingRows = $GLOBALS['home_page_featured_airing'];
                                } elseif (!empty($homePayload['ok']) && !empty($homePayload['data']['popular']) && is_array($homePayload['data']['popular'])) {
                                    $trendingRows = $homePayload['data']['popular'];
                                } elseif (!empty($homePayload['ok']) && !empty($homePayload['data']['top_airing']) && is_array($homePayload['data']['top_airing'])) {
                                    $trendingRows = $homePayload['data']['top_airing'];
                                }

                                foreach ($trendingRows as $key => $popular) {
                                    $title = (string)($popular['animeTitle'] ?? $popular['name'] ?? 'Unknown');
                                    $animeId = (string)($popular['animeId'] ?? $popular['anime_id'] ?? '');
                                    $image = (string)($popular['imgUrl'] ?? $popular['animeImg'] ?? ($websiteUrl . '/files/images/no_poster.jpg'));
                                    if ($animeId === '') {
                                        continue;
                                    }
                            ?>

                            <div class="swiper-slide">
                                <div class="item">
                                    <div class="number">
                                        <span><?=$key+1?></span>
                                        <div class="film-title dynamic-name" data-jname="<?=app_e($title)?>">
                                            <?=app_e($title)?>
                                        </div>
                                    </div>
                                    <a href="<?=$websiteUrl?>/anime/<?=app_e($animeId)?>" class="film-poster"
                                        title="<?=app_e($title)?>">
                                        <img data-src="<?=app_e($image)?>"
                                            src="<?=$websiteUrl?>/files/images/no_poster.jpg"
                                            class="film-poster-img lazyload" alt="<?=app_e($title)?>">
                                    </a>
                                    <div class="clearfix"></div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if (empty($trendingRows)) { ?>
                            <div class="swiper-slide">
                                <div class="item">
                                    <div class="number">
                                        <span>0</span>
                                        <div class="film-title">Trending titles are loading. Please check back shortly.</div>
                                    </div>
                                    <div class="clearfix"></div>
                                </div>
                            </div>
                            <?php } ?>



                        </div>
                        <div class="clearfix"></div>
                        <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span>
                    </div>
                    <div class="trending-navi">
                        <div class="navi-next swiper-button-disabled" tabindex="-1" role="button"
                            aria-label="Next slide" aria-disabled="true"><i class="fas fa-angle-right"></i>
                        </div>
                        <div class="navi-prev swiper-button-disabled" tabindex="-1" role="button"
                            aria-label="Previous slide" aria-disabled="true"><i class="fas fa-angle-left"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
