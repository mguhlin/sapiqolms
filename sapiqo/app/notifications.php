<?php
// In-app notifications. Complements email — always available, no SMTP needed.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Create a notification for a user. Never throws (best-effort).
function notify(int $userId, string $message, string $url = ''): void {
    if ($userId <= 0 || $message === '') return;
    try {
        db_run('INSERT INTO notifications (user_id, message, url, created_at) VALUES (?,?,?,?)',
            [$userId, $message, $url, now_utc()]);
    } catch (Throwable $e) {
        error_log('notify: ' . $e->getMessage());
    }
}

function unread_count(int $userId): int {
    try {
        return (int) (db_one('SELECT COUNT(*) n FROM notifications WHERE user_id=? AND read_at IS NULL', [$userId])['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function notifications_for(int $userId, int $limit = 50): array {
    try {
        return db_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)), [$userId]);
    } catch (Throwable $e) {
        return [];
    }
}

function mark_all_read(int $userId): void {
    db_run('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL', [now_utc(), $userId]);
}
