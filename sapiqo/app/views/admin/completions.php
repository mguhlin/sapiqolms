<?php /** @var array $rows @var array $filters @var array $orgs @var array $campuses @var array $groups */
$sel = fn($a, $b) => (string) $a === (string) $b ? ' selected' : '';
// Build the export URL keeping the current filters.
$qs = http_build_query(array_filter([
    'q' => $filters['q'], 'org' => $filters['org'], 'campus' => $filters['campus'],
    'status' => $filters['status'], 'group' => $filters['group'] ?: '',
], fn($v) => $v !== '' && $v !== 0));
$exportUrl = url('/admin/completions?' . ($qs ? $qs . '&' : '') . 'export=1');
$active = $filters['q'] !== '' || $filters['org'] !== '' || $filters['campus'] !== '' || $filters['status'] !== '' || $filters['group'] > 0;
?>
<div class="page-head"><h1>Completions</h1><p>Enrollment and completion across all courses.</p></div>

<div class="card">
  <form method="get" action="<?= e(url('/admin/completions')) ?>" class="cmp-filters">
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name or email…" class="cmp-search">
    <select name="group" data-autosubmit>
      <option value="0">All groups</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"<?= $sel($filters['group'], $g['id']) ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="org" data-autosubmit>
      <option value="">All organizations</option>
      <?php foreach ($orgs as $o): ?><option value="<?= e($o) ?>"<?= $sel($filters['org'], $o) ?>><?= e($o) ?></option><?php endforeach; ?>
    </select>
    <select name="campus" data-autosubmit>
      <option value="">All campuses</option>
      <?php foreach ($campuses as $c): ?><option value="<?= e($c) ?>"<?= $sel($filters['campus'], $c) ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <select name="status" data-autosubmit>
      <option value="">Any status</option>
      <option value="enrolled"<?= $sel($filters['status'], 'enrolled') ?>>Enrolled</option>
      <option value="completed"<?= $sel($filters['status'], 'completed') ?>>Completed</option>
    </select>
    <button class="btn btn-navy btn-sm" type="submit">Search</button>
    <?php if ($active): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/completions')) ?>" style="color:var(--navy-700);border-color:var(--line-strong)">Clear</a><?php endif; ?>
    <a class="btn btn-outline btn-sm cmp-export" href="<?= e($exportUrl) ?>">⬇ Export CSV<?= $active ? ' (filtered)' : '' ?></a>
  </form>

  <p class="muted" style="font-size:.85rem;margin:2px 0 12px"><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?><?= count($rows) >= 5000 ? ' (showing first 5000 — narrow with filters)' : '' ?>.</p>

  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th>Name</th><th>Email</th><th>Organization</th><th>Course</th>
        <th>Status</th><th>Completed</th><th>Credential</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">No matching enrollments.</td></tr>
      <?php else: foreach ($rows as $r): $code = $r['badge_code'] ?? ''; ?>
        <tr>
          <td><a href="<?= e(url('/admin/users/' . (int)$r['user_id'])) ?>"><?= e(trim($r['first_name'].' '.$r['last_name']) ?: 'View') ?></a></td>
          <td><?= e($r['email']) ?></td>
          <td><?= e($r['organization']) ?></td>
          <td><?= e($r['course']) ?></td>
          <td><span class="pill <?= $r['status']==='completed'?'pill--done':'pill--progress' ?>"><?= e($r['status']) ?></span></td>
          <td><?= e($r['completed_at'] ? date('M j, Y', strtotime($r['completed_at'])) : '—') ?></td>
          <td>
            <div class="cmp-cred">
              <?php if ($code): ?>
                <a href="<?= e(url('/badge/' . $code)) ?>" target="_blank" rel="noopener" title="View badge">
                  <img src="<?= e(url('/badge/' . $code)) ?>" alt="Badge" width="30" height="30" class="cmp-thumb"></a>
                <a class="btn btn-outline btn-xs" href="<?= e(url('/badge/' . $code . '?download=1')) ?>" title="Download badge PNG">Badge</a>
                <a class="btn btn-outline btn-xs" href="<?= e(url('/certificate/' . $code . '.pdf')) ?>" title="Download certificate PDF">Cert</a>
              <?php endif; ?>
              <a class="btn btn-outline btn-xs" href="<?= e(url('/admin/users/' . (int)$r['user_id'] . '/transcript')) ?>" title="View full transcript">Transcript</a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.cmp-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:6px}
.cmp-filters select,.cmp-search{padding:8px 10px;border:1.5px solid var(--line-strong);border-radius:9px;font:inherit;background:#fff}
.cmp-search{flex:1;min-width:200px}
.cmp-export{margin-left:auto}
.cmp-cred{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.cmp-thumb{border-radius:6px;background:#fff;vertical-align:middle}
.btn-xs{padding:3px 9px;font-size:.78rem;border-radius:7px}
@media (max-width:640px){.cmp-export{margin-left:0}}
</style>
