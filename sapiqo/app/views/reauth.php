<?php /** @var string $next @var bool $has_password @var bool $has_mfa */ ?>
<div class="auth card">
  <span class="tag">Security check</span>
  <h1>Confirm it's you</h1>

  <?php if ($has_mfa || $has_password): ?>
    <p class="muted">This area can change the site's code, keys, or full data.
      <?= $has_mfa ? 'Enter a code from your authenticator app or a recovery code' : 'Enter your password' ?>
      to continue. You won't be asked again for 10 minutes.</p>
    <form method="post" action="<?= e(url('/reauth')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?php if ($has_mfa): ?>
        <div class="field">
          <label for="code">Verification or recovery code</label>
          <input id="code" name="code" autocomplete="one-time-code" required autofocus>
        </div>
      <?php else: ?>
        <div class="field">
          <label for="current_password">Password</label>
          <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus>
        </div>
      <?php endif; ?>
      <button class="btn btn-gold" type="submit">Continue</button>
    </form>
  <?php else: ?>
    <p class="muted">Your account signs in through single sign-on, so there's no password to confirm.
      Sign out and sign in again, then return here within 10 minutes.</p>
    <a class="btn btn-gold" href="<?= e(url('/logout')) ?>">Sign out and sign in again</a>
  <?php endif; ?>
  <p class="muted" style="margin-top:16px"><a href="<?= e(url('/dashboard')) ?>">Cancel</a></p>
</div>
