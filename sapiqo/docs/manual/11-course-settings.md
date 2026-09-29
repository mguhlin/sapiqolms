# Course settings

**Audience:** course developer
**Where:** Admin → Courses (`/admin/courses`) → ✏ Edit → **⚙ Settings** (top bar), or the ⋯ menu → **⚙ Course settings** (`/admin/editor/<slug>#settings`)

## What it is

Course settings are the per-course options that control enrollment, completion
gating, and discussion — kept separate from the page content you build with
blocks. They open in a modal dialog inside the visual editor.

The settings panel covers:

- **Tagline** — a short one-line description shown with the course.
- **CPE hours** — professional-development credit awarded on completion.
- **Access expires after (days)** — an enrollment lifetime.
- **Prerequisite course** — another course a learner must finish first.
- **Sequential mode** — force lessons to be done in order.
- **Discussion forum** — enable forums, optionally with a post-first gate.

## How to use it

1. Open the course in the visual editor (**Admin → Courses → ✏ Edit**).
2. Click **⚙ Settings** in the top bar. (Opening the editor with the URL ending
   in `#settings`, or the ⋯ menu's **⚙ Course settings** link, opens it
   automatically.)
3. Fill in the fields you need:
   - **Tagline** — a one-line description.
   - **CPE hours** — a number (supports quarter-hour steps, e.g. `1.5`).
   - **Access expires after (days)** — `0` for no expiry.
   - **Prerequisite course** — pick from the dropdown, or **— none —**.
   - **Sequential mode** — Default, On, or Off.
   - **Discussion forum** — On, On + post-first, or Off.
4. Click **Save settings**. On success you'll see **Saved ✓** and the dialog
   closes; the **✕** button closes it without saving.

## Options & behavior

- **Tagline**
  - Free text, one line. Stored in `course.json` (not the database).
- **CPE hours**
  - Number field, `min="0"`, `step="0.25"`, default `0`.
  - Written to `courses.cpe_hours`; awarded and recorded on the learner's badge
    when they complete the course. Anything already earned stays on the
    transcript permanently.
- **Access expires after (days)**
  - Number field, `min="0"`; `0` means **no expiry** (per the field's tooltip).
  - Backed by `set_course_enroll_days()`. Setting a positive value
    **recomputes `expires_at` for existing active (non-completed)
    enrollments** from each learner's original enrollment date. Setting it back
    to `0` clears all expiry dates for the course.
  - When access lapses, the learner is soft-unenrolled (progress, badges,
    certificate, and CPE hours are kept); pre-expiry reminder emails/in-app
    notices are sent at the configured milestones.
- **Prerequisite course**
  - Dropdown of every other course; **— none —** clears it.
  - The server guards against a course being its own prerequisite and against a
    simple circular prerequisite (A→B, B→A). A blocked choice is silently
    reset to none.
  - A learner cannot enter a course until they've **completed** its
    prerequisite.
- **Sequential mode**
  - Options map to: **Default** = follow the site's global default,
    **On** = always sequential, **Off** = free navigation.
  - Backed by `set_course_sequential()`. When sequential is in effect, the
    reader **locks each lesson until the previous one (and its quiz) is
    complete**, so the badge/certificate is only reachable after finishing in
    order.
  - "Default" defers to the `sequential_default` site setting (ON unless an
    administrator changed it).
- **Discussion forum**
  - **On** — forums are enabled.
  - **On + post-first** — a "post before you see" gate: a learner must
    contribute before they can read others' posts.
  - **Off** — forums disabled.
  - Backed by the `forum_enabled` and `forum_gated` columns
    (`forum_enabled=0` for Off; `forum_gated=1` for the post-first variant).
  - Forums themselves are added per module in the editor outline via the
    **＋ Forum** button; this setting turns the feature on/off course-wide.

## How it works

- The modal is pre-filled from the `settings` object returned by
  `GET /api/editor/<slug>`, which reads live values from the `courses` row
  (`cpe_hours`, `prereq_id`, `enroll_days`, `sequential`, `forum_enabled`,
  `forum_gated`) plus the tagline from `course.json`.
- **Save settings** posts a form-encoded body to
  `POST /api/editor/<slug>/settings`. The route:
  - updates `cpe_hours` and `prereq_id` (with the self/cycle guards),
  - calls `set_course_enroll_days()` (which recomputes enrollments),
  - calls `set_course_sequential()` (`inherit` → `NULL`, else on/off),
  - updates `forum_enabled` / `forum_gated`,
  - writes the tagline back into `course.json`,
  - records a `course.settings` audit entry.
- Administrators can also change several of these outside the editor via
  dedicated course-management routes (e.g. `/admin/courses/{slug}/cpe`,
  `/prereq`, `/expiry`, `/sequential`, `/forum`); the editor's Settings panel is
  the one-stop, course-developer-friendly place to do it all at once.

## Tips & gotchas

- **CPE hours and expiry live in the database**, while the **tagline lives in
  `course.json`.** Editing the Markdown source and rebuilding can overwrite the
  tagline but won't touch database-backed settings.
- Changing **Access expires after** rewrites deadlines for everyone already
  enrolled — do it deliberately, especially mid-cohort.
- **Sequential = Default** is not the same as **Off.** "Default" follows the
  site policy (which ships as ON); choose **Off** explicitly if you want free
  navigation regardless of site policy.
- Prerequisites are enforced on **completion**, not enrollment or progress — a
  learner who is halfway through the prerequisite still can't start this course.
- The **post-first** forum gate only matters once forums are **On**; it has no
  effect when the forum is Off.

## Related

- [Visual block editor](10-visual-block-editor.md) — adding forums with ＋ Forum
- [Markdown authoring](14-markdown-authoring.md)
- [Gradebook](13-gradebook.md)
