# Course management

**Audience:** administrator
**Where:** Manage courses — `/admin/courses`

## What it is
The **Courses** screen is where you manage the whole course catalog. It shows one
row per course with its status, unit count, and enrollment count, plus the tools
to create courses, edit them, hide or restore them, clone and export them, and
re-sync the catalog with the course folders on disk.

Two facts shape everything on this page:

- **Courses are folders on disk.** Each course lives in its own folder under the
  course content directory (the "drop-in" folder), and each folder holds a
  `course.json` describing the course. The catalog table is kept in sync with
  those folders.
- **Hiding is not deleting.** Hiding a course removes it from the learner catalog
  but keeps its folder, its badges, certificates, enrollments, and progress. The
  only way to permanently remove a course is to delete its folder on disk — and
  even then, learner badges and progress remain in the database.

## How to use it
1. Go to **Manage courses** (`/admin/courses`). The toolbar at the top holds the
   create/import/scan actions; the table below lists every course.
2. **Create a course visually:** type a title in the **New course title** box and
   choose **Create (visual)**. This opens the visual block editor on a new,
   empty course.
3. **Create a course in Markdown:** choose **Markdown editor** to author with the
   Markdown-based creator instead (`/admin/create`).
4. **Edit an existing course:** in its row, choose **✏ Edit** to open the visual
   block editor (pages, blocks, media, quizzes, and course settings). If the
   course is available, **Open** launches the live learner view in a new tab.
5. **Do more from the ⋯ menu:** each row has a **⋯** ("More actions") kebab menu
   with course settings, source editing, clone, export options, and hide/restore
   (see below).
6. **Re-sync folders:** after copying a course folder onto the server (or
   removing one), choose **Rescan drop-in folder** to update the catalog.
7. **Import instead of build:** choose **Import courses / users** to go to the
   Imports screen for packages, Common Cartridge, SCORM, LearnDash, and users.

## Options & behavior

**The courses table**
Columns are **Course** (title + slug), **Status**, **Units**, **Enrolled**, and
**Actions**. Status shows a green **Available** pill for active courses or an
amber **Hidden** pill for deactivated ones. **Units** is the total number of
steps discovered in the course; **Enrolled** is the number of enrolled learners.

**The ⋯ (kebab) menu**
The kebab opens a menu with these items:

- **⚙ Course settings** — jumps to the visual editor's Settings tab
  (`/admin/editor/<slug>#settings`).
- **Edit Markdown source** — opens the Markdown creator on this course. Shown
  only when the course has an editable Markdown source.
- **Clone** — opens the Markdown creator pre-loaded with a copy of this course;
  you review the slug/title and publish it as a brand-new course. Shown only when
  the course has an editable source.
- **Export ▸ To another LMS (Common Cartridge)** — a guided `.imscc` export page.
- **Export ▸ Package (.tar)** — the full course package (content + media).
- **Export ▸ Course data (.json)** — the raw `course.json`.
- **Export ▸ Split (large uploads)** — the `.tar` broken into small parts for
  servers with tight upload limits.
- **Hide from catalog / Restore to catalog** — toggles the course's visibility.
  The confirmation reminds you that learner badges and progress are kept.

(Export formats are covered in detail on the Exports page.)

**Rescan drop-in folder**
Choose **Rescan drop-in folder** (or the **Rescan courses** action on the admin
hub) to reconcile the catalog with the folders on disk. On each scan Sapiqo:

- **Adds** any new folder that contains a valid `course.json` (with a `slug`).
- **Updates** the title, path, unit count, and badge image of existing courses.
- **Deactivates** (soft-removes) a course whose folder has disappeared — the row
  and all learner data are kept, so restoring the folder brings it back.
- **Reactivates** a course whose folder has reappeared.

The result flash reports how many were added, reactivated, and deactivated (and
names anything removed). A folder containing a `.disabled` marker file is scanned
in as **Hidden**; that marker survives rescans.

**Per-course configuration**
Each course carries several settings that admins (and course developers) can set.
Most are reached from **⚙ Course settings** in the editor; each is saved by its
own action and recorded in the audit log:

- **Prerequisite** (`/admin/courses/<slug>/prereq`) — require another course to
  be completed first. Sapiqo blocks a course from being its own prerequisite and
  rejects a change that would create a direct circular prerequisite.
- **CPE hours** (`/admin/courses/<slug>/cpe`) — continuing-education credit hours
  printed on the certificate. Accepts a decimal (comma or dot); negatives clamp
  to 0.
- **Enrollment expiry** (`/admin/courses/<slug>/expiry`) — days until an
  enrollment expires; **0** means no expiry.
- **Sequential mode** (`/admin/courses/<slug>/sequential`) — whether lessons must
  be completed in order. Three choices: **inherit** the global default, **on**
  (locked order), or **off** (any order).
- **Forum mode** (`/admin/courses/<slug>/forum`) — **off**, **on** (open
  discussion), or **gated** ("post before you see" — participants must post
  before they can read others' posts).

## How it works
The catalog table is derived from disk by the course scanner. For each folder
under the content directory it reads `course.json`, requires a non-empty `slug`,
and upserts a row (title, path, total units, badge image, active flag). A course
folder with a `.disabled` file is registered as inactive. Courses whose folder is
gone are set inactive rather than deleted, which is why hiding, deleting, and
restoring never lose badges or progress.

The unit count comes from the course's stats: for SCORM courses it is the unit
count from the package (at least 1); for native courses it is the sum of
lessons + topics + quizzes.

Badge resolution during a scan looks first for a `badge.png/.jpg/.jpeg/.svg` in
the course folder (self-contained), then for a name match in the shared badge
library, and otherwise falls back to a placeholder.

Hiding a course writes/removes the `.disabled` marker and re-scans; that is why a
hidden course stays hidden across future rescans until you restore it.

## Tips & gotchas
- **To permanently delete a course, remove its folder** from the content
  directory, then rescan. Learner badges and progress remain in the database.
- **Prefer "Hide" over deleting** when you only want a course out of the catalog
  temporarily — it is fully reversible and keeps the folder in place.
- **Clone / Edit Markdown source only appear for courses that have a Markdown
  source.** Courses imported as SCORM or Common Cartridge, or built purely in the
  visual editor, may not show these items.
- **After copying a folder onto the server, you must Rescan** for it to appear —
  new folders are not detected automatically on every page load.
- **Very large courses** (hundreds of MB of video) can exceed the web upload
  limit. Use the **Split** export/import, drop the folder in directly and
  rescan, or raise `upload_max_filesize` / `post_max_size`.
- The number in the **Units** column drives the average-progress calculation in
  Training reports, so it changes when you edit a course and rescan.

## Related
- Importing courses and users (`31-importing-courses-and-users.md`)
- Exports (`32-exports.md`)
- Reports and completions (`33-reports-and-completions.md`)
- Visual block editor (`10-visual-block-editor.md`)
- Course settings (`11-course-settings.md`)
