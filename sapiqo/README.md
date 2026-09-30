> Version 1.13 adds assignments/rubrics, course instructor grants, MFA, scoped API keys and complete recovery. See the [release verification](../docs/RELEASE_1.13.md) and [operations guide](../docs/OPERATIONS.md) for current behavior and compatibility limits.

# Sapiqo LMS

**Learning made clear.** A focused, self‑hostable learning management system. It
serves interactive course sites, tracks per‑learner completion, runs assessments
and discussion forums, issues digital badges + CPE/GT certificates, and can be
authored entirely in the browser with a visual block editor.

Pure **PHP + PDO**. **No framework, no Composer, no npm, no external services.**
Runs on any LAMP server (Linux/Apache/MySQL/PHP) or Windows + PHP, and on SQLite
for zero‑config local use. Current version: see `app/version.php`.

---

## Quick start

From inside `sapiqo/`:

```bash
bash install.sh                                        # Linux/macOS
powershell -ExecutionPolicy Bypass -File install.ps1  # Windows
```

The installer checks PHP + extensions, prompts for the database and an admin
account, creates the sibling `sapiqo-data/` folder, builds the schema, and scans
`content/` for courses. Then run the dev server:

```bash
php -S localhost:8000 -t public public/router.php
```

Open <http://localhost:8000/> — a public splash/catalog when logged out, the
learner dashboard when signed in. See [`DEPLOYMENT.md`](DEPLOYMENT.md) for LAMP,
Windows, MySQL, Docker, cron, and upgrade instructions.

---

## Recent updates (September 2026)

- **Safe concurrent editing** — the visual editor now guards against two people
  overwriting each other: a soft per‑course **lock/lease** (with *Take over* and a
  read‑only banner + 30‑second heartbeat), an **optimistic revision guard** that
  refuses a stale save (clear "changed by X — reload" message instead of a silent
  clobber), **atomic writes**, and recoverable **per‑save snapshots**.
- **GT hours** alongside CPE — set per course/badge; shown on catalog cards, the
  certificate, and the transcript (GT column appears only when used).
- **Certifications** — mark a course as a certification (gold banner, sorted first)
  and manage certifications on their own admin page, separate from courses.
- **Course lifecycle** — **draft ⇄ published** toggle, reversible **hide/restore**,
  and admin‑only **permanent delete** that still keeps learners' badges/certificates.
- **Enrollment codes** — generate redeemable codes that enroll learners into one or
  more courses; learners redeem from a nav link.
- **Purchase‑to‑enroll** — optional, config‑gated (`catalog_purchase`) catalog mode
  that shows per‑course **Purchase** links instead of self‑enrollment.
- **Share on your network** — admin page with the LAN URL for quick demos/workshops.
- **Role‑aware Help landing page** — a short intro tailored to learner / course
  developer / admin, showing only the topics relevant to each.
- **Badge library** — course badges resolve from a shared library of transparent
  PNGs (or a course's own `badge.png`); badge image loading is format‑agnostic.
- **Public Terms of Service & Privacy Policy page** (`/privacy`, `/terms`, linked
  in the footer) — no login required. Content comes from an operator‑supplied
  `<data>/legal/terms-privacy.md` file (update‑safe, per‑instance) or falls back
  to a generic, config‑driven default so the page is never empty on a fresh
  install. A configurable **contact link** (`help_contact_url`) appears on this
  page and in Help; configurable **`org_url`/`org_blog_url`** add optional
  "back to our main site" links in the footer.

---

## Features by function

### Course content & the reader
- **Drop‑in courses**: any folder in `content/` with a `course.json` is
  auto‑discovered and added to the catalog; remove it to hide the course
  (learners keep their badges and progress). "Rescan" and a boot‑time scan pick
  up changes.
- **Interactive reader** served at `/courses/<slug>/`: sidebar navigation,
  per‑step progress, resume, and localStorage fallback when used standalone. When
  a learner is signed in, it syncs completion to the LMS via `/api`.
- **Media**: self‑hosted video with HTTP range (seeking) + captions, images, and
  embeds. Content is **gated** — signed‑out visitors get only a syllabus preview
  (outline, no media); enrolled learners get the full course.
- **Course lifecycle**: **draft ⇄ published** (drafts stay hidden from learners),
  **hide/restore** (reversible), and admin‑only **permanent delete** (removes the
  course and its files but **keeps every learner's badges/certificates** on their
  transcript). **Certifications** are flagged per course and managed on their own
  admin page, separate from regular courses.

### Visual course editor (author in the browser)
- **Outline**: modules → pages, with **drag‑and‑drop** to reorder pages and to
  move whole modules (each with its pages). Add/rename/delete with named,
  confirmed prompts.
- **Block‑based pages**: build each page from blocks — **rich text, Markdown,
  heading, image, video/embed, file, divider** — reorder or delete any block.
- **Word‑processor toolbar** on rich‑text: paragraph styles, font size, bold/
  italic/underline/strike, **text + highlight color**, alignment, lists, indent,
  **tables** (insert/add/delete rows & columns), link/unlink, clear formatting,
  undo/redo. **Clean paste** keeps formatting from Google Docs/Word but strips the
  junk. **Drag handles** resize images and table columns.
- **Embeds**: YouTube, Vimeo, self‑hosted MP4, Google Drive/Docs, OneDrive/
  SharePoint, Dropbox, or any HTTPS page.
- **Course settings** (⚙ in the editor): tagline, **CPE + GT hours**, prerequisite,
  enrollment expiry, sequential mode, **certification flag**, **draft/published**
  status, and the course forum on/off/gated.
- **Per‑course Resource Center**: upload and organize images/videos/PDFs/docs;
  reuse them as blocks.
- **Safe concurrent editing**: a soft per‑course **edit lock/lease** warns when
  someone else is already editing (with a *Take over* option and read‑only mode),
  a **revision guard** refuses a save built on a stale copy so two people can't
  silently clobber each other, and every save is written **atomically** with a
  recoverable **snapshot** of the previous version (kept under the data folder).
- Legacy Markdown‑authored and imported courses open in the visual editor too.

### Authoring, import & export
- **Create visually** (block editor) or with **Markdown** (in‑browser editor).
- **Import**: course packages (`.tar`), **IMS Common Cartridge** (`.imscc`),
  **SCORM** (1.2/2004, with an auto‑generated player), **LearnDash** (WordPress)
  course exports (`.json`, with optional local image mirroring), user CSV, and
  **OneRoster** SIS rosters. Large uploads can be split into parts.
- **Export**: to another LMS via **Common Cartridge** (Canvas / Blackboard /
  Sakai / Moodle) with step‑by‑step per‑platform guidance, plus `.tar` package,
  raw `.json`, and split export. A central **Exports** page gathers roster CSV,
  completions CSV, gradebook CSVs, course content exports, audit CSV, and backup.

### Learners
- **Dashboard**: enrolled courses with progress, resume deep‑links, and earned
  badges/certificates.
- **Catalog** with prerequisites (locked until met), **CPE/GT‑hour pills**, and
  **certification** programs highlighted (gold banner) and sorted first; draft
  courses are hidden from learners.
- **Transcript**: a permanent, printable record of completed courses, **CPE + GT
  hours**, badges, and certificates — retained even after a course's access period ends.
- **Badges + certificates**: a personalized badge PNG + a 2‑page PDF certificate
  (with **CPE + GT hours** and signatory block) are issued automatically at 100% and
  are downloadable and verifiable by code. Course badges resolve from a shared
  **badge library** (transparent PNGs) or a course's own `badge.png`.

### Assessments & gradebook
- **Knowledge‑check quizzes** in courses: single/multiple choice, true/false,
  short answer, shuffle, pick‑N banks, attempt limits, and per‑question feedback;
  graded server‑side (answer keys never sent to learners).
- **Custom gradebook** per course: a spreadsheet‑style **scores grid** (learners
  × assessments) with categories, points, **letter grades** (configurable scale),
  inline auto‑saving cells, and CSV export, alongside auto‑graded quiz results.

### Discussion forums
- **Forums are authored like pages**: add one or more **named forums** to any
  module in the editor, each with a rich **discussion prompt** (blocks with text/
  images/video/embeds).
- **Threaded discussions**: nested replies to any post, avatars, relative
  timestamps, 👍 reactions, and moderation (hide/delete).
- **"Post before you see"** gating per forum (optional).
- **Global announcements board**: everyone with an account can read; only admins
  post.

### Accounts, roles & least‑privilege access
- **Central account management** (`/admin/accounts`): grant and review every
  privileged role in one place.
- **Roles**: **Administrator** (full), **Course developer** (author/edit content
  only — not users/settings), and **Group manager** (manage a group's members
  and/or enrollments, per‑grant permissions; can't touch content unless also made
  a course developer).
- **Impersonation**: admins can "View as" any user for troubleshooting (never
  another admin) and switch back with one click — fully audited.

### Enrollment, expiry & retention
- Per‑course **access window** (days); when it lapses the enrollment is
  soft‑removed but **badges, certificates, CPE, and transcript are kept**.
- **Graduated reminders** before expiry (default 30 / 7 / 1 days, configurable) by
  email + in‑app notification, each sent once.
- **Enrollment codes**: generate redeemable codes that enroll a learner into one or
  more courses (handy for events/workshops); learners redeem from a nav link.
- **Purchase‑to‑enroll** (optional, config‑gated `catalog_purchase`): non‑enrolled
  learners see a per‑course **Purchase** link instead of self‑enroll, and an admin
  enrolls them once payment is confirmed. Off by default (learners self‑enroll).

### Organizations, groups & subscriptions
- **Organizations** (`/admin/orgs`) — a first‑class client/district (e.g. *Aldirk
  ISD*) that owns groups and members. **Subscribe** a whole org, or an individual
  **group**, to one or more courses: every member is enrolled automatically, and
  anyone added later (to the org or any of its groups) is auto‑enrolled too. A
  member's access is the union of their org‑wide + per‑group course subscriptions.
- **Non‑destructive un‑subscribe**: removing a course from an org/group stops
  *future* auto‑enrollment but keeps current learners' access, progress, and badges.
- **Groups** — organize learners by **campus/cohort** (optionally inside an org);
  create groups from organizations or campuses; each group has its own subscriptions.
- **Managers** — assign an **organization manager** (runs the whole org — all its
  groups) or a **group manager**, each with per‑grant permissions (enroll / edit
  members / manage course subscriptions) via their scoped `/manage` area.

### Reports, completions & analytics
- **Training reports**: completion‑rate donut, 8‑week activity chart, per‑course
  performance, breakdowns by organization and user type, recent activity — all
  dependency‑free inline SVG, with CSV export.
- **Completions**: every enrollment with status, dates, and **clickable badge /
  certificate / transcript**; filter by **search / group / organization / campus /
  status**, with a filter‑aware CSV export.

### Branding & white‑label
- **Admin → Settings**: platform name, tagline, org name, **logo upload** (used in
  the nav, splash, login, and certificate), **theme presets** (12 skins) + custom
  navy/gold colors, fonts, corner radius, certificate signatory / CPE provider,
  and default language.
- **i18n**: server‑side UI translations (en/es/vi/ar/hi/ur/zh, RTL‑aware) with a
  language switcher; drop `app/lang/<code>.php` to add more.

### Integrations
- **SSO** (config‑gated): Google, Microsoft, **Clever**, **ClassLink**.
- **LTI 1.3** tool provider: launch from Canvas/Moodle/Blackboard with grade
  passback (AGS), hand‑rolled RS256/JWKS (no library).
- **REST API v1**: Bearer‑token endpoints for courses, users, enrollments,
  completions, and badges.
- **OneRoster** SIS import and **SCORM** packages.

### Mobile, accessibility & admin UX
- **Responsive** across the whole LMS (collapsible nav, stacked layouts, scrollable
  grids) — including the editor and gradebook.
- **Accessible**: skip links, landmarks, labels, focus styles, live regions.
- **Admin menu**: a dropdown organizes admin into sections — Course management,
  Account management, Imports & backups, **Exports**, Settings & integrations —
  each its own page with icon cards. Optional per‑page **hero images** light up
  automatically when dropped into `public/assets/img/heroes/`.
- **Share on your network**: an admin page shows the LAN URL(s) for the running
  instance so others on the same Wi‑Fi can connect during a demo or workshop.
- **Role‑aware Help**: a built‑in user guide whose landing page shows a short
  intro tailored to the reader's access level (learner / course developer / admin)
  and lists only the topics relevant to them.

### Software updates (WordPress‑style)
- **Admin → Software updates**: upload a `.tar.gz` package to update the LMS
  **code** without touching data, courses, or settings. It **backs up the current
  code first** and supports one‑click **rollback**. Build a package from the CLI
  (`php bin/build-update.php`) or from the admin page. Migrations run automatically
  on the next load.

---

## Layout

```
courses/                 the project root (clean 3‑folder layout)
  sapiqo/                CODE — replace this folder to upgrade
    public/              web root: index.php, router.php, .htaccess, assets/ (incl. app.js)
    app/                 config, db, auth, courses, editor, forum, gradebook, discovery,
                         serve, groups, settings, i18n, api, lti, scorm, updater,
                         security, badge, avatar, routes.php, views/, lang/
    creator/  scripts/  sql/  bin/  installer/  install.sh  install.ps1
  sapiqo-data/           PRIVATE — config.local.php, data/ (DB, badges, avatars, LTI keys, updates)
  content/               ALL course content (drop‑in, auto‑discovered) + shared reader assets/
```

The document root is `sapiqo/public/`. Course files are served at `/courses/<slug>/`
by a **path‑confined PHP passthrough** that refuses the private `sapiqo*` folders,
so the database, config, and keys stay unreachable. The data root resolves via
`SAPIQO_DATA` (else sibling `sapiqo-data/`); content via `SAPIQO_COURSES` (else the
sibling `content/`).

**Data model:** `users`, `courses`, `enrollments`, `progress` (per step),
`badges`, `quiz_results`, `assessments`/`assessment_scores`, `forum_posts`/
`forum_reactions`, `user_groups`/`group_managers`, `notifications`, `settings`,
`audit_log`, `lti_*`, `api_keys`. Completion = distinct completed steps ÷
`courses.total_units`; at 100% a badge is issued (idempotent) and the enrollment
is marked complete. The schema self‑migrates on version bump.

---

## Security and validation

See the current [code audit](../docs/CODE_AUDIT.md), [security policy](../SECURITY.md),
and [test instructions](../CONTRIBUTING.md). The application uses PDO prepared
statements, route authorization, session CSRF tokens, password hashing, login
throttling, script nonces, archive validation, and controlled course serving.
These safeguards do not constitute an independent security certification.

Deploy only `public/` as the document root, configure HTTPS and `public_url`, and
keep persistent data outside the web root. Administrators must trust active
SCORM/native packages and uploaded software updates. API keys grant full admin
scope. Back up course content separately from the data backup.

## License

Sapiqo LMS © 2026 Miguel Guhlin. Original project material is licensed under
[CC BY-SA 4.0](../LICENSE). Bundled fonts retain their original licenses; see
[third-party notices](../THIRD_PARTY_NOTICES.md).
