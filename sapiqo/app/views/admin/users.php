<?php /** @var array $users @var string $q @var int $page @var int $pages @var int $total @var array $courses @var array $groups */ ?>
<div class="page-head"><h1>Users</h1><p>Search accounts, run bulk actions, and manage roles. <?= (int)$total ?> total.</p></div>
<?php // Shared hidden form so each row's "View as" button can POST without nesting
      // inside the bulk-actions form (nested <form>s are invalid HTML). ?>
<form id="impForm" method="post" style="display:none"><?= csrf_field() ?></form>
<?php $meId = (int) (current_user()['id'] ?? 0); ?>

<div class="card">
  <form method="get" action="<?= e(url('/admin/users')) ?>" class="toolbar">
    <label class="sr-only" for="q">Search users</label>
    <input id="q" name="q" value="<?= e($q) ?>" placeholder="Search name, email, campus, or organization"
           style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <button class="btn btn-navy" type="submit">Search</button>
    <a class="btn btn-outline" href="<?= e(url('/admin/import')) ?>">Import CSV</a>
    <a class="btn btn-outline" href="<?= e(url('/admin/users.csv' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>"><?= $q !== '' ? 'Export results' : 'Export CSV' ?></a>
  </form>

  <form method="post" action="<?= e(url('/admin/users/bulk')) ?>" id="bulkForm">
    <?= csrf_field() ?>
    <div class="bulkbar" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:6px 0 14px;padding:10px 12px;background:var(--surface-alt);border-radius:10px">
      <label class="sr-only" for="bulkAction">Bulk action</label>
      <select id="bulkAction" name="action" style="padding:8px 10px;border:1.5px solid var(--line-strong);border-radius:8px">
        <option value="">Bulk action…</option>
        <option value="enroll">Enroll in course</option>
        <option value="unenroll">Remove from course</option>
        <option value="group_add">Add to group</option>
        <option value="group_remove">Remove from group</option>
        <option value="role_admin">Make administrator</option>
        <option value="role_learner">Make learner</option>
        <option value="delete">Delete users</option>
      </select>
      <label class="sr-only" for="bulkCourse">Course</label>
      <select id="bulkCourse" name="course_id" style="padding:8px 10px;border:1.5px solid var(--line-strong);border-radius:8px">
        <option value="">— course —</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
      </select>
      <label class="sr-only" for="bulkGroup">Group</label>
      <select id="bulkGroup" name="group_id" style="padding:8px 10px;border:1.5px solid var(--line-strong);border-radius:8px">
        <option value="">— group —</option>
        <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-navy btn-sm" type="submit" id="bulkApply"
        data-confirm="Apply this bulk action to the selected users?">Apply</button>
      <span class="muted" id="selCount" aria-live="polite">0 selected</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <caption class="sr-only">User accounts</caption>
        <thead><tr>
          <th scope="col" style="width:34px"><input type="checkbox" id="selAll" aria-label="Select all on this page"></th>
          <th scope="col">Name</th><th scope="col">Email</th><th scope="col">Type</th>
          <th scope="col">Campus</th><th scope="col">Organization</th><th scope="col">Role</th><th scope="col"></th>
        </tr></thead>
        <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="8" class="muted">No users found.</td></tr>
        <?php else: foreach ($users as $u): ?>
          <tr>
            <td><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int)$u['id'] ?>"
                       aria-label="Select <?= e(trim($u['first_name'].' '.$u['last_name']) ?: $u['email']) ?>"></td>
            <td><span style="display:inline-flex;align-items:center;gap:8px"><?= avatar_html($u, 26) ?>
              <a href="<?= e(url('/admin/users/' . $u['id'])) ?>"><?= e(trim($u['first_name'].' '.$u['last_name'])) ?: 'View' ?></a></span></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['user_type']) ?></td>
            <td><?= e($u['campus']) ?></td>
            <td><?= e($u['organization']) ?></td>
            <td><span class="pill <?= $u['role']==='admin'?'pill--done':'pill--progress' ?>"><?= e($u['role']) ?></span></td>
            <td style="white-space:nowrap">
              <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users/' . $u['id'])) ?>">Edit</a>
              <?php if ($u['role'] !== 'admin' && (int) $u['id'] !== $meId): ?>
                <button class="btn btn-ghost btn-sm" type="submit" form="impForm"
                        formaction="<?= e(url('/admin/users/' . $u['id'] . '/impersonate')) ?>"
                        title="Sign in as this user for troubleshooting">View as</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </form>

  <?php if ($pages > 1): ?>
    <nav class="pager-nav" aria-label="User list pages" style="display:flex;gap:8px;align-items:center;margin-top:14px">
      <?php $qs = $q !== '' ? '&q=' . urlencode($q) : ''; ?>
      <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users?page=' . ($page-1) . $qs)) ?>">&larr; Prev</a><?php endif; ?>
      <span class="muted">Page <?= (int)$page ?> of <?= (int)$pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users?page=' . ($page+1) . $qs)) ?>">Next &rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function(){
  var all=document.getElementById("selAll"), rows=Array.prototype.slice.call(document.querySelectorAll(".rowchk")),
      count=document.getElementById("selCount");
  function upd(){ var n=rows.filter(function(r){return r.checked;}).length; count.textContent=n+" selected"; }
  if(all) all.addEventListener("change",function(){ rows.forEach(function(r){r.checked=all.checked;}); upd(); });
  rows.forEach(function(r){ r.addEventListener("change",upd); });
  upd();
})();
</script>
