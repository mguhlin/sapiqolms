<?php
// Server-side quiz grading + result storage. The answer key lives only in the
// course.json on disk (stripped before it reaches learners), so grading is
// authoritative here and a passing quiz marks its progress step complete.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/courses.php';

// Locate a quiz by id inside a course's course.json. Returns the quiz array
// (with answer key) or null.
function quiz_find(string $slug, string $quizId): ?array {
    $dir = rtrim(lms_config()['courses_dir'], '/');
    $path = "$dir/$slug/course.json";
    if (!is_file($path)) return null;
    $data = json_decode((string) file_get_contents($path), true) ?: [];
    foreach ($data['modules'] ?? [] as $m) {
        foreach ($m['lessons'] ?? [] as $l) {
            if (!empty($l['quiz']) && ($l['quiz']['id'] ?? '') === $quizId) return $l['quiz'];
        }
    }
    return null;
}

// Normalize a short-answer string for lenient matching.
function _sa_norm(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\s+/', ' ', $s);
    return preg_replace('/[.!?,;:"\'\x60]+$/u', '', $s);
}

// Index questions by their qid (falls back to positional q<index>).
function _quiz_by_qid(array $quiz): array {
    $map = [];
    foreach ($quiz['questions'] ?? [] as $i => $q) {
        $map[$q['qid'] ?? ('q' . $i)] = $q;
    }
    return $map;
}

// Grade answers. $answers is keyed by qid: choice => [optionIndex,...] (original
// option order), short => "text". $asked limits grading to a presented subset
// (for pick-N); null = all questions. Returns score/total/percent/passed + a
// per-question detail array (for feedback), or null if the quiz has no questions.
function quiz_grade(array $quiz, array $answers, ?array $asked = null): ?array {
    $byId = _quiz_by_qid($quiz);
    if (!$byId) return null;
    $ids = $asked !== null && $asked ? array_values(array_intersect(array_keys($byId), $asked)) : array_keys($byId);
    $total = count($ids);
    if ($total === 0) return null;

    $score = 0; $detail = [];
    foreach ($ids as $qid) {
        $q = $byId[$qid];
        $ok = false;
        if (($q['type'] ?? 'single') === 'short') {
            $given = _sa_norm((string) ($answers[$qid] ?? ''));
            foreach ($q['answers'] ?? [] as $acc) {
                if ($given !== '' && _sa_norm((string) $acc) === $given) { $ok = true; break; }
            }
        } else {
            $correctSet = [];
            foreach ($q['options'] ?? [] as $oi => $o) if (!empty($o['correct'])) $correctSet[] = (int) $oi;
            $sel = array_map('intval', (array) ($answers[$qid] ?? []));
            sort($sel); sort($correctSet);
            $ok = ($sel === $correctSet && $sel !== []);
        }
        if ($ok) $score++;
        $detail[$qid] = ['correct' => $ok, 'feedback' => (string) ($q['feedback'] ?? '')];
    }
    $pass = (int) ($quiz['pass'] ?? 70);
    $percent = (int) round($score / $total * 100);
    return ['score' => $score, 'total' => $total, 'percent' => $percent,
            'passed' => $percent >= $pass, 'pass' => $pass, 'detail' => $detail];
}

// Attempts used by a learner on a quiz (0 if none).
function quiz_attempts_used(int $userId, int $courseId, string $quizId): int {
    $r = db_one('SELECT attempts FROM quiz_results WHERE user_id=? AND course_id=? AND quiz_id=?',
        [$userId, $courseId, $quizId]);
    return (int) ($r['attempts'] ?? 0);
}

// Record (upsert) a learner's quiz result, incrementing the attempt count.
function quiz_record(int $userId, int $courseId, string $quizId, array $graded): void {
    $existing = db_one('SELECT id, attempts FROM quiz_results WHERE user_id=? AND course_id=? AND quiz_id=?',
        [$userId, $courseId, $quizId]);
    $now = now_utc();
    if ($existing) {
        db_run('UPDATE quiz_results SET score=?, total=?, passed=?, attempts=attempts+1, updated_at=? WHERE id=?',
            [$graded['score'], $graded['total'], $graded['passed'] ? 1 : 0, $now, $existing['id']]);
    } else {
        db_run('INSERT INTO quiz_results (user_id,course_id,quiz_id,score,total,passed,attempts,updated_at)
                VALUES (?,?,?,?,?,?,?,?)',
            [$userId, $courseId, $quizId, $graded['score'], $graded['total'], $graded['passed'] ? 1 : 0, 1, $now]);
    }
}

// Quiz results for a learner in a course, keyed by quiz_id.
function quiz_results_for(int $userId, int $courseId): array {
    $out = [];
    foreach (db_all('SELECT * FROM quiz_results WHERE user_id=? AND course_id=?', [$userId, $courseId]) as $r) {
        $out[$r['quiz_id']] = $r;
    }
    return $out;
}

// Keep each presented bank subset in the authenticated session. A learner's
// submission cannot replace it with a smaller/easier list of question IDs.
function quiz_session_key(int $courseId, string $quizId): string {
    return (string) ($_SESSION['uid'] ?? 0) . ':' . $courseId . ':' . $quizId;
}
function quiz_prepare_course(array $data, int $courseId): array {
    foreach ($data['modules'] ?? [] as $mi => $module) foreach ($module['lessons'] ?? [] as $li => $lesson) {
        if (empty($lesson['quiz']['id'])) continue;
        $quiz = &$data['modules'][$mi]['lessons'][$li]['quiz'];
        $byId = _quiz_by_qid($quiz);
        $key = quiz_session_key($courseId, (string) $quiz['id']);
        $fingerprint = hash('sha256', json_encode($quiz));
        $saved = $_SESSION['quiz_sets'][$key] ?? null;
        if (!$saved || ($saved['fingerprint'] ?? '') !== $fingerprint) {
            $ids = array_keys($byId);
            $pick = (int) ($quiz['pick'] ?? 0);
            if ($pick > 0 && $pick < count($ids)) {
                // Fisher-Yates with a cryptographic random source.
                for ($i = count($ids) - 1; $i > 0; $i--) {
                    $j = random_int(0, $i); [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                }
                $ids = array_slice($ids, 0, $pick);
            }
            $saved = ['ids' => $ids, 'fingerprint' => $fingerprint];
            $_SESSION['quiz_sets'][$key] = $saved;
        }
        $quiz['questions'] = [];
        foreach ($saved['ids'] as $qid) $quiz['questions'][] = $byId[$qid] + ['qid' => $qid];
        unset($quiz['pick']); // subset already selected by the server
        unset($quiz);
    }
    return $data;
}

// Lock a result row before checking/incrementing attempts. The first write also
// serializes SQLite's writer transactions, including first-ever attempts.
function quiz_record_limited(int $userId, int $courseId, string $quizId, array $graded, int $limit): bool {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $sql = lms_config()['db_driver'] === 'mysql'
            ? 'INSERT IGNORE INTO quiz_results (user_id,course_id,quiz_id,score,total,passed,attempts,updated_at) VALUES (?,?,?,0,0,0,0,?)'
            : 'INSERT OR IGNORE INTO quiz_results (user_id,course_id,quiz_id,score,total,passed,attempts,updated_at) VALUES (?,?,?,0,0,0,0,?)';
        db_run($sql, [$userId, $courseId, $quizId, now_utc()]);
        $suffix = lms_config()['db_driver'] === 'mysql' ? ' FOR UPDATE' : '';
        $row = db_one('SELECT attempts, passed FROM quiz_results WHERE user_id=? AND course_id=? AND quiz_id=?' . $suffix,
            [$userId, $courseId, $quizId]);
        if ($limit > 0 && (int) $row['attempts'] >= $limit && !(int) $row['passed']) {
            $pdo->rollBack(); return false;
        }
        quiz_record($userId, $courseId, $quizId, $graded);
        $pdo->commit(); return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
