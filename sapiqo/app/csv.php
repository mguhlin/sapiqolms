<?php
// CSV bulk import of users + enrollment, plus the downloadable template.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/courses.php';
require_once __DIR__ . '/groups.php';

// Write a CSV row, guarding against "CSV/formula injection": spreadsheet apps
// (Excel, Sheets) treat a cell starting with =, +, -, or @ as a formula, which
// can exfiltrate data or run commands when the file is opened. Names/emails/
// titles in these exports are user-supplied (self-registration, course
// titles, …), so every export route should call this instead of fputcsv().
// Prefixing with a tab neutralizes the formula while keeping the value visible
// and round-trippable (Excel/Sheets/LibreOffice all display it as plain text).
function csv_out($fh, array $row): void {
    fputcsv($fh, array_map(function ($v) {
        $s = (string) $v;
        return $s !== '' && strpbrk($s[0], "=+-@") !== false ? "\t" . $s : $s;
    }, $row));
}

// Canonical template columns (order shown to admins).
const CSV_COLUMNS = [
    'first name', 'last name', 'email', 'campus',
    'organization', 'user type', 'course title/id', 'group',
];

function csv_template_string(): string {
    $rows = [
        CSV_COLUMNS,
        ['Ada', 'Lovelace', 'ada@example.edu', 'Central High School', 'Example ISD', 'Teacher', 'Chromebook Educator', 'Example ISD'],
        ['Grace', 'Hopper', 'grace@example.edu', 'North Elementary', 'Example ISD', 'Teacher', 'chromebook-educator', 'Example ISD'],
    ];
    $fh = fopen('php://temp', 'r+');
    foreach ($rows as $r) fputcsv($fh, $r);
    rewind($fh);
    return stream_get_contents($fh);
}

// Normalize a header cell: lowercase, collapse spaces, strip BOM.
function _csv_norm(string $h): string {
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
    return strtolower(trim($h));
}

// Map flexible header names to canonical keys.
function _csv_header_map(array $headers): array {
    $aliases = [
        'first name' => 'first_name', 'firstname' => 'first_name', 'first' => 'first_name',
        'last name' => 'last_name', 'lastname' => 'last_name', 'last' => 'last_name', 'surname' => 'last_name',
        'email' => 'email', 'e-mail' => 'email', 'email address' => 'email',
        'campus' => 'campus', 'school' => 'campus',
        'organization' => 'organization', 'org' => 'organization', 'district' => 'organization',
        'user type' => 'user_type', 'usertype' => 'user_type', 'type' => 'user_type', 'role' => 'user_type',
        'course title/id' => 'course', 'course' => 'course', 'course title' => 'course',
        'course id' => 'course', 'course_id' => 'course', 'title/id' => 'course',
        'group' => 'group', 'group name' => 'group', 'cohort' => 'group', 'district group' => 'group',
    ];
    $map = [];
    foreach ($headers as $i => $h) {
        $key = $aliases[_csv_norm($h)] ?? null;
        if ($key) $map[$key] = $i;
    }
    return $map;
}

// Import a CSV file. Returns a structured report; does not throw on row errors.
function import_users_csv(string $filepath): array {
    $report = [
        'created' => [], 'enrolled' => [], 'existing' => [], 'grouped' => [], 'errors' => [], 'rows' => 0,
    ];

    $fh = fopen($filepath, 'r');
    if (!$fh) {
        $report['errors'][] = ['row' => 0, 'message' => 'Could not open the uploaded file.'];
        return $report;
    }

    $headers = fgetcsv($fh);
    if ($headers === false) {
        $report['errors'][] = ['row' => 0, 'message' => 'The file appears to be empty.'];
        fclose($fh);
        return $report;
    }
    $map = _csv_header_map($headers);
    if (!isset($map['email'])) {
        $report['errors'][] = ['row' => 1, 'message' => 'Missing required "email" column. Download the template.'];
        fclose($fh);
        return $report;
    }

    $get = function (array $row, string $key) use ($map): string {
        return isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
    };

    $line = 1;
    while (($row = fgetcsv($fh)) !== false) {
        $line++;
        if (count(array_filter($row, fn($c) => trim((string) $c) !== '')) === 0) continue; // blank line
        $report['rows']++;

        $email = strtolower($get($row, 'email'));
        if (!valid_email($email)) {
            $report['errors'][] = ['row' => $line, 'message' => "Invalid or missing email: '" . $email . "'"];
            continue;
        }

        $data = [
            'first_name'   => $get($row, 'first_name'),
            'last_name'    => $get($row, 'last_name'),
            'email'        => $email,
            'campus'       => $get($row, 'campus'),
            'organization' => $get($row, 'organization'),
            'user_type'    => $get($row, 'user_type'),
        ];
        $courseRef = $get($row, 'course');
        $groupRef = $get($row, 'group');

        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) {
            $tempPw = _temp_password();
            [$ok, $res] = register_local($data + ['password' => $tempPw, 'role' => 'learner']);
            if (!$ok) {
                $report['errors'][] = ['row' => $line, 'message' => (string) $res];
                continue;
            }
            $userId = (int) $res;
            $report['created'][] = ['row' => $line, 'email' => $email, 'temp_password' => $tempPw];
            $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
        } else {
            $userId = (int) $user['id'];
            // Fill in any blank profile fields without clobbering existing values.
            _fill_blanks($userId, $user, $data);
            $report['existing'][] = ['row' => $line, 'email' => $email];
        }

        if ($courseRef !== '') {
            $course = resolve_course($courseRef);
            if (!$course) {
                $report['errors'][] = ['row' => $line, 'message' => "Course not found: '" . $courseRef . "'"];
                continue;
            }
            enroll($userId, (int) $course['id']);
            $report['enrolled'][] = ['row' => $line, 'email' => $email, 'course' => $course['title']];
        }

        if ($groupRef !== '') {
            $gid = create_group($groupRef);
            add_member($gid, $userId);
            $report['grouped'][] = ['row' => $line, 'email' => $email, 'group' => $groupRef];
        }
    }
    fclose($fh);
    return $report;
}

function _fill_blanks(int $userId, array $user, array $data): void {
    $updates = [];
    $params = [];
    foreach (['first_name', 'last_name', 'campus', 'organization', 'user_type'] as $f) {
        if (($user[$f] ?? '') === '' && ($data[$f] ?? '') !== '') {
            $updates[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if ($updates) {
        $params[] = now_utc();
        $params[] = $userId;
        db_run('UPDATE users SET ' . implode(', ', $updates) . ', updated_at = ? WHERE id = ?', $params);
    }
}

function _temp_password(): string {
    // Human-friendly temporary password: 3 short chunks.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i > 0 && $i % 4 === 0) $out .= '-';
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}
