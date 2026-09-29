# Taking a course

**Audience:** learner
**Where:** Dashboard, Catalog (top navigation → Catalog, or /catalog), and the
course reader at /learn/<slug>

## What it is
A Sapiqo course is a set of modules, each containing lessons (and optional bonus
topics and knowledge-check quizzes). You work through it in the course reader — a
full-screen player with a module/lesson sidebar, a progress bar, lesson content,
embedded videos, and any resources the author added. Finishing every trackable
step completes the course and earns your badge.

Your progress is saved to your account, so you can stop on one device and pick up
where you left off on another.

## How to use it
1. From the **Catalog**, find a course. Choose **Enroll & start** (or
   **Continue** if you are already enrolled) to open it.
2. Alternatively, from your **Dashboard**, choose **Start**, **Resume**, or
   **Review** on any course you are enrolled in.
3. The reader opens on the course home, showing the modules and a
   **Start / Resume / Review** button. Select a module card or a lesson in the
   left sidebar to begin.
4. Read the lesson. Watch any embedded videos and open any bonus **Topics in this
   lesson** (each is an expandable item).
5. When you finish a lesson, choose **Mark lesson complete**. For bonus topics,
   choose **Mark topic complete**. If the lesson has a knowledge check, take the
   quiz and pass it.
6. Use **Previous** and **Next** at the bottom of a lesson, or the sidebar, to
   move between lessons.
7. Watch the **progress bar** in the sidebar climb toward 100%. When you reach
   100%, your badge is issued and a "Course complete!" message appears with a
   link to download your badge.

## Options & behavior
- **Enroll & start / Continue** (Catalog) — enrolls you if needed and opens the
  course. Enrollment also happens automatically the first time you open a course.
- **Start / Resume / Review** (Dashboard) — the label reflects your state: not
  started, in progress, or already completed. **Resume** jumps you back to the
  last lesson you were viewing.
- **Sidebar** — lists modules and their lessons. A green check marks completed
  items; each module shows a "done / total" count. A ▶ badge shows how many
  videos a lesson contains.
- **Mark lesson complete / Mark topic complete** — records that step. Selecting it
  again un-marks it. Bonus topics count toward your overall progress but do not
  block you from advancing.
- **Knowledge check** — a quiz inside a lesson. You must pass it (default 70%) for
  that lesson to count as fully complete. See
  [Quizzes and assessments](03-quizzes-and-assessments.md).
- **Previous / Next** — move between lessons. In sequential mode the **Next**
  button shows a lock and is disabled until the current lesson is complete.
- **Prerequisites** — if a course requires another course first, the Catalog
  shows a "🔒 Requires <course>" pill and a **Start prerequisite** button, and the
  reader will not open until you have completed the required course.
- **Unavailable courses** — if a course is retired, it shows an **Unavailable**
  pill on your dashboard and cannot be opened, but any badge or certificate you
  already earned stays on your dashboard and transcript.

### Sequential (locked-order) mode
Some courses are set to sequential mode. When on:
- Each lesson stays **locked** (shown with a lock icon in the sidebar) until the
  previous lesson — and its knowledge check, if any — is complete.
- The **Next** button and locked lesson cards will not let you jump ahead; a
  brief "🔒 Locked" notice appears if you try.
- Because you must finish in order, the badge and certificate are only reachable
  once you complete the whole course in sequence.

Bonus topics do not gate progression — you can advance without opening them,
though they still count toward your overall percentage.

### Videos and resources
- Videos are embedded directly in the lesson (or topic) content. Self-hosted
  videos support seeking/scrubbing, and captions display when the author provided
  them. Embedded videos (for example from a hosting service) play in place.
- Some lessons are "a set of resources" — instead of prose, they present items to
  open one at a time under **Topics in this lesson**.

## How it works
Every lesson, bonus topic, and knowledge check is a trackable "step." Your
completion percentage is `completed steps ÷ total steps`, capped at 100%. When
you mark a step complete (or pass a quiz), the reader records it locally and, when
you are signed in, mirrors it to the server so it counts toward your badge and
follows you across devices. On load, the server's record is the source of truth
and hydrates the reader.

Course content and media are served from the same origin as Sapiqo and are gated:
signed-out visitors can only see a syllabus preview (module and lesson titles, no
lesson content and no media). Signed-in learners get the full course, but quiz
answer keys are stripped before the course data reaches your browser — grading
happens on the server. Sequential mode is applied by the server as a course
setting the reader honors.

Reaching 100% issues your badge, marks the enrollment complete, and can trigger a
congratulations notification (and email, if configured).

## Tips & gotchas
- You are enrolled automatically the first time you open a course from the
  Catalog or a course link.
- If a course won't open and mentions a prerequisite, complete that course first.
- In sequential mode, completing a lesson (and passing its quiz) immediately
  unlocks the next one — no page reload needed.
- Marking a lesson complete is separate from passing its quiz; a lesson with a
  knowledge check counts as fully done only when both are done.
- Your "resume" position tracks the last lesson you viewed, so use **Resume** from
  the dashboard to jump straight back in.

## Related
- [Quizzes and assessments](03-quizzes-and-assessments.md)
- [Discussion forums](04-discussion-forums.md)
- [Badges, certificates, and your transcript](05-badges-certificates-transcript.md)
- [Accounts and signing in](01-accounts-and-signing-in.md)
