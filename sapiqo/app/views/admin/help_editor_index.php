<?php
/** @var array $pages */
/** @var array $sections */
?>
<div class="page-head">
  <h1>Help pages</h1>
  <p>Edit the in-app Help manual. Each topic opens in the same rich-text editor as Terms &amp; Privacy — changes
    go live at <code>/help/&lt;topic&gt;</code> immediately, no file access needed.</p>
</div>

<?php foreach ($sections as $sec): $rows = array_values(array_filter($pages, fn($p) => $p['num'] >= $sec['min'] && $p['num'] <= $sec['max'])); if (!$rows) continue; ?>
  <div class="card">
    <h2><?= e($sec['label']) ?></h2>
    <table class="table">
      <tbody>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td><?= e($p['title']) ?><?php if ($p['edited']): ?> <span class="pill pill--done">Edited</span><?php endif; ?></td>
            <td style="text-align:right;white-space:nowrap">
              <a class="btn" href="<?= e(url('/help/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <a class="btn btn-gold" href="<?= e(url('/admin/help-editor/' . $p['slug'])) ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>
