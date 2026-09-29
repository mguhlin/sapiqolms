<?php /** @var array $rows @var string $q @var string $action @var array $actions */ ?>
<div class="page-head"><h1>Audit log</h1><p>An append-only record of privileged actions, for FERPA / Texas DPA accountability.</p></div>

<div class="card">
  <form method="get" action="<?= e(url('/admin/audit')) ?>" class="toolbar">
    <label class="sr-only" for="auditq">Search audit entries</label>
    <input id="auditq" name="q" value="<?= e($q) ?>" placeholder="Search actor, target, or detail"
           style="flex:1;min-width:200px;padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
    <label class="sr-only" for="auditaction">Filter by action</label>
    <select id="auditaction" name="action" style="padding:9px 12px;border:1.5px solid var(--line-strong);border-radius:9px">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $a === $action ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-navy" type="submit">Filter</button>
    <a class="btn btn-outline" href="<?= e(url('/admin/audit?export=1' . ($q !== '' ? '&q=' . urlencode($q) : '') . ($action !== '' ? '&action=' . urlencode($action) : ''))) ?>">Export CSV</a>
  </form>

  <div class="table-wrap">
    <table class="table">
      <caption class="sr-only">Audit log entries, most recent first</caption>
      <thead><tr>
        <th scope="col">When (UTC)</th><th scope="col">Actor</th><th scope="col">Action</th>
        <th scope="col">Target</th><th scope="col">Detail</th><th scope="col">IP</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="muted">No audit entries yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td style="white-space:nowrap"><?= e($r['created_at']) ?></td>
          <td><?= e($r['actor_email'] ?: '—') ?></td>
          <td><span class="pill"><?= e($r['action']) ?></span></td>
          <td><?= e(trim(($r['target_type'] ? $r['target_type'] . ' ' : '') . $r['target_id'])) ?: '—' ?></td>
          <td class="muted"><?= e($r['detail']) ?></td>
          <td class="muted"><?= e($r['ip']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted" style="margin-top:10px">Showing up to 500 most recent entries.</p>
</div>
