<?php /* Anikami – new footer partial */ ?>
<!-- ============================  FOOTER  ============================ -->
<div id="ak-footer">
  <div class="ak-footer-inner">
    <div class="ak-footer-top">
      <div class="ak-footer-brand">
        <div class="ak-f-logo">❤Anikami</div>
        <div class="ak-f-tagline">Watch anime online in high quality. No ads, fast streaming, and daily updates!</div>
        <div class="ak-footer-social">
          <a href="<?=htmlspecialchars($discord)?>" target="_blank" rel="noopener" class="ak-social-btn dc" aria-label="Join our Discord"><i class="fab fa-discord" aria-hidden="true"></i></a>
          <a href="<?=htmlspecialchars($telegram)?>" target="_blank" rel="noopener" class="ak-social-btn tg" aria-label="Join our Telegram"><i class="fab fa-telegram" aria-hidden="true"></i></a>
          <a href="<?=htmlspecialchars($redit)?>" target="_blank" rel="noopener" class="ak-social-btn rd" aria-label="Visit our Reddit"><i class="fab fa-reddit-alien" aria-hidden="true"></i></a>
          <a href="<?=htmlspecialchars($twitter)?>" target="_blank" rel="noopener" class="ak-social-btn tw" aria-label="Follow us on Twitter"><i class="fab fa-twitter" aria-hidden="true"></i></a>
        </div>
      </div>
      <div class="ak-footer-cols">
        <div class="ak-footer-col">
          <h4>Browse</h4>
          <a href="<?=$websiteUrl?>/anime">Anime List</a>
          <a href="<?=$websiteUrl?>/new-season">New Season</a>
          <a href="<?=$websiteUrl?>/popular">Popular Anime</a>
          <a href="<?=$websiteUrl?>/random" rel="nofollow">Random Anime</a>
          <a href="<?=$websiteUrl?>/type/movies">Anime Movies</a>
        </div>
        <div class="ak-footer-col">
          <h4>Info</h4>
          <a href="#">About Us</a>
          <a href="mailto:<?=htmlspecialchars($contactEmail)?>">Contact</a>
          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
          <a href="#">DMCA</a>
        </div>
        <div class="ak-footer-col">
          <h4>Support</h4>
          <a href="#">FAQ</a>
          <a href="#">Report Error</a>
          <a href="#">Request Anime</a>
          <a href="<?=htmlspecialchars($donate)?>">Donate</a>
        </div>
      </div>
    </div>

    <!-- A–Z list -->
    <div class="ak-footer-az">
      <div class="ak-footer-az-label">A–Z LIST — Browse anime alphabetically</div>
      <div class="ak-az-list">
        <a href="<?=$websiteUrl?>/az/all">All</a>
        <?php foreach (range('A','Z') as $letter): ?>
        <a href="<?=$websiteUrl?>/az/<?=strtolower($letter)?>"><?=$letter?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ak-footer-bottom">
      <span><?=$websiteTitle?> does not store any files on our server, we only link to media hosted on 3rd-party services.</span>
      <div class="ak-footer-links">
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
        <a href="#">DMCA</a>
        <a href="mailto:<?=htmlspecialchars($contactEmail)?>">Contact</a>
      </div>
      <span>Copyright &copy; <?=date('Y')?> <?=$websiteTitle?>. All Rights Reserved.</span>
    </div>
  </div>
</div>

<script>
(function(){
  /* ── Header interactions ── */
  var hamburger = document.getElementById('akHamburger');
  var mobileNav = document.getElementById('akMobileNav');
  if (hamburger && mobileNav) {
    hamburger.addEventListener('click', function() {
      mobileNav.classList.toggle('open');
    });
    mobileNav.addEventListener('click', function(e) {
      if (e.target === mobileNav) mobileNav.classList.remove('open');
    });
  }

  /* ── User dropdown ── */
  var avatarBtn = document.getElementById('akUserAvatarBtn');
  var userDrop  = document.getElementById('akUserDrop');
  if (avatarBtn && userDrop) {
    avatarBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      userDrop.classList.toggle('open');
    });
    document.addEventListener('click', function() {
      userDrop.classList.remove('open');
    });
  }

  /* ── Search ── */
  var searchInput    = document.getElementById('akSearchInput');
  var searchDropdown = document.getElementById('akSearchDropdown');
  var searchBtn      = document.getElementById('akSearchBtn');
  var websiteUrl     = '<?=$websiteUrl?>';
  var fallbackPoster = websiteUrl + '/files/images/no_poster.jpg';

  function escHtml(s) {
    return String(s||'').replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function renderSearchResults(list) {
    if (!list || !list.length) {
      searchDropdown.innerHTML = '<div class="ak-search-item"><div class="ak-search-item-info"><h4>No results found</h4><p>Try another keyword.</p></div></div>';
      searchDropdown.style.display = 'block';
      return;
    }
    searchDropdown.innerHTML = list.map(function(item) {
      var img = escHtml(item.image || item.animeImg || item.imgUrl || fallbackPoster);
      var title = escHtml(item.title || item.animeTitle || 'Unknown');
      var meta  = escHtml((item.type || 'TV') + (item.releasedDate ? ' · ' + item.releasedDate : ''));
      var href  = websiteUrl + '/anime/' + encodeURIComponent(item.animeId || '');
      return '<a class="ak-search-item" href="' + href + '">' +
               '<img src="' + img + '" alt="' + title + '" onerror="this.src=\'' + escHtml(fallbackPoster) + '\'">' +
               '<div class="ak-search-item-info"><h4>' + title + '</h4><p>' + meta + '</p></div>' +
             '</a>';
    }).join('');
    searchDropdown.style.display = 'block';
  }

  function renderSearchSkeleton() {
    var html = '';
    for (var i = 0; i < 4; i++) {
      html += '<div class="ak-search-item-skel">'
            + '<div class="ak-search-skel-img"></div>'
            + '<div class="ak-search-skel-lines">'
            + '<div class="ak-search-skel-line w70"></div>'
            + '<div class="ak-search-skel-line w40"></div>'
            + '</div></div>';
    }
    searchDropdown.innerHTML = html;
    searchDropdown.style.display = 'block';
  }

  var searchTimer;
  if (searchInput && searchDropdown) {
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimer);
      var q = searchInput.value.trim();
      if (q.length < 2) { searchDropdown.style.display = 'none'; return; }
      renderSearchSkeleton();
      searchTimer = setTimeout(function() {
        fetch(websiteUrl + '/api/search.php?keyword=' + encodeURIComponent(q) + '&limit=8')
          .then(function(r){ return r.json(); })
          .then(function(data){
            var results = (data.data && data.data.results) ? data.data.results
                        : (Array.isArray(data.data) ? data.data : []);
            renderSearchResults(results);
          })
          .catch(function(){
            searchDropdown.style.display = 'none';
          });
      }, 320);
    });

    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        var q = searchInput.value.trim();
        if (q) window.location.href = websiteUrl + '/search?keyword=' + encodeURIComponent(q);
      }
    });

    if (searchBtn) {
      searchBtn.addEventListener('click', function() {
        var q = searchInput.value.trim();
        if (q) window.location.href = websiteUrl + '/search?keyword=' + encodeURIComponent(q);
      });
    }

    document.addEventListener('click', function(e) {
      var wrap = document.getElementById('akSearchWrap');
      if (wrap && !wrap.contains(e.target)) searchDropdown.style.display = 'none';
    });
  }
})();
</script>
<!-- Lazy-load images: lazysizes converts data-src → src when in viewport -->
<script>window.lazySizesConfig = window.lazySizesConfig || {}; window.lazySizesConfig.loadMode = 1;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js" async></script>

<script>
/* ── Navigation progress bar + page-transition skeleton ── */
(function() {
  var bar     = document.getElementById('ak-nprog-bar');
  var overlay = document.getElementById('ak-nav-overlay');
  var progTimer, progTimer2;

  function nprogStart() {
    if (!bar) return;
    clearTimeout(progTimer); clearTimeout(progTimer2);
    bar.style.transition = 'none';
    bar.style.width = '0';
    bar.style.opacity = '1';
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        bar.style.transition = 'width 4s cubic-bezier(.04,.6,.18,1)';
        bar.style.width = '80%';
      });
    });
  }

  function nprogDone() {
    if (!bar) return;
    clearTimeout(progTimer); clearTimeout(progTimer2);
    bar.style.transition = 'width 200ms ease';
    bar.style.width = '100%';
    progTimer = setTimeout(function() {
      bar.style.transition = 'opacity 250ms ease';
      bar.style.opacity = '0';
      progTimer2 = setTimeout(function() {
        bar.style.width = '0';
        bar.style.transition = 'none';
        bar.style.opacity = '1';
      }, 260);
    }, 200);
  }

  function isInternal(a) {
    var href = a.getAttribute('href') || '';
    if (!href || href === '#' || href.indexOf('javascript') === 0) return false;
    try {
      var url = new URL(href, window.location.href);
      return url.origin === window.location.origin;
    } catch (e) { return false; }
  }

  /* Bind all links — use event delegation so dynamically added links work too */
  document.addEventListener('click', function(e) {
    var a = e.target.closest('a[href]');
    if (!a) return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if (a.getAttribute('target') === '_blank') return;
    if (!isInternal(a)) return;
    /* Show progress bar */
    nprogStart();
    /* Show overlay after tiny delay so same-page anchors don't flicker */
    setTimeout(function() {
      if (overlay) overlay.classList.add('visible');
    }, 60);
  });

  /* Page loaded — finish bar and hide overlay */
  nprogDone();
  if (overlay) {
    overlay.classList.remove('visible');
  }

  /* Fallback: hide overlay if back/forward navigation used */
  window.addEventListener('pageshow', function() {
    nprogDone();
    if (overlay) overlay.classList.remove('visible');
  });
})();
</script>
