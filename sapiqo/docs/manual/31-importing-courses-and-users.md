# Importing courses and users

**Audience:** administrator
**Where:** Imports & backups — `/admin/import`

## What it is
The **Imports & backups** screen brings people and course content into Sapiqo in
one place. It has three sections, reachable from the jump links at the top:

- **Users (CSV)** — bulk-create/update accounts (and optionally enroll and group
  them) from a spreadsheet.
- **OneRoster (SIS)** — import users, schools, and enrollments from a Student
  Information System export.
- **Course content** — import a whole course as a package, Common Cartridge,
  SCORM, or LearnDash export.

A fourth link, **Download backup**, goes to the backup page (covered separately).

## How to use it

### Bulk-import users (CSV)
1. In the **Users (CSV)** section, choose **⬇ Download CSV template** to get the
   exact column layout (also available at `/admin/template.csv`).
2. Fill in one row per person. The header row is required. Columns are:

   ```
   first name,last name,email,campus,organization,user type,course title/id,group
   ```

   Only **email** is required per row. Header names are matched flexibly (for
   example `school` maps to campus, `district` to organization, `role` to user
   type, `cohort` to group), but the template names are safest.
3. Choose the CSV file and select **Upload and import**.
4. Read the **Import report** that appears below the form (see Options & behavior).

Example rows from the template:

```
first name,last name,email,campus,organization,user type,course title/id,group
Ada,Lovelace,ada@example.edu,Central High School,Example ISD,Teacher,Chromebook Educator,Example ISD
Grace,Hopper,grace@example.edu,North Elementary,Example ISD,Teacher,chromebook-educator,Example ISD
```

The **course title/id** column accepts either the course's display title or its
slug/id; leave it blank to create an account without enrolling. The **group**
column creates (or reuses) a group by name and adds the person to it.

### Import from a SIS (OneRoster)
1. In the **OneRoster (SIS)** section, choose your export file. Sapiqo accepts a
   **OneRoster v1.1** `.zip` containing `orgs.csv`, `users.csv`, `classes.csv`,
   and `enrollments.csv` — or just a single `users.csv`.
2. Select **Import OneRoster**.

Accounts are created or updated, grouped by school/organization, and enrolled
where a class **title** matches an existing course.

### Import course content
In the **Course content** section, each format has its own upload form. New
courses appear in the catalog immediately after a successful import.

1. **Course package** — choose a `.tar` / `.zip` produced by Sapiqo's own Export,
   then **Import package**. Also accepts `.tar.gz` / `.tgz`.
2. **Common Cartridge** — choose a `.imscc` (from Canvas, Blackboard, Moodle, or
   Sakai), then **Import .imscc**.
3. **SCORM package** — choose a `.zip` (SCORM 1.2 or 2004), then **Import SCORM**.
4. **LearnDash export** — choose a `.json` exported from a WordPress LearnDash
   course, leave **Mirror images locally** checked (recommended), then
   **Import LearnDash**.

### Import a very large course in split parts
When a course exceeds the server's upload limit, use the split workflow on the
**Manage courses** page (`/admin/courses`):

1. Export the course in parts from its **⋯ ▸ Export ▸ Split (large uploads)**
   menu, and download every part file.
2. On **Manage courses**, in the **Import split parts** bar, choose all the part
   files at once and select **Upload & install**. Parts upload one request at a
   time (so each stays under the cap), then are assembled and installed.

## Options & behavior

**CSV import report.** After a user import, Sapiqo shows how many rows were
processed and lists:

- **Created accounts** with a one-time **temporary password** per new user —
  share these securely; ask each person to change it on their Profile page.
- **Enrollments** made (email → course).
- **Group assignments** made.
- **Errors** by row number (for example an invalid email or an unmatched course).

Existing accounts are matched by email; blank profile fields are filled in
without overwriting values the account already has.

**OneRoster report.** Reports counts of orgs, users created/updated, groups,
enrollments, and any **unmatched classes** (class titles that did not match a
course). Roles are mapped to Sapiqo roles/user types (teacher, student,
administrator, staff).

**LearnDash import.**
- Lessons are grouped into **modules by their number prefix** (1.1, 1.2, …); a
  "Get Your Badge"/certificate lesson lands in a Finish module.
- **Vimeo embeds are kept** and rewritten into responsive players; the original
  Vimeo link is preserved for each lesson.
- **Mirror images locally** (checked by default) downloads referenced images into
  the course so it works with no external requests. Image fetching is
  SSRF-guarded and capped, so a few images may be reported as failed; the count
  of mirrored vs. failed images appears after import.
- A LearnDash `.json` **references videos on Vimeo, not files** — imported
  courses embed Vimeo rather than self-hosting the video.

**File-type support (exact acceptance):**

| Section | Accepted files |
|---|---|
| Users | `.csv` |
| OneRoster | `.zip`, or a single `.csv` |
| Course package | `.tar`, `.tar.gz`, `.tgz`, `.zip` |
| Common Cartridge | `.imscc`, `.zip` |
| SCORM | `.zip` |
| LearnDash | `.json` |

## How it works
Course imports extract into a scratch directory under the data folder (not system
temp, which is often too small for video), verify the archive has no unsafe file
paths, locate the course, and copy it into the content directory. Each successful
course import triggers a rescan so the catalog updates, and is recorded in the
audit log (`course.import`, `course.import_cc`, `course.import_scorm`,
`course.import_learndash`, `course.import_split`). User imports are recorded as
`users.import` and `users.import_oneroster`.

Common Cartridge parsing reads the `imsmanifest.xml` organization into
modules/lessons, pulls web-content HTML as lesson text, converts QTI
multiple-choice into quizzes, and bundles referenced media; the report lists
lessons, quizzes, media, and anything skipped. SCORM import copies the package
under a `scorm/` folder, detects the version from the manifest, and generates a
player that maps the package's completion/score back to Sapiqo progress.

## Tips & gotchas
- **Only email is required** in the user CSV — a minimal import can be a single
  email column.
- **Temporary passwords are shown once, in the report.** Capture them before
  leaving the page.
- **Unmatched courses/classes are reported, not created** — the course must
  already exist for enrollment to happen. Import the course first.
- **To self-host LearnDash videos** instead of embedding Vimeo: drop the MP4s in
  `content/<slug>/media/videos/`, add a `videos.json` map, then Rescan (see
  DEPLOYMENT.md).
- **Split import lives on Manage courses**, not this page — the Imports screen
  links you there for oversized courses.
- Alternatively, you can **copy a course folder directly** into the content
  directory and **Rescan** — no upload limit involved.

## Related
- Course management (`30-course-management.md`)
- Exports (`32-exports.md`)
- Users and roles (`20-users-and-roles.md`)
- Organizations and subscriptions (`21-organizations-and-subscriptions.md`)
- Backups (`34-backups.md`)
