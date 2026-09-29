<?php /** @var array $groups */ ?>
<div class="page-head"><h1>Groups</h1>
  <p>Organize users (for example by district or campus) and track completion by group.</p></div>

<div class="card">
  <h2>Create a group</h2>
  <form method="post" action="<?= e(url('/admin/groups')) ?>" class="toolbar">
    <?= csrf_field() ?>
    <label class="sr-only" for="groupName">Group name</label>
    <input id="groupName" name="name" placeholder="Group name (e.g. Austin ISD)" required
           style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <label class="sr-only" for="groupDesc">Group description</label>
    <input id="groupDesc" name="description" placeholder="Description (optional)"
           style="flex:1;min-width:200px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <button class="btn btn-gold" type="submit">Create group</button>
  </form>
  <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
  <form method="post" action="<?= e(url('/admin/groups/from-organizations')) ?>"
        data-confirm="Create a group for each distinct Organization and add its users?">
    <?= csrf_field() ?>
    <button class="btn btn-outline" type="submit">Create groups from organizations (districts)</button>
    <span class="muted">One group per distinct Organization, with all matching users added.</span>
  </form>
  <form method="post" action="<?= e(url('/admin/groups/from-campuses')) ?>" style="margin-top:10px"
        data-confirm="Create a group for each distinct Campus and add its users?">
    <?= csrf_field() ?>
    <button class="btn btn-outline" type="submit">Create groups from campuses</button>
    <span class="muted">One group per distinct Campus (named &ldquo;Organization · Campus&rdquo;).</span>
  </form>
</div>

<div class="card">
  <h2>All groups</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Group</th><th>Members</th><th>Enrollments</th><th>Completions</th><th>Completion rate</th><th>Steps completed</th><th></th></tr></thead>
      <tbody>
      <?php if (!$groups): ?>
        <tr><td colspan="7" class="muted">No groups yet. Create one above.</td></tr>
      <?php else: foreach ($groups as $g): ?>
        <tr>
          <td><a href="<?= e(url('/admin/groups/' . $g['id'])) ?>"><strong><?= e($g['name']) ?></strong></a>
            <?php if ($g['description']): ?><br><span class="muted"><?= e($g['description']) ?></span><?php endif; ?></td>
          <td><?= (int)$g['members'] ?></td>
          <td><?= (int)$g['enrollments'] ?></td>
          <td><?= (int)$g['completions'] ?></td>
          <td><strong><?= (int)$g['rate'] ?>%</strong></td>
          <td><?= (int)$g['steps'] ?></td>
          <td><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/groups/' . $g['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
