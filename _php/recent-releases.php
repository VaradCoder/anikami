                    <section class="block_area block_area_category">
                        <div class="block_area-header">
                            <div class="float-left bah-heading mr-4">
                                <h2 class="cat-heading">Recent Releases</h2>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <div class="tab-content">
                            <div class="block_area-content block_area-list film_list film_list-grid film_list-wfeature">
                                <div class="film_list-wrap">
                                <?php
                                $homePayload = $GLOBALS['home_page_api_payload'] ?? null;
                                $recentRows = [];
                                if (!empty($GLOBALS['home_page_recent_subbed']) && is_array($GLOBALS['home_page_recent_subbed'])) {
                                    $recentRows = $GLOBALS['home_page_recent_subbed'];
                                } elseif (!empty($homePayload['ok']) && !empty($homePayload['data']['recent_subbed']) && is_array($homePayload['data']['recent_subbed'])) {
                                    $recentRows = $homePayload['data']['recent_subbed'];
                                }

                                foreach ($recentRows as $recentRelease) {
                                    $name = (string)($recentRelease['name'] ?? 'Unknown');
                                    $episodeId = (string)($recentRelease['episodeId'] ?? '');
                                    $image = (string)($recentRelease['imgUrl'] ?? ($websiteUrl . '/files/images/no_poster.jpg'));
                                    $lang = (string)($recentRelease['subOrDub'] ?? 'Sub');
                                    $episodeNum = (string)($recentRelease['episodeNum'] ?? '?');
                                    if ($episodeId === '') {
                                        continue;
                                    }
                                ?>
                                    <div class="flw-item ">
                                        <div class="film-poster">
                                            <div class="tick ltr">
                                                <div class="tick-item-sub  tick-eps amp-algn"><?=app_e($lang)?></div>
                                            </div>
                                            <div class="tick rtl">
                                                <div class="tick-item tick-eps amp-algn">Episode <?=app_e($episodeNum)?>
                                                </div>
                                            </div>
                                            <img class="film-poster-img lazyload"
                                                data-src="<?=app_e($image)?>"
                                                src="<?=$websiteUrl?>/files/images/no_poster.jpg"
                                                alt="<?=app_e($name)?>">
                                            <a class="film-poster-ahref"
                                                href="<?=$websiteUrl?>/watch/<?=app_e($episodeId)?>"
                                                title="<?=app_e($name)?>"
                                                data-jname="<?=app_e($name)?>"><i class="fas fa-play"></i></a>
                                        </div>
                                        <div class="film-detail">
                                            <h3 class="film-name">
                                                <a
                                                    href="<?=$websiteUrl?>/watch/<?=app_e($episodeId)?>"
                                                    title="<?=app_e($name)?>"
                                                    data-jname="<?=app_e($name)?>"><?=app_e($name)?></a>
                                            </h3>
                                            <div class="fd-infor">
                                                <span class="fdi-item"><?=app_e($lang)?></span>
                                                <span class="dot"></span>
                                                <span class="fdi-item">Latest</span>
                                            </div>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                <?php } ?>

                                </div>
                                <?php if (empty($recentRows)) { ?>
                                    <div class="flw-item">
                                        <div class="film-detail">
                                            <h3 class="film-name">Recent releases are temporarily unavailable.</h3>
                                            <div class="fd-infor">
                                                <span class="fdi-item">Please refresh again in a moment.</span>
                                            </div>
                                        </div>
                                        <div class="clearfix"></div>
                                    </div>
                                <?php } ?>
                                <div class="clearfix"></div>
                            </div>
                        </div>
                    </section>
