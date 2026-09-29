# API keys & the REST API

**Audience:** administrator / operator
**Where:** Admin → Settings & integrations → **API keys** (`/admin/api-keys`).
REST base path: `{your-site}/api/v1`.

## What it is

Sapiqo exposes a small token-authenticated REST API for server-to-server
integrations — SIS syncs, reporting bots, provisioning scripts. Access is granted
through **API keys** you create in the admin UI. A v1 key grants full,
admin-scoped access to the `/api/v1` endpoints, so treat a key like an admin
credential.

Keys are shown **once** at creation. Only the SHA-256 hash of the key is stored
(`app/api.php`), so a leaked database can't be used to recover live tokens.

## How to use it

### Create a key

1. Open **Admin → API keys**.
2. In **Create a key**, enter a descriptive name (e.g. "SIS sync", "Reporting
   bot") and click **Create key**.
3. The new key is displayed once in a highlighted card — **copy it immediately**.
   It won't be shown again. Keys look like `sk_` followed by 48 hex characters.

The key list shows each key's name, prefix (first 10 chars, for identification),
created date, last-used timestamp, and status (Active/Revoked).

### Revoke a key

Click **Revoke** next to any active key. Apps using it stop working immediately.
Revocation is a soft delete (`revoked_at` is set); the row remains for audit.

### Authenticate a request

Send the key as a Bearer token on every request:

```
Authorization: Bearer sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Example:

```bash
curl -H "Authorization: Bearer $SAPIQO_KEY" \
     https://lms.example.org/api/v1/courses
```

A missing or invalid key returns `401` with a JSON error and a
`WWW-Authenticate: Bearer` header.

## Options & behavior

### Endpoints (v1)

All responses are JSON; list endpoints wrap results in a `data` array.

| Method & path | Purpose |
|---------------|---------|
| `GET /api/v1/courses` | List courses (id, slug, title, total_units, active). |
| `GET /api/v1/users` | List users, paginated (`?page=`, `?per_page=`, max 200). Returns `data`, `page`, `per_page`, `total`. |
| `POST /api/v1/users` | Create a user (first_name, last_name, email, role, user_type, campus, organization, optional password). Returns `201`. |
| `GET /api/v1/users/{id}` | One user with their `enrollments` and `badges`. |
| `GET /api/v1/enrollments` | Enrollments for one user (`?user=<id>`) **or** one course (`?course=<id>`). One param is required. |
| `POST /api/v1/enrollments` | Enroll a user in a course. Identify the user by `user_id` or `email`, the course by `course_id`, `course`, or `slug`. Returns `201`. |
| `GET /api/v1/completions` | Completed enrollments (email, name, course slug, status, completed_at, badge_code), newest first, up to 1000. |
| `GET /api/v1/badges` | Badges, all or for one user (`?user=<id>`), up to 1000. |

Request bodies for POST endpoints may be JSON or form-encoded (`api_body()` reads
JSON first, then falls back to POST fields).

### Behavior notes

- **Roles on create.** `POST /api/v1/users` only honors `role: admin`; anything
  else creates a **learner**. If no password is supplied, a random one is
  generated (the account can then use password reset or SSO).
- **Idempotent-ish enrollment.** `POST /api/v1/enrollments` calls the same
  `enroll()` used by the UI; enrolling an already-enrolled user is safe.
- **Pagination.** Only `/api/v1/users` is paginated. `completions` and `badges`
  are hard-capped at 1000 rows per call.
- **Auditing.** Write actions record audit entries attributed to `api:<key name>`
  (e.g. `api.user.create`, `api.enroll`), so you can trace which key did what.
- **Last used.** Each authenticated call stamps the key's `last_used_at`.

## How it works

Every `/api/v1` route calls `require_api_key()`, which reads the `Authorization`
header (falling back to `apache_request_headers()` where needed), extracts the
Bearer token, hashes it with SHA-256, and looks up a non-revoked row in `api_keys`.
No match → `401` JSON and exit. On success the key row is returned and its
`last_used_at` updated.

Key creation (`api_key_create`) generates `sk_` + 24 random bytes (hex), stores the
SHA-256 hash plus a 10-char prefix and the creating admin's id, and returns the
plaintext once (stashed in the session flash for the one-time display).

## Tips & gotchas

- **Copy the key at creation** — it is unrecoverable afterward. If lost, revoke it
  and create a new one.
- **A v1 key is admin-scoped.** There are no per-endpoint or read-only scopes yet;
  issue keys narrowly, name them clearly, and revoke unused ones.
- **Only administrators** can create or revoke keys; those POSTs are
  CSRF-protected. The API calls themselves are stateless (no CSRF, no session).
- **Send `Authorization: Bearer`, not a query param.** The token is only read from
  the header.
- **Use HTTPS.** A Bearer token in cleartext over HTTP is exposed on the wire.
- Rotate keys periodically: create the new one, cut over the integration, then
  revoke the old one — the last-used column helps confirm nothing still uses it.

## Related

- `20-users-and-roles.md` — the roles the API can assign.
- `21-organizations-and-subscriptions.md` — org/group enrollment (UI side).
- `45-security.md` — hashed-token storage, HTTPS, audit log.
