# Reports and completions

**Audience:** administrator
**Where:** Training reports — `/admin/reports`; Completions — `/admin/completions`

## What it is
Two related screens answer "how is training going?":

- **Training reports** (`/admin/reports`) is a dashboard: headline numbers, a
  completion-rate donut, an 8-week activity chart, per-course performance, and
  breakdowns by organization, campus, user type, and group — plus a recent-activity
  feed. Every chart is drawn as inline SVG, so the dashboard works with no
  external services and stays offline-capable.
- **Completions** (`/admin/completions`) is the row-level view: every enrollment
  with its status, dates, and credential, a filter toolbar, and a filter-aware CSV
  export.

## How to use it

### Training reports
1. Go to **Training reports** (`/admin/reports`).
2. Read the **KPI strip** across the top: Total users, Enrollments, Completions,
   In progress, Active learners, Badges issued.
3. Use the two charts: the **Overall completion rate** donut and the **Activity
   (last 8 weeks)** grouped bar chart (Registered / Enrolled / Completed).
4. Scan the tables: **Course performance**, **By organization**, **By campus**,
   **By user type**, **By group** (when groups exist), and the **Recent activity**
   feed.
5. To export, use the toolbar: **⬇ Export summary CSV** (`/admin/reports.csv`) or
   **⬇ Export completions CSV**.

### Completions
1. Go to **Completions** (`/admin/completions`).
2. Narrow the list with the **filter toolbar** (search, group, organization,
   campus, status). The status, group, org, and campus dropdowns auto-submit;
   search uses the **Search** button.
3. Read each row's credential column: click the **badge** thumbnail to view it,
   **Badge** to download the badge PNG, **Cert** for the certificate PDF, and
   **Transcript** for the learner's full transcript.
4. Choose **⬇ Export CSV** to download exactly the rows shown (see below).
5. Choose **Clear** to reset all filters.

## Options & behavior

**Training reports — what each panel shows**
- **KPI strip.** Counts across users, enrollments, completions, in-progress
  enrollments, active learners (anyone with recorded progress), and badges issued.
- **Completion-rate donut.** Completed enrollments as a percentage of all
  enrollments, with "X of Y enrollments completed" beneath.
- **Activity (last 8 weeks).** Weekly buckets of new registrations, new
  enrollments, and completions.
- **Course performance.** Per course: Enrolled, Completed, Completion rate, and an
  **Avg. progress** bar (based on steps completed against enrolled × total units).
- **By organization / By campus / By user type.** Rollups with user counts,
  completions, and completion rates. Blank organizations show as `(none)`; blank
  user types show as `(unspecified)`.
- **By group.** Members, enrollments, completions, completion rate, and steps
  completed, with each group name linking to its detail page.
- **Recent activity.** A merged, time-sorted feed of registrations, enrollments,
  and completions.

**Completions — the table**
Columns are **Name**, **Email**, **Organization**, **Course**, **Status**,
**Completed**, and **Credential**. Status shows a green **completed** pill or an
amber **enrolled** pill. The name links to the user's admin page. The Credential
cell shows the badge and download buttons only when a badge code exists for that
enrollment; the Transcript link is always present.

**Completions — the filter toolbar**
- **Search** — matches first name, last name, or email.
- **Group** — limit to members of one group.
- **Organization** and **Campus** — populated from the values on user accounts.
- **Status** — Enrolled or Completed (or any).

**Filter-aware CSV export.** The **Export CSV** link carries your current filters
in its URL, so the download matches what you see. When any filter is active the
button label reads **Export CSV (filtered)**. Columns: First name, Last name,
Email, Campus, Organization, User type, Course, Status, Enrolled, Completed, Badge
code. The file is `sapiqo-completions.csv`.

**Row cap.** The Completions view (and its export) returns up to **5,000 rows**;
when that cap is hit the page notes "showing first 5000 — narrow with filters."

## How it works
Reports run aggregate SQL queries and bucket dates in PHP so they behave the same
on SQLite and MySQL. Charts are generated as inline SVG (`svg_donut` and
`svg_bar_chart`) with no chart library. The Completions list is a single joined
query over enrollments → users → courses, left-joined to badges to pick up the
badge code, filtered by the toolbar's parameters and ordered by status then name.
The CSV export reuses the same rows.

## Tips & gotchas
- **Two completions exports exist.** The one on the Completions page respects your
  filters; the one on the Reports toolbar (and the admin Exports card) is the
  unfiltered `/admin/completions?export=1`.
- **Time-on-task and quiz scores are not part of this dashboard.** Reporting is
  based on step completion, enrollment, and badges (see the note at the bottom of
  the Reports page). For assessment scores, use the Gradebook.
- **Org/campus filter options come from account fields** — keep those clean on
  import (or via user editing) so the breakdowns and filters are meaningful.
- **The Credential buttons are the fastest way to reissue** a badge PNG or
  certificate PDF for a learner who needs a copy.
- **Blank organization rows appear as `(none)`** in the org breakdown; that is a
  real bucket, not an error.

## Related
- Completions export details (`32-exports.md`)
- Course management (`30-course-management.md`)
- Badges, certificates, and transcript (`05-badges-certificates-transcript.md`)
- Gradebook (`13-gradebook.md`)
- Groups and managers (`22-groups-and-managers.md`)
