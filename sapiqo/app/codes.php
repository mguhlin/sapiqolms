<?php
// Enrollment codes: an admin-issued (or externally-supplied) code that a learner
// redeems to auto-enroll into one OR many courses (a series / group subscription).
// Schema lives in db.php (db_ensure_enroll_codes): enroll_codes,
// enroll_code_courses, enroll_code_redemptions.

// Unambiguous alphabet for generated codes (no 0/O/1/I to avoid transcription errors).
const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

// Normalize a typed code: uppercase, drop all whitespace (dashes kept as typed).
function code_normalize(string $s): string {
    return preg_replace('/\s+/', '', strtoupper(trim($s)));
}

// Generate a fresh, unique, human-friendly code like "ABCD-EF23-GH45".
function code_generate(int $groups = 3, int $per = 4): string {
    $n = strlen(CODE_ALPHABET);
    $parts = [];
    for ($try = 0; $try < 25; $try++) {
        $parts = [];
        for ($g = 0; $g < $groups; $g++) {
            $s = '';
            for ($i = 0; $i < $per; $i++) $s .= CODE_ALPHABET[random_int(0, $n - 1)];
            $parts[] = $s;
        }
        $code = implode('-', $parts);
        if (!db_one('SELECT id FROM enroll_codes WHERE code = ?', [$code])) return $code;
    }
    // Extremely unlikely fallback: add entropy.
    return implode('-', $parts) . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function code_by_string(string $code): ?array {
    $code = code_normalize($code);
    if ($code === '') return null;
    return db_one('SELECT * FROM enroll_codes WHERE code = ?', [$code]);
}

function code_by_id(int $id): ?array {
    return db_one('SELECT * FROM enroll_codes WHERE id = ?', [$id]);
}

// Courses a code grants (joins to courses; skips any since-deleted course rows).
function code_courses(int $codeId): array {
    return db_all(
        'SELECT c.* FROM enroll_code_courses ec JOIN courses c ON c.id = ec.course_id
         WHERE ec.code_id = ? ORDER BY c.title', [$codeId]);
}

// Create a code granting one or many courses. $custom = an externally-supplied
// code string (from another system); blank = auto-generate. $maxUses null/<=0 =
// unlimited seats. $expiresAt = 'Y-m-d H:i:s' UTC or null.
// Returns [true, codeRow] or [false, errorString].
function code_create(string $label, array $courseIds, ?int $maxUses, ?string $expiresAt, int $createdBy, string $custom = ''): array {
    $courseIds = array_values(array_unique(array_filter(array_map('intval', $courseIds))));
    if (!$courseIds) return [false, 'Pick at least one course for the code.'];
    $valid = [];
    foreach ($courseIds as $cid) {
        if (course_by_id($cid)) $valid[] = $cid;
    }
    if (!$valid) return [false, 'None of the selected courses exist.'];

    $custom = code_normalize($custom);
    if ($custom !== '') {
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,62}[A-Z0-9]$/', $custom)) {
            return [false, 'Custom code must be 3–64 characters: letters, digits, and dashes.'];
        }
        if (db_one('SELECT id FROM enroll_codes WHERE code = ?', [$custom])) {
            return [false, 'That code already exists — pick a different one.'];
        }
        $code = $custom;
    } else {
        $code = code_generate();
    }

    if ($maxUses !== null && $maxUses <= 0) $maxUses = null;
    $exp = ($expiresAt !== null && $expiresAt !== '') ? $expiresAt : null;

    $id = db_insert(
        'INSERT INTO enroll_codes (code, label, max_uses, used_count, expires_at, active, created_by, created_at)
         VALUES (?,?,?,?,?,?,?,?)',
        [$code, trim($label), $maxUses, 0, $exp, 1, $createdBy, now_utc()]);
    foreach ($valid as $cid) {
        db_run('INSERT INTO enroll_code_courses (code_id, course_id) VALUES (?,?)', [$id, $cid]);
    }
    return [true, code_by_id($id)];
}

// Is a code currently redeemable (independent of whether THIS user already has)?
// Returns [true, ''] or [false, humanReason].
function code_redeemable(array $c): array {
    if ((int) $c['active'] !== 1) return [false, 'This code has been deactivated.'];
    if (!empty($c['expires_at']) && strtotime($c['expires_at'] . ' UTC') < time()) {
        return [false, 'This code has expired.'];
    }
    if ($c['max_uses'] !== null && (int) $c['used_count'] >= (int) $c['max_uses']) {
        return [false, 'This code has reached its usage limit.'];
    }
    return [true, ''];
}

// Redeem a code for a user: enroll into ALL its courses. Idempotent per
// (code, user) — re-redeeming re-syncs enrollments without consuming a new seat.
// Returns [true, ['code'=>row,'courses'=>[titles],'already'=>bool]] or [false, error].
function code_redeem(string $codeStr, int $userId): array {
    $c = code_by_string($codeStr);
    if (!$c) return [false, 'That code was not recognized.'];

    $already = db_one('SELECT id FROM enroll_code_redemptions WHERE code_id = ? AND user_id = ?',
        [$c['id'], $userId]);
    if (!$already) {
        [$ok, $why] = code_redeemable($c);
        if (!$ok) return [false, $why];
    }

    $titles = [];
    foreach (code_courses((int) $c['id']) as $course) {
        enroll($userId, (int) $course['id']);   // enroll() is idempotent
        $titles[] = $course['title'];
    }
    if (!$titles) return [false, 'This code has no active courses attached.'];

    if (!$already) {
        db_run('INSERT INTO enroll_code_redemptions (code_id, user_id, redeemed_at) VALUES (?,?,?)',
            [$c['id'], $userId, now_utc()]);
        db_run('UPDATE enroll_codes SET used_count = used_count + 1 WHERE id = ?', [$c['id']]);
    }
    return [true, ['code' => $c, 'courses' => $titles, 'already' => (bool) $already]];
}

// Admin listing: codes (optionally filtered by a search string over code+label),
// each decorated with its courses[] and redeemed count. Newest first.
function codes_all(string $q = ''): array {
    $q = trim($q);
    if ($q === '') {
        $rows = db_all('SELECT * FROM enroll_codes ORDER BY created_at DESC, id DESC');
    } else {
        $like = '%' . strtoupper($q) . '%';
        $rows = db_all(
            'SELECT * FROM enroll_codes WHERE UPPER(code) LIKE ? OR UPPER(label) LIKE ?
             ORDER BY created_at DESC, id DESC', [$like, $like]);
    }
    foreach ($rows as &$r) {
        $r['courses']  = code_courses((int) $r['id']);
        $r['redeemed'] = (int) (db_one('SELECT COUNT(*) n FROM enroll_code_redemptions WHERE code_id = ?',
            [$r['id']])['n'] ?? 0);
    }
    return $rows;
}

// Users who redeemed a given code, newest first.
function code_redemptions(int $codeId): array {
    return db_all(
        'SELECT r.redeemed_at, u.id AS user_id, u.first_name, u.last_name, u.email
         FROM enroll_code_redemptions r JOIN users u ON u.id = r.user_id
         WHERE r.code_id = ? ORDER BY r.redeemed_at DESC, u.last_name', [$codeId]);
}

function code_toggle(int $id): void {
    db_run('UPDATE enroll_codes SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
}

// Delete a code and its course links + redemption records (does NOT unenroll
// users already enrolled — their access/progress is preserved).
function code_delete(int $id): void {
    db_run('DELETE FROM enroll_code_courses WHERE code_id = ?', [$id]);
    db_run('DELETE FROM enroll_code_redemptions WHERE code_id = ?', [$id]);
    db_run('DELETE FROM enroll_codes WHERE id = ?', [$id]);
}

// Apply a code stashed in the session (from a /redeem?code= link followed by a
// sign-in/sign-up). Returns the redeem result array on success, else null.
function code_apply_pending(int $userId): ?array {
    $code = (string) ($_SESSION['pending_code'] ?? '');
    unset($_SESSION['pending_code']);
    if ($code === '') return null;
    [$ok, $res] = code_redeem($code, $userId);
    return $ok ? $res : null;
}
