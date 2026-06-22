<?php
require_once __DIR__ . '/db.php';

const APP_REPORT_REASONS = ['spoiler', 'spam', 'abuse', 'broken_stream'];

function app_comment_create(int $userId, string $animeId, ?int $episode, ?int $parentId, string $body, bool $isSpoiler): int|false
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 2000) {
        return false;
    }
    if ($parentId !== null) {
        $parent = app_comment_get($parentId);
        if (!$parent || $parent['anime_id'] !== $animeId) {
            return false;
        }
    }
    $stmt = app_db()->prepare(
        'INSERT INTO comments(anime_id, episode, user_id, parent_id, body, is_spoiler, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$animeId, $episode, $userId, $parentId, $body, $isSpoiler ? 1 : 0, gmdate('c')]);
    return (int)app_db()->lastInsertId();
}

function app_comment_get(int $id): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM comments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Returns a flat list with replies nested under `replies`, newest top-level
// first (pinned first), oldest-first within replies. $viewerId is used only
// to flag which comments the current viewer has liked.
function app_comment_list(string $animeId, ?int $episode, ?int $viewerId = null, bool $includeHidden = false): array
{
    $params = [$animeId];
    $episodeSql = $episode === null ? 'episode IS NULL' : 'episode = ?';
    if ($episode !== null) $params[] = $episode;
    $hiddenSql = $includeHidden ? '' : 'AND is_hidden = 0';

    $stmt = app_db()->prepare(
        "SELECT c.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS like_count
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.anime_id = ? AND $episodeSql $hiddenSql
         ORDER BY c.is_pinned DESC, c.created_at ASC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $likedIds = [];
    if ($viewerId && $rows) {
        $ids = array_column($rows, 'id');
        $in = implode(',', array_map('intval', $ids));
        $likedIds = array_column(
            app_db()->query("SELECT comment_id FROM comment_likes WHERE user_id = $viewerId AND comment_id IN ($in)")->fetchAll(),
            'comment_id'
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
    // Re-sort top-level: pinned first, then newest first (replies stay oldest-first above).
    usort($top, function ($a, $b) {
        if ($a['is_pinned'] !== $b['is_pinned']) return $b['is_pinned'] - $a['is_pinned'];
        return strcmp($b['created_at'], $a['created_at']);
    });
    return $top;
}

function app_comment_count(string $animeId, ?int $episode = null): int
{
    $params = [$animeId];
    $episodeSql = $episode === null ? '' : 'AND episode = ?';
    if ($episode !== null) $params[] = $episode;
    $stmt = app_db()->prepare("SELECT COUNT(*) FROM comments WHERE anime_id = ? $episodeSql AND is_hidden = 0");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function app_comment_update(int $id, int $userId, string $body): bool
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 2000) return false;
    $stmt = app_db()->prepare('UPDATE comments SET body = ?, edited_at = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$body, gmdate('c'), $id, $userId]);
    return $stmt->rowCount() > 0;
}

// $asAdmin bypasses the ownership check (admin moderation delete).
function app_comment_delete(int $id, int $userId, bool $asAdmin = false): bool
{
    if ($asAdmin) {
        $stmt = app_db()->prepare('DELETE FROM comments WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $stmt = app_db()->prepare('DELETE FROM comments WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
    return $stmt->rowCount() > 0;
}

function app_comment_set_pinned(int $id, bool $pinned): void
{
    app_db()->prepare('UPDATE comments SET is_pinned = ? WHERE id = ?')->execute([$pinned ? 1 : 0, $id]);
}

function app_comment_set_hidden(int $id, bool $hidden): void
{
    app_db()->prepare('UPDATE comments SET is_hidden = ? WHERE id = ?')->execute([$hidden ? 1 : 0, $id]);
}

// Returns the new liked state (true = now liked, false = now unliked).
function app_comment_toggle_like(int $commentId, int $userId): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM comment_likes WHERE comment_id = ? AND user_id = ?');
    $stmt->execute([$commentId, $userId]);
    if ($stmt->fetchColumn()) {
        app_db()->prepare('DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?')->execute([$commentId, $userId]);
        return false;
    }
    app_db()->prepare('INSERT INTO comment_likes(comment_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$commentId, $userId, gmdate('c')]);
    return true;
}

function app_comment_recent_for_moderation(int $limit = 50): array
{
    $stmt = app_db()->prepare(
        'SELECT c.*, u.username,
                (SELECT COUNT(*) FROM reports WHERE target_type = "comment" AND target_id = CAST(c.id AS TEXT) AND status = "pending") AS report_count
         FROM comments c JOIN users u ON u.id = c.user_id
         ORDER BY c.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Reports (shared schema: comments, reviews, streams, etc via target_type) ──

function app_report_create(string $targetType, string $targetId, int $reporterId, string $reason, string $details = ''): bool
{
    if (!in_array($reason, APP_REPORT_REASONS, true)) return false;
    $stmt = app_db()->prepare(
        'INSERT INTO reports(target_type, target_id, reporter_id, reason, details, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$targetType, $targetId, $reporterId, $reason, trim($details), gmdate('c')]);
    return true;
}

function app_report_list(string $status = 'pending', int $limit = 100): array
{
    $stmt = app_db()->prepare(
        'SELECT r.*, u.username AS reporter_username
         FROM reports r JOIN users u ON u.id = r.reporter_id
         WHERE r.status = ? ORDER BY r.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $status);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_report_resolve(int $id, int $adminId, string $status): void
{
    $stmt = app_db()->prepare('UPDATE reports SET status = ?, resolved_at = ?, resolved_by = ? WHERE id = ?');
    $stmt->execute([$status, gmdate('c'), $adminId, $id]);
}

function app_report_count_pending(): int
{
    return (int)app_db()->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
}
