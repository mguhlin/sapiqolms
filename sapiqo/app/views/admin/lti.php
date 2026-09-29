<?php /** @var array $platforms @var array $tool @var array $keys */ ?>
<div class="page-head"><h1>LTI 1.3</h1><p>Let this platform be launched as an external tool from Canvas, Moodle, Blackboard, Schoology, and other LTI 1.3 platforms.</p></div>

<div class="card">
  <h2>Your tool details</h2>
  <p class="muted">Give these to the platform administrator when adding the tool.</p>
  <div class="table-wrap"><table class="table">
    <tbody>
      <tr><th scope="row">OIDC login / initiation URL</th><td><code><?= e($tool['login_url']) ?></code></td></tr>
      <tr><th scope="row">Launch / redirect URL</th><td><code><?= e($tool['launch_url']) ?></code></td></tr>
      <tr><th scope="row">Public JWKS URL</th><td><code><?= e($tool['jwks_url']) ?></code></td></tr>
      <tr><th scope="row">Key ID (kid)</th><td><code><?= e($keys['kid']) ?></code></td></tr>
    </tbody>
  </table></div>
  <p class="muted">Point the course to Sapiqo by adding a custom parameter <code>course=&lt;slug&gt;</code> on the link
    in your platform (otherwise the launch lands on the learner dashboard). Grade passback (AGS) is sent on completion.</p>
</div>

<div class="card">
  <h2>Register a platform</h2>
  <form method="post" action="<?= e(url('/admin/lti')) ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field"><label for="name">Name</label><input id="name" name="name" placeholder="Our district Canvas" required></div>
      <div class="field"><label for="issuer">Issuer (iss)</label><input id="issuer" name="issuer" placeholder="https://canvas.instructure.com" required></div>
    </div>
    <div class="row">
      <div class="field"><label for="client_id">Client ID</label><input id="client_id" name="client_id" required></div>
      <div class="field"><label for="deployment_id">Deployment ID</label><input id="deployment_id" name="deployment_id"></div>
    </div>
    <div class="row">
      <div class="field"><label for="auth_login_url">Auth / OIDC authorize URL</label><input id="auth_login_url" name="auth_login_url" placeholder="https://…/api/lti/authorize_redirect" required></div>
      <div class="field"><label for="auth_token_url">Access-token URL</label><input id="auth_token_url" name="auth_token_url" placeholder="https://…/login/oauth2/token"></div>
    </div>
    <div class="field"><label for="jwks_url">Platform JWKS URL</label><input id="jwks_url" name="jwks_url" placeholder="https://…/api/lti/security/jwks"></div>
    <div class="field"><label for="public_key">…or paste the platform public key (PEM)</label>
      <textarea id="public_key" name="public_key" rows="4" placeholder="-----BEGIN PUBLIC KEY-----" style="width:100%;font-family:monospace"></textarea>
      <small>Provide a JWKS URL (preferred) or an inline PEM for verification.</small></div>
    <button class="btn btn-gold" type="submit">Register platform</button>
  </form>
</div>

<div class="card">
  <h2>Registered platforms</h2>
  <div class="table-wrap"><table class="table">
    <caption class="sr-only">Registered LTI platforms</caption>
    <thead><tr><th scope="col">Name</th><th scope="col">Issuer</th><th scope="col">Client ID</th><th scope="col">Verify via</th><th scope="col"></th></tr></thead>
    <tbody>
      <?php if (!$platforms): ?>
        <tr><td colspan="5" class="muted">No platforms registered yet.</td></tr>
      <?php else: foreach ($platforms as $pl): ?>
        <tr>
          <td><?= e($pl['name']) ?></td>
          <td class="muted"><?= e($pl['issuer']) ?></td>
          <td class="muted"><code><?= e($pl['client_id']) ?></code></td>
          <td class="muted"><?= $pl['jwks_url'] !== '' ? 'JWKS URL' : ($pl['public_key'] !== '' ? 'inline PEM' : '—') ?></td>
          <td style="text-align:right">
            <form method="post" action="<?= e(url('/admin/lti/' . (int)$pl['id'] . '/delete')) ?>" style="display:inline" data-confirm="Remove this platform?">
              <?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table></div>
</div>
