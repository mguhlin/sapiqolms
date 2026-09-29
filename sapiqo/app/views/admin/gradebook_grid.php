<?php /** @var array $course @var array $assessments @var array $learners @var array $scores @var array $scale */
$slug = $course['slug'];
$scaleStr = implode('  ·  ', array_map(fn($b) => $b['letter'] . ' ≥ ' . rtrim(rtrim(number_format($b['min'], 1), '0'), '.') . '%', $scale));
?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
  <div>
    <h1>Gradebook — <?= e($course['title']) ?></h1>
    <p><a href="<?= e(url('/admin/gradebook')) ?>">&larr; All gradebooks</a> ·
       <?= count($learners) ?> learner<?= count($learners) === 1 ? '' : 's' ?> · <?= count($assessments) ?> assessment<?= count($assessments) === 1 ? '' : 's' ?></p>
  </div>
  <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/gradebook/' . urlencode($slug) . '?export=1')) ?>">Export CSV</a>
</div>

<div class="card">
  <h2 style="margin-top:0">Add / edit an assessment</h2>
  <form method="post" action="<?= e(url('/admin/gradebook/' . urlencode($slug) . '/assessment')) ?>" class="gb-addform">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="gbEditId" value="">
    <div class="field"><label for="gbTitle">Title</label>
      <input id="gbTitle" name="title" required placeholder="e.g. Speaking Task"></div>
    <div class="field"><label for="gbCat">Category</label>
      <input id="gbCat" name="category" placeholder="e.g. Participation"></div>
    <div class="field"><label for="gbMax">Points</label>
      <input id="gbMax" name="max_points" type="number" min="1" step="0.5" value="100" required style="max-width:110px"></div>
    <div class="field"><label for="gbDue">Due (optional)</label>
      <input id="gbDue" name="due_date" type="date"></div>
    <button class="btn btn-navy" type="submit">Save</button>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
    <h2 style="margin:0">Scores</h2>
    <span class="muted" style="font-size:.82rem"><?= e($scaleStr) ?>  ·  else F</span>
  </div>
  <?php if (!$learners): ?>
    <p class="muted" style="margin-top:12px">No one is enrolled in this course yet. Enroll learners to grade them.</p>
  <?php elseif (!$assessments): ?>
    <p class="muted" style="margin-top:12px">Add an assessment above to start grading.</p>
  <?php else: ?>
    <div class="table-wrap gb-wrap" style="margin-top:12px">
      <table class="table gb">
        <thead>
          <tr>
            <th class="gb-sticky" scope="col">Learner</th>
            <?php foreach ($assessments as $a): ?>
              <th scope="col" class="gb-acol">
                <div class="gb-atitle"><?= e($a['title']) ?></div>
                <div class="muted gb-ameta">/<?= e(gb_num($a['max_points'])) ?><?= $a['category'] ? ' · ' . e($a['category']) : '' ?></div>
                <div class="gb-ahdr-actions">
                  <button type="button" class="gb-mini gb-edit" title="Edit"
                    data-id="<?= (int)$a['id'] ?>" data-title="<?= e($a['title']) ?>" data-cat="<?= e($a['category']) ?>"
                    data-max="<?= e(gb_num($a['max_points'])) ?>" data-due="<?= e($a['due_date']) ?>">✎</button>
                  <form method="post" action="<?= e(url('/admin/gradebook/' . urlencode($slug) . '/assessment/' . $a['id'] . '/delete')) ?>" style="display:inline" data-confirm="Delete “<?= e($a['title']) ?>” and its scores?">
                    <?= csrf_field() ?><button type="submit" class="gb-mini" title="Delete">🗑</button>
                  </form>
                </div>
              </th>
            <?php endforeach; ?>
            <th scope="col" class="gb-overall">Overall</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($learners as $l): $uid = (int) $l['id']; $pct = gb_overall($uid, $assessments, $scores); ?>
            <tr>
              <th class="gb-sticky" scope="row">
                <strong><?= e(trim($l['last_name'] . ', ' . $l['first_name'])) ?: e($l['email']) ?></strong>
                <div class="muted" style="font-size:.76rem"><?= e($l['email']) ?></div>
              </th>
              <?php foreach ($assessments as $a): $aid = (int) $a['id']; $val = $scores[$aid][$uid] ?? null; ?>
                <td class="gb-cell">
                  <input class="gb-score" type="number" step="0.5" min="0" max="<?= e(gb_num($a['max_points'])) ?>"
                         value="<?= $val === null ? '' : e(gb_num($val)) ?>"
                         data-a="<?= $aid ?>" data-u="<?= $uid ?>" aria-label="Score for <?= e($l['email']) ?> on <?= e($a['title']) ?>">
                </td>
              <?php endforeach; ?>
              <td class="gb-overall" data-u="<?= $uid ?>">
                <span class="gb-pct"><?= $pct === null ? '—' : e(gb_num($pct)) . '%' ?></span>
                <span class="gb-letter"><?= e(gb_letter($pct)) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="font-size:.82rem;margin-top:10px">Type a score and it saves automatically. Leave blank to clear. Overall = total points earned ÷ total possible on graded items.</p>
  <?php endif; ?>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
const GB = { slug: <?= json_encode($slug) ?>, csrf: <?= json_encode(csrf_token()) ?>, base: <?= json_encode(rtrim(url('/'), '/')) ?> };
document.querySelectorAll('.gb-edit').forEach(b=>b.addEventListener('click',()=>{
  document.getElementById('gbEditId').value=b.dataset.id;
  document.getElementById('gbTitle').value=b.dataset.title||'';
  document.getElementById('gbCat').value=b.dataset.cat||'';
  document.getElementById('gbMax').value=b.dataset.max||'';
  document.getElementById('gbDue').value=b.dataset.due||'';
  document.getElementById('gbTitle').focus();
  window.scrollTo({top:0,behavior:'smooth'});
}));
document.querySelectorAll('.gb-score').forEach(inp=>{
  inp.addEventListener('change', async ()=>{
    inp.classList.remove('gb-saved','gb-err');
    const fd=new URLSearchParams({assessment_id:inp.dataset.a,user_id:inp.dataset.u,points:inp.value,_csrf:GB.csrf});
    try{
      const r=await fetch(GB.base+'/api/gradebook/score',{method:'POST',headers:{'X-CSRF-Token':GB.csrf,'Content-Type':'application/x-www-form-urlencoded'},body:fd});
      const d=await r.json();
      if(d.ok){ inp.classList.add('gb-saved');
        const cell=document.querySelector('.gb-overall[data-u="'+inp.dataset.u+'"]');
        if(cell){ cell.querySelector('.gb-pct').textContent=(d.overall===null?'—':d.overall+'%'); cell.querySelector('.gb-letter').textContent=d.letter; }
      } else inp.classList.add('gb-err');
    }catch(e){ inp.classList.add('gb-err'); }
  });
});
</script>

<style>
.gb-addform{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.gb-addform .field{margin-bottom:0}
.gb .gb-sticky{position:sticky;left:0;background:var(--surface-alt);z-index:2;min-width:170px;text-align:left}
.gb thead .gb-sticky{z-index:3}
.gb-acol{min-width:110px;vertical-align:top}
.gb-atitle{font-weight:700;white-space:normal;max-width:150px}
.gb-ameta{font-size:.74rem;font-weight:400}
.gb-ahdr-actions{margin-top:3px}
.gb-mini{border:0;background:transparent;cursor:pointer;font-size:.8rem;padding:1px 3px;color:var(--ink-soft)}
.gb-mini:hover{color:var(--navy-700)}
.gb-cell{padding:4px}
.gb-score{width:70px;padding:6px 7px;border:1.5px solid var(--line-strong);border-radius:7px;font:inherit;text-align:center}
.gb-score:focus{outline:none;border-color:var(--navy-500)}
.gb-score.gb-saved{border-color:var(--success);background:var(--success-soft)}
.gb-score.gb-err{border-color:var(--danger);background:var(--danger-soft)}
.gb-overall{white-space:nowrap;font-weight:700;text-align:center;background:var(--surface-alt)}
.gb-overall .gb-letter{display:inline-block;margin-left:6px;color:var(--navy-700)}
</style>
