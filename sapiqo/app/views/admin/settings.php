<?php /** @var array $cfg @var array $s */
$val = fn(string $k, string $def = '') => e($s[$k] ?? $def);
?>
<div class="page-head">
  <h1>Branding &amp; settings</h1>
  <p>White-label the platform for your organization. These apply across the app, catalog, badges, and certificates.</p>
</div>

<div class="card">
  <h2>Theme presets</h2>
  <p class="muted">One-click skins — colors, font, and corner style evoking familiar platforms. Applies instantly; fine-tune the colors below.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
    <?php foreach (theme_presets() as $key => $pre):
      $active = ($s['theme_primary'] ?? '') === $pre['primary'] && ($s['theme_accent'] ?? '') === $pre['accent']; ?>
      <form method="post" action="<?= e(url('/admin/settings/preset')) ?>" style="margin:0">
        <?= csrf_field() ?><input type="hidden" name="preset" value="<?= e($key) ?>">
        <button type="submit" style="width:100%;text-align:left;cursor:pointer;padding:0;border:2px solid <?= $active ? 'var(--navy-700)' : 'var(--line)' ?>;border-radius:<?= (int)$pre['radius'] ?>px;overflow:hidden;background:#fff">
          <span style="display:flex;height:40px">
            <span style="flex:2;background:<?= e($pre['primary']) ?>"></span>
            <span style="flex:1;background:<?= e($pre['accent']) ?>"></span>
          </span>
          <span style="display:block;padding:8px 10px">
            <strong style="font-size:.9rem"><?= e($pre['label']) ?></strong><?= $active ? ' <span class="pill pill--done">current</span>' : '' ?>
            <br><span class="muted" style="font-size:.78rem"><?= e($pre['note']) ?></span>
          </span>
        </button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="card">
    <h2>Identity</h2>
    <div class="row">
      <div class="field"><label for="app_name">Platform name</label>
        <input id="app_name" name="app_name" value="<?= $val('app_name', $cfg['app_name']) ?>" placeholder="Sapiqo">
        <small>Shown in the top bar, page titles, and emails.</small></div>
      <div class="field"><label for="app_tagline">Tagline</label>
        <input id="app_tagline" name="app_tagline" value="<?= $val('app_tagline', $cfg['app_tagline'] ?? '') ?>" placeholder="Learning made clear."></div>
    </div>
    <div class="row">
      <div class="field"><label for="org_name">Organization name</label>
        <input id="org_name" name="org_name" value="<?= $val('org_name', $cfg['org_name']) ?>">
        <small>Appears on certificates and the footer.</small></div>
      <div class="field"><label for="catalog_name">Course-site name</label>
        <input id="catalog_name" name="catalog_name" value="<?= $val('catalog_name') ?>" placeholder="<?= e($cfg['app_name'] . ' Courses') ?>">
        <small>Brand shown inside the course reader. Blank = “<?= e($cfg['app_name']) ?> Courses”.</small></div>
    </div>
    <div class="field" style="max-width:180px"><label for="brand_mark">Brand mark (letter/initials)</label>
      <input id="brand_mark" name="brand_mark" maxlength="2" value="<?= $val('brand_mark') ?>" placeholder="<?= e(mb_substr($cfg['app_name'],0,1)) ?>">
      <small>Used when no logo is uploaded.</small></div>
  </div>

  <div class="card">
    <h2>Logo</h2>
    <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
      <?php if (($s['logo'] ?? '') !== ''): ?>
        <img src="<?= e(url('/brand/logo')) ?>" alt="Current logo" style="height:56px;background:var(--navy-900);padding:8px;border-radius:10px">
      <?php else: ?>
        <span class="brand__mark" style="width:56px;height:56px;font-size:1.6rem"><?= e($cfg['brand_mark']) ?></span>
      <?php endif; ?>
      <div class="field" style="margin:0">
        <label for="logo">Upload logo (PNG/SVG, transparent recommended)</label>
        <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
      </div>
      <?php if (($s['logo'] ?? '') !== ''): ?>
        <label style="display:inline-flex;align-items:center;gap:6px"><input type="checkbox" name="remove_logo" value="1"> <span class="muted">Remove logo</span></label>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Theme colors</h2>
    <p class="muted">A preset above fills these; adjust for a custom look. Blank = default navy/gold. Two colors drive the whole palette.</p>
    <div class="row">
      <div class="field" style="max-width:220px"><label for="theme_primary">Primary (headers, buttons)</label>
        <input id="theme_primary" name="theme_primary" value="<?= $val('theme_primary') ?>" placeholder="#0b2e5b"
               pattern="#[0-9a-fA-F]{6}" style="font-family:monospace"></div>
      <div class="field" style="max-width:220px"><label for="theme_accent">Accent (highlights, badges)</label>
        <input id="theme_accent" name="theme_accent" value="<?= $val('theme_accent') ?>" placeholder="#f4b41a"
               pattern="#[0-9a-fA-F]{6}" style="font-family:monospace"></div>
    </div>
    <small class="muted">Font &amp; corner style come from the preset (currently:
      <?= ($s['theme_font'] ?? '') !== '' ? 'custom font' : 'default font' ?>,
      radius <?= e($s['theme_radius'] ?? 'default') ?>). Pick a preset to change them.</small>
  </div>

  <div class="card">
    <h2>Language</h2>
    <div class="field" style="max-width:260px"><label for="default_locale">Default UI language</label>
      <select id="default_locale" name="default_locale">
        <?php $curLoc = $s['default_locale'] ?? ($cfg['default_locale'] ?? 'en');
        foreach (available_locales() as $code): ?>
          <option value="<?= e($code) ?>" <?= $curLoc === $code ? 'selected' : '' ?>><?= e(I18N_LANGS[$code]['label'] ?? $code) ?></option>
        <?php endforeach; ?>
      </select>
      <small>Learners can still switch languages themselves. Add more languages by dropping <code>app/lang/&lt;code&gt;.php</code> files.</small></div>
  </div>

  <div class="card">
    <h2>Accounts</h2>
    <div class="field">
      <label style="display:inline-flex;align-items:center;gap:8px">
        <input type="checkbox" name="allow_self_registration" value="1" <?= !empty($cfg['allow_self_registration']) ? 'checked' : '' ?>>
        <span>Let people create their own account (Sign in → Create an account)</span>
      </label>
      <small>Turn this off if every account should be created by an admin, imported in bulk, or created via an
        enrollment code instead. This does <strong>not</strong> block people who have a valid
        <a href="<?= e(url('/admin/codes')) ?>">enrollment code</a> — redeeming one always lets someone create an
        account (by email/password or single sign-on), even while this is off.</small>
    </div>
    <div class="field">
      <label for="session_idle_timeout_minutes">Sign out after inactivity (minutes)</label>
      <input id="session_idle_timeout_minutes" name="session_idle_timeout_minutes" type="number" min="0" max="1440"
             style="max-width:140px" value="<?= (int) ($cfg['session_idle_timeout_minutes'] ?? 60) ?>">
      <small>Applies to every signed-in user (learners, course developers, and admins). Without this, a signed-in
        browser tab stays logged in indefinitely as long as it's never closed — there's otherwise no fixed session
        length. Set to <strong>0</strong> to turn the timeout off.</small>
    </div>
  </div>

  <div class="card">
    <h2>Course behavior</h2>
    <div class="field">
      <label style="display:inline-flex;align-items:center;gap:8px">
        <input type="checkbox" name="sequential_default" value="1" <?= (($s['sequential_default'] ?? '1') === '1') ? 'checked' : '' ?>>
        <span>Make courses sequential by default</span>
      </label>
      <small>Learners must complete each lesson (and its knowledge check) before the next unlocks, so the badge and
        certificate are only reachable after finishing every lesson in order. Individual courses can override this on
        <a href="<?= e(url('/admin/courses')) ?>">Manage courses</a> (set their Sequential column to On or Off).</small>
    </div>
    <div class="field">
      <label for="expiry_reminder_days">Access-expiry reminders (days before)</label>
      <input id="expiry_reminder_days" name="expiry_reminder_days"
             value="<?= e($s['expiry_reminder_days'] ?? '30,7,1') ?>" placeholder="30,7,1">
      <small>When a course has an access window, learners get an email + in-app reminder at each of these milestones
        before it ends — e.g. <code>30,7,1</code> = a month out, the week of, and the day before. Emails require mail to be
        configured; the sweep runs from <code>bin/expire.php</code> (cron, daily). Earned badges, certificates, and CPE
        hours always remain on the learner's transcript after access ends.</small>
    </div>
  </div>

  <div class="card">
    <h2>Certificates</h2>
    <p class="muted">Printed in the signatory block on page 1 of the certificate PDF.</p>
    <div class="row">
      <div class="field"><label for="cert_signatory">Signatory name</label>
        <input id="cert_signatory" name="cert_signatory" value="<?= $val('cert_signatory') ?>" placeholder="Ben Starr"></div>
      <div class="field"><label for="cert_signatory_title">Signatory title</label>
        <input id="cert_signatory_title" name="cert_signatory_title" value="<?= $val('cert_signatory_title') ?>" placeholder="Executive Director"></div>
    </div>
    <div class="row">
      <div class="field"><label for="cert_provider">CPE provider line</label>
        <input id="cert_provider" name="cert_provider" value="<?= $val('cert_provider') ?>" placeholder="TEA Provider #500114">
        <small>Ensures CPE credit hours are recognized.</small></div>
      <div class="field"><label for="cert_website">Footer website</label>
        <input id="cert_website" name="cert_website" value="<?= $val('cert_website') ?>" placeholder="www.example.org"></div>
    </div>
    <p class="muted">Set each course's <strong>CPE credit hours</strong> on
      <a href="<?= e(url('/admin/courses')) ?>">Manage courses</a>.</p>
  </div>

  <div class="toolbar">
    <button class="btn btn-gold" type="submit">Save branding</button>
    <a class="btn btn-outline" href="<?= e(url('/admin')) ?>">Back to admin</a>
  </div>
</form>
