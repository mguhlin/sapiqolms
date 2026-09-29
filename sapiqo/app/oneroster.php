<?php
// OneRoster v1.1 CSV roster import. Accepts a OneRoster ZIP (orgs.csv, users.csv,
// classes.csv, enrollments.csv) or a single users.csv. Maps to Sapiqo users,
// groups (by school/org), and enrollments (class title -> course).

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/courses.php';
require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/course_io.php';   // io_tmp_dir(), _rmtree()

// Parse a CSV file into a list of assoc rows keyed by (lowercased) header.
function _or_csv(string $file): array {
    if (!is_file($file)) return [];
    $fh = fopen($file, 'r');
    if (!$fh) return [];
    $head = fgetcsv($fh);
    if (!$head) { fclose($fh); return []; }
    $head = array_map(fn($h) => strtolower(trim((string) $h)), $head);
    $rows = [];
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) === 1 && ($r[0] === null || $r[0] === '')) continue;
        $row = [];
        foreach ($head as $i => $key) $row[$key] = $r[$i] ?? '';
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function _or_role_map(string $role): array {
    $role = strtolower(trim($role));
    $admin = in_array($role, ['administrator', 'districtadministrator', 'siteadministrator'], true);
    $type = match (true) {
        str_contains($role, 'teacher') => 'Teacher',
        str_contains($role, 'student') => 'Student',
        $admin => 'Administrator',
        default => 'Staff',
    };
    return [$admin ? 'admin' : 'learner', $type];
}

// Import. Returns a report array. $tmpFile is the uploaded path, $origName its name.
function import_oneroster(string $tmpFile, string $origName): array {
    $report = ['orgs' => 0, 'users_created' => 0, 'users_updated' => 0, 'groups' => 0,
               'enrolled' => 0, 'unmatched_classes' => [], 'errors' => []];
    $work = io_tmp_dir('oneroster-');
    try {
        $dir = $work;
        if (preg_match('/\.zip$/i', $origName)) {
            $staged = $work . '/roster.zip';
            copy($tmpFile, $staged);
            if (function_exists('archive_entries_safe') && !archive_entries_safe($staged)) return array_replace($report, ['errors' => ['The archive contains unsafe file paths.']]);
            try { (new PharData($staged))->extractTo($work, null, true); }
            catch (Throwable $e) { return array_replace($report, ['errors' => ['Could not open the ZIP: ' . $e->getMessage()]]); }
            @unlink($staged);
            // Files may be one level deep.
            if (!is_file("$dir/users.csv")) {
                foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $sub) {
                    if (is_file("$sub/users.csv")) { $dir = $sub; break; }
                }
            }
        } else {
            // Single users.csv upload.
            copy($tmpFile, $work . '/users.csv');
        }

        if (!is_file("$dir/users.csv")) { $report['errors'][] = 'No users.csv found.'; return $report; }

        // orgs: sourcedId -> name
        $orgs = [];
        foreach (_or_csv("$dir/orgs.csv") as $o) {
            $sid = $o['sourcedid'] ?? '';
            if ($sid !== '') { $orgs[$sid] = $o['name'] ?? $sid; $report['orgs']++; }
        }
        // groups cache: org name -> group id
        $groupIds = [];
        $groupFor = function (string $name) use (&$groupIds, &$report) {
            $name = trim($name);
            if ($name === '') return 0;
            if (!isset($groupIds[$name])) {
                $groupIds[$name] = create_group($name);   // idempotent by unique name
                $report['groups']++;
            }
            return $groupIds[$name];
        };

        // users
        $userBySourced = [];
        foreach (_or_csv("$dir/users.csv") as $u) {
            $email = strtolower(trim($u['email'] ?? ''));
            if ($email === '' || !valid_email($email)) { continue; }
            [$role, $type] = _or_role_map($u['role'] ?? '');
            $orgName = '';
            $orgIds = array_filter(array_map('trim', explode(',', $u['orgsourcedids'] ?? '')));
            if ($orgIds) $orgName = $orgs[$orgIds[0]] ?? '';

            $existing = db_one('SELECT * FROM users WHERE email = ?', [$email]);
            if ($existing) {
                db_run('UPDATE users SET first_name=?, last_name=?, user_type=?, organization=?, updated_at=? WHERE id=?', [
                    $u['givenname'] ?? $existing['first_name'], $u['familyname'] ?? $existing['last_name'],
                    $type, $orgName ?: $existing['organization'], now_utc(), $existing['id'],
                ]);
                $uid = (int) $existing['id'];
                $report['users_updated']++;
            } else {
                [$ok, $res] = register_local([
                    'first_name' => $u['givenname'] ?? '', 'last_name' => $u['familyname'] ?? '',
                    'email' => $email, 'user_type' => $type, 'organization' => $orgName,
                    'password' => bin2hex(random_bytes(6)) . 'Aa1', 'role' => $role,
                ]);
                if (!$ok) { $report['errors'][] = "$email: $res"; continue; }
                $uid = (int) $res;
                $report['users_created']++;
            }
            if ($orgName !== '') add_member($groupFor($orgName), $uid);
            $sid = $u['sourcedid'] ?? '';
            if ($sid !== '') $userBySourced[$sid] = $uid;
        }

        // classes: sourcedId -> title/course
        $classes = [];
        foreach (_or_csv("$dir/classes.csv") as $c) {
            $sid = $c['sourcedid'] ?? '';
            if ($sid !== '') $classes[$sid] = $c['title'] ?? ($c['classcode'] ?? $sid);
        }
        // enrollments: enroll students into matching courses
        foreach (_or_csv("$dir/enrollments.csv") as $en) {
            $uid = $userBySourced[$en['usersourcedid'] ?? ''] ?? 0;
            $title = $classes[$en['classsourcedid'] ?? ''] ?? '';
            if (!$uid || $title === '') continue;
            $course = resolve_course($title);
            if (!$course) { if (!in_array($title, $report['unmatched_classes'], true)) $report['unmatched_classes'][] = $title; continue; }
            enroll($uid, (int) $course['id']);
            $report['enrolled']++;
        }
        return $report;
    } catch (Throwable $e) {
        $report['errors'][] = $e->getMessage();
        return $report;
    } finally {
        _rmtree($work);
    }
}
