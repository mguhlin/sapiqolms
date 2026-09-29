<?php /** @var array $u @var array $courses @var bool $can_members @var bool $can_enroll */
$can_members = $can_members ?? true;
$can_enroll  = $can_enroll ?? true;
?>
<div class="page-head">
  <h1>Edit member</h1>
  <p><a href="javascript:history.back()">&larr; Back</a></p>
</div>

<form method="post" action="<?= e(url('/manage/users/' . $u['id'])) ?>">
  <?= csrf_field() ?>
  <?php if ($can_members): ?>
  <div class="card">
    <h2>Profile</h2>
    <div class="row">
      <div class="field"><label for="first_name">First name</label>
        <input id="first_name" name="first_name" value="<?= e($u['first_name']) ?>"></div>
      <div class="field"><label for="last_name">Last name</label>
        <input id="last_name" name="last_name" value="<?= e($u['last_name']) ?>"></div>
    </div>
    <div class="field"><label>Email</label>
      <input value="<?= e($u['email']) ?>" disabled>
      <small>Email (sign-in) can only be changed by a full administrator.</small></div>
    <div class="row">
      <div class="field"><label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($u['phone']) ?>"></div>
      <div class="field"><label for="user_type">User type</label>
        <select id="user_type" name="user_type">
          <?php foreach (['','Teacher','Student','Administrator','Staff','Other'] as $t): ?>
            <option<?= $u['user_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
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
      <small>At least 8 characters.</small></div>
  </div>
  <?php endif; ?>

  <?php if ($can_enroll): ?>
  <div class="card">
    <h2>Enrollments</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th style="width:60px">Enroll</th><th>Course</th><th>Progress</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td style="text-align:center"><input type="checkbox" name="enroll[]" value="<?= (int)$c['id'] ?>" <?= $c['enrolled']?'checked':'' ?>></td>
            <td><?= e($c['title']) ?></td>
            <td><?php if ($c['enrolled']): ?>
              <div class="progress" style="max-width:200px"><div class="progress__bar" style="width:<?= (int)$c['percent'] ?>%"></div></div>
              <span class="muted"><?= (int)$c['percent'] ?>%</span>
            <?php else: ?><span class="muted">—</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="toolbar">
    <button class="btn btn-gold" type="submit">Save changes</button>
  </div>
</form>
