<?php /** @var string $version @var int $schema @var array $backups @var bool $writable @var string $code_root @var string $edition */
$fmtSize = fn($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
?>
<div class="page-head">
  <h1>Software updates</h1>
  <p>Update the LMS software without losing your settings, users, courses, badges, or content.</p>
</div>

<div class="card">
  <h2>This install</h2>
  <p style="font-size:1.1rem;margin:0">
    Version <strong><?= e($version) ?></strong>
    <span class="muted">· data schema v<?= (int) $schema ?></span>
  </p>
  <?php if ($edition !== ''): ?>
    <p style="margin:6px 0 0">Edition: <strong><?= e($edition) ?></strong></p>
  <?php endif; ?>
  <p class="muted" style="font-size:.88rem;margin-top:10px">
    Updates replace only the <strong>code</strong> (the <code>sapiqo/</code> folder). Your database, uploaded files,
    branding, local configuration, and everything under <code>content/</code> live outside the code and are never touched.
    A backup of the current code is saved automatically before each update so you can roll back.
  </p>
  <?php if (!$writable): ?>
    <div class="flash flash--error" style="margin-top:10px">The code folder <code><?= e($code_root) ?></code> is not writable by the
      web server, so updates can't be applied here. Fix its permissions (or update the folder manually) and reload.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Apply an update</h2>
  <p>Upload an update package (<code>.tar.gz</code>) you were given or built. The installed version is compared to the
    package; only newer packages apply unless you force it.<?= $edition !== '' ? ' If the package was built from a different edition than this install, it\'s also refused unless you force it.' : '' ?></p>
  <form method="post" action="<?= e(url('/admin/update')) ?>" enctype="multipart/form-data"
        data-confirm="Apply this update? The current code will be backed up first.">
    <?= csrf_field() ?>
    <div class="field">
      <label for="package">Update package</label>
      <input id="package" name="package" type="file" accept=".gz,.tgz,.tar,application/gzip" required <?= $writable ? '' : 'disabled' ?>>
    </div>
    <label style="display:inline-flex;gap:8px;align-items:center;margin:6px 0">
      <input type="checkbox" name="force" value="1"> Apply anyway, even if it isn't newer, or from a different edition (reinstall / downgrade / override)
    </label>
    <div class="toolbar">
      <button class="btn btn-gold" type="submit" <?= $writable ? '' : 'disabled' ?>>Upload &amp; apply update</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Create an update package</h2>
  <p>Build a distributable package from <em>this</em> install's current code (version <?= e($version) ?>). Hand the file to
    another install and apply it there, or keep it as a code snapshot.</p>
  <a class="btn btn-navy" href="<?= e(url('/admin/update/download')) ?>">⬇ Download update package</a>
  <p class="muted" style="font-size:.84rem;margin-top:8px">From the command line you can also run
    <code>php bin/build-update.php</code>.</p>
</div>

<div class="card">
  <h2>Backups &amp; rollback</h2>
  <?php if (!$backups): ?>
    <p class="muted">No backups yet. One is created automatically the first time you apply an update.</p>
  <?php else: ?>
    <p>Each update saves the previous code here first. Roll back if an update causes trouble.</p>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th scope="col">Backup</th><th scope="col">Size</th><th scope="col"></th></tr></thead>
        <tbody>
        <?php foreach ($backups as $i => $b): ?>
          <tr>
            <td><code><?= e($b['file']) ?></code><?= $i === 0 ? ' <span class="pill pill--progress" style="font-size:.72rem">most recent</span>' : '' ?></td>
            <td><?= e($fmtSize($b['size'])) ?></td>
            <td style="text-align:right">
              <form method="post" action="<?= e(url('/admin/update/rollback')) ?>" style="display:inline"
                    data-confirm="Roll back to <?= e($b['file']) ?>? This replaces the current code with this backup.">
                <?= csrf_field() ?>
                <input type="hidden" name="file" value="<?= e($b['file']) ?>">
                <button class="btn btn-outline btn-sm" type="submit" <?= $writable ? '' : 'disabled' ?>>Roll back</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
