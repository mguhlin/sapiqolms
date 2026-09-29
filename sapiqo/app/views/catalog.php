<?php /** @var array $courses */ ?>
<?= hero_banner('catalog', t('catalog.title', 'Course catalog'), t('catalog.intro', 'Enroll and start learning. Your progress is saved to your account.')) ?>

<div class="card">
  <?php if (!$courses): ?>
    <?= empty_state('empty-courses', t('catalog.none', 'No courses are available yet.'), t('catalog.none_sub', 'New courses will appear here as they are published.')) ?>
  <?php else: foreach ($courses as $c):
    // Cover: the course's own art if set, otherwise a rotating default variant so
    // the catalog looks intentional rather than repetitive.
    $variants = ['course-cover-default.webp', 'course-cover-default-teal-navy.webp', 'course-cover-default-plum-navy.webp'];
    $cover = url('/assets/img/' . $variants[(int)$c['id'] % 3]);
  ?>
    <div class="course-row">
      <img class="course-row__cover" src="<?= e($cover) ?>" alt="" loading="lazy">
      <div style="flex:1">
        <h3><?= e($c['title']) ?>
          <?php if (!empty($c['is_certification'])): ?> <span class="pill" style="background:#fdf3d3;color:#8a6d1a">Certification</span><?php endif; ?>
          <?php if (($c['status'] ?? 'published') === 'draft'): ?> <span class="pill pill--progress">Draft</span><?php endif; ?>
        </h3>
        <span class="muted"><?php $cpe=(float)($c['cpe_hours']??0); if ($cpe>0): ?><strong><?= $cpe==(int)$cpe?(int)$cpe:rtrim(rtrim(number_format($cpe,1),'0'),'.') ?> CPE Hours</strong> · <?php endif;
          $gt=(float)($c['gt_hours']??0); if ($gt>0): ?><strong><?= $gt==(int)$gt?(int)$gt:rtrim(rtrim(number_format($gt,1),'0'),'.') ?> GT Hours</strong> · <?php endif; ?><?= (int)$c['total_units'] ?> <?= e(t('catalog.steps', 'steps')) ?>
          <?php if ($c['enrolled']): ?> · <span class="pill pill--progress"><?= e(t('catalog.enrolled', 'Enrolled')) ?></span><?php endif; ?>
          <?php if (!empty($c['locked_by'])): ?> · <span class="pill pill--progress">🔒 <?= e(t('catalog.requires', 'Requires')) ?> <?= e($c['locked_by']['title']) ?></span><?php endif; ?></span>
      </div>
      <?php if ($c['enrolled']): ?>
        <a class="btn btn-navy" href="<?= e(url('/learn/' . $c['slug'])) ?>"><?= e(t('catalog.continue', 'Continue')) ?></a>
      <?php elseif (!empty($c['locked_by'])): ?>
        <a class="btn btn-outline" href="<?= e(url('/learn/' . $c['locked_by']['slug'])) ?>"><?= e(t('catalog.start_prereq', 'Start prerequisite')) ?></a>
      <?php elseif (!empty($purchase_mode)): ?>
        <a class="btn btn-gold" href="<?= e($c['purchase_url'] ?: 'https://example.org/courses') ?>" target="_blank" rel="noopener"><?= e(t('catalog.purchase', 'Purchase')) ?> ↗</a>
      <?php else: ?>
        <a class="btn btn-navy" href="<?= e(url('/learn/' . $c['slug'])) ?>"><?= e(t('catalog.enroll', 'Enroll & start')) ?></a>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
