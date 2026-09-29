<?php
// One-time setup: create tables, seed courses, and create an administrator.
//
// CLI usage:
//   php bin/setup.php --email admin@example.edu --password 'Secret123' \
//       --first Admin --last User
//
// Re-running is safe: tables use IF NOT EXISTS, the course is upserted, and an
// existing admin email is updated rather than duplicated.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line: php bin/setup.php --email ... --password ...\n");
}

$APP = dirname(__DIR__) . '/app';
require_once $APP . '/config.php';
require_once $APP . '/db.php';
require_once $APP . '/auth.php';
require_once $APP . '/courses.php';
require_once $APP . '/discovery.php';
require_once $APP . '/badge.php';

// Parse --key value args.
$args = [];
for ($i = 1; $i < $argc; $i++) {
    if (str_starts_with($argv[$i], '--')) {
        $key = substr($argv[$i], 2);
        $val = ($i + 1 < $argc && !str_starts_with($argv[$i + 1], '--')) ? $argv[++$i] : '1';
        $args[$key] = $val;
    }
}

if (isset($args['from-env'])) {
    foreach (['email', 'password', 'first', 'last'] as $key) {
        $value = getenv('ADMIN_' . strtoupper($key));
        if ($value !== false) $args[$key] = $value;
    }
}

$cfg = lms_config();
echo "Sapiqo setup — driver: {$cfg['db_driver']}\n";

// Writable dirs.
foreach ([$cfg['data_dir'], $cfg['badge_dir'], dirname($cfg['badge_placeholder'])] as $d) {
    if (!is_dir($d)) { mkdir($d, 0775, true); echo "  created $d\n"; }
}

// Tables.
db_migrate();
echo "  schema applied\n";

// Placeholder badge art.
ensure_placeholder_badge();
echo "  placeholder badge ready\n";

// Auto-discover courses from the drop-in courses directory.
$s = scan_courses();
echo '  courses scanned: ' . count($s['added']) . ' added, '
   . count($s['updated']) . ' updated, ' . count($s['deactivated']) . " deactivated\n";

// Create / update the administrator.
$email = strtolower(trim($args['email'] ?? ''));
if ($email === '') {
    echo "\nNo --email given, so no admin was created.\n";
    echo "Create one with:\n  php bin/setup.php --email you@example.edu --password 'YourPassword' --first First --last Last\n";
    exit(0);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Invalid --email\n"); exit(1); }
$password = (string) ($args['password'] ?? '');
if (strlen($password) < 8) { fwrite(STDERR, "--password must be at least 8 characters\n"); exit(1); }

$existing = db_one('SELECT * FROM users WHERE email = ?', [$email]);
$now = now_utc();
if ($existing) {
    db_run('UPDATE users SET role=?, password_hash=?, first_name=?, last_name=?, updated_at=? WHERE id=?', [
        'admin', password_hash($password, PASSWORD_DEFAULT),
        $args['first'] ?? $existing['first_name'], $args['last'] ?? $existing['last_name'],
        $now, $existing['id'],
    ]);
    echo "  updated existing user to admin: $email\n";
} else {
    db_insert('INSERT INTO users (first_name,last_name,email,password_hash,role,user_type,auth_provider,created_at,updated_at)
               VALUES (?,?,?,?,?,?,?,?,?)', [
        $args['first'] ?? 'Admin', $args['last'] ?? 'User', $email,
        password_hash($password, PASSWORD_DEFAULT), 'admin', 'Administrator', 'local', $now, $now,
    ]);
    echo "  created admin: $email\n";
}
echo "\nDone. Start the dev server with:\n  php -S localhost:8000 -t public public/router.php\nThen open http://localhost:8000/\n";

function seed_course_from_json(string $slug, string $fallbackTitle, string $coursesDir): void {
    $jsonPath = rtrim($coursesDir, '/') . "/$slug/course.json";
    $title = $fallbackTitle;
    $units = 0;
    if (is_file($jsonPath)) {
        $data = json_decode((string) file_get_contents($jsonPath), true);
        if ($data) {
            $title = $data['title'] ?? $fallbackTitle;
            $units = (int) (($data['stats']['lessons'] ?? 0) + ($data['stats']['topics'] ?? 0));
        }
    }
    // Optional: map course slug -> badge art in public/assets/badges/.
    // Leave empty; badges can also be set per-course in the admin UI.
    $badgeMap = [];
    $badge = $badgeMap[$slug] ?? '';

    $path = "/courses/$slug/";
    $existing = db_one('SELECT id FROM courses WHERE slug = ?', [$slug]);
    if ($existing) {
        db_run('UPDATE courses SET title=?, path=?, total_units=?, badge_image=? WHERE id=?',
            [$title, $path, $units, $badge, $existing['id']]);
        echo "  course updated: $title ($units units)" . ($badge ? " [badge set]" : "") . "\n";
    } else {
        db_insert('INSERT INTO courses (slug,title,path,total_units,badge_image,active,created_at) VALUES (?,?,?,?,?,1,?)',
            [$slug, $title, $path, $units, $badge, now_utc()]);
        echo "  course seeded: $title ($units units)" . ($badge ? " [badge set]" : "") . "\n";
    }
}
