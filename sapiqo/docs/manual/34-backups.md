# Backups

**Audience:** administrator
**Where:** Download backup — `/admin/backup`

## What it is
**Download backup** produces a single archive snapshot of all of Sapiqo's
**persistent data** — configuration, the database, badges, avatars, and uploads —
for disaster recovery or moving to a new server. It backs up the **data root**,
not the course content (courses are static files that are backed up separately).

## How to use it
1. Go to **Download backup** (`/admin/backup`), reachable from the **Imports &
   backups** area and the **Exports** card group.
2. The archive builds on the server and downloads to your browser as a single
   file named `sapiqo-backup-<timestamp>` (`.zip`, or `.tar.gz` on servers
   without the Zip extension).
3. Store the file somewhere safe and off the server. Repeat on a schedule.

There is no on-screen form — visiting the page starts the download. (A CLI
equivalent exists at `bin/backup.php` for scheduled, unattended backups.)

## Options & behavior
- **Format.** A `.zip` when PHP's Zip extension is present; otherwise a portable
  `.tar.gz`. The download is served with the matching content type and then the
  temporary copy on the server is removed.
- **What's inside.** Everything under the data root, laid out under a
  `data-root/` folder in the archive, plus a `BACKUP-INFO.txt` describing when it
  was made and which database driver was used.
- **Database consistency.** Before archiving, Sapiqo makes the database
  consistent: for **SQLite** it checkpoints the write-ahead log; for **MySQL** it
  runs `mysqldump` and adds the result as `db/dump.sql` inside the archive.
- **What's excluded.** Any prior backups (the `backups/` folder) and transient
  SQLite side files (`.sqlite-wal`, `.sqlite-shm`) are left out to keep the
  archive clean.
- **Audit.** Each download is recorded in the audit log as `backup.download`
  with the archive filename.

## How it works
The backup routine resolves the data root, walks every file beneath it (skipping
prior backups and SQLite side files), and adds them to a Zip (or, as a fallback,
a PharData `.tar.gz`). For SQLite the live database file is included directly
after a WAL checkpoint; for MySQL a `mysqldump` is embedded so the backup is
portable to a fresh database. A short `BACKUP-INFO.txt` with the creation time,
driver, and file count is written into the archive.

The **data root** is the folder Sapiqo keeps its writable data in. On a typical
deployment this is a `sapiqo-data/` directory alongside the application (its
name can be overridden with the `SAPIQO_DATA` environment variable); it holds
the database, badges, avatars,
uploads, exports staging, and configuration. That folder — not the code and not
the course content — is the real data to protect.

## Restoring or moving to a new server
1. **Stand up Sapiqo** on the target server (same or newer version).
2. **Restore the data root:** extract the archive and copy the contents of
   `data-root/` into the new install's data directory (`sapiqo-data/`). For a
   MySQL install, load `db/dump.sql` into the target database; for SQLite, the
   database file is already in the restored data root.
3. **Restore course content separately:** copy the course folders into the
   content directory (or re-import each course's `.tar` package), then open
   **Manage courses** and choose **Rescan drop-in folder** so the catalog matches
   the folders on disk.
4. **Verify:** sign in as an admin, check the course catalog, a few learner
   accounts, badges, and settings.

## Tips & gotchas
- **Back up two things, not one.** The `/admin/backup` archive covers the data
  root; **course content is not in it.** Also copy the content folder (or export
  each course as a `.tar`) so you can fully rebuild.
- **Large data roots take time and memory.** The download builds the whole
  archive before sending; on big installs run the CLI backup (`bin/backup.php`)
  on a schedule instead of the browser route.
- **Keep backups off-box.** The archive excludes previous backups by design, so
  don't rely on the server's own `backups/` folder as your only copy.
- **Match or exceed the version.** Restore into the same Sapiqo version or a
  newer one; restoring a newer backup into older code is not supported.
- **Downloads are audited.** Expect a `backup.download` entry in the audit log
  each time.

## Related
- Exports (`32-exports.md`)
- Importing courses and users (`31-importing-courses-and-users.md`)
- Course management (`30-course-management.md`)
- Audit log (`35-audit-log.md`)
