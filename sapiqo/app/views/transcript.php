<?php
/** @var array $user @var array $rows @var float $total_cpe @var float $total_gt */
$total_gt = $total_gt ?? 0;
$showGt = $total_gt > 0;
$cfg = lms_config();
$org = $cfg['org_name'] ?? '';
$provider = function_exists('setting') ? setting('cert_provider', $cfg['cert_provider'] ?? '') : ($cfg['cert_provider'] ?? '');
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
$name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
?>
<?= hero_banner('transcript', t('transcript.title', 'Transcript'), t('transcript.intro', 'Your completed courses, CPE hours earned, and badges.')) ?>
<div class="toolbar no-print" style="justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin:-8px 0 16px">
  <p class="muted" style="font-size:.86rem;margin:0"><?= e(t('transcript.retention', 'These records are permanent. Your badge, certificate, and CPE hours stay here even after a course\'s access period ends.')) ?></p>
  <button type="button" class="btn btn-outline" data-print><?= e(t('transcript.print', 'Print / Save as PDF')) ?></button>
</div>
<?php if (!empty($admin_view)): ?>
  <div class="flash flash--success no-print" role="status">Viewing <strong><?= e($name ?: $user['email']) ?></strong>'s transcript as an administrator.
    <a href="<?= e(url('/admin/completions')) ?>">&larr; Back to completions</a></div>
<?php endif; ?>

<div class="card transcript">
  <div class="transcript__head">
    <div>
      <div class="muted" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em"><?= e(t('transcript.learner', 'Learner')) ?></div>
      <strong style="font-size:1.15rem"><?= e($name ?: $user['email']) ?></strong><br>
      <span class="muted"><?= e($user['email']) ?><?= !empty($user['organization']) ? ' · ' . e($user['organization']) : '' ?></span>
    </div>
    <div class="transcript__totals">
      <div class="stat"><b><?= count($rows) ?></b><span><?= e(t('transcript.completed', 'Courses completed')) ?></span></div>
      <div class="stat"><b><?= e($fmt($total_cpe)) ?></b><span><?= e(t('transcript.cpe', 'CPE hours')) ?></span></div>
      <?php if ($showGt): ?><div class="stat"><b><?= e($fmt($total_gt)) ?></b><span><?= e(t('transcript.gt', 'GT hours')) ?></span></div><?php endif; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted" style="margin-top:18px"><?= e(t('transcript.none', 'No completed courses yet. Finish a course to earn a badge and CPE hours.')) ?>
      <a href="<?= e(url('/catalog')) ?>"><?= e(t('transcript.browse', 'Browse the catalog')) ?></a>.</p>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:16px">
      <table class="table">
        <caption class="sr-only">Completed courses</caption>
        <thead><tr>
          <th scope="col"><?= e(t('transcript.badge', 'Badge')) ?></th>
          <th scope="col"><?= e(t('transcript.course', 'Course')) ?></th>
          <th scope="col"><?= e(t('transcript.date', 'Completed')) ?></th>
          <th scope="col" style="text-align:right"><?= e(t('transcript.cpe', 'CPE hours')) ?></th>
          <?php if ($showGt): ?><th scope="col" style="text-align:right"><?= e(t('transcript.gt', 'GT hours')) ?></th><?php endif; ?>
          <th scope="col" class="no-print"><?= e(t('transcript.credential', 'Credential')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><img class="transcript__badge" src="<?= e(url('/badge/' . $r['code'])) ?>" alt="Badge for <?= e($r['title']) ?>" width="52" height="52"></td>
            <td><strong><?= e($r['title']) ?></strong><br><span class="muted code">#<?= e($r['code']) ?></span></td>
            <td><?= e(date('M j, Y', strtotime($r['issued_at']) ?: time())) ?></td>
            <td style="text-align:right"><?= (float) $r['cpe_hours'] > 0 ? e($fmt($r['cpe_hours'])) : '<span class="muted">—</span>' ?></td>
            <?php if ($showGt): ?><td style="text-align:right"><?= (float) $r['gt_hours'] > 0 ? e($fmt($r['gt_hours'])) : '<span class="muted">—</span>' ?></td><?php endif; ?>
            <td class="no-print">
              <a class="btn btn-outline btn-sm" href="<?= e(url('/badge/' . $r['code'] . '?download=1')) ?>"><?= e(t('dash.badge_png', 'Badge (PNG)')) ?></a>
              <a class="btn btn-gold btn-sm" href="<?= e(url('/certificate/' . $r['code'] . '.pdf')) ?>"><?= e(t('dash.cert_pdf', 'Certificate (PDF)')) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <td colspan="3" style="text-align:right;font-weight:700"><?= $showGt ? e(t('transcript.totals', 'Totals')) : e(t('transcript.total', 'Total CPE hours')) ?></td>
          <td style="text-align:right;font-weight:700"><?= e($fmt($total_cpe)) ?></td>
          <?php if ($showGt): ?><td style="text-align:right;font-weight:700"><?= e($fmt($total_gt)) ?></td><?php endif; ?>
          <td class="no-print"></td>
        </tr></tfoot>
      </table>
    </div>

    <p class="muted" style="margin-top:16px;font-size:.86rem">
      <?php if ($provider !== ''): ?><?= e($provider) ?>. <?php endif; ?>
      <?php if ($org !== ''): ?><?= e(t('transcript.issued_by', 'Issued by')) ?> <?= e($org) ?>. <?php endif; ?>
      <?= e(t('transcript.verify', 'Each credential is verifiable by its code on the certificate.')) ?>
    </p>
  <?php endif; ?>
</div>

<style>
.transcript__head{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;align-items:flex-start;
  border-bottom:2px solid var(--surface-alt);padding-bottom:16px}
.transcript__totals{display:flex;gap:26px}
.transcript__totals .stat{text-align:center}
.transcript__totals .stat b{display:block;font-size:1.7rem;color:var(--navy-700);line-height:1}
.transcript__totals .stat span{font-size:.8rem;color:var(--ink-soft)}
.transcript__badge{border-radius:8px;object-fit:contain;background:#fff}
.transcript .code{font-size:.78rem}
@media print{
  .no-print{display:none !important}
  .topbar,.topnav,.sidebar,.flash,footer{display:none !important}
  .card{box-shadow:none;border:none}
  body{background:#fff}
}
</style>
