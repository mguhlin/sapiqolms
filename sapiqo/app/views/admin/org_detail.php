<?php /** @var array $o @var array $stats @var array $members @var array $groups @var array $subs @var array $managers @var array $courses @var array $all_group_list */
  $subIds = array_map(fn($c) => (int)$c['id'], $subs);
  $orgGroupIds = array_map(fn($g) => (int)$g['id'], $groups);
  $attachable = array_filter($all_group_list, fn($g) => !in_array((int)$g['id'], $orgGroupIds, true));
?>
<div class="page-head">
  <h1>🏢 <?= e($o['name']) ?></h1>
  <p><a href="<?= e(url('/admin/orgs')) ?>">&larr; All organizations</a>
     <?php if ($o['description']): ?> · <?= e($o['description']) ?><?php endif; ?></p>
</div>

<!-- Stats -->
<div class="card">
  <div class="stats">
    <div class="stat"><b><?= (int)$stats['members'] ?></b><span>Members</span></div>
    <div class="stat"><b><?= count($groups) ?></b><span>Groups</span></div>
    <div class="stat"><b><?= count($subs) ?></b><span>Org-wide courses</span></div>
    <div class="stat"><b><?= (int)$stats['enrollments'] ?></b><span>Enrollments</span></div>
    <div class="stat"><b><?= (int)$stats['rate'] ?>%</b><span>Completion rate</span></div>
    <div class="stat"><b><?= (int)$stats['badges'] ?></b><span>Badges</span></div>
  </div>
  <p style="margin-top:12px"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/orgs/' . $o['id'] . '?export=1')) ?>">⬇ Export members CSV</a></p>
</div>

<!-- Org-wide course subscriptions -->
<div class="card">
  <h2>Org-wide course subscriptions</h2>
  <p class="muted">Every member of <?= e($o['name']) ?> is enrolled in these courses — and anyone added to the
    organization (or to any of its groups) is enrolled automatically. For courses only some people need, use a group instead.</p>
  <?php if ($subs): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Course</th><th></th></tr></thead>
      <tbody><?php foreach ($subs as $c): ?>
        <tr>
          <td><strong><?= e($c['title']) ?></strong></td>
          <td><form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/unsubscribe')) ?>" style="display:inline"
                    data-confirm="Remove this org-wide subscription? Current learners keep the course and their progress; only new members stop being auto-enrolled.">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove</button></form></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No org-wide subscriptions yet.</p><?php endif; ?>

  <?php $avail = array_filter($courses, fn($c) => !in_array((int)$c['id'], $subIds, true)); ?>
  <?php if ($avail): ?>
    <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/subscribe')) ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <div class="field"><label>Add org-wide course(s) <span class="muted">(enrolls all <?= (int)$stats['members'] ?> member(s) now)</span></label>
        <div class="check-grid">
          <?php foreach ($avail as $c): ?>
            <label class="check"><input type="checkbox" name="course_ids[]" value="<?= (int)$c['id'] ?>"> <?= e($c['title']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-gold" type="submit">Subscribe &amp; enroll</button>
    </form>
  <?php endif; ?>
</div>

<!-- Groups inside the org -->
<div class="card">
  <h2>Groups in this organization</h2>
  <p class="muted">Different groups can subscribe to different courses. Open a group to manage its own subscriptions and members.</p>
  <?php if ($groups): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Group</th><th>Description</th><th></th></tr></thead>
      <tbody><?php foreach ($groups as $g): ?>
        <tr>
          <td><a href="<?= e(url('/admin/groups/' . $g['id'])) ?>"><strong><?= e($g['name']) ?></strong></a></td>
          <td class="muted"><?= e($g['description'] ?? '') ?></td>
          <td><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/groups/' . $g['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No groups yet in this organization.</p><?php endif; ?>

  <div class="grid grid-2" style="margin-top:12px">
    <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/groups/create')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label for="gname">Create a group here</label>
        <input id="gname" name="name" placeholder="e.g. Elementary Teachers" required></div>
      <button class="btn btn-navy btn-sm" type="submit">Create group</button>
    </form>
    <?php if ($attachable): ?>
    <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/groups/attach')) ?>" class="toolbar">
      <?= csrf_field() ?>
      <label for="attach" class="muted">Add an existing group:</label>
      <select id="attach" name="group_id" style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
        <?php foreach ($attachable as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm" type="submit">Add</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Members -->
<div class="card">
  <h2>Members</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Enrollments</th><th>Completions</th><th>Badges</th><th></th></tr></thead>
      <tbody>
      <?php if (!$members): ?>
        <tr><td colspan="6" class="muted">No members yet. Add some below, or via a group.</td></tr>
      <?php else: foreach ($members as $m): ?>
        <tr>
          <td><a href="<?= e(url('/admin/users/' . $m['id'])) ?>"><?= e(trim($m['first_name'].' '.$m['last_name'])) ?: 'View' ?></a></td>
          <td><?= e($m['email']) ?></td>
          <td><?= (int)$m['enrollments'] ?></td>
          <td><?= (int)$m['completions'] ?></td>
          <td><?= (int)$m['badges'] ?></td>
          <td>
            <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/members/remove')) ?>" style="display:inline"
                  data-confirm="Remove this member from the organization (and its groups)? Their enrollments and progress are kept.">
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
  <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/members/add')) ?>" style="margin-top:12px">
    <?= csrf_field() ?>
    <div class="field"><label for="emails">Add members by email</label>
      <input id="emails" name="emails" placeholder="ada@aldirk.edu, grace@aldirk.edu" required>
      <small>Separate with commas, spaces, or new lines. Accounts must already exist —
        create them in bulk via <a href="<?= e(url('/admin/import')) ?>">Imports</a>. New members are auto-enrolled in the org-wide courses.</small></div>
    <button class="btn btn-gold" type="submit">Add to organization</button>
  </form>
</div>

<!-- Managers -->
<div class="card">
  <h2>Organization managers</h2>
  <p class="muted">A manager runs this whole organization — all its groups — from their
    <a href="<?= e(url('/manage')) ?>">Manage</a> area, limited to the permissions you grant. They&rsquo;re added as a member too.</p>
  <?php if ($managers): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Can enroll</th><th>Can edit members</th><th>Can manage courses</th><th></th></tr></thead>
      <tbody><?php foreach ($managers as $m):
        $pe = $m['perm_enroll'] === null || (int)$m['perm_enroll'] === 1;
        $pm = $m['perm_members'] === null || (int)$m['perm_members'] === 1;
        $pc = (int)($m['perm_courses'] ?? 0) === 1; ?>
        <tr>
          <td><?= e(trim($m['first_name'].' '.$m['last_name'])) ?: '—' ?></td>
          <td><?= e($m['email']) ?></td>
          <td><?= $pe ? '✅' : '—' ?></td>
          <td><?= $pm ? '✅' : '—' ?></td>
          <td><?= $pc ? '✅' : '—' ?></td>
          <td><form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/manager/remove')) ?>" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">Remove</button></form></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php else: ?><p class="muted">No managers assigned.</p><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/manager/add')) ?>" style="margin-top:12px">
    <?= csrf_field() ?>
    <div class="field"><label for="memail">Assign a manager by email</label>
      <input id="memail" name="email" type="email" placeholder="manager@aldirk.edu" required></div>
    <label class="check" style="display:inline-flex"><input type="checkbox" name="perm_enroll" checked> Can enroll members</label>
    <label class="check" style="display:inline-flex"><input type="checkbox" name="perm_members" checked> Can add/edit members</label>
    <label class="check" style="display:inline-flex"><input type="checkbox" name="perm_courses"> Can manage course subscriptions</label>
    <div style="margin-top:10px"><button class="btn btn-navy" type="submit">Assign manager</button></div>
  </form>
</div>

<!-- Danger zone -->
<div class="card">
  <h2>Danger zone</h2>
  <p class="muted">Deleting an organization keeps its groups (they become standalone), and keeps every account,
    enrollment, and badge. Only the org, its membership list, subscriptions, and manager grants are removed.</p>
  <form method="post" action="<?= e(url('/admin/orgs/' . $o['id'] . '/delete')) ?>"
        data-confirm="Delete this organization? Its groups and members stay; only the org grouping is removed.">
    <?= csrf_field() ?>
    <button class="btn btn-outline" type="submit">Delete organization</button>
  </form>
</div>
