# Changelog

## 1.13.1 — October 30, 2026

- Open every lesson in administrator/course-author previews without paid enrollment or learner-order locks. Learners retain sequential access checks.
- Label preview mode, avoid recording preview progress, and version the reader script so refreshed course tabs receive the fix.
- Bundle the runtime reader in signed code updates; retain matching shared assets for standalone course exports.
- Cover administrator Open links, late-module navigation, learner restrictions and unchanged progress in HTTP and browser regressions.

## Unreleased — security

- Stop group managers from resetting course-instructor passwords.
- Return enrollments and badges from `GET /api/v1/users/{id}` only when the API
  key also has `enrollments:read` / `badges:read`.
- Write the LTI private key 0600 in a 0700 folder, and tighten existing keys.
- Accept only update packages signed with a trusted Ed25519 release key, and
  check every file against the signed manifest. `bin/build-update.php --key=`
  signs; `--keygen=` creates a key. Own builds: add the public key to
  `update_trusted_keys`. `allow_unsigned_updates` exists only in
  `config.local.php`.
- Ask for the password (or an MFA code) again, within 10 minutes, before
  software updates, rollback, backup download, API keys and LTI platform
  registration.
- Bind LTI launches to the browser that started them (login CSRF).
- Lock MFA code entry after 20 wrong codes in 24 hours and notify the owner.
- Neutralize spreadsheet formulas hidden behind leading spaces or quotes in CSV
  exports, and sandbox SVGs opened from admin uploads.
- Join tar `prefix` fields only for real ustar headers when checking archives.
- PHP2Shell (CVE-2026-109618/-109619): turn off `session.upload_progress`
  (unused) in every deployment path. Preflight now reports unpatched PHP, and the
  Docker build refuses a vulnerable base image. Update PHP to 8.2.35, 8.3.36,
  8.4.27, 8.5.12 or newer.

## 1.13.0 — 2026-09-30

- Deliver complete backups/restores, MFA, session revocation, explicit identity
  linking, scoped API keys and isolated active-content shells.
- Add readiness guidance, templates, publication checks and next activity.
- Add assignments, rubrics, protected attachments, instructor grants, cohort
  schedules, late rules, deadline reminders and actionable reports.
- Fix MariaDB quiz contention and reject email header injection.
- Expand HTTP/browser/concurrency/recovery/upgrade checks and CI environments.
- Publish the roadmap, compatibility evidence and pilot protocol.

See [release verification and limits](docs/RELEASE_1.13.md). Existing credentials
are retained; legacy API keys keep access until rotated. Update shared reader
assets alongside code. Custom imported root-shell scripts are replaced by trusted
shells; the SCORM sandbox supports a basic completion bridge, not full conformance.

## 1.12.1 — 2026-09-29

Initial public GitHub release of Sapiqo LMS by Miguel Guhlin.

- Add project website, repository documentation, CI, and GitHub Pages deployment.
- Publish under CC BY-SA 4.0 with original third-party font notices.
- Harden course access, grading, progress, CSRF, SSO/LTI identity handling,
  archive imports, remote fetches, backups, resets, and installation.
- Add isolated security/HTTP regression tests and a backup/restore check.
- Exclude private runtime state and local configuration from publication.

See [the code audit](docs/CODE_AUDIT.md) for findings and remaining limitations.
