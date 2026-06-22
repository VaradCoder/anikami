var userSettings = $('#footer').data('settings') || {};
var page = $('#wrapper').data('page');
var loading = false;

toastr.options.positionClass = 'toast-bottom-right';

/* =========================
   SOURCE MAP (SAFE)
   ========================= */
function smap(url) {
    if (!url) return;
    const script = document.createElement('script');
    script.textContent = `//# sourceMappingURL=${url}?v=${Date.now()}`;
    document.head.appendChild(script);
    script.remove();
}

/* =========================
   TOGGLE ANIME NAME
   ========================= */
function toggleAnimeName() {
    $('.dynamic-name').each(function () {
        let currentName = $(this).text();
        let jName = $(this).data('jname') || '';
        let _this = $(this);

        if (!jName) return;

        _this.animate({ opacity: 0 }, 200, function () {
            _this.text(jName).animate({ opacity: 1 }, 200);
            _this.data('jname', currentName);
        });
    });
}

/* =========================
   WATCHLIST
   ========================= */
function watchListSubmit(data) {
    if (loading) return;

    loading = true;

    $.post('/ajax/watch-list/add', data, function (res) {
        if (res.redirectTo) {
            window.location.href = res.redirectTo;
            return;
        }

        if (res.status) {
            toastr.success(res.msg || 'Added successfully');
            if (res.html) {
                $('#watch-list-content').html(res.html);
            } else {
                location.reload();
            }
        } else {
            toastr.error(res.msg || 'Something went wrong');
        }

        loading = false;
    }).fail(() => {
        toastr.error('Network error');
        loading = false;
    });
}

/* =========================
   SETTINGS
   ========================= */
function quickSettings(option, value) {
    userSettings[option] = value;

    if (isLoggedIn) {
        $.post('/ajax/user/settings?action=quick', { option, value }, function (res) {
            if (res.status) {
                toastr.success(res.msg || 'Updated');
            } else {
                toastr.error(res.msg || 'Error');
            }

            if (option === 'enable_dub') location.reload();
            if (res.redirectTo) window.location.href = res.redirectTo;
        });
    } else {
        Cookies.set('userSettings', JSON.stringify(userSettings), {
            path: '/',
            expires: 365
        });

        if (option === 'enable_dub') location.reload();
    }
}

/* =========================
   INIT
   ========================= */
$(document).ready(function () {

    /* FIXED SWIPER (IMPORTANT) */
    new Swiper('#slider', {
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev'
        },
        pagination: {
            el: '#slider .swiper-pagination',
            clickable: true
        },
        loop: true,
        autoplay: {
            delay: 3000
        }
    });

    new Swiper('#trending-home .swiper-container', {
        slidesPerView: 6,
        spaceBetween: 30,
        navigation: {
            nextEl: '.trending-navi .navi-next',
            prevEl: '.trending-navi .navi-prev'
        },
        breakpoints: {
            320: { slidesPerView: 3, spaceBetween: 2 },
            480: { slidesPerView: 3, spaceBetween: 15 },
            900: { slidesPerView: 4, spaceBetween: 20 },
            1320: { slidesPerView: 6, spaceBetween: 20 },
            1880: { slidesPerView: 8, spaceBetween: 20 }
        },
        autoplay: {
            delay: 2000
        }
    });

    /* =========================
       USER SETTINGS INIT FIX
       ========================= */
    if (!userSettings || Object.keys(userSettings).length === 0) {
        let cookie = Cookies.get('userSettings');

        if (!cookie) {
            userSettings = {
                auto_play: 1,
                auto_next: 1,
                auto_load_comments: 0,
                enable_dub: 0,
                anime_name: "en",
                play_original_audio: 0
            };
        } else {
            userSettings = JSON.parse(cookie);
        }

        Cookies.set('userSettings', JSON.stringify(userSettings), {
            path: '/',
            expires: 365
        });
    }

    /* APPLY SETTINGS */
    if (userSettings.anime_name !== "en") {
        $('.select-anime-name').addClass('off');
        toggleAnimeName();
    }

    if (parseInt(userSettings.enable_dub) === 1) {
        $('.select-play-dub').addClass('active');
    }

    /* CLICK EVENTS */
    $('.select-anime-name').click(function () {
        $(this).toggleClass('off');
        quickSettings('anime_name', $(this).hasClass('off') ? 'jp' : 'en');
        toggleAnimeName();
    });

    $('.select-play-dub').click(function () {
        $(this).toggleClass('active');
        quickSettings('enable_dub', $(this).hasClass('active') ? 1 : 0);
    });

    /* =========================
       SEARCH (FIXED DEBOUNCE)
       ========================= */
    let timeout = null;

    $('.search-input').keyup(function () {
        clearTimeout(timeout);

        timeout = setTimeout(() => {
            let keyword = $(this).val().trim();

            if (keyword.length > 1) {
                $('#search-suggest').show();
                $('#search-loading').show();

                $.get(`/ajax/search/suggest?keyword=${keyword}`, function (res) {
                    // Backend may return either:
                    // 1) JSON like: { html: "..." }
                    // 2) raw HTML string
                    let html = '';
                    try {
                        if (res && typeof res === 'object') html = res.html || '';
                        else html = res;
                    } catch (e) {}

                    // Inject suggestions
                    const $box = $('#search-suggest');
                    if (html && typeof html === 'string') {
                        // keep existing nested structure if present
                        $box.html(html);
                    } else {
                        $box.html('');
                    }

                    // Enhance suggestions behavior (rerank + highlight + keyboard)
                    enhanceSearchSuggestions($box, keyword);

                    $('#search-loading').hide();
                });
            } else {
                $('#search-suggest').hide();
            }
        }, 400);
    });

});


// =========================
// SEARCH SUGGESTION ENHANCER
// =========================
function enhanceSearchSuggestions($box, keyword) {
    if (!$box || !$box.length) return;

    const input = (keyword || '').toLowerCase();
    if (!input || input.length < 2) return;

    // Candidate item anchors
    const $items = $box.find('a').filter(function () {
        const $a = $(this);
        // keep only anchors that look like suggestion items
        return ($a.text() || '').trim().length > 0 && $a.attr('href');
    });

    if (!$items.length) return;

    // Extract label/title from anchor text
    const extracted = [];
    $items.each(function () {
        const $a = $(this);
        const rawText = ($a.text() || '').trim();
        const label = rawText;

        extracted.push({
            label,
            labelLower: label.toLowerCase(),
            $a
        });
    });

    // Re-rank locally: startsWith first, then contains
    const starts = [];
    const contains = [];
    for (const it of extracted) {
        if (it.labelLower.startsWith(input)) starts.push(it);
        else if (it.labelLower.includes(input)) contains.push(it);
    }
    const others = extracted.filter(it => !it.labelLower.startsWith(input) && !it.labelLower.includes(input));

    const ranked = starts.concat(contains, others);

    // Highlight match using <mark>
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#39;');
    }

    function highlightText(text) {
        const raw = String(text);
        const lower = raw.toLowerCase();
        const idx = lower.indexOf(input);
        if (idx < 0) return escapeHtml(raw);

        const before = escapeHtml(raw.slice(0, idx));
        const mid = escapeHtml(raw.slice(idx, idx + input.length));
        const after = escapeHtml(raw.slice(idx + input.length));
        return `${before}<mark class="hkx-search-mark">${mid}</mark>${after}`;
    }

    // Build mapping from anchor DOM to ranked order
    // Replace anchor contents with highlighted HTML
    ranked.forEach(it => {
        it.$a.html(highlightText(it.label));
    });

    // Keyboard navigation: use the closest item container if present, else anchor
    // Keep a list of row nodes
    const $rows = $box.find('a').filter(function () {
        const $a = $(this);
        return ranked.some(r => r.$a && r.$a.get(0) === $a.get(0));
    });

    // Re-append in ranked order based on anchor nodes
    // Since DOM structure is backend-controlled, we move anchors themselves.
    // If anchors are direct children, this works. Otherwise it still keeps order within their container.
    const rowNodes = $rows.toArray();
    const rankedAnchors = ranked.map(r => r.$a.get(0)).filter(Boolean);

    // Only reorder if we can find all anchors
    if (rankedAnchors.length === rowNodes.length) {
        // Clear container and rebuild in order
        // Prefer to keep outer HTML minimal: create a fragment of ranked anchors
        const $anchorContainer = $('<div></div>');
        rankedAnchors.forEach(node => $anchorContainer.append(node));
        // Replace content
        $box.empty().append($anchorContainer.html());
    }

    // Ensure keyboard listeners are bound once per input box
    const $searchInput = $('.search-input');
    if (!$searchInput.length) return;

    $searchInput.off('keydown.hkxSuggest');
    $searchInput.on('keydown.hkxSuggest', function (e) {
        const key = e.key;
        const $currentItems = $box.find('a').filter(function () {
            return ($(this).text() || '').trim().length > 0;
        });

        if (!$currentItems.length) return;

        let activeIndex = parseInt($box.attr('data-active-index') || '-1', 10);

        const setActive = (idx) => {
            $box.attr('data-active-index', String(idx));
            $box.find('.hkx-search-suggest-active').removeClass('hkx-search-suggest-active');

            const $target = $currentItems.eq(idx);
            if ($target && $target.length) {
                // highlight selected row
                $target.addClass('hkx-search-suggest-active');
                // ensure visible
                if ($box[0] && $box[0].scrollHeight > $box[0].clientHeight) {
                    try {
                        $target[0].scrollIntoView({ block: 'nearest' });
                    } catch (err) {}
                }
            }
        };

        if (key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = activeIndex < 0 ? 0 : Math.min($currentItems.length - 1, activeIndex + 1);
            setActive(activeIndex);
            return;
        }

        if (key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = activeIndex < 0 ? ($currentItems.length - 1) : Math.max(0, activeIndex - 1);
            setActive(activeIndex);
            return;
        }

        if (key === 'Enter') {
            // If nothing active, fall back to first ranked
            if (activeIndex < 0) activeIndex = 0;
            const $target = $currentItems.eq(activeIndex);
            if ($target && $target.length) {
                e.preventDefault();
                const href = $target.attr('href');
                if (href) window.location.href = href;
            }
            return;
        }

        if (key === 'Escape') {
            $box.hide();
            $box.attr('data-active-index', '-1');
            $box.find('.hkx-search-suggest-active').removeClass('hkx-search-suggest-active');
        }
    });
}


