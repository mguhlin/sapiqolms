<?php
// PDO database layer. Supports SQLite (default) and MySQL via the same code.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/version.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = lms_config();
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($cfg['db_driver'] === 'mysql') {
        $m = $cfg['mysql'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $m['host'], $m['port'], $m['dbname'], $m['charset']);
        $pdo = new PDO($dsn, $m['user'], $m['pass'], $opts);
    } else {
        $path = $cfg['sqlite_path'];
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

// Convenience helpers ---------------------------------------------------------

function db_one(string $sql, array $params = []): ?array {
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_run(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_insert(string $sql, array $params = []): int {
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}

function now_utc(): string {
    return gmdate('Y-m-d H:i:s');
}

// Bump when schema.*.sql or db_ensure_columns() change, so existing installs
// re-run the (idempotent) migration on the next request.
const DB_SCHEMA_VERSION = 21;

// Runs the schema for the active driver. Idempotent (CREATE TABLE IF NOT EXISTS).
function db_migrate(): void {
    $cfg = lms_config();
    $file = dirname(__DIR__) . '/sql/schema.' . ($cfg['db_driver'] === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read schema file: $file");
    }
    // Split on semicolons at line ends (schema uses simple statements).
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        db()->exec(rtrim($stmt, ';'));
    }
    db_ensure_columns();
}

// Add columns that were introduced after the initial table definitions.
// SQLite/MySQL both lack a portable "ADD COLUMN IF NOT EXISTS", so we probe.
function db_ensure_columns(): void {
    db_add_column_if_missing('enrollments', 'last_step_id', 'TEXT', 'VARCHAR(191)');
    db_add_column_if_missing('enrollments', 'last_seen_at', 'TEXT', 'DATETIME');
    db_add_column_if_missing('enrollments', 'expires_at', 'TEXT', 'DATETIME');
    db_add_column_if_missing('enrollments', 'expiry_warned', 'INTEGER', 'TINYINT');
    // Per-course enrollment lifetime in days (0/NULL = no expiry).
    db_add_column_if_missing('courses', 'enroll_days', 'INTEGER', 'INT');
    // Prerequisite course that must be completed first (NULL = none).
    db_add_column_if_missing('courses', 'prereq_id', 'INTEGER', 'BIGINT');
    // CPE credit hours awarded by the course (shown on the certificate; 0 = none).
    db_add_column_if_missing('courses', 'cpe_hours', 'REAL', 'DECIMAL(5,2)');
    // Gifted & Talented (GT) credit hours awarded by the course (0 = none).
    db_add_column_if_missing('courses', 'gt_hours', 'REAL', 'DECIMAL(5,2)');
    // Publication status: 'published' (visible to everyone) or 'draft' (visible
    // only to admins and course developers).
    db_add_column_if_missing('courses', 'status', 'TEXT', "VARCHAR(16)");
    // Certification courses get distinct card styling and sort to the top.
    db_add_column_if_missing('courses', 'is_certification', 'INTEGER', 'TINYINT');
    // Sequential mode: learners must complete each lesson before the next, so the
    // badge/certificate is only reachable after finishing every lesson in order.
    db_add_column_if_missing('courses', 'sequential', 'INTEGER', 'TINYINT');
    // Transcript durability: snapshot the CPE hours and slug at the moment a badge
    // is earned, so the learner's permanent record stays accurate even if the
    // course's CPE value changes (or the course is later deactivated) afterward.
    db_add_column_if_missing('badges', 'cpe_hours', 'REAL', 'DECIMAL(5,2)');
    db_add_column_if_missing('badges', 'gt_hours', 'REAL', 'DECIMAL(5,2)');
    db_add_column_if_missing('badges', 'course_slug', 'TEXT', 'VARCHAR(191)');
    // Course-developer capability: a non-admin who may author/edit course content
    // (the in-browser editor + content imports) but not manage users or settings.
    db_add_column_if_missing('users', 'can_edit_content', 'INTEGER', 'TINYINT');
    // Per-grant group-manager permissions (default on = backward compatible): may
    // enroll their members into courses, and add/edit member profiles.
    db_add_column_if_missing('group_managers', 'perm_enroll', 'INTEGER', 'TINYINT');
    db_add_column_if_missing('group_managers', 'perm_members', 'INTEGER', 'TINYINT');
    // May the manager add/remove the group's course subscriptions? Default off:
    // deciding which courses a group gets is a higher privilege the admin grants.
    db_add_column_if_missing('group_managers', 'perm_courses', 'INTEGER', 'TINYINT');
    // Organization a group belongs to (NULL = standalone group, no org).
    db_add_column_if_missing('user_groups', 'org_id', 'INTEGER', 'BIGINT');
    // Discussion forum: per-course enable (NULL/1 = on) + "post before you see"
    // gating toggle (0 = off) that a course developer controls.
    db_add_column_if_missing('courses', 'forum_enabled', 'INTEGER', 'TINYINT');
    db_add_column_if_missing('courses', 'forum_gated', 'INTEGER', 'TINYINT');
    db_ensure_forum();
    // Optional expiry for a post (used by announcements: hidden from regular
    // users past this date, but admins can still see the full history).
    db_add_column_if_missing('forum_posts', 'expires_at', 'TEXT', 'DATETIME');
    db_ensure_gradebook();
    db_ensure_orgs();
    db_ensure_enroll_codes();
    db_ensure_indexes();
}

// Enrollment codes: an admin-issued (or externally-supplied) code that a learner
// redeems to auto-enroll into one OR many courses (a series / group subscription).
//   enroll_codes             — the code itself: seats (max_uses), expiry, on/off
//   enroll_code_courses      — the course(s) a code grants (many-to-many)
//   enroll_code_redemptions  — who redeemed which code, and when (one per user/code)
// Created here (not schema.*.sql) so the single non-scrubbed db.php carries them to
// both the live install and the template. Idempotent.
function db_ensure_enroll_codes(): void {
    if (lms_config()['db_driver'] === 'mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS enroll_codes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(64) NOT NULL,
            label VARCHAR(191) NOT NULL DEFAULT '',
            max_uses INT NULL,
            used_count INT NOT NULL DEFAULT 0,
            expires_at DATETIME NULL,
            active TINYINT NOT NULL DEFAULT 1,
            created_by BIGINT NULL,
            created_at DATETIME NULL,
            UNIQUE KEY uq_enroll_code (code)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS enroll_code_courses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            code_id BIGINT NOT NULL,
            course_id BIGINT NOT NULL,
            UNIQUE KEY uq_code_course (code_id, course_id),
            KEY idx_codecrs_code (code_id),
            KEY idx_codecrs_course (course_id)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS enroll_code_redemptions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            code_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            redeemed_at DATETIME NULL,
            UNIQUE KEY uq_code_redemption (code_id, user_id),
            KEY idx_coderdm_code (code_id),
            KEY idx_coderdm_user (user_id)
        )");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS enroll_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            label TEXT NOT NULL DEFAULT '',
            max_uses INTEGER,
            used_count INTEGER NOT NULL DEFAULT 0,
            expires_at TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            created_by INTEGER,
            created_at TEXT,
            UNIQUE (code)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS enroll_code_courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            UNIQUE (code_id, course_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_codecrs_code ON enroll_code_courses(code_id)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_codecrs_course ON enroll_code_courses(course_id)");
        db()->exec("CREATE TABLE IF NOT EXISTS enroll_code_redemptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            redeemed_at TEXT,
            UNIQUE (code_id, user_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_coderdm_code ON enroll_code_redemptions(code_id)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_coderdm_user ON enroll_code_redemptions(user_id)");
    }
}

// Organizations (e.g. "Aldirk ISD") own groups and members, and can subscribe
// whole cohorts to courses. Created here (not in schema.*.sql) so the single
// non-scrubbed db.php carries them to both the live install and the template.
//   organizations  — the top-level entity
//   org_members    — who belongs to an org (union with group members drives access)
//   org_courses    — courses an entire org is subscribed to (everyone gets them)
//   group_courses  — courses a single group is subscribed to (that group gets them)
//   org_managers   — sub-admins scoped to a whole org (all its groups)
// Idempotent.
function db_ensure_orgs(): void {
    if (lms_config()['db_driver'] === 'mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS organizations (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NULL,
            UNIQUE KEY uq_org_name (name)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS org_members (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            added_at DATETIME NULL,
            UNIQUE KEY uq_org_member (org_id, user_id),
            KEY idx_orgmem_org (org_id),
            KEY idx_orgmem_user (user_id)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS org_courses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT NOT NULL,
            course_id BIGINT NOT NULL,
            added_at DATETIME NULL,
            UNIQUE KEY uq_org_course (org_id, course_id),
            KEY idx_orgcrs_org (org_id)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS group_courses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT NOT NULL,
            course_id BIGINT NOT NULL,
            added_at DATETIME NULL,
            UNIQUE KEY uq_group_course (group_id, course_id),
            KEY idx_grpcrs_group (group_id)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS org_managers (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            org_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            perm_enroll TINYINT NULL,
            perm_members TINYINT NULL,
            perm_courses TINYINT NULL,
            added_at DATETIME NULL,
            UNIQUE KEY uq_org_manager (org_id, user_id),
            KEY idx_orgmgr_user (user_id)
        )");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS organizations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            created_at TEXT,
            UNIQUE (name)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS org_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            org_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            added_at TEXT,
            UNIQUE (org_id, user_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_orgmem_org ON org_members(org_id)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_orgmem_user ON org_members(user_id)");
        db()->exec("CREATE TABLE IF NOT EXISTS org_courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            org_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            added_at TEXT,
            UNIQUE (org_id, course_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_orgcrs_org ON org_courses(org_id)");
        db()->exec("CREATE TABLE IF NOT EXISTS group_courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            added_at TEXT,
            UNIQUE (group_id, course_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_grpcrs_group ON group_courses(group_id)");
        db()->exec("CREATE TABLE IF NOT EXISTS org_managers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            org_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            perm_enroll INTEGER,
            perm_members INTEGER,
            perm_courses INTEGER,
            added_at TEXT,
            UNIQUE (org_id, user_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_orgmgr_user ON org_managers(user_id)");
    }
}

// Secondary indexes for the "by course" reporting/roster direction. The base
// schema already indexes the per-user direction (idx_enroll_user, etc.); these
// cover the completions, gradebook, and roster pages that filter by course_id
// alone, which otherwise degrade to full scans as the tables grow (matters at
// thousands of users x enrollments). Idempotent and portable across drivers.
function db_ensure_indexes(): void {
    db_add_index_if_missing('enrollments',  'idx_enroll_course',   'course_id');
    db_add_index_if_missing('progress',     'idx_progress_course', 'course_id');
    db_add_index_if_missing('quiz_results', 'idx_quiz_course',     'course_id');
    db_add_index_if_missing('badges',       'idx_badges_course',   'course_id');
}

// Custom gradebook: instructor-created assessments (beyond auto-graded quizzes)
// and their per-learner scores. Created in db.php so both trees get it. Idempotent.
function db_ensure_gradebook(): void {
    if (lms_config()['db_driver'] === 'mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS assessments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            course_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            category VARCHAR(120) NOT NULL DEFAULT '',
            max_points DECIMAL(8,2) NOT NULL DEFAULT 100,
            due_date VARCHAR(32) NULL,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            INDEX idx_assess_course (course_id)
        )");
        db()->exec("CREATE TABLE IF NOT EXISTS assessment_scores (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            assessment_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            points DECIMAL(8,2) NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_ascore (assessment_id, user_id),
            INDEX idx_ascore_user (user_id)
        )");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS assessments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            category TEXT NOT NULL DEFAULT '',
            max_points REAL NOT NULL DEFAULT 100,
            due_date TEXT,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_assess_course ON assessments(course_id)");
        db()->exec("CREATE TABLE IF NOT EXISTS assessment_scores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            assessment_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            points REAL,
            updated_at TEXT,
            UNIQUE (assessment_id, user_id)
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_ascore_user ON assessment_scores(user_id)");
    }
}

// Discussion forum posts: per-module course boards plus a global announcement
// board. Created here (not in schema.*.sql) so the single non-scrubbed db.php
// carries it to both the live install and the clean template. Idempotent.
function db_ensure_forum(): void {
    if (lms_config()['db_driver'] === 'mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS forum_posts (
            id         BIGINT AUTO_INCREMENT PRIMARY KEY,
            scope      VARCHAR(20)  NOT NULL DEFAULT 'course',   -- 'course' | 'announcement'
            course_id  BIGINT       NULL,
            module_key VARCHAR(191) NULL,
            parent_id  BIGINT       NULL,                        -- NULL = top-level post/thread
            user_id    BIGINT       NOT NULL,
            title      VARCHAR(255) NOT NULL DEFAULT '',
            body       MEDIUMTEXT   NOT NULL,
            pinned     TINYINT      NOT NULL DEFAULT 0,
            hidden     TINYINT      NOT NULL DEFAULT 0,
            created_at DATETIME     NULL,
            updated_at DATETIME     NULL,
            INDEX idx_forum_scope (scope),
            INDEX idx_forum_course (course_id, module_key),
            INDEX idx_forum_parent (parent_id)
        )");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS forum_posts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            scope      TEXT NOT NULL DEFAULT 'course',
            course_id  INTEGER,
            module_key TEXT,
            parent_id  INTEGER,
            user_id    INTEGER NOT NULL,
            title      TEXT NOT NULL DEFAULT '',
            body       TEXT NOT NULL,
            pinned     INTEGER NOT NULL DEFAULT 0,
            hidden     INTEGER NOT NULL DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_forum_scope  ON forum_posts(scope)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_forum_course ON forum_posts(course_id, module_key)");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_forum_parent ON forum_posts(parent_id)");
    }
    // Reactions (likes) on forum posts.
    if (lms_config()['db_driver'] === 'mysql') {
        db()->exec("CREATE TABLE IF NOT EXISTS forum_reactions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT NOT NULL, user_id BIGINT NOT NULL, created_at DATETIME NULL,
            UNIQUE KEY uq_reaction (post_id, user_id), INDEX idx_reaction_post (post_id))");
    } else {
        db()->exec("CREATE TABLE IF NOT EXISTS forum_reactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL, user_id INTEGER NOT NULL, created_at TEXT,
            UNIQUE (post_id, user_id))");
        db()->exec("CREATE INDEX IF NOT EXISTS idx_reaction_post ON forum_reactions(post_id)");
    }
}

function db_add_column_if_missing(string $table, string $col, string $sqliteType, string $mysqlType): void {
    $cfg = lms_config();
    if ($cfg['db_driver'] === 'mysql') {
        $exists = db_one(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col]
        );
        if (!$exists) db()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $mysqlType NULL");
    } else {
        foreach (db_all("PRAGMA table_info($table)") as $c) {
            if (($c['name'] ?? '') === $col) return;
        }
        db()->exec("ALTER TABLE $table ADD COLUMN $col $sqliteType");
    }
}

// Create an index only if it isn't already present. MySQL has no portable
// "CREATE INDEX IF NOT EXISTS", so we probe information_schema; SQLite supports
// the IF NOT EXISTS form directly. $cols is a bare column list, e.g. "course_id".
function db_add_index_if_missing(string $table, string $index, string $cols): void {
    $cfg = lms_config();
    if ($cfg['db_driver'] === 'mysql') {
        $exists = db_one(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );
        if (!$exists) db()->exec("CREATE INDEX `$index` ON `$table` ($cols)");
    } else {
        db()->exec("CREATE INDEX IF NOT EXISTS $index ON $table ($cols)");
    }
}

// Cheap boot-time guard: run migrations only when the schema marker is stale.
// The marker file lives in the persistent data dir, so no DB query per request.
function ensure_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cfg = lms_config();
    $dir = $cfg['data_dir'];
    $marker = $dir . '/.schema-v' . DB_SCHEMA_VERSION;
    if (is_file($marker)) return;
    try {
        db_migrate();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($marker, gmdate('c'));
    } catch (Throwable $e) {
        // Don't hard-fail the request if migration can't complete (e.g. DB not
        // yet provisioned); setup.php / installer will handle first-time setup.
        error_log('ensure_schema: ' . $e->getMessage());
    }
}
