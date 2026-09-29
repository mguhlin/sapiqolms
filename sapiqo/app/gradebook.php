<?php
// Custom gradebook: instructor-created assessments + per-learner scores, with a
// TalentLMS-style scores grid (learners x assessments), overall %, and letters.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function gb_assessments(int $courseId): array {
    return db_all('SELECT * FROM assessments WHERE course_id = ? ORDER BY position, id', [$courseId]);
}
function gb_assessment(int $id): ?array {
    return db_one('SELECT * FROM assessments WHERE id = ?', [$id]);
}

function gb_add_assessment(int $courseId, string $title, string $category, float $max, ?string $due): int {
    $pos = (int) (db_one('SELECT COALESCE(MAX(position),0)+1 n FROM assessments WHERE course_id = ?', [$courseId])['n'] ?? 1);
    return db_insert(
        'INSERT INTO assessments (course_id, title, category, max_points, due_date, position, created_at) VALUES (?,?,?,?,?,?,?)',
        [$courseId, trim($title) ?: 'Untitled', trim($category), max(0.0, $max) ?: 100, $due ?: null, $pos, now_utc()]);
}
function gb_update_assessment(int $id, string $title, string $category, float $max, ?string $due): void {
    db_run('UPDATE assessments SET title=?, category=?, max_points=?, due_date=? WHERE id=?',
        [trim($title) ?: 'Untitled', trim($category), max(0.0, $max) ?: 100, $due ?: null, $id]);
}
function gb_delete_assessment(int $id): void {
    db_run('DELETE FROM assessments WHERE id = ?', [$id]);
    db_run('DELETE FROM assessment_scores WHERE assessment_id = ?', [$id]);
}

// Set (or clear, when $points is null) a learner's score for an assessment.
function gb_set_score(int $assessmentId, int $userId, ?float $points): void {
    if ($points === null) {
        db_run('DELETE FROM assessment_scores WHERE assessment_id = ? AND user_id = ?', [$assessmentId, $userId]);
        return;
    }
    $exists = db_one('SELECT id FROM assessment_scores WHERE assessment_id = ? AND user_id = ?', [$assessmentId, $userId]);
    if ($exists) {
        db_run('UPDATE assessment_scores SET points = ?, updated_at = ? WHERE id = ?', [$points, now_utc(), $exists['id']]);
    } else {
        db_run('INSERT INTO assessment_scores (assessment_id, user_id, points, updated_at) VALUES (?,?,?,?)',
            [$assessmentId, $userId, $points, now_utc()]);
    }
}

// [assessment_id => [user_id => points]] for a course.
function gb_scores_for_course(int $courseId): array {
    $rows = db_all(
        'SELECT s.assessment_id, s.user_id, s.points FROM assessment_scores s
         JOIN assessments a ON a.id = s.assessment_id WHERE a.course_id = ?', [$courseId]);
    $map = [];
    foreach ($rows as $r) $map[(int) $r['assessment_id']][(int) $r['user_id']] = $r['points'];
    return $map;
}

// Enrolled learners for a course (grid rows).
function gb_learners(int $courseId): array {
    return db_all(
        "SELECT u.id, u.first_name, u.last_name, u.email FROM users u
         JOIN enrollments e ON e.user_id = u.id
         WHERE e.course_id = ? AND u.role <> 'admin'
         ORDER BY u.last_name, u.first_name", [$courseId]);
}

// Letter-grade scale: [minPercent => letter], highest first. Overridable via the
// `grade_scale` setting ("A:90,B:80,C:70,D:60").
function gb_grade_scale(): array {
    $def = 'A:90,B:80,C:70,D:60';
    $raw = function_exists('setting') ? (setting('grade_scale', '') ?: $def) : $def;
    $scale = [];
    foreach (explode(',', $raw) as $pair) {
        if (!str_contains($pair, ':')) continue;
        [$l, $p] = explode(':', $pair, 2);
        $scale[] = ['letter' => trim($l), 'min' => (float) trim($p)];
    }
    usort($scale, fn($a, $b) => $b['min'] <=> $a['min']);
    return $scale ?: [['letter' => 'A', 'min' => 90], ['letter' => 'B', 'min' => 80], ['letter' => 'C', 'min' => 70], ['letter' => 'D', 'min' => 60]];
}
function gb_letter(?float $pct): string {
    if ($pct === null) return '—';
    foreach (gb_grade_scale() as $band) if ($pct >= $band['min']) return $band['letter'];
    return 'F';
}

// A learner's overall percent = sum(points) / sum(max) over SCORED assessments.
function gb_overall(int $userId, array $assessments, array $scores): ?float {
    $got = 0.0; $poss = 0.0;
    foreach ($assessments as $a) {
        $aid = (int) $a['id'];
        if (isset($scores[$aid][$userId]) && $scores[$aid][$userId] !== null) {
            $got += (float) $scores[$aid][$userId];
            $poss += (float) $a['max_points'];
        }
    }
    if ($poss <= 0) return null;
    return round($got / $poss * 100, 1);
}

// Format a numeric value without trailing zeros (3.00 -> "3", 2.50 -> "2.5").
function gb_num($n): string {
    if ($n === null || $n === '') return '';
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}
