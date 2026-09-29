<?php /** @var string $key @var array $section */
// Prefer a section-specific hero (heroes/admin-<key>.webp); hero_banner falls
// back to a plain heading if the image isn't present.
$hero = preg_match('/^[a-z]+$/', $key) ? 'admin-' . $key : 'admin';
?>
<?= hero_banner($hero, $section['label'], '') ?>

<p style="margin:-8px 0 16px"><a href="<?= e(url('/admin')) ?>">&larr; Admin overview</a></p>

<div class="qa-cards qa-cards--lg">
  <?php foreach ($section['cards'] as [$href, $title, $desc, $icon]): ?>
    <?php if ($href === '@rescan'): ?>
      <form method="post" action="<?= e(url('/admin/courses/rescan')) ?>" style="margin:0;display:contents">
        <?= csrf_field() ?>
        <button type="submit" class="qa-card"><span class="qa-ic"><?= $icon ?></span><b><?= e($title) ?></b><span><?= e($desc) ?></span></button>
      </form>
    <?php else: ?>
      <a class="qa-card" href="<?= e(url($href)) ?>"><span class="qa-ic"><?= $icon ?></span><b><?= e($title) ?></b><span><?= e($desc) ?></span></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<style>
.qa-cards--lg{grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}
.qa-card .qa-ic{font-size:1.8rem;display:block;margin-bottom:6px;line-height:1}
</style>
