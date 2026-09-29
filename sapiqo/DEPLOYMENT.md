# Sapiqo — Deployment Guide

Sapiqo is pure PHP + PDO with no Composer dependencies, so deployment is
"copy the folder, point the web root at `public/`, run the installer." It runs on
LAMP (Linux/Apache/MySQL/PHP) and on Windows + PHP, with MySQL in production or
SQLite for small/offline installs.

---

## 1. Requirements

- **PHP 8.1+** with extensions: `pdo`, `pdo_mysql` (or `pdo_sqlite`), `gd`
  (badge images), `mbstring`, `openssl`, and `curl` (only for SSO).
- A web server: **Apache** (with `mod_rewrite`), Nginx + PHP-FPM, or IIS.
- **MySQL / MariaDB** for production (or SQLite for a small/offline install).

Check extensions: `php -m`.

## 2. Folder layout (everything inside courses/)

The whole project lives in one `courses/` folder:

```
courses/                    the project — a clean 3-folder root; zip it to relocate everything
  sapiqo/                 CODE — replace this subfolder to upgrade (app, public, sql, bin,
                          creator/, scripts/, install scripts)
  sapiqo-data/            PRIVATE, PERSISTS — config.local.php, data/ (DB, badges, avatars, lti keys), badge-library/
  content/                ALL course content (drop a course folder here → auto-discovered)
    assets/                 shared course-reader assets (css/js/fonts)
    index.html  data/       standalone (no-LMS) catalog for static hosting
    chromebook-educator/    a course (course.json, media/, badge.png)
    cybersmart-educator/    …
```

The web server's **document root is `courses/sapiqo/public/`**. The code
resolves the data folder via the `SAPIQO_DATA` env var, else the sibling
`sapiqo-data/`; and the course-content folder via `SAPIQO_COURSES`, else the
sibling `content/`. **Upgrading is "replace `courses/sapiqo/`."** — nothing in it is user data.

Course files are served same-origin at `/courses/<slug>/…` by a controlled PHP
passthrough (with HTTP range support for video). It only serves course content
and **refuses `sapiqo/` and `sapiqo-data/`**, so the database, config, and
code are never web-reachable even though they sit in the same folder. (Optional:
on Apache you may instead add `Alias /courses "…/courses"` with a `<Directory>`
that denies `sapiqo*` for static-file performance.)

## 3. Install (one script)

From inside the unpacked `sapiqo/` folder:

```bash
bash install.sh            # Linux / macOS
```
```powershell
powershell -ExecutionPolicy Bypass -File install.ps1    # Windows
```

The installer checks PHP + extensions, prompts for the database (SQLite or
MySQL) and an administrator account, creates `sapiqo-data/` + `config.local.php`,
builds the schema, creates the admin, **scans `courses/` and registers every
course automatically**, and prints how to start the site. It is safe to re-run.

For MySQL, create the database/user first:

```sql
CREATE DATABASE sapiqo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sapiqo'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON sapiqo.* TO 'sapiqo'@'localhost'; FLUSH PRIVILEGES;
```
(the installer asks for these credentials). Prefer a manual setup? Run
`SAPIQO_DATA=../sapiqo-data php bin/setup.php --email you@x.edu --password '...'`.

## 3a. Drop-in courses + upgrades

- **Add a course:** drop its folder (containing `course.json`, `media/`, and an
  optional `badge.png`) into `courses/`. It is picked up automatically (admins
  can also click **Rescan courses**). A `<slug>/badge.png` is used as the course
  badge; otherwise a name match in `badge-library/`, otherwise a generic medallion.
- **Remove a course:** delete/move its folder out of `courses/`. It becomes
  **unavailable** (hidden from the catalog, launch blocked) but every learner's
  **badge, certificate, enrollment, and progress is preserved**. Restore the
  folder (even a revamped version) and access returns.
- **Upgrade the LMS:** two options — (a) replace the `sapiqo/` folder with the new
  version, or (b) **Admin → Software updates**: upload a `.tar.gz` package
  (built with `php bin/build-update.php` or the same page's download button) to
  update the **code** in place. Either way `sapiqo-data/` and `content/` are
  untouched and schema changes apply on next run. The in-browser updater **backs up
  the current code first** and supports one-click **rollback** (admin-only; it runs
  uploaded code — restrict the admin role accordingly).

## 4. (Manual alternative) Run setup directly

```bash
SAPIQO_DATA="$(pwd)/../sapiqo-data" \
  php bin/setup.php --email admin@yourdistrict.org --password 'StrongPassword' --first Site --last Admin
```

This creates the tables, the administrator, scans the drop-in courses, and
generates the placeholder badge. Safe to re-run.

## 5. Permissions

The persistent **`sapiqo-data/`** folder must be writable by the web server
user (SQLite DB, generated badges, and uploaded profile photos live there). The
installer sets this when run as root; otherwise:

```bash
chown -R www-data:www-data ../sapiqo-data     # Linux/Apache; adjust user
chmod -R 775 ../sapiqo-data
```

On MySQL, `data/` still holds generated badge images, so it must remain writable.

## 6. Web server configuration

### Apache

`public/.htaccess` is included and handles routing, the WebVTT MIME type, and
blocks the SQLite file. Ensure `AllowOverride All` is set for the vhost and
`mod_rewrite` is enabled.

```apache
<VirtualHost *:443>
  ServerName lms.yourdistrict.org
  DocumentRoot /var/www/sapiqo/public
  <Directory /var/www/sapiqo/public>
    AllowOverride All
    Require all granted
  </Directory>
  # SSLEngine on + certificate directives here
</VirtualHost>
```

### Nginx + PHP-FPM

```nginx
server {
  listen 443 ssl;
  server_name lms.yourdistrict.org;
  root /var/www/sapiqo/public;
  index index.php;

  location / { try_files $uri $uri/ /index.php?$query_string; }
  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
  types { text/vtt vtt; }              # captions
  location ~* \.(sqlite|sqlite-wal|sqlite-shm)$ { deny all; }
}
```

### Windows + IIS

Install PHP (with the extensions above) and URL Rewrite. Map the site root to
`public\`. Add a rewrite rule equivalent to the `.htaccess`: serve existing
files/directories as-is, otherwise route to `index.php`. Ensure the app pool
identity can write to `data\`.

## 6a. Scaling & performance (large deployments)

The app is pure PHP + PDO with no per-request framework overhead, so a **single
well-tuned server (4–8 vCPU, 8–16 GB RAM) comfortably serves ~5,000 accounts**
with a few hundred concurrently-active users. Enrollments, quiz submissions, and
forum posts are the only write-heavy paths; everything else is cacheable reads.
The items below are ordered by impact. The first three are the ones that matter.

### 1. Use MySQL/MariaDB, never SQLite, in production

SQLite takes a **single global write lock** — every progress save, quiz submit,
and forum post serializes into one queue, which stalls under concurrency. The
sample `config.local.php` already defaults to `db_driver => 'mysql'`; keep it.
SQLite is fine only for a personal/offline install.

Tune the database for the working set:

```ini
# MySQL/MariaDB (my.cnf) — a starting point for ~5,000 users
innodb_buffer_pool_size = 4G        # ~60–70% of RAM on a dedicated DB box
max_connections         = 200       # a little above your PHP-FPM max workers
innodb_flush_log_at_trx_commit = 2  # faster writes; ~1s durability window
```

### 2. Enable OPcache + a realpath cache (PHP)

Without OPcache, PHP re-parses every `.php` file on every request. Turning it on
is typically a **3–5× throughput gain** — the single biggest lever. In `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1        # set 0 for max speed; then reload PHP on deploy
realpath_cache_size=4096K
realpath_cache_ttl=600
```

### 3. Run PHP-FPM and size the worker pool

Serve via **PHP-FPM behind nginx/Apache** (never `php -S`, which is single-
threaded and dev-only). Size the pool to RAM, budgeting ~40–60 MB per worker:

```ini
; /etc/php/*/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 60        ; ≈ available_RAM_for_PHP / 50MB
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 20
```

### 4. Offload media transfers to the web server

You may ship several GB of course media. By default the app streams it through a
PHP worker (`app/serve.php`), which ties that worker up for the whole download.
Enable the built-in offload so the web server pushes the bytes and the worker is
freed immediately (auth + path checks still run in PHP first). Set **one** option
in `config.local.php` to match your stack:

**nginx** — `'x_accel_redirect' => '/__protected_courses'`, then add an internal
location aliased to your `courses/` directory:

```nginx
# inside the server { } block; the alias MUST point at your courses/ folder
location /__protected_courses/ {
  internal;
  alias /var/www/courses/;      # trailing slash required
}
```

**Apache / lighttpd** — install `mod_xsendfile`, then set `'x_sendfile' => true`:

```apache
XSendFile On
XSendFilePath /var/www/courses      # whitelist the courses dir
```

Sandboxed uploads (SVG/HTML) are deliberately **never** offloaded, so their
protective CSP headers always apply.

### 5. Cache static assets + enable compression

The reader shell, JS, CSS, and images are highly cacheable. `serve.php` already
sends `Cache-Control: public, max-age=3600`; add gzip/brotli and long-lived
caching for `/assets/` at the web server, or front the whole site with a CDN
(the media offload above pairs well with a CDN origin-pull).

### 6. Session locking (handled) and multi-node

PHP locks the session file for the duration of a request; the app already calls
`session_write_close()` before streaming media so one user's parallel requests
don't serialize. For a **single** app server that's all you need. To run **more
than one** app server behind a load balancer, make the tier stateless:

- **Sessions** → shared store (Redis/Memcached) via `session.save_handler`, or
  enable sticky sessions on the load balancer.
- **`sapiqo-data/`** (uploads, badges, branding) → shared storage (NFS or an
  S3-compatible mount) so every node sees the same files.
- **Database** → all nodes point at the same MySQL/MariaDB (or a managed one).

### 7. Database indexes (automatic)

The schema indexes both the per-user and per-course directions of the hot tables
(enrollments, progress, quiz_results, badges, forum_posts). The course-direction
indexes used by the completions/gradebook/roster reports are created
automatically on upgrade by `db_ensure_indexes()` — no manual step. Large admin
lists are paginated so a big roster never renders all at once.

## 7. HTTPS

Run over HTTPS in production (session cookies are marked secure automatically
when HTTPS is detected). Use your district CA, Let's Encrypt, or a reverse proxy.

## 8. Optional single sign-on (Google / Microsoft / Clever / ClassLink / Rhythm)

Edit `sapiqo-data/config.local.php`:

```php
'sso' => [
  'google' => [
    'enabled' => true,
    'client_id' => 'xxxx.apps.googleusercontent.com',
    'client_secret' => 'xxxx',
  ],
],
```

Register the redirect URI with the provider:
`https://lms.yourdistrict.org/auth/google/callback` (swap `google` for
`microsoft`, `clever`, `classlink`, or `rhythm`). Enabled providers appear as
buttons on the login page; local accounts keep working alongside SSO. On SSO
login the provider profile photo is pulled in automatically (until the user
uploads their own).

**K-12 providers:** `clever` and `classlink` use fixed provider endpoints — just
supply the client id/secret. Skyward/Ascender districts typically federate
through Clever or ClassLink rather than a separate button. `rhythm` endpoints
vary by district tenant, so set `auth_url` / `token_url` / `userinfo_url`
(and an optional field `map`) as shown in `config.local.example.php`.

## 9. Bulk-enrolling users + groups

Admin → **Bulk import users** accepts a CSV with the header row:

```
first name,last name,email,campus,organization,user type,course title/id,group
```

Only `email` is required per row. The course column may be a course **title**,
**slug**, or **id**; the `group` column auto-creates/assigns a group (e.g. a
district). New accounts receive a temporary password shown in the import report.
Download the template from the admin screen or use `csv-template.csv`.

**Groups & sub-admins:** Admin → **Groups** create groups (or one click "from
organizations/campuses"), view completion rate + steps per group, and assign a
**manager (sub-admin)** who can add/edit/enroll/remove members for that group
only, via their **Manage** area.

## 9a. Organizations & course subscriptions

For onboarding a whole client/district at once, use **Admin → Organizations**
(`/admin/orgs`). An organization (e.g. *Aldirk ISD*) owns groups and members and
can **subscribe** cohorts to courses so enrollment is managed, not manual:

- **Org-wide subscription** — every member of the org is enrolled in the course,
  and anyone added to the org (or to any of its groups) later is auto-enrolled.
- **Per-group subscription** — a single group inside the org gets its own courses,
  so different groups (e.g. *Elementary* vs *Secondary*) can have different access.
  A member's access is the union of their org-wide and per-group subscriptions.
- **Auto-enroll on join** — adding a member (individually or via the bulk CSV into
  a group) immediately enrolls them in everything their org/groups are subscribed to.
- **Non-destructive un-subscribe** — removing a course from an org/group stops
  *future* auto-enrollment; learners already in it keep the course, their progress,
  and any earned badge/certificate.
- **Managers** — assign an **organization manager** (manages the whole org and all
  its groups) or a **group manager**, each with per-grant permissions: *enroll*,
  *edit members*, and *manage course subscriptions* (the last is off by default and
  granted explicitly). They work from their scoped **Manage** area.

Typical flow: create the org → bulk-import its people (CSV `organization`/`group`
columns) or add them by email → subscribe the org and/or its groups to courses →
assign a manager to run it day-to-day.

## 10. Backups

Back up the whole **`sapiqo-data/`** folder — it holds the database
(MySQL dump, or `data/lms.sqlite*`), generated badges, and profile photos — plus
the `courses/` folder. This is real student data; align retention and access with
FERPA and your Texas DPA obligations. The `sapiqo/` code folder needs no backup.

## 10a. Moving to a new server

The whole application is **self-contained in the `courses/` folder** (code +
data + courses), so a move is essentially "copy the folder." Pick the path that
matches your database.

### Staying on SQLite (simplest — recommended for demos / pre-launch)

Nothing to configure; the SQLite database travels inside the folder.

1. On the **old** server, make sure nothing is mid-write (stop the web server or
   pick a quiet moment), then copy the entire `courses/` tree to the new server —
   e.g. `rsync -a courses/ user@newhost:/var/www/courses/`. This carries the code,
   the `content/` courses **and** `sapiqo-data/` (the `lms.sqlite` database with all
   users, enrollments, progress, badges, and forum posts).
2. On the new server, install PHP 8.1+ with the extensions (`bash installer/install.sh
   --install-deps` will add them, or use Docker), point the web root at
   `courses/sapiqo/public/`, and ensure `sapiqo-data/` is writable by the web-server
   user (`chown -R www-data:www-data courses/sapiqo-data`).
3. Enable HTTPS (see §7) and smoke-test: sign in, play a video, download a badge.

That's it — no database import, because the database *is* a file inside the copy.
(If you only copied part of the tree, remember `sapiqo-data/data/lms.sqlite` is the
database — without it you get an empty install.)

### Switching to MySQL/MariaDB

Two sub-cases:

- **Fresh MySQL install (no existing data to keep).** Create the database + user
  (the installer can do this for you), then run the installer pointed at MySQL:
  ```bash
  bash installer/install.sh --db mysql --create-db \
    --mysql-host 127.0.0.1 --mysql-db sapiqo --mysql-user sapiqo --mysql-pass 'AppSecret' \
    --mysql-admin root --mysql-admin-pass 'RootSecret' \
    --email you@example.org --password 'AdminPass' --first Ada --last Lovelace --yes
  ```
  Or just run `bash installer/install.sh` interactively — it now asks **SQLite or
  MySQL**, prompts for the connection details, and offers to **create the database +
  app user** for you (given a MySQL admin login). It writes
  `sapiqo-data/config.local.php` and builds the MySQL schema automatically. Your
  `content/` courses are picked up on the setup scan; user accounts start empty.
- **Moving an existing SQLite instance onto MySQL (keep users/progress).** This is a
  data migration, not just a copy — backup/restore is same-driver only, so there is
  no built-in SQLite→MySQL converter yet. If you need this, ask and a one-time
  migration script can be added; until then, either stay on SQLite or start fresh
  on MySQL.

See **§6a (Scaling & performance)** for when MySQL is worth it — for demos and
modest usage, SQLite is perfectly fine.

## Packaging checklist

- [ ] Everything under `courses/`: `sapiqo/` (code), `sapiqo-data/` (data), course folders
- [ ] Document root is `courses/sapiqo/public/`
- [ ] `install.sh` / `install.ps1` run (or manual setup) — admin can sign in
- [ ] `sapiqo-data/` writable by the web server user
- [ ] Drop-in course appears after Rescan; removing its folder makes it unavailable
- [ ] HTTPS enabled; a video plays with captions (206 range); a badge downloads
- [ ] Public splash (`/`) loads logged-out
- [ ] Security: `/courses/sapiqo-data/...` and `/courses/sapiqo/...` return 403
- [ ] To upgrade: replace the `courses/sapiqo/` folder only

## Security

Reviewed against the **OWASP Top 10**. The app applies the following by default.

### Built-in protections

- **Strict Content-Security-Policy** (`app/security.php` → `send_security_headers()`):
  `script-src 'self' 'nonce-<per-request>'` — **no `'unsafe-inline'` for scripts**, so
  injected scripts cannot run. All UI behaviors are unobtrusive JS (`public/assets/js/app.js`
  + nonce'd page scripts). Also sent: `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, and
  `Strict-Transport-Security` (HTTPS only). (`style-src` still allows inline styles —
  low risk; CSP nonces don't cover inline `style=` attributes.)
- **Output encoding + HTML sanitizing (XSS)**: all user data is HTML-escaped; the visual
  editor's rich text is sanitized (scripts, event handlers, and non-HTTPS iframes removed).
  Uploaded **SVG/HTML is served sandboxed** (`Content-Security-Policy: sandbox`) so it can't
  execute in the site's origin.
- **SQL**: 100% PDO **prepared statements** (no string-built queries; the one dynamic column
  list is a fixed whitelist).
- **SSRF**: every server-side URL fetch (SSO avatars, course image mirroring, badge-from-URL)
  goes through `safe_fetch()`, which blocks loopback/private/link-local/cloud-metadata hosts,
  refuses non-HTTP(S) schemes, doesn't follow redirects, and caps the response size.
- **Archive imports** (`.tar`/`.imscc`/SCORM/OneRoster) and the self-updater validate entries
  against Zip/Tar-Slip before extraction (PharData also normalizes traversal).
- **Access control**: every sensitive route is guarded (`require_admin` / `require_login` /
  `require_content_access`); badges/certificates/transcripts are owner-or-admin only;
  impersonation can't target another admin; the course file server is path-confined
  (realpath + prefix check + traversal / private-folder denial).
- **Sessions**: HttpOnly + SameSite=Lax cookies, `Secure` automatically over HTTPS,
  `session.use_strict_mode` + `use_only_cookies`, and session ID regenerated on login
  and on privilege change (impersonation).
- **CSRF**: every state-changing POST validates a per-session token (`csrf_check()`). The
  LTI 1.3 OIDC endpoints use DB-backed state/nonce instead (they're cross-site by design).
- **Login throttling** (`login_*` config): per-account and per-IP lockout over a sliding
  window. Tune `login_max_per_account`, `login_max_per_ip`, `login_window_seconds`.
- **Password reset tokens**: single-use, 1-hour, only the SHA-256 hash is stored.
- **Uploads**: extension allow-list, size cap (`max_upload_mb`), randomized filenames;
  staged outside the web root and served via PHP (never executed — media is static).
- **Content gating**: quiz answer keys are stripped from learner course data (server-side
  grading); signed-out visitors get only a syllabus preview (no media).
- **Audit log** (`/admin/audit`, CSV export): logins, user/role/access changes, enrollment,
  course publish/hide, imports, impersonation, software updates, and backups.
- **Error display**: off by default (`'debug' => false`); errors are logged, never shown.
- **No third-party runtime dependencies** (no Composer/npm) — minimal supply-chain surface.

### Pre-launch hardening checklist (operator)

- [ ] Serve over **HTTPS** and redirect HTTP→HTTPS (enables Secure cookies + HSTS).
- [ ] **Change the default admin password** created during install; use a strong one.
- [ ] Keep `sapiqo-data/` (DB, `config.local.php`, LTI keys, backups) **outside the web
      root** and unreadable by others (`chmod 750`, owned by the web user).
- [ ] Restrict who holds the **Administrator** role — the in-browser software updater
      executes uploaded code by design (admin-only). Use **Course developer** / **Group
      manager** for delegated staff instead.
- [ ] Behind a proxy/load balancer, set `'trusted_proxies'` so client IPs (throttling +
      audit) are read from `X-Forwarded-For` only from trusted hops.
- [ ] Leave `'debug' => false` in production.
- [ ] Configure **mail** if you want pre-expiry reminder emails to send.
- [ ] Schedule the expiry sweep and backups (below), and copy backups off-box.
- [ ] Smoke test: `/courses/sapiqo/...` and `/courses/sapiqo-data/...` return **403**; a
      video plays with captions (HTTP 206 range); a badge + certificate download.

### Reverse proxy / TLS
Terminate TLS at your web server or proxy and redirect HTTP→HTTPS. Keep the document
root at `public/`; the data root (`sapiqo-data/`) must stay outside the web root
(the default layout already ensures this).

## Scheduled jobs (cron)

- **Enrollment expiry + reminders** — set a per-course lifetime in the visual editor's
  **⚙ Settings** (or Admin → Courses). Run the sweep daily to soft-unenroll learners past
  their window (**badges, certificates, CPE, and transcript are kept**) and to send
  **graduated pre-expiry reminders** — by default 30, 7, and 1 day before (configurable in
  Admin → Settings), each sent once, by email + in-app notification when mail is configured:
  ```cron
  0 2 * * *  php /path/to/courses/sapiqo/bin/expire.php
  ```
  Admins can also run it on demand from **Admin → Courses → Run expiry sweep**.
- **Backups** — schedule `bin/backup.php` (e.g. nightly) and copy the archive off-box.

## Interoperability (import / export)

**Admin → Courses** offers several transfer formats:

- **Export / Import (native `.tar`)** — a full course (content + media) as a portable tar.
  Best for moving courses between Sapiqo installs and per-course backups. Very large
  (video-heavy) courses may exceed the PHP upload limit — for those, copy the folder into
  the courses directory and click *Rescan*, or raise `upload_max_filesize`/`post_max_size`.
- **Export `.imscc`** — IMS **Common Cartridge 1.1**: lessons become HTML web-content,
  quizzes become QTI 1.2 assessments, media is bundled. Import this into **Canvas,
  Blackboard, Moodle, or Sakai**.
- **Import `.imscc`** — bring a Common Cartridge exported from Canvas/Sakai/Moodle/
  Blackboard into Sapiqo. The manifest organization becomes modules/lessons, web
  content becomes lesson HTML (referenced images are bundled into `media/`), and QTI
  multiple-choice becomes gated quizzes. The result flash lists any unsupported item
  types (discussions, LTI, proprietary interactives) that were skipped.
- **Import LearnDash** (`.json`) — a WordPress/LearnDash course export. **Admin →
  Imports → LearnDash export** (no Python needed; it's a native PHP importer).
  Lessons are bucketed into modules by their number prefix (`1.1`, `1.2`, …) and
  module titles come from the export's sections; Gutenberg block markup is cleaned;
  Vimeo embeds become responsive players (each Vimeo link is kept in the lesson's
  `videos[]`). Tick **Mirror images locally** to download referenced images into the
  course so it serves with no external requests. New course appears in the catalog
  immediately. (The CLI `scripts/build.py` does the same and additionally supports a
  `videos.json` MP4 map for self-hosting.)
- **Self-hosting the videos** — a JSON export references Vimeo, not files. To serve
  MP4s locally instead: drop them in `content/<slug>/media/videos/<vimeo_id>.mp4`,
  add a `videos.json` (`{"by_vimeo_id": {"<id>": "media/videos/<id>.mp4"}}`), and
  Rescan. build.py then emits an HTML5 `<video>` for each mapped id.

Fidelity note: Common Cartridge round-trips structure, pages, files, and basic
quizzes. Vendor-specific features outside the standard won't map and are reported.
