<?php /** @var array $o @var array $stats @var array $members @var array $groups @var array $subs @var array $courses @var bool $can_members @var bool $can_courses */
  $subIds = array_map(fn($c) => (int)$c['id'], $subs);
  $created = $_SESSION['manage_created'] ?? null; unset($_SESSION['manage_created']);
?>
<div class="page-head">
  <h1>🏢 <?= e($o['name']) ?></h1>
  <p><a href="<?= e(url('/manage')) ?>">&larr; Manage</a>
     <?php if ($o['description']): ?> · <?= e($o['description']) ?><?php endif; ?></p>
</div>

<?php if ($created): ?>
<div class="card" style="border-color:var(--gold)">
  <h2>New accounts created</h2>
  <p class="muted">Share these one-time passwords securely; ask each person to change it on first sign-in.</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Email</th><th>Temporary password</th></tr></thead>
    <tbody><?php foreach ($created as $c): ?>
      <tr><td><?= e($c['email']) ?></td><td><code><?= e($c['temp_password']) ?></code></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <div class="stats">
    <div class="stat"><b><?= (int)$stats['members'] ?></b><span>Members</span></div>
    <div class="stat"><b><?= count($groups) ?></b><span>Groups</span></div>
    <div class="stat"><b><?= count($subs) ?></b><span>Org-wide courses</span></div>
    <div class="stat"><b><?= (int)$stats['completions'] ?></b><span>Completions</span></div>
    <div class="stat"><b><?= (int)$stats['rate'] ?>%</b><span>Completion rate</span></div>
  </div>
</div>

<!-- Course subscriptions -->
<div class="card">
  <h2>Org-wide course subscriptions</h2>
  <?php if ($subs): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Course</th><?php if ($can_courses): ?><th></th><?php endif; ?></tr></thead>
      <tbody><?php foreach ($subs as $c): ?>
        <tr>
          <td><strong><?= e($c['title']) ?></strong></td>
          <?php if ($can_courses): ?>
          <td><form method="post" action="<?= e(url('/manage/orgs/' . $o['id'] . '/unsubscribe')) ?>" style="display:inline"
                    data-confirm="Remove this subscription? Current learners keep the course and their progress.">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove</button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No org-wide subscriptions yet.</p><?php endif; ?>

  <?php if ($can_courses): $avail = array_filter($courses, fn($c) => !in_array((int)$c['id'], $subIds, true)); ?>
    <?php if ($avail): ?>
    <form method="post" action="<?= e(url('/manage/orgs/' . $o['id'] . '/subscribe')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <div class="field"><label>Add org-wide course(s) <span class="muted">(enrolls all members now)</span></label>
        <div class="check-grid">
          <?php foreach ($avail as $c): ?>
            <label class="check"><input type="checkbox" name="course_ids[]" value="<?= (int)$c['id'] ?>"> <?= e($c['title']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-gold" type="submit">Subscribe &amp; enroll</button>
    </form>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">An administrator controls which courses this organization is subscribed to.</p>
  <?php endif; ?>
</div>

<!-- Groups -->
<?php if ($groups): ?>
<div class="card">
  <h2>Groups</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Group</th><th></th></tr></thead>
    <tbody><?php foreach ($groups as $g): ?>
      <tr><td><strong><?= e($g['name']) ?></strong></td>
        <td><a class="btn btn-outline btn-sm" href="<?= e(url('/manage/groups/' . $g['id'])) ?>">Open</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- Members -->
<div class="card">
  <h2>Members</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Enrollments</th><th>Completions</th><th>Badges</th><?php if ($can_members): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (!$members): ?>
        <tr><td colspan="<?= $can_members ? 6 : 5 ?>" class="muted">No members yet.</td></tr>
      <?php else: foreach ($members as $m): ?>
        <tr>
          <td><a href="<?= e(url('/manage/users/' . $m['id'])) ?>"><?= e(trim($m['first_name'].' '.$m['last_name'])) ?: 'View' ?></a></td>
          <td><?= e($m['email']) ?></td>
          <td><?= (int)$m['enrollments'] ?></td>
          <td><?= (int)$m['completions'] ?></td>
          <td><?= (int)$m['badges'] ?></td>
          <?php if ($can_members): ?>
          <td><form method="post" action="<?= e(url('/manage/orgs/' . $o['id'] . '/members/remove')) ?>" style="display:inline"
                    data-confirm="Remove this member from the organization? Their enrollments and progress are kept.">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove</button></form></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($can_members): ?>
  <form method="post" action="<?= e(url('/manage/orgs/' . $o['id'] . '/members/add')) ?>" style="margin-top:12px">
    <?= csrf_field() ?>
    <div class="field"><label for="emails">Add members by email</label>
      <input id="emails" name="emails" placeholder="ada@aldirk.edu, grace@aldirk.edu" required>
      <small>Separate with commas, spaces, or new lines. Unknown emails get a new learner account with a
        temporary password (shown after adding). New members are auto-enrolled in the org-wide courses.</small></div>
    <button class="btn btn-gold" type="submit">Add to organization</button>
  </form>
  <?php endif; ?>
</div>
