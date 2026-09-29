# Markdown authoring

**Audience:** course developer
**Where:** command line — `python3 creator/build_course.py <file>.md`; publish via Admin → Courses → **Rescan drop-in folder** (`/admin/courses`). Browser alternative: Admin → Courses → **Markdown editor** (`/admin/create`)

## What it is

The Course Creator turns a **single Markdown file** into a complete, ready-to-run
course folder — no import, no database, no server needed at build time. You write
one `.md` file with light structure and directives, run one command, and the
course is generated and auto-discovered by Sapiqo.

It is fully self-contained and dependency-free: a small built-in Markdown
renderer (no pip packages) covers headings, paragraphs, lists, blockquotes,
rules, bold/italic/code, links, and images. Images referenced by URL are
downloaded locally and local files are copied in, so the course stays offline and
portable.

The `creator/` folder contains:

- `build_course.py` — the builder (Markdown → course folder)
- `template.md` — an annotated starting point to copy
- `sample-course.md` — a tiny working example
- `README.md` — the reference for the syntax

## How to use it

1. Copy `creator/template.md` to `my-course.md` (anywhere).
2. Edit the **front matter** at the top (between `---` lines): `title`, `slug`,
   `tagline`, and optional `badge`, `tags`, `about`.
3. Structure the body with headings and directives (see Options & behavior).
4. Build it:
   ```bash
   python3 creator/build_course.py my-course.md
   python3 creator/build_course.py my-course.md --transcribe   # + captions for local videos
   ```
5. Publish it: it appears in the LMS on the next course scan, or immediately when
   you click **Admin → Courses → Rescan drop-in folder**.
6. To revise, edit the `.md` and re-run the build — it rebuilds in place. The
   source is saved into the course as `source.md` for future rebuilds.

**No command line?** Signed-in admins can author in the browser at
**Admin → Courses → Markdown editor** (`/admin/create`): a split-pane Markdown
editor with live preview and a **Publish** button that builds and catalogs the
course instantly, using the same syntax. The command-line builder is still the
way to attach **local video files + auto captions** (`--transcribe`); the
in-browser editor supports platform-hosted video and image/video URLs.

## Options & behavior

- **Front matter** (`title`, `slug`, `tagline`, `tags`, `badge`, `about`):
  `slug` becomes the folder name and URL (lowercase-with-dashes; derived from the
  title if omitted). `about` becomes the course-home overview. `badge` points at
  an image next to the `.md`; without one the course uses the generic medallion.
- **Structure by heading level:**
  - `# ` → a **module**. Titles are given smart labels: *Welcome* / *Start…* →
    "Start Here"; a title containing *Certificate* / *Badge* → "Finish";
    *Module N* → "Module N".
  - `## ` → a **lesson** (page) inside the current module.
  - `### ` / `#### ` → headings **inside** a lesson.
  - Normal Markdown: paragraphs, `**bold**`, `*italic*`, `` `code` ``, `-`/`1.`
    lists, `> quotes`, and `![alt](img)` images.
- **Directives** (each on its own line):
  - `@video <url>` — a **Vimeo** URL, a **YouTube** URL, or `@video local:clip.mp4`
    for a local file (copied in; add `--transcribe` for captions). YouTube/Vimeo
    render as privacy-friendly responsive iframes; local files render an HTML5
    player.
  - `@embed <url>` — general embed for Vimeo/YouTube, Google Slides/Docs/Drive,
    or a direct `.mp4`/`.webm` URL (inline player/iframe); anything else becomes
    a plain link.
  - `@resource [Title](https://…) optional note` — grouped into a **Resources**
    list at the end of the lesson.
  - `@image path-or-url "alt"` — an image with alt text.
  - `@quiz` … `@endquiz` — a knowledge check.
- **Quiz syntax** (between `@quiz` and `@endquiz`):
  - Options: `pass: 70` (percent to pass), `shuffle: true` (randomize option
    order), `pick: 5` (ask a random N-question subset — a question bank),
    `attempts: 3` (max tries).
  - Question types:
    - `Q: question?` (add `(multiple)` for multi-select) with `- choice` /
      `- *correct` lines (a `*` prefix marks the correct option). You can also
      mark answers with `= exact option text`.
    - `TF: statement` then `= true` or `= false`.
    - `SA: question?` then one or more `= accepted answer` lines (matched
      case/whitespace-insensitively).
    - `feedback: explanation` after a question (shown once answered).
  - Grading is **server-side** in the LMS and answer keys are stripped from the
    data sent to learners' browsers. A learner must **pass** for the quiz to
    count toward completion / the badge.
- **`--transcribe`** generates English captions (`.vtt`) for local videos using
  OpenAI Whisper (`WHISPER_BIN`, default `whisper`,
  model `turbo`) and attaches a `<track>` to each local video embed.

## The output folder

Running the builder writes `courses/<slug>/` (under the `content/` folder, or
`SAPIQO_COURSES` if set) containing:

- **`course.json`** — the compiled course: front matter, `stats` (modules,
  lessons, videos, quizzes…), and `modules[] → lessons[]` with `content` HTML,
  `videos`, and any `quiz`.
- **`index.html`** — the reader shell that renders the course at
  `/courses/<slug>/`.
- **`media/`** — mirrored images; **`media/videos/`** — copied local videos (and
  `.vtt` captions when transcribed).
- **`badge.png`** — if you provided one.
- **`source.md`** — a copy of your Markdown, kept for re-builds.

The console prints the output path and a per-module summary, ending with:
*"Drop-in ready — it will appear in the LMS on the next course scan."*

## How it works

- `build_course.py` parses front matter, splits the body into modules/lessons by
  heading, expands directives, mirrors media, and renders each lesson's HTML with
  the built-in Markdown converter.
- Videos are detected by provider (YouTube/Vimeo) or treated as local; `@embed`
  additionally handles Google Docs/Drive and direct video URLs.
- The result is written as `course.json` plus the reader shell, mirrored media,
  and badge.
- Sapiqo **auto-discovers** any subfolder of the courses directory that contains
  a `course.json`. **Rescan drop-in folder** runs `scan_courses()`, registering
  new courses, reactivating restored ones, and deactivating removed ones — and
  reports how many of each.
- The in-browser **Markdown editor** (`/admin/create` → Publish) uses the same
  syntax and builds + catalogs the course in one step.

## Tips & gotchas

- **Re-running rebuilds in place.** Editing the `.md` and running the builder
  again regenerates the folder; the stored `source.md` also lets you re-edit a
  course from **Admin → Courses → ⋯ → Edit Markdown source**.
- **Keep media local.** URL images are downloaded and local paths copied, so the
  course stays offline/portable — reference files relative to the `.md`.
- **Markdown vs. the visual editor.** A course built here can also be opened in
  the visual block editor, but note that saving there switches it to
  block-authored (`course.json` becomes the source of truth); mixing workflows on
  the same course can be confusing. Pick one as your primary.
- **`--transcribe` needs Whisper installed** at `WHISPER_BIN`; without it the
  flag is skipped with a warning and no captions are generated.
- If a new course doesn't show up, confirm its folder is under the courses
  directory (`content/`, or `SAPIQO_COURSES`) with a valid `course.json`, then
  click **Rescan drop-in folder**.
- For **imported LearnDash** courses use `scripts/build.py` instead; this creator
  is for authoring new courses by hand.

## Related

- [Visual block editor](10-visual-block-editor.md)
- [Resource center](12-resource-center.md)
- [Gradebook](13-gradebook.md) — quiz results from `@quiz` blocks
- [Course settings](11-course-settings.md)
