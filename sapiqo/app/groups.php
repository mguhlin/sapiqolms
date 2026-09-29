<?php
// User groups (e.g. by district/campus/cohort): create, assign members, and
// report completion rate + steps completed per group.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function all_groups(): array {
    return db_all(
        'SELECT g.*, (SELECT COUNT(*) FROM user_group_members m WHERE m.group_id = g.id) AS members
         FROM user_groups g ORDER BY g.name'
    );
}

function group_by_id(int $id): ?array {
    return db_one('SELECT * FROM user_groups WHERE id = ?', [$id]);
}

// Create (or return existing) group by name. Returns group id.
function create_group(string $name, string $desc = ''): int {
    $name = trim($name);
    $existing = db_one('SELECT id FROM user_groups WHERE name = ?', [$name]);
    if ($existing) return (int) $existing['id'];
    return db_insert('INSERT INTO user_groups (name, description, created_at) VALUES (?,?,?)',
        [$name, $desc, now_utc()]);
}

function delete_group(int $id): void {
    db_run('DELETE FROM user_groups WHERE id = ?', [$id]);  // members cascade
}

function add_member(int $groupId, int $userId): void {
    if (db_one('SELECT id FROM user_group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId])) return;
    db_run('INSERT INTO user_group_members (group_id, user_id, added_at) VALUES (?,?,?)',
        [$groupId, $userId, now_utc()]);
    // Auto-enroll the new member in the group's (and its org's) course
    // subscriptions. Guarded so groups work even if orgs.php isn't loaded.
    if (function_exists('on_group_member_added')) on_group_member_added($groupId, $userId);
}

function remove_member(int $groupId, int $userId): void {
    db_run('DELETE FROM user_group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
}

function groups_for_user(int $userId): array {
    return db_all(
        'SELECT g.* FROM user_groups g
         JOIN user_group_members m ON m.group_id = g.id
         WHERE m.user_id = ? ORDER BY g.name', [$userId]
    );
}

// Replace a user's group membership with the given set of group ids.
function set_user_groups(int $userId, array $groupIds): void {
    $groupIds = array_map('intval', $groupIds);
    $current = array_column(groups_for_user($userId), 'id');
    foreach (array_diff($groupIds, $current) as $gid) add_member((int) $gid, $userId);
    foreach (array_diff($current, $groupIds) as $gid) remove_member((int) $gid, $userId);
}

// --- Group managers (sub-admins) --------------------------------------------

function add_manager(int $groupId, int $userId, bool $permEnroll = true, bool $permMembers = true): void {
    $e = $permEnroll ? 1 : 0; $m = $permMembers ? 1 : 0;
    if (db_one('SELECT id FROM group_managers WHERE group_id = ? AND user_id = ?', [$groupId, $userId])) {
        db_run('UPDATE group_managers SET perm_enroll = ?, perm_members = ? WHERE group_id = ? AND user_id = ?',
            [$e, $m, $groupId, $userId]);
        return;
    }
    db_run('INSERT INTO group_managers (group_id, user_id, added_at, perm_enroll, perm_members) VALUES (?,?,?,?,?)',
        [$groupId, $userId, now_utc(), $e, $m]);
}

// Update a manager's per-group permissions.
function set_manager_perms(int $groupId, int $userId, bool $permEnroll, bool $permMembers, bool $permCourses = false): void {
    db_run('UPDATE group_managers SET perm_enroll = ?, perm_members = ?, perm_courses = ? WHERE group_id = ? AND user_id = ?',
        [$permEnroll ? 1 : 0, $permMembers ? 1 : 0, $permCourses ? 1 : 0, $groupId, $userId]);
}

// Does a manager hold a given permission for a group? 'enroll'/'members' default
// to enabled for legacy NULL rows (pre-dates per-grant permissions); 'courses'
// (managing subscriptions) defaults OFF and must be granted explicitly. Falls
// back to org-level management when the group belongs to an org the user runs.
function manager_perm(int $userId, int $groupId, string $perm): bool {
    $col = ['enroll' => 'perm_enroll', 'members' => 'perm_members', 'courses' => 'perm_courses'][$perm] ?? 'perm_members';
    $row = db_one("SELECT $col AS p FROM group_managers WHERE user_id = ? AND group_id = ?", [$userId, $groupId]);
    if ($row) {
        if ($perm === 'courses') return (int) ($row['p'] ?? 0) === 1;
        return $row['p'] === null || (int) $row['p'] === 1;
    }
    // Not a direct group manager — maybe they manage the org this group is in.
    if (function_exists('org_of_group_id')) {
        $oid = org_of_group_id($groupId);
        if ($oid && is_org_manager($userId, $oid)) return org_manager_perm($userId, $oid, $perm);
    }
    return false;
}

// True if the manager holds $perm for ANY group the target user belongs to.
function manager_perm_over_user(int $managerId, int $targetId, string $perm): bool {
    foreach (managed_group_ids($managerId) as $gid) {
        if (db_one('SELECT id FROM user_group_members WHERE user_id = ? AND group_id = ?', [$targetId, $gid])
            && manager_perm($managerId, $gid, $perm)) return true;
    }
    return false;
}

// --- Course-developer capability (global content-authoring grant) ------------

function grant_course_dev(int $userId): void {
    db_run('UPDATE users SET can_edit_content = 1, updated_at = ? WHERE id = ?', [now_utc(), $userId]);
}
function revoke_course_dev(int $userId): void {
    db_run('UPDATE users SET can_edit_content = 0, updated_at = ? WHERE id = ?', [now_utc(), $userId]);
}
// Non-admin users who hold the course-developer capability.
function course_developers(): array {
    return db_all("SELECT * FROM users WHERE can_edit_content = 1 AND role <> 'admin' ORDER BY last_name, first_name");
}

function remove_manager(int $groupId, int $userId): void {
    db_run('DELETE FROM group_managers WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
}

function managers_of(int $groupId): array {
    return db_all(
        'SELECT u.* FROM users u JOIN group_managers gm ON gm.user_id = u.id
         WHERE gm.group_id = ? ORDER BY u.last_name, u.first_name', [$groupId]
    );
}

// Group ids a user manages (sub-admin of) — directly, plus every group under an
// organization they manage (org managers implicitly manage all its groups).
function managed_group_ids(int $userId): array {
    $ids = array_map('intval', array_column(
        db_all('SELECT group_id FROM group_managers WHERE user_id = ?', [$userId]), 'group_id'));
    if (function_exists('managed_org_ids')) {
        foreach (managed_org_ids($userId) as $oid) {
            foreach (db_all('SELECT id FROM user_groups WHERE org_id = ?', [$oid]) as $g) {
                $ids[] = (int) $g['id'];
            }
        }
    }
    return array_values(array_unique($ids));
}

function managed_groups(int $userId): array {
    $ids = managed_group_ids($userId);
    if (!$ids) return [];
    $out = [];
    foreach ($ids as $gid) {
        $g = group_by_id($gid);
        if ($g) $out[] = $g + group_stats($gid);
    }
    usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $out;
}

function is_group_manager(int $userId, int $groupId): bool {
    return (bool) db_one('SELECT id FROM group_managers WHERE user_id = ? AND group_id = ?', [$userId, $groupId]);
}

// True if the manager may administer the target user (shares a managed group).
function manager_can_edit_user(int $managerId, int $targetId): bool {
    $target = db_one('SELECT role, can_edit_content FROM users WHERE id=?', [$targetId]);
    // A scoped manager must never reset a privileged account's password.
    if (!$target || $target['role'] === 'admin' || !empty($target['can_edit_content'])
        || managed_group_ids($targetId) !== []
        || (function_exists('managed_org_ids') && managed_org_ids($targetId) !== [])) return false;
    $ids = managed_group_ids($managerId);
    if (!$ids) return false;
    $in = implode(',', array_fill(0, count($ids), '?'));
    return (bool) db_one(
        "SELECT id FROM user_group_members WHERE user_id = ? AND group_id IN ($in)",
        array_merge([$targetId], $ids));
}

// True if the user manages a group or org that is subscribed to this course —
// i.e. has a real, scoped relationship to it. Used to keep forum moderation
// (and similar course-scoped manager actions) from reaching courses a manager
// has no connection to.
function manager_can_moderate_course(int $userId, int $courseId): bool {
    foreach (managed_group_ids($userId) as $gid) {
        if (db_one('SELECT id FROM group_courses WHERE group_id = ? AND course_id = ?', [$gid, $courseId])) return true;
    }
    if (function_exists('managed_org_ids')) {
        foreach (managed_org_ids($userId) as $oid) {
            if (db_one('SELECT id FROM org_courses WHERE org_id = ? AND course_id = ?', [$oid, $courseId])) return true;
        }
    }
    return false;
}

// Members of a group with per-user completion metrics.
function group_members(int $groupId): array {
    $rows = db_all(
        'SELECT u.* FROM users u
         JOIN user_group_members m ON m.user_id = u.id
         WHERE m.group_id = ? ORDER BY u.last_name, u.first_name', [$groupId]
    );
    foreach ($rows as &$u) {
        $uid = (int) $u['id'];
        $u['enrollments'] = (int) (db_one('SELECT COUNT(*) n FROM enrollments WHERE user_id = ?', [$uid])['n'] ?? 0);
        $u['completions'] = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE user_id = ? AND status='completed'", [$uid])['n'] ?? 0);
        $u['steps']       = (int) (db_one('SELECT COUNT(*) n FROM progress WHERE user_id = ?', [$uid])['n'] ?? 0);
        $u['badges']      = (int) (db_one('SELECT COUNT(*) n FROM badges WHERE user_id = ?', [$uid])['n'] ?? 0);
    }
    return $rows;
}

// Aggregate stats for a group (optionally scoped to one course_id).
function group_stats(int $groupId, ?int $courseId = null): array {
    $memberSub = '(SELECT user_id FROM user_group_members WHERE group_id = ?)';
    $params = [$groupId];
    $courseClause = '';
    if ($courseId) { $courseClause = ' AND course_id = ?'; $params[] = $courseId; }

    $members = (int) (db_one('SELECT COUNT(*) n FROM user_group_members WHERE group_id = ?', [$groupId])['n'] ?? 0);
    $enroll  = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE user_id IN $memberSub$courseClause", $params)['n'] ?? 0);
    $comp    = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE user_id IN $memberSub AND status='completed'"
                     . ($courseId ? ' AND course_id = ?' : ''), $params)['n'] ?? 0);
    $steps   = (int) (db_one("SELECT COUNT(*) n FROM progress WHERE user_id IN $memberSub$courseClause", $params)['n'] ?? 0);
    $badges  = (int) (db_one("SELECT COUNT(*) n FROM badges WHERE user_id IN $memberSub$courseClause", $params)['n'] ?? 0);
    return [
        'members' => $members, 'enrollments' => $enroll, 'completions' => $comp,
        'steps' => $steps, 'badges' => $badges,
        'rate' => $enroll > 0 ? (int) round($comp / $enroll * 100) : 0,
    ];
}

// All groups with aggregate stats (for the groups list + reports).
function groups_with_stats(): array {
    $out = [];
    foreach (all_groups() as $g) {
        $out[] = $g + group_stats((int) $g['id']);
    }
    return $out;
}
