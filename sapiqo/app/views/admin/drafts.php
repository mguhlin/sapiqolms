<?php /** @var array $drafts */ ?>
<div class="page-head">
  <h1>Course drafts</h1>
  <p>Unpublished drafts you've saved in the editor. Open one to keep working, then publish when ready.</p>
</div>

<div class="card">
  <div class="toolbar" style="margin-bottom:14px">
    <a class="btn btn-gold" href="<?= e(url('/admin/create')) ?>">New course</a>
    <a class="btn btn-outline" href="<?= e(url('/admin/courses')) ?>">All courses</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <caption class="sr-only">Saved course drafts</caption>
      <thead><tr><th scope="col">Title</th><th scope="col">Slug</th><th scope="col">Last saved (UTC)</th><th scope="col">Actions</th></tr></thead>
      <tbody>
      <?php if (!$drafts): ?>
        <tr><td colspan="4" class="muted">No drafts saved yet. Click “Save draft” in the editor to keep work in progress.</td></tr>
      <?php else: foreach ($drafts as $d): ?>
        <tr>
          <td><strong><?= e($d['title']) ?></strong></td>
          <td class="muted"><?= e($d['slug']) ?></td>
          <td class="muted"><?= e($d['updated_at']) ?></td>
          <td style="white-space:nowrap">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/create?draft=' . (int)$d['id'])) ?>">Open</a>
            <form method="post" action="<?= e(url('/admin/drafts/' . (int)$d['id'] . '/delete')) ?>" style="display:inline"
                  data-confirm="Delete this draft?">
              <?= csrf_field() ?>
              <button class="btn btn-outline btn-sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
