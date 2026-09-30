# Operating Sapiqo LMS 1.13

© 2026 Miguel Guhlin · CC BY-SA 4.0

## Install and prepare

Serve only `sapiqo/public/` on a PHP server. GitHub Pages is the introduction,
not the LMS. Use HTTPS and set the canonical `public_url` in the persistent
`config.local.php`. Run `php installer/preflight.php`, then use
Administration → Installation readiness. Configure branding and certificates,
send an email test from Mail, enable administrator MFA, create a sample course,
and complete it with a separate learner account before inviting a cohort.

New features are additive. Existing gradebook assessments remain scores-only
until a new submission assignment is created. Existing API keys retain legacy
full access; rotate them using explicit scopes. Existing credentials remain on
transcripts when requirements or submissions change.

## Complete backup and restore

```bash
php sapiqo/bin/backup.php /private/backup-directory
# Stop the PHP application and scheduled writers before restoring.
php sapiqo/bin/restore.php /private/backup-directory/backup.tar.gz --force
```

Format-2 backups include the database snapshot/dump, course content, uploads,
private configuration and keys (including `data/mfa.key`). Files have SHA-256
integrity records checked before restore writes. These hashes detect corruption;
they are not signatures proving the backup's author. Backups contain passwords,
private keys and personal data: protect their storage and transport.

SQLite paths outside the data root are captured as `db/snapshot.sqlite` and
restored to the destination installation's configured SQLite path. MySQL dumps
use a temporary private options file so credentials do not appear in command
arguments. MySQL restore failure returns a nonzero exit status.

For relocation, configure the destination first and preserve its configuration:

```bash
SAPIQO_DATA=/new/private-data SAPIQO_COURSES=/new/content \
  php sapiqo/bin/restore.php /private/backup.tar.gz --force --keep-config
```

Without `--keep-config`, source configuration is restored and absolute paths,
URLs and database credentials must be reviewed before restart. Restore overwrites
matching files; it does not remove files added since capture. Prefer an empty
destination for an exact recovery. Stop writers for a consistent point-in-time
capture of database plus files; the database snapshot alone does not serialize
concurrent course edits. Legacy data-only archives remain supported and do not
restore courses. Application code is distributed separately.

Run `python3 tests/recovery.py` for relocation and corruption checks. Run
`python3 tests/upgrades.py` to reproduce the 1.12.1 → 1.13.0 additive migration
and code rollback on a temporary installation.

## Updates

Back up data and courses before upgrading. Stop writers; deploy the complete
release to a staging installation first. Preserve `sapiqo-data/`, `content/`
course directories and local configuration; update shipped shared reader assets
from `content/assets/` along with application code. The in-app updater replaces
application code only and does not update the separate shared reader assets.

Schema 22 adds columns/tables without deleting existing records. The tested
rollback restores 1.12.1 application code while leaving additive schema changes.
Updates still copy files individually and may leave obsolete files. They are not
an atomic deployment system or a guarantee that arbitrary future migrations can
be rolled back. Use a maintenance window and keep a full recovery archive.

## Account security

Profile → Account security supports six-digit SHA-1 TOTP (30-second period),
replay protection and ten single-use recovery codes. Enter the setup secret into
your authenticator; confirm its code before activation. Save recovery codes when
shown. MFA secrets are encrypted using the persistent `mfa.key`; restore that
key together with the database. A password reset does not disable MFA.

Security changes require the current password or an unused MFA/recovery code.
Accounts without a local password require a fresh provider sign-in within five
minutes. Additional SSO identities are linked only after authenticating the
current account and the provider. An identity already belonging to another
account cannot be linked. Linking LTI accounts to SSO is not an automatic merge
of separate transcript records.

Password changes revoke existing sessions. Sign out all sessions also revokes
the current browser. MFA is opt-in; enable it for every administrator before a
production pilot. An operator recovering a lost MFA key/account should use a
verified private recovery process, not turn off verification through a public
endpoint.

## Imported active content

The application generates trusted reader/player shells instead of executing a
package-supplied root HTML file in the LMS origin. SCORM documents run with an
opaque browser origin (`sandbox allow-scripts`), without same-origin access,
forms or network fetches. Their local shim exposes a narrow completion/score
message bridge. The privileged player validates the message source, opaque
origin and per-launch channel. Other imported HTML and SVG remain sandboxed.

Basic single-document SCORM 1.2/2004 completion is supported by the shim. Packages
requiring XHR/fetch, popup navigation, persistent suspend-data, nested SCO
launches or complete SCORM sequencing may need adaptation. Test each actual
package; do not claim full SCORM conformance. A separately hosted content origin
with a fuller bridge remains an interoperability follow-up. Custom imported
root-shell scripts are intentionally replaced; standalone exported packages
retain their files.

## Deadline reminders

Run this daily using the installation's environment and canonical public URL:

```bash
php sapiqo/bin/deadline-reminders.php
```

It creates one in-app reminder per learner/assignment/deadline during the three
days before a due date; optional email complements the in-app reminder. Failed
email delivery is not retried automatically. Keep the existing enrollment-expiry
job as well. Do not install cron entries pointing at this source checkout until
you have selected and configured the actual LMS installation.

## Demonstration and pilots

Sapiqo is distributed for self-hosting. Run the local demonstration with SQLite
using `installer/install.sh` and `installer/serve.sh`, or configure your own
MariaDB server. The project's production publication consists of the GitHub
Pages website and release downloads; it does not include a centrally hosted LMS.

A hosted demo requires a reachable PHP host, separate data/content directories,
nonproduction credentials and a scheduled reset from disposable fixtures. Never
reset an organizational installation. No public demo server or production PHP
host was configured in this repository; deployment instructions do not constitute
a deployed demo. Use the pilot protocol before making institutional support or
accessibility-conformance claims.
