<?php /** @var array $providers */ ?>
<div class="auth-split">
  <div class="auth-aside" style="background-image:linear-gradient(180deg,rgba(7,27,56,.15),rgba(7,27,56,.45)),url('<?= e(url('/assets/img/auth-side.webp')) ?>')" aria-hidden="true"></div>
<div class="auth card">
  <div class="auth-brand">
    <?php if (lms_config()['logo'] !== ''): ?>
      <img class="auth-logo" src="<?= e(url('/brand/logo')) ?>" alt="<?= e(lms_config()['app_name']) ?>">
    <?php else: ?>
      <span class="brand__mark" style="width:42px;height:42px;font-size:1.35rem"><?= e(lms_config()['brand_mark']) ?></span>
      <strong style="font-size:1.2rem;color:var(--navy-900)"><?= e(lms_config()['app_name']) ?></strong>
    <?php endif; ?>
  </div>
  <span class="tag"><?= e(t('nav.signin', 'Sign in')) ?></span>
  <h1><?= e(t('auth.welcome', 'Welcome back')) ?></h1>
  <p class="muted"><?= e(lms_config()['app_tagline'] ?? '') ?> <?= e(t('auth.signin_sub', 'Sign in to continue your courses and view your badges.')) ?></p>

  <?php if ($providers): ?>
    <div class="sso">
      <?php foreach ($providers as $key => $p): ?>
        <a class="btn btn-outline" href="<?= e(url('/auth/' . $key)) ?>"><?= e(t('auth.continue_with', 'Continue with')) ?> <?= e($p['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="divider"><?= e(t('auth.or_email', 'or use your email')) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email"><?= e(t('auth.email', 'Email')) ?></label>
      <input id="email" name="email" type="email" autocomplete="email" required>
    </div>
    <div class="field">
      <label for="password"><?= e(t('auth.password', 'Password')) ?></label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-gold" type="submit"><?= e(t('auth.signin_btn', 'Sign in')) ?></button>
  </form>

  <p class="muted" style="margin-top:14px"><a href="<?= e(url('/forgot')) ?>"><?= e(t('auth.forgot', 'Forgot your password?')) ?></a></p>
  <?php if (lms_config()['allow_self_registration']): ?>
    <p class="muted" style="margin-top:4px"><?= e(t('auth.new_here', 'New here?')) ?> <a href="<?= e(url('/register')) ?>"><?= e(t('auth.create_account', 'Create an account')) ?></a>.</p>
  <?php endif; ?>
  <div class="divider"><?= e(t('auth.have_code', 'Have an enrollment code?')) ?></div>
  <a class="btn btn-outline" style="width:100%;justify-content:center" href="<?= e(url('/redeem')) ?>">🎟️ <?= e(t('auth.redeem_start', 'Redeem a code')) ?></a>
</div>
</div>
