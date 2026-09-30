# Sapiqo LMS planned roadmap

© 2026 Miguel Guhlin · CC BY-SA 4.0

## Product direction

Make Sapiqo a dependable, accessible, self-hosted LMS for educator professional
learning and small organizations. Build on course authoring, CPE/GT credits,
certificates, groups, and completion reporting. Preserve existing courses and
workflows; deliver changes in tested, reviewable increments.

This is a **planned roadmap**, not a statement that the features or independent
certifications are already available. The sequence matters more than dates;
the original 12-week estimate is illustrative. Implementation and verification
will be recorded below as work lands.

## Current release and remaining work

Updated September 30, 2026. [Version 1.13.0](https://github.com/mguhlin/sapiqolms/releases/tag/v1.13.0)
delivered the main account-security, recovery, authoring and teaching features.
All six [release verification jobs](https://github.com/mguhlin/sapiqolms/actions/runs/36725641715)
passed: PHP 8.1/8.3/8.4, Chromium, MariaDB and Docker/Apache. Complete
backup/restore, concurrency and upgrade/rollback checks passed. These completed
checks are not outstanding roadmap items.

The following work remains open, in the recommended order. Each item stays open
until its acceptance evidence is recorded; there are no promised completion dates.

| Order | Remaining work | Completion evidence / dependencies |
| --- | --- | --- |
| 1 | Accessibility review and fixes | Complete learner, author and admin tasks by keyboard; check focus, labels, errors, contrast, zoom and touch targets; perform screen-reader review against the WCAG 2.2 AA target |
| 2 | Firefox and Safari coverage | Run core account, authoring, assessment and certificate journeys; fix failures; actual Safari testing requires a suitable Apple environment |
| 3 | Representative course-package compatibility | Import/export real SCORM, Common Cartridge, LearnDash and OneRoster examples; check navigation, resources, questions, completion and re-entry; document unsupported behavior |
| 4 | Broader SCORM support and isolation review | Assess separate-origin hosting and implement or explicitly bound sequencing, suspend-data and nested/network package behavior; basic shim tests do not establish full conformance |
| 5 | Live identity and LTI interoperability | Test configured SSO linking/login/logout and LTI launch/grade passback with real providers; requires credentials and a reachable test installation; certification remains optional follow-up |
| 6 | Email and scheduled reminders | Verify SMTP TLS delivery and scheduled deadline reminders end to end; record duplicate suppression and delivery failures; requires a configured mail provider and scheduler |
| 7 | Additional hosting environments | Demonstrate installation, login, upgrades and complete recovery on nginx and Windows/IIS before claiming tested support |
| 8 | Organizational pilots and capacity | Two or three organizations complete the pilot protocol; measure install time, resume navigation, task failures, support needs and representative concurrent workload |

Sapiqo is self-hosted: users provide their own machine/server, with SQLite for
local use or their own configured MariaDB backend. GitHub Pages publishes the
website and downloads. A centrally hosted LMS or public demo is outside the
current publication scope.

Use [Compatibility](COMPATIBILITY.md) for tested boundaries and
[Pilot protocol](PILOT_PROTOCOL.md) for the remaining acceptance checks.

## Delivery plan

| Priority | Phase | Work | Acceptance criteria |
| --- | --- | --- | --- |
| P0 | Production foundation | Isolate active course content; administrator MFA; session revocation; scoped API keys; verified account linking; complete backups; supported deployment checks; focused service extraction | Security regression suite passes; database and course recovery demonstrated; environment evidence recorded |
| P1 | Excellent core experience | Operator readiness guide; course templates; publication validation; learner next activity/deadlines; accessible forms and navigation | Install → publish → enroll → assess → certify journey passes; new capabilities work by keyboard |
| P2 | Teaching workflows | Assignment submissions/resubmissions; reusable rubrics; feedback and gradebook integration; cohort deadlines and late rules; clear completion requirements; actionable reports | An instructor can deliver and assess a complete course using the new workflow |
| P3 | Verified integrations and release readiness | Provider and import compatibility matrix; concurrency checks; update/recovery checks; pilot and accessibility protocols; self-hosted distribution and local demo | Each capability has an explicit tested/not-tested status; real-provider and pilot evidence required before claiming validation |

## P0: production foundation

- Contain imported HTML/JavaScript and SCORM runtime access; use a narrow,
  validated message bridge. Document any residual browser isolation limits.
- Administrator MFA, revocation of existing sessions, least-privilege API keys,
  and explicit verified identity linking without matching accounts by email.
- Back up the database, courses, uploads, and required private configuration;
  document confidentiality, maintenance-mode restore, and rollback requirements.
- Validate Linux/PHP/SQLite first, then Docker and MySQL. Publish a truthful
  matrix for Apache/nginx, SMTP, Windows/IIS, and external identity providers.
- Extract focused services from routes as features change. Avoid a wholesale
  rewrite; preserve security boundaries with regression tests.

## P1: core experience

- Guided readiness checks for storage, database, email, public URL, and branding.
- Workshop, self-paced professional learning, and facilitated cohort templates.
- Pre-publication validation for content, identifiers, assessments, resource
  paths, completion rules, and certificate settings.
- Learner navigation that clearly shows the next required activity and deadlines.
- WCAG 2.2 AA target: keyboard, focus, labels, accessible authentication,
  assessment feedback, and usable touch targets. Automated testing alone does
  not establish conformance.

## P2: teaching workflows

- Text/file submissions, drafts, resubmissions, instructor feedback and status.
- Reusable rubrics connected to gradebook scores.
- Cohort due dates, late policies, scheduled release and timezone-aware reminders.
- Explicit completion requirements: view, pass, submit, instructor approval,
  and certificate eligibility.
- Reports for not-started, stalled, overdue and failed learners with authorized
  enrollment/report actions.

## P3: release readiness

- Real SSO account-linking/login/logout and LTI launch/grade-passback tests.
- Supported SCORM/Common Cartridge examples and documented archive limits.
- SMTP, parallel quiz attempts/reset tokens, upgrades and recovery tests.
- Local SQLite demonstration and self-hosted SQLite/MariaDB installations.
  GitHub Pages hosts the introduction, roadmap and release downloads. A public
  hosted demo is optional future work, outside the current publication scope.
- Two or three organizational pilots and manual keyboard/screen-reader review.
- Consider LTI certification only after live interoperability is established.

## Success measures

- Operator installs and publishes a sample course within 30 minutes.
- Learner resumes the next required activity within two clicks.
- Authors configure and explain completion requirements without assistance.
- Every supported deployment passes complete backup/restore.
- No unresolved critical/high findings in the supported pilot configuration.
- Core browser, keyboard, and assessment journeys pass; pilot task failures
  and support requests decline between releases.

## Implementation ledger

| Item | Status | Evidence |
| --- | --- | --- |
| Roadmap saved and introduced on website | Implemented | This document; project website roadmap section |
| Complete backup/recovery and relocation | Implemented and tested | `tests/run.py`, `tests/recovery.py`, `tests/mysql.py` |
| MFA, revocation, scoped keys and verified SSO linking | Implemented; real SSO callback validation pending | Security/HTTP/browser tests; account services |
| Trusted shells and active-content isolation | Opaque-origin sandbox and basic SCORM bridge implemented/tested | Browser parent-access test; separate-origin/full SCORM interoperability remains follow-up |
| Supported environment checks | Passed locally and in release CI | PHP 8.1/8.3/8.4, Chromium, MariaDB and Docker/Apache; release run linked above |
| Readiness, templates, publication checklist, next activity | Implemented and browser-tested | Learning services and browser journeys |
| Assignments, rubrics, feedback, completion gates | Implemented and tested | Security/HTTP/browser/MariaDB checks |
| Instructor grants and protected attachments | Implemented and boundary-tested | Explicit course access; HTTP private-file checks |
| Cohort schedules, late rules, reminders and reports | Implemented; real scheduled delivery validation pending | UTC/deadline regressions; CLI reminder job |
| Focused service extraction | Implemented incrementally | Account, learning and assignment service/route files |
| Parallel requests and update/recovery checks | Passed on tested drivers | Eight-worker contention; real 1.12.1 code upgrade/rollback |
| Compatibility matrix and pilot protocols | Published | `COMPATIBILITY.md`, `PILOT_PROTOCOL.md` |
| Self-hosted distribution and local demo | Installer and SQLite/MariaDB checks delivered | Users provide their own machine/server; Pages publishes the website and downloads |
| Live provider tests, independent accessibility review, organization pilots | External validation required | Credentials, reachable server and participating users are needed; do not claim completion from local tests |

See [the publication audit](CODE_AUDIT.md) for the starting point and
[security policy](../SECURITY.md) for reporting vulnerabilities.

Release evidence: [1.13 verification](RELEASE_1.13.md). Remaining external
acceptance criteria are deliberately open; this release does not declare the
entire roadmap complete or claim independent certification.
