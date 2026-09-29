# Gradebook

**Audience:** course developer
**Where:** Admin → Gradebook (`/admin/gradebook`); a single course's scores grid at `/admin/gradebook/<slug>`

## What it is

The gradebook has two parts:

- A **custom scores grid** per course (learners × assessments) where you create
  your own assessments — title, category, points, due date — and enter scores by
  hand. Sapiqo computes each learner's overall percent and a letter grade, and
  exports the grid to CSV.
- An **auto-graded quiz results** table that lists every learner's score,
  pass/fail, and attempts on the knowledge-check quizzes built into pages.

The two are complementary: quizzes are graded automatically by the LMS; the
scores grid is for everything you grade yourself (speaking tasks, projects,
participation, etc.).

## How to use it

**Open a course's scores grid:**

1. Go to **Admin → Gradebook** (`/admin/gradebook`).
2. Under **Scores grids**, click the course card (**Open the scores grid →**).

**Create or edit an assessment (a column):**

1. In the **Add / edit an assessment** card, fill in:
   - **Title** (required, e.g. *Speaking Task*)
   - **Category** (optional, e.g. *Participation*)
   - **Points** (required, default `100`, step `0.5`)
   - **Due (optional)** date
2. Click **Save**. To edit an existing assessment, click the **✎** in its
   column header — the form is pre-filled and scrolls into view — then Save.
3. Delete an assessment with the **🗑** in its header (confirms; removes its
   scores too).

**Enter scores:**

1. Type a number into any cell in the **Scores** table.
2. It **saves automatically** when you leave the cell — a green border confirms
   the save, red means it failed. Leave a cell **blank to clear** that score.
3. The learner's **Overall %** and letter grade update live in the last column.

**Export:**

- Click **Export CSV** on the grid for that course, or on the main Gradebook
  page for the quiz-results table (with an optional course filter).

## Options & behavior

- **Grid rows** are the course's enrolled learners (admins excluded), sorted by
  last then first name; each row shows name and email.
- **Grid columns** are your assessments in their saved order, each showing its
  title, `/max points`, and category.
- **Score cells** are numeric inputs bounded to `0 … max_points` (step `0.5`).
  Saving posts to `POST /api/gradebook/score`; a blank value deletes the score.
- **Overall %** = total points earned ÷ total possible, **counting only
  assessments the learner has a score on**. Unscored assessments don't drag the
  average down. If nothing is scored, it shows **—**.
- **Letter grade** comes from the grade scale, default
  `A ≥ 90, B ≥ 80, C ≥ 70, D ≥ 60`, else **F**. Administrators can override it
  with the `grade_scale` site setting (format `A:90,B:80,C:70,D:60`). The active
  scale is printed above the grid.
- **CSV export (grid):** columns are Last name, First name, Email, one column per
  assessment (headed `Title (/max)`), Overall %, and Grade. Filename
  `gradebook-<slug>.csv`.
- **Empty states:** if no one is enrolled, or no assessments exist yet, the grid
  shows a prompt instead of a table.
- **Auto-graded quiz results table** (main Gradebook page): Course, Learner,
  Quiz, Score (`score/total (%)`), Result (Passed / Not yet), Attempts, and
  Updated (UTC). Filter by course with the dropdown; **Export CSV** downloads
  `sapiqo-gradebook.csv` (up to 5000 rows).

## How it works

- Assessments and scores are stored in the `assessments` and
  `assessment_scores` tables (see `app/gradebook.php`), separate from the
  file-based course content — so re-running a Markdown build never touches
  grades.
- `gb_add_assessment()` / `gb_update_assessment()` manage columns;
  `gb_set_score()` upserts (or, for a null value, deletes) one learner's score.
- `gb_overall()` sums points/possible over scored items only; `gb_letter()`
  maps a percent through `gb_grade_scale()`.
- Score edits are AJAX (`/api/gradebook/score`), CSRF-protected and
  admin-restricted; the response returns the recomputed overall + letter so the
  row updates without a reload.
- The **quiz results** table is a separate read over the `quiz_results` table
  (score, total, passed, attempts) joined to users and courses — populated
  automatically when learners submit page quizzes.
- Assessment add/edit/delete actions are recorded in the audit log
  (`gradebook.assessment`, `gradebook.assessment_delete`).

## Tips & gotchas

- **The scores grid and quiz results are independent.** A page quiz's pass/fail
  lives in the quiz-results table and drives completion/badges; it does **not**
  appear as a column in the custom scores grid, and grid scores don't affect
  quiz completion.
- **Overall % ignores unscored assessments** — a learner with one graded item at
  100% shows 100% overall even if other columns are blank. Enter every score you
  want counted.
- **Only enrolled learners appear** as rows. Enroll people first, then grade.
- **Blank clears, it doesn't zero.** To count a missing item as zero, type `0`.
- Grid access is **admin-only** (`require_admin`); a content-editing course
  developer without admin rights may not see the grid — coordinate with an
  administrator.
- Letter grades follow the **site-wide** `grade_scale`; there's no per-course
  scale override.

## Related

- [Visual block editor](10-visual-block-editor.md) — page knowledge-check quizzes
- [Markdown authoring](14-markdown-authoring.md) — `@quiz` blocks
- [Course settings](11-course-settings.md) — CPE hours on completion
