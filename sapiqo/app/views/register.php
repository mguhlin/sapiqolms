<?php /** @var array $courses @var string $code @var array $providers */ ?>
<div class="auth auth--wide card">
  <span class="tag">Create account</span>
  <h1>Register</h1>
  <p class="muted">Set up your account to enroll in courses and earn badges.</p>

  <?php if (!empty($providers)): ?>
    <div class="sso">
      <?php foreach ($providers as $key => $p): ?>
        <a class="btn btn-outline" href="<?= e(url('/auth/' . $key)) ?>">Continue with <?= e($p['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="divider">or use your email</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/register')) ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field">
        <label for="first_name">First name</label>
        <input id="first_name" name="first_name" required>
      </div>
      <div class="field">
        <label for="last_name">Last name</label>
        <input id="last_name" name="last_name" required>
      </div>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="email" required>
    </div>
    <div class="row">
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <small>At least 8 characters.</small>
      </div>
      <div class="field">
        <label for="phone">Phone (optional)</label>
        <input id="phone" name="phone" autocomplete="tel">
      </div>
    </div>
    <div class="row">
      <div class="field">
        <label for="campus">Campus</label>
        <input id="campus" name="campus" placeholder="e.g. Central High School">
      </div>
      <div class="field">
        <label for="organization">Organization / District</label>
        <input id="organization" name="organization" placeholder="e.g. Example ISD">
      </div>
    </div>
    <div class="row">
      <div class="field">
        <label for="user_type">User type</label>
        <select id="user_type" name="user_type">
          <option value="">Select…</option>
          <option>Teacher</option><option>Student</option>
          <option>Administrator</option><option>Staff</option><option>Other</option>
        </select>
      </div>
      <div class="field">
        <label for="course">Enroll in a course (optional)</label>
        <select id="course" name="course">
          <option value="">None for now</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= e($c['slug']) ?>"><?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label for="code">Enrollment code (optional)</label>
      <input id="code" name="code" value="<?= e($code ?? '') ?>" placeholder="Have a code? Enter it to unlock your courses" autocapitalize="characters">
      <small>Given to you by your organization or on your invite — it enrolls you automatically.</small>
    </div>
    <button class="btn btn-gold" type="submit">Create account</button>
    <a class="btn btn-outline" href="<?= e(url('/login')) ?>">I already have an account</a>
  </form>
</div>
