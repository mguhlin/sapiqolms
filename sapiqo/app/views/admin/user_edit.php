<?php /** @var array $u @var array $courses @var array $groups @var array $userGroupIds */ ?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
  <div>
    <h1>Edit user</h1>
    <p><a href="<?= e(url('/admin/users')) ?>">&larr; Back to users</a></p>
  </div>
  <?php if (($u['role'] ?? '') !== 'admin' && (int) $u['id'] !== (int) (current_user()['id'] ?? 0)): ?>
    <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/impersonate')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <button class="btn btn-outline btn-sm" type="submit"
              title="Sign in as this user for troubleshooting; you can switch back anytime">👁️ View as this user</button>
    </form>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(url('/admin/users/' . $u['id'])) ?>">
  <?= csrf_field() ?>

  <div class="card">
    <h2>Account</h2>
    <div class="row">
      <div class="field"><label for="first_name">First name</label>
        <input id="first_name" name="first_name" value="<?= e($u['first_name']) ?>"></div>
      <div class="field"><label for="last_name">Last name</label>
        <input id="last_name" name="last_name" value="<?= e($u['last_name']) ?>"></div>
    </div>
    <div class="row">
      <div class="field"><label for="email">Email (sign-in)</label>
        <input id="email" name="email" type="email" value="<?= e($u['email']) ?>" required></div>
      <div class="field"><label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($u['phone']) ?>"></div>
    </div>
    <div class="row">
      <div class="field"><label for="user_type">User type</label>
        <select id="user_type" name="user_type">
          <?php foreach (['','Teacher','Student','Administrator','Staff','Other'] as $t): ?>
            <option<?= $u['user_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="role">Role</label>
        <select id="role" name="role">
          <option value="learner"<?= $u['role']==='learner'?' selected':'' ?>>Learner</option>
          <option value="admin"<?= $u['role']==='admin'?' selected':'' ?>>Administrator</option>
        </select></div>
    </div>
    <div class="row">
      <div class="field"><label for="campus">Campus</label>
        <input id="campus" name="campus" value="<?= e($u['campus']) ?>"></div>
      <div class="field"><label for="organization">Organization / District</label>
        <input id="organization" name="organization" value="<?= e($u['organization']) ?>"></div>
    </div>
    <div class="field"><label for="password">Reset password (optional)</label>
      <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current">
      <small>At least 8 characters. The learner should change it after signing in.
        Auth provider: <?= e($u['auth_provider']) ?>.</small></div>
  </div>

  <div class="card">
    <h2>Enrollments</h2>
    <p class="muted">Check to enroll, uncheck to remove. Progress and badges are kept
      on removal unless you tick the purge option.</p>
    <div class="table-wrap">
      <table class="table">
        <caption class="sr-only">Course enrollments for this user</caption>
        <thead><tr><th scope="col" style="width:60px">Enroll</th><th scope="col">Course</th><th scope="col">Progress</th><th scope="col">Quizzes</th><th scope="col">Manage</th></tr></thead>
        <tbody>
        <?php if (!$courses): ?>
          <tr><td colspan="5" class="muted">No courses exist yet.</td></tr>
        <?php else: foreach ($courses as $c): ?>
          <tr>
            <td style="text-align:center">
              <input type="checkbox" name="enroll[]" value="<?= (int)$c['id'] ?>" <?= $c['enrolled']?'checked':'' ?>>
            </td>
            <td><?= e($c['title']) ?> <?php if (!$c['active']): ?><span class="pill pill--progress">inactive</span><?php endif; ?></td>
            <td>
              <?php if ($c['enrolled']): ?>
                <div class="progress" style="max-width:200px"><div class="progress__bar" style="width:<?= (int)$c['percent'] ?>%"></div></div>
                <span class="muted"><?= (int)$c['percent'] ?>%</span>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td>
              <?php if ((int)$c['quiz_attempted'] > 0): ?>
                <span class="pill <?= (int)$c['quiz_passed'] === (int)$c['quiz_attempted'] ? 'pill--done' : 'pill--progress' ?>">
                  <?= (int)$c['quiz_passed'] ?>/<?= (int)$c['quiz_attempted'] ?> passed</span>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
            </td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users/' . (int)$u['id'] . '/course/' . urlencode($c['slug']))) ?>">Manage &rarr;</a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <label style="display:inline-flex;align-items:center;gap:8px;margin-top:12px">
      <input type="checkbox" name="purge_on_unenroll" value="1">
      <span class="muted">Also delete progress and any badge when removing an enrollment</span>
    </label>
  </div>

  <div class="card">
    <h2>Groups</h2>
    <?php if (!$groups): ?>
      <p class="muted">No groups yet. Create groups on the
        <a href="<?= e(url('/admin/groups')) ?>">Groups</a> page (e.g. by district), then assign users here.</p>
    <?php else: ?>
      <p class="muted">Assign this user to one or more groups.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px">
        <?php foreach ($groups as $grp): ?>
          <label style="display:inline-flex;align-items:center;gap:8px">
            <input type="checkbox" name="groups[]" value="<?= (int)$grp['id'] ?>"
              <?= in_array((int)$grp['id'], $userGroupIds, true) ? 'checked' : '' ?>>
            <span><?= e($grp['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="toolbar">
    <button class="btn btn-gold" type="submit">Save changes</button>
    <a class="btn btn-outline" href="<?= e(url('/admin/users')) ?>">Cancel</a>
  </div>
</form>

<div class="card">
  <h2>Password reset link</h2>
  <p class="muted">Generate a one-time, one-hour link the learner can use to set their own password.
    It will be emailed if email is configured, otherwise shown here to share securely.</p>
  <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/reset-link')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-outline" type="submit">Generate reset link</button>
  </form>
</div>
