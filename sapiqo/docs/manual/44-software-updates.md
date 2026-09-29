# Software updates

**Audience:** administrator / operator
**Where:** Admin → Settings & integrations → **Software updates** (`/admin/update`)

## What it is

A WordPress-style in-browser updater for the Sapiqo **code**. You upload an update
package (`.tar.gz`) and Sapiqo replaces its own code in place — after taking an
automatic backup so you can roll back. It updates **only** the code (the `sapiqo/`
folder). The persistent data root (`sapiqo-data/` — database, uploads, badges,
branding, LTI keys, local config) and course content (`content/`) are never
touched, so upgrading is effectively "replace the `sapiqo/` folder."

The current version is defined by `APP_VERSION` in `app/version.php` (at time of
writing, `1.11.0`). The data schema is versioned separately (`DB_SCHEMA_VERSION`);
any needed migrations run automatically on the next page load after an update.

## How to use it

Open **Admin → Software updates**. The page has four cards.

### This install

Shows the installed version and data schema version. If the code folder is **not
writable** by the web server, a warning appears and the update/rollback controls are
disabled — updates can't be applied through the browser until permissions are fixed
(or you update the folder manually).

### Apply an update

1. Click into **Update package** and choose a `.tar.gz` package.
2. (Optional) Tick **"Apply anyway, even if it isn't newer"** to reinstall the same
   version or intentionally downgrade.
3. Click **Upload & apply update** and confirm the prompt.

Sapiqo validates the package, backs up the current code, applies it, and shows a
result message (e.g. "Updated from 1.11.0 to 1.12.0. A backup of the previous code
was saved."). Reload; migrations run on the next page load.

### Create an update package

Click **Download update package** to build a distributable `.tar.gz` from *this*
install's current code. Hand it to another Sapiqo install and apply it there, or
keep it as a code snapshot. The same package can be built from the command line with
`php bin/build-update.php`.

### Backups & rollback

Every applied update first saves the previous code here. To undo an update, click
**Roll back** next to a backup and confirm; it restores that code (most recent is
marked). Reload to run on the restored code.

## Options & behavior

**What a valid package looks like.** A package is a `.tar.gz` (`.tgz`/`.tar` also
accepted) containing an `update.json` manifest (`name: sapiqo-update`, a `version`,
etc.) and a `code/` tree with the real code (verified by the presence of
`code/app/config.php` and `code/public/`). A package missing the manifest, with the
wrong name, or without the code tree is rejected with a clear message.

**Version check.** `apply_update_package()` compares the package `version` to the
installed `APP_VERSION` with `version_compare()`. If the package is not newer, it is
refused **unless** you tick "apply anyway" (the `force` option) — which allows
reinstall or downgrade.

**Automatic backup.** Before applying, `backup_code()` archives the current code to
`sapiqo-data/data/updates/backup-<version>-<timestamp>.tar.gz`. Because backups live
in the persistent data root, they survive the code replacement itself. Backups are
listed newest-first.

**How code is replaced.** The package's `code/` tree is merged over the live code
(`_copy_tree` — it adds and overwrites, never deletes). Files removed in a new
release therefore linger, but nothing in use is lost. The data root, content folder,
and `config.local.php` are outside `code/` (and `config.local.php` is explicitly
excluded from packages), so none of them is affected.

**Excluded paths.** `data`, `.git`, and `node_modules` are never packaged, backed
up, or overwritten (`update_excluded_top()`).

**Safety on extract.** Uploaded archives are checked against Zip/Tar-Slip
(`archive_entries_safe()`) before extraction — an archive whose entries would escape
the extraction directory is rejected.

**Rollback.** `rollback_update()` restores a chosen backup (or the latest by
default) by extracting it over the code root. Backup filenames are validated against
a strict pattern before use.

**Auditing.** Applying (`app.update`), building (`app.update.build`), and rolling
back (`app.update.rollback`) all write audit-log entries.

## How it works

The GET `/admin/update` route (`require_admin`) renders the page with the current
version, schema, backup list, and code-root writability. POST `/admin/update`
(`require_admin` + `csrf_check`) hands the uploaded temp file to
`apply_update_package()`. The updater stages the upload in a temp dir, runs the
slip check, reads and validates `update.json`, confirms the `code/` tree, enforces
the version rule (unless forced), backs up the current code, then merges the new
code over the live tree. Rollback and package-download have their own admin-only,
CSRF-protected routes.

## Tips & gotchas

- **The code folder must be web-server-writable** for in-browser updates and
  rollbacks. If it isn't, the controls are disabled — fix permissions or update the
  folder by hand. (For hardening you may prefer to keep code read-only and update
  out of band; see below.)
- **"Not newer" is refused by default.** To reinstall or downgrade, tick "apply
  anyway."
- **Removed files linger.** The merge never deletes; for a fully clean tree, replace
  the `sapiqo/` folder manually instead of applying a package.
- **Migrations run on next load**, not during the upload — reload the app once after
  updating.
- **Back up first.** The updater backs up code automatically, but it does **not**
  back up your database. Snapshot `sapiqo-data/` before major upgrades.
- **Security note:** applying a package executes uploaded code by design and is
  admin-only. Restrict who holds the Administrator role, and copy backups off-box.
- Keep at least the most recent backup available so rollback is always possible.

## Related

- [Updating the software — a step-by-step guide for administrators](47-updating-the-software-plain-language-guide.md) — the plain-language walkthrough, no jargon.
- `45-security.md` — why the updater is admin-only and how uploads are slip-checked.
- `DEPLOYMENT.md` §3a & §10 — upgrade layout and backups.
- `40-branding-and-settings.md` — settings survive updates (they live in data).
