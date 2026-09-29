<?php /** @var string $content @var string $__title */ ?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" dir="<?= e(locale_dir()) ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($__title) ?> · <?= e(lms_config()['app_name']) ?></title>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/assets/img/favicon-32.png')) ?>" />
  <link rel="icon" type="image/png" sizes="512x512" href="<?= e(url('/assets/img/favicon.png')) ?>" />
  <link rel="apple-touch-icon" href="<?= e(url('/assets/img/apple-touch-icon.png')) ?>" />
  <link rel="stylesheet" href="<?= e(url('/assets/css/lms.css')) ?>" />
  <?= brand_theme_style() ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<?php if (function_exists('is_impersonating') && is_impersonating()): $__imp = impersonator_user(); $__act = current_user(); ?>
  <div class="impersonation-bar" role="alert"
       style="background:#b45309;color:#fff;padding:8px 0;font-size:.9rem;position:relative;z-index:50">
    <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <span>👁️ Viewing as <strong><?= e(trim(($__act['first_name'] ?? '') . ' ' . ($__act['last_name'] ?? '')) ?: ($__act['email'] ?? '')) ?></strong>
        for troubleshooting<?php if ($__imp): ?> · admin <?= e($__imp['email']) ?><?php endif; ?></span>
      <form method="post" action="<?= e(url('/impersonate/stop')) ?>" style="margin:0">
        <?= csrf_field() ?>
        <button type="submit" style="background:#fff;color:#b45309;border:0;border-radius:6px;padding:5px 12px;font-weight:700;cursor:pointer">Return to admin</button>
      </form>
    </div>
  </div>
<?php endif; ?>
<header class="topbar">
  <div class="wrap topbar__inner">
    <a class="brand" href="<?= e(url('/')) ?>">
      <?php if (lms_config()['logo'] !== ''): ?>
        <img class="brand__logo" src="<?= e(url('/brand/logo')) ?>" alt="<?= e(lms_config()['app_name']) ?>" style="height:42px;width:auto;display:block">
        <span><?= e(lms_config()['app_name']) ?></span>
      <?php else: ?>
        <span class="brand__mark"><?= e(lms_config()['brand_mark']) ?></span>
        <span><?= e(lms_config()['app_name']) ?></span>
      <?php endif; ?>
    </a>
    <button class="nav-toggle" type="button" aria-label="Toggle menu" aria-controls="topnav" aria-expanded="false"
            data-nav-toggle="topnav">☰</button>
    <nav class="topnav" id="topnav" aria-label="Primary">
      <?php if (is_logged_in()): $me = current_user(); ?>
        <a href="<?= e(url('/dashboard')) ?>"><?= e(t('nav.dashboard', 'Dashboard')) ?></a>
        <a href="<?= e(url('/catalog')) ?>"><?= e(t('nav.catalog', 'Catalog')) ?></a>
        <?php if (!is_admin()): ?><a href="<?= e(url('/redeem')) ?>"><?= e(t('nav.redeem', 'Redeem a code')) ?></a><?php endif; ?>
        <a href="<?= e(url('/forum')) ?>"><?= e(t('nav.forum', 'Forum')) ?></a>
        <?php if (is_admin()): ?>
          <details class="navdrop">
            <summary><?= e(t('nav.admin', 'Admin')) ?></summary>
            <div class="navdrop-menu">
              <a href="<?= e(url('/admin')) ?>">📈 Overview</a>
              <a href="<?= e(url('/admin/certifications')) ?>">🎓 <?= e(t('nav.certifications', 'Certifications')) ?></a>
              <a href="<?= e(url('/admin/share')) ?>">📡 <?= e(t('nav.share', 'Share on network')) ?></a>
              <a href="<?= e(url('/admin/legal-editor')) ?>">📜 Terms &amp; Privacy</a>
              <a href="<?= e(url('/admin/help-editor')) ?>">❓ Help pages</a>
              <?php foreach (admin_sections() as $skey => $sec): ?>
                <a href="<?= e(url('/admin/section/' . $skey)) ?>"><?= ui_icon($skey, 18) ?: $sec['icon'] ?> <?= e($sec['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
        <?php if (!is_admin() && can_edit_content()): ?><a href="<?= e(url('/admin/courses')) ?>"><?= e(t('nav.author', 'Author')) ?></a><?php endif; ?>
        <?php if (!is_admin() && manages_any_groups()): ?><a href="<?= e(url('/manage')) ?>"><?= e(t('nav.manage', 'Manage')) ?></a><?php endif; ?>
        <a href="<?= e(url('/transcript')) ?>"><?= e(t('nav.transcript', 'Transcript')) ?></a>
        <a href="<?= e(url('/help')) ?>"><?= e(t('nav.help', 'Help')) ?></a>
        <a href="<?= e(url('/profile')) ?>"><?= e(t('nav.profile', 'Profile')) ?></a>
        <?php $unread = unread_count((int) $me['id']); ?>
        <a href="<?= e(url('/notifications')) ?>" class="notif-bell" aria-label="Notifications<?= $unread ? ' (' . $unread . ' unread)' : '' ?>" style="position:relative;text-decoration:none">
          <span aria-hidden="true" style="font-size:1.15rem">🔔</span>
          <?php if ($unread): ?><span class="notif-badge" style="position:absolute;top:-6px;right:-8px;background:var(--gold-500);color:var(--navy-900);font-size:.68rem;font-weight:700;border-radius:999px;padding:0 5px;min-width:16px;text-align:center"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </a>
        <span class="topnav__user" style="display:inline-flex;align-items:center;gap:8px">
          <?= avatar_html($me, 28) ?><?= e(trim($me['first_name'].' '.$me['last_name']) ?: $me['email']) ?>
        </span>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/logout')) ?>"><?= e(t('nav.signout', 'Sign out')) ?></a>
      <?php else: ?>
        <a href="<?= e(url('/login')) ?>"><?= e(t('nav.signin', 'Sign in')) ?></a>
        <?php if (lms_config()['allow_self_registration']): ?>
          <a class="btn btn-gold btn-sm" href="<?= e(url('/register')) ?>"><?= e(t('nav.register', 'Register')) ?></a>
        <?php endif; ?>
      <?php endif; ?>
      <?= language_switcher('lang-switcher') ?>
    </nav>
  </div>
</header>

<main class="wrap main" id="main">
  <?php foreach (take_flashes() as $f): ?>
    <div class="flash flash--<?= e($f['type']) ?>" role="status" aria-live="polite"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<footer class="footer">
  <div class="wrap">
    <?php if ((lms_config()['org_name'] ?? '') !== ''): ?>&copy; <?= date('Y') ?> <?= e(lms_config()['org_name']) ?>. <?php endif; ?>
    <a href="<?= e(url('/privacy')) ?>"><?= e(t('footer.privacy', 'Terms of Service & Privacy Policy')) ?></a>
    <?php $orgUrl = safe_url((string) (lms_config()['org_url'] ?? '')); if ($orgUrl !== ''): ?>
    · <a href="<?= e($orgUrl) ?>" target="_blank" rel="noopener"><?= e(lms_config()['org_name']) ?></a>
    <?php endif; ?>
    <?php $blogUrl = safe_url((string) (lms_config()['org_blog_url'] ?? '')); if ($blogUrl !== ''): ?>
    · <a href="<?= e($blogUrl) ?>" target="_blank" rel="noopener">Blog</a>
    <?php endif; ?>
    <div style="margin-top:6px;font-size:.85rem;opacity:.85">Sapiqo LMS &copy; 2026 Miguel Guhlin · Licensed under <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC&nbsp;BY-SA&nbsp;4.0</a></div>
  </div>
</footer>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<script nonce="<?= e(csp_nonce()) ?>">
// Close the Admin dropdown when clicking outside it.
document.addEventListener('click',function(e){
  document.querySelectorAll('details.navdrop[open]').forEach(function(d){ if(!d.contains(e.target)) d.removeAttribute('open'); });
});
</script>
</body>
</html>
