<?php /** @var array $course @var array $board @var array $tree @var bool $locked @var bool $can_post @var bool $can_moderate */
$slug = $course['slug'];
$action = url('/forum/course/' . urlencode($slug) . '/' . rawurlencode($board['id']));
$me = current_user();
$body_html = fn($s) => nl2br(e($s));

// Recursive post renderer.
$render = function (array $post, int $depth) use (&$render, $action, $can_moderate, $me, $body_html) {
    if ((int) $post['hidden'] === 1 && !$can_moderate) return '';   // hide subtree from non-mods
    $uid = (int) $post['user_id'];
    $mine = $uid === (int) ($me['id'] ?? 0);
    $author = ['id' => $uid, 'first_name' => $post['first_name'] ?? '', 'last_name' => $post['last_name'] ?? '', 'email' => $post['email'] ?? ''];
    ob_start(); ?>
    <div class="fp<?= (int)$post['hidden']===1?' fp--hidden':'' ?>" id="p<?= (int)$post['id'] ?>">
      <div class="fp-avatar"><?= function_exists('avatar_html') ? avatar_html($author, 40) : '' ?></div>
      <div class="fp-body">
        <div class="fp-head">
          <strong><?= e(forum_author_name($post)) ?></strong>
          <span class="muted fp-time"><?= e(forum_ago($post['created_at'])) ?></span>
          <?php if ((int)$post['hidden']===1): ?><span class="pill pill--progress" style="font-size:.68rem">hidden</span><?php endif; ?>
        </div>
        <div class="fp-text"><?= $body_html($post['body']) ?></div>
        <div class="fp-actions">
          <form method="post" action="<?= e(url('/forum/post/' . $post['id'] . '/like')) ?>" class="fp-like" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="fp-btn<?= (int)$post['liked']>0?' is-liked':'' ?>" data-id="<?= (int)$post['id'] ?>">
              <?= ui_icon('reaction-spark', 16) ?: '👍' ?> <span class="fp-likes"><?= (int)$post['likes'] ?></span></button>
          </form>
          <?php if ($can_post ?? true): ?>
            <button type="button" class="fp-btn fp-replybtn" data-reply-toggle>↩ Reply</button>
          <?php endif; ?>
          <?php if ($can_moderate): ?>
            <form method="post" action="<?= e(url('/forum/post/' . $post['id'] . '/hide')) ?>" style="margin:0">
              <?= csrf_field() ?><button class="fp-btn" type="submit"><?= (int)$post['hidden']===1?'Unhide':'Hide' ?></button>
            </form>
          <?php endif; ?>
          <?php if ($mine || $can_moderate): ?>
            <form method="post" action="<?= e(url('/forum/post/' . $post['id'] . '/delete')) ?>" style="margin:0" data-confirm="Delete this post?">
              <?= csrf_field() ?><button class="fp-btn fp-del" type="submit">Delete</button>
            </form>
          <?php endif; ?>
        </div>
        <form method="post" action="<?= e($action) ?>" class="fp-reply">
          <?= csrf_field() ?>
          <input type="hidden" name="parent_id" value="<?= (int)$post['id'] ?>">
          <textarea name="body" rows="2" required placeholder="Write a reply…"></textarea>
          <div style="margin-top:6px"><button class="btn btn-gold btn-sm" type="submit">Reply</button></div>
        </form>
        <?php if (!empty($post['children'])): ?>
          <div class="fp-children">
            <?php foreach ($post['children'] as $child) echo $render($child, $depth + 1); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php return ob_get_clean();
};
?>
<?= hero_banner('forum', $board['name'], $course['title'] . ' · ' . $board['module']) ?>
<p style="margin:-8px 0 16px"><a href="<?= e(url('/forum/course/' . urlencode($slug))) ?>">&larr; All forums in this course</a></p>

<?php
// The forum's discussion prompt (block content). Media paths are course-relative,
// so rewrite them to absolute since this page isn't served under /courses/<slug>/.
$prompt = trim((string) ($board['content'] ?? ''));
if ($prompt !== ''):
    $cbase = rtrim(url('/courses/' . $slug), '/') . '/';
    $prompt = preg_replace('~\b(src|href)="(?!https?:|//|/|data:|\#|mailto:)~i', '$1="' . $cbase, $prompt);
?>
  <div class="card fb-prompt"><?= $prompt ?></div>
<?php endif; ?>

<?php if ($can_post): ?>
  <div class="card">
    <h2 style="margin-top:0">Start a discussion</h2>
    <?php if ($locked): ?>
      <p class="muted" style="background:var(--surface-alt);padding:10px 12px;border-radius:8px">🔒 This forum is set to <strong>post before you see</strong> — share your own thoughts to reveal what others have posted.</p>
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>">
      <?= csrf_field() ?>
      <textarea name="body" rows="3" required placeholder="What would you like to discuss?"></textarea>
      <p class="muted" style="font-size:.82rem;margin:6px 0 0">Don't share sensitive personal information (health, financial, ID numbers, or anyone else's private details) in posts.</p>
      <div style="margin-top:8px"><button class="btn btn-gold" type="submit">Post</button></div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <?php $html = ''; foreach ($tree as $t) $html .= $render($t, 0); ?>
  <?php if (trim($html) === ''): ?>
    <p class="muted">No posts yet<?= $can_post ? ' — start the conversation above.' : '.' ?></p>
  <?php else: ?>
    <div class="fp-thread"><?= $html ?></div>
  <?php endif; ?>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
// AJAX likes (fallback: the form still posts + redirects).
document.querySelectorAll('.fp-like').forEach(f=>{
  f.addEventListener('submit',async e=>{
    e.preventDefault();
    const btn=f.querySelector('button'), r=await fetch(f.action,{method:'POST',headers:{'X-Requested-With':'fetch','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(new FormData(f))});
    try{ const d=await r.json(); if(d.ok){ f.querySelector('.fp-likes').textContent=d.count; btn.classList.toggle('is-liked',d.liked); } }catch(_){ location.reload(); }
  });
});
</script>

<style>
.fb-prompt img{max-width:100%;height:auto;border-radius:8px}
.fb-prompt figure{margin:1rem 0}
.fb-prompt .video-embed,.fb-prompt .doc-embed{position:relative;padding-bottom:56.25%;height:0;margin:1rem 0}
.fb-prompt .video-embed iframe,.fb-prompt .doc-embed iframe{position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:8px}
.fb-prompt video{max-width:100%;border-radius:8px}
.fp-thread{display:flex;flex-direction:column;gap:4px}
.fp{display:flex;gap:12px;padding:14px 0;border-top:1px solid var(--surface-alt)}
.fp-thread > .fp:first-child{border-top:0}
.fp--hidden{opacity:.55}
.fp-avatar{flex:none}
.fp-avatar img,.fp-avatar .avatar{width:40px;height:40px;border-radius:50%}
.fp-body{flex:1;min-width:0}
.fp-head{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.fp-time{font-size:.8rem}
.fp-text{margin:4px 0 8px;line-height:1.55;word-wrap:break-word}
.fp-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.fp-btn{border:1px solid var(--line);background:#fff;border-radius:999px;padding:4px 12px;font-size:.82rem;cursor:pointer;color:var(--ink-soft);font-family:inherit}
.fp-btn:hover{background:var(--surface-alt);color:var(--navy-700)}
.fp-btn.is-liked{background:var(--navy-800);color:#fff;border-color:var(--navy-800)}
.fp-del:hover{color:var(--danger);border-color:var(--danger)}
.fp-reply{display:none;margin-top:10px}
.fp-reply.open{display:block}
.fp-reply textarea{width:100%}
.fp-children{margin-top:10px;padding-left:16px;border-left:2px solid var(--surface-alt);display:flex;flex-direction:column;gap:2px}
@media (max-width:560px){.fp-children{padding-left:10px}}
</style>
