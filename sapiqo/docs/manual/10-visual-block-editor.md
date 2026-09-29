# Visual block editor

**Audience:** course developer
**Where:** Admin → Courses (`/admin/courses`) → ✏ Edit on any course → `/admin/editor/<slug>`

## What it is

The visual block editor is Sapiqo's in-browser, word-processor-style tool for
building a course without touching Markdown or code. A course is a set of
**modules**, each holding **pages** (and optionally **discussion forums**). Each
page is built from stackable **blocks** — text, headings, images, video, files,
and dividers.

- The course is still stored as `course.json`; the editor keeps an editable
  `blocks` array on every page and, on save, **compiles those blocks into the
  HTML** that learners see in the reader.
- Existing courses built from Markdown open cleanly: a page with no blocks is
  wrapped as a single rich-text block holding its current HTML, so you can start
  editing immediately.
- Courses created here are marked as GUI-authored (`"editor": "blocks"` in
  `course.json`).

The screen has three parts: a **top bar** (title, Settings, Resources, Preview,
Save), a left **outline** (modules, pages, forums), and the right **page
editor** where you build blocks.

## How to use it

1. Go to **Admin → Courses** and click **✏ Edit** on the course you want to
   author. To start a brand-new course, type a title in the **New course
   title** box and click **Create (visual)** — you land straight in the editor.
2. Edit the **course title** in the top-bar field (it doubles as the `<title>`).
3. Build your outline in the left panel:
   - Click **＋ Add module** at the bottom of the outline to add a module.
   - Click the **＋** icon in a module's header row to add a page to it.
   - Rename a module inline in its header field; rename a page by editing its
     **Page title** at the top of the page editor.
4. Add content to a page by clicking a block button under the page:
   **＋ Text**, **＋ Markdown**, **＋ Heading**, **＋ Image**, **＋ Video / embed**,
   **＋ File**, or **＋ Divider**.
5. Reorder or remove blocks with the per-block controls: **↑** (move up),
   **↓** (move down), **🗑** (delete block).
6. Reorder pages and modules by dragging (see Options & behavior).
7. Click **Save** (top-right) — or press **Ctrl/Cmd + S** — at any time.
8. Click **Preview ↗** to open the live reader for the course in a new tab.

## Options & behavior

- **Block types** (exact set, in the order they appear on the add bar):
  - **Text** — a rich-text (`richtext`) block with the full formatting toolbar.
  - **Markdown** — a plain textarea; its Markdown is converted to HTML on save.
  - **Heading** — an H2/H3/H4 selector plus the heading text.
  - **Image** — URL/resource picker, alt text, caption, and alignment.
  - **Video / embed** — either an embed/link URL or a self-hosted file.
  - **File** — a downloadable attachment (link text + optional description).
  - **Divider** — a horizontal rule (`<hr>`).
- **Adding modules/pages:** a new course always keeps at least one module; the
  editor blocks deleting the last one. Adding a page selects it immediately.
- **Drag-and-drop reordering:**
  - **Pages** — drag a page row by its **⠿** grip; drop it anywhere within the
    same or another module's page list. A dashed outline shows the drop target.
  - **Modules** — drag the module header's **⠿** grip to reorder whole modules
    (their pages travel with them).
- **Rich-text toolbar** (on every Text block), left to right:
  - **Paragraph** dropdown: Normal, Heading, Subheading, Quote.
  - **Size** dropdown: Small, Normal, Large, X-Large, Huge.
  - **B / I / U / S** — bold, italic, underline, strikethrough.
  - **Text color** and **Highlight** color pickers.
  - **Alignment**: align left, center, right, justify.
  - **Lists**: bulleted (•) and numbered (1.).
  - **Indent**: decrease (⇤) and increase (⇥). Indent adjusts the block's
    left margin in 40px steps rather than wrapping text in a blockquote.
  - **⊞ Table** menu: Insert table…, Add row, Add column, Delete row,
    Delete column, Delete table.
  - **🔗** insert link, **⛓** remove link, **⌫** clear formatting.
  - **↶ / ↷** undo / redo.
- **Clean paste:** pasting from Google Docs or Word keeps meaningful structure
  and formatting (headings, bold/italic, lists, links, tables) but strips
  classes, ids, junk wrappers, Google's fake-bold containers, and all but a
  safe subset of inline styles. If the clipboard has no HTML, plain text is
  inserted.
- **Image resizing:** click an image inside a Text block to show a navy resize
  handle at its bottom-right corner; drag to set the width (min 24px, height
  stays auto).
- **Table column resizing:** hover a table cell's right edge inside a Text
  block (the cursor becomes a column-resize arrow) and drag to size that
  column across all rows.
- **Image block alignment:** chips for **Full width**, **Left**, **Center**,
  **Right**, plus a **Wrap text around image** checkbox that is only enabled
  for Left/Right alignment (wrapped images float at up to 48% width).
- **Video / embed block:**
  - **Embed / link** mode accepts YouTube, Vimeo, Google Drive/Docs,
    OneDrive/SharePoint, Dropbox, or a direct `.mp4` URL. The field hint reads:
    *"YouTube, Vimeo, Google Drive, OneDrive, Dropbox, or .mp4 URL."*
  - **Self-hosted file** mode lets you pick an uploaded video from resources.
  - A note reminds you: *"For OneDrive/Dropbox, use the 'Embed' or share link."*
- **File block:** pick a file (or paste a URL); if you don't set link text, the
  filename is used. Renders as `📎 <name>` with an optional description.
- **Knowledge check (quiz):** below the blocks, tick **Knowledge check (quiz)
  on this page** to add a simple quiz — set a **Pass %**, add questions, add
  options, and tick the correct one(s). A page with a saved quiz shows a
  **✓ quiz** badge in the outline.
- **Deleting a page:** click the **🗑** on the page row (it appears on hover or
  when the page is active). You are asked to confirm; the note warns the delete
  is permanent once you save.
- **Deleting a module:** click the **✗** in the module header. You must keep at
  least one module; deleting one that has pages asks you to confirm the page
  count.

## How it works

- On load, the editor fetches `GET /api/editor/<slug>`, which returns the full
  course structure (with `blocks`), the resource list, and course settings.
- Every edit sets a **dirty** flag and shows *"Unsaved changes."* Leaving the
  page with unsaved work triggers the browser's leave-confirmation prompt.
- **Save** posts the whole structure as JSON to `POST /api/editor/<slug>`. The
  server compiles each block to reader HTML and writes `course.json`:
  - `richtext` → sanitized HTML (scripts, styles, forms, inline event handlers,
    and `javascript:` URIs are removed; only `https://` iframes survive).
  - `markdown` → HTML via the built-in Markdown renderer.
  - `heading` → `<h2>`–`<h4>`.
  - `image` → a `<figure>` with alignment/wrap styles and an optional caption.
  - `video` → a provider embed (YouTube/Vimeo/Drive/OneDrive/Dropbox/MP4) or a
    self-hosted `<video>`; each video is also recorded in the page's `videos`
    array for stats.
  - `file` → a `📎` download link; `divider` → `<hr>`.
- After writing `course.json`, the server ensures a reader shell (`index.html`)
  exists and re-scans courses so the catalog, unit totals, and completion math
  stay in sync.
- Media paths stored relative to the course (e.g. `media/pic.png`) are shown
  with an absolute URL while editing and re-stored relative on save, so images
  render both in the editor and in the reader.

## Tips & gotchas

- **Save often.** Deleting a page or module only becomes permanent when you
  save, but there's no in-editor undo for structure changes — the confirm
  dialogs are your safety net.
- **Ctrl/Cmd + S** saves without reaching for the mouse.
- Use **Text** blocks for most content; reach for **Markdown** only if you
  prefer writing raw Markdown. Note the two are compiled differently.
- The **Heading** block emits a semantic `<h2>`/`<h3>`/`<h4>` for structure and
  accessibility — prefer it over faking headings with big/bold text.
- Pasting from Google Docs/Word is safe and encouraged; the cleaner removes the
  clutter automatically. If a paste still looks off, select it and use
  **⌫ Clear formatting**, then reapply.
- Image and table-column **resize handles only appear inside Text blocks**, not
  on the standalone Image block (which uses alignment chips instead).
- Only **`https://`** iframes/embeds are preserved on save; an `http://` embed
  URL will be dropped by the sanitizer.

## Related

- [Course settings](11-course-settings.md)
- [Resource center](12-resource-center.md)
- [Markdown authoring](14-markdown-authoring.md)
- [Gradebook](13-gradebook.md)
