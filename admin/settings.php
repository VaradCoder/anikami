<?php
require_once __DIR__ . '/../_config.php';
$admin = app_require_admin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && app_validate_csrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_cache') {
        app_clear_all_cache();
        app_audit_log('clear_cache', 'system', null);
        $msg = 'Cache cleared.';
    } elseif ($action === 'set_slug') {
        $key = (string)($_POST['anime_key'] ?? '');
        $val = (string)($_POST['slug_value'] ?? '');
        app_slug_override_set($key, $val);
        app_audit_log('slug_override_set', 'anime', $key, ['slug' => $val]);
        $msg = 'Slug override updated.';
    } elseif ($action === 'feature') {
        $aid = (string)($_POST['anime_id'] ?? '');
        app_featured_set($aid, $_POST['title'] ?? '', $_POST['image_url'] ?? '', (int)($_POST['sort_order'] ?? 0));
        app_audit_log('featured_set', 'anime', $aid);
        $msg = 'Featured anime saved.';
    } elseif ($action === 'remove_featured') {
        $aid = (string)($_POST['anime_id'] ?? '');
        app_featured_remove($aid);
        app_audit_log('featured_remove', 'anime', $aid);
        $msg = 'Featured anime removed.';
    }
}

$featured = app_featured_list(20);

$adminPageTitle = 'Settings';
$adminActive = 'settings';
include __DIR__ . '/_layout_top.php';
?>

<?php if ($msg): ?><div class="ak-admin-panel" style="border-color:rgba(34,197,94,.3);color:#86efac;"><?=app_e($msg)?></div><?php endif; ?>

<div class="ak-admin-grid-2">
  <div class="ak-admin-panel" id="system">
    <h3><i class="fas fa-gear"></i> System</h3>
    <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">Clears the file-based API response cache (Jikan/AniList lookups). Use after a provider outage or stale data.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
      <input type="hidden" name="action" value="clear_cache">
      <button class="ak-abtn warn" type="submit"><i class="fas fa-broom"></i> Clear API Cache</button>
    </form>

    <h3 style="margin-top:24px;"><i class="fas fa-link"></i> Slug Override</h3>
    <form method="post" style="display:flex;flex-direction:column;gap:8px;">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
      <input type="hidden" name="action" value="set_slug">
      <input class="ak-admin-input" name="anime_key" placeholder="Anime title or key" required>
      <input class="ak-admin-input" name="slug_value" placeholder="Override slug" required>
      <button class="ak-abtn primary" type="submit" style="width:fit-content;">Save Override</button>
    </form>
  </div>

  <div class="ak-admin-panel">
    <h3><i class="fas fa-star"></i> Feature Anime on Homepage</h3>
    <form method="post" style="display:flex;flex-direction:column;gap:8px;">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
      <input type="hidden" name="action" value="feature">
      <input class="ak-admin-input" name="anime_id" placeholder="Anime ID (slug)" required>
      <input class="ak-admin-input" name="title" placeholder="Title">
      <input class="ak-admin-input" name="image_url" placeholder="Image URL">
      <input class="ak-admin-input" name="sort_order" type="number" placeholder="Sort order" value="0">
      <button class="ak-abtn primary" type="submit" style="width:fit-content;">Save Featured</button>
    </form>
  </div>
</div>

<div class="ak-admin-panel">
  <h3><i class="fas fa-list"></i> Current Featured Anime</h3>
  <?php if ($featured): ?>
  <table class="ak-admin-table">
    <tr><th>Anime ID</th><th>Title</th><th>Sort</th><th></th></tr>
    <?php foreach ($featured as $f): ?>
    <tr>
      <td><?=app_e($f['anime_id'])?></td>
      <td><?=app_e($f['title'])?></td>
      <td><?=(int)$f['sort_order']?></td>
      <td>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
          <input type="hidden" name="action" value="remove_featured">
          <input type="hidden" name="anime_id" value="<?=app_e($f['anime_id'])?>">
          <button class="ak-abtn danger sm" type="submit"><i class="fas fa-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="ak-admin-empty">No featured anime set.</div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
