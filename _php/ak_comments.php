<?php
// Self-contained comments widget. Caller must set, before including this file:
//   $commentsAnimeId (string)        — required
//   $commentsEpisode (int|null)      — null = anime-level thread
// Relies on globals already set by _config.php: $websiteUrl, $viewer (if any).
$cAnimeId  = (string)($commentsAnimeId ?? '');
$cEpisode  = $commentsEpisode ?? null;
$cViewer   = function_exists('app_current_user') ? app_current_user() : null;
?>
<style>
.ak-cm-wrap { margin-top: 28px; }
.ak-cm-head { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 14px; }
.ak-cm-head i { color: var(--accent); }
.ak-cm-form { display: flex; gap: 10px; margin-bottom: 18px; }
.ak-cm-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,var(--accent),#8b0000); display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0; }
.ak-cm-form-body { flex: 1; }
.ak-cm-textarea { width: 100%; min-height: 64px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.09); border-radius: var(--radius-sm); padding: 10px 12px; color: var(--text-main); font-size: 13px; resize: vertical; outline: none; font-family: inherit; }
.ak-cm-textarea:focus { border-color: var(--accent); }
.ak-cm-form-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }
.ak-cm-spoiler-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); cursor: pointer; user-select: none; }
.ak-cm-spoiler-toggle input { accent-color: var(--accent); }
.ak-cm-submit { background: var(--accent); border: none; border-radius: var(--radius-sm); color: #fff; font-size: 12.5px; font-weight: 700; padding: 8px 16px; cursor: pointer; }
.ak-cm-submit:hover { background: var(--accent-hover); }
.ak-cm-submit:disabled { opacity: .5; cursor: not-allowed; }
.ak-cm-login-note { font-size: 12.5px; color: var(--text-muted); padding: 14px; background: var(--bg-card); border-radius: var(--radius-sm); margin-bottom: 18px; }
.ak-cm-login-note a { color: var(--accent); text-decoration: none; }
.ak-cm-list { list-style: none; }
.ak-cm-item { display: flex; gap: 10px; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
.ak-cm-item.pinned { background: rgba(200,16,46,.04); border-radius: var(--radius-sm); padding: 14px 12px; }
.ak-cm-body-col { flex: 1; min-width: 0; }
.ak-cm-meta { display: flex; align-items: center; gap: 8px; font-size: 12.5px; margin-bottom: 4px; flex-wrap: wrap; }
.ak-cm-user { font-weight: 700; color: var(--text-main); }
.ak-cm-time { color: var(--text-muted); }
.ak-cm-pin-badge, .ak-cm-spoiler-badge { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; }
.ak-cm-pin-badge { background: rgba(200,16,46,.15); color: #ff6b81; }
.ak-cm-spoiler-badge { background: rgba(245,158,11,.15); color: #fcd34d; }
.ak-cm-text { font-size: 13.5px; color: var(--text-secondary); line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.ak-cm-text.is-spoiler { filter: blur(5px); cursor: pointer; transition: filter .15s; }
.ak-cm-text.is-spoiler.revealed { filter: none; cursor: text; }
.ak-cm-actions { display: flex; align-items: center; gap: 14px; margin-top: 6px; }
.ak-cm-action-btn { background: none; border: none; color: var(--text-muted); font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 5px; padding: 0; }
.ak-cm-action-btn:hover { color: var(--text-main); }
.ak-cm-action-btn.liked { color: var(--accent); }
.ak-cm-replies { list-style: none; margin-top: 10px; padding-left: 20px; border-left: 1px solid rgba(255,255,255,.06); }
.ak-cm-reply-form { display: none; margin-top: 8px; }
.ak-cm-reply-form.open { display: flex; gap: 8px; }
.ak-cm-empty { font-size: 13px; color: var(--text-muted); padding: 20px 0; text-align: center; }
</style>

<div class="ak-cm-wrap" id="akComments" data-anime="<?=htmlspecialchars($cAnimeId, ENT_QUOTES)?>" data-episode="<?=$cEpisode === null ? '' : (int)$cEpisode?>">
  <div class="ak-cm-head"><i class="fas fa-comments"></i> Comments <span id="akCmCount" style="color:var(--text-muted);font-weight:400;font-size:13px;"></span></div>

  <?php if ($cViewer): ?>
  <div class="ak-cm-form">
    <div class="ak-cm-avatar"><?=mb_strtoupper(mb_substr($cViewer['username'], 0, 1))?></div>
    <div class="ak-cm-form-body">
      <textarea class="ak-cm-textarea" id="akCmInput" placeholder="Share your thoughts…" maxlength="2000"></textarea>
      <div class="ak-cm-form-actions">
        <label class="ak-cm-spoiler-toggle"><input type="checkbox" id="akCmSpoiler"> Contains spoilers</label>
        <button class="ak-cm-submit" id="akCmSubmit">Post Comment</button>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="ak-cm-login-note"><a href="<?=$websiteUrl?>/login.php">Log in</a> to join the conversation.</div>
  <?php endif; ?>

  <ul class="ak-cm-list" id="akCmList"><li class="ak-cm-empty">Loading comments…</li></ul>
</div>

<script>
(function(){
  var root = document.getElementById('akComments');
  if (!root) return;
  var animeId = root.dataset.anime;
  var episode = root.dataset.episode;
  var apiBase = '<?=$websiteUrl?>/api/comments.php';
  var csrf = '<?=function_exists('app_csrf_token') ? app_csrf_token() : ''?>';
  var isLoggedIn = <?=$cViewer ? 'true' : 'false'?>;
  var listEl = document.getElementById('akCmList');
  var countEl = document.getElementById('akCmCount');

  function esc(s) {
    return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
  }
  function timeAgo(iso) {
    var sec = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
    if (sec < 60) return 'just now';
    if (sec < 3600) return Math.floor(sec/60) + 'm ago';
    if (sec < 86400) return Math.floor(sec/3600) + 'h ago';
    return Math.floor(sec/86400) + 'd ago';
  }

  function post(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('csrf', csrf);
    Object.keys(data || {}).forEach(function(k){ fd.append(k, data[k]); });
    return fetch(apiBase, { method: 'POST', body: fd }).then(function(r){ return r.json(); });
  }

  function renderComment(c, depth) {
    var li = document.createElement('li');
    li.className = 'ak-cm-item' + (c.is_pinned ? ' pinned' : '');
    var spoilerCls = c.is_spoiler ? ' is-spoiler' : '';
    li.innerHTML =
      '<div class="ak-cm-avatar" style="width:32px;height:32px;font-size:12px;">' + esc(c.username.charAt(0).toUpperCase()) + '</div>' +
      '<div class="ak-cm-body-col">' +
        '<div class="ak-cm-meta">' +
          '<span class="ak-cm-user">' + esc(c.username) + '</span>' +
          (c.is_pinned ? '<span class="ak-cm-pin-badge">PINNED</span>' : '') +
          (c.is_spoiler ? '<span class="ak-cm-spoiler-badge">SPOILER</span>' : '') +
          '<span class="ak-cm-time">' + timeAgo(c.created_at) + (c.edited_at ? ' · edited' : '') + '</span>' +
        '</div>' +
        '<div class="ak-cm-text' + spoilerCls + '" data-text="' + esc(c.body) + '">' + (c.is_spoiler ? 'Spoiler — click to reveal' : esc(c.body)) + '</div>' +
        '<div class="ak-cm-actions">' +
          '<button class="ak-cm-action-btn ak-cm-like' + (c.liked_by_viewer ? ' liked' : '') + '" data-id="' + c.id + '"><i class="fas fa-heart"></i> <span>' + c.like_count + '</span></button>' +
          (depth < 2 ? '<button class="ak-cm-action-btn ak-cm-reply-toggle" data-id="' + c.id + '"><i class="fas fa-reply"></i> Reply</button>' : '') +
          '<button class="ak-cm-action-btn ak-cm-report" data-id="' + c.id + '"><i class="fas fa-flag"></i> Report</button>' +
        '</div>' +
        '<div class="ak-cm-reply-form" id="akCmReplyForm' + c.id + '">' +
          '<textarea class="ak-cm-textarea" rows="2" style="min-height:0;"></textarea>' +
          '<button class="ak-cm-submit ak-cm-reply-submit" data-id="' + c.id + '">Reply</button>' +
        '</div>' +
        '<ul class="ak-cm-replies" id="akCmReplies' + c.id + '"></ul>' +
      '</div>';

    var spoilerEl = li.querySelector('.ak-cm-text.is-spoiler');
    if (spoilerEl) {
      spoilerEl.addEventListener('click', function(){
        spoilerEl.classList.add('revealed');
        spoilerEl.textContent = spoilerEl.dataset.text;
      });
    }
    var repliesEl = li.querySelector('.ak-cm-replies');
    (c.replies || []).forEach(function(r){ repliesEl.appendChild(renderComment(r, depth + 1)); });
    return li;
  }

  function load() {
    fetch(apiBase + '?action=list&anime_id=' + encodeURIComponent(animeId) + (episode ? '&episode=' + encodeURIComponent(episode) : ''))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) return;
        countEl.textContent = '(' + d.count + ')';
        listEl.innerHTML = '';
        if (!d.items.length) { listEl.innerHTML = '<li class="ak-cm-empty">No comments yet — be the first to say something.</li>'; return; }
        d.items.forEach(function(c){ listEl.appendChild(renderComment(c, 0)); });
      });
  }

  document.getElementById('akCmSubmit') && document.getElementById('akCmSubmit').addEventListener('click', function(){
    var input = document.getElementById('akCmInput');
    var body = input.value.trim();
    if (!body) return;
    var isSpoiler = document.getElementById('akCmSpoiler').checked;
    post('create', { anime_id: animeId, episode: episode, body: body, is_spoiler: isSpoiler ? 1 : 0 }).then(function(d){
      if (d.ok) { input.value = ''; document.getElementById('akCmSpoiler').checked = false; load(); }
      else alert(d.error || 'Could not post comment.');
    });
  });

  listEl.addEventListener('click', function(e){
    var likeBtn = e.target.closest('.ak-cm-like');
    if (likeBtn) {
      if (!isLoggedIn) { alert('Log in to like comments.'); return; }
      post('like', { id: likeBtn.dataset.id }).then(function(d){ if (d.ok) load(); });
      return;
    }
    var replyToggle = e.target.closest('.ak-cm-reply-toggle');
    if (replyToggle) {
      if (!isLoggedIn) { alert('Log in to reply.'); return; }
      var form = document.getElementById('akCmReplyForm' + replyToggle.dataset.id);
      if (form) form.classList.toggle('open');
      return;
    }
    var replySubmit = e.target.closest('.ak-cm-reply-submit');
    if (replySubmit) {
      var form2 = document.getElementById('akCmReplyForm' + replySubmit.dataset.id);
      var ta = form2.querySelector('textarea');
      var body = ta.value.trim();
      if (!body) return;
      post('create', { anime_id: animeId, episode: episode, parent_id: replySubmit.dataset.id, body: body }).then(function(d){
        if (d.ok) { ta.value = ''; form2.classList.remove('open'); load(); }
        else alert(d.error || 'Could not post reply.');
      });
      return;
    }
    var reportBtn = e.target.closest('.ak-cm-report');
    if (reportBtn) {
      if (!isLoggedIn) { alert('Log in to report comments.'); return; }
      var reason = prompt('Reason: spoiler, spam, abuse, or broken_stream');
      if (!reason) return;
      post('report', { target_type: 'comment', target_id: reportBtn.dataset.id, reason: reason.trim() }).then(function(d){
        alert(d.ok ? 'Reported. Thanks for flagging it.' : (d.error || 'Could not submit report.'));
      });
    }
  });

  load();
})();
</script>
