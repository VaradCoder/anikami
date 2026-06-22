<?php
// Self-contained reviews widget. Caller must set $reviewsAnimeId before
// including this file.
$rAnimeId = (string)($reviewsAnimeId ?? '');
$rViewer  = function_exists('app_current_user') ? app_current_user() : null;
?>
<style>
.ak-rv-wrap { margin-top: 28px; }
.ak-rv-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.ak-rv-title { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; color: var(--text-main); }
.ak-rv-title i { color: var(--accent); }
.ak-rv-summary { display: flex; align-items: center; gap: 10px; }
.ak-rv-avg { font-size: 26px; font-weight: 700; color: var(--accent); }
.ak-rv-avg-sub { font-size: 11.5px; color: var(--text-muted); }
.ak-rv-form { background: var(--bg-card); border: 1px solid rgba(255,255,255,.06); border-radius: var(--radius-md); padding: 16px; margin-bottom: 18px; }
.ak-rv-rating-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.ak-rv-rating-row label { font-size: 12.5px; color: var(--text-secondary); }
.ak-rv-stars { display: flex; gap: 3px; }
.ak-rv-star { font-size: 18px; color: rgba(255,255,255,.18); cursor: pointer; transition: color .1s; }
.ak-rv-star.active, .ak-rv-star:hover { color: var(--accent); }
.ak-rv-textarea { width: 100%; min-height: 80px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.09); border-radius: var(--radius-sm); padding: 10px 12px; color: var(--text-main); font-size: 13px; resize: vertical; outline: none; font-family: inherit; margin-bottom: 10px; }
.ak-rv-textarea:focus { border-color: var(--accent); }
.ak-rv-form-actions { display: flex; align-items: center; justify-content: space-between; }
.ak-rv-spoiler-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); cursor: pointer; }
.ak-rv-spoiler-toggle input { accent-color: var(--accent); }
.ak-rv-submit { background: var(--accent); border: none; border-radius: var(--radius-sm); color: #fff; font-size: 12.5px; font-weight: 700; padding: 8px 18px; cursor: pointer; }
.ak-rv-submit:hover { background: var(--accent-hover); }
.ak-rv-login-note { font-size: 12.5px; color: var(--text-muted); padding: 14px; background: var(--bg-card); border-radius: var(--radius-sm); margin-bottom: 18px; }
.ak-rv-login-note a { color: var(--accent); text-decoration: none; }
.ak-rv-sort { display: flex; gap: 6px; }
.ak-rv-sort button { background: none; border: 1px solid rgba(255,255,255,.1); border-radius: 6px; color: var(--text-muted); font-size: 11.5px; padding: 4px 10px; cursor: pointer; }
.ak-rv-sort button.active { background: rgba(200,16,46,.12); color: var(--accent); border-color: rgba(200,16,46,.3); }
.ak-rv-list { list-style: none; }
.ak-rv-item { padding: 16px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
.ak-rv-item.featured { background: rgba(200,16,46,.04); border-radius: var(--radius-sm); padding: 16px 14px; }
.ak-rv-meta { display: flex; align-items: center; gap: 9px; margin-bottom: 6px; flex-wrap: wrap; }
.ak-rv-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,var(--accent),#8b0000); display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff; }
.ak-rv-user { font-weight: 700; color: var(--text-main); font-size: 13px; }
.ak-rv-score { font-size: 11.5px; font-weight: 700; color: var(--accent); background: rgba(200,16,46,.12); padding: 1px 8px; border-radius: 5px; }
.ak-rv-time { font-size: 11.5px; color: var(--text-muted); margin-left: auto; }
.ak-rv-text { font-size: 13.5px; color: var(--text-secondary); line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
.ak-rv-text.is-spoiler { filter: blur(5px); cursor: pointer; }
.ak-rv-text.is-spoiler.revealed { filter: none; cursor: text; }
.ak-rv-actions { display: flex; align-items: center; gap: 14px; margin-top: 8px; }
.ak-rv-action-btn { background: none; border: none; color: var(--text-muted); font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 5px; padding: 0; }
.ak-rv-action-btn:hover { color: var(--text-main); }
.ak-rv-action-btn.voted { color: var(--accent); }
.ak-rv-empty { font-size: 13px; color: var(--text-muted); padding: 20px 0; text-align: center; }
</style>

<div class="ak-rv-wrap" id="akReviews" data-anime="<?=htmlspecialchars($rAnimeId, ENT_QUOTES)?>">
  <div class="ak-rv-head">
    <div class="ak-rv-title"><i class="fas fa-star-half-stroke"></i> Reviews</div>
    <div class="ak-rv-summary" id="akRvSummary"></div>
  </div>

  <?php if ($rViewer): ?>
  <div class="ak-rv-form">
    <div class="ak-rv-rating-row">
      <label>Your rating:</label>
      <div class="ak-rv-stars" id="akRvStars">
        <?php for ($i = 1; $i <= 10; $i++): ?><span class="ak-rv-star" data-val="<?=$i?>"><i class="fas fa-star"></i></span><?php endfor; ?>
      </div>
      <span id="akRvRatingLabel" style="font-size:12.5px;color:var(--text-muted);">—/10</span>
    </div>
    <textarea class="ak-rv-textarea" id="akRvInput" placeholder="What did you think? (this replaces your existing review for this anime if you already left one)" maxlength="4000"></textarea>
    <div class="ak-rv-form-actions">
      <label class="ak-rv-spoiler-toggle"><input type="checkbox" id="akRvSpoiler"> Contains spoilers</label>
      <button class="ak-rv-submit" id="akRvSubmit">Post Review</button>
    </div>
  </div>
  <?php else: ?>
  <div class="ak-rv-login-note"><a href="<?=$websiteUrl?>/login.php">Log in</a> to rate and review.</div>
  <?php endif; ?>

  <div class="ak-rv-sort" style="margin-bottom:14px;">
    <button class="active" data-sort="helpful">Most Helpful</button>
    <button data-sort="recent">Most Recent</button>
  </div>

  <ul class="ak-rv-list" id="akRvList"><li class="ak-rv-empty">Loading reviews…</li></ul>
</div>

<script>
(function(){
  var root = document.getElementById('akReviews');
  if (!root) return;
  var animeId = root.dataset.anime;
  var apiBase = '<?=$websiteUrl?>/api/reviews.php';
  var csrf = '<?=function_exists('app_csrf_token') ? app_csrf_token() : ''?>';
  var isLoggedIn = <?=$rViewer ? 'true' : 'false'?>;
  var listEl = document.getElementById('akRvList');
  var summaryEl = document.getElementById('akRvSummary');
  var currentSort = 'helpful';
  var selectedRating = 0;

  function esc(s) { return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function timeAgo(iso) {
    var sec = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
    if (sec < 60) return 'just now';
    if (sec < 3600) return Math.floor(sec/60) + 'm ago';
    if (sec < 86400) return Math.floor(sec/3600) + 'h ago';
    return Math.floor(sec/86400) + 'd ago';
  }
  function post(action, data) {
    var fd = new FormData();
    fd.append('action', action); fd.append('csrf', csrf);
    Object.keys(data || {}).forEach(function(k){ fd.append(k, data[k]); });
    return fetch(apiBase, { method: 'POST', body: fd }).then(function(r){ return r.json(); });
  }

  var starsEl = document.getElementById('akRvStars');
  if (starsEl) {
    starsEl.addEventListener('click', function(e){
      var star = e.target.closest('.ak-rv-star');
      if (!star) return;
      selectedRating = parseInt(star.dataset.val, 10);
      Array.prototype.forEach.call(starsEl.children, function(s, i){ s.classList.toggle('active', i < selectedRating); });
      document.getElementById('akRvRatingLabel').textContent = selectedRating + '/10';
    });
  }

  function renderReview(r) {
    var li = document.createElement('li');
    li.className = 'ak-rv-item' + (r.is_featured ? ' featured' : '');
    var spoilerCls = r.has_spoilers ? ' is-spoiler' : '';
    li.innerHTML =
      '<div class="ak-rv-meta">' +
        '<div class="ak-rv-avatar">' + esc(r.username.charAt(0).toUpperCase()) + '</div>' +
        '<span class="ak-rv-user">' + esc(r.username) + '</span>' +
        '<span class="ak-rv-score">' + r.rating + '/10</span>' +
        (r.is_featured ? '<span class="ak-rv-score" style="color:#fcd34d;background:rgba(245,158,11,.12);">FEATURED</span>' : '') +
        '<span class="ak-rv-time">' + timeAgo(r.created_at) + (r.edited_at ? ' · edited' : '') + '</span>' +
      '</div>' +
      '<div class="ak-rv-text' + spoilerCls + '" data-text="' + esc(r.body) + '">' + (r.has_spoilers ? 'Spoiler — click to reveal' : esc(r.body)) + '</div>' +
      '<div class="ak-rv-actions">' +
        '<button class="ak-rv-action-btn ak-rv-helpful' + (r.voted_by_viewer ? ' voted' : '') + '" data-id="' + r.id + '"><i class="fas fa-thumbs-up"></i> Helpful (' + r.helpful_count + ')</button>' +
        '<button class="ak-rv-action-btn ak-rv-report" data-id="' + r.id + '"><i class="fas fa-flag"></i> Report</button>' +
      '</div>';
    var spoilerEl = li.querySelector('.ak-rv-text.is-spoiler');
    if (spoilerEl) spoilerEl.addEventListener('click', function(){ spoilerEl.classList.add('revealed'); spoilerEl.textContent = spoilerEl.dataset.text; });
    return li;
  }

  function load() {
    fetch(apiBase + '?action=list&anime_id=' + encodeURIComponent(animeId) + '&sort=' + currentSort)
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) return;
        summaryEl.innerHTML = d.summary.average !== null
          ? '<span class="ak-rv-avg">' + d.summary.average + '</span><span class="ak-rv-avg-sub">/10 · ' + d.summary.count + ' review' + (d.summary.count===1?'':'s') + '</span>'
          : '<span class="ak-rv-avg-sub">No ratings yet</span>';
        if (d.my_review && document.getElementById('akRvInput')) {
          document.getElementById('akRvInput').value = d.my_review.body;
          document.getElementById('akRvSpoiler').checked = !!d.my_review.has_spoilers;
          selectedRating = d.my_review.rating;
          if (starsEl) {
            Array.prototype.forEach.call(starsEl.children, function(s, i){ s.classList.toggle('active', i < selectedRating); });
            document.getElementById('akRvRatingLabel').textContent = selectedRating + '/10';
          }
        }
        listEl.innerHTML = '';
        if (!d.items.length) { listEl.innerHTML = '<li class="ak-rv-empty">No reviews yet — be the first to rate this anime.</li>'; return; }
        d.items.forEach(function(r){ listEl.appendChild(renderReview(r)); });
      });
  }

  document.querySelectorAll('.ak-rv-sort button').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('.ak-rv-sort button').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      currentSort = btn.dataset.sort;
      load();
    });
  });

  var submitBtn = document.getElementById('akRvSubmit');
  if (submitBtn) {
    submitBtn.addEventListener('click', function(){
      var body = document.getElementById('akRvInput').value.trim();
      if (!selectedRating) { alert('Pick a rating from 1 to 10 first.'); return; }
      if (!body) { alert('Write a few words about why.'); return; }
      var hasSpoilers = document.getElementById('akRvSpoiler').checked;
      post('upsert', { anime_id: animeId, rating: selectedRating, body: body, has_spoilers: hasSpoilers ? 1 : 0 }).then(function(d){
        if (d.ok) load();
        else alert(d.error || 'Could not post review.');
      });
    });
  }

  listEl.addEventListener('click', function(e){
    var helpfulBtn = e.target.closest('.ak-rv-helpful');
    if (helpfulBtn) {
      if (!isLoggedIn) { alert('Log in to vote.'); return; }
      post('helpful', { id: helpfulBtn.dataset.id }).then(function(d){ if (d.ok) load(); });
      return;
    }
    var reportBtn = e.target.closest('.ak-rv-report');
    if (reportBtn) {
      if (!isLoggedIn) { alert('Log in to report reviews.'); return; }
      var reason = prompt('Reason: spoiler, spam, or abuse');
      if (!reason) return;
      post('report', { target_id: reportBtn.dataset.id, reason: reason.trim() }).then(function(d){
        alert(d.ok ? 'Reported. Thanks for flagging it.' : (d.error || 'Could not submit report.'));
      });
    }
  });

  load();
})();
</script>
