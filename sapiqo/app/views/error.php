<?php /** @var int $code @var string $message @var ?string $html */ ?>
<?php $labels = [423 => 'Locked', 413 => 'Upload too large', 410 => 'Unavailable', 404 => 'Not found', 403 => 'Forbidden']; ?>
<div class="card" style="text-align:center;max-width:520px;margin:40px auto">
  <h1 style="font-size:2.4rem;margin:0"><?= e($labels[$code] ?? (string)(int)$code) ?></h1>
  <p class="muted"><?= isset($html) ? $html : e($message) ?></p>
  <a class="btn btn-gold" href="<?= e(url('/')) ?>">Back to home</a>
</div>
