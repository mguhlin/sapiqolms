# Security overview

**Audience:** administrator / operator
**Where:** Built into the app (`app/security.php`, `app/helpers.php`); operator
tasks in `DEPLOYMENT.md` → "Security". Audit log at `/admin/audit`.

## What it is

This page is an operator-facing summary of the protections Sapiqo applies by
default, and — just as important — the few things **you** must still do to run it
safely. Sapiqo was reviewed against the OWASP Top 10 and has no third-party runtime
dependencies (no Composer/npm), which keeps the supply-chain surface small.

## How to use it

There is nothing to switch on for the built-in protections — they apply on every
request. Your job is the hardening checklist at the end: serve over HTTPS, change
the default admin password, keep `sapiqo-data/` out of the web root, restrict the
Administrator role, and set `trusted_proxies` if you're behind a load balancer.

Review the **audit log** (Admin → Account management → Audit log, `/admin/audit`,
with CSV export) periodically to see who did what.

## Options & behavior

### What protections exist

**Strict Content-Security-Policy with per-request script nonces.**
`send_security_headers()` emits a CSP whose `script-src` is `'self'` plus a
per-request nonce (`csp_nonce()`) — **no `'unsafe-inline'` for scripts**, so an
injected `<script>` cannot run. All UI behavior is unobtrusive JS plus nonce'd
inline blocks. Also sent every response: `X-Content-Type-Options: nosniff`,
`X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy` (geolocation/mic/camera off), and — over HTTPS only —
`Strict-Transport-Security`. (`style-src` still allows inline styles, which is low
risk and doesn't enable script execution.)

**SSRF-guarded outbound fetches.** Every server-side fetch of a user-influenced URL
(SSO avatars, image mirroring, badge-from-URL, LTI JWKS) goes through `safe_fetch()`,
which first calls `url_is_safe_public()`. That resolves the host and rejects
loopback, private (10/8, 172.16/12, 192.168/16), link-local (169.254/16, including
cloud metadata `169.254.169.254`), and reserved ranges, and refuses non-HTTP(S)
schemes. `safe_fetch()` additionally does not follow redirects (so a public URL
can't 302 into an internal host) and caps the response size.

**Sandboxed SVG/HTML uploads.** Because an SVG or uploaded HTML/XML can carry
scripts, `serve.php` serves those types with `Content-Security-Policy: … sandbox`
so nothing in them can execute in the site's origin (stored-XSS defense). These
sandboxed types are deliberately **never** offloaded to the web server, so their
protective header always applies.

**Archive-slip protection.** Archive imports (`.tar`/`.imscc`/SCORM/OneRoster) and
the software updater validate every entry with `archive_entries_safe()` before
extraction — any entry with an absolute path or `..` traversal aborts the operation.

**CSRF on state-changing actions.** Every state-changing POST validates a
per-session token via `csrf_check()` (`csrf_field()` in forms). The LTI 1.3 OIDC
endpoints instead use DB-backed state/nonce, because they are cross-site by design.

**Role-based access control.** Sensitive routes are guarded: `require_admin()`,
`require_login()`, `require_content_access()` (course developers),
`require_group_access()` / `require_org_access()` / `require_user_access()`
(delegated managers). Badges, certificates, and transcripts are owner-or-admin
only; impersonation can't target another admin and can't reach admin-only routes;
the course file server is path-confined (realpath + prefix check + traversal and
private-folder denial in `serve.php`).

**Hashed passwords + login throttling.** Passwords are stored with
`password_hash()` (bcrypt/Argon per PHP default) and opportunistically rehashed when
the cost changes. Login throttling (`app/security.php`) applies a sliding-window
lockout per account **and** per IP; tune `login_max_per_account` (default 5),
`login_max_per_ip` (default 15), and `login_window_seconds` (default 900). Password
reset tokens are single-use, 1-hour, and only their SHA-256 hash is stored.

**Sessions.** Cookies are HttpOnly + `SameSite=Lax`, `Secure` automatically over
HTTPS, with `session.use_strict_mode` and `use_only_cookies`; the session ID is
regenerated on login and on privilege change (impersonation).

**Secrets outside the web root.** The web/document root is `public/`. All secrets
and data — the database, `config.local.php` (DB creds, SSO client secrets), LTI
private keys, uploads, and backups — live in `sapiqo-data/`, outside the web root.
`serve.php` additionally refuses any URL path into `sapiqo/` or `sapiqo-data/`, so
code and data are never web-reachable even though they share the `courses/` folder.

**Audit log.** `/admin/audit` (with CSV export) records logins, user/role/access
changes, enrollment, course publish/hide, imports, impersonation, API-key and
software-update actions, and backups — actor, IP, target, and timestamp. Auditing
never throws (a logging failure won't break a request).

Additional defaults: all output is HTML-escaped and rich text is sanitized; SQL is
100% PDO prepared statements; uploads use an extension allow-list, a size cap
(`max_upload_mb`), and randomized filenames, staged outside the web root and served
statically (never executed); quiz answer keys are stripped from learner course data;
error display is off by default (`'debug' => false`).

### What the operator must still do

- **Serve over HTTPS** and redirect HTTP→HTTPS — this is what enables Secure cookies
  and HSTS.
- **Change the default admin password** created during install; use a strong one.
- **Keep `sapiqo-data/` outside the web root**, owned by the web user and
  unreadable by others (`chmod 750`). It holds the DB, `config.local.php`, LTI
  keys, and backups.
- **Restrict the Administrator role.** The in-browser software updater executes
  uploaded code by design and is admin-only; use Course developer / Group manager
  for delegated staff instead.
- **Set `trusted_proxies`** when behind a proxy/load balancer, so client IPs (used
  for throttling and audit) are read from `X-Forwarded-For` only from trusted hops.
- **Leave `'debug' => false`** in production.
- **Smoke test:** `/courses/sapiqo/...` and `/courses/sapiqo-data/...` return **403**.

## How it works

Baseline headers are set by `send_security_headers()` on every response; the CSP
script nonce comes from `csp_nonce()` (one value per request, reused in each nonce'd
inline block). Outbound fetches funnel through `safe_fetch()` →
`url_is_safe_public()`. Uploads/updates funnel through `archive_entries_safe()`.
CSRF is `csrf_token()`/`csrf_check()`; RBAC is the `require_*` guards in
`app/auth.php`; throttling and the audit trail are in `app/security.php`.

## Tips & gotchas

- **HTTPS is load-bearing.** Without it, cookies aren't marked Secure and HSTS is
  not sent (this is intentional so plain-HTTP LAN/dev installs still work).
- **Behind a proxy, always set `trusted_proxies`.** Otherwise throttling and audit
  IPs reflect the proxy, and an unset trust list means `X-Forwarded-For` is ignored.
- **Admin = full control, including code execution** via the updater. Grant it
  sparingly.
- **Sandboxed SVG/HTML won't be CDN/X-Sendfile offloaded** — that's deliberate;
  don't try to bypass it for those types.
- **`style-src` allows inline styles** by design; CSP nonces cover scripts, not
  inline `style=` attributes. This is a documented, low-risk trade-off.
- Copy backups off-box and verify a restore occasionally.

## Related

- `44-software-updates.md` — why the updater is admin-only; slip checks on packages.
- `41-single-sign-on.md` / `43-api-keys-and-rest-api.md` — where secrets/tokens live.
- `24-impersonation.md` — the impersonation guardrails referenced above.
- `DEPLOYMENT.md` → Security — the full checklist and reverse-proxy/TLS guidance.
