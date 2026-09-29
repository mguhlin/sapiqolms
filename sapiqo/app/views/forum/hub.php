<?php /** @var array $announcements @var array $courses @var bool $can_announce @var bool $can_moderate @var bool $is_admin_view */
$body_html = fn($s) => nl2br(e($s));
$when = fn($ts) => $ts ? date('M j, Y g:i a', strtotime($ts) ?: time()) : '';
$isExpired = fn($a) => !empty($a['expires_at']) && strtotime($a['expires_at']) < time();
?>
<?= hero_banner('forum', 'Forum', 'Announcements for everyone, plus discussion boards for your courses.') ?>

<div class="card">
  <h2>📣 Announcements</h2>
  <?php if ($can_announce): ?>
    <form method="post" action="<?= e(url('/forum/announce')) ?>" style="margin-bottom:16px;padding:12px;background:var(--surface-alt);border-radius:10px">
      <?= csrf_field() ?>
      <div class="field"><label for="a_title">Title (optional)</label>
        <input id="a_title" name="title" maxlength="255" placeholder="e.g. New course available"></div>
      <div class="field"><label for="a_body">Announcement</label>
        <textarea id="a_body" name="body" rows="3" required placeholder="Visible to everyone with an account (read-only for them)."></textarea>
        <p class="muted" style="font-size:.82rem;margin:4px 0 0">Don't share sensitive personal information (health, financial, ID numbers, or anyone else's private details) in posts.</p></div>
      <label style="display:inline-flex;gap:6px;align-items:center;margin:4px 0"><input type="checkbox" name="pinned" value="1"> Pin to top</label>
      <div class="field" style="max-width:220px"><label for="a_expires">Expires (optional)</label>
        <input id="a_expires" name="expires_at" type="date">
        <small>After this date, regular users stop seeing it — admins can still view it here as history.</small></div>
      <div class="toolbar"><button class="btn btn-navy" type="submit">Post announcement</button></div>
    </form>
  <?php endif; ?>

  <?php if (!$announcements): ?>
    <p class="muted">No announcements yet.</p>
  <?php else: foreach ($announcements as $a): $expired = $isExpired($a); ?>
    <article class="forum-post" style="border-bottom:1px solid var(--surface-alt);padding:12px 0<?= $expired ? ';opacity:.6' : '' ?>">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <?php if ((int) $a['pinned'] === 1): ?><span class="pill pill--done" style="font-size:.7rem">📌 Pinned</span><?php endif; ?>
          <?php if ($expired): ?><span class="pill pill--progress" style="font-size:.7rem">⏳ Expired <?= e($when($a['expires_at'])) ?></span><?php endif; ?>
          <?php if (!empty($a['title'])): ?><strong style="font-size:1.05rem"><?= e($a['title']) ?></strong><br><?php endif; ?>
          <span class="muted" style="font-size:.84rem"><?= e(forum_author_name($a)) ?> · <?= e($when($a['created_at'])) ?><?php if (!empty($a['expires_at']) && !$expired): ?> · expires <?= e($when($a['expires_at'])) ?><?php endif; ?></span>
        </div>
        <?php if ($can_moderate): ?>
          <form method="post" action="<?= e(url('/forum/post/' . $a['id'] . '/delete')) ?>" data-confirm="Delete this announcement?" style="margin:0">
            <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">Delete</button>
          </form>
        <?php endif; ?>
      </div>
      <div style="margin-top:6px<?= (int) $a['hidden'] === 1 ? ';opacity:.5' : '' ?>"><?= $body_html($a['body']) ?></div>
    </article>
  <?php endforeach; endif; ?>
  <?php if ($is_admin_view && array_filter($announcements, $isExpired)): ?>
    <p class="muted" style="font-size:.82rem;margin-top:8px">⏳ Expired announcements are shown to you as an admin only — regular users no longer see them.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>💬 Course discussions</h2>
  <?php if (!$courses): ?>
    <p class="muted">You don't have any course forums yet. Enroll in a course to join its discussion.
      <a href="<?= e(url('/catalog')) ?>">Browse the catalog →</a></p>
  <?php else: ?>
    <div class="lms-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
      <?php foreach ($courses as $c): ?>
        <a class="qa-card" href="<?= e(url('/forum/course/' . urlencode($c['slug']))) ?>" style="text-decoration:none">
          <b><?= e($c['title']) ?></b>
          <span>Open the discussion boards for each module.</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
