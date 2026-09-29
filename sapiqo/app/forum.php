<?php
// Discussion forum: a per-module board inside each course (enrolled participants
// + staff), plus one global announcement board (everyone reads, staff posts).
//
// Post model: a top-level post is a "thread"; a post with parent_id is a reply
// (one level of threading). Course boards are keyed by (course_id, module_key).

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// --- Capabilities ------------------------------------------------------------

// Staff = anyone who can moderate + post announcements: admins, group managers,
// and course developers. Regular learners are not staff.
function forum_is_staff(): bool {
    if (function_exists('is_admin') && is_admin()) return true;
    if (function_exists('manages_any_groups') && manages_any_groups()) return true;
    if (function_exists('can_edit_content') && can_edit_content()) return true;
    return false;
}

// Moderators: admins (any course) + group/org managers, but ONLY for a course
// their group(s)/org(s) are actually subscribed to — a manager of Group A must
// not be able to hide/delete posts in Group B's course. Pass the post's
// course_id (null for the global announcement board, which stays admin-only).
function forum_can_moderate(?int $courseId = null): bool {
    if (function_exists('is_admin') && is_admin()) return true;
    if ($courseId === null) return false;
    $u = function_exists('current_user') ? current_user() : null;
    return $u !== null && function_exists('manager_can_moderate_course')
        && manager_can_moderate_course((int) $u['id'], $courseId);
}

function forum_enabled_for(array $course): bool {
    $v = $course['forum_enabled'] ?? null;   // NULL = default on
    return $v === null || (int) $v === 1;
}

function forum_gated_for(array $course): bool {
    return (int) ($course['forum_gated'] ?? 0) === 1;
}

// May the current user READ a course's forum? Staff see all; otherwise enrolled.
function forum_can_view_course(array $course): bool {
    if (!forum_enabled_for($course)) return false;
    if (forum_is_staff()) return true;
    $u = current_user();
    return $u !== null && course_access_error((int) $u['id'], $course) === null
        && is_enrolled((int) $u['id'], (int) $course['id']);
}

// May the current user POST in a course's forum? Enrolled participants + staff.
function forum_can_post_course(array $course): bool {
    return forum_enabled_for($course) && forum_can_view_course($course);
}

// Only administrators may post announcements; everyone signed in may read them.
function forum_can_post_announcement(): bool {
    return function_exists('is_admin') && is_admin();
}

// --- Reads -------------------------------------------------------------------

function forum_post(int $id): ?array {
    return db_one('SELECT * FROM forum_posts WHERE id = ?', [$id]);
}

// Top-level posts for a scope, newest first (announcements: pinned first).
// $includeExpired: false (default) hides posts whose expires_at has passed —
// used for regular users. Admins pass true to see the full history, including
// expired announcements (see forum_announcements()).
function forum_threads(string $scope, ?int $courseId = null, ?string $moduleKey = null, bool $includeExpired = false): array {
    $sql = "SELECT p.*, u.first_name, u.last_name, u.email,
                   (SELECT COUNT(*) FROM forum_posts r WHERE r.parent_id = p.id AND r.hidden = 0) AS reply_count
            FROM forum_posts p JOIN users u ON u.id = p.user_id
            WHERE p.scope = ? AND p.parent_id IS NULL";
    $params = [$scope];
    if ($courseId !== null) { $sql .= ' AND p.course_id = ?'; $params[] = $courseId; }
    if ($moduleKey !== null) { $sql .= ' AND p.module_key = ?'; $params[] = $moduleKey; }
    if (!$includeExpired) { $sql .= ' AND (p.expires_at IS NULL OR p.expires_at > ?)'; $params[] = now_utc(); }
    $sql .= ' ORDER BY p.pinned DESC, p.created_at DESC';
    return db_all($sql, $params);
}

function forum_replies(int $parentId): array {
    return db_all(
        'SELECT p.*, u.first_name, u.last_name, u.email FROM forum_posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.parent_id = ? ORDER BY p.created_at ASC', [$parentId]);
}

// How many top-level posts a module board has (for listings).
function forum_thread_count(int $courseId, string $moduleKey): int {
    return (int) (db_one(
        "SELECT COUNT(*) n FROM forum_posts WHERE scope='course' AND course_id=? AND module_key=? AND parent_id IS NULL AND hidden=0",
        [$courseId, $moduleKey])['n'] ?? 0);
}

// Has this user posted (a thread or reply) in a given module board? Drives the
// "post before you see" gate.
function forum_user_posted_in_module(int $userId, int $courseId, string $moduleKey): bool {
    return (bool) db_one(
        "SELECT id FROM forum_posts WHERE scope='course' AND course_id=? AND module_key=? AND user_id=? LIMIT 1",
        [$courseId, $moduleKey, $userId]);
}

function forum_announcements(bool $includeExpired = false): array {
    return forum_threads('announcement', null, null, $includeExpired);
}

// --- Writes ------------------------------------------------------------------

// Create a post (thread if $parentId is null, else a reply). Returns the new id.
// $data['expires_at'] (optional, announcements only): a 'Y-m-d H:i:s' UTC
// string, or null for no expiry.
function forum_create(array $data): int {
    return db_insert(
        'INSERT INTO forum_posts (scope, course_id, module_key, parent_id, user_id, title, body, pinned, hidden, expires_at, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,0,?,?,?)',
        [
            $data['scope'],
            $data['course_id'] ?? null,
            $data['module_key'] ?? null,
            $data['parent_id'] ?? null,
            (int) $data['user_id'],
            trim((string) ($data['title'] ?? '')),
            trim((string) ($data['body'] ?? '')),
            !empty($data['pinned']) ? 1 : 0,
            $data['expires_at'] ?? null,
            now_utc(), now_utc(),
        ]);
}

function forum_set_hidden(int $id, bool $hidden): void {
    db_run('UPDATE forum_posts SET hidden = ?, updated_at = ? WHERE id = ?', [$hidden ? 1 : 0, now_utc(), $id]);
}

// Delete a post and, if it was a thread, its replies.
function forum_delete(int $id): void {
    db_run('DELETE FROM forum_posts WHERE id = ? OR parent_id = ?', [$id, $id]);
}

function forum_author_name(array $row): string {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['email'] ?? 'Someone');
}

// --- Threaded boards (forums as named items on modules) ----------------------

// Relative "time ago" for a UTC timestamp.
function forum_ago(?string $ts): string {
    if (!$ts) return '';
    $t = strtotime($ts . ' UTC') ?: strtotime($ts);
    if (!$t) return '';
    $d = max(0, time() - $t);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    if ($d < 2592000) return floor($d / 604800) . 'w ago';
    return date('M j, Y', $t);
}

// Boards defined by a course's structure: each module's forums[] (with backward
// compat for the old one-board-per-module model). Flat list, in module order.
function forum_boards_for_course(string $slug): array {
    $data = function_exists('course_load_json') ? course_load_json($slug) : [];
    $out = [];
    foreach ($data['modules'] ?? [] as $i => $m) {
        $modTitle = $m['title'] ?? ($m['label'] ?? ('Module ' . ($i + 1)));
        $modKey = (string) ($m['id'] ?? $m['key'] ?? ('m' . $i));
        // Only forums explicitly added to the module in the editor — no auto
        // per-module boards.
        if (!empty($m['forums']) && is_array($m['forums'])) {
            foreach ($m['forums'] as $f) {
                $fid = (string) ($f['id'] ?? '');
                if ($fid === '') continue;
                $out[] = ['module' => $modTitle, 'module_key' => $modKey, 'id' => $fid,
                          'name' => trim((string) ($f['name'] ?? '')) ?: 'Discussion',
                          'content' => (string) ($f['content'] ?? '')];
            }
        }
    }
    return $out;
}

function forum_board($slug, string $boardId): ?array {
    foreach (forum_boards_for_course($slug) as $b) if ($b['id'] === $boardId) return $b;
    return null;
}

// Thread + post counts and last-activity time for a board.
function forum_board_stats(int $courseId, string $boardKey): array {
    $threads = (int) (db_one("SELECT COUNT(*) n FROM forum_posts WHERE scope='course' AND course_id=? AND module_key=? AND parent_id IS NULL AND hidden=0", [$courseId, $boardKey])['n'] ?? 0);
    $posts   = (int) (db_one("SELECT COUNT(*) n FROM forum_posts WHERE scope='course' AND course_id=? AND module_key=? AND hidden=0", [$courseId, $boardKey])['n'] ?? 0);
    $last    = db_one("SELECT created_at FROM forum_posts WHERE scope='course' AND course_id=? AND module_key=? AND hidden=0 ORDER BY created_at DESC LIMIT 1", [$courseId, $boardKey]);
    return ['threads' => $threads, 'posts' => $posts, 'last' => $last['created_at'] ?? null];
}

// All posts in a board (flat, chronological) with author + like info for $viewer.
function forum_board_posts(int $courseId, string $boardKey, int $viewerId): array {
    return db_all(
        "SELECT p.*, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM forum_reactions r WHERE r.post_id = p.id) AS likes,
                (SELECT COUNT(*) FROM forum_reactions r WHERE r.post_id = p.id AND r.user_id = ?) AS liked
         FROM forum_posts p JOIN users u ON u.id = p.user_id
         WHERE p.scope='course' AND p.course_id=? AND p.module_key=?
         ORDER BY p.created_at ASC",
        [$viewerId, $courseId, $boardKey]);
}

// Build a nested tree (children by parent_id) from a flat post list.
function forum_build_tree(array $posts): array {
    $children = [];
    foreach ($posts as $p) {
        $pid = ($p['parent_id'] === null || $p['parent_id'] === '') ? 0 : (int) $p['parent_id'];
        $children[$pid][] = $p;
    }
    $attach = function ($parentId) use (&$attach, &$children) {
        $out = [];
        foreach ($children[$parentId] ?? [] as $node) {
            $node['children'] = $attach((int) $node['id']);
            $out[] = $node;
        }
        return $out;
    };
    return $attach(0);
}

// Toggle a like on a post. Returns true if now liked.
function forum_toggle_like(int $postId, int $userId): bool {
    $ex = db_one('SELECT id FROM forum_reactions WHERE post_id=? AND user_id=?', [$postId, $userId]);
    if ($ex) { db_run('DELETE FROM forum_reactions WHERE id=?', [$ex['id']]); return false; }
    db_run('INSERT INTO forum_reactions (post_id, user_id, created_at) VALUES (?,?,?)', [$postId, $userId, now_utc()]);
    return true;
}
function forum_like_count(int $postId): int {
    return (int) (db_one('SELECT COUNT(*) n FROM forum_reactions WHERE post_id=?', [$postId])['n'] ?? 0);
}
