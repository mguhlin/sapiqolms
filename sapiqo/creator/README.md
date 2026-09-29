# Sapiqo Course Creator

Author a brand-new course from a single **Markdown file** — no import, no
database, no server. Run one command and the course folder is generated and
auto-discovered by the LMS.

> **No command line?** Signed-in admins can author courses right in the browser
> at **Admin → Create a course** (`/admin/create`): a split-pane Markdown editor
> with live preview and a **Publish** button that builds the course and adds it
> to the catalog instantly. It uses the same syntax as this folder. The
> command-line builder below is still the way to attach **local video files +
> Whisper captions** (`--transcribe`); the in-browser editor supports Vimeo and
> image/video URLs.

```
creator/
  build_course.py     the builder (Markdown -> course folder)
  template.md         annotated starting point — copy this
  sample-course.md    a tiny working example
  README.md           this file
```

## Write a course

1. Copy `template.md` to `my-course.md` (anywhere).
2. Edit the front matter (`title`, `slug`, `tagline`, optional `badge`, `tags`, `about`).
3. Structure with headings and directives:
   - `# ` → a **module** (`Welcome` and titles with `Certificate`/`Module N` get smart labels)
   - `## ` → a **lesson**
   - `### ` / `#### ` → headings inside a lesson
   - normal Markdown: paragraphs, `**bold**`, `*italic*`, `` `code` ``, lists, `> quotes`, `![alt](img)`
   - `@video https://vimeo.com/123`, a **YouTube** URL, or `@video local:clip.mp4`
   - `@embed <url>` — general embed for Vimeo/YouTube, Google Slides/Docs/Drive,
     or a direct `.mp4`/`.webm` URL (renders an inline player/iframe)
   - `@resource [Title](https://…) optional note`
   - `@image path-or-url "alt"`
   - `@quiz` … `@endquiz` — a knowledge check. Quiz options: `pass: 70` (percent
     to pass), `shuffle: true` (randomize option order), `pick: 5` (ask a random
     N-question subset — a question bank), `attempts: 3` (max tries). Question
     types:
     - `Q: question?` (add `(multiple)`) with `- choice` / `- *correct`
     - `TF: statement` then `= true` or `= false`
     - `SA: question?` then one or more `= accepted answer` lines (matched
       case/whitespace-insensitively)
     - `feedback: explanation` after a question (shown once answered)
     A learner must pass for the quiz to count toward completion / the badge;
     grading is server-side in the LMS, and answer keys are stripped from the
     course data sent to learners' browsers.

## Build it

```bash
python3 creator/build_course.py my-course.md            # images mirrored, Vimeo embedded
python3 creator/build_course.py my-course.md --transcribe  # + English captions for local videos (needs whisper)
```

This creates `courses/<slug>/` with `course.json`, the reader `index.html`,
mirrored `media/`, local videos + captions, and `badge.png` (if provided). It
appears in the LMS on the next course scan (or click **Admin → Rescan courses**).
Re-running rebuilds it in place; the original `.md` is saved as `source.md`.

## Notes

- **Dependency-free:** pure Python; a small built-in Markdown renderer (no pip
  packages). Images referenced by URL are downloaded locally; local paths are
  copied — the course stays self-contained/offline.
- **Badge:** drop a `badge.png` next to the `.md` and set `badge: badge.png`, or
  leave it out to use the generic medallion (swap art in later).
- **Captions:** `--transcribe` uses the OpenAI Whisper venv (`WHISPER_BIN`,
  default `whisper`, model `turbo`).
- For imported LearnDash courses, use `../scripts/build.py` instead; this creator
  is for authoring new courses by hand.
