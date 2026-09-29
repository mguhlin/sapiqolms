<?php /** @var array $g @var array $members @var array $managers @var array $stats @var array $courses @var ?int $course_filter @var array $subs @var ?array $org @var array $orgs */
  $subIds = array_map(fn($c) => (int)$c['id'], $subs);
?>
<div class="page-head">
  <h1><?= e($g['name']) ?></h1>
  <p><a href="<?= e(url('/admin/groups')) ?>">&larr; All groups</a>
     <?php if ($org): ?> · <span class="muted">in</span> <a href="<?= e(url('/admin/orgs/' . $org['id'])) ?>">🏢 <?= e($org['name']) ?></a><?php endif; ?>
     <?php if ($g['description']): ?> · <?= e($g['description']) ?><?php endif; ?></p>
</div>

<!-- Course subscriptions -->
<div class="card">
  <h2>Course subscriptions</h2>
  <p class="muted">Everyone in this group is enrolled in the courses below — and anyone added later is enrolled automatically.
    <?php if ($org): ?> Members also inherit <a href="<?= e(url('/admin/orgs/' . $org['id'])) ?>"><?= e($org['name']) ?></a>&rsquo;s org-wide courses.<?php endif; ?></p>
  <?php if ($subs): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Course</th><th></th></tr></thead>
      <tbody><?php foreach ($subs as $c): ?>
        <tr>
          <td><strong><?= e($c['title']) ?></strong></td>
          <td><form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/unsubscribe')) ?>" style="display:inline"
                    data-confirm="Remove this subscription? Current learners keep the course and their progress; only new members stop being auto-enrolled.">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove</button></form></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No subscriptions yet — this group&rsquo;s members are not auto-enrolled in anything.</p><?php endif; ?>

  <?php $avail = array_filter($courses, fn($c) => !in_array((int)$c['id'], $subIds, true)); ?>
  <?php if ($avail): ?>
    <form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/subscribe')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <div class="field"><label>Add course subscriptions <span class="muted">(enrolls all members now)</span></label>
        <div class="check-grid">
          <?php foreach ($avail as $c): ?>
            <label class="check"><input type="checkbox" name="course_ids[]" value="<?= (int)$c['id'] ?>"> <?= e($c['title']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-gold" type="submit">Subscribe &amp; enroll</button>
    </form>
  <?php endif; ?>

  <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
  <form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/set-org')) ?>" class="toolbar">
    <?= csrf_field() ?>
    <label for="org_id" class="muted">Organization:</label>
    <select id="org_id" name="org_id" style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
      <option value="0">— none (standalone group) —</option>
      <?php foreach ($orgs as $o): ?>
        <option value="<?= (int)$o['id'] ?>"<?= $org && (int)$org['id']===(int)$o['id']?' selected':'' ?>><?= e($o['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline btn-sm" type="submit">Save</button>
  </form>
</div>

<!-- Filter by course + stats -->
<div class="card">
  <form method="get" action="<?= e(url('/admin/groups/' . $g['id'])) ?>" class="toolbar">
    <label for="course" class="muted">Completion for:</label>
    <select id="course" name="course" data-autosubmit
            style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
      <option value="">All courses</option>
      <?php foreach ($courses as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= $course_filter===(int)$c['id']?' selected':'' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/groups/' . $g['id'] . '?export=1')) ?>">⬇ Export CSV</a>
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

<!-- Members -->
<div class="card">
  <h2>Members</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Organization</th><th>Enrollments</th><th>Completions</th><th>Steps</th><th>Badges</th><th></th></tr></thead>
      <tbody>
      <?php if (!$members): ?>
        <tr><td colspan="8" class="muted">No members yet. Add some below.</td></tr>
      <?php else: foreach ($members as $m): ?>
        <tr>
          <td><a href="<?= e(url('/admin/users/' . $m['id'])) ?>"><?= e(trim($m['first_name'].' '.$m['last_name'])) ?: 'View' ?></a></td>
          <td><?= e($m['email']) ?></td>
          <td><?= e($m['organization']) ?></td>
          <td><?= (int)$m['enrollments'] ?></td>
          <td><?= (int)$m['completions'] ?></td>
          <td><?= (int)$m['steps'] ?></td>
          <td><?= (int)$m['badges'] ?></td>
          <td>
            <form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/remove')) ?>" style="display:inline"
                  data-confirm="Remove this member from the group?">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Managers (sub-admins) -->
<div class="card">
  <h2>Managers (sub-admins)</h2>
  <p class="muted">Managers can add, edit, enroll, and remove members for this group only —
    via their <a href="<?= e(url('/manage')) ?>">Manage</a> area. They are also added as members.</p>
  <?php if ($managers): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Name</th><th>Email</th><th></th></tr></thead>
      <tbody><?php foreach ($managers as $m): ?>
        <tr>
          <td><?= e(trim($m['first_name'].' '.$m['last_name'])) ?: '—' ?></td>
          <td><?= e($m['email']) ?></td>
          <td><form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/remove-manager')) ?>" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove manager</button></form></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No managers assigned.</p><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/add-manager')) ?>" class="toolbar" style="margin-top:12px">
    <?= csrf_field() ?>
    <input name="email" type="email" placeholder="manager@district.org" required
           style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <button class="btn btn-navy" type="submit">Assign manager</button>
  </form>
</div>

<!-- Add members + delete group -->
<div class="grid grid-2">
  <div class="card">
    <h2>Add members</h2>
    <form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/add')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="emails">Emails</label>
        <input id="emails" name="emails" placeholder="ada@example.edu, grace@example.edu" required>
        <small>Separate multiple emails with commas, spaces, or new lines. Users must already exist
          (create accounts via <a href="<?= e(url('/admin/import')) ?>">Bulk import</a> or registration).</small></div>
      <button class="btn btn-gold" type="submit">Add to group</button>
    </form>
  </div>
  <div class="card">
    <h2>Danger zone</h2>
    <p class="muted">Deleting a group removes the group and its memberships. User accounts,
      enrollments, and progress are not affected.</p>
    <form method="post" action="<?= e(url('/admin/groups/' . $g['id'] . '/delete')) ?>"
          data-confirm="Delete this group? Members stay, but the grouping is removed.">
      <?= csrf_field() ?>
      <button class="btn btn-outline" type="submit">Delete group</button>
    </form>
  </div>
</div>
