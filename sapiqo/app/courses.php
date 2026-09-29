<?php
// Course, enrollment, progress, and completion logic.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/badge.php';

function all_courses(bool $activeOnly = true): array {
    $sql = 'SELECT * FROM courses' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY title';
    return db_all($sql);
}

function course_by_id(int $id): ?array {
    return db_one('SELECT * FROM courses WHERE id = ?', [$id]);
}

// Active courses with a short description (read from each course.json) for the
// public splash/catalog. Cheap for the handful of courses a district runs.
function public_courses(): array {
    $cfg = lms_config();
    $dir = rtrim($cfg['courses_dir'], '/');
    $out = [];
    foreach (all_courses(true) as $c) {
        // The public splash shows published courses only (drafts are hidden from
        // everyone except admins/course developers, who use the catalog).
        if (($c['status'] ?? 'published') === 'draft') continue;
        $tagline = ''; $lessons = (int) $c['total_units'];
        $jsonPath = "$dir/{$c['slug']}/course.json";
        if (is_file($jsonPath)) {
            $data = json_decode((string) file_get_contents($jsonPath), true) ?: [];
            $tagline = $data['tagline'] ?? '';
            $lessons = (int) ($data['stats']['lessons'] ?? $lessons);
            $c['stats'] = $data['stats'] ?? [];
        }
        $c['tagline'] = $tagline;
        $c['lesson_count'] = $lessons;
        $out[] = $c;
    }
    return sort_courses_certs_first($out);
}

// Certifications first, then alphabetical by title.
function sort_courses_certs_first(array $courses): array {
    usort($courses, function ($a, $b) {
        $ca = (int) !empty($a['is_certification']);
        $cb = (int) !empty($b['is_certification']);
        if ($ca !== $cb) return $cb <=> $ca;                 // certs before non-certs
        return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
    });
    return $courses;
}

function course_by_slug(string $slug): ?array {
    return db_one('SELECT * FROM courses WHERE slug = ?', [$slug]);
}

// Resolve a course by numeric id, slug, or exact/loose title (used by CSV import).
function resolve_course(string $ref): ?array {
    $ref = trim($ref);
    if ($ref === '') return null;
    if (ctype_digit($ref)) {
        $c = course_by_id((int) $ref);
        if ($c) return $c;
    }
    $c = course_by_slug($ref);
    if ($c) return $c;
    $c = db_one('SELECT * FROM courses WHERE LOWER(title) = LOWER(?)', [$ref]);
    if ($c) return $c;
    // Loose contains match as a last resort.
    return db_one('SELECT * FROM courses WHERE LOWER(title) LIKE LOWER(?) LIMIT 1', ['%' . $ref . '%']);
}

// Has the learner completed a course (enrollment marked complete, or 100%)?
function has_completed(int $userId, int $courseId): bool {
    $e = db_one('SELECT status FROM enrollments WHERE user_id=? AND course_id=?', [$userId, $courseId]);
    if ($e && $e['status'] === 'completed') return true;
    $c = course_by_id($courseId);
    return $c ? course_percent($userId, $c) >= 100 : false;
}

// If $course has a prerequisite the learner hasn't completed, return the prereq
// course row; otherwise null (satisfied or none set).
function unmet_prerequisite(int $userId, array $course): ?array {
    $pid = (int) ($course['prereq_id'] ?? 0);
    if ($pid <= 0) return null;
    if (has_completed($userId, $pid)) return null;
    return course_by_id($pid);
}

function enroll(int $userId, int $courseId): void {
    $exists = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if ($exists) return;
    $now = now_utc();
    $expires = null;
    $course = course_by_id($courseId);
    $days = (int) ($course['enroll_days'] ?? 0);
    if ($days > 0) $expires = gmdate('Y-m-d H:i:s', time() + $days * 86400);
    db_run('INSERT INTO enrollments (user_id, course_id, status, enrolled_at, expires_at) VALUES (?,?,?,?,?)',
        [$userId, $courseId, 'enrolled', $now, $expires]);
    if ($course && function_exists('notify')) {
        notify($userId, 'You are enrolled in "' . $course['title'] . '".', '/learn/' . $course['slug']);
    }
}

// Set a course's sequential override. null = follow the global default,
// true = always sequential, false = never (free navigation). When on, the
// reader locks each lesson until the previous one (and its quiz) is complete,
// so the badge/certificate is only reachable after finishing in order.
function set_course_sequential(int $courseId, ?bool $on): void {
    db_run('UPDATE courses SET sequential = ? WHERE id = ?',
        [$on === null ? null : ($on ? 1 : 0), $courseId]);
}

// Is a course sequential in effect? Uses the course's own override when set,
// otherwise the global default (sequential_default — ON unless changed).
function sequential_default_on(): bool {
    return !function_exists('setting') || setting('sequential_default', '1') === '1';
}
function course_is_sequential(array $course): bool {
    $v = $course['sequential'] ?? null;
    if ($v === null || $v === '') return sequential_default_on();
    return (int) $v === 1;
}

// Set a course's enrollment lifetime (days; 0 disables). Recomputes expires_at
// for existing active enrollments from their enrolled_at.
function set_course_enroll_days(int $courseId, int $days): void {
    db_run('UPDATE courses SET enroll_days = ? WHERE id = ?', [$days, $courseId]);
    if ($days > 0) {
        foreach (db_all("SELECT id, enrolled_at FROM enrollments WHERE course_id = ? AND status <> 'completed'", [$courseId]) as $e) {
            $base = strtotime(($e['enrolled_at'] ?: now_utc()) . ' UTC') ?: time();
            db_run('UPDATE enrollments SET expires_at = ? WHERE id = ?',
                [gmdate('Y-m-d H:i:s', $base + $days * 86400), $e['id']]);
        }
    } else {
        db_run('UPDATE enrollments SET expires_at = NULL WHERE course_id = ?', [$courseId]);
    }
}

// Reminder milestones (days before expiry), most-distant first. Configurable via
// the `expiry_reminder_days` setting (default "30,7,1" → a month out, the week of,
// and the day before). Each milestone is tracked by its own bit in
// enrollments.expiry_warned so a learner gets each reminder at most once.
function expiry_reminder_thresholds(?string $csv = null): array {
    $csv = $csv ?? (function_exists('setting') ? setting('expiry_reminder_days', '30,7,1') : '30,7,1');
    $days = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $csv)), fn($d) => $d > 0)));
    rsort($days);   // e.g. [30, 7, 1] — larger threshold = lower bit index
    return $days;
}

// Sweep expired enrollments: soft-unenroll (keep badges/progress/transcript) any
// that are past expires_at and not completed. Before that, send graduated
// pre-expiry reminders (email + in-app) at each configured milestone. Anything a
// learner has already earned — badge, certificate, CPE hours — stays on their
// permanent transcript regardless. Returns counts of ended + reminded enrollments.
function expire_enrollments(?string $reminderDaysCsv = null): array {
    $now = now_utc();
    $removed = 0; $warned = 0;

    $thresholds = expiry_reminder_thresholds($reminderDaysCsv);
    if ($thresholds) {
        $maxT = $thresholds[0];
        $window = gmdate('Y-m-d H:i:s', time() + $maxT * 86400);
        $rows = db_all(
            "SELECT e.*, u.email, u.first_name, c.title, c.slug FROM enrollments e
             JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
             WHERE e.expires_at IS NOT NULL AND e.expires_at > ? AND e.expires_at <= ?
               AND e.status <> 'completed'",
            [$now, $window]
        );
        $mailOn = function_exists('mail_enabled') && mail_enabled();
        foreach ($rows as $e) {
            $mask  = (int) ($e['expiry_warned'] ?? 0);
            $expTs = strtotime($e['expires_at'] . ' UTC') ?: time();
            $daysLeft = (int) ceil(($expTs - time()) / 86400);

            // Which milestones has the enrollment now crossed into?
            $crossed = [];
            foreach ($thresholds as $i => $T) { if ($daysLeft <= $T) $crossed[] = $i; }
            if (!$crossed) continue;
            $urgent = max($crossed);   // highest index = smallest (most urgent) threshold

            $newMask = $mask;
            foreach ($crossed as $i) $newMask |= (1 << $i);

            // Only actually notify for the most-urgent newly-crossed milestone, so a
            // late cron run that jumps two milestones sends one (current) reminder.
            if (!($mask & (1 << $urgent))) {
                $when  = date('M j, Y', $expTs);
                $human = $daysLeft <= 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : "in $daysLeft days");
                if ($mailOn && !empty($e['email'])) {
                    send_mail($e['email'], 'Your access to ' . $e['title'] . ' ends ' . $human,
                        "Hi " . (trim($e['first_name']) ?: 'there') . ",\n\nYour access to \""
                        . $e['title'] . "\" ends on $when (UTC) — $human. Finish the course before then "
                        . "to earn your badge and CPE hours.\n\n"
                        . "Anything you've already earned (badge, certificate, CPE hours) stays on your "
                        . "transcript permanently, even after access ends.\n");
                }
                if (function_exists('notify')) {
                    notify((int) $e['user_id'],
                        'Your access to "' . $e['title'] . '" ends ' . $human . ' (' . $when . ').',
                        '/learn/' . ($e['slug'] ?? ''));
                }
                $warned++;
            }
            if ($newMask !== $mask) {
                db_run('UPDATE enrollments SET expiry_warned = ? WHERE id = ?', [$newMask, $e['id']]);
            }
        }
    }

    // Now expire the overdue ones (soft: keeps progress + badge + transcript).
    $due = db_all(
        "SELECT * FROM enrollments WHERE expires_at IS NOT NULL AND expires_at < ? AND status <> 'completed'",
        [$now]
    );
    foreach ($due as $e) {
        unenroll((int) $e['user_id'], (int) $e['course_id']);  // soft: keeps progress + badge
        if (function_exists('notify')) {
            $c = course_by_id((int) $e['course_id']);
            notify((int) $e['user_id'],
                'Your access to "' . ($c['title'] ?? 'a course') . '" has ended. Your badge, certificate, '
                . 'and CPE hours remain on your transcript.', '/transcript');
        }
        $removed++;
    }
    return ['removed' => $removed, 'warned' => $warned];
}

function is_enrolled(int $userId, int $courseId): bool {
    return (bool) db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
}

// Central access decision used by the reader, downloads, and progress APIs.
function course_access_error(int $userId, array $course, bool $authorPreview = false): ?string {
    if ($authorPreview && function_exists('can_edit_content') && can_edit_content()) return null;
    if (!(int) $course['active'] || ($course['status'] ?? 'published') === 'draft') return 'Course is unavailable.';
    if (unmet_prerequisite($userId, $course)) return 'Complete the prerequisite course first.';
    $enrollment = db_one('SELECT * FROM enrollments WHERE user_id=? AND course_id=?', [$userId, $course['id']]);
    if ($enrollment && $enrollment['status'] !== 'completed' && !empty($enrollment['expires_at'])
        && strtotime($enrollment['expires_at'] . ' UTC') <= time()) return 'Enrollment has expired.';
    if (!$enrollment && !empty(lms_config()['catalog_purchase'])) return 'Enrollment is required for this course.';
    return null;
}

function course_step_kind(string $slug, string $stepId): ?string {
    $data = course_load_json($slug);
    if (($data['type'] ?? '') === 'scorm') return $stepId === 'scorm' ? 'scorm' : null;
    foreach (course_structure($slug) as $module) foreach ($module['lessons'] as $lesson) {
        foreach ($lesson['units'] as $unit) if ($unit['id'] === $stepId) return $unit['kind'];
    }
    return null;
}

function course_step_unlocked(int $userId, array $course, string $stepId): bool {
    if (!course_is_sequential($course)) return true;
    foreach (course_structure($course['slug']) as $module) foreach ($module['lessons'] as $lesson) {
        $ids = array_column($lesson['units'], 'id');
        if (in_array($stepId, $ids, true)) return true;
        // Topics are optional in the reader's navigation lock; lessons and quizzes are required.
        foreach ($lesson['units'] as $unit) {
            if ($unit['kind'] === 'topic') continue;
            if (!db_one('SELECT id FROM progress WHERE user_id=? AND course_id=? AND step_id=?',
                [$userId, $course['id'], $unit['id']])) return false;
        }
    }
    return false;
}

// Remove an enrollment. Progress rows and any issued badge are left intact so a
// later re-enrollment restores the learner's place; pass $purge to wipe them.
function unenroll(int $userId, int $courseId, bool $purge = false): void {
    db_run('DELETE FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if ($purge) {
        db_run('DELETE FROM progress WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
        db_run('DELETE FROM badges WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    }
}

function enrollments_for(int $userId): array {
    return db_all(
        'SELECT e.*, c.title, c.slug, c.path, c.total_units, c.active
         FROM enrollments e JOIN courses c ON c.id = e.course_id
         WHERE e.user_id = ? ORDER BY c.title',
        [$userId]
    );
}

function completed_units(int $userId, int $courseId): int {
    $row = db_one('SELECT COUNT(*) AS n FROM progress WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    return (int) ($row['n'] ?? 0);
}

// --- Course structure (server-side, for admin progress management) -----------

function course_load_json(string $slug): array {
    $path = rtrim(lms_config()['courses_dir'], '/') . "/$slug/course.json";
    if (!is_file($path)) return [];
    return json_decode((string) file_get_contents($path), true) ?: [];
}

// Ordered structure of a course as modules → lessons → trackable units, matching
// the reader's step-id scheme (lesson id, "lessonId::topicId", quiz id).
function course_structure(string $slug): array {
    $data = course_load_json($slug);
    if (($data['type'] ?? '') === 'scorm') return [['title' => 'SCORM', 'lessons' => [
        ['id' => 'scorm', 'title' => 'SCORM', 'units' => [['id' => 'scorm', 'label' => 'SCORM', 'kind' => 'scorm']]]
    ]]];
    $mods = [];
    foreach ($data['modules'] ?? [] as $m) {
        $lessons = [];
        foreach ($m['lessons'] ?? [] as $l) {
            $lid = (string) ($l['id'] ?? '');
            if ($lid === '') continue;
            $units = [['id' => $lid, 'label' => $l['title'] ?? $lid, 'kind' => 'lesson']];
            foreach ($l['topics'] ?? [] as $t) {
                $tid = (string) ($t['id'] ?? '');
                if ($tid !== '') $units[] = ['id' => $lid . '::' . $tid, 'label' => $t['title'] ?? $tid, 'kind' => 'topic'];
            }
            if (!empty($l['quiz']['id'])) {
                $units[] = ['id' => (string) $l['quiz']['id'], 'label' => 'Knowledge check', 'kind' => 'quiz'];
            }
            $lessons[] = ['id' => $lid, 'title' => $l['title'] ?? $lid, 'units' => $units];
        }
        $mods[] = ['title' => $m['title'] ?? ($m['label'] ?? ''), 'lessons' => $lessons];
    }
    return $mods;
}

// Flat list of every trackable step id in a course (order preserved).
function course_all_step_ids(string $slug): array {
    $ids = [];
    foreach (course_structure($slug) as $m) {
        foreach ($m['lessons'] as $l) {
            foreach ($l['units'] as $u) $ids[] = $u['id'];
        }
    }
    return $ids;
}

// Admin: set a learner's completed steps for a course to exactly $desired (step
// ids). Auto-enrolls, adds/removes progress rows, then recomputes completion —
// issuing the badge and marking the enrollment complete at 100%, or reopening
// the enrollment if it drops below (an already-earned badge is left intact).
function admin_sync_progress(int $userId, int $courseId, array $desired): array {
    $course = course_by_id($courseId);
    if (!$course) return ['error' => 'Unknown course'];
    enroll($userId, $courseId);
    $desired = array_values(array_intersect(course_all_step_ids($course['slug']), array_map('strval', $desired)));
    $current = array_column(db_all('SELECT step_id FROM progress WHERE user_id=? AND course_id=?', [$userId, $courseId]), 'step_id');
    $add = array_diff($desired, $current);
    $remove = array_diff($current, $desired);
    foreach ($add as $sid) {
        try {
            db_run('INSERT INTO progress (user_id, course_id, step_id, completed_at) VALUES (?,?,?,?)',
                [$userId, $courseId, $sid, now_utc()]);
        } catch (PDOException $e) { /* already recorded */ }
    }
    foreach ($remove as $sid) {
        db_run('DELETE FROM progress WHERE user_id=? AND course_id=? AND step_id=?', [$userId, $courseId, $sid]);
    }
    $percent = course_percent($userId, $course);
    $badge = null;
    if ((int) $course['total_units'] > 0 && $percent >= 100) {
        $badge = issue_badge($userId, $courseId);
        mark_enrollment_complete($userId, $courseId);
    } else {
        db_run("UPDATE enrollments SET status='enrolled', completed_at=NULL
                WHERE user_id=? AND course_id=? AND status='completed'", [$userId, $courseId]);
    }
    return ['percent' => $percent, 'added' => count($add), 'removed' => count($remove), 'badge' => $badge];
}

function course_percent(int $userId, array $course): int {
    $total = (int) $course['total_units'];
    if ($total <= 0) return 0;
    $done = completed_units($userId, (int) $course['id']);
    return (int) min(100, round($done / $total * 100));
}

// Record a step completion (idempotent). Auto-enrolls, then checks for course
// completion and issues a badge when the course is finished. Returns a status
// array describing progress + any newly issued badge.
function record_progress(int $userId, int $courseId, string $stepId, bool $done): array {
    $course = course_by_id($courseId);
    if (!$course) return ['error' => 'Unknown course'];
    $error = course_access_error($userId, $course);
    if ($error !== null) return ['error' => $error];
    if (course_step_kind($course['slug'], $stepId) === null) return ['error' => 'Unknown progress step'];
    if ($done && !course_step_unlocked($userId, $course, $stepId)) return ['error' => 'Complete earlier lessons first.'];

    enroll($userId, $courseId);
    set_last_seen($userId, $courseId, $stepId);

    if ($done) {
        // INSERT OR IGNORE across drivers.
        try {
            db_run('INSERT INTO progress (user_id, course_id, step_id, completed_at) VALUES (?,?,?,?)',
                [$userId, $courseId, $stepId, now_utc()]);
        } catch (PDOException $e) {
            // Duplicate (already recorded) — ignore.
        }
    } else {
        db_run('DELETE FROM progress WHERE user_id = ? AND course_id = ? AND step_id = ?',
            [$userId, $courseId, $stepId]);
    }

    $percent = course_percent($userId, $course);
    $badge = null;
    if ($course['total_units'] > 0 && $percent >= 100) {
        $badge = issue_badge($userId, $courseId);
        mark_enrollment_complete($userId, $courseId);
    }
    return [
        'ok'        => true,
        'percent'   => $percent,
        'completed' => completed_units($userId, $courseId),
        'total'     => (int) $course['total_units'],
        'badge'     => $badge,
    ];
}

// Track the most recently viewed step so the dashboard can offer "resume".
function set_last_seen(int $userId, int $courseId, string $stepId): void {
    db_run('UPDATE enrollments SET last_step_id = ?, last_seen_at = ? WHERE user_id = ? AND course_id = ?',
        [$stepId, now_utc(), $userId, $courseId]);
}

function mark_enrollment_complete(int $userId, int $courseId): void {
    db_run("UPDATE enrollments SET status = 'completed', completed_at = COALESCE(completed_at, ?)
            WHERE user_id = ? AND course_id = ?", [now_utc(), $userId, $courseId]);
}

// Idempotently issue a badge for a completed course.
function issue_badge(int $userId, int $courseId): ?array {
    $existing = db_one('SELECT * FROM badges WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if ($existing) return $existing;

    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    $course = course_by_id($courseId);
    if (!$user || !$course) return null;

    $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
    $issuedAt = now_utc();
    $image = generate_badge_image($user, $course, $code, $issuedAt);

    $id = db_insert(
        'INSERT INTO badges (user_id, course_id, code, title, issued_at, image_path, cpe_hours, gt_hours, course_slug) VALUES (?,?,?,?,?,?,?,?,?)',
        [$userId, $courseId, $code, $course['title'], $issuedAt, $image,
         (float) ($course['cpe_hours'] ?? 0), (float) ($course['gt_hours'] ?? 0), (string) ($course['slug'] ?? '')]
    );
    // In-app congratulations.
    if (function_exists('notify')) {
        notify($userId, 'You earned the badge for "' . $course['title'] . '"! 🎉', '/dashboard');
    }
    // LTI AGS grade passback (if the course was launched from a platform).
    if (function_exists('lti_send_score')) {
        try { lti_send_score($userId, $courseId, 100); } catch (Throwable $e) { error_log('lti passback: ' . $e->getMessage()); }
    }
    // Congratulate the learner by email (no-op unless mail is configured).
    if (function_exists('mail_enabled') && mail_enabled() && !empty($user['email'])) {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'there';
        $link = (function_exists('base_url_absolute') ? base_url_absolute() : '') . '/dashboard';
        send_mail($user['email'], 'Congratulations — you completed ' . $course['title'],
            "Hi $name,\n\nYou've completed \"{$course['title']}\" and earned your badge!\n\n"
            . "View and download your certificate from your dashboard:\n$link\n\nWell done.\n");
    }
    return db_one('SELECT * FROM badges WHERE id = ?', [$id]);
}

function badges_for(int $userId): array {
    return db_all(
        'SELECT b.*, c.title AS course_title FROM badges b
         JOIN courses c ON c.id = b.course_id WHERE b.user_id = ? ORDER BY b.issued_at DESC',
        [$userId]
    );
}
