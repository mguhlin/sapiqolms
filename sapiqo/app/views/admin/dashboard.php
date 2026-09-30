<?php /** @var array $stats @var array $timeline */
$series = [
    ['label' => 'Registered', 'color' => '#2b62aa'],
    ['label' => 'Enrolled',   'color' => '#7aa0d0'],
    ['label' => 'Completed',  'color' => '#f4b41a'],
];
$rows = array_map(fn($b) => ['label' => $b['label'], 'values' => [$b['registered'], $b['enrolled'], $b['completed']]], $timeline);
?>
<?= hero_banner('admin', 'Administration', 'Manage users, enrollment, and completion.') ?>

<div class="card"><a class="btn btn-outline" href="<?= e(url('/admin/readiness')) ?>">Installation readiness</a> <a class="btn btn-outline" href="<?= e(url('/admin/learning-report')) ?>">Learners needing attention</a></div>
<div class="stats">
  <div class="stat"><b><?= (int)$stats['users'] ?></b><span>Total users</span></div>
  <div class="stat"><b><?= (int)$stats['learners'] ?></b><span>Learners</span></div>
  <div class="stat"><b><?= (int)$stats['courses'] ?></b><span>Courses</span></div>
  <div class="stat"><b><?= (int)$stats['enrollments'] ?></b><span>Enrollments</span></div>
  <div class="stat"><b><?= (int)$stats['completed'] ?></b><span>Completions</span></div>
  <div class="stat"><b><?= (int)$stats['rate'] ?>%</b><span>Completion rate</span></div>
</div>

<div class="grid grid-2" style="margin-top:20px">
  <div class="card" style="text-align:center">
    <h2>Completion rate</h2>
    <div style="margin:10px 0"><?= svg_donut((int)$stats['rate']) ?></div>
    <p class="muted"><?= (int)$stats['completed'] ?> of <?= (int)$stats['enrollments'] ?> enrollments · <?= (int)$stats['badges'] ?> badges issued</p>
  </div>
  <div class="card">
    <h2>Activity (last 8 weeks)</h2>
    <?= svg_bar_chart($rows, $series) ?>
    <div class="legend">
      <?php foreach ($series as $s): ?>
        <span class="legend__item"><span class="legend__dot" style="background:<?= $s['color'] ?>"></span><?= e($s['label']) ?></span>
      <?php endforeach; ?>
    </div>
    <p style="margin:14px 0 0"><a class="btn btn-navy btn-sm" href="<?= e(url('/admin/reports')) ?>">Open full training reports →</a></p>
  </div>
</div>

<div class="card">
  <h2>Admin areas</h2>
  <p class="muted" style="margin-top:0">Jump to a section (also in the <strong>Admin</strong> menu at the top).</p>
  <div class="qa-cards qa-cards--lg">
    <?php foreach (admin_sections() as $skey => $sec): ?>
      <a class="qa-card" href="<?= e(url('/admin/section/' . $skey)) ?>">
        <span class="qa-ic"><?= ui_icon($skey, 30) ?: $sec['icon'] ?></span>
        <b><?= e($sec['label']) ?></b>
        <span><?= count($sec['cards']) ?> tool<?= count($sec['cards']) === 1 ? '' : 's' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="margin-top:14px">Courses are auto-discovered from the drop-in
    <code>content/</code> folder. Drop in a folder to add a course; remove it to make the
    course unavailable (learners keep their badges and progress).</p>
</div>
<style>
.qa-cards--lg{grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
.qa-card .qa-ic{font-size:1.9rem;display:block;margin-bottom:6px;line-height:1}
</style>
