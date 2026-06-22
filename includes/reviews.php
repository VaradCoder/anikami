<?php
require_once __DIR__ . '/db.php';

// One review per user per anime — create acts as upsert (matches the UI:
// "edit your review" rather than allowing duplicates).
function app_review_upsert(int $userId, string $animeId, int $rating, string $body, bool $hasSpoilers): int|false
{
    $rating = max(1, min(10, $rating));
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 4000) {
        return false;
    }
    $existing = app_review_get_by_user($userId, $animeId);
    $now = gmdate('c');
    if ($existing) {
        $stmt = app_db()->prepare('UPDATE reviews SET rating = ?, body = ?, has_spoilers = ?, edited_at = ? WHERE id = ?');
        $stmt->execute([$rating, $body, $hasSpoilers ? 1 : 0, $now, $existing['id']]);
        return (int)$existing['id'];
    }
    $stmt = app_db()->prepare(
        'INSERT INTO reviews(anime_id, user_id, rating, body, has_spoilers, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$animeId, $userId, $rating, $body, $hasSpoilers ? 1 : 0, $now]);
    return (int)app_db()->lastInsertId();
}

function app_review_get_by_user(int $userId, string $animeId): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM reviews WHERE anime_id = ? AND user_id = ?');
    $stmt->execute([$animeId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function app_review_get(int $id): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM reviews WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function app_review_list(string $animeId, ?int $viewerId = null, bool $includeHidden = false, string $sort = 'helpful'): array
{
    $hiddenSql = $includeHidden ? '' : 'AND r.is_hidden = 0';
    $orderSql = $sort === 'recent' ? 'r.created_at DESC' : 'r.is_featured DESC, helpful_count DESC, r.created_at DESC';
    $stmt = app_db()->prepare(
        "SELECT r.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM review_helpful_votes WHERE review_id = r.id) AS helpful_count
         FROM reviews r JOIN users u ON u.id = r.user_id
         WHERE r.anime_id = ? $hiddenSql
         ORDER BY $orderSql"
    );
    $stmt->execute([$animeId]);
    $rows = $stmt->fetchAll();

    $votedIds = [];
    if ($viewerId && $rows) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
        $votedIds = array_column(
            app_db()->query("SELECT review_id FROM review_helpful_votes WHERE user_id = $viewerId AND review_id IN ($ids)")->fetchAll(),
            'review_id'
        );
    }
    foreach ($rows as &$r) {
        $r['helpful_count'] = (int)$r['helpful_count'];
        $r['voted_by_viewer'] = in_array((int)$r['id'], array_map('intval', $votedIds), true);
    }
    return $rows;
}

function app_review_rating_summary(string $animeId): array
{
    $stmt = app_db()->prepare('SELECT COUNT(*) AS c, AVG(rating) AS avg_rating FROM reviews WHERE anime_id = ? AND is_hidden = 0');
    $stmt->execute([$animeId]);
    $row = $stmt->fetch();
    return [
        'count' => (int)($row['c'] ?? 0),
        'average' => $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : null,
    ];
}

function app_review_delete(int $id, int $userId, bool $asAdmin = false): bool
{
    if ($asAdmin) {
        $stmt = app_db()->prepare('DELETE FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $stmt = app_db()->prepare('DELETE FROM reviews WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
    return $stmt->rowCount() > 0;
}

function app_review_set_hidden(int $id, bool $hidden): void
{
    app_db()->prepare('UPDATE reviews SET is_hidden = ? WHERE id = ?')->execute([$hidden ? 1 : 0, $id]);
}

function app_review_set_featured(int $id, bool $featured): void
{
    app_db()->prepare('UPDATE reviews SET is_featured = ? WHERE id = ?')->execute([$featured ? 1 : 0, $id]);
}

function app_review_toggle_helpful(int $reviewId, int $userId): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM review_helpful_votes WHERE review_id = ? AND user_id = ?');
    $stmt->execute([$reviewId, $userId]);
    if ($stmt->fetchColumn()) {
        app_db()->prepare('DELETE FROM review_helpful_votes WHERE review_id = ? AND user_id = ?')->execute([$reviewId, $userId]);
        return false;
    }
    app_db()->prepare('INSERT INTO review_helpful_votes(review_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$reviewId, $userId, gmdate('c')]);
    return true;
}

function app_review_recent_for_moderation(int $limit = 50): array
{
    $stmt = app_db()->prepare(
        'SELECT r.*, u.username,
                (SELECT COUNT(*) FROM reports WHERE target_type = "review" AND target_id = CAST(r.id AS TEXT) AND status = "pending") AS report_count
         FROM reviews r JOIN users u ON u.id = r.user_id
         ORDER BY r.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
