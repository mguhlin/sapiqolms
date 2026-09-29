<?php /** @var array $admins @var array $devs @var array $managers @var array $groups */
$fullname = fn($u) => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['email'] ?? '');
?>
<div class="page-head">
  <h1>Account management</h1>
  <p>One place to grant and review privileged access. <a href="<?= e(url('/admin/users')) ?>">All users &rarr;</a></p>
</div>

<div class="card" style="background:var(--surface-alt)">
  <h2 style="margin-top:0">How access works</h2>
  <ul style="line-height:1.6;margin:0">
    <li><strong>Administrator</strong> — full control of everything (users, courses, settings, integrations).</li>
    <li><strong>Course developer</strong> — may create, edit, and import <em>course content</em> only. Cannot manage users, settings, or integrations.</li>
    <li><strong>Group manager</strong> — manages the members of specific group(s): enroll them into courses and/or edit their profiles, per the permissions you grant. A group manager <em>cannot</em> add or change course content unless you also give them course-developer access.</li>
  </ul>
</div>

<!-- Administrators -->
<div class="card">
  <h2>Administrators</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col"></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr>
          <td><strong><?= e($fullname($a)) ?></strong></td>
          <td><?= e($a['email']) ?></td>
          <td style="text-align:right"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users/' . $a['id'])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted" style="font-size:.86rem">Promote or demote administrators from a user's <a href="<?= e(url('/admin/users')) ?>">profile</a> (the role field). You cannot remove your own admin role.</p>
</div>

<!-- Course developers -->
<div class="card">
  <h2>Course developers</h2>
  <form method="post" action="<?= e(url('/admin/accounts/course-dev')) ?>" class="toolbar" style="margin-bottom:12px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="grant">
    <label class="sr-only" for="dev_email">User email</label>
    <input id="dev_email" name="email" type="email" required placeholder="person@school.edu"
           style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <button class="btn btn-navy" type="submit">Grant content-editing access</button>
  </form>
  <?php if (!$devs): ?>
    <p class="muted">No course developers yet. Grant access above to let someone author courses without full admin rights.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col"></th></tr></thead>
        <tbody>
        <?php foreach ($devs as $d): ?>
          <tr>
            <td><strong><?= e($fullname($d)) ?></strong></td>
            <td><?= e($d['email']) ?></td>
            <td style="text-align:right">
              <form method="post" action="<?= e(url('/admin/accounts/course-dev')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($d['email']) ?>">
                <input type="hidden" name="action" value="revoke">
                <button class="btn btn-outline btn-sm" type="submit">Revoke</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Group managers -->
<div class="card">
  <h2>Group managers</h2>
  <?php if (!$groups): ?>
    <p class="muted">Create a <a href="<?= e(url('/admin/groups')) ?>">group</a> first (e.g. a school or campus), then assign a manager for it here.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/admin/accounts/manager')) ?>" style="margin-bottom:14px;padding:12px;background:var(--surface-alt);border-radius:10px">
      <?= csrf_field() ?>
      <div class="row" style="align-items:flex-end">
        <div class="field"><label for="mgr_email">User email</label>
          <input id="mgr_email" name="email" type="email" required placeholder="lead@school.edu"></div>
        <div class="field"><label for="mgr_group">Group they manage</label>
          <select id="mgr_group" name="group_id" required>
            <option value="">Choose a group…</option>
            <?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <div style="display:flex;gap:18px;flex-wrap:wrap;margin:8px 0">
        <label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="perm_enroll" value="1" checked> Can enroll members into courses</label>
        <label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="perm_members" value="1" checked> Can add / edit member profiles</label>
        <label style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="allow_content" value="1"> Also allow editing course content</label>
      </div>
      <button class="btn btn-navy" type="submit">Assign group manager</button>
    </form>

    <?php if (!$managers): ?>
      <p class="muted">No group managers assigned yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th scope="col">Manager</th><th scope="col">Group</th>
            <th scope="col">Permissions</th><th scope="col"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($managers as $m):
              $enroll = $m['perm_enroll'] === null || (int) $m['perm_enroll'] === 1;
              $members = $m['perm_members'] === null || (int) $m['perm_members'] === 1; ?>
            <tr>
              <td><strong><?= e($fullname($m)) ?></strong><br><span class="muted"><?= e($m['email']) ?></span>
                <?php if ((int) ($m['can_edit_content'] ?? 0) === 1): ?><br><span class="pill pill--done" style="font-size:.72rem">+ content editing</span><?php endif; ?></td>
              <td><?= e($m['group_name']) ?></td>
              <td>
                <form method="post" action="<?= e(url('/admin/accounts/manager/perms')) ?>" style="margin:0;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                  <input type="hidden" name="group_id" value="<?= (int) $m['group_id'] ?>">
                  <label style="display:inline-flex;gap:5px;align-items:center;font-size:.86rem"><input type="checkbox" name="perm_enroll" value="1" <?= $enroll ? 'checked' : '' ?>> Enroll</label>
                  <label style="display:inline-flex;gap:5px;align-items:center;font-size:.86rem"><input type="checkbox" name="perm_members" value="1" <?= $members ? 'checked' : '' ?>> Members</label>
                  <button class="btn btn-outline btn-sm" type="submit">Save</button>
                </form>
              </td>
              <td style="text-align:right">
                <form method="post" action="<?= e(url('/admin/accounts/manager/remove')) ?>" style="display:inline"
                      data-confirm="Remove this group-manager grant? The user keeps their account and group membership.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                  <input type="hidden" name="group_id" value="<?= (int) $m['group_id'] ?>">
                  <button class="btn btn-outline btn-sm" type="submit">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
