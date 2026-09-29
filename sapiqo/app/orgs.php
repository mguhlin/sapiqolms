<?php
// Organizations: a top-level entity (e.g. "Aldirk ISD") that owns groups and
// members and can subscribe whole cohorts to courses. Course access flows down:
// an org-wide subscription enrolls every org member; a group subscription
// enrolls that group's members. Adding a member auto-enrolls them in whatever
// their org / groups are subscribed to. Removing a subscription is
// non-destructive: existing enrollments and progress are kept — only future
// auto-enrollment stops. Enrollment itself is idempotent (see enroll()).

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// --- Organization CRUD -------------------------------------------------------

function all_orgs(): array {
    return db_all(
        "SELECT o.*,
            (SELECT COUNT(*) FROM org_members  m WHERE m.org_id = o.id) AS member_count,
            (SELECT COUNT(*) FROM user_groups  g WHERE g.org_id = o.id) AS group_count,
            (SELECT COUNT(*) FROM org_courses  c WHERE c.org_id = o.id) AS course_count
         FROM organizations o ORDER BY o.name");
}

function org_by_id(int $id): ?array {
    return db_one('SELECT * FROM organizations WHERE id = ?', [$id]);
}

function org_by_name(string $name): ?array {
    return db_one('SELECT * FROM organizations WHERE name = ?', [trim($name)]);
}

// Create (or return existing) org by name. Returns org id.
function create_org(string $name, string $desc = ''): int {
    $name = trim($name);
    $ex = org_by_name($name);
    if ($ex) return (int) $ex['id'];
    return db_insert('INSERT INTO organizations (name, description, created_at) VALUES (?,?,?)',
        [$name, trim($desc), now_utc()]);
}

function rename_org(int $id, string $name, string $desc): void {
    db_run('UPDATE organizations SET name = ?, description = ? WHERE id = ?', [trim($name), trim($desc), $id]);
}

// Delete an org non-destructively: detach its groups (keep them) and keep every
// enrollment/badge. Only the org-scoped rows (membership, subscriptions,
// managers) are removed.
function delete_org(int $id): void {
    db_run('UPDATE user_groups SET org_id = NULL WHERE org_id = ?', [$id]);
    db_run('DELETE FROM org_members  WHERE org_id = ?', [$id]);
    db_run('DELETE FROM org_courses  WHERE org_id = ?', [$id]);
    db_run('DELETE FROM org_managers WHERE org_id = ?', [$id]);
    db_run('DELETE FROM organizations WHERE id = ?', [$id]);
}

// --- Groups within an org ----------------------------------------------------

function groups_in_org(int $orgId): array {
    return db_all('SELECT * FROM user_groups WHERE org_id = ? ORDER BY name', [$orgId]);
}

// The org_id a group belongs to (0 = none).
function org_of_group_id(int $groupId): int {
    return (int) (db_one('SELECT org_id FROM user_groups WHERE id = ?', [$groupId])['org_id'] ?? 0);
}

function org_of_group(int $groupId): ?array {
    $oid = org_of_group_id($groupId);
    return $oid ? org_by_id($oid) : null;
}

// Move a group into an org (or out with null). Current members inherit org
// membership + org-wide course access when it joins an org.
function set_group_org(int $groupId, ?int $orgId): void {
    db_run('UPDATE user_groups SET org_id = ? WHERE id = ?', [$orgId ?: null, $groupId]);
    if ($orgId) {
        foreach (db_all('SELECT user_id FROM user_group_members WHERE group_id = ?', [$groupId]) as $r) {
            org_add_member($orgId, (int) $r['user_id']);
        }
    }
}

// --- Org membership ----------------------------------------------------------

function is_org_member(int $orgId, int $userId): bool {
    return (bool) db_one('SELECT id FROM org_members WHERE org_id = ? AND user_id = ?', [$orgId, $userId]);
}

function org_member_ids(int $orgId): array {
    return array_map('intval', array_column(
        db_all('SELECT user_id FROM org_members WHERE org_id = ?', [$orgId]), 'user_id'));
}

// Add a user to an org: record membership, then auto-enroll in its org-wide
// courses. Idempotent.
function org_add_member(int $orgId, int $userId): void {
    if (!is_org_member($orgId, $userId)) {
        db_run('INSERT INTO org_members (org_id, user_id, added_at) VALUES (?,?,?)', [$orgId, $userId, now_utc()]);
    }
    foreach (org_course_ids($orgId) as $cid) enroll($userId, $cid);
}

// Remove from an org: drop membership and membership in the org's groups.
// Keep-access: enrollments and progress are left intact.
function org_remove_member(int $orgId, int $userId): void {
    db_run('DELETE FROM org_members WHERE org_id = ? AND user_id = ?', [$orgId, $userId]);
    foreach (groups_in_org($orgId) as $g) remove_member((int) $g['id'], $userId);
}

// Members of an org with per-user completion metrics (mirrors group_members()).
function org_members(int $orgId): array {
    $rows = db_all(
        'SELECT u.* FROM users u JOIN org_members m ON m.user_id = u.id
         WHERE m.org_id = ? ORDER BY u.last_name, u.first_name', [$orgId]);
    foreach ($rows as &$u) {
        $uid = (int) $u['id'];
        $u['enrollments'] = (int) (db_one('SELECT COUNT(*) n FROM enrollments WHERE user_id = ?', [$uid])['n'] ?? 0);
        $u['completions'] = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE user_id = ? AND status='completed'", [$uid])['n'] ?? 0);
        $u['badges']      = (int) (db_one('SELECT COUNT(*) n FROM badges WHERE user_id = ?', [$uid])['n'] ?? 0);
    }
    return $rows;
}

// Aggregate stats for an org (all members).
function org_stats(int $orgId): array {
    $sub = '(SELECT user_id FROM org_members WHERE org_id = ?)';
    $members = (int) (db_one('SELECT COUNT(*) n FROM org_members WHERE org_id = ?', [$orgId])['n'] ?? 0);
    $enroll  = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE user_id IN $sub", [$orgId])['n'] ?? 0);
    $comp    = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE user_id IN $sub AND status='completed'", [$orgId])['n'] ?? 0);
    $badges  = (int) (db_one("SELECT COUNT(*) n FROM badges WHERE user_id IN $sub", [$orgId])['n'] ?? 0);
    return [
        'members' => $members, 'enrollments' => $enroll, 'completions' => $comp, 'badges' => $badges,
        'rate' => $enroll > 0 ? (int) round($comp / $enroll * 100) : 0,
    ];
}

// --- Course subscriptions ----------------------------------------------------

function org_course_ids(int $orgId): array {
    return array_map('intval', array_column(
        db_all('SELECT course_id FROM org_courses WHERE org_id = ?', [$orgId]), 'course_id'));
}

function org_courses_list(int $orgId): array {
    return db_all(
        'SELECT c.* FROM org_courses oc JOIN courses c ON c.id = oc.course_id
         WHERE oc.org_id = ? ORDER BY c.title', [$orgId]);
}

// Subscribe a whole org to a course: record it, then enroll every current
// member. Idempotent. Ignores non-existent courses (guards against forged ids).
function org_subscribe(int $orgId, int $courseId): void {
    if (!course_by_id($courseId)) return;
    if (!db_one('SELECT id FROM org_courses WHERE org_id = ? AND course_id = ?', [$orgId, $courseId])) {
        db_run('INSERT INTO org_courses (org_id, course_id, added_at) VALUES (?,?,?)', [$orgId, $courseId, now_utc()]);
    }
    foreach (org_member_ids($orgId) as $uid) enroll($uid, $courseId);
}

// Remove an org's subscription. Non-destructive: current enrollments stay.
function org_unsubscribe(int $orgId, int $courseId): void {
    db_run('DELETE FROM org_courses WHERE org_id = ? AND course_id = ?', [$orgId, $courseId]);
}

function group_course_ids(int $groupId): array {
    return array_map('intval', array_column(
        db_all('SELECT course_id FROM group_courses WHERE group_id = ?', [$groupId]), 'course_id'));
}

function group_courses_list(int $groupId): array {
    return db_all(
        'SELECT c.* FROM group_courses gc JOIN courses c ON c.id = gc.course_id
         WHERE gc.group_id = ? ORDER BY c.title', [$groupId]);
}

// Subscribe a group to a course and enroll every current member. Idempotent.
// Ignores non-existent courses (guards against forged ids).
function group_subscribe(int $groupId, int $courseId): void {
    if (!course_by_id($courseId)) return;
    if (!db_one('SELECT id FROM group_courses WHERE group_id = ? AND course_id = ?', [$groupId, $courseId])) {
        db_run('INSERT INTO group_courses (group_id, course_id, added_at) VALUES (?,?,?)', [$groupId, $courseId, now_utc()]);
    }
    foreach (db_all('SELECT user_id FROM user_group_members WHERE group_id = ?', [$groupId]) as $r) {
        enroll((int) $r['user_id'], $courseId);
    }
}

// Remove a group's subscription. Non-destructive: current enrollments stay.
function group_unsubscribe(int $groupId, int $courseId): void {
    db_run('DELETE FROM group_courses WHERE group_id = ? AND course_id = ?', [$groupId, $courseId]);
}

// The distinct courses a user has access to via any org or group subscription,
// each labelled with its source (for display on the member/user pages).
function subscription_sources_for_user(int $userId): array {
    $out = [];
    $rows = db_all(
        "SELECT c.id, c.title, o.name AS via FROM org_courses oc
           JOIN courses c ON c.id = oc.course_id
           JOIN organizations o ON o.id = oc.org_id
           JOIN org_members m ON m.org_id = oc.org_id AND m.user_id = ?
         UNION
         SELECT c.id, c.title, g.name AS via FROM group_courses gc
           JOIN courses c ON c.id = gc.course_id
           JOIN user_groups g ON g.id = gc.group_id
           JOIN user_group_members gm ON gm.group_id = gc.group_id AND gm.user_id = ?
         ORDER BY title", [$userId, $userId]);
    foreach ($rows as $r) $out[] = $r;
    return $out;
}

// --- Auto-enroll hook (called by groups.php add_member) ----------------------

// When a user is added to a group: enroll them in the group's course
// subscriptions, and — if the group belongs to an org — enroll them as an org
// member too (so org-wide subscriptions reach them and they appear under it).
function on_group_member_added(int $groupId, int $userId): void {
    foreach (group_course_ids($groupId) as $cid) enroll($userId, $cid);
    $oid = org_of_group_id($groupId);
    if ($oid) org_add_member($oid, $userId);
}

// --- Org managers (sub-admins scoped to a whole org) -------------------------

function add_org_manager(int $orgId, int $userId, bool $permEnroll = true, bool $permMembers = true, bool $permCourses = false): void {
    $e = $permEnroll ? 1 : 0; $m = $permMembers ? 1 : 0; $c = $permCourses ? 1 : 0;
    if (db_one('SELECT id FROM org_managers WHERE org_id = ? AND user_id = ?', [$orgId, $userId])) {
        db_run('UPDATE org_managers SET perm_enroll = ?, perm_members = ?, perm_courses = ? WHERE org_id = ? AND user_id = ?',
            [$e, $m, $c, $orgId, $userId]);
        return;
    }
    db_run('INSERT INTO org_managers (org_id, user_id, perm_enroll, perm_members, perm_courses, added_at) VALUES (?,?,?,?,?,?)',
        [$orgId, $userId, $e, $m, $c, now_utc()]);
}

function remove_org_manager(int $orgId, int $userId): void {
    db_run('DELETE FROM org_managers WHERE org_id = ? AND user_id = ?', [$orgId, $userId]);
}

function org_managers_of(int $orgId): array {
    return db_all(
        'SELECT u.*, gm.perm_enroll, gm.perm_members, gm.perm_courses
         FROM users u JOIN org_managers gm ON gm.user_id = u.id
         WHERE gm.org_id = ? ORDER BY u.last_name, u.first_name', [$orgId]);
}

function managed_org_ids(int $userId): array {
    return array_map('intval', array_column(
        db_all('SELECT org_id FROM org_managers WHERE user_id = ?', [$userId]), 'org_id'));
}

function managed_orgs(int $userId): array {
    $out = [];
    foreach (managed_org_ids($userId) as $oid) {
        $o = org_by_id($oid);
        if ($o) $out[] = $o + org_stats($oid);
    }
    usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $out;
}

function is_org_manager(int $userId, int $orgId): bool {
    return (bool) db_one('SELECT id FROM org_managers WHERE user_id = ? AND org_id = ?', [$userId, $orgId]);
}

function manages_any_orgs(): bool {
    $u = current_user();
    return $u !== null && !empty(managed_org_ids((int) $u['id']));
}

// Does an org manager hold a permission? 'enroll'/'members' default ON for
// legacy NULL rows; 'courses' defaults OFF (must be granted explicitly).
function org_manager_perm(int $userId, int $orgId, string $perm): bool {
    $col = ['enroll' => 'perm_enroll', 'members' => 'perm_members', 'courses' => 'perm_courses'][$perm] ?? 'perm_members';
    $row = db_one("SELECT $col AS p FROM org_managers WHERE user_id = ? AND org_id = ?", [$userId, $orgId]);
    if (!$row) return false;
    if ($perm === 'courses') return (int) ($row['p'] ?? 0) === 1;
    return $row['p'] === null || (int) $row['p'] === 1;
}
