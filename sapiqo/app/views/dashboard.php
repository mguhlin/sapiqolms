<?php /** @var array $user @var array $enrollments @var array $badges @var array $catalog */ ?>
<?= hero_banner('dashboard',
    (t('dash.hello', 'Hello')) . ', ' . ($user['first_name'] ?: 'there'),
    t('dash.intro', 'Track your progress, resume a course, and download the badges you have earned.')) ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="margin:0"><?= e(t('dash.your_courses', 'Your courses')) ?></h2>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/redeem')) ?>">🎟️ <?= e(t('dash.redeem', 'Redeem a code')) ?></a>
  </div>
  <?php if (!$enrollments): ?>
    <?= empty_state('empty-enrollments', t('dash.none', 'You are not enrolled in any courses yet.'),
        '<a href="' . e(url('/catalog')) . '">' . e(t('dash.browse', 'Browse the catalog')) . '</a>'
        . ' &nbsp;·&nbsp; <a href="' . e(url('/redeem')) . '">' . e(t('dash.redeem_have', 'Have a code? Redeem it')) . '</a>') ?>
  <?php else: foreach ($enrollments as $en): ?>
    <div class="course-row">
      <div style="flex:1">
        <h3><?= e($en['title']) ?></h3>
        <div class="progress"><div class="progress__bar" style="width:<?= (int)$en['percent'] ?>%"></div></div>
        <?php $pct = (int)$en['percent']; $ms = $pct >= 100 ? 100 : ($pct >= 50 ? 50 : ($pct >= 25 ? 25 : 0));
              $msFile = $ms ? dirname(__DIR__, 2) . "/public/assets/img/badges/milestone-$ms.png" : ''; ?>
        <span class="muted"><?php if ($ms && is_file($msFile)): ?><img class="milestone-chip" src="<?= e(url("/assets/img/badges/milestone-$ms.png")) ?>" width="22" height="22" alt="" title="<?= $ms ?>% milestone"><?php endif; ?><?= $pct ?>% <?= e(t('dash.complete', 'complete')) ?>
          <?php if ($en['status'] === 'completed'): ?><span class="pill pill--done"><?= e(t('dash.completed', 'Completed')) ?></span>
          <?php else: ?><span class="pill pill--progress"><?= e(t('dash.in_progress', 'In progress')) ?></span><?php endif; ?>
          <?php if ((int)$en['active'] === 0): ?><span class="pill pill--progress"><?= e(t('dash.unavailable', 'Unavailable')) ?></span><?php endif; ?>
          <?php if (!empty($en['last_seen_at'])): ?>
            · <span class="muted"><?= e(t('dash.last_active', 'Last active')) ?> <?= e(date('M j, Y', strtotime($en['last_seen_at']) ?: time())) ?></span>
          <?php endif; ?>
        </span>
      </div>
      <?php if ((int)$en['active'] === 0): ?>
        <span class="btn btn-outline" style="pointer-events:none;opacity:.6"><?= e(t('dash.unavailable', 'Unavailable')) ?></span>
      <?php else:
        $resumeHref = (!empty($en['last_step_id']) && $en['status'] !== 'completed')
          ? url('/courses/' . $en['slug'] . '/') . '#/' . rawurlencode($en['last_step_id'])
          : url('/learn/' . $en['slug']); ?>
        <a class="btn btn-navy" href="<?= e($resumeHref) ?>">
          <?= $en['status'] === 'completed' ? e(t('dash.review', 'Review')) : ($en['percent'] > 0 ? e(t('dash.resume', 'Resume')) : e(t('dash.start', 'Start'))) ?>
        </a>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="margin:0"><?= e(t('dash.your_badges', 'Your badges')) ?></h2>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/transcript')) ?>"><?= e(t('dash.view_transcript', 'View transcript →')) ?></a>
  </div>
  <?php if (!$badges): ?>
    <p class="muted"><?= e(t('dash.no_badges', 'Complete a course to earn your first badge.')) ?></p>
  <?php else: ?>
    <div class="badge-grid">
      <?php foreach ($badges as $b): ?>
        <div class="badge-card">
          <img src="<?= e(url('/badge/' . $b['code'])) ?>" alt="Badge for <?= e($b['course_title']) ?>">
          <p><strong><?= e($b['course_title']) ?></strong><br>
            <span class="muted"><?= e(date('M j, Y', strtotime($b['issued_at']) ?: time())) ?></span></p>
          <p class="code">Code: <?= e($b['code']) ?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
            <a class="btn btn-gold btn-sm" href="<?= e(url('/badge/' . $b['code'] . '?download=1')) ?>"><?= e(t('dash.badge_png', 'Badge (PNG)')) ?></a>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/certificate/' . $b['code'] . '.pdf')) ?>"><?= e(t('dash.cert_pdf', 'Certificate (PDF)')) ?></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
