# Quizzes and assessments

**Audience:** learner
**Where:** Inside a lesson in the course reader (/learn/<slug>) — shown as a
**Knowledge check**

## What it is
A knowledge check is a short quiz attached to a lesson. It confirms you
understood the material before the lesson counts as fully complete. Questions can
be single-choice, multiple-choice ("choose all that apply"), or short-answer.
Every quiz has a passing score (70% by default), and passing counts that lesson's
quiz toward course completion and your badge.

Grading happens on the server, and the correct answers are never sent to your
browser — so you can trust the result and can't peek at the key.

## How to use it
1. Scroll to the **Knowledge check** section at the end of a lesson.
2. Read the passing requirement shown under the title, for example "You need 70%
   to pass" (it also notes how many questions and how many attempts are allowed,
   when limited).
3. Answer each question:
   - **Single choice** — select one option.
   - **Choose all that apply** — check every correct option.
   - **Short answer** — type your response in the text box.
4. Choose **Submit answers**. You must answer every question first, or you'll be
   asked to complete them.
5. Read your result: your score, percentage, and per-question feedback (✓ Correct
   or ✗ Not quite, plus any note the author added).
6. If you passed, the check is marked complete. If not, review the feedback and
   choose **Retake quiz** to try again.

## Options & behavior
- **Passing score** — shown as "You need X% to pass" (default 70%). You pass when
  your percentage is at or above it.
- **Question types** — single choice, multiple choice (choose all that apply), and
  short answer.
- **Attempts** — if the author set a limit, it is displayed ("N attempts
  allowed") and your result shows "Attempt X of N." Once you have used all
  attempts without passing, submitting returns "No attempts remaining." Once you
  have passed, further retakes are not blocked by the limit.
- **Question bank / pick-N** — some quizzes present a random subset of their
  questions each attempt; the meta line shows how many questions you'll get.
- **Shuffled options** — some quizzes randomize the order of answer options.
- **Per-question feedback** — after grading, each question is marked correct or
  incorrect with any author-provided explanation.
- **Retake quiz** — appears after a first submission (and after passing) so you
  can attempt again, subject to any attempt limit.

## How it works
When you submit, the reader sends your answers to the server, which grades them
against the answer key stored on the server and returns your score, pass/fail,
and per-question feedback. The correct-answer flags and accepted short answers are
stripped out of the course data before it ever reaches your browser, so grading
is authoritative on the server side.

Short answers are matched leniently: case, surrounding spaces, extra internal
spaces, and trailing punctuation are ignored. Multiple-choice questions require
your selected set to match the correct set exactly.

Each submission is recorded and increments your attempt count. Passing a
knowledge check marks that quiz's step complete, which counts toward your course
percentage — and if it takes you to 100%, it issues your badge on the spot. A
later failed retake does not un-complete a check you have already passed.

## Tips & gotchas
- You must answer every question shown before you can submit.
- For "choose all that apply," partial selections are marked wrong — select every
  correct option.
- If attempts are limited, use them thoughtfully; the count is shown with your
  result.
- If you get a random subset (pick-N), retaking may present different questions.
- Passing is what makes a lesson with a quiz count as fully complete — marking the
  lesson complete alone is not enough.

## Related
- [Taking a course](02-taking-a-course.md)
- [Badges, certificates, and your transcript](05-badges-certificates-transcript.md)
