<?php /** @var array $cfg */
$smtp = $cfg['smtp'] ?? [];
?>
<div class="page-head">
  <h1>Mail (SMTP)</h1>
  <p>Outgoing email for welcome messages, password resets, enrollment-expiry reminders, and course-completion notices.</p>
</div>

<div class="card">
  <h2>Current configuration</h2>
  <p style="font-size:1.05rem;margin:0">
    <?php if (!empty($cfg['enabled'])): ?>
      <span class="pill pill--done">Enabled</span>
    <?php else: ?>
      <span class="pill pill--progress">Disabled</span> <span class="muted">— emails are not sent</span>
    <?php endif; ?>
  </p>
  <table class="table" style="margin-top:14px">
    <tbody>
      <tr><th scope="row" style="text-align:left;white-space:nowrap">From</th>
        <td><?= e($cfg['from_name'] ?? '') ?> &lt;<?= e($cfg['from'] ?? '') ?>&gt;</td></tr>
      <tr><th scope="row" style="text-align:left">Transport</th>
        <td><?= e($cfg['transport'] ?? 'mail') ?><?= ($cfg['transport'] ?? '') === 'mail' ? ' (local server mail() — no SMTP relay configured)' : '' ?></td></tr>
      <?php if (($cfg['transport'] ?? '') === 'smtp'): ?>
        <tr><th scope="row" style="text-align:left">SMTP server</th>
          <td><?= e($smtp['host'] ?? '') ?>:<?= e((string) ($smtp['port'] ?? '')) ?>
            <span class="muted">(<?= e($smtp['secure'] ?? 'none') ?: 'none' ?>)</span></td></tr>
        <tr><th scope="row" style="text-align:left">SMTP account</th>
          <td><?= $smtp['user'] !== '' ? e($smtp['user']) : '<span class="muted">not set</span>' ?></td></tr>
        <tr><th scope="row" style="text-align:left">App password</th>
          <td><?= $smtp['pass'] !== '' ? '✅ configured' : '<span class="muted">not set</span>' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p class="muted" style="font-size:.86rem;margin-top:12px">
    These values live in <code>config.local.php</code>'s <code>'mail'</code> block — a secret (like the database
    password), so they're set in that file rather than this page. See the comment at the top of
    <code>app/mailer.php</code> for the Gmail SMTP walkthrough (host <code>smtp.gmail.com</code>, port 587, an
    <strong>App Password</strong> — not your normal Gmail password — and, if sending as a different address, a
    verified "Send mail as" alias in that Gmail account).
  </p>
</div>

<div class="card">
  <h2>Send a test email</h2>
  <p>Sends immediately using the configuration above, even if it's currently disabled — so you can verify Gmail/SMTP
    settings before turning it on. If it fails, the exact step that failed (connect, STARTTLS, login, etc.) and the
    server's own response are shown, which is usually enough to pinpoint the problem.</p>
  <form method="post" action="<?= e(url('/admin/mail/test')) ?>" class="toolbar">
    <?= csrf_field() ?>
    <label class="sr-only" for="to">Send test to</label>
    <input id="to" name="to" type="email" required placeholder="you@example.org"
           value="<?= e(current_user()['email'] ?? '') ?>" style="flex:1;min-width:240px">
    <button class="btn btn-gold" type="submit">Send test email</button>
  </form>
</div>
