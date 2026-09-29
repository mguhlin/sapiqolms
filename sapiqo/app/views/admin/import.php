<?php /** @var ?array $report */ ?>
<div class="page-head"><h1>Imports &amp; backups</h1>
  <p>Bring in users and course content, and download a backup — all in one place.</p></div>

<nav class="toolbar" style="margin-bottom:16px" aria-label="Import sections">
  <a class="btn btn-outline btn-sm" href="#users">Users (CSV)</a>
  <a class="btn btn-outline btn-sm" href="#oneroster">OneRoster (SIS)</a>
  <a class="btn btn-outline btn-sm" href="#courses">Course content</a>
  <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/backup')) ?>">Download backup</a>
</nav>

<div class="card" id="users">
  <h2>Bulk import users (CSV)</h2>
  <div class="toolbar">
    <a class="btn btn-outline" href="<?= e(url('/admin/template.csv')) ?>">⬇ Download CSV template</a>
  </div>
  <p class="muted">Columns (header row required): <code>first name, last name, email,
    campus, organization, user type, course title/id, group</code>. Only <strong>email</strong>
    is required per row. The course column may be a course title or its slug/id;
    leave it blank to create an account without enrolling. New accounts get a
    temporary password shown in the report below.</p>

  <form method="post" action="<?= e(url('/admin/import')) ?>" enctype="multipart/form-data" class="toolbar">
    <?= csrf_field() ?>
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <button class="btn btn-gold" type="submit">Upload and import</button>
  </form>
</div>

<div class="card" id="oneroster">
  <h2>OneRoster (SIS) import</h2>
  <p class="muted">Import from your SIS using the <strong>OneRoster v1.1 CSV</strong> standard — a
    <code>.zip</code> containing <code>orgs.csv</code>, <code>users.csv</code>, <code>classes.csv</code>,
    <code>enrollments.csv</code> (or just a <code>users.csv</code>). Accounts are created/updated,
    grouped by school/organization, and enrolled where a class title matches a course.</p>
  <form method="post" action="<?= e(url('/admin/import/oneroster')) ?>" enctype="multipart/form-data" class="toolbar">
    <?= csrf_field() ?>
    <label class="sr-only" for="roster">OneRoster file</label>
    <input id="roster" type="file" name="roster" accept=".zip,.csv,application/zip,text/csv" required>
    <button class="btn btn-gold" type="submit">Import OneRoster</button>
  </form>
</div>

<div class="card" id="courses">
  <h2>Course content</h2>
  <p class="muted">Import a whole course. New courses appear in the catalog immediately.</p>
  <div style="display:grid;gap:14px;max-width:640px">
    <form method="post" action="<?= e(url('/admin/courses/import')) ?>" enctype="multipart/form-data"
          style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <label style="flex:1;min-width:220px"><strong>Course package</strong> <span class="muted">(.tar / .zip from Export)</span><br>
        <input type="file" name="archive" accept=".tar,.tar.gz,.tgz,.zip,application/x-tar,application/gzip,application/zip" required></label>
      <button class="btn btn-outline" type="submit">Import package</button>
    </form>
    <form method="post" action="<?= e(url('/admin/courses/import-cc')) ?>" enctype="multipart/form-data"
          style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <label style="flex:1;min-width:220px"><strong>Common Cartridge</strong> <span class="muted">(.imscc — Canvas/Blackboard/Moodle/Sakai)</span><br>
        <input type="file" name="cartridge" accept=".imscc,.zip,application/zip" required></label>
      <button class="btn btn-outline" type="submit">Import .imscc</button>
    </form>
    <form method="post" action="<?= e(url('/admin/courses/import-scorm')) ?>" enctype="multipart/form-data"
          style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <label style="flex:1;min-width:220px"><strong>SCORM package</strong> <span class="muted">(.zip — SCORM 1.2 / 2004)</span><br>
        <input type="file" name="scorm" accept=".zip,application/zip" required></label>
      <button class="btn btn-outline" type="submit">Import SCORM</button>
    </form>
    <form method="post" action="<?= e(url('/admin/courses/import-learndash')) ?>" enctype="multipart/form-data"
          style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <label style="flex:1;min-width:220px"><strong>LearnDash export</strong> <span class="muted">(.json — WordPress LearnDash course)</span><br>
        <input type="file" name="learndash" accept=".json,application/json" required></label>
      <label class="check" style="display:inline-flex" title="Download referenced images into the course so it works with no external requests">
        <input type="checkbox" name="mirror" value="1" checked> Mirror images locally</label>
      <button class="btn btn-outline" type="submit">Import LearnDash</button>
    </form>
  </div>
  <p class="muted" style="margin-top:8px"><strong>LearnDash notes:</strong> lessons become modules by their number prefix
    (1.1, 1.2, …); Vimeo embeds are kept as players. To <em>self-host</em> the videos instead, drop the MP4s in
    <code>content/&lt;slug&gt;/media/videos/</code> and add a <code>videos.json</code> map, then Rescan (see DEPLOYMENT.md).</p>
  <p class="muted" style="margin-top:10px">Very large courses may exceed the upload limit — use
    <a href="<?= e(url('/admin/courses')) ?>">Manage courses</a> to Export/Import in split parts, or drop the
    folder into <code>content/</code> and Rescan.</p>
</div>

<?php if ($report): ?>
<div class="card report">
  <h2>Import report</h2>
  <p class="muted">Processed <?= (int)$report['rows'] ?> data row(s):
    <?= count($report['created']) ?> created, <?= count($report['enrolled']) ?> enrolled,
    <?= count($report['existing']) ?> already existed, <?= count($report['errors']) ?> error(s).</p>

  <?php if ($report['created']): ?>
    <h3>Created accounts — share these temporary passwords securely</h3>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Email</th><th>Temporary password</th></tr></thead>
      <tbody><?php foreach ($report['created'] as $c): ?>
        <tr><td><?= e($c['email']) ?></td><td><code><?= e($c['temp_password']) ?></code></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <p class="muted">Ask these users to sign in and change their password on the Profile page.</p>
  <?php endif; ?>

  <?php if ($report['enrolled']): ?>
    <h3>Enrollments</h3>
    <ul><?php foreach ($report['enrolled'] as $en): ?>
      <li><?= e($en['email']) ?> → <?= e($en['course']) ?></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>

  <?php if (!empty($report['grouped'])): ?>
    <h3>Group assignments</h3>
    <ul><?php foreach ($report['grouped'] as $gr): ?>
      <li><?= e($gr['email']) ?> → <?= e($gr['group']) ?></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>

  <?php if ($report['errors']): ?>
    <h3>Errors</h3>
    <ul><?php foreach ($report['errors'] as $err): ?>
      <li>Row <?= (int)$err['row'] ?>: <?= e($err['message']) ?></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
</div>
<?php endif; ?>
