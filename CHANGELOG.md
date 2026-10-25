# Changelog

## Unreleased — security

- Stop group managers from resetting course-instructor passwords.
- Return enrollments and badges from `GET /api/v1/users/{id}` only when the API
  key also has `enrollments:read` / `badges:read`.
- Write the LTI private key 0600 in a 0700 folder, and tighten existing keys.
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
