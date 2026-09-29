# Exports

**Audience:** administrator
**Where:** the **Exports** card group in Account/Admin navigation, plus each
course's ⋯ menu on `/admin/courses`

## What it is
Sapiqo can export almost everything it holds — people, results, course content,
the audit trail, and a full data snapshot — as downloadable files. The **Exports**
section of the admin hub gathers the shortcuts; the actual downloads are served by
dedicated routes and, for course content, from each course's **⋯** menu.

The exports available are:

- **User roster (CSV)** — every account with profile fields.
- **Completions (CSV)** — enrollments, status, dates, and badge codes
  (filter-aware).
- **Gradebook / grades** — a course scores grid, each with its own CSV export.
- **Course content** — export any course to another LMS (Common Cartridge), a
  `.tar` package, or raw `.json`, from the course's ⋯ menu.
- **Audit log (CSV)** — a record of admin actions.
- **Full data backup** — everything as a single archive.

## How to use it

### User roster (CSV)
1. Open **User roster (CSV)** (`/admin/users.csv`).
2. The file downloads as `sapiqo-users.csv`. Optionally add a search term with the
   `?q=` parameter to export only matching users (matches email, name,
   organization, or campus).

### Completions (CSV)
1. Go to **View completions** (`/admin/completions`) and set any filters you want.
2. Choose **⬇ Export CSV**. The export honors the current filters — the button
   even reads **Export CSV (filtered)** when filters are active. The file is
   `sapiqo-completions.csv`.
3. You can also grab an unfiltered completions export directly from
   `/admin/completions?export=1` or from the Training reports toolbar.

### Gradebook / grades
1. Open **Gradebook & grades** (`/admin/gradebook`) and select a course.
2. Each course scores grid has its own CSV export for points, categories, letter
   grades, and quiz results.

### Course content (from the ⋯ menu)
1. On **Manage courses** (`/admin/courses`), open a course's **⋯** menu.
2. Under **Export**, choose one:
   - **To another LMS (Common Cartridge)** — opens a guided page and downloads a
     `.imscc` for Canvas / Blackboard / Moodle / Sakai.
   - **Package (.tar)** — the full course (content + media) for moving between
     Sapiqo servers or archiving.
   - **Course data (.json)** — the raw `course.json` (structure, lessons,
     quizzes), no media.
   - **Split (large uploads)** — the `.tar` broken into small numbered parts, on a
     download page, for servers with tight upload limits.

### Audit log (CSV)
1. Go to the **Audit log** (`/admin/audit`), optionally filter by search term or
   action.
2. Choose **Export CSV** (or use `/admin/audit?export=1`). The export keeps your
   current filters; the file is `sapiqo-audit.csv`.

### Full data backup
1. Choose **Full data backup** / **Download backup** (`/admin/backup`).
2. A single archive of all persistent data downloads. See the Backups page for
   what it contains and how to restore.

There is also a **report summary CSV** on the Training reports toolbar
(`/admin/reports.csv`, downloads `sapiqo-report-summary.csv`) with headline KPIs,
per-course performance, and per-organization rows.

## Options & behavior

**User roster columns.** `sapiqo-users.csv` contains: ID, First name, Last name,
Email, Phone, Role, User type, Campus, Organization, Auth provider, Created,
Updated. Exporting the roster is recorded in the audit log as `users.export`.

**Completions columns.** `sapiqo-completions.csv` contains: First name, Last name,
Email, Campus, Organization, User type, Course, Status, Enrolled, Completed, Badge
code. The rows match exactly what the on-screen table shows under the same
filters.

**Course export formats at a glance:**

| Format | Extension | Use it to… | Audit action |
|---|---|---|---|
| Package | `.tar` | move a whole course (content + media) between Sapiqo servers or archive it | `course.export` |
| Common Cartridge | `.imscc` | import into Canvas / Blackboard / Moodle / Sakai | `course.export_cc` |
| Course data | `.json` | grab the raw structure (lessons, quizzes) without media | `course.export_json` |
| Split | `.tar` in parts | re-import on a server with a small upload limit | `course.export_split` |

The `.tar` uses no compression on purpose: course media (mp4/png/jpg) is already
compressed, so gzip would add time without shrinking the file.

**Split export.** The split page lists each part with a download link and shows
the chosen chunk size. Parts are served from a token-scoped URL for admins only.
Chunk size auto-fits under the server's upload limit (90% of the smaller of
`upload_max_filesize` / `post_max_size`), optionally capped by the
`export_chunk_mb` config value, and never below 256 KB.

## How it works
CSV exports stream directly to the browser with a `Content-Disposition:
attachment` header and are generated on the fly from the current database — the
completions and audit exports reuse the same query (and filters) that produce the
on-screen tables. Course archive exports are built with PharData into a scratch
directory under the data root, streamed to you, then deleted. Long-running
exports raise the script time limit so large media courses can finish packaging.

Course content export/import is symmetric: a `.tar` package produced here imports
cleanly on any other Sapiqo install (see Importing courses and users).

## Tips & gotchas
- **Completions export is capped at 5,000 rows** (the same cap as the table). If
  you have more, narrow with filters and export in batches.
- **`.json` export contains no media** — use the `.tar` package if you need the
  images and videos too.
- **Common Cartridge is the interoperable format** for other LMSs; the `.tar`
  package is Sapiqo-to-Sapiqo only.
- **Split parts are staged on the server** under the export directory; download
  all of them, since a missing part cannot be reassembled on import.
- **The full data backup is separate from course content** — courses are static
  files; back them up by copying the content folder or exporting each course.
  See the Backups page.

## Related
- Course management (`30-course-management.md`)
- Importing courses and users (`31-importing-courses-and-users.md`)
- Reports and completions (`33-reports-and-completions.md`)
- Backups (`34-backups.md`)
- Audit log (`35-audit-log.md`)
- Gradebook (`13-gradebook.md`)
