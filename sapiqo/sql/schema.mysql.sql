-- Sapiqo schema (MySQL / MariaDB).
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(120) NOT NULL DEFAULT '',
  last_name     VARCHAR(120) NOT NULL DEFAULT '',
  email         VARCHAR(255) NOT NULL,
  phone         VARCHAR(60)  NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'learner',
  user_type     VARCHAR(40)  NOT NULL DEFAULT '',
  campus        VARCHAR(160) NOT NULL DEFAULT '',
  organization  VARCHAR(160) NOT NULL DEFAULT '',
  auth_provider VARCHAR(20)  NOT NULL DEFAULT 'local',
  provider_sub  VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS courses (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(160) NOT NULL,
  title        VARCHAR(255) NOT NULL,
  path         VARCHAR(255) NOT NULL DEFAULT '',
  total_units  INT NOT NULL DEFAULT 0,
  badge_image  VARCHAR(255) NOT NULL DEFAULT '',
  active       TINYINT NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL,
  UNIQUE KEY uq_courses_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enrollments (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  course_id    BIGINT UNSIGNED NOT NULL,
  status       VARCHAR(20) NOT NULL DEFAULT 'enrolled',
  enrolled_at  DATETIME NOT NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_enroll (user_id, course_id),
  KEY idx_enroll_user (user_id),
  CONSTRAINT fk_enroll_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  course_id    BIGINT UNSIGNED NOT NULL,
  step_id      VARCHAR(191) NOT NULL,
  completed_at DATETIME NOT NULL,
  UNIQUE KEY uq_progress (user_id, course_id, step_id),
  KEY idx_progress_user_course (user_id, course_id),
  CONSTRAINT fk_prog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS badges (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  course_id  BIGINT UNSIGNED NOT NULL,
  code       VARCHAR(64) NOT NULL,
  title      VARCHAR(255) NOT NULL DEFAULT '',
  issued_at  DATETIME NOT NULL,
  image_path VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uq_badge_code (code),
  UNIQUE KEY uq_badge_user_course (user_id, course_id),
  KEY idx_badges_user (user_id),
  CONSTRAINT fk_badge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_badge_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_groups (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(160) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL,
  UNIQUE KEY uq_group_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_group_members (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id  BIGINT UNSIGNED NOT NULL,
  user_id   BIGINT UNSIGNED NOT NULL,
  added_at  DATETIME NOT NULL,
  UNIQUE KEY uq_group_member (group_id, user_id),
  KEY idx_ugm_group (group_id),
  KEY idx_ugm_user (user_id),
  CONSTRAINT fk_ugm_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_ugm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_managers (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id  BIGINT UNSIGNED NOT NULL,
  user_id   BIGINT UNSIGNED NOT NULL,
  added_at  DATETIME NOT NULL,
  UNIQUE KEY uq_group_manager_m (group_id, user_id),
  KEY idx_gm_user (user_id),
  CONSTRAINT fk_gm_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_gm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_results (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  course_id  BIGINT UNSIGNED NOT NULL,
  quiz_id    VARCHAR(191) NOT NULL,
  score      INT NOT NULL DEFAULT 0,
  total      INT NOT NULL DEFAULT 0,
  passed     TINYINT NOT NULL DEFAULT 0,
  attempts   INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_quiz (user_id, course_id, quiz_id),
  KEY idx_quiz_user_course (user_id, course_id),
  CONSTRAINT fk_quiz_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_quiz_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id    BIGINT UNSIGNED NULL,
  actor_email VARCHAR(255) NOT NULL DEFAULT '',
  action      VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) NOT NULL DEFAULT '',
  target_id   VARCHAR(64) NOT NULL DEFAULT '',
  detail      TEXT NULL,
  ip          VARCHAR(64) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL,
  KEY idx_audit_created (created_at),
  KEY idx_audit_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ident      VARCHAR(255) NOT NULL DEFAULT '',
  ip         VARCHAR(64) NOT NULL DEFAULT '',
  success    TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_login_ident (ident, created_at),
  KEY idx_login_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(191) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_reset_token (token_hash),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course_drafts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id   BIGINT UNSIGNED NOT NULL,
  slug       VARCHAR(160) NOT NULL DEFAULT '',
  title      VARCHAR(255) NOT NULL DEFAULT '',
  markdown   MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_draft (admin_id, slug),
  CONSTRAINT fk_draft_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key`      VARCHAR(64) NOT NULL PRIMARY KEY,
  value      TEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lti_platforms (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(160) NOT NULL DEFAULT '',
  issuer         VARCHAR(255) NOT NULL DEFAULT '',
  client_id      VARCHAR(255) NOT NULL DEFAULT '',
  deployment_id  VARCHAR(255) NOT NULL DEFAULT '',
  auth_login_url VARCHAR(255) NOT NULL DEFAULT '',
  auth_token_url VARCHAR(255) NOT NULL DEFAULT '',
  jwks_url       VARCHAR(255) NOT NULL DEFAULT '',
  public_key     TEXT NULL,
  created_at     DATETIME NOT NULL,
  KEY idx_lti_iss (issuer, client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lti_state (
  state       VARCHAR(64) NOT NULL PRIMARY KEY,
  nonce       VARCHAR(64) NOT NULL DEFAULT '',
  platform_id BIGINT UNSIGNED NOT NULL,
  target      VARCHAR(500) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lti_results (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  course_id   BIGINT UNSIGNED NOT NULL,
  platform_id BIGINT UNSIGNED NOT NULL,
  lineitem    VARCHAR(500) NOT NULL DEFAULT '',
  scopes      TEXT NULL,
  sub         VARCHAR(255) NOT NULL DEFAULT '',
  updated_at  DATETIME NOT NULL,
  UNIQUE KEY uq_lti_result (user_id, course_id),
  CONSTRAINT fk_ltires_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ltires_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_keys (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL DEFAULT '',
  prefix       VARCHAR(16) NOT NULL DEFAULT '',
  token_hash   VARCHAR(191) NOT NULL,
  created_by   BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  revoked_at   DATETIME NULL,
  KEY idx_apikey_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  message    VARCHAR(500) NOT NULL DEFAULT '',
  url        VARCHAR(255) NOT NULL DEFAULT '',
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_notif_user (user_id, read_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
