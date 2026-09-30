# Sapiqo — User & Administrator Manual

A complete, feature-by-feature guide to Sapiqo, a self-hosted learning management
system. Each page explains **what a feature is, how to use it step by step, its
options, how it works under the hood, and the gotchas to watch for.**

Pages are grouped by who uses them. If you are new, start with your role's first
page and follow the **Related** links at the bottom of each page.

---

## For learners

Everything a course participant needs.

| Page | What it covers |
|------|----------------|
| [Accounts & signing in](01-accounts-and-signing-in.md) | Registering, logging in, single sign-on, password reset, profile, avatar |
| [Taking a course](02-taking-a-course.md) | Finding & enrolling, the course reader, progress, sequential mode, video, resources |
| [Quizzes & assessments](03-quizzes-and-assessments.md) | Taking quizzes, attempts, passing, server-side grading, feedback |
| [Discussion forums](04-discussion-forums.md) | Threads, replies, reactions, "post before you see," announcements |
| [Badges, certificates & transcript](05-badges-certificates-transcript.md) | Earning badges, downloading certificates, CPE hours, your permanent transcript |
| [Notifications](06-notifications.md) | In-app and email notifications, expiry reminders |
| [Redeeming an enrollment code](07-redeeming-a-code.md) | Unlocking course(s) with a code — at sign-up, from a link, or on your dashboard |

| [Assignments & feedback](08-assignments-and-feedback.md) | Drafts, attachments, submissions, rubric scores and completion |
| [Account security](09-account-security.md) | MFA, recovery codes, sessions and linked identities |

## For course developers

Building and maintaining course content.

| Page | What it covers |
|------|----------------|
| [The visual block editor](10-visual-block-editor.md) | Modules, pages, blocks, the rich-text toolbar, tables, images, video/embeds, saving |
| [Course settings](11-course-settings.md) | CPE hours, prerequisites, enrollment expiry, sequential mode, forum toggle |
| [The resource center](12-resource-center.md) | Uploading and reusing images, video, and PDFs in a course |
| [The gradebook](13-gradebook.md) | Custom assessments, the scores grid, letter grades, CSV export |
| [Markdown authoring](14-markdown-authoring.md) | Building a course from a single Markdown file with the course creator |

| [Assignments, rubrics & cohorts](15-assignments-rubrics-cohorts.md) | Instructor access, grading, release/deadlines and late work |

## For administrators — people & access

Managing who can do what.

| Page | What it covers |
|------|----------------|
| [Users & roles](20-users-and-roles.md) | Accounts, the four roles, editing users, bulk actions, reset links |
| [Organizations & subscriptions](21-organizations-and-subscriptions.md) | Districts/clients, org-wide & per-group course subscriptions, auto-enroll, org managers |
| [Groups & managers](22-groups-and-managers.md) | Groups, group course subscriptions, group managers (sub-admins) |
| [Enrollment, expiry & reminders](23-enrollment-expiry-and-reminders.md) | Access windows, the expiry sweep, graduated reminders, retained credit |
| [Impersonation ("View as")](24-impersonation.md) | Troubleshooting as a specific user, safely and audited |
| [The Manage area for sub-admins](25-manage-area-for-sub-admins.md) | What group and organization managers can do, per permission |
| [Enrollment codes](26-enrollment-codes.md) | Generating/importing codes that auto-enroll learners; seats, expiry, redemptions |

## For administrators — courses & data

Getting content in and reporting on it.

| Page | What it covers |
|------|----------------|
| [Course management](30-course-management.md) | Creating, hiding, cloning, rescanning, and configuring courses |
| [Importing courses & users](31-importing-courses-and-users.md) | User CSV, OneRoster, Common Cartridge, SCORM, LearnDash, packages |
| [Exports](32-exports.md) | Roster, completions, grades, course content, audit log, full backup |
| [Reports & completions](33-reports-and-completions.md) | Dashboards, completion tracking, filters, filter-aware CSV export |
| [Backups](34-backups.md) | Downloading a full backup, what's inside, restoring |
| [Audit log](35-audit-log.md) | What's recorded and how to review it |

## For administrators — platform & integrations

Configuring and operating the platform.

| Page | What it covers |
|------|----------------|
| [Branding & settings](40-branding-and-settings.md) | Name, logo, themes, language, certificate signatory, reminders |
| [Single sign-on](41-single-sign-on.md) | Google, Microsoft, Clever, ClassLink, Rhythm configuration |
| [LTI 1.3](42-lti.md) | Launching Sapiqo as a tool from Canvas/Moodle/Blackboard, grade passback |
| [API keys & the REST API](43-api-keys-and-rest-api.md) | Creating keys and using the API |
| [Software updates](44-software-updates.md) | The in-browser updater, backup & rollback |
| [Security](45-security.md) | Built-in protections and the operator's responsibilities |
| [Scaling & performance](46-scaling-and-performance.md) | Running for thousands of users |

---

## Quick starts

- **"I just want to publish a course."** → [Markdown authoring](14-markdown-authoring.md) or
  [the visual block editor](10-visual-block-editor.md) → [Course management](30-course-management.md) to publish → [Course settings](11-course-settings.md) for CPE/expiry.
- **"I'm onboarding a whole district."** → [Organizations & subscriptions](21-organizations-and-subscriptions.md)
  (create the org, subscribe it to courses, add people) → [Importing courses & users](31-importing-courses-and-users.md) for the bulk roster → assign an [org manager](25-manage-area-for-sub-admins.md).
- **"I'm setting up the server."** → [Security](45-security.md) and [Scaling & performance](46-scaling-and-performance.md),
  plus `DEPLOYMENT.md` in the project root for install/hardening steps.
- **"Move a course in from another LMS."** → [Importing courses & users](31-importing-courses-and-users.md).

## About this manual

- These pages describe the software behavior; for install, server configuration,
  and hardening steps see **`DEPLOYMENT.md`** in the project root, and
  **`README.md`** for a feature overview.
- Nav paths (e.g. `Admin → Organizations`) and URLs (e.g. `/admin/orgs`) refer to
  the running application. Menu items appear based on your role.
