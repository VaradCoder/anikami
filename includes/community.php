<?php
require_once __DIR__ . '/db.php';

const APP_COMMUNITY_CATEGORIES = ['general', 'updates', 'discussion', 'requests', 'bugs'];

// Comments (_php/ak_comments.php) format relative time client-side in JS;
// the community board/thread pages render server-side, so this is the
// PHP-side equivalent — same "Xm/Xh/Xd ago" behavior.
function app_time_ago(string $isoDate): string
{
    $diff = time() - strtotime($isoDate);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return gmdate('M j, Y', strtotime($isoDate));
}

function app_community_category_label(string $category): string
{
    $labels = [
        'general'    => 'General',
        'updates'    => 'Updates',
        'discussion' => 'Discussion',
        'requests'   => 'Requests',
        'bugs'       => 'Bugs',
    ];
    return $labels[$category] ?? ucfirst($category);
}

function app_community_post_create(int $userId, string $category, string $title, string $body): int|false
{
    $category = in_array($category, APP_COMMUNITY_CATEGORIES, true) ? $category : 'general';
    $title = trim($title);
    $body = trim($body);
    if ($title === '' || mb_strlen($title) > 200) return false;
    if ($body === '' || mb_strlen($body) > 5000) return false;

    $stmt = app_db()->prepare(
        'INSERT INTO community_posts(user_id, category, title, body, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $category, $title, $body, gmdate('c')]);
    return (int)app_db()->lastInsertId();
}

function app_community_post_get(int $id): ?array
{
    $stmt = app_db()->prepare(
        'SELECT p.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM community_replies WHERE post_id = p.id AND is_hidden = 0) AS reply_count,
                (SELECT COUNT(*) FROM community_post_likes WHERE post_id = p.id) AS like_count
         FROM community_posts p JOIN users u ON u.id = p.user_id
         WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Pinned first, then newest first. $category === null lists every category.
function app_community_post_list(?string $category = null, int $limit = 20, int $offset = 0, bool $includeHidden = false): array
{
    $where = [];
    $params = [];
    if (!$includeHidden) $where[] = 'p.is_hidden = 0';
    if ($category !== null) { $where[] = 'p.category = ?'; $params[] = $category; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = app_db()->prepare(
        "SELECT p.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM community_replies WHERE post_id = p.id AND is_hidden = 0) AS reply_count,
                (SELECT COUNT(*) FROM community_post_likes WHERE post_id = p.id) AS like_count,
                (SELECT MAX(created_at) FROM community_replies WHERE post_id = p.id AND is_hidden = 0) AS last_reply_at
         FROM community_posts p JOIN users u ON u.id = p.user_id
         $whereSql
         ORDER BY p.is_pinned DESC, p.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $i = 1;
    foreach ($params as $p) { $stmt->bindValue($i++, $p); }
    $stmt->bindValue($i++, max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue($i++, max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_community_post_count(?string $category = null): int
{
    if ($category === null) {
        return (int)app_db()->query('SELECT COUNT(*) FROM community_posts WHERE is_hidden = 0')->fetchColumn();
    }
    $stmt = app_db()->prepare('SELECT COUNT(*) FROM community_posts WHERE is_hidden = 0 AND category = ?');
    $stmt->execute([$category]);
    return (int)$stmt->fetchColumn();
}

function app_community_post_update(int $id, int $userId, string $title, string $body): bool
{
    $title = trim($title);
    $body = trim($body);
    if ($title === '' || mb_strlen($title) > 200) return false;
    if ($body === '' || mb_strlen($body) > 5000) return false;
    $stmt = app_db()->prepare('UPDATE community_posts SET title = ?, body = ?, edited_at = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $body, gmdate('c'), $id, $userId]);
    return $stmt->rowCount() > 0;
}

function app_community_post_delete(int $id, int $userId, bool $asAdmin = false): bool
{
    if ($asAdmin) {
        $stmt = app_db()->prepare('DELETE FROM community_posts WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $stmt = app_db()->prepare('DELETE FROM community_posts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
    return $stmt->rowCount() > 0;
}

function app_community_post_set_pinned(int $id, bool $pinned): void
{
    app_db()->prepare('UPDATE community_posts SET is_pinned = ? WHERE id = ?')->execute([$pinned ? 1 : 0, $id]);
}

function app_community_post_set_hidden(int $id, bool $hidden): void
{
    app_db()->prepare('UPDATE community_posts SET is_hidden = ? WHERE id = ?')->execute([$hidden ? 1 : 0, $id]);
}

function app_community_post_liked_by(int $postId, int $userId): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM community_post_likes WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function app_community_post_toggle_like(int $postId, int $userId): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM community_post_likes WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, $userId]);
    if ($stmt->fetchColumn()) {
        app_db()->prepare('DELETE FROM community_post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, $userId]);
        return false;
    }
    app_db()->prepare('INSERT INTO community_post_likes(post_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$postId, $userId, gmdate('c')]);
    return true;
}

// ── Replies (nested one level deep, same convention as comments.php) ──

function app_community_reply_create(int $postId, int $userId, ?int $parentId, string $body): int|false
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 2000) return false;
    if ($parentId !== null) {
        $parent = app_community_reply_get($parentId);
        if (!$parent || (int)$parent['post_id'] !== $postId) return false;
    }
    $stmt = app_db()->prepare(
        'INSERT INTO community_replies(post_id, user_id, parent_id, body, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$postId, $userId, $parentId, $body, gmdate('c')]);
    return (int)app_db()->lastInsertId();
}

function app_community_reply_get(int $id): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM community_replies WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function app_community_reply_list(int $postId, ?int $viewerId = null, bool $includeHidden = false): array
{
    $hiddenSql = $includeHidden ? '' : 'AND is_hidden = 0';
    $stmt = app_db()->prepare(
        "SELECT r.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM community_reply_likes WHERE reply_id = r.id) AS like_count
         FROM community_replies r JOIN users u ON u.id = r.user_id
         WHERE r.post_id = ? $hiddenSql
         ORDER BY r.created_at ASC"
    );
    $stmt->execute([$postId]);
    $rows = $stmt->fetchAll();

    $likedIds = [];
    if ($viewerId && $rows) {
        $ids = array_column($rows, 'id');
        $in = implode(',', array_map('intval', $ids));
        $likedIds = array_column(
            app_db()->query("SELECT reply_id FROM community_reply_likes WHERE user_id = $viewerId AND reply_id IN ($in)")->fetchAll(),
            'reply_id'
        );
    }

    $byId = [];
    $top = [];
    foreach ($rows as $r) {
        $r['like_count'] = (int)$r['like_count'];
        $r['liked_by_viewer'] = in_array((int)$r['id'], array_map('intval', $likedIds), true);
        $r['replies'] = [];
        $byId[$r['id']] = $r;
    }
    foreach ($byId as $id => $r) {
        if ($r['parent_id'] && isset($byId[$r['parent_id']])) {
            $byId[$r['parent_id']]['replies'][] = &$byId[$id];
        } else {
            $top[] = &$byId[$id];
        }
    }
    return $top;
}

function app_community_reply_update(int $id, int $userId, string $body): bool
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 2000) return false;
    $stmt = app_db()->prepare('UPDATE community_replies SET body = ?, edited_at = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$body, gmdate('c'), $id, $userId]);
    return $stmt->rowCount() > 0;
}

function app_community_reply_delete(int $id, int $userId, bool $asAdmin = false): bool
{
    if ($asAdmin) {
        $stmt = app_db()->prepare('DELETE FROM community_replies WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $stmt = app_db()->prepare('DELETE FROM community_replies WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
    return $stmt->rowCount() > 0;
}

function app_community_reply_set_hidden(int $id, bool $hidden): void
{
    app_db()->prepare('UPDATE community_replies SET is_hidden = ? WHERE id = ?')->execute([$hidden ? 1 : 0, $id]);
}

function app_community_reply_toggle_like(int $replyId, int $userId): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM community_reply_likes WHERE reply_id = ? AND user_id = ?');
    $stmt->execute([$replyId, $userId]);
    if ($stmt->fetchColumn()) {
        app_db()->prepare('DELETE FROM community_reply_likes WHERE reply_id = ? AND user_id = ?')->execute([$replyId, $userId]);
        return false;
    }
    app_db()->prepare('INSERT INTO community_reply_likes(reply_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$replyId, $userId, gmdate('c')]);
    return true;
}
