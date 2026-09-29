<?php /** @var array $g @var array $members @var array $stats @var array $courses @var ?int $course_filter @var bool $can_members @var bool $can_courses @var array $subs */
$created = $_SESSION['manage_created'] ?? null; unset($_SESSION['manage_created']);
$can_members = $can_members ?? true;
$can_courses = $can_courses ?? false;
$subs = $subs ?? [];
$subIds = array_map(fn($c) => (int)$c['id'], $subs); ?>
<div class="page-head">
  <h1><?= e($g['name']) ?></h1>
  <p><a href="<?= e(url('/manage')) ?>">&larr; Your groups</a>
     <?php if ($g['description']): ?> · <?= e($g['description']) ?><?php endif; ?></p>
</div>

<?php if ($created): ?>
<div class="card">
  <h2>New accounts created</h2>
  <p class="muted">Share these temporary passwords securely; ask each person to change it on their Profile page.</p>
  <div class="table-wrap"><table class="table"><thead><tr><th>Email</th><th>Temporary password</th></tr></thead>
    <tbody><?php foreach ($created as $c): ?><tr><td><?= e($c['email']) ?></td><td><code><?= e($c['temp_password']) ?></code></td></tr><?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <form method="get" action="<?= e(url('/manage/groups/' . $g['id'])) ?>" class="toolbar">
    <label for="course" class="muted">Completion for:</label>
    <select id="course" name="course" data-autosubmit
            style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
      <option value="">All courses</option>
      <?php foreach ($courses as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= $course_filter===(int)$c['id']?' selected':'' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/manage/groups/' . $g['id'] . '?export=1')) ?>">⬇ Export CSV</a>
  </form>
  <div class="stats" style="margin-top:12px">
    <div class="stat"><b><?= (int)$stats['members'] ?></b><span>Members</span></div>
    <div class="stat"><b><?= (int)$stats['enrollments'] ?></b><span>Enrollments</span></div>
    <div class="stat"><b><?= (int)$stats['completions'] ?></b><span>Completions</span></div>
    <div class="stat"><b><?= (int)$stats['rate'] ?>%</b><span>Completion rate</span></div>
    <div class="stat"><b><?= (int)$stats['steps'] ?></b><span>Steps completed</span></div>
    <div class="stat"><b><?= (int)$stats['badges'] ?></b><span>Badges</span></div>
  </div>
</div>

<!-- Course subscriptions -->
<div class="card">
  <h2>Course subscriptions</h2>
  <p class="muted">Members of this group are auto-enrolled in these courses.</p>
  <?php if ($subs): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Course</th><?php if ($can_courses): ?><th></th><?php endif; ?></tr></thead>
      <tbody><?php foreach ($subs as $c): ?>
        <tr>
          <td><strong><?= e($c['title']) ?></strong></td>
          <?php if ($can_courses): ?>
          <td><form method="post" action="<?= e(url('/manage/groups/' . $g['id'] . '/unsubscribe')) ?>" style="display:inline"
                    data-confirm="Remove this subscription? Current learners keep the course and their progress.">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove</button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No subscriptions yet.</p><?php endif; ?>
  <?php if ($can_courses): $avail = array_filter($courses, fn($c) => !in_array((int)$c['id'], $subIds, true)); ?>
    <?php if ($avail): ?>
    <form method="post" action="<?= e(url('/manage/groups/' . $g['id'] . '/subscribe')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <div class="field"><label>Add course(s) <span class="muted">(enrolls all members now)</span></label>
        <div class="check-grid">
          <?php foreach ($avail as $c): ?>
            <label class="check"><input type="checkbox" name="course_ids[]" value="<?= (int)$c['id'] ?>"> <?= e($c['title']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-gold" type="submit">Subscribe &amp; enroll</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Members</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Campus</th><th>Enrollments</th><th>Completions</th><th>Steps</th><th></th></tr></thead>
      <tbody>
      <?php if (!$members): ?>
        <tr><td colspan="7" class="muted">No members yet. Add some below.</td></tr>
      <?php else: foreach ($members as $m): ?>
        <tr>
          <td><span style="display:inline-flex;align-items:center;gap:8px"><?= avatar_html($m, 26) ?>
            <a href="<?= e(url('/manage/users/' . $m['id'])) ?>"><?= e(trim($m['first_name'].' '.$m['last_name'])) ?: 'Edit' ?></a></span></td>
          <td><?= e($m['email']) ?></td>
          <td><?= e($m['campus']) ?></td>
          <td><?= (int)$m['enrollments'] ?></td>
          <td><?= (int)$m['completions'] ?></td>
          <td><?= (int)$m['steps'] ?></td>
          <td style="white-space:nowrap">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/manage/users/' . $m['id'])) ?>">Edit</a>
            <?php if ($can_members): ?>
            <form method="post" action="<?= e(url('/manage/groups/' . $g['id'] . '/remove')) ?>" style="display:inline"
                  data-confirm="Remove this member from the group?">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit">Remove</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($can_members): ?>
<div class="card">
  <h2>Add members</h2>
  <form method="post" action="<?= e(url('/manage/groups/' . $g['id'] . '/add')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="emails">Emails</label>
      <input id="emails" name="emails" placeholder="teacher1@district.org, teacher2@district.org" required>
      <small>Separate with commas, spaces, or new lines. New emails get an account with a
        temporary password (shown after adding); existing users are added to this group.</small></div>
    <button class="btn btn-gold" type="submit">Add to group</button>
  </form>
</div>
<?php endif; ?>
