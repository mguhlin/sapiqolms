<?php /** @var array $orgs */ ?>
<div class="page-head"><h1>Organizations</h1>
  <p>A top-level client or district (e.g. <em>Aldirk ISD</em>). Enroll its people, subscribe the whole
     organization or its individual groups to courses, and hand day-to-day management to an org manager.</p></div>

<div class="card">
  <h2>Create an organization</h2>
  <form method="post" action="<?= e(url('/admin/orgs')) ?>" class="toolbar">
    <?= csrf_field() ?>
    <label class="sr-only" for="orgName">Organization name</label>
    <input id="orgName" name="name" placeholder="Organization name (e.g. Aldirk ISD)" required
           style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <label class="sr-only" for="orgDesc">Description</label>
    <input id="orgDesc" name="description" placeholder="Description (optional)"
           style="flex:1;min-width:200px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <button class="btn btn-gold" type="submit">Create organization</button>
  </form>
</div>

<div class="card">
  <h2>All organizations</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Organization</th><th>Members</th><th>Groups</th><th>Org-wide courses</th><th></th></tr></thead>
      <tbody>
      <?php if (!$orgs): ?>
        <tr><td colspan="5" class="muted">No organizations yet. Create one above.</td></tr>
      <?php else: foreach ($orgs as $o): ?>
        <tr>
          <td><a href="<?= e(url('/admin/orgs/' . $o['id'])) ?>"><strong><?= e($o['name']) ?></strong></a>
            <?php if ($o['description']): ?><br><span class="muted"><?= e($o['description']) ?></span><?php endif; ?></td>
          <td><?= (int)$o['member_count'] ?></td>
          <td><?= (int)$o['group_count'] ?></td>
          <td><?= (int)$o['course_count'] ?></td>
          <td><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/orgs/' . $o['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
