<?php /** @var array $codes @var array $courses @var string $q @var ?array $detail */
$base = base_url_absolute();
$fmt = function (?string $dt): string {
    if (!$dt) return '';
    $ts = strtotime($dt . ' UTC');
    return $ts ? gmdate('M j, Y', $ts) : '';
};
$status = function (array $c): array {
    if ((int) $c['active'] !== 1) return ['Inactive', 'progress'];
    if (!empty($c['expires_at']) && strtotime($c['expires_at'] . ' UTC') < time()) return ['Expired', 'progress'];
    if ($c['max_uses'] !== null && (int) $c['used_count'] >= (int) $c['max_uses']) return ['Full', 'progress'];
    return ['Active', 'done'];
};
?>
<div class="page-head">
  <h1>Enrollment codes</h1>
  <p>Generate a code (or enter one from another system) that auto-enrolls learners into one or many courses.
     Share it, or hand out a redeem link; learners enter it at sign-up, on <code><?= e(url('/redeem')) ?></code>, or via a link.</p>
</div>

<div class="card">
  <h2>Create a code</h2>
  <form method="post" action="<?= e(url('/admin/codes')) ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field">
        <label for="label">Label <span class="muted">(internal — who/what it's for)</span></label>
        <input id="label" name="label" placeholder="e.g. Aldirk ISD — Fall cohort" required>
      </div>
      <div class="field">
        <label for="custom_code">Code</label>
        <input id="custom_code" name="custom_code" placeholder="Leave blank to auto-generate" autocapitalize="characters">
        <small>Blank = a random code is generated. Or paste an external code (letters, digits, dashes).</small>
      </div>
    </div>
    <div class="field">
      <label>Courses this code grants <span class="muted">(pick one or many for a series / group subscription)</span></label>
      <div class="table-wrap" style="max-height:220px;overflow:auto;border:1px solid var(--line,#e2e4ea);border-radius:8px;padding:8px">
        <?php if (!$courses): ?>
          <p class="muted" style="margin:6px">No courses yet — create a course first.</p>
        <?php else: foreach ($courses as $c): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:4px 6px">
            <input type="checkbox" name="course_ids[]" value="<?= (int) $c['id'] ?>">
            <span><?= e($c['title']) ?><?php if ((int) ($c['active'] ?? 1) !== 1): ?> <span class="muted">(hidden)</span><?php endif; ?></span>
          </label>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="row">
      <div class="field">
        <label for="max_uses">Seats / max uses</label>
        <input id="max_uses" name="max_uses" type="number" min="0" placeholder="Blank or 0 = unlimited">
        <small>How many people can redeem it (a group subscription's seat count).</small>
      </div>
      <div class="field">
        <label for="expires_at">Expires</label>
        <input id="expires_at" name="expires_at" type="date">
        <small>Optional. After this date the code stops working.</small>
      </div>
    </div>
    <button class="btn btn-gold" type="submit">Create code</button>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="margin:0">Issued codes</h2>
    <form method="get" action="<?= e(url('/admin/codes')) ?>" style="display:flex;gap:6px">
      <input name="q" value="<?= e($q) ?>" placeholder="Search code or label…" aria-label="Search codes">
      <button class="btn btn-outline btn-sm" type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/codes')) ?>">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap"><table class="table">
    <caption class="sr-only">Issued enrollment codes</caption>
    <thead><tr>
      <th scope="col">Code</th><th scope="col">Label</th><th scope="col">Courses</th>
      <th scope="col">Used</th><th scope="col">Expires</th><th scope="col">Status</th><th scope="col"></th>
    </tr></thead>
    <tbody>
      <?php if (!$codes): ?>
        <tr><td colspan="7" class="muted"><?= $q !== '' ? 'No codes match that search.' : 'No codes issued yet.' ?></td></tr>
      <?php else: foreach ($codes as $c):
        [$slabel, $sclass] = $status($c);
        $redeemLink = $base . '/redeem?code=' . urlencode($c['code']); ?>
        <tr>
          <td><code><?= e($c['code']) ?></code></td>
          <td><?= e($c['label']) ?: '<span class="muted">—</span>' ?></td>
          <td class="muted">
            <?php $titles = array_map(fn($x) => $x['title'], $c['courses']);
                  echo e(implode(', ', array_slice($titles, 0, 2)));
                  if (count($titles) > 2) echo ' <span class="muted">+' . (count($titles) - 2) . ' more</span>'; ?>
          </td>
          <td><?= (int) $c['redeemed'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?></td>
          <td class="muted"><?= e($fmt($c['expires_at'])) ?: '—' ?></td>
          <td><span class="pill pill--<?= $sclass ?>"><?= e($slabel) ?></span></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/codes?code_id=' . (int) $c['id'])) ?>">Redemptions</a>
            <form method="post" action="<?= e(url('/admin/codes/toggle')) ?>" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit"><?= (int) $c['active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
            </form>
            <form method="post" action="<?= e(url('/admin/codes/delete')) ?>" style="display:inline" data-confirm="Delete code <?= e($c['code']) ?>? Enrolled learners keep their access.">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
        <tr><td colspan="7" class="muted" style="padding-top:0">
          <small>Redeem link: <code><?= e($redeemLink) ?></code></small>
        </td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>

<?php if ($detail): $c = $detail['code']; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h2 style="margin:0">Code <code><?= e($c['code']) ?></code> — redemptions</h2>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/codes')) ?>">Back to all codes</a>
  </div>
  <p class="muted"><?= e($c['label']) ?></p>
  <p><strong>Courses affected (<?= count($detail['courses']) ?>):</strong>
    <?= $detail['courses'] ? e(implode(', ', array_map(fn($x) => $x['title'], $detail['courses']))) : '<span class="muted">none</span>' ?></p>
  <div class="table-wrap"><table class="table">
    <caption class="sr-only">Users who redeemed this code</caption>
    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Redeemed</th><th scope="col"></th></tr></thead>
    <tbody>
      <?php if (!$detail['redemptions']): ?>
        <tr><td colspan="4" class="muted">No one has redeemed this code yet.</td></tr>
      <?php else: foreach ($detail['redemptions'] as $r): ?>
        <tr>
          <td><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?: '<span class="muted">—</span>' ?></td>
          <td class="muted"><?= e($r['email']) ?></td>
          <td class="muted"><?= e($fmt($r['redeemed_at'])) ?></td>
          <td style="text-align:right"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/users?q=' . urlencode($r['email']))) ?>">Open user</a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
