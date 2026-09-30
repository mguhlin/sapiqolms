<?php /** @var array $courses @var string $mode */ $mode = $mode ?? 'courses'; $isCert = $mode === 'certifications'; ?>
<div class="page-head">
  <h1><?= $isCert ? 'Certifications' : 'Courses' ?></h1>
  <p><?= $isCert
      ? 'Manage certification programs only. Hidden certifications keep all learner badges and progress.'
      : 'Manage the course catalog (certifications are managed separately). Hidden courses keep all learner badges and progress.' ?></p>
</div>

<div class="card">
  <div class="toolbar" style="margin-bottom:14px">
    <form method="post" action="<?= e(url('/admin/editor-new')) ?>" style="display:inline-flex;gap:6px;align-items:center">
      <?= csrf_field() ?>
      <input name="title" placeholder="New course title" required
             style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
      <button class="btn btn-gold" type="submit">Create (visual)</button>
    </form>
    <a class="btn btn-outline" href="<?= e(url('/admin/create')) ?>" title="Author with Markdown instead">Markdown editor</a>
    <form method="post" action="<?= e(url('/admin/courses/rescan')) ?>" style="display:inline">
      <?= csrf_field() ?>
      <button class="btn btn-outline" type="submit">Rescan drop-in folder</button>
    </form>
    <a class="btn btn-outline" href="<?= e(url('/admin/import')) ?>" title="Import course packages, Common Cartridge, SCORM, and users">Import courses / users</a>
    <form method="post" action="<?= e(url('/admin/expire')) ?>" style="display:inline"
          data-confirm="Run the enrollment-expiry sweep now?">
      <?= csrf_field() ?>
      <button class="btn btn-outline" type="submit">Run expiry sweep</button>
    </form>
  </div>

  <div class="bulkbar" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:6px 0 4px;padding:10px 12px;background:var(--surface-alt);border-radius:10px">
    <strong style="font-size:.9rem">Import split parts:</strong>
    <label class="sr-only" for="splitParts">Select all exported parts</label>
    <input id="splitParts" type="file" multiple accept=".part001,.part002,.part,application/octet-stream">
    <button class="btn btn-outline btn-sm" type="button" id="splitImportBtn">Upload &amp; install</button>
    <span class="muted" id="splitStatus" aria-live="polite"></span>
  </div>
  <input type="hidden" id="csrfHolder" value="<?= e(csrf_token()) ?>">

  <div class="table-wrap">
    <table class="table">
      <caption class="sr-only">All courses with status and actions</caption>
      <thead><tr>
        <th scope="col">Course</th><th scope="col">Status</th><th scope="col">Units</th>
        <th scope="col">Enrolled</th><th scope="col">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$courses): ?>
        <tr><td colspan="5" class="muted">No courses yet. Click “Create a course” or drop a folder into the courses directory.</td></tr>
      <?php else: foreach ($courses as $c): ?>
        <tr>
          <td>
            <strong><?= e($c['title']) ?></strong><br>
            <span class="muted"><?= e($c['slug']) ?></span>
          </td>
          <td>
            <?php if ((int)$c['active'] === 1): ?>
              <span class="pill pill--done">Available</span>
            <?php else: ?>
              <span class="pill pill--progress">Hidden</span>
            <?php endif; ?>
            <?php if (($c['status'] ?? 'published') === 'draft'): ?><br><span class="pill pill--progress" style="margin-top:4px;display:inline-block">Draft</span><?php endif; ?>
            <?php if (!empty($c['is_certification'])): ?><br><span class="pill" style="background:#fdf3d3;color:#8a6d1a;margin-top:4px;display:inline-block">Certification</span><?php endif; ?>
          </td>
          <td><?= (int)$c['total_units'] ?></td>
          <td><?= (int)$c['enrolled_count'] ?></td>
          <td class="course-actions" style="white-space:nowrap">
            <a class="btn btn-navy btn-sm" href="<?= e(url('/admin/editor/' . urlencode($c['slug']))) ?>" title="Visual editor: pages, blocks, media, quizzes, and course settings">✏ Edit</a>
            <?php if ((int)$c['active'] === 1): ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('/courses/' . $c['slug'] . '/')) ?>" target="_blank" rel="noopener">Open</a>
            <?php endif; ?>
            <details class="kebab">
              <summary class="btn btn-outline btn-sm" title="More actions" aria-label="More actions">⋯</summary>
              <div class="kebab-menu">
                <a href="<?= e(url('/admin/courses/' . $c['slug'] . '/checklist')) ?>">Publication checklist</a>
                <a href="<?= e(url('/admin/assignments/' . $c['slug'])) ?>">Assignments and rubrics</a>
                <a href="<?= e(url('/admin/editor/' . urlencode($c['slug']) . '#settings')) ?>">⚙ Course settings</a>
                <form method="post" action="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/status')) ?>" style="margin:0">
                  <?= csrf_field() ?>
                  <button type="submit" style="all:unset;display:block;width:100%;box-sizing:border-box;padding:8px 12px;cursor:pointer">
                    <?= (($c['status'] ?? 'published') === 'draft') ? '📢 Publish (show to learners)' : '📝 Move to Draft (hide from learners)' ?>
                  </button>
                </form>
                <?php if ($c['has_source']): ?>
                  <a href="<?= e(url('/admin/create?slug=' . urlencode($c['slug']))) ?>">Edit Markdown source</a>
                  <a href="<?= e(url('/admin/create?clone=' . urlencode($c['slug']))) ?>">Clone</a>
                <?php endif; ?>
                <div class="kebab-sep">Export</div>
                <a href="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/lms-export')) ?>">To another LMS (Common Cartridge)</a>
                <a href="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/export')) ?>">Package (.tar)</a>
                <a href="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/export-json')) ?>">Course data (.json)</a>
                <a href="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/export-split')) ?>">Split (large uploads)</a>
                <div class="kebab-sep"></div>
                <form method="post" action="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/toggle')) ?>"
                      data-confirm="<?= (int)$c['active']===1 ? 'Hide this course from the catalog? Learner badges and progress are kept.' : 'Make this course available again?' ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="kebab-danger"><?= (int)$c['active']===1 ? 'Hide from catalog' : 'Restore to catalog' ?></button>
                </form>
                <?php if (is_admin()):
                  $warn = "Delete \"" . $c['title'] . "\" permanently?\n\n"
                    . "Consider “Hide from catalog” or “Move to Draft” instead — those keep the course and are fully reversible.\n\n"
                    . ((int)$c['enrolled_count'] > 0
                        ? (int)$c['enrolled_count'] . " learner(s) are enrolled — their enrollment and progress will be removed (earned badges/certificates stay on their transcripts).\n\n"
                        : "")
                    . "This deletes the course and its files for good and cannot be undone."; ?>
                <form method="post" action="<?= e(url('/admin/courses/' . urlencode($c['slug']) . '/delete')) ?>"
                      data-confirm="<?= e($warn) ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="kebab-danger">🗑 Delete permanently…</button>
                </form>
                <?php endif; ?>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted" style="margin-top:10px">To permanently delete a course, remove its folder from the courses directory
    (learner badges and progress remain). “Hide” is reversible and keeps the folder in place.</p>
  <div class="muted" style="margin-top:6px;font-size:.86rem">
    <strong>Export formats (per course):</strong>
    <code>.tar</code> — full package (content + media) to move between Sapiqo servers or archive ·
    <code>.imscc</code> — IMS Common Cartridge to import into Canvas / Blackboard / Moodle / Sakai ·
    <code>.json</code> — the raw course data (structure, lessons, quizzes) ·
    <strong>Split</strong> — the .tar in small parts for limited-upload servers.
    (LearnDash uses a WordPress-specific format, not a standard export — ask if you need a converter.)
  </div>
  <p class="muted">Export/import moves a whole course (content + media) as a <code>.tar</code>. Very large
    courses (e.g. hundreds of MB of video) may exceed the web upload limit — use <strong>Split</strong>
    to export in parts and <strong>Import split parts</strong> to re-install them, copy the folder in
    directly and click “Rescan”, or raise <code>upload_max_filesize</code>/<code>post_max_size</code>.</p>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
// Close any open ⋯ menu when opening another or clicking outside.
document.addEventListener('click', function (e) {
  document.querySelectorAll('details.kebab[open]').forEach(function (d) {
    if (!d.contains(e.target)) d.removeAttribute('open');
  });
});
</script>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
  var btn = document.getElementById("splitImportBtn"),
      input = document.getElementById("splitParts"),
      status = document.getElementById("splitStatus"),
      csrf = document.getElementById("csrfHolder").value;
  if (!btn) return;
  var PART_URL = <?= json_encode(url('/admin/import/part')) ?>,
      ASSEMBLE_URL = <?= json_encode(url('/admin/import/assemble')) ?>;

  function baseName(files) {
    // Strip a trailing .partNNN to recover the original archive name.
    var n = files[0].name.replace(/\.part\d+$/i, "");
    return n || "course.tar";
  }
  function token() { var s = ""; for (var i = 0; i < 12; i++) s += "0123456789abcdef"[Math.floor(Math.random() * 16)]; return s; }

  btn.addEventListener("click", function () {
    var files = Array.prototype.slice.call(input.files);
    if (!files.length) { status.textContent = "Choose the exported part files first."; return; }
    files.sort(function (a, b) { return a.name.localeCompare(b.name, undefined, { numeric: true }); });
    var tk = token(), i = 0;
    btn.disabled = true;

    function next() {
      if (i >= files.length) return finish();
      status.textContent = "Uploading part " + (i + 1) + " of " + files.length + "…";
      var fd = new FormData();
      fd.append("_csrf", csrf); fd.append("token", tk); fd.append("seq", String(i + 1)); fd.append("chunk", files[i]);
      fetch(PART_URL, { method: "POST", body: fd, credentials: "same-origin", headers: { "Accept": "application/json" } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (!d.ok) throw new Error(d.error || "part failed"); i++; next(); })
        .catch(function (e) { btn.disabled = false; status.textContent = "⚠ " + e.message; });
    }
    function finish() {
      status.textContent = "Assembling and installing…";
      var f = document.createElement("form");
      f.method = "POST"; f.action = ASSEMBLE_URL;
      [["_csrf", csrf], ["token", tk], ["name", baseName(files)]].forEach(function (kv) {
        var inp = document.createElement("input"); inp.type = "hidden"; inp.name = kv[0]; inp.value = kv[1]; f.appendChild(inp);
      });
      document.body.appendChild(f); f.submit();
    }
    next();
  });
})();
</script>
