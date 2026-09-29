<?php /** @var array $items */ ?>
<?= hero_banner('notifications', t('notif.title', 'Notifications'), t('notif.intro', 'Your recent activity and updates.')) ?>

<div class="card">
  <?php if (!$items): ?>
    <?= empty_state('empty-notifications', t('notif.none', "You're all caught up"), t('notif.none_sub', 'New activity and updates will show up here.')) ?>
  <?php else: ?>
    <ul class="notif-list" style="list-style:none;margin:0;padding:0">
      <?php foreach ($items as $n): ?>
        <li style="display:flex;gap:12px;align-items:flex-start;padding:12px 4px;border-bottom:1px solid var(--line)">
          <span aria-hidden="true" style="font-size:1.1rem"><?= empty($n['read_at']) ? '🔵' : '⚪' ?></span>
          <div style="flex:1">
            <?php if (($n['url'] ?? '') !== ''): ?>
              <a href="<?= e(str_starts_with($n['url'], 'http') ? $n['url'] : url($n['url'])) ?>"><?= e($n['message']) ?></a>
            <?php else: ?>
              <?= e($n['message']) ?>
            <?php endif; ?>
            <div class="muted" style="font-size:.8rem"><?= e($n['created_at']) ?> UTC</div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
