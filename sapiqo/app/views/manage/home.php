<?php /** @var array $groups @var array $orgs @var bool $is_admin */ ?>
<div class="page-head"><h1>Manage</h1>
  <p>Add, edit, enroll, and remove members for the organizations and groups you manage.</p></div>

<?php if (!empty($orgs)): ?>
<div class="card">
  <h2>Organizations</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Organization</th><th>Members</th><th>Completions</th><th>Completion rate</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orgs as $o): ?>
        <tr>
          <td><a href="<?= e(url('/manage/orgs/' . $o['id'])) ?>"><strong><?= e($o['name']) ?></strong></a>
            <?php if ($o['description']): ?><br><span class="muted"><?= e($o['description']) ?></span><?php endif; ?></td>
          <td><?= (int)$o['members'] ?></td>
          <td><?= (int)$o['completions'] ?></td>
          <td><strong><?= (int)$o['rate'] ?>%</strong></td>
          <td><a class="btn btn-outline btn-sm" href="<?= e(url('/manage/orgs/' . $o['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h2>Groups</h2>
  <?php if (!$groups): ?>
    <p class="muted">You are not assigned as a manager of any group yet. An administrator
      can assign you on a group's page.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Group</th><th>Members</th><th>Completions</th><th>Completion rate</th><th>Steps</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($groups as $g): ?>
          <tr>
            <td><a href="<?= e(url('/manage/groups/' . $g['id'])) ?>"><strong><?= e($g['name']) ?></strong></a>
              <?php if ($g['description']): ?><br><span class="muted"><?= e($g['description']) ?></span><?php endif; ?></td>
            <td><?= (int)$g['members'] ?></td>
            <td><?= (int)$g['completions'] ?></td>
            <td><strong><?= (int)$g['rate'] ?>%</strong></td>
            <td><?= (int)$g['steps'] ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('/manage/groups/' . $g['id'])) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php if ($is_admin): ?>
    <p class="muted" style="margin-top:12px">As an administrator you can also manage all groups from
      <a href="<?= e(url('/admin/groups')) ?>">Admin → Groups</a>.</p>
  <?php endif; ?>
</div>
