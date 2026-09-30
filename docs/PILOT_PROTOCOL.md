# Pilot and accessibility validation protocol

© 2026 Miguel Guhlin · CC BY-SA 4.0

Use two or three participating organizations, separate installations where
organizational isolation is required, synthetic data for technical tests, and
consent-based observation for usability studies. Record release, PHP/database,
browser, hardware, participant role and task outcome. Do not treat organization
and group features as independently verified SaaS tenant isolation.

## Core tasks

1. New operator installs, configures email/branding and publishes a course.
   Target: 30 minutes without assistance. Test recovery before enrollment.
2. New author chooses a template, replaces prompts, previews as learner, reviews
   publication checks, adds an assignment/rubric, and publishes.
3. Learner joins, understands completion requirements, resumes the next activity
   in two clicks, completes a quiz, saves/submits an assignment, reads feedback,
   revises work and retrieves their certificate.
4. Instructor configures a cohort schedule across time zones, handles late work,
   grades with a rubric, and identifies not-started, stalled or overdue learners.
5. Operator upgrades staging, checks existing users/courses/transcripts and
   demonstrates full restore and code rollback before production deployment.

## Accessibility target: WCAG 2.2 AA

- Complete all learner, author and administrator tasks by keyboard. Record
  focus order, visible focus, modal escape/return and drag-and-drop alternatives.
- Test NVDA/Firefox and VoiceOver/Safari (or equivalent supported combinations).
  Check headings, landmarks, form labels, validation errors, quiz feedback,
  progress, rubric tables and status announcements.
- Test 200% zoom, narrow/mobile layouts, touch target sizes, contrast and reduced
  motion. Test actual author-uploaded content, captions and image alternatives.
- Test authenticator and recovery-code entry with paste/password managers.
- Report each criterion as PASS/FAIL/NOT RUN/N/A, with steps and evidence.
  Browser fixtures alone do not establish WCAG conformance.

Reference: [W3C WCAG 2.2](https://www.w3.org/TR/WCAG22/).

## Provider and package evidence

For each configured SSO provider: existing linked account, new account with
registration open/closed, provider-email changes, duplicate linking rejection,
MFA, logout, and session revocation. Record credential configuration privately.
For LTI: launch, issuer/client/deployment/nonce rejection, scoped grade passback,
and platform account separation. Follow the
[1EdTech LTI certification process](https://www.1edtech.org/certification/lti)
only after real interoperability checks pass.

For imported packages: course navigation, media/resources, actual questions,
completion, re-entry and accessibility. Record exporter/version and unsupported
features. Never infer full SCORM compliance from one synthetic completion test.

## Performance and release decision

Measure representative concurrent users on the chosen production hardware,
including login, course reads, submissions and assessment saves. Record p50/p95,
error rate, worker/memory limits and database contention. Separate large media
transfer time from application response time. The eight-worker race tests prove
single-use behavior, not institutional capacity.

Release to pilots only with no unresolved critical/high findings in the selected
configuration, green supported-environment checks and a successful restore.
Track assistance requests, task failures and completion time between releases.
Keep real provider, independent accessibility and participant results marked
pending until evidence is collected.
