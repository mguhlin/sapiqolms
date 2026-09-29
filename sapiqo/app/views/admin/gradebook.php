<?php /** @var array $rows @var array $courses @var ?int $course_filter */ ?>
<?= hero_banner('gradebook', 'Gradebook', 'Custom assessments, scores grids, and quiz results.') ?>

<div class="card">
  <h2 style="margin-top:0">Scores grids</h2>
  <p class="muted">Open a course to build custom assessments and grade learners in a spreadsheet-style grid (points, categories, letter grades).</p>
  <div class="qa-cards">
    <?php foreach ($courses as $c): ?>
      <a class="qa-card" href="<?= e(url('/admin/gradebook/' . urlencode($c['slug']))) ?>"><b><?= e($c['title']) ?></b><span>Open the scores grid →</span></a>
    <?php endforeach; ?>
  </div>
</div>

<h2 style="margin:24px 0 10px">Auto-graded quiz results</h2>

<div class="card">
  <form method="get" action="<?= e(url('/admin/gradebook')) ?>" class="toolbar">
    <label class="sr-only" for="course">Filter by course</label>
    <select id="course" name="course" data-autosubmit
            style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
      <option value="">All courses</option>
      <?php foreach ($courses as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $course_filter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-navy" type="submit">Filter</button></noscript>
    <a class="btn btn-outline" href="<?= e(url('/admin/gradebook?export=1' . ($course_filter ? '&course=' . $course_filter : ''))) ?>">Export CSV</a>
  </form>

  <div class="table-wrap">
    <table class="table">
      <caption class="sr-only">Quiz results</caption>
      <thead><tr>
        <th scope="col">Course</th><th scope="col">Learner</th><th scope="col">Quiz</th>
        <th scope="col">Score</th><th scope="col">Result</th><th scope="col">Attempts</th><th scope="col">Updated (UTC)</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">No quiz results yet.</td></tr>
      <?php else: foreach ($rows as $r): $pct = (int)$r['total'] > 0 ? (int) round($r['score'] / $r['total'] * 100) : 0; ?>
        <tr>
          <td><?= e($r['course_title']) ?></td>
          <td><?= e(trim($r['first_name'].' '.$r['last_name'])) ?: e($r['email']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
          <td class="muted"><?= e($r['quiz_id']) ?></td>
          <td><?= (int)$r['score'] ?>/<?= (int)$r['total'] ?> (<?= $pct ?>%)</td>
          <td><span class="pill <?= (int)$r['passed'] ? 'pill--done' : 'pill--progress' ?>"><?= (int)$r['passed'] ? 'Passed' : 'Not yet' ?></span></td>
          <td><?= (int)$r['attempts'] ?></td>
          <td class="muted"><?= e($r['updated_at']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
