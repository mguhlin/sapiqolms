<?php /** @var array $course */
$slug = $course['slug'];
$dl = url('/admin/courses/' . urlencode($slug) . '/export-cc');
// Per-platform import guidance. `note` flags accuracy caveats we don't want to hide.
$targets = [
    [
        'name' => 'Canvas',
        'steps' => [
            'Open the destination course, then <strong>Settings → Import Course Content</strong>.',
            'Content Type: <strong>Common Cartridge 1.x Package</strong>.',
            'Choose the downloaded <code>.imscc</code> file and select <strong>Import</strong>.',
            'Modules, pages, and quizzes appear once the migration finishes.',
        ],
        'note' => 'Canvas has strong native Common Cartridge support — this is the smoothest target.',
    ],
    [
        'name' => 'Blackboard Learn',
        'steps' => [
            'In the destination course go to <strong>Packages and Utilities → Import Package / View Logs</strong>.',
            'Choose <strong>Import Package</strong> and upload the <code>.imscc</code>.',
            'Select the materials to include (content areas, tests) and submit.',
            'Ultra courses: use <strong>Import Course Content → Common Cartridge</strong> from the course menu.',
        ],
        'note' => 'Blackboard imports Common Cartridge; quiz question types map to Tests.',
    ],
    [
        'name' => 'Sakai',
        'steps' => [
            'In the destination site open <strong>Site Info → Import from Archive File</strong> (or the <strong>Lessons</strong> tool import).',
            'Upload the <code>.imscc</code> as an <strong>IMS Common Cartridge</strong>.',
            'Sakai builds Lessons pages and Tests &amp; Quizzes items from the cartridge.',
        ],
        'note' => 'Sakai reads IMS Common Cartridge natively.',
    ],
    [
        'name' => 'Moodle',
        'steps' => [
            'Confirm your site can import Common Cartridge — recent Moodle supports it, otherwise install the free <em>Common Cartridge import</em> plugin (admin task).',
            'In the destination course: <strong>Course reuse → Import</strong> (or the CC import tool) and upload the <code>.imscc</code>.',
            'Content pages and quizzes come in; review imported quizzes, since QTI mapping varies by version.',
        ],
        'note' => 'Moodle\'s native CC support is more limited than the others — a plugin may be required, and quiz formatting can need a quick review.',
    ],
];
?>
<div class="page-head">
  <h1>Export “<?= e($course['title']) ?>” to another LMS</h1>
  <p><a href="<?= e(url('/admin/courses')) ?>">&larr; Back to courses</a></p>
</div>

<div class="card">
  <h2>1. Download the package</h2>
  <p>Sapiqo exports your course as an <strong>IMS Common Cartridge</strong> (<code>.imscc</code>) — the standard interchange
     format that <strong>Canvas, Blackboard, Sakai, and Moodle</strong> all import. Lessons become web pages, knowledge
     checks become QTI quizzes, and images/videos/captions are bundled inside the package.</p>
  <p><a class="btn btn-gold" href="<?= e($dl) ?>">⬇ Download <?= e($slug) ?>.imscc</a></p>
  <p class="muted" style="font-size:.86rem">One package works for every platform below — download it once, then follow the steps for your target LMS.
     Very large media courses may take a moment to build.</p>
</div>

<div class="card">
  <h2>2. Import it into your LMS</h2>
  <div class="lms-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:8px">
    <?php foreach ($targets as $tgt): ?>
      <section class="card" style="margin:0;background:var(--surface-alt)">
        <h3 style="margin-top:0"><?= e($tgt['name']) ?></h3>
        <ol style="margin:0 0 8px;padding-left:1.15em;line-height:1.5">
          <?php foreach ($tgt['steps'] as $s): ?><li><?= $s /* trusted static markup */ ?></li><?php endforeach; ?>
        </ol>
        <p class="muted" style="font-size:.82rem;margin:0"><?= e($tgt['note']) ?></p>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h2>What transfers — and what to check</h2>
  <ul style="line-height:1.6">
    <li><strong>Transfers:</strong> module/lesson structure, lesson content (as HTML pages), knowledge-check questions
        (multiple choice &amp; multiple response as QTI), and bundled media (images, video, captions).</li>
    <li><strong>Review after import:</strong> quiz scoring/feedback and any short-answer questions — QTI support differs
        by platform. Embedded third-party videos (YouTube/Vimeo) import as links.</li>
    <li><strong>Not included:</strong> learner records (enrollments, progress, badges, CPE) stay in Sapiqo — this exports
        the <em>course content</em>, not the roster or completion history.</li>
    <li>Moving between <strong>Sapiqo servers</strong> instead? Use the <code>.tar</code> package on
        <a href="<?= e(url('/admin/courses')) ?>">Manage courses</a> — it preserves everything exactly.</li>
  </ul>
</div>
