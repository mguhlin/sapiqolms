<?php
// Course auto-discovery. Scans the drop-in courses directory and keeps the
// courses table in sync: new/updated folders are registered, missing folders
// are marked inactive (soft-removed) so learner badges, certificates,
// enrollments, and progress are preserved. Restoring a folder reactivates it.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Resolve a course's badge: <slug>/badge.(png|jpg|svg) first (self-contained
// drop-in), then a name match in the shared badge library, then '' (placeholder).
function discover_badge(string $slug, string $courseDir, string $title): string {
    foreach (['badge.png', 'badge.jpg', 'badge.jpeg', 'badge.svg'] as $f) {
        if (is_file("$courseDir/$f")) return realpath("$courseDir/$f");
    }
    $lib = lms_config()['badge_library'];
    if (is_dir($lib)) {
        $needle = strtolower(preg_replace('/[^a-z0-9]+/i', '', $slug . $title));
        foreach (glob("$lib/*.png") ?: [] as $png) {
            $name = strtolower(preg_replace('/[^a-z0-9]+/i', '', basename($png, '.png')));
            // loose contains match either direction
            if ($name !== '' && (str_contains($needle, $name) || str_contains($name, str_replace('educator','',$needle)))) {
                return realpath($png);
            }
        }
    }
    return '';
}

// Scan the courses directory; upsert present courses, deactivate missing ones.
// Returns a summary array.
function scan_courses(): array {
    $cfg = lms_config();
    $dir = rtrim($cfg['courses_dir'], '/');
    $found = [];
    $summary = ['added' => [], 'updated' => [], 'deactivated' => [], 'reactivated' => []];

    foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $courseDir) {
        $jsonPath = "$courseDir/course.json";
        if (!is_file($jsonPath)) continue;
        $data = json_decode((string) file_get_contents($jsonPath), true);
        if (!$data || empty($data['slug'])) continue;

        $slug  = $data['slug'];
        if (!is_string($slug) || !preg_match('/^[a-z0-9-]+$/', $slug) || basename($courseDir) !== $slug) continue;
        $title = $data['title'] ?? $slug;
        $units = ($data['type'] ?? '') === 'scorm'
            ? max(1, (int) ($data['stats']['units'] ?? 1))
            : (int) (($data['stats']['lessons'] ?? 0) + ($data['stats']['topics'] ?? 0) + ($data['stats']['quizzes'] ?? 0));
        $path  = "/courses/$slug/";
        $badge = discover_badge($slug, $courseDir, $title);
        // A ".disabled" marker lets an admin hide a course from the catalog
        // without deleting its folder (survives rescans). Learner data is kept.
        $active = is_file("$courseDir/.disabled") ? 0 : 1;
        // Publication + certification metadata (from course.json, so it travels
        // with the course and survives a rescan).
        $cpe    = (float) ($data['cpe_hours'] ?? 0);
        $gt     = (float) ($data['gt_hours'] ?? 0);
        $status = (($data['status'] ?? 'published') === 'draft') ? 'draft' : 'published';
        $isCert = !empty($data['certification']) ? 1 : 0;
        $found[] = $slug;

        $existing = db_one('SELECT * FROM courses WHERE slug = ?', [$slug]);
        if ($existing) {
            db_run('UPDATE courses SET title=?, path=?, total_units=?, badge_image=?, active=?, cpe_hours=?, gt_hours=?, status=?, is_certification=? WHERE id=?',
                [$title, $path, $units, $badge, $active, $cpe, $gt, $status, $isCert, $existing['id']]);
            if ((int) $existing['active'] === 0 && $active === 1) $summary['reactivated'][] = $slug;
            elseif ((int) $existing['active'] === 1 && $active === 0) $summary['deactivated'][] = $slug;
            else $summary['updated'][] = $slug;
        } else {
            db_insert('INSERT INTO courses (slug,title,path,total_units,badge_image,active,cpe_hours,gt_hours,status,is_certification,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$slug, $title, $path, $units, $badge, $active, $cpe, $gt, $status, $isCert, now_utc()]);
            $summary['added'][] = $slug;
        }
    }

    // Deactivate courses whose folder is gone (keep the row + all learner data).
    foreach (db_all('SELECT id, slug FROM courses WHERE active = 1') as $c) {
        if (!in_array($c['slug'], $found, true)) {
            db_run('UPDATE courses SET active = 0 WHERE id = ?', [$c['id']]);
            $summary['deactivated'][] = $c['slug'];
        }
    }
    return $summary;
}
