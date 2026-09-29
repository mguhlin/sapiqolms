<?php /** @var array $user */ ?>
<?= hero_banner('profile', 'Your profile', 'Update your photo, name, contact details, and password.') ?>

<div class="card" style="max-width:640px">
  <h2>Profile photo</h2>
  <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
    <?= avatar_html($user, 84) ?>
    <form method="post" action="<?= e(url('/profile/avatar')) ?>" enctype="multipart/form-data"
          style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <label class="sr-only" for="photo">Choose a profile photo</label>
      <input id="photo" type="file" name="photo" accept="image/png,image/jpeg,image/webp" required>
      <button class="btn btn-gold btn-sm" type="submit">Upload photo</button>
    </form>
    <?php if (has_avatar((int)$user['id'])): ?>
      <form method="post" action="<?= e(url('/profile/avatar')) ?>" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="remove" value="1">
        <button class="btn btn-outline btn-sm" type="submit">Remove</button>
      </form>
    <?php endif; ?>
  </div>
  <p class="muted" style="margin-top:10px">JPEG, PNG, or WebP; centered and resized to a square.
    If you signed in with Google or Microsoft, your provider photo is used automatically until you upload one.</p>
</div>

<div class="card auth--wide" style="max-width:640px">
  <form method="post" action="<?= e(url('/profile')) ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field"><label for="first_name">First name</label>
        <input id="first_name" name="first_name" value="<?= e($user['first_name']) ?>"></div>
      <div class="field"><label for="last_name">Last name</label>
        <input id="last_name" name="last_name" value="<?= e($user['last_name']) ?>"></div>
    </div>
    <div class="field"><label>Email</label>
      <input value="<?= e($user['email']) ?>" disabled>
      <small>Email is your sign-in and cannot be changed here. Ask an administrator if it must change.</small></div>
    <div class="row">
      <div class="field"><label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($user['phone']) ?>"></div>
      <div class="field"><label for="user_type">User type</label>
        <select id="user_type" name="user_type">
          <?php foreach (['','Teacher','Student','Administrator','Staff','Other'] as $t): ?>
            <option<?= $user['user_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="row">
      <div class="field"><label for="campus">Campus</label>
        <input id="campus" name="campus" value="<?= e($user['campus']) ?>"></div>
      <div class="field"><label for="organization">Organization / District</label>
        <input id="organization" name="organization" value="<?= e($user['organization']) ?>"></div>
    </div>
    <div class="field"><label for="password">New password (optional)</label>
      <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current">
      <small>At least 8 characters.</small></div>
    <button class="btn btn-gold" type="submit">Save changes</button>
  </form>
</div>
