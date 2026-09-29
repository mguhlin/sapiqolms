<?php /** @var array $course @var array $byModule */
$slug = $course['slug'];
?>
<?= hero_banner('forum', $course['title'] . ' — Discussion', 'Join the conversation in each forum below.') ?>
<p style="margin:-8px 0 16px"><a href="<?= e(url('/forum')) ?>">&larr; All forums</a></p>

<?php if (!$byModule): ?>
  <div class="card"><p class="muted">No discussion forums yet. A course developer can add forums to modules in the visual editor &mdash; once they do, those forums will appear here below.</p></div>
<?php endif; ?>

<?php foreach ($byModule as $moduleName => $boards): ?>
  <div class="card">
    <h2 style="margin-top:0"><?= e($moduleName) ?></h2>
    <div class="fb-list">
      <?php foreach ($boards as $b): $s = $b['stats']; ?>
        <a class="fb-board" href="<?= e(url('/forum/course/' . urlencode($slug) . '/' . rawurlencode($b['id']))) ?>">
          <span class="fb-ic">💬</span>
          <span class="fb-main">
            <strong><?= e($b['name']) ?></strong>
            <span class="muted fb-sub"><?= (int)$s['threads'] ?> thread<?= (int)$s['threads']===1?'':'s' ?> · <?= (int)$s['posts'] ?> post<?= (int)$s['posts']===1?'':'s' ?></span>
          </span>
          <span class="muted fb-last"><?= $s['last'] ? 'Active ' . e(forum_ago($s['last'])) : 'No posts yet' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<style>
.fb-list{display:flex;flex-direction:column;gap:8px}
.fb-board{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--line);border-radius:12px;
  text-decoration:none;color:inherit;transition:.12s}
.fb-board:hover{border-color:var(--navy-500);box-shadow:var(--shadow-sm);transform:translateY(-1px)}
.fb-ic{font-size:1.5rem}
.fb-main{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0}
.fb-sub{font-size:.82rem}
.fb-last{font-size:.82rem;white-space:nowrap}
@media (max-width:560px){.fb-last{display:none}}
</style>
