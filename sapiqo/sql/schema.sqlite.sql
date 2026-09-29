-- Sapiqo schema (SQLite).
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  first_name    TEXT NOT NULL DEFAULT '',
  last_name     TEXT NOT NULL DEFAULT '',
  email         TEXT NOT NULL UNIQUE,
  phone         TEXT NOT NULL DEFAULT '',
  password_hash TEXT,
  role          TEXT NOT NULL DEFAULT 'learner',   -- 'admin' | 'learner'
  user_type     TEXT NOT NULL DEFAULT '',          -- Student | Teacher | Administrator | Staff | Other
  campus        TEXT NOT NULL DEFAULT '',
  organization  TEXT NOT NULL DEFAULT '',
  auth_provider TEXT NOT NULL DEFAULT 'local',      -- local | google | microsoft
  provider_sub  TEXT,
  created_at    TEXT NOT NULL DEFAULT '',
  updated_at    TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS courses (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  slug         TEXT NOT NULL UNIQUE,
  title        TEXT NOT NULL,
  path         TEXT NOT NULL DEFAULT '',            -- launch URL (relative)
  total_units  INTEGER NOT NULL DEFAULT 0,
  badge_image  TEXT NOT NULL DEFAULT '',
  active       INTEGER NOT NULL DEFAULT 1,
  created_at   TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS enrollments (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id    INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  status       TEXT NOT NULL DEFAULT 'enrolled',    -- enrolled | completed
  enrolled_at  TEXT NOT NULL DEFAULT '',
  completed_at TEXT,
  UNIQUE (user_id, course_id)
);

CREATE TABLE IF NOT EXISTS progress (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id    INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  step_id      TEXT NOT NULL,
  completed_at TEXT NOT NULL DEFAULT '',
  UNIQUE (user_id, course_id, step_id)
);

CREATE TABLE IF NOT EXISTS badges (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id  INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  code       TEXT NOT NULL UNIQUE,
  title      TEXT NOT NULL DEFAULT '',
  issued_at  TEXT NOT NULL DEFAULT '',
  image_path TEXT NOT NULL DEFAULT '',
  UNIQUE (user_id, course_id)
);

CREATE INDEX IF NOT EXISTS idx_progress_user_course ON progress(user_id, course_id);
CREATE INDEX IF NOT EXISTS idx_enroll_user ON enrollments(user_id);
CREATE INDEX IF NOT EXISTS idx_badges_user ON badges(user_id);

CREATE TABLE IF NOT EXISTS user_groups (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL UNIQUE,
  description TEXT NOT NULL DEFAULT '',
  created_at  TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS user_group_members (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id  INTEGER NOT NULL REFERENCES user_groups(id) ON DELETE CASCADE,
  user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  added_at  TEXT NOT NULL DEFAULT '',
  UNIQUE (group_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_ugm_group ON user_group_members(group_id);
CREATE INDEX IF NOT EXISTS idx_ugm_user ON user_group_members(user_id);

CREATE TABLE IF NOT EXISTS group_managers (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id  INTEGER NOT NULL REFERENCES user_groups(id) ON DELETE CASCADE,
  user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  added_at  TEXT NOT NULL DEFAULT '',
  UNIQUE (group_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_gm_user ON group_managers(user_id);

-- Quiz / knowledge-check results (one row per learner per quiz step).
CREATE TABLE IF NOT EXISTS quiz_results (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id  INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  quiz_id    TEXT NOT NULL,
  score      INTEGER NOT NULL DEFAULT 0,
  total      INTEGER NOT NULL DEFAULT 0,
  passed     INTEGER NOT NULL DEFAULT 0,
  attempts   INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT '',
  UNIQUE (user_id, course_id, quiz_id)
);
CREATE INDEX IF NOT EXISTS idx_quiz_user_course ON quiz_results(user_id, course_id);

-- Append-only audit trail of privileged actions.
CREATE TABLE IF NOT EXISTS audit_log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id    INTEGER,
  actor_email TEXT NOT NULL DEFAULT '',
  action      TEXT NOT NULL,
  target_type TEXT NOT NULL DEFAULT '',
  target_id   TEXT NOT NULL DEFAULT '',
  detail      TEXT NOT NULL DEFAULT '',
  ip          TEXT NOT NULL DEFAULT '',
  created_at  TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_actor ON audit_log(actor_id);

-- Login attempts for throttling/lockout.
CREATE TABLE IF NOT EXISTS login_attempts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  ident      TEXT NOT NULL DEFAULT '',
  ip         TEXT NOT NULL DEFAULT '',
  success    INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_login_ident ON login_attempts(ident, created_at);
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip, created_at);

-- Single-use, time-limited password reset tokens (hash stored, never the token).
CREATE TABLE IF NOT EXISTS password_resets (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at    TEXT,
  created_at TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_reset_token ON password_resets(token_hash);

-- Saved (unpublished) course editor drafts, one namespace per admin.
CREATE TABLE IF NOT EXISTS course_drafts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  slug       TEXT NOT NULL DEFAULT '',
  title      TEXT NOT NULL DEFAULT '',
  markdown   TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL DEFAULT '',
  UNIQUE (admin_id, slug)
);

-- Key/value application settings (branding, theme, etc.).
CREATE TABLE IF NOT EXISTS settings (
  key        TEXT PRIMARY KEY,
  value      TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL DEFAULT ''
);

-- LTI 1.3 registered platforms (Canvas/Moodle/Blackboard/etc.).
CREATE TABLE IF NOT EXISTS lti_platforms (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  name           TEXT NOT NULL DEFAULT '',
  issuer         TEXT NOT NULL DEFAULT '',
  client_id      TEXT NOT NULL DEFAULT '',
  deployment_id  TEXT NOT NULL DEFAULT '',
  auth_login_url TEXT NOT NULL DEFAULT '',
  auth_token_url TEXT NOT NULL DEFAULT '',
  jwks_url       TEXT NOT NULL DEFAULT '',
  public_key     TEXT NOT NULL DEFAULT '',
  created_at     TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_lti_iss ON lti_platforms(issuer, client_id);

-- Short-lived LTI OIDC state/nonce (survives cross-site launch without cookies).
CREATE TABLE IF NOT EXISTS lti_state (
  state       TEXT PRIMARY KEY,
  nonce       TEXT NOT NULL DEFAULT '',
  platform_id INTEGER NOT NULL,
  target      TEXT NOT NULL DEFAULT '',
  created_at  TEXT NOT NULL DEFAULT ''
);

-- LTI AGS line items captured at launch (for grade passback on completion).
CREATE TABLE IF NOT EXISTS lti_results (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  course_id   INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  platform_id INTEGER NOT NULL,
  lineitem    TEXT NOT NULL DEFAULT '',
  scopes      TEXT NOT NULL DEFAULT '',
  sub         TEXT NOT NULL DEFAULT '',
  updated_at  TEXT NOT NULL DEFAULT '',
  UNIQUE (user_id, course_id)
);

-- REST API keys (only the SHA-256 hash is stored).
CREATE TABLE IF NOT EXISTS api_keys (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL DEFAULT '',
  prefix      TEXT NOT NULL DEFAULT '',
  token_hash  TEXT NOT NULL,
  created_by  INTEGER,
  created_at  TEXT NOT NULL DEFAULT '',
  last_used_at TEXT,
  revoked_at  TEXT
);
CREATE INDEX IF NOT EXISTS idx_apikey_hash ON api_keys(token_hash);

-- In-app notifications.
CREATE TABLE IF NOT EXISTS notifications (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  message    TEXT NOT NULL DEFAULT '',
  url        TEXT NOT NULL DEFAULT '',
  read_at    TEXT,
  created_at TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, read_at);
