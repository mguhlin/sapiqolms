<?php /** @var ?array $page @var ?string $html @var array $pages */
  $visible = array_filter($pages, 'help_can_see');
?>
<?php if ($page === null): ?>
  <?= hero_banner('help', t('help.title', 'Help & user guide'), t('help.subtitle', 'Step-by-step guides to every feature. Pages you can act on are shown for your role.')) ?>

  <?php
    $role = is_admin() ? 'admin' : ((function_exists('can_edit_content') && can_edit_content()) ? 'dev' : 'learner');
    $intro = [
      'learner' => t('help.intro_learner', 'Everything you need as a learner: find and take courses, complete quizzes, earn digital badges and certificates, redeem an enrollment code, join the discussion forums, and keep a permanent transcript of what you have finished.'),
      'dev'     => t('help.intro_dev', 'As a course developer, you will find guides for building courses in the visual block editor, managing your resource center and the gradebook, and configuring course settings (CPE and GT hours, prerequisites, expiry, forums) — plus everything a learner sees.'),
      'admin'   => t('help.intro_admin', 'As an administrator, this guide covers the whole platform: accounts and roles, organizations and groups, certifications, enrollment codes, imports and exports, backups, reports, branding, and integrations (SSO, LTI, API, updates) — plus the course-authoring and learner guides.'),
    ][$role];
    $roleLabel = ['learner' => t('help.role_learner', 'learner'), 'dev' => t('help.role_dev', 'course developer'), 'admin' => t('help.role_admin', 'administrator')][$role];
  ?>
  <div class="card" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:260px">
      <h2 style="margin:0 0 6px"><?= e(t('help.welcome', 'Welcome to Help')) ?></h2>
      <p style="margin:0 0 8px"><?= e(t('help.signed_in_as', 'You are signed in as a')) ?> <strong><?= e($roleLabel) ?></strong>. <?= e($intro) ?></p>
      <p class="muted" style="margin:0;font-size:.9rem"><?= e(t('help.browse_topics', 'Browse the topics below — only the ones relevant to your access are shown.')) ?>
        <?php $contactUrl = safe_url((string) (lms_config()['help_contact_url'] ?? '')); if ($contactUrl !== ''): ?>
        <?= e(t('help.cant_find', "Can't find what you need?")) ?> <a href="<?= e($contactUrl) ?>" target="_blank" rel="noopener"><?= e(t('help.contact_us', 'Contact us')) ?></a>.
        <?php endif; ?></p>
    </div>
  </div>

  <?php foreach (help_sections() as $sec):
    $inSec = array_filter($visible, fn($p) => $p['num'] >= $sec['min'] && $p['num'] <= $sec['max']);
    if (!$inSec) continue; ?>
    <div class="card">
      <h2><?= e($sec['label']) ?></h2>
      <ul class="help-index">
        <?php foreach ($inSec as $p): ?>
          <li><a href="<?= e(url('/help/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <div class="page-head">
    <p><a href="<?= e(url('/help')) ?>">&larr; <?= e(t('help.all_topics', 'All help topics')) ?></a></p>
  </div>
  <div class="help-layout">
    <aside class="help-nav card">
      <h2><?= e(t('help.topics', 'Topics')) ?></h2>
      <?php foreach (help_sections() as $sec):
        $inSec = array_filter($visible, fn($p) => $p['num'] >= $sec['min'] && $p['num'] <= $sec['max']);
        if (!$inSec) continue; ?>
        <div class="help-nav__group"><?= e($sec['label']) ?></div>
        <ul>
          <?php foreach ($inSec as $p): ?>
            <li><a href="<?= e(url('/help/' . $p['slug'])) ?>"<?= $p['slug'] === $page['slug'] ? ' class="is-current"' : '' ?>><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </aside>
    <article class="card doc"><?= $html ?></article>
  </div>
<?php endif; ?>
