<?php /** @var array $u @var array $course @var array $structure @var array $done @var bool $enrolled @var array|null $enrollment @var string $lastStep @var int $percent @var array|null $badge */ ?>
<div class="page-head">
  <h1>Manage progress</h1>
  <p><?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?> · <?= e($u['email']) ?> — <strong><?= e($course['title']) ?></strong></p>
</div>

<p><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users/' . (int)$u['id'])) ?>">← Back to user</a></p>

<div class="card">
  <h2>Status</h2>
  <div class="progress" style="max-width:340px"><div class="progress__bar" style="width:<?= (int)$percent ?>%"></div></div>
  <p style="margin-top:8px">
    <strong><?= (int)$percent ?>%</strong> complete ·
    <?php if ($enrolled): ?><span class="pill pill--done">Enrolled</span><?php else: ?><span class="pill pill--progress">Not enrolled</span><?php endif; ?>
    <?php if ($badge): ?>
      · <span class="pill pill--done">Badge earned</span>
      <a href="<?= e(url('/certificate/' . $badge['code'])) ?>">certificate (PDF)</a>
    <?php endif; ?>
  </p>
  <?php if ($lastStep !== ''): ?>
    <p class="muted">Last active step: <code><?= e($lastStep) ?></code> — highlighted below as “stuck here”.</p>
  <?php endif; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
    <?php if (!$enrolled): ?>
      <form method="post" action="<?= e(url('/admin/users/' . (int)$u['id'] . '/course/' . urlencode($course['slug']) . '/enroll')) ?>">
        <?= csrf_field() ?><button class="btn btn-navy btn-sm" type="submit">Enroll this user</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= e(url('/admin/users/' . (int)$u['id'] . '/course/' . urlencode($course['slug']) . '/disenroll')) ?>"
            data-confirm="Remove this user from “<?= e($course['title']) ?>”?"
            style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <?= csrf_field() ?>
        <label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="purge" value="1"><span class="muted">also delete progress + badge</span></label>
        <button class="btn btn-outline btn-sm" type="submit">Disenroll</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e(url('/admin/users/' . (int)$u['id'] . '/course/' . urlencode($course['slug']))) ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <h2 style="margin:0">Lessons &amp; topics</h2>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-outline btn-sm" data-check-all="true">Check all</button>
        <button type="button" class="btn btn-outline btn-sm" data-check-all="false">Uncheck all</button>
      </div>
    </div>
    <p class="muted">Tick the parts this learner has completed, then <strong>Save progress</strong>. Reaching 100% issues
      the badge and marks the course complete; dropping below reopens it (an earned badge is kept unless you disenroll with purge).</p>

    <?php if (!$structure): ?>
      <p class="muted">No readable lesson structure for this course (imported without a course.json). You can still use
        <em>Mark entire course complete</em> below.</p>
    <?php endif; ?>

    <?php foreach ($structure as $m): ?>
      <fieldset style="border:1px solid var(--line);border-radius:10px;padding:10px 16px 14px;margin:0 0 14px">
        <legend style="font-weight:700;color:var(--navy-800);padding:0 6px"><?= e($m['title']) ?></legend>
        <?php foreach ($m['lessons'] as $l): ?>
          <?php foreach ($l['units'] as $unit):
            $isDone = isset($done[$unit['id']]);
            $isHere = $lastStep !== '' && $unit['id'] === $lastStep;
            $indent = $unit['kind'] === 'lesson' ? '2px' : '24px'; ?>
            <label style="display:flex;gap:9px;align-items:center;padding:4px 6px 4px <?= $indent ?>;border-radius:7px;<?= $isHere ? 'background:var(--surface-alt)' : '' ?>">
              <input type="checkbox" name="steps[]" value="<?= e($unit['id']) ?>" <?= $isDone ? 'checked' : '' ?>>
              <span style="<?= $unit['kind'] === 'lesson' ? 'font-weight:600' : 'color:var(--ink-soft)' ?>">
                <?= e($unit['label']) ?>
                <?php if ($unit['kind'] === 'topic'): ?> <span class="muted">· topic</span><?php endif; ?>
                <?php if ($unit['kind'] === 'quiz'): ?> <span class="muted">· quiz</span><?php endif; ?>
                <?php if ($isHere): ?> <span class="pill pill--progress">stuck here</span><?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>

    <div class="toolbar" style="margin-top:12px;gap:10px">
      <button class="btn btn-gold" type="submit">Save progress</button>
      <button class="btn btn-navy" type="submit" name="complete_all" value="1"
              data-confirm="Mark every lesson, topic and quiz complete for this user and issue the badge?">Mark entire course complete</button>
      <a class="btn btn-outline" href="<?= e(url('/admin/users/' . (int)$u['id'])) ?>">Done</a>
    </div>
  </div>
</form>

<script nonce="<?= e(csp_nonce()) ?>">
document.querySelectorAll('[data-check-all]').forEach(function(b){
  b.addEventListener('click', function(){
    var on = b.getAttribute('data-check-all') === 'true';
    document.querySelectorAll('input[name="steps[]"]').forEach(function(c){ c.checked = on; });
  });
});
</script>
