# Sapiqo LMS 1.13.0 verification

© 2026 Miguel Guhlin · CC BY-SA 4.0 · September 30, 2026

This implements the first substantial delivery across the planned roadmap.
It does not claim the real-provider, independent accessibility or
organizational-pilot acceptance criteria have been completed. Users supply their
own host; the local demo uses SQLite and MariaDB is an optional configured backend.

## Delivered

- Complete database/data/course backups, integrity checks before restore writes,
  relocation with custom SQLite paths and `--keep-config`, private MySQL options.
- TOTP MFA, encrypted persistent secrets, one-use recovery codes, replay
  protection, global session revocation, password-change revocation and verified
  SSO linking. Additional and original provider identities cannot be stolen.
- Scoped API keys; new keys default to course read. Existing keys retain legacy
  access until rotated, with a visible notice.
- Trusted generated reader shells and an opaque-origin sandboxed SCORM player
  with a bounded, source/channel-validated completion bridge.
- Readiness guide, three course templates, publication checks and next activity.
- Text/file assignment drafts and resubmissions, reusable rubrics, instructor
  feedback, gradebook integration and required-assignment certificate gating.
- Explicit course instructor grants; content developers cannot read student
  submissions merely because they can author content. Attachments are private.
- UTC-normalized cohort schedules, late policies, daily deadline reminders and
  actionable incomplete-learning reports.
- Focused account, learning and assignment services/routes; no wholesale rewrite.
- SMTP header injection rejection and a MariaDB quiz-locking fix found by stress
  tests during this work.

## Executed locally

- 84 security regression checks and 39 real HTTP checks.
- Database/course backup restore, custom SQLite relocation and tampered backup
  rejection before destination writes.
- Eight independent PHP workers contest each reset token, quiz limit,
  authenticator code and assignment revision: exactly one success on SQLite and
  MariaDB.
- Disposable MariaDB 11.8.6: schema, assignment grading/completion, dump/restore.
- Chromium: desktop/mobile roadmap, mobile account/assignment screens, author →
  learner → grade → certificate, private workflow boundaries, sandboxed SCORM
  parent access denial, MFA challenge/recovery and session revocation.
- Local SMTP protocol/header/dot-stuffing fixture; no email sent externally.
- Real 1.12.1 code upgraded to 1.13.0 in a temporary installation, migrated to
  schema 22, then code rolled back; users, configuration and courses preserved.
- PHP syntax, shipped JavaScript syntax, shell syntax and Python checks.

All six [GitHub Actions release jobs](https://github.com/mguhlin/sapiqolms/actions/runs/36725641715)
passed on September 30, 2026: PHP 8.1/8.3/8.4 regression, Chromium, MariaDB,
and Docker/Apache build/login/readiness. The verified release commit is
`0285f0a5326c5e8cc4ffd7fc6169affea803b8e9`. Pages deployment succeeded.
The [roadmap](ROADMAP.md#current-release-and-remaining-work) lists remaining work.

## Material limits

Read [Operations](OPERATIONS.md), [Compatibility](COMPATIBILITY.md) and the
[Pilot protocol](PILOT_PROTOCOL.md). In particular: SCORM's basic shim does not
implement full sequencing/suspend-data/network support; imported custom root
shell scripts are replaced; shared reader assets require updating with the
release; file-by-file updates are not atomic; MFA is opt-in; attachments are not
antivirus-scanned; users operate their own PHP installation.
Real SSO/LTI credentials, SMTP TLS delivery, assistive-technology review,
representative package round trips and organizational pilots remain unverified.
