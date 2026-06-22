var movieId = $('#main-wrapper').data('id');
var clickedLoadComment = false;
var initDisqus = false;
var loading = false;

/* =========================
   DISQUS LOAD
   ========================= */
function loadDisqus() {
    let activeTab = $('.btn-comment-tab.active').data('type');

    let url = (activeTab === 'anime')
        ? (window.movie?.shortlink || window.location.href)
        : (window.episode_play?.shortlink || window.location.href);

    $('.btn-load-comment').hide();

    if (!initDisqus) {
        var disqus_config = function () {
            this.page.url = url;
        };

        (function () {
            var d = document, s = d.createElement('script');
            s.src = '//' + (site_config?.disqus || '') + '/embed.js';
            s.setAttribute('data-timestamp', +new Date());
            (d.head || d.body).appendChild(s);
            initDisqus = true;
        })();

    } else {
        if (typeof DISQUS !== 'undefined') {
            DISQUS.reset({
                reload: true,
                config: function () {
                    this.page.url = url;
                }
            });
        }
    }
}

/* =========================
   LOAD SERVERS
   ========================= */
function getsrv() {
    if (!movieId) return;

    $.get('/' + movieId, function (res) {
        if (!res || !res.status) return;

        $('#dt_sv').html(res.html);

        $('.server-item').off('click').on('click', function () {
            $('.server-item .btn').removeClass('active');
            $(this).find('.btn').addClass('active');

            let source = $(this).data('type');
            let serverId = $(this).data('server-id');
            let linkIframe = $(this).data('embed');

            localStorage.setItem('currentSource', source);
            localStorage.setItem('currentServer', serverId);

            // Server switch delegated to PlayerManager (Phase 2.3)
            $('#embed-loading').show();

            if (window.playerManager && typeof window.playerManager.switchServer === 'function') {
                // PlayerManager uses the same normalized server list ordering.
                // We rely on server-item's index from markup.
                const idxRaw = $(this).data('server-index');
                const idx = idxRaw !== undefined ? parseInt(idxRaw, 10) : 0;
                window.playerManager.switchServer(idx);
            } else {
                // Legacy fallback: update iframe if present.
                const iframe = document.getElementById('anime-player-iframe');
                if (iframe) {
                    iframe.src = linkIframe;
                }
                setTimeout(() => $('#embed-loading').hide(), 800);
            }
        });

        /* RESTORE LAST SERVER */
        let currentSource = localStorage.getItem('currentSource');

        if (currentSource && $('.servers-' + currentSource).length > 0) {
            let currentServer = localStorage.getItem('currentServer');
            let svEl = $('.servers-' + currentSource + ' .server-item[data-server-id="' + currentServer + '"]');

            if (svEl.length > 0) {
                svEl.click();
            } else {
                $('.servers-' + currentSource + ' .server-item').first().click();
            }
        } else {
            $('.servers-mixed .server-item').first().click();
        }
    }).fail(() => {
        console.error("Server load failed");
    });
}

/* =========================
   COUNT VIEW
   ========================= */
function countViewMovie() {
    setTimeout(function () {
        $.post('/' + movieId);
    }, 60000);
}

/* =========================
   EPISODE NAV
   ========================= */
function nextEpisode() {
    let nextEl = $('.ep-item.active').next();
    if (nextEl.length) window.location.href = nextEl.attr('href');
}

function prevEpisode() {
    let prevEl = $('.ep-item.active').prev();
    if (prevEl.length) window.location.href = prevEl.attr('href');
}

/* =========================
   VOTE
   ========================= */
function voteSubmit(data) {
    if (loading) return;

    loading = true;

    $.post('/', data, function (res) {
        $('#vote-loading').hide();

        if (res.redirectTo) {
            window.location.href = res.redirectTo;
            return;
        }

        if (res.status) {
            $('#vote-info').html(res.html);
            toastr.success(res.msg || "Success", "", { timeOut: 5000 });
        } else {
            toastr.error(res.msg || "Error", "", { timeOut: 5000 });
        }

        loading = false;
    }).fail(() => {
        toastr.error("Network error");
        loading = false;
    });
}

/* =========================
   INIT
   ========================= */
$(document).ready(function () {

    if (page === "movie_info") {
        $.get('/' + movieId + '?page=' + page, function (res) {
            if (res?.status) {
                $('#watch-list-content').html(res.html);
            }
        });
    }

    if (page === "movie_watch") {

        getsrv();
        countViewMovie();

        if (parseInt(userSettings?.auto_play) === 1) {
            $('.quick-settings[data-option="auto_play"]').removeClass('off');
        }

        if (parseInt(userSettings?.auto_next) === 1) {
            $('.quick-settings[data-option="auto_next"]').removeClass('off');
        }

        /* COMMENTS */
        $('.btn-comment-tab').click(function () {
            $('.btn-comment-tab').removeClass('active');
            $(this).addClass('active');
            loadDisqus();
        });

        $('.btn-load-comment').click(function () {
            clickedLoadComment = true;
            $(this).hide();
            loadDisqus();
        });

        /* RESIZE */
        $("#media-resize").click(function () {
            $(".anis-watch-wrap").toggleClass("extend");

            $(this).html(
                $(".anis-watch-wrap").hasClass('extend')
                    ? '<i class="fas fa-compress mr-1"></i>Collapse'
                    : '<i class="fas fa-expand mr-1"></i>Expand'
            );
        });

        /* LIGHT TOGGLE */
        $("#turn-off-light").click(function () {
            $("#mask-overlay, .anis-watch-wrap").toggleClass("active");
        });

        $("#mask-overlay").click(function () {
            $("#mask-overlay, .anis-watch-wrap").removeClass("active");
            $("#turn-off-light").removeClass("off");
        });

        /* VOTE */
        $(document).on("click", ".btn-vote", function () {
            $('#vote-loading').show();

            let mark = $(this).data('mark');

            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.execute(recaptchaSiteKey, { action: 'vote' })
                    .then(function (_token) {
                        voteSubmit({ movieId, mark, _token });
                    });
            } else {
                voteSubmit({ movieId, mark, _token: '' });
            }
        });
    }
});

/* =========================
   EPISODE PAGINATION
   ========================= */
$(document).on("click", ".ep-page-item", function () {

    $('.ep-page-item').removeClass('active');
    $('.ep-page-item .ic-active').hide();

    $(this).addClass('active');
    $(this).find('.ic-active').show();

    $('.ss-list-min').hide();
    $('#episodes-page-' + $(this).data('page')).show();

    $('#current-page').text($(this).text().trim());
});
