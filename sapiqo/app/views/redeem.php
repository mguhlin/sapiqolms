<?php /** @var string $code */ $in = is_logged_in(); ?>
<div class="auth auth--wide card">
  <span class="tag">Redeem a code</span>
  <h1>Enter your enrollment code</h1>
  <p class="muted">
    <?php if ($in): ?>
      Have a code from your organization or invite? Enter it below to unlock your course(s).
    <?php else: ?>
      Start here with the code from your organization or invite. Next you’ll create an account
      (or sign in) and your course(s) unlock automatically.
    <?php endif; ?>
  </p>

  <form method="post" action="<?= e(url('/redeem')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="code">Enrollment code</label>
      <input id="code" name="code" value="<?= e($code ?? '') ?>" placeholder="e.g. ABCD-EF23-GH45" autocapitalize="characters" autofocus required>
    </div>
    <button class="btn btn-gold" type="submit"><?= $in ? 'Redeem' : 'Continue' ?></button>
    <?php if ($in): ?>
      <a class="btn btn-outline" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a>
    <?php else: ?>
      <a class="btn btn-outline" href="<?= e(url('/login')) ?>">I already have an account</a>
    <?php endif; ?>
  </form>
</div>
