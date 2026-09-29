<?php
// Training metrics / reporting. Driver-agnostic (SQLite + MySQL): simple
// aggregations use SQL; date bucketing is done in PHP. Charts are inline SVG so
// there are no external dependencies and the site stays offline-capable.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function report_kpis(): array {
    $users       = (int) (db_one('SELECT COUNT(*) n FROM users')['n'] ?? 0);
    $learners    = (int) (db_one("SELECT COUNT(*) n FROM users WHERE role='learner'")['n'] ?? 0);
    $admins      = (int) (db_one("SELECT COUNT(*) n FROM users WHERE role='admin'")['n'] ?? 0);
    $enrollments = (int) (db_one('SELECT COUNT(*) n FROM enrollments')['n'] ?? 0);
    $completions = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE status='completed'")['n'] ?? 0);
    $badges      = (int) (db_one('SELECT COUNT(*) n FROM badges')['n'] ?? 0);
    $active      = (int) (db_one('SELECT COUNT(DISTINCT user_id) n FROM progress')['n'] ?? 0);
    $inProgress  = (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE status='enrolled'")['n'] ?? 0);
    $rate = $enrollments > 0 ? (int) round($completions / $enrollments * 100) : 0;
    return [
        'users' => $users, 'learners' => $learners, 'admins' => $admins,
        'enrollments' => $enrollments, 'completions' => $completions,
        'in_progress' => $inProgress, 'badges' => $badges,
        'active_learners' => $active, 'completion_rate' => $rate,
    ];
}

function report_course_performance(): array {
    $rows = db_all(
        "SELECT c.id, c.title, c.total_units,
                COUNT(DISTINCT e.user_id) AS enrolled,
                SUM(CASE WHEN e.status='completed' THEN 1 ELSE 0 END) AS completed
         FROM courses c
         LEFT JOIN enrollments e ON e.course_id = c.id
         GROUP BY c.id, c.title, c.total_units
         ORDER BY c.title"
    );
    foreach ($rows as &$r) {
        $r['enrolled']  = (int) $r['enrolled'];
        $r['completed'] = (int) $r['completed'];
        $r['rate'] = $r['enrolled'] > 0 ? (int) round($r['completed'] / $r['enrolled'] * 100) : 0;
        $prog = (int) (db_one('SELECT COUNT(*) n FROM progress WHERE course_id = ?', [$r['id']])['n'] ?? 0);
        $denom = $r['enrolled'] * max(1, (int) $r['total_units']);
        $r['avg_progress'] = $denom > 0 ? (int) round(min(100, $prog / $denom * 100)) : 0;
    }
    return $rows;
}

function report_by_organization(): array {
    $rows = db_all(
        "SELECT CASE WHEN u.organization = '' THEN '(none)' ELSE u.organization END AS organization,
                COUNT(DISTINCT u.id) AS users,
                COUNT(DISTINCT e.user_id) AS enrolled,
                SUM(CASE WHEN e.status='completed' THEN 1 ELSE 0 END) AS completions
         FROM users u
         LEFT JOIN enrollments e ON e.user_id = u.id
         GROUP BY organization
         ORDER BY users DESC, organization"
    );
    foreach ($rows as &$r) {
        $r['users'] = (int) $r['users'];
        $r['enrolled'] = (int) $r['enrolled'];
        $r['completions'] = (int) $r['completions'];
        $r['rate'] = $r['enrolled'] > 0 ? (int) round($r['completions'] / $r['enrolled'] * 100) : 0;
    }
    return $rows;
}

function report_by_campus(): array {
    $rows = db_all(
        "SELECT CASE WHEN u.campus = '' THEN '(none)' ELSE u.campus END AS campus,
                CASE WHEN u.organization = '' THEN '' ELSE u.organization END AS organization,
                COUNT(DISTINCT u.id) AS users,
                COUNT(DISTINCT e.user_id) AS enrolled,
                SUM(CASE WHEN e.status='completed' THEN 1 ELSE 0 END) AS completions
         FROM users u
         LEFT JOIN enrollments e ON e.user_id = u.id
         GROUP BY campus, organization
         ORDER BY users DESC, campus"
    );
    foreach ($rows as &$r) {
        $r['users'] = (int) $r['users'];
        $r['enrolled'] = (int) $r['enrolled'];
        $r['completions'] = (int) $r['completions'];
        $r['rate'] = $r['enrolled'] > 0 ? (int) round($r['completions'] / $r['enrolled'] * 100) : 0;
    }
    return $rows;
}

function report_by_user_type(): array {
    $rows = db_all(
        "SELECT CASE WHEN user_type = '' THEN '(unspecified)' ELSE user_type END AS user_type,
                COUNT(*) AS users
         FROM users GROUP BY user_type ORDER BY users DESC"
    );
    foreach ($rows as &$r) $r['users'] = (int) $r['users'];
    return $rows;
}

// Weekly buckets for the last $weeks weeks: registrations, enrollments, completions.
function report_timeline(int $weeks = 8): array {
    $buckets = [];
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $t = strtotime("-$i weeks");
        $key = date('oW', $t);
        $buckets[$key] = [
            'label' => date('M j', strtotime('monday this week', $t)),
            'registered' => 0, 'enrolled' => 0, 'completed' => 0,
        ];
    }
    $bump = function (string $sql, string $field) use (&$buckets) {
        foreach (db_all($sql) as $row) {
            $ts = $row['ts'] ?? '';
            if (!$ts) continue;
            $key = date('oW', strtotime($ts));
            if (isset($buckets[$key])) $buckets[$key][$field]++;
        }
    };
    $bump('SELECT created_at AS ts FROM users', 'registered');
    $bump('SELECT enrolled_at AS ts FROM enrollments', 'enrolled');
    $bump("SELECT completed_at AS ts FROM enrollments WHERE status='completed' AND completed_at IS NOT NULL", 'completed');
    return array_values($buckets);
}

// Merged recent-activity feed across registrations, enrollments, completions.
function report_recent_activity(int $limit = 12): array {
    $events = [];
    foreach (db_all('SELECT first_name,last_name,email,created_at AS ts FROM users ORDER BY created_at DESC LIMIT 20') as $r) {
        $events[] = ['ts' => $r['ts'], 'type' => 'registered', 'who' => _who($r), 'what' => 'registered'];
    }
    foreach (db_all('SELECT u.first_name,u.last_name,u.email,c.title,e.enrolled_at AS ts
                     FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id
                     ORDER BY e.enrolled_at DESC LIMIT 20') as $r) {
        $events[] = ['ts' => $r['ts'], 'type' => 'enrolled', 'who' => _who($r), 'what' => 'enrolled in ' . $r['title']];
    }
    foreach (db_all("SELECT u.first_name,u.last_name,u.email,c.title,e.completed_at AS ts
                     FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id
                     WHERE e.status='completed' AND e.completed_at IS NOT NULL
                     ORDER BY e.completed_at DESC LIMIT 20") as $r) {
        $events[] = ['ts' => $r['ts'], 'type' => 'completed', 'who' => _who($r), 'what' => 'completed ' . $r['title']];
    }
    usort($events, fn($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));
    return array_slice($events, 0, $limit);
}

function _who(array $r): string {
    $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    return $name !== '' ? $name : ($r['email'] ?? 'Someone');
}

// --- Inline SVG charts -------------------------------------------------------

function svg_donut(int $percent, string $centerLabel = '', int $size = 140): string {
    $percent = max(0, min(100, $percent));
    $r = ($size / 2) - 14;
    $c = 2 * M_PI * $r;
    $fill = $c * $percent / 100;
    $cx = $cy = $size / 2;
    $label = $centerLabel !== '' ? $centerLabel : ($percent . '%');
    return '<svg viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . e($percent . ' percent') . '">'
        . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#e2e8f2" stroke-width="14"/>'
        . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#f4b41a" stroke-width="14"'
        . ' stroke-linecap="round" stroke-dasharray="' . round($fill, 2) . ' ' . round($c, 2) . '"'
        . ' transform="rotate(-90 ' . $cx . ' ' . $cy . ')"/>'
        . '<text x="50%" y="50%" text-anchor="middle" dominant-baseline="central"'
        . ' font-family="sans-serif" font-size="' . round($size / 5) . '" font-weight="700" fill="#0b2e5b">' . e($label) . '</text>'
        . '</svg>';
}

// Grouped vertical bar chart. $series = [['label'=>'#123','color'=>'#..'], ...]
// $rows = [['label'=>'Sep 1', 'values'=>[a,b,c]], ...]
function svg_bar_chart(array $rows, array $series, int $width = 720, int $height = 240): string {
    $padL = 32; $padB = 28; $padT = 12; $padR = 8;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $max = 1;
    foreach ($rows as $r) foreach ($r['values'] as $v) $max = max($max, (int) $v);
    $groups = max(1, count($rows));
    $groupW = $plotW / $groups;
    $barGap = 3;
    $nSeries = max(1, count($series));
    $barW = max(3, ($groupW - 10 - ($nSeries - 1) * $barGap) / $nSeries);

    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="' . $height . '" role="img" aria-label="Activity over time">';
    // gridlines + y labels (0, mid, max)
    foreach ([0, 0.5, 1] as $g) {
        $y = $padT + $plotH - $plotH * $g;
        $val = (int) round($max * $g);
        $svg .= '<line x1="' . $padL . '" y1="' . $y . '" x2="' . ($width - $padR) . '" y2="' . $y . '" stroke="#eef2f9"/>';
        $svg .= '<text x="' . ($padL - 6) . '" y="' . ($y + 3) . '" text-anchor="end" font-family="sans-serif" font-size="9" fill="#8595ab">' . $val . '</text>';
    }
    foreach ($rows as $gi => $r) {
        $gx = $padL + $groupW * $gi + 5;
        foreach ($r['values'] as $si => $v) {
            $h = $plotH * ((int) $v) / $max;
            $x = $gx + $si * ($barW + $barGap);
            $y = $padT + $plotH - $h;
            $color = $series[$si]['color'] ?? '#2b62aa';
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round($h, 1) . '" rx="2" fill="' . $color . '"><title>' . e($r['label'] . ': ' . $v) . '</title></rect>';
        }
        $svg .= '<text x="' . round($gx + $groupW / 2 - 5, 1) . '" y="' . ($height - 8) . '" text-anchor="middle" font-family="sans-serif" font-size="9" fill="#8595ab">' . e($r['label']) . '</text>';
    }
    $svg .= '</svg>';
    return $svg;
}
