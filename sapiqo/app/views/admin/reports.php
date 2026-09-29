<?php
/** @var array $kpis @var array $courses @var array $orgs @var array $types
 *  @var array $timeline @var array $activity */
$series = [
    ['label' => 'Registered', 'color' => '#2b62aa'],
    ['label' => 'Enrolled',   'color' => '#7aa0d0'],
    ['label' => 'Completed',  'color' => '#f4b41a'],
];
$rows = array_map(fn($b) => ['label' => $b['label'], 'values' => [$b['registered'], $b['enrolled'], $b['completed']]], $timeline);
?>
<div class="page-head"><h1>Training reports</h1><p>Completion, course performance, and activity across your organization.</p></div>

<div class="toolbar">
  <a class="btn btn-outline" href="<?= e(url('/admin/reports.csv')) ?>">⬇ Export summary CSV</a>
  <a class="btn btn-outline" href="<?= e(url('/admin/completions?export=1')) ?>">⬇ Export completions CSV</a>
</div>

<!-- Headline KPIs -->
<div class="stats" style="margin-bottom:20px">
  <div class="stat"><b><?= $kpis['users'] ?></b><span>Total users</span></div>
  <div class="stat"><b><?= $kpis['enrollments'] ?></b><span>Enrollments</span></div>
  <div class="stat"><b><?= $kpis['completions'] ?></b><span>Completions</span></div>
  <div class="stat"><b><?= $kpis['in_progress'] ?></b><span>In progress</span></div>
  <div class="stat"><b><?= $kpis['active_learners'] ?></b><span>Active learners</span></div>
  <div class="stat"><b><?= $kpis['badges'] ?></b><span>Badges issued</span></div>
</div>

<div class="grid grid-2">
  <!-- Completion-rate donut -->
  <div class="card" style="text-align:center">
    <h2>Overall completion rate</h2>
    <div style="margin:10px 0"><?= svg_donut($kpis['completion_rate']) ?></div>
    <p class="muted"><?= $kpis['completions'] ?> of <?= $kpis['enrollments'] ?> enrollments completed</p>
  </div>

  <!-- Activity timeline -->
  <div class="card">
    <h2>Activity (last 8 weeks)</h2>
    <?= svg_bar_chart($rows, $series) ?>
    <div class="legend">
      <?php foreach ($series as $s): ?>
        <span class="legend__item"><span class="legend__dot" style="background:<?= $s['color'] ?>"></span><?= e($s['label']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Course performance -->
<div class="card">
  <h2>Course performance</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Course</th><th>Enrolled</th><th>Completed</th><th>Completion rate</th><th style="min-width:180px">Avg. progress</th></tr></thead>
      <tbody>
      <?php if (!$courses): ?><tr><td colspan="5" class="muted">No courses yet.</td></tr>
      <?php else: foreach ($courses as $c): ?>
        <tr>
          <td><?= e($c['title']) ?></td>
          <td><?= $c['enrolled'] ?></td>
          <td><?= $c['completed'] ?></td>
          <td><strong><?= $c['rate'] ?>%</strong></td>
          <td>
            <div class="progress"><div class="progress__bar" style="width:<?= $c['avg_progress'] ?>%"></div></div>
            <span class="muted"><?= $c['avg_progress'] ?>%</span>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-2">
  <!-- By organization -->
  <div class="card">
    <h2>By organization</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Organization</th><th>Users</th><th>Completions</th><th>Rate</th></tr></thead>
        <tbody>
        <?php foreach ($orgs as $o): ?>
          <tr><td><?= e($o['organization']) ?></td><td><?= $o['users'] ?></td><td><?= $o['completions'] ?></td><td><?= $o['rate'] ?>%</td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- By campus -->
  <div class="card">
    <h2>By campus</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Campus</th><th>Organization</th><th>Users</th><th>Completions</th><th>Rate</th></tr></thead>
        <tbody>
        <?php foreach (($campuses ?? []) as $c): ?>
          <tr><td><?= e($c['campus']) ?></td><td><?= e($c['organization']) ?></td><td><?= $c['users'] ?></td><td><?= $c['completions'] ?></td><td><?= $c['rate'] ?>%</td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- By user type -->
  <div class="card">
    <h2>By user type</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>User type</th><th>Users</th></tr></thead>
        <tbody>
        <?php foreach ($types as $t): ?>
          <tr><td><?= e($t['user_type']) ?></td><td><?= $t['users'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- By group -->
<?php if (!empty($groups)): ?>
<div class="card">
  <h2>By group</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Group</th><th>Members</th><th>Enrollments</th><th>Completions</th><th>Completion rate</th><th>Steps completed</th></tr></thead>
      <tbody>
      <?php foreach ($groups as $g): ?>
        <tr>
          <td><a href="<?= e(url('/admin/groups/' . $g['id'])) ?>"><?= e($g['name']) ?></a></td>
          <td><?= (int)$g['members'] ?></td>
          <td><?= (int)$g['enrollments'] ?></td>
          <td><?= (int)$g['completions'] ?></td>
          <td><strong><?= (int)$g['rate'] ?>%</strong></td>
          <td><?= (int)$g['steps'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Recent activity -->
<div class="card">
  <h2>Recent activity</h2>
  <?php if (!$activity): ?><p class="muted">No activity yet.</p>
  <?php else: ?>
    <ul class="feed">
      <?php foreach ($activity as $ev): ?>
        <li class="feed__item feed__item--<?= e($ev['type']) ?>">
          <span class="feed__dot"></span>
          <span class="feed__text"><strong><?= e($ev['who']) ?></strong> <?= e($ev['what']) ?></span>
          <span class="feed__time"><?= e($ev['ts'] ? date('M j, Y', strtotime($ev['ts'])) : '') ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<p class="muted" style="font-size:.85rem">Note: time-on-task and quiz scores are not tracked in this version (the courses
are self-paced with no graded assessments). Reporting is based on step completion, enrollment, and badges.</p>
