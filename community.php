<?php
require('./_config.php');

$category = trim((string)($_GET['category'] ?? ''));
if ($category !== '' && !in_array($category, APP_COMMUNITY_CATEGORIES, true)) {
    $category = '';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

$posts = app_community_post_list($category !== '' ? $category : null, $limit, ($page - 1) * $limit);
$total = app_community_post_count($category !== '' ? $category : null);
$lastPage = max(1, (int)ceil($total / $limit));

$viewer = app_current_user();

$pageTitle     = 'Community — ' . $websiteTitle;
$pageDesc      = 'Discuss anime, share updates, and hang out with other ' . $websiteTitle . ' users.';
$pageRobots    = 'index, follow';
$pageCanonical = $websiteUrl . '/community' . ($category !== '' ? '?category=' . urlencode($category) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
<style>
  .ak-comm-wrap { max-width: 820px; margin: 0 auto; padding: 20px 0; }
  .ak-comm-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
  .ak-comm-title { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
  .ak-comm-title i { color: var(--accent); }
  .ak-btn-newpost { padding: 9px 18px; border-radius: 8px; background: var(--accent); color: #fff; border: none; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; }
  .ak-btn-newpost:hover { filter: brightness(1.1); }
  .ak-comm-tabs { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 18px; }
  .ak-comm-tab { flex-shrink: 0; padding: 7px 14px; border-radius: 20px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); color: var(--text-secondary); font-size: 12px; font-weight: 600; text-decoration: none; }
  .ak-comm-tab:hover { color: #fff; background: rgba(255,255,255,.06); }
  .ak-comm-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }

  .ak-comm-form { display: none; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.07); border-radius: 12px; padding: 16px; margin-bottom: 18px; }
  .ak-comm-form.open { display: block; }
  .ak-comm-form select, .ak-comm-form input[type=text], .ak-comm-form textarea {
    width: 100%; background: var(--bg-card); border: 1px solid rgba(255,255,255,.1); border-radius: 8px;
    color: var(--text-main); padding: 9px 12px; font-size: 13px; margin-bottom: 10px; font-family: inherit;
  }
  .ak-comm-form textarea { min-height: 100px; resize: vertical; }
  .ak-comm-form-actions { display: flex; justify-content: flex-end; gap: 8px; }
  .ak-comm-form-actions button { padding: 8px 16px; border-radius: 7px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; }
  .ak-comm-cancel { background: rgba(255,255,255,.08); color: var(--text-secondary); }
  .ak-comm-submit { background: var(--accent); color: #fff; }
  .ak-comm-error { color: #f87171; font-size: 12px; margin-bottom: 8px; display: none; }

  .ak-comm-list { display: flex; flex-direction: column; gap: 8px; }
  .ak-comm-post { display: flex; gap: 12px; padding: 14px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,.06); border-radius: 10px; text-decoration: none; color: inherit; transition: background .15s; }
  .ak-comm-post:hover { background: rgba(255,255,255,.04); }
  .ak-comm-post-avatar { flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; }
  .ak-comm-post-body { flex: 1; min-width: 0; }
  .ak-comm-post-top { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
  .ak-comm-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 7px; border-radius: 4px; background: rgba(200,16,46,.15); color: var(--accent); }
  .ak-comm-pinned { color: #fbbf24; font-size: 11px; }
  .ak-comm-post-title { font-size: 15px; font-weight: 600; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ak-comm-post-meta { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
  .ak-comm-post-stats { flex-shrink: 0; text-align: center; font-size: 12px; color: var(--text-muted); align-self: center; }
  .ak-comm-post-stats b { display: block; color: var(--text-main); font-size: 15px; }
  .ak-comm-empty { text-align: center; padding: 60px 20px; color: var(--text-muted); }
  .ak-comm-empty i { font-size: 34px; margin-bottom: 12px; display: block; color: var(--accent); opacity: .7; }
</style>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <main class="ak-comm-wrap">

    <nav class="ak-breadcrumb">
      <a href="<?=$websiteUrl?>"><i class="fas fa-home"></i></a>
      <span class="sep">/</span>
      <span class="current">Community</span>
    </nav>

    <div class="ak-comm-head">
      <div class="ak-comm-title"><i class="fas fa-comments"></i> Community</div>
      <?php if ($viewer): ?>
      <button type="button" class="ak-btn-newpost" id="akCommNewBtn"><i class="fas fa-plus"></i> New Post</button>
      <?php else: ?>
      <a href="<?=$websiteUrl?>/login.php" class="ak-btn-newpost"><i class="fas fa-plus"></i> New Post</a>
      <?php endif; ?>
    </div>

    <div class="ak-comm-tabs">
      <a class="ak-comm-tab <?=$category===''?'active':''?>" href="<?=$websiteUrl?>/community">All</a>
      <?php foreach (APP_COMMUNITY_CATEGORIES as $cat): ?>
      <a class="ak-comm-tab <?=$category===$cat?'active':''?>" href="<?=$websiteUrl?>/community?category=<?=$cat?>">#<?=app_e(app_community_category_label($cat))?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($viewer): ?>
    <form class="ak-comm-form" id="akCommForm">
      <div class="ak-comm-error" id="akCommError"></div>
      <select name="category" id="akCommCategory">
        <?php foreach (APP_COMMUNITY_CATEGORIES as $cat): ?>
        <option value="<?=$cat?>" <?=$category===$cat?'selected':''?>>#<?=app_e(app_community_category_label($cat))?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="title" id="akCommTitle" placeholder="Title" maxlength="200">
      <textarea name="body" id="akCommBody" placeholder="What's on your mind?" maxlength="5000"></textarea>
      <div class="ak-comm-form-actions">
        <button type="button" class="ak-comm-cancel" id="akCommCancel">Cancel</button>
        <button type="submit" class="ak-comm-submit">Post</button>
      </div>
    </form>
    <?php endif; ?>

    <?php if (!empty($posts)): ?>
    <div class="ak-comm-list">
      <?php foreach ($posts as $post):
        $pid = (int)$post['id'];
        $uname = (string)($post['username'] ?? 'User');
        $initial = strtoupper(substr($uname, 0, 1));
      ?>
      <a class="ak-comm-post" href="<?=$websiteUrl?>/community/post/<?=$pid?>">
        <div class="ak-comm-post-avatar"><?=app_e($initial)?></div>
        <div class="ak-comm-post-body">
          <div class="ak-comm-post-top">
            <?php if ((int)$post['is_pinned'] === 1): ?><i class="fas fa-thumbtack ak-comm-pinned" title="Pinned"></i><?php endif; ?>
            <span class="ak-comm-tag">#<?=app_e(app_community_category_label($post['category']))?></span>
          </div>
          <div class="ak-comm-post-title"><?=app_e($post['title'])?></div>
          <div class="ak-comm-post-meta">by <?=app_e($uname)?> · <?=app_e(app_time_ago($post['created_at']))?></div>
        </div>
        <div class="ak-comm-post-stats"><b><?=(int)$post['reply_count']?></b>replies</div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($lastPage > 1): ?>
    <ul style="display:flex;gap:6px;justify-content:center;margin-top:20px;list-style:none;padding:0">
      <?=legacy_pagination_html($page, $lastPage, $category !== '' ? ['category' => $category] : [], '/community')?>
    </ul>
    <?php endif; ?>

    <?php else: ?>
    <div class="ak-comm-empty">
      <i class="fas fa-comment-slash"></i>
      <h3>No posts yet</h3>
      <p>Be the first to start a discussion.</p>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
(function(){
  var csrf = '<?=app_e(app_csrf_token())?>';
  var newBtn = document.getElementById('akCommNewBtn');
  var form = document.getElementById('akCommForm');
  var cancelBtn = document.getElementById('akCommCancel');
  var errorEl = document.getElementById('akCommError');
  if (newBtn && form) {
    newBtn.addEventListener('click', function(){ form.classList.add('open'); document.getElementById('akCommTitle').focus(); });
  }
  if (cancelBtn && form) {
    cancelBtn.addEventListener('click', function(){ form.classList.remove('open'); errorEl.style.display = 'none'; });
  }
  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      errorEl.style.display = 'none';
      var body = new URLSearchParams({
        action: 'create_post', csrf: csrf,
        category: document.getElementById('akCommCategory').value,
        title: document.getElementById('akCommTitle').value,
        body: document.getElementById('akCommBody').value
      });
      fetch('<?=$websiteUrl?>/api/community.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data.ok) {
            window.location.href = '<?=$websiteUrl?>/community/post/' + data.id;
          } else {
            errorEl.textContent = data.error || 'Something went wrong.';
            errorEl.style.display = 'block';
          }
        })
        .catch(function(){ errorEl.textContent = 'Network error — try again.'; errorEl.style.display = 'block'; });
    });
  }
})();
</script>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
