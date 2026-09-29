# Sapiqo LMS

A focused, self-hostable learning management system. Pure PHP + PDO (SQLite by
default, MySQL/MariaDB for production) — no framework, no Composer, no npm. Drop
in courses, author in the browser, enroll learners, run assessments and forums,
and issue badges and CPE certificates.

**[Project website](https://mguhlin.github.io/sapiqolms/) ·
[Download](https://github.com/mguhlin/sapiqolms/releases) ·
[User manual](sapiqo/docs/manual/README.md) · [Code audit](docs/CODE_AUDIT.md)**

Version **1.12.1**. Original project material: **© 2026 Miguel Guhlin · CC BY-SA 4.0**.

GitHub Pages hosts the project introduction. To run the LMS, install it on a PHP
server or use Docker; Pages cannot execute PHP or store learner accounts.

## What's inside (highlights)

- **Visual course editor** — drag-and-drop modules/pages, block-based content
  (rich text with a full word-processor toolbar + tables, Markdown, images,
  video/embeds, files), image/column resize, and a per-course Resource Center.
- **Authoring & interchange** — build from Markdown or the GUI; import/export
  Common Cartridge, SCORM, packages, and OneRoster; export to Canvas/Blackboard/
  Sakai/Moodle with per-platform guidance.
- **Assessments** — quizzes (multiple types, banks, attempts) + a custom
  gradebook scores grid with letter grades and CSV export.
- **Discussion forums** — named, threaded forums added to modules like pages,
  each with a rich prompt; nested replies, reactions, moderation; plus a global
  announcements board.
- **Accounts & access** — admins, course developers, and group managers with
  least-privilege permissions; user impersonation for support; enrollment expiry
  with graduated reminders and a permanent transcript (badges/CPE retained).
- **Organizations & subscriptions** — first-class organizations (districts/clients)
  that own groups and members; subscribe a whole org or an individual group to
  courses to auto-enroll cohorts; org- and group-level managers with per-grant
  permissions.
- **Reports & completions** — dashboards, filters, and filter-aware CSV exports;
  one-click badge/certificate/transcript access.
- **Integrations** — SSO (Google, Microsoft, Clever, ClassLink), LTI 1.3, a REST
  API, SCORM, and OneRoster.
- **Operations** — white-label branding + themes + i18n, full mobile
  responsiveness, an organized Admin menu, and a WordPress-style in-browser
  software updater with backup/rollback.

## Security

The publication includes a source audit and regression tests for authentication,
course access, progress, quizzes, archives, and backups. Read the
[audit findings and limitations](docs/CODE_AUDIT.md) and [security policy](SECURITY.md).
Use HTTPS, your own administrator credentials, and a canonical `public_url`.

## Layout

    sapiqo/        The application (replaceable on upgrade). Code, admin,
                   course creator, CLI tools in bin/.
    sapiqo-data/   Persistent, writable data — the SQLite DB, uploads, badges,
                   branding, LTI keys. Starts empty; created on first run.
    content/       Your courses, one folder per course (each with a course.json).
                   Starts blank except content/assets/ (the shared reader).
    installer/     Turnkey install for Linux, Windows, and Docker.

## Quick start (turnkey)

Linux/macOS:

    bash installer/install.sh        # checks PHP, sets up data + admin account
    bash installer/serve.sh       # http://localhost:8000

Windows (PowerShell):

    .\installer\install.ps1
    .\installer\serve.bat

Docker (bundles every extension):

    export ADMIN_EMAIL='you@example.org'
    read -rsp 'Administrator password: ' ADMIN_PASSWORD; echo
    export ADMIN_PASSWORD
    docker compose -f installer/docker-compose.yml up -d --build
    # Open http://localhost:8080

Run the pre-flight check any time:

    php installer/preflight.php

## First run & credentials

Sapiqo ships with an **empty database and no default login** — the admin
account is created the first time you install, from credentials *you* provide.
There is no built-in username/password to guess.

- **Installer (`install.sh` / `install.ps1`):** you pass the admin email and
  password, e.g.

      bash installer/install.sh --email you@example.org --password 'ChangeMe123!' \
                             --first Admin --last User

  Run it with no flags for an interactive prompt. If you use `--yes` without a
  `--password`, a strong random password is generated and printed once — copy it.

- **Docker:** requires `ADMIN_EMAIL` and `ADMIN_PASSWORD` environment variables.
  No default administrator password is shipped. Port 8080 binds to localhost;
  use a configured HTTPS reverse proxy for public access.

- **Manual / scripted:** create (or reset) an admin any time with

      php sapiqo/bin/setup.php --email you@example.org --password 'ChangeMe123!' \
                              --first Admin --last User

  Re-running is safe — an existing admin email is updated, not duplicated.

Whatever method you use, **change the password after first sign-in** and run
over HTTPS in production. See `installer/README.md` for the full install matrix
and `sapiqo/README.md` for the security overview.

## Add a course

Author one Markdown file and build it into a course folder:

    python3 sapiqo/creator/build_course.py my-course.md
    # -> writes content/my-course/ (course.json + reader shell + media)

Sapiqo auto-discovers any subfolder of content/ that has a course.json.
You can also import Common Cartridge (.imscc), SCORM, and course packages
from Admin -> Imports.

## Branding

Everything visual is white-label. Sign in as the admin and open
Admin -> Settings to set the organization name, logo, colors, theme, default
language, and certificate signatory / CPE provider line.

## License

Sapiqo LMS © 2026 Miguel Guhlin, licensed under
[Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)](https://creativecommons.org/licenses/by-sa/4.0/).
You may share and adapt it, including commercially, as long as you give
attribution to "Sapiqo LMS by Miguel Guhlin" and license your derivatives under
the same terms. See [`LICENSE`](LICENSE) for details.

Bundled third-party fonts retain their own licenses and notices; see
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## Development and contributions

```bash
python3 tests/run.py
```

The suite uses temporary data and a localhost PHP server. See
[CONTRIBUTING.md](CONTRIBUTING.md) for requirements and verification scope.
GitHub Actions runs checks and deploys only `site/` to Pages after they pass.
