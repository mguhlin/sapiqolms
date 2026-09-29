<?php /** @var array $ips @var int $port @var array $urls @var bool $on_lan */ ?>
<div class="page-head">
  <h1>Share on your network</h1>
  <p>Running a local demo? Anyone on the <strong>same Wi-Fi</strong> can open this site in their browser using the address below — no internet or install needed.</p>
</div>

<?php if (!$urls): ?>
  <div class="card">
    <p class="muted">Couldn't detect a local network address. Make sure this machine is connected to Wi-Fi or a wired LAN, then reload.</p>
  </div>
<?php else: ?>
  <div class="card">
    <h2>Open this on other devices</h2>
    <?php foreach ($urls as $i => $u): ?>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0">
        <code id="shareUrl<?= $i ?>" style="font-size:1.35rem;font-weight:700;color:var(--navy-800);background:var(--surface-alt,#eef2f8);padding:10px 16px;border-radius:10px"><?= e($u) ?></code>
        <button class="btn btn-outline btn-sm" type="button" data-copy="shareUrl<?= $i ?>">Copy</button>
      </div>
    <?php endforeach; ?>
    <p class="muted" style="margin-top:6px">
      Detected network address<?= count($ips) > 1 ? 'es' : '' ?>: <?= e(implode(', ', $ips)) ?> · port <?= (int) $port ?>.
    </p>
  </div>

  <div class="card">
    <h2>Steps for staff</h2>
    <ol style="line-height:1.9;margin:0 0 4px 18px">
      <li>Connect their laptop, tablet, or phone to the <strong>same Wi-Fi</strong> as this machine.</li>
      <li>Open a browser and go to the address above (e.g. <code><?= e($urls[0]) ?></code>).</li>
      <li>Sign in, or create an account, and start exploring the courses.</li>
    </ol>
  </div>

  <div class="card">
    <h2>If they can't connect</h2>
    <ul style="line-height:1.9;margin:0 0 4px 18px">
      <li><strong>Bind the server to all interfaces.</strong> Start it so it listens on the network, not just this machine:
        <br><code>php -S 0.0.0.0:<?= (int) $port ?> -t public public/router.php</code>
        <?php if (!$on_lan): ?><br><span class="muted">(You're viewing this over localhost, so the server may currently be bound to 127.0.0.1 only — restart it with <code>0.0.0.0</code> as above.)</span><?php endif; ?>
      </li>
      <li><strong>Allow the port through the firewall.</strong> On the host machine, permit incoming connections on port <?= (int) $port ?> (Windows Defender / macOS firewall may prompt the first time).</li>
      <li><strong>Same network.</strong> Guest Wi-Fi or "client isolation" can block device-to-device traffic; use the same regular network.</li>
    </ul>
    <p class="muted">This is intended for local demos on a trusted network — not for production hosting.</p>
  </div>

  <script nonce="<?= e(csp_nonce()) ?>">
    document.querySelectorAll('[data-copy]').forEach(function (b) {
      b.addEventListener('click', function () {
        var el = document.getElementById(b.getAttribute('data-copy'));
        navigator.clipboard.writeText(el.textContent).then(function () {
          var t = b.textContent; b.textContent = 'Copied!'; setTimeout(function () { b.textContent = t; }, 1500);
        });
      });
    });
  </script>
<?php endif; ?>
