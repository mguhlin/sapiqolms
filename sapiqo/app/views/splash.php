<?php /** @var array $courses */ $cfg = lms_config(); ?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>" dir="<?= e(locale_dir()) ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($cfg['app_name']) ?> — Professional Learning</title>
  <meta name="description" content="Self-paced professional learning courses." />
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/assets/img/favicon-32.png')) ?>" />
  <link rel="icon" type="image/png" sizes="512x512" href="<?= e(url('/assets/img/favicon.png')) ?>" />
  <link rel="apple-touch-icon" href="<?= e(url('/assets/img/apple-touch-icon.png')) ?>" />
  <link rel="stylesheet" href="<?= e(url('/assets/css/lms.css')) ?>" />
  <?= brand_theme_style() ?>
  <style>
    .hero{background:var(--surface);padding:26px 0 10px}
    .hero-img{display:block;width:100%;height:auto;border-radius:16px;box-shadow:var(--shadow-md)}
    .hero-imglink{display:block;border-radius:16px}
    .hero-imglink:focus-visible{outline:3px solid var(--gold-400);outline-offset:3px}
    .hero-split{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(240px,1fr);gap:30px;align-items:center}
    .hero-features{display:flex;flex-direction:column;gap:20px}
    .hero-features .feature{display:flex;gap:14px;align-items:flex-start;text-align:left}
    .hero-features .feature img{width:60px;height:60px;flex:none;border-radius:10px;box-shadow:var(--shadow-sm)}
    .hero-features .feature h3{margin:0 0 3px;font-size:1.12rem}
    .hero-features .feature p{margin:0;font-size:.9rem;color:var(--ink-soft)}
    @media (max-width:820px){.hero-split{grid-template-columns:1fr;gap:22px}.hero-features{flex-direction:row;flex-wrap:wrap}.hero-features .feature{flex:1 1 200px}}
    .section{padding:52px 0}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px}
    .course-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);
      box-shadow:var(--shadow-sm);overflow:hidden;display:flex;flex-direction:column;transition:.14s}
    .course-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
    .course-card__banner{height:96px;background:linear-gradient(135deg,var(--navy-800),var(--navy-600));
      display:grid;place-items:center;position:relative;overflow:hidden}
    .course-card__banner img{height:78px;width:78px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25))}
    .course-card__banner svg{width:44px;height:44px;color:var(--gold-400)}
    .course-card__body{padding:20px 22px 10px;flex:1}
    .course-card__body h3{margin:8px 0 8px;font-size:1.18rem}
    .course-card__body p{color:var(--ink-soft);font-size:.94rem;margin:0 0 12px}
    .course-card__meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-size:.82rem;color:var(--navy-700);font-weight:600}
    .course-card__meta .cpe{background:#fdf3d3;color:#8a6d1a;padding:2px 9px;border-radius:999px}
    .course-card__meta .cpe--gt{background:#dceafd;color:#1c4e80}
    .course-card--cert .course-card__banner{background:linear-gradient(135deg,var(--gold-500),var(--gold-300))}
    .course-card--cert .course-card__banner svg{color:var(--navy-800)}
    .course-card--cert .course-card__banner img{filter:none}
    .course-card__tag{position:absolute;top:10px;left:12px;background:var(--navy-900);color:var(--gold-300);
      font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:3px 9px;border-radius:999px}
    .course-card__foot{padding:14px 22px 20px}
    .site-footer{background:var(--navy-900);color:#a9c0e0;padding:26px 0;font-size:.88rem;margin-top:20px}
    .site-footer a{color:var(--gold-300)}
  </style>
</head>
<body>
  <header class="topbar"><div class="wrap topbar__inner">
    <a class="brand" href="<?= e(url('/')) ?>"><?php if ($cfg['logo'] !== ''): ?><img src="<?= e(url('/brand/logo')) ?>" alt="<?= e($cfg['app_name']) ?>" style="height:34px;width:auto"> <span><?= e($cfg['app_name']) ?></span><?php else: ?><span class="brand__mark"><?= e($cfg['brand_mark']) ?></span><span><?= e($cfg['app_name']) ?></span><?php endif; ?></a>
    <nav class="topnav">
      <a href="#courses"><?= e(t('nav.catalog', 'Courses')) ?></a>
      <a href="<?= e(url('/login')) ?>"><?= e(t('splash.signin', 'Sign in')) ?></a>
      <?php if ($cfg['allow_self_registration']): ?>
        <a class="btn btn-gold btn-sm" href="<?= e(url('/register')) ?>"><?= e(t('splash.create', 'Create account')) ?></a>
      <?php endif; ?>
      <?= language_switcher('splash-lang') ?>
    </nav>
  </div></header>

  <section class="hero"><div class="wrap">
    <h1 class="sr-only"><?= e($cfg['app_name']) ?> — <?= e($cfg['app_tagline'] ?? 'Learning made clear.') ?></h1>
    <div class="hero-split">
      <a class="hero-imglink" href="<?= e(url('/login')) ?>">
        <img class="hero-img" src="<?= e(url('/assets/img/hero.webp')) ?>" width="1672" height="941"
             alt="<?= e($cfg['app_name']) ?> — <?= e($cfg['app_tagline'] ?? 'Learning made clear.') ?>. <?= e(t('splash.hero_sub2', 'Sign in to enroll, track your progress, and earn a digital badge and certificate.')) ?>">
      </a>
      <div class="hero-features">
        <div class="feature">
          <img src="<?= e(url('/assets/img/feature-learn.webp')) ?>" alt="" loading="lazy">
          <div><h3><?= e(t('splash.f_learn', 'Learn')) ?></h3>
            <p class="muted"><?= e(t('splash.f_learn_sub', 'Work through self-paced lessons and videos, on any device.')) ?></p></div>
        </div>
        <div class="feature">
          <img src="<?= e(url('/assets/img/feature-practice.webp')) ?>" alt="" loading="lazy">
          <div><h3><?= e(t('splash.f_practice', 'Practice')) ?></h3>
            <p class="muted"><?= e(t('splash.f_practice_sub', 'Check your understanding with quizzes as you go.')) ?></p></div>
        </div>
        <div class="feature">
          <img src="<?= e(url('/assets/img/feature-earn.webp')) ?>" alt="" loading="lazy">
          <div><h3><?= e(t('splash.f_earn', 'Earn')) ?></h3>
            <p class="muted"><?= e(t('splash.f_earn_sub', 'Finish to earn a digital badge and CPE certificate.')) ?></p></div>
        </div>
      </div>
    </div>
  </div></section>

  <main class="wrap section" id="courses">
    <div class="page-head"><h2 style="font-size:1.7rem;margin:0 0 6px"><?= e(t('splash.available', 'Available courses')) ?></h2>
      <p class="muted"><?= e(t('splash.available_sub', 'Sign in to enroll. Your progress and badges are saved to your account.')) ?></p></div>
    <?php if (!$courses): ?>
      <div class="card"><p class="muted">No courses are available right now. Please check back soon.</p></div>
    <?php else: ?>
      <div class="grid">
        <?php $fmtHrs = fn($h) => $h > 0 ? ($h == (int)$h ? (int)$h : rtrim(rtrim(number_format($h,1),'0'),'.')) : '';
        foreach ($courses as $c): $isCert = !empty($c['is_certification']);
              $cpeTxt = $fmtHrs((float) ($c['cpe_hours'] ?? 0)); $gtTxt = $fmtHrs((float) ($c['gt_hours'] ?? 0)); ?>
          <div class="course-card<?= $isCert ? ' course-card--cert' : '' ?>">
            <div class="course-card__banner">
              <?php if ($isCert): ?><span class="course-card__tag">Certification</span><?php endif; ?>
              <?php $badgeUrl = is_file(rtrim($cfg['courses_dir'],'/')."/{$c['slug']}/badge.png") ? url('/courses/'.$c['slug'].'/badge.png') : ''; ?>
              <?php if ($badgeUrl): ?><img src="<?= e($badgeUrl) ?>" alt="">
              <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1 2.5 3 6 3s6-2 6-3v-5"/></svg><?php endif; ?>
            </div>
            <div class="course-card__body">
              <h3><?= e($c['title']) ?></h3>
              <p><?= e($c['tagline'] ?: 'A self-paced professional learning course.') ?></p>
              <div class="course-card__meta">
                <?php if ($cpeTxt !== ''): ?><span class="cpe"><?= $cpeTxt ?> CPE Hours</span><?php endif; ?>
                <?php if ($gtTxt !== ''): ?><span class="cpe cpe--gt"><?= $gtTxt ?> GT Hours</span><?php endif; ?>
                <?php if (!empty($c['stats']['lessons'])): ?><span><?= (int)$c['stats']['lessons'] ?> <?= e(t('splash.lessons', 'lessons')) ?></span><?php endif; ?>
                <?php if (!empty($c['stats']['videos'])): ?><span><?= (int)$c['stats']['videos'] ?> <?= e(t('splash.videos', 'videos')) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="course-card__foot" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <a class="btn btn-navy btn-sm" href="<?= e(url('/courses/' . $c['slug'] . '/')) ?>"><?= e(t('splash.overview', 'View overview →')) ?></a>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('/login')) ?>"><?= e(t('splash.signin', 'Sign in')) ?></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <footer class="site-footer"><div class="wrap">
    <?php if (($cfg['org_name'] ?? '') !== ''): ?>&copy; <?= date('Y') ?> <?= e($cfg['org_name']) ?>. <?php endif; ?>
    <a href="<?= e(url('/privacy')) ?>">Privacy &amp; data protection</a>
    <div style="margin-top:6px;font-size:.85rem;opacity:.85">Sapiqo LMS &copy; 2026 Miguel Guhlin · Licensed under <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC&nbsp;BY-SA&nbsp;4.0</a></div>
  </div></footer>
</body>
</html>
