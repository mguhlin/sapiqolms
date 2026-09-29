<?php /** @var bool $valid @var string $token */ ?>
<div class="auth card">
  <span class="tag">Password reset</span>
  <h1>Choose a new password</h1>

  <?php if (!$valid): ?>
    <p>This reset link is invalid or has expired.</p>
    <p style="margin-top:16px"><a class="btn btn-gold" href="<?= e(url('/forgot')) ?>">Request a new link</a></p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/reset')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
        <small>At least 8 characters.</small>
      </div>
      <div class="field">
        <label for="password2">Confirm new password</label>
        <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="8" required>
      </div>
      <button class="btn btn-gold" type="submit">Set new password</button>
    </form>
  <?php endif; ?>
</div>
