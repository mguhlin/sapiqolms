<?php /** @var bool $sent @var bool $mail_on */ ?>
<div class="auth card">
  <span class="tag">Password reset</span>
  <h1>Reset your password</h1>

  <?php if ($sent): ?>
    <p>If an account exists for that email address, we've started a password reset for it.</p>
    <?php if ($mail_on): ?>
      <p class="muted">Check your inbox for a link to choose a new password. It expires in one hour.</p>
    <?php else: ?>
      <p class="muted">Email delivery isn't configured on this server, so please contact your
        administrator to receive your reset link.</p>
    <?php endif; ?>
    <p style="margin-top:16px"><a class="btn btn-outline" href="<?= e(url('/login')) ?>">Back to sign in</a></p>
  <?php else: ?>
    <p class="muted">Enter your account email and we'll send a link to set a new password.</p>
    <form method="post" action="<?= e(url('/forgot')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required>
      </div>
      <button class="btn btn-gold" type="submit">Send reset link</button>
    </form>
    <p class="muted" style="margin-top:16px"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
  <?php endif; ?>
</div>
