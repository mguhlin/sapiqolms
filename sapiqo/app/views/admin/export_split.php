<?php /** @var string $slug @var string $token @var string $archive @var array $parts @var float $chunkMb */ ?>
<div class="page-head">
  <h1>Split export — <?= e($slug) ?></h1>
  <p>The course was packaged and split into <?= count($parts) ?> part(s) of about <?= e((string)$chunkMb) ?> MB each,
     so it can be re-imported on servers with tight upload limits. Download all parts, then use
     <strong>Import split parts</strong> on the Courses page to reassemble and install them.</p>
</div>

<div class="card">
  <p><strong>Archive:</strong> <code><?= e($archive) ?></code></p>
  <ol>
    <?php foreach ($parts as $part): ?>
      <li><a href="<?= e(url('/admin/exports/' . $token . '/' . rawurlencode($part))) ?>"><?= e($part) ?></a></li>
    <?php endforeach; ?>
  </ol>
  <p class="muted">Keep the parts together and in order (they are numbered). During import, select all parts at once —
    each is uploaded in its own request to stay under the limit, then the server reassembles them.</p>
  <p style="margin-top:14px"><a class="btn btn-outline" href="<?= e(url('/admin/courses')) ?>">&larr; Back to courses</a></p>
</div>
