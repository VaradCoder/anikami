<?php
require('./_config.php');

$postId = (int)($_GET['post'] ?? 0);
$post = $postId > 0 ? app_community_post_get($postId) : null;
$viewer = app_current_user();
$viewerIsAdmin = $viewer && ($viewer['role'] ?? '') === 'admin';

if (!$post || ((int)$post['is_hidden'] === 1 && !$viewerIsAdmin)) {
    http_response_code(404);
    include('./_php/ak_header.php');
    echo '<div style="padding:120px 20px;text-align:center;color:var(--text-secondary)"><i class="fas fa-comment-slash" style="font-size:40px;color:var(--accent);display:block;margin-bottom:14px"></i><h2>Post not found</h2><a href="'.$websiteUrl.'/community" style="color:var(--accent)">&larr; Back to Community</a></div>';
    include('./_php/ak_footer.php');
    exit;
}

$post['liked_by_viewer'] = $viewer ? app_community_post_liked_by($postId, (int)$viewer['id']) : false;
$replies = app_community_reply_list($postId, $viewer ? (int)$viewer['id'] : null, $viewerIsAdmin);
$replyCount = 0;
foreach ($replies as $r) { $replyCount++; $replyCount += count($r['replies']); }

$pageTitle     = app_e($post['title']) . ' — Community — ' . $websiteTitle;
$pageDesc      = mb_substr(strip_tags($post['body']), 0, 160);
$pageRobots    = ((int)$post['is_hidden'] === 1) ? 'noindex, follow' : 'index, follow';
$pageCanonical = $websiteUrl . '/community/post/' . $postId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
<style>
  .ak-cpost-wrap { max-width: 820px; margin: 0 auto; padding: 20px 0; }
  .ak-cpost-card { background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 12px; padding: 18px; margin-bottom: 18px; }
  .ak-cpost-top { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
  .ak-cpost-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 7px; border-radius: 4px; background: rgba(200,16,46,.15); color: var(--accent); }
  .ak-cpost-pinned { color: #fbbf24; font-size: 12px; display: flex; align-items: center; gap: 4px; }
  .ak-cpost-title { font-size: 20px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
  .ak-cpost-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted); margin-bottom: 14px; }
  .ak-cpost-avatar { width: 26px; height: 26px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
  .ak-cpost-body { font-size: 14px; color: var(--text-secondary); line-height: 1.65; white-space: pre-wrap; word-break: break-word; margin-bottom: 12px; }
  .ak-cpost-actions { display: flex; align-items: center; gap: 16px; }
  .ak-cpost-action-btn { background: none; border: none; color: var(--text-muted); font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 0; }
  .ak-cpost-action-btn:hover { color: var(--text-main); }
  .ak-cpost-action-btn.liked { color: var(--accent); }

  .ak-cpost-reply-head { font-size: 15px; font-weight: 700; margin-bottom: 12px; }
  .ak-cr-form { display: flex; gap: 10px; margin-bottom: 20px; }
  .ak-cr-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }
  .ak-cr-form-body { flex: 1; }
  .ak-cr-textarea { width: 100%; min-height: 60px; background: var(--bg-card); border: 1px solid rgba(255,255,255,.1); border-radius: 8px; padding: 9px 12px; color: var(--text-main); font-size: 13px; resize: vertical; font-family: inherit; }
  .ak-cr-submit { margin-top: 6px; background: var(--accent); border: none; border-radius: 7px; color: #fff; font-size: 12.5px; font-weight: 700; padding: 7px 16px; cursor: pointer; }
  .ak-cr-login-note { font-size: 12.5px; color: var(--text-muted); padding: 12px 14px; background: var(--bg-card); border-radius: 8px; margin-bottom: 18px; }
  .ak-cr-login-note a { color: var(--accent); text-decoration: none; }

  .ak-cr-list { list-style: none; margin: 0; padding: 0; }
  .ak-cr-item { display: flex; gap: 10px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
  .ak-cr-body-col { flex: 1; min-width: 0; }
  .ak-cr-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 3px; }
  .ak-cr-user { font-weight: 700; color: var(--text-main); }
  .ak-cr-time { color: var(--text-muted); }
  .ak-cr-text { font-size: 13px; color: var(--text-secondary); line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
  .ak-cr-actions { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
  .ak-cr-action-btn { background: none; border: none; color: var(--text-muted); font-size: 11.5px; cursor: pointer; display: flex; align-items: center; gap: 5px; padding: 0; }
  .ak-cr-action-btn:hover { color: var(--text-main); }
  .ak-cr-action-btn.liked { color: var(--accent); }
  .ak-cr-replies { list-style: none; margin-top: 8px; padding-left: 18px; border-left: 1px solid rgba(255,255,255,.06); }
  .ak-cr-reply-form { display: none; margin-top: 8px; gap: 8px; }
  .ak-cr-reply-form.open { display: flex; }
  .ak-cr-empty { font-size: 13px; color: var(--text-muted); padding: 20px 0; text-align: center; }
</style>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <main class="ak-cpost-wrap">

    <nav class="ak-breadcrumb">
      <a href="<?=$websiteUrl?>"><i class="fas fa-home"></i></a>
      <span class="sep">/</span>
      <a href="<?=$websiteUrl?>/community">Community</a>
      <span class="sep">/</span>
      <span class="current"><?=app_e($post['title'])?></span>
    </nav>

    <div class="ak-cpost-card">
      <div class="ak-cpost-top">
        <?php if ((int)$post['is_pinned'] === 1): ?><span class="ak-cpost-pinned"><i class="fas fa-thumbtack"></i> Pinned</span><?php endif; ?>
        <span class="ak-cpost-tag">#<?=app_e(app_community_category_label($post['category']))?></span>
      </div>
      <div class="ak-cpost-title"><?=app_e($post['title'])?></div>
      <div class="ak-cpost-meta">
        <div class="ak-cpost-avatar"><?=app_e(strtoupper(substr((string)$post['username'], 0, 1)))?></div>
        <span><?=app_e($post['username'])?></span>
        <span>·</span>
        <span><?=app_e(app_time_ago($post['created_at']))?><?=$post['edited_at']?' · edited':''?></span>
      </div>
      <div class="ak-cpost-body"><?=app_e($post['body'])?></div>
      <div class="ak-cpost-actions">
        <button class="ak-cpost-action-btn <?=!empty($post['liked_by_viewer'])?'liked':''?>" id="akCpostLike" data-id="<?=$postId?>">
          <i class="fas fa-heart"></i> <span id="akCpostLikeCount"><?=(int)$post['like_count']?></span>
        </button>
        <span style="font-size:12.5px;color:var(--text-muted)"><i class="fas fa-comment"></i> <?=$replyCount?> replies</span>
        <?php if ($viewer && ((int)$post['user_id'] === (int)$viewer['id'] || $viewerIsAdmin)): ?>
        <button class="ak-cpost-action-btn" id="akCpostDelete" data-id="<?=$postId?>"><i class="fas fa-trash"></i> Delete</button>
        <?php endif; ?>
        <?php if ($viewerIsAdmin): ?>
        <button class="ak-cpost-action-btn" id="akCpostPin" data-id="<?=$postId?>" data-pinned="<?=(int)$post['is_pinned']?>">
          <i class="fas fa-thumbtack"></i> <?=(int)$post['is_pinned']===1?'Unpin':'Pin'?>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="ak-cpost-reply-head">Replies</div>

    <?php if ($viewer): ?>
    <form class="ak-cr-form" id="akCrForm" data-post="<?=$postId?>">
      <div class="ak-cr-avatar"><?=app_e(strtoupper(substr((string)$viewer['username'], 0, 1)))?></div>
      <div class="ak-cr-form-body">
        <textarea class="ak-cr-textarea" id="akCrInput" placeholder="Write a reply…" maxlength="2000"></textarea>
        <button type="submit" class="ak-cr-submit">Reply</button>
      </div>
    </form>
    <?php else: ?>
    <div class="ak-cr-login-note">
      <a href="<?=$websiteUrl?>/login.php">Log in</a> to join the conversation.
    </div>
    <?php endif; ?>

    <?php
    function ak_render_reply(array $r, string $websiteUrl, bool $canModerate, ?array $viewer): void {
        $rid = (int)$r['id'];
        $uname = (string)($r['username'] ?? 'User');
        $isOwner = $viewer && (int)$r['user_id'] === (int)$viewer['id'];
        ?>
        <li class="ak-cr-item" id="akCrItem<?=$rid?>">
          <div class="ak-cr-avatar" style="width:30px;height:30px;font-size:11px"><?=app_e(strtoupper(substr($uname, 0, 1)))?></div>
          <div class="ak-cr-body-col">
            <div class="ak-cr-meta">
              <span class="ak-cr-user"><?=app_e($uname)?></span>
              <span class="ak-cr-time"><?=app_e(app_time_ago($r['created_at']))?><?=$r['edited_at']?' · edited':''?></span>
            </div>
            <div class="ak-cr-text"><?=app_e($r['body'])?></div>
            <div class="ak-cr-actions">
              <button class="ak-cr-action-btn ak-cr-like <?=!empty($r['liked_by_viewer'])?'liked':''?>" data-id="<?=$rid?>">
                <i class="fas fa-heart"></i> <span><?=(int)$r['like_count']?></span>
              </button>
              <?php if ($viewer): ?>
              <button class="ak-cr-action-btn ak-cr-reply-toggle" data-id="<?=$rid?>"><i class="fas fa-reply"></i> Reply</button>
              <?php endif; ?>
              <?php if ($isOwner || $canModerate): ?>
              <button class="ak-cr-action-btn ak-cr-delete" data-id="<?=$rid?>"><i class="fas fa-trash"></i> Delete</button>
              <?php endif; ?>
            </div>
            <?php if ($viewer): ?>
            <form class="ak-cr-reply-form" id="akCrReplyForm<?=$rid?>" data-parent="<?=$rid?>">
              <input type="text" class="ak-cr-textarea" placeholder="Reply…" maxlength="2000" style="min-height:auto">
              <button type="submit" class="ak-cr-submit" style="margin-top:0">Send</button>
            </form>
            <?php endif; ?>
            <?php if (!empty($r['replies'])): ?>
            <ul class="ak-cr-replies">
              <?php foreach ($r['replies'] as $child) ak_render_reply($child, $websiteUrl, $canModerate, $viewer); ?>
            </ul>
            <?php endif; ?>
          </div>
        </li>
        <?php
    }
    ?>

    <?php if (!empty($replies)): ?>
    <ul class="ak-cr-list" id="akCrList">
      <?php foreach ($replies as $r) ak_render_reply($r, $websiteUrl, $viewerIsAdmin, $viewer); ?>
    </ul>
    <?php else: ?>
    <div class="ak-cr-empty" id="akCrEmpty">No replies yet — be the first to respond.</div>
    <?php endif; ?>

  </main>
</div>

<script>
(function(){
  var csrf = '<?=app_e(app_csrf_token())?>';
  var apiUrl = '<?=$websiteUrl?>/api/community.php';
  var isLoggedIn = <?=$viewer?'true':'false'?>;

  function post(action, params) {
    var body = new URLSearchParams(Object.assign({ action: action, csrf: csrf }, params));
    return fetch(apiUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
      .then(function(r){ return r.json(); });
  }

  /* Post like */
  var likeBtn = document.getElementById('akCpostLike');
  if (likeBtn) {
    likeBtn.addEventListener('click', function(){
      if (!isLoggedIn) { window.location.href = '<?=$websiteUrl?>/login.php'; return; }
      post('like_post', { id: likeBtn.dataset.id }).then(function(data){
        if (!data.ok) return;
        likeBtn.classList.toggle('liked', data.liked);
        var countEl = document.getElementById('akCpostLikeCount');
        countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + (data.liked ? 1 : -1);
      });
    });
  }

  /* Post delete */
  var delBtn = document.getElementById('akCpostDelete');
  if (delBtn) {
    delBtn.addEventListener('click', function(){
      if (!confirm('Delete this post? This cannot be undone.')) return;
      post('delete_post', { id: delBtn.dataset.id }).then(function(data){
        if (data.ok) window.location.href = '<?=$websiteUrl?>/community';
      });
    });
  }

  /* Post pin (admin) */
  var pinBtn = document.getElementById('akCpostPin');
  if (pinBtn) {
    pinBtn.addEventListener('click', function(){
      var nowPinned = pinBtn.dataset.pinned !== '1';
      post('pin_post', { id: pinBtn.dataset.id, pinned: nowPinned ? '1' : '' }).then(function(data){
        if (data.ok) window.location.reload();
      });
    });
  }

  /* Top-level reply form */
  var crForm = document.getElementById('akCrForm');
  if (crForm) {
    crForm.addEventListener('submit', function(e){
      e.preventDefault();
      var input = document.getElementById('akCrInput');
      var val = input.value.trim();
      if (!val) return;
      post('reply', { post_id: crForm.dataset.post, body: val }).then(function(data){
        if (data.ok) window.location.reload();
      });
    });
  }

  /* Delegated handlers for reply-level actions (like/delete/reply-toggle/nested-submit) */
  document.addEventListener('click', function(e){
    var likeEl = e.target.closest('.ak-cr-like');
    if (likeEl) {
      if (!isLoggedIn) { window.location.href = '<?=$websiteUrl?>/login.php'; return; }
      post('like_reply', { id: likeEl.dataset.id }).then(function(data){
        if (!data.ok) return;
        likeEl.classList.toggle('liked', data.liked);
        var span = likeEl.querySelector('span');
        span.textContent = (parseInt(span.textContent, 10) || 0) + (data.liked ? 1 : -1);
      });
      return;
    }
    var delEl = e.target.closest('.ak-cr-delete');
    if (delEl) {
      if (!confirm('Delete this reply?')) return;
      post('delete_reply', { id: delEl.dataset.id }).then(function(data){
        if (data.ok) window.location.reload();
      });
      return;
    }
    var toggleEl = e.target.closest('.ak-cr-reply-toggle');
    if (toggleEl) {
      var f = document.getElementById('akCrReplyForm' + toggleEl.dataset.id);
      if (f) f.classList.toggle('open');
      return;
    }
  });

  document.addEventListener('submit', function(e){
    var f = e.target.closest('.ak-cr-reply-form');
    if (!f) return;
    e.preventDefault();
    var input = f.querySelector('input');
    var val = input.value.trim();
    if (!val) return;
    post('reply', { post_id: '<?=$postId?>', parent_id: f.dataset.parent, body: val }).then(function(data){
      if (data.ok) window.location.reload();
    });
  });
})();
</script>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
