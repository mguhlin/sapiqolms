# Audit log

**Audience:** administrator
**Where:** Audit log — `/admin/audit`

## What it is
The **Audit log** is an append-only record of privileged actions taken in Sapiqo,
kept for accountability (for example FERPA / state data-privacy compliance). Every
entry records **when** (UTC), **who** (actor email), **what** (action), the
**target**, a **detail** string, and the requester's **IP address**. Auditing is
best-effort and never blocks the action it is recording.

## How to use it
1. Go to the **Audit log** (`/admin/audit`).
2. The table lists the most recent entries, newest first.
3. **Search** with the box (matches actor email, target, or detail) and/or pick a
   specific action from the **All actions** dropdown, then choose **Filter**.
4. Choose **Export CSV** to download the currently filtered entries.

## Options & behavior

**Columns.** When (UTC), Actor, Action, Target, Detail, IP. The Action shows as a
pill; a missing actor or target renders as an em dash.

**Filters.**
- **Search (`q`)** — substring match across actor email, target, and detail.
- **Action** — the dropdown is populated from the distinct actions actually
  present in the log, so it only offers actions that have occurred.

**Display cap.** The screen shows up to the **500 most recent** matching entries
(noted at the bottom of the page).

**CSV export.** **Export CSV** downloads `sapiqo-audit.csv` and **keeps your
current search and action filters**. Columns: When (UTC), Actor, Action, Target
type, Target, Detail, IP.

**What gets recorded.** Actions are logged across the app. Representative examples:

- **Sign-in & sessions:** `login.success`, `login.fail`, `login.blocked`,
  `logout`.
- **Passwords:** `password.reset_requested`, `password.reset_done`,
  `password.admin_reset_link`.
- **Accounts & roles:** `user.register`, `user.edit`, `user.role`,
  `account.course_dev`, and manager grants
  (`account.manager.assign` / `.perms` / `.remove`).
- **Enrollment:** `user.enroll`, `user.disenroll`, `user.progress_set`,
  `enrollments.expire`, `course.expiry`.
- **Impersonation:** `user.impersonate.start`, `user.impersonate.stop`.
- **Courses:** `course.editor_new`, `course.editor_save`, `course.publish`,
  `course.rescan`, `course.settings`, `course.cpe`, `course.prereq`,
  `course.sequential`, `course.forum`, and resource upload/delete.
- **Imports:** `course.import`, `course.import_cc`, `course.import_scorm`,
  `course.import_learndash`, `course.import_split`, `users.import`,
  `users.import_oneroster`, `users.bulk`.
- **Exports & backups:** `course.export`, `course.export_cc`,
  `course.export_json`, `course.export_split`, `users.export`, `backup.download`.
- **Organizations & groups:** `org.create`, `org.delete`, `org.subscribe`,
  `org.manager.assign`; `group.create`, `group.delete`, `group.subscribe`,
  `group.add_manager`.
- **Settings & integrations:** `settings.update`, `settings.theme_preset`,
  `api_key.create`, `api_key.revoke`, `lti.platform.create`, `lti.platform.delete`,
  `lti.launch`.
- **Software updates:** `app.update`, `app.update.build`, `app.update.rollback`.

(The exact set grows with the software; the dropdown always reflects what has
actually happened on your install.)

## How it works
Each audited action calls a single logging helper that inserts a row into the
`audit_log` table with the acting user (resolved from the session), the action
name, an optional target type/id, a detail string (arrays are stored as JSON), the
client IP, and a UTC timestamp. The helper is wrapped so a logging failure is
written to the PHP error log rather than interrupting the user's request. The
screen and the CSV both read from this table via the same filtered query, newest
first.

## Tips & gotchas
- **The log is append-only from the UI** — there is no edit or delete button.
  Treat it as your tamper-evident record.
- **Times are UTC.** Convert to local time when correlating with other systems.
- **The 500-row view is a display cap, not a data cap** — older entries remain in
  the database; use search/action filters (and the CSV export) to reach them.
- **The action dropdown only lists actions that have occurred**, so a brand-new
  install shows a short list that fills out over time.
- **Impersonation is fully logged** (`user.impersonate.start` / `.stop`), so
  actions taken while impersonating are attributable.
- **Use the Detail column to see specifics** — for example CPE hours set,
  prerequisite ids, sequential/forum mode, or the imported course slug.

## Related
- Impersonation (`24-impersonation.md`)
- Exports (`32-exports.md`)
- Backups (`34-backups.md`)
- Users and roles (`20-users-and-roles.md`)
