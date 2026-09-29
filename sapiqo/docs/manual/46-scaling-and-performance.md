# Scaling & performance

**Audience:** administrator / operator
**Where:** `sapiqo-data/config.local.php`, your PHP/web-server/DB config, and
`DEPLOYMENT.md` → "6a. Scaling & performance (large deployments)".

## What it is

Sapiqo is pure PHP + PDO with no per-request framework overhead, so a single
well-tuned server (roughly 4–8 vCPU, 8–16 GB RAM) comfortably serves about 5,000
accounts with a few hundred concurrently-active learners. The write-heavy paths are
enrollments, quiz submissions, and forum posts; nearly everything else is cacheable
reads. This page summarizes the levers, ordered by impact; the authoritative,
copy-pasteable config lives in `DEPLOYMENT.md` §6a.

## How to use it

Work down this list — the first three matter most.

### 1. Use MySQL/MariaDB, never SQLite, in production

SQLite takes a single global write lock, so every progress save, quiz submit, and
forum post serializes into one queue and stalls under concurrency. The sample
`config.local.php` already defaults to `db_driver => 'mysql'`; keep it. SQLite is
fine only for a personal/offline install. Tune the DB for your working set
(`innodb_buffer_pool_size` ~60–70% of RAM on a dedicated DB box, `max_connections`
a little above your PHP-FPM worker count, `innodb_flush_log_at_trx_commit = 2` for
faster writes). Exact starting values are in `DEPLOYMENT.md` §6a.

### 2. Enable OPcache

Without OPcache, PHP re-parses every `.php` file on every request; turning it on is
typically a 3–5× throughput gain — the single biggest lever. Enable
`opcache.enable=1` with generous `opcache.memory_consumption` and
`opcache.max_accelerated_files`, plus a realpath cache. In production you can set
`opcache.validate_timestamps=0` for maximum speed, but then you must reload PHP
after each deploy/update so new code is picked up.

### 3. Run PHP-FPM and size the pool

Serve via PHP-FPM behind nginx/Apache — never `php -S`, which is single-threaded and
dev-only. Budget roughly 40–60 MB per worker and size `pm.max_children` to available
RAM (≈ RAM-for-PHP ÷ 50 MB). Set `pm = dynamic` with sensible
start/min/max spare servers. See `DEPLOYMENT.md` §6a for a pool template.

### 4. Offload media transfers to the web server

By default Sapiqo streams course media through a PHP worker (`app/serve.php`), which
ties that worker up for the whole download — costly when you ship several GB of
video. Enable the built-in offload so the web server pushes the bytes and the worker
frees immediately (auth + path checks still run in PHP first). Set **exactly one**
option in `config.local.php`:

- **nginx:** `'x_accel_redirect' => '/__protected_courses'`, then add an `internal`
  location aliased to your `courses/` directory. Sapiqo emits
  `X-Accel-Redirect: /__protected_courses/<rel-path>` and nginx serves the file.
- **Apache / lighttpd:** install `mod_xsendfile`, set `'x_sendfile' => true`, and
  whitelist the courses dir with `XSendFilePath`. Sapiqo emits
  `X-Sendfile: <absolute-path>`.

Sandboxed uploads (SVG/HTML) are **never** offloaded, so their protective CSP
headers always apply. Offload is also skipped for POST requests.

### 5. Cache static assets + use a CDN

The reader shell, JS, CSS, and images are highly cacheable. `serve.php` already
sends `Cache-Control: public, max-age=3600` (and supports HTTP range requests so
video seeking works). Add gzip/brotli and long-lived caching for `/assets/` at the
web server, or front the whole site with a CDN — the media offload above pairs well
with a CDN origin-pull.

### 6. Sessions (handled) and multi-node

PHP locks the session file for the duration of a request. Sapiqo already calls
`session_write_close()` in `serve.php` **after** all auth/gating decisions but
**before** streaming media, so one learner's many parallel media/image requests
don't serialize behind each other — a big perceived-speed win at scale. For a single
app server that's all you need.

To run more than one app server behind a load balancer, make the tier stateless:

- **Sessions** → a shared store (Redis/Memcached) via `session.save_handler`, or
  enable sticky sessions on the load balancer.
- **`sapiqo-data/`** (uploads, badges, branding, LTI keys) → shared storage (NFS or
  an S3-compatible mount) so every node sees the same files.
- **Database** → all nodes point at the same MySQL/MariaDB.

### 7. Database indexes (automatic)

The hot tables are indexed in both the per-user and per-course directions. The
course-direction indexes used by the completions/gradebook/roster reports are
created automatically on upgrade by `db_ensure_indexes()` (in `app/db.php`) — it adds
`idx_enroll_course`, `idx_progress_course`, `idx_quiz_course`, and `idx_badges_course`
if missing, on both SQLite and MySQL. No manual step is required. Large admin lists
are also paginated so a big roster never renders all at once.

## Options & behavior

| Config key (`config.local.php`) | Effect |
|--------------------------------|--------|
| `db_driver` | `mysql` (production) or `sqlite` (personal/offline only). |
| `x_accel_redirect` | nginx internal-redirect prefix; enables media offload. Empty = off. |
| `x_sendfile` | `true` for Apache/lighttpd `mod_xsendfile` offload. `false` = off. |
| `trusted_proxies` | Trust `X-Forwarded-For` from these hops (correct client IPs behind a balancer). |

Enable only one of `x_accel_redirect` / `x_sendfile`, matching your web server.

## How it works

`serve.php` runs all auth and content-gating checks first, then calls
`session_write_close()` to release the session lock before it streams. If an offload
key is set (and the request isn't a sandboxed type or a POST), it sends the
appropriate `X-Accel-Redirect` / `X-Sendfile` header and exits, handing byte-pushing
to the web server; otherwise it streams the file itself with HTTP range support.
Index creation happens through `db_ensure_indexes()`, invoked during the schema
bootstrap so upgrades pick up the course-direction indexes with no operator action.

## Tips & gotchas

- **Never ship production on SQLite.** The global write lock is the classic
  concurrency stall.
- **Enable exactly one offload option**, and make the nginx `alias` (or
  `XSendFilePath`) point at your real `courses/` directory with the trailing slash
  nginx expects.
- **`opcache.validate_timestamps=0` requires a PHP reload after every code update**
  (including the in-browser updater) — otherwise you'll keep running the old code.
- **Multi-node needs all three**: shared sessions, shared `sapiqo-data/`, and a
  shared database. Missing any one causes inconsistent behavior between nodes.
- **Set `trusted_proxies`** behind a balancer so throttling/audit see real client
  IPs (see the security page).
- Sandboxed SVG/HTML is intentionally excluded from offload and CDN — don't try to
  route it around PHP.

## Related

- `DEPLOYMENT.md` §6a — the authoritative, copy-pasteable config for all of the above.
- `45-security.md` — session hardening, `trusted_proxies`, sandboxed uploads.
- `44-software-updates.md` — reload PHP after updates when timestamps are disabled.
