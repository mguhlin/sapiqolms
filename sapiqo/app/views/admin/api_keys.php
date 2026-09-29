<?php /** @var array $keys @var ?string $new */ ?>
<div class="page-head"><h1>API keys</h1><p>Grant server-to-server access to the Sapiqo REST API (<code>/api/v1</code>).</p></div>

<?php if ($new): ?>
  <div class="card" style="border-left:5px solid var(--gold-500)">
    <h2>Your new API key</h2>
    <p class="muted">Copy it now — for security, it won't be shown again.</p>
    <input readonly value="<?= e($new) ?>" data-select-all
           style="width:100%;font-family:monospace;padding:10px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
  </div>
<?php endif; ?>

<div class="card">
  <h2>Create a key</h2>
  <form method="post" action="<?= e(url('/admin/api-keys')) ?>" class="toolbar">
    <?= csrf_field() ?>
    <label class="sr-only" for="name">Key name</label>
    <input id="name" name="name" placeholder="e.g. SIS sync, Reporting bot" required
           style="flex:1;min-width:220px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <button class="btn btn-gold" type="submit">Create key</button>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <caption class="sr-only">API keys</caption>
      <thead><tr><th scope="col">Name</th><th scope="col">Prefix</th><th scope="col">Created</th><th scope="col">Last used</th><th scope="col">Status</th><th scope="col"></th></tr></thead>
      <tbody>
      <?php if (!$keys): ?>
        <tr><td colspan="6" class="muted">No API keys yet.</td></tr>
      <?php else: foreach ($keys as $k): ?>
        <tr>
          <td><?= e($k['name']) ?></td>
          <td class="muted"><code><?= e($k['prefix']) ?>…</code></td>
          <td class="muted"><?= e($k['created_at']) ?></td>
          <td class="muted"><?= e($k['last_used_at'] ?: '—') ?></td>
          <td><?php if ($k['revoked_at']): ?><span class="pill pill--progress">Revoked</span><?php else: ?><span class="pill pill--done">Active</span><?php endif; ?></td>
          <td style="text-align:right">
            <?php if (!$k['revoked_at']): ?>
              <form method="post" action="<?= e(url('/admin/api-keys/' . (int)$k['id'] . '/revoke')) ?>" style="display:inline"
                    data-confirm="Revoke this key? Apps using it will stop working.">
                <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">Revoke</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted" style="margin-top:10px">Authenticate requests with <code>Authorization: Bearer &lt;key&gt;</code>.
    Endpoints: <code>GET /api/v1/courses</code>, <code>GET/POST /api/v1/users</code>,
    <code>GET/POST /api/v1/enrollments</code>, <code>GET /api/v1/completions</code>, <code>GET /api/v1/badges</code>.</p>
</div>
