<?php /** @var string $slug @var string $title @var array $resources */
$fmtSize = fn($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
$icon = ['image' => '🖼', 'video' => '🎬', 'pdf' => '📄', 'doc' => '📝', 'caption' => '💬', 'file' => '📎'];
$byKind = [];
foreach ($resources as $r) { $byKind[$r['kind']][] = $r; }
$order = ['image' => 'Images', 'video' => 'Videos', 'pdf' => 'PDFs', 'doc' => 'Documents', 'caption' => 'Captions', 'file' => 'Other files'];
?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
  <div>
    <h1>Resources — <?= e($title) ?></h1>
    <p><a href="<?= e(url('/admin/editor/' . urlencode($slug))) ?>">&larr; Back to the editor</a> ·
       <?= count($resources) ?> file<?= count($resources) === 1 ? '' : 's' ?></p>
  </div>
</div>

<div class="card">
  <h2>Upload files</h2>
  <p class="muted">Images, videos (MP4/WebM), PDFs, Office docs, and captions. Uploaded files are available to attach as
    blocks in the course editor.</p>
  <form id="rcForm">
    <label class="btn btn-gold">Choose files… <input id="rcFiles" type="file" multiple hidden></label>
    <span id="rcStatus" class="muted" aria-live="polite"></span>
  </form>
</div>

<?php foreach ($order as $kind => $label): if (empty($byKind[$kind])) continue; ?>
  <div class="card">
    <h2><?= e($icon[$kind] ?? '📎') ?> <?= e($label) ?> <span class="muted" style="font-weight:400">(<?= count($byKind[$kind]) ?>)</span></h2>
    <div class="rc-grid">
      <?php foreach ($byKind[$kind] as $r): ?>
        <div class="rc-item" data-path="<?= e($r['path']) ?>">
          <?php if ($r['kind'] === 'image'): ?>
            <img class="rc-thumb" src="<?= e(url('/courses/' . $slug . '/' . $r['path'])) ?>" alt="" loading="lazy">
          <?php else: ?>
            <div class="rc-thumb rc-thumb--icon"><?= e($icon[$r['kind']] ?? '📎') ?></div>
          <?php endif; ?>
          <div class="rc-meta">
            <strong class="rc-name" title="<?= e($r['name']) ?>"><?= e($r['label'] ?: $r['name']) ?></strong>
            <span class="muted"><?= e($fmtSize($r['size'])) ?></span>
            <code class="rc-path"><?= e($r['path']) ?></code>
          </div>
          <div class="rc-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/courses/' . $slug . '/' . $r['path'])) ?>" target="_blank" rel="noopener">Open</a>
            <button class="btn btn-outline btn-sm rc-del" type="button" data-path="<?= e($r['path']) ?>">Delete</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$resources): ?>
  <div class="card"><p class="muted">No files yet. Upload some above, or add them while editing the course.</p></div>
<?php endif; ?>

<script nonce="<?= e(csp_nonce()) ?>">
const RC = { slug: <?= json_encode($slug) ?>, csrf: <?= json_encode(csrf_token()) ?>, base: <?= json_encode(rtrim(url('/'), '/')) ?> };
document.getElementById('rcFiles').addEventListener('change', async e => {
  const files = [...e.target.files]; const st = document.getElementById('rcStatus');
  for (let i = 0; i < files.length; i++) {
    st.textContent = `Uploading ${i+1}/${files.length}…`;
    const fd = new FormData(); fd.append('file', files[i]); fd.append('_csrf', RC.csrf);
    const r = await fetch(RC.base + '/api/editor/' + RC.slug + '/upload', {method:'POST', headers:{'X-CSRF-Token':RC.csrf}, body:fd});
    const d = await r.json();
    if (!d.ok) { st.textContent = 'Error: ' + (d.error || 'upload failed'); return; }
  }
  st.textContent = 'Uploaded. Reloading…'; location.reload();
});
document.querySelectorAll('.rc-del').forEach(b => b.addEventListener('click', async () => {
  if (!confirm('Delete this file? Pages that reference it will show a broken link.')) return;
  const fd = new FormData(); fd.append('path', b.dataset.path); fd.append('_csrf', RC.csrf);
  const r = await fetch(RC.base + '/api/editor/' + RC.slug + '/resource/delete', {method:'POST', headers:{'X-CSRF-Token':RC.csrf}, body:fd});
  const d = await r.json();
  if (d.ok) b.closest('.rc-item').remove(); else alert('Could not delete.');
}));
</script>

<style>
.rc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.rc-item{border:1px solid var(--line);border-radius:10px;padding:10px;display:flex;flex-direction:column;gap:8px}
.rc-thumb{width:100%;height:120px;object-fit:cover;border-radius:8px;background:var(--surface-alt)}
.rc-thumb--icon{display:flex;align-items:center;justify-content:center;font-size:2.4rem}
.rc-meta{display:flex;flex-direction:column;gap:2px;min-width:0}
.rc-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rc-path{font-size:.72rem;color:var(--ink-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rc-actions{display:flex;gap:6px;margin-top:auto}
</style>
