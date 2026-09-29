# Resource center

**Audience:** course developer
**Where:** Admin → Courses (`/admin/courses`) → ✏ Edit → **Resources** (top bar) → `/admin/resources/<slug>`; also the **Pick / upload** buttons and **Upload new…** inside the editor's resource picker

## What it is

The resource center is a per-course file library. Every file you upload lives
under that course's `media/` folder, keeps the course self-contained, and can be
reused in any block through the picker. It handles images, videos, PDFs, Office
documents, and caption files.

There are two ways in:

- The dedicated **Resources** page (`/admin/resources/<slug>`) — a gallery for
  bulk uploading, previewing, opening, and deleting files.
- The **resource picker** inside the editor — a modal that lets you choose an
  existing file or upload a new one directly into an Image, Video, or File block.

## How to use it

**From the Resources page:**

1. Open the course in the editor and click **Resources** in the top bar.
2. Click **Choose files…** and select one or more files. They upload one at a
   time with a progress count, then the page reloads.
3. Browse uploads grouped by type (Images, Videos, PDFs, Documents, Captions,
   Other files). Each card shows a thumbnail/icon, label or filename, size, and
   its course-relative path (e.g. `media/worksheet.pdf`).
4. Use **Open** to view a file in a new tab, or **Delete** to remove it (you're
   warned that pages referencing it will show a broken link).

**From inside a block (editor):**

1. Add an **Image**, **Video / embed** (Self-hosted file mode), or **File**
   block.
2. Click **Pick / upload** next to the URL field.
3. In the picker, either click an existing file to insert it, or click
   **Upload new…** to upload one — it's inserted into the block automatically.

## Options & behavior

- **Allowed file types** (by extension): `png`, `jpg`, `jpeg`, `gif`, `webp`,
  `svg`, `mp4`, `webm`, `m4v`, `mov`, `vtt`, `srt`, `pdf`, `doc`, `docx`, `ppt`,
  `pptx`, `xls`, `xlsx`, `odt`, `txt`, `csv`. Anything else is rejected with
  *"That file type is not allowed."*
- **Kind detection** (drives icons and picker filtering):
  - image → `png/jpg/jpeg/gif/webp/svg`
  - video → `mp4/webm/m4v/mov`
  - caption → `vtt/srt`
  - pdf → `pdf`
  - doc → `doc/docx/ppt/pptx/xls/xlsx/odt/txt/csv`
  - file → anything else allowed
- **Where files land:** everything goes under the course's `media/` directory;
  **videos are stored in `media/videos/`**. The stored value in a block is the
  relative path (e.g. `media/videos/intro.mp4`).
- **Duplicate names** are auto-suffixed (`worksheet.pdf`, `worksheet-2.pdf`, …)
  so an upload never overwrites an existing file.
- **Picker filtering:** the Image block's picker shows only images; the
  Self-hosted Video picker shows only videos; the File block's picker shows
  **all** resource types. Uploading through the picker inserts the file into the
  block immediately.
- **Labels:** the Resources page shows a human label when one is set (otherwise
  the filename). Labels are stored in `media/resources.json` keyed by path; the
  editor's inline upload stores files without a label by default.
- **Deleting** removes the file from disk and clears its label metadata. It is
  confined to the course's `media/` tree (no path traversal); pages still
  pointing at a deleted file will render a broken link.

## How it works

- Uploads post to `POST /api/editor/<slug>/upload` (multipart, CSRF-protected).
  The server validates the extension, sanitizes the base filename, routes videos
  into `media/videos/`, de-duplicates the name, and returns the relative path
  plus a full URL.
- The resource list comes from `editor_resources()`, which recursively walks the
  course's `media/` folder and reports each file's path, name, kind, size, and
  label (skipping `resources.json` itself).
- Deletes post to `POST /api/editor/<slug>/resource/delete` and are validated to
  stay inside `media/` via `realpath` checks.
- Because files live inside the course folder, they travel with the course when
  it's exported/packaged and are served from `/courses/<slug>/media/…`.
- Uploads and deletes are recorded in the audit log
  (`course.resource_upload`, `course.resource_delete`).

## Tips & gotchas

- **Upload once, reuse anywhere.** A file in the resource center can be inserted
  into as many blocks (and pages) as you like via the picker.
- **Deleting a resource does not update pages** that reference it — those blocks
  will show a broken image/link until you fix them.
- For **captions**, upload the `.vtt`/`.srt` next to (or after) the video; the
  command-line builder's `--transcribe` flag can also generate captions for
  local videos automatically (see Markdown authoring).
- Prefer uploading video through the resource center and using the **Self-hosted
  file** video mode; for platform-hosted video (YouTube, Vimeo, Drive, etc.) use
  the **Embed / link** mode instead — those aren't files you upload here.
- The **course-relative path** shown on each card (e.g. `media/pic.png`) is
  exactly what you can paste into a block's URL field if you'd rather not use the
  picker.
- Very large uploads are bounded by the server's `upload_max_filesize` /
  `post_max_size`; if an upload fails silently, check those PHP limits.

## Related

- [Visual block editor](10-visual-block-editor.md) — Image, Video, and File blocks
- [Markdown authoring](14-markdown-authoring.md) — local video + `--transcribe`
- [Course settings](11-course-settings.md)
