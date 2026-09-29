# Enrollment expiry and reminders

**Audience:** administrator
**Where:** Per course — Admin → Courses (/admin/courses) → a course's expiry
setting (or the visual editor's **⚙ Settings**). Reminder cadence — Admin →
Settings (/admin/settings). On-demand sweep — Admin → Courses → **Run expiry
sweep** (/admin/expire).

## What it is
Each course can have an **enrollment lifetime** measured in days. When a lifetime
is set, a learner's access to that course ends a fixed number of days after they
enrolled. Before access ends, Sapiqo sends **graduated pre-expiry reminders**
(by default 30, 7, and 1 day out), and when the window passes it **soft-unenrolls**
the learner.

Crucially, expiry is non-destructive to earned credit: **badges, certificates,
CPE hours, and the transcript are kept permanently**, even after access ends.
Progress rows are also kept, so re-enrolling later restores the learner's place.

## How to use it

### Set a course's enrollment lifetime
1. Open Admin → Courses (/admin/courses), or open the course in the visual editor.
2. Set the enrollment lifetime in **days** (in the editor this is the **Expiry**
   field under ⚙ Settings).
3. Save. A value greater than 0 turns on expiry; **0 disables it** (no expiry).

Setting a lifetime immediately recomputes the expiry date for existing, not-yet-
completed enrollments in that course, based on each learner's original enrollment
date. Setting it back to 0 clears all expiry dates for that course.

### Set the reminder cadence
1. Open Admin → Settings (/admin/settings).
2. In **Access-expiry reminders (days before)**, enter a comma-separated list of
   day thresholds. The default is `30,7,1` (a month out, the week of, and the day
   before).
3. Save. The list applies to all courses.

### Run the sweep
Expiry and reminders are applied by a **sweep**, not continuously. Run it:
- **On demand:** Admin → Courses → **Run expiry sweep**. Sapiqo reports how many
  enrollments ended and how many learners were warned.
- **On a schedule (recommended):** run `bin/expire.php` daily via cron. Example:
  ```cron
  0 2 * * *  php /path/to/courses/sapiqo/bin/expire.php
  ```

## Options & behavior
- **Enrollment lifetime (days)** — per course. `> 0` enables expiry; `0` disables
  it. Newly enrolled learners get an expiry date of enrollment time + N days.
- **Recompute on change** — changing a course's lifetime rewrites `expires_at`
  for its active (non-completed) enrollments from each learner's `enrolled_at`.
- **Reminder thresholds** — the `expiry_reminder_days` setting, default `30,7,1`.
  Sorted most-distant-first; each threshold is tracked per enrollment so a learner
  gets **each reminder at most once**.
- **Delivery** — reminders go out by **email** (only when mail is configured) and
  as an **in-app notification** linking to the course.
- **Catch-up behavior** — if a sweep runs late and an enrollment has crossed two
  thresholds since the last run, Sapiqo sends a single reminder for the *most
  urgent* threshold, not one per skipped milestone.
- **Soft-unenroll at expiry** — once past `expires_at` (and not completed), the
  enrollment is removed but **progress, badge, certificate, CPE, and transcript
  are retained**. The learner also gets a notification that access has ended.
- **Completed courses are exempt** — enrollments with status `completed` are never
  warned or expired.

## How it works
`set_course_enroll_days()` writes the `enroll_days` column and recomputes
`expires_at` for active enrollments. New enrollments get their `expires_at` set by
`enroll()` at creation time.

`expire_enrollments()` does two passes. First it selects enrollments whose
`expires_at` falls within the widest reminder window, computes days remaining, and
sends the most-urgent newly-crossed reminder — recording it in a per-enrollment
bitmask (`expiry_warned`) so each milestone fires once. Then it selects
enrollments already past `expires_at` (and not completed) and calls the soft
`unenroll()`, which deletes the enrollment row but leaves progress and badges
intact. It returns counts of `removed` and `warned`, which the on-demand sweep
surfaces in a flash message and the audit log (`enrollments.expire`).

Reminder emails explicitly reassure learners that anything already earned — badge,
certificate, CPE hours — stays on their transcript permanently.

## Tips & gotchas
- **Nothing expires without the sweep running.** The dates are computed, but a
  learner is only reminded/unenrolled when `bin/expire.php` (cron) or the
  **Run expiry sweep** button runs. Schedule the daily cron.
- **Set the lifetime to 0** for courses that should never expire (the default when
  unset).
- **Reminders need mail configured** to reach learners by email; in-app
  notifications still appear regardless.
- **Changing the lifetime rebases existing learners** from their original
  enrollment date — it does not restart the clock from "today."
- **"Access ended" is reversible.** Re-enrolling a learner (individually, in bulk,
  or via a group/org subscription) restores their kept progress; a re-earned or
  already-earned badge is not duplicated.
- Because reminders are one-time per milestone, shortening the threshold list
  later won't re-send reminders a learner already received.

## Related
- [Users and roles](20-users-and-roles.md)
- [Organizations and subscriptions](21-organizations-and-subscriptions.md)
- [Groups and managers](22-groups-and-managers.md)
