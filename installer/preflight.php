<?php
// Sapiqo pre-flight check. Verifies PHP version, extensions, and writable paths,
// then prints per-OS remediation. Exit code 0 = ready, 1 = a REQUIRED item is
// missing. Safe to run any time:  php installer/preflight.php
//
// This file is part of the (discardable) installer/ folder — nothing at runtime
// depends on it.

declare(strict_types=1);

$root    = dirname(__DIR__);                 // courses/
$code    = $root . '/sapiqo';
$dataDir = getenv('SAPIQO_DATA') ?: ($root . '/sapiqo-data');

$C = fn($s, $c) => (PHP_SAPI === 'cli' && stream_isatty(STDOUT)) ? "\033[{$c}m{$s}\033[0m" : $s;
$ok = fn($s) => $C($s, '32'); $bad = fn($s) => $C($s, '31'); $warn = fn($s) => $C($s, '33');
$line = str_repeat('─', 56);

echo "\nSapiqo pre-flight check\n$line\n";

// --- PHP version ---
$phpOK = version_compare(PHP_VERSION, '8.1.0', '>=');
printf("PHP %-8s %s\n", PHP_VERSION, $phpOK ? $ok('OK (>= 8.1)') : $bad('TOO OLD — need 8.1+'));

// --- Extensions ---
$required = [
    'pdo' => 'database access', 'mbstring' => 'text handling', 'openssl' => 'SSO/LTI crypto + sessions',
    'json' => 'data', 'phar' => 'course/backup archives', 'curl' => 'SSO / LTI / JWKS',
    'gd' => 'badges, certificates, PDFs',
];
$dbAny = ['pdo_sqlite' => 'SQLite (default, zero-config)', 'pdo_mysql' => 'MySQL/MariaDB (production)'];
$recommended = [
    'zip' => 'native ZipArchive (faster .zip; PharData is the fallback)',
    'dom' => 'robust Common Cartridge import (regex fallback exists)',
    'simplexml' => 'alt XML parser for imports',
    'fileinfo' => 'upload validation', 'zlib' => 'gzip for backups', 'sqlite3' => 'SQLite tooling',
    'intl' => 'locale niceties (optional)',
];

$missingReq = [];
echo "\nRequired extensions:\n";
foreach ($required as $ext => $why) {
    $has = extension_loaded($ext);
    if (!$has) $missingReq[] = $ext;
    printf("  %-10s %s  %s\n", $ext, $has ? $ok('yes') : $bad('MISSING'), $C($why, '2'));
}
$dbHas = array_filter(array_keys($dbAny), 'extension_loaded');
printf("  %-10s %s  %s\n", 'pdo_*', $dbHas ? $ok('yes (' . implode(', ', $dbHas) . ')') : $bad('MISSING — need pdo_sqlite or pdo_mysql'),
    $C('at least one database driver', '2'));
if (!$dbHas) $missingReq[] = 'pdo_sqlite';

$missingRec = [];
echo "\nRecommended extensions:\n";
foreach ($recommended as $ext => $why) {
    $has = extension_loaded($ext);
    if (!$has) $missingRec[] = $ext;
    printf("  %-10s %s  %s\n", $ext, $has ? $ok('yes') : $warn('not installed'), $C($why, '2'));
}
// dom OR simplexml is enough — drop both from "missing" if either present.
if (extension_loaded('dom') || extension_loaded('simplexml')) {
    $missingRec = array_values(array_diff($missingRec, ['dom', 'simplexml']));
}

// --- Writable data location ---
echo "\nFilesystem:\n";
$dataParent = is_dir($dataDir) ? $dataDir : dirname($dataDir);
$writable = is_writable($dataParent);
printf("  data dir   %s  %s\n", $writable ? $ok('writable') : $bad('NOT writable'), $C($dataDir, '2'));
printf("  code dir   %s  %s\n", is_dir($code) ? $ok('found') : $bad('MISSING'), $C($code, '2'));

// --- Upload limits (course / SCORM / Common Cartridge imports) ---
$toBytes = function ($v) { $v = trim((string) $v); $u = strtolower(substr($v, -1)); $n = (int) $v;
    return $u === 'g' ? $n << 30 : ($u === 'm' ? $n << 20 : ($u === 'k' ? $n << 10 : $n)); };
$ufs = (string) ini_get('upload_max_filesize'); $pms = (string) ini_get('post_max_size');
$small = $toBytes($ufs) < 64 * 1024 * 1024 || $toBytes($pms) < 64 * 1024 * 1024;
echo "\nUpload limits (for course/SCORM/Common Cartridge imports):\n";
printf("  upload_max_filesize %s   post_max_size %s   %s\n", $ufs, $pms,
    $small ? $warn('LOW — large imports (e.g. Canvas .imscc) may be rejected') : $ok('OK'));
if ($small) echo $C("  Raise both to e.g. 1024M (public/.user.ini already sets this for PHP-FPM/CGI;\n"
    . "  for the built-in server pass -d upload_max_filesize=1024M -d post_max_size=1056M).", '2') . "\n";

// --- Remediation ---
function pkg_hint(array $exts): array {
    $e = implode(' ', array_map(fn($x) => 'php-' . str_replace('_', '-', $x), $exts));
    $eDnf = implode(' ', array_map(fn($x) => 'php-' . str_replace('_', '', $x), $exts));
    return [
        'Debian/Ubuntu' => "sudo apt-get install -y $e",
        'RHEL/Fedora'   => "sudo dnf install -y $eDnf",
        'Alpine'        => 'apk add ' . implode(' ', array_map(fn($x) => "php-$x", $exts)),
        'macOS (brew)'  => 'brew install php   # bundles these extensions',
        'Windows'       => 'Enable in php.ini: ' . implode(', ', array_map(fn($x) => "extension=$x", $exts)),
    ];
}

if ($missingReq || $missingRec) {
    echo "\n$line\nTo install the missing extensions:\n";
    foreach (pkg_hint(array_merge($missingReq, $missingRec)) as $os => $cmd) {
        printf("  %-15s %s\n", $os, $cmd);
    }
    echo "  (then restart your web server)\n";
    echo "\nThe simplest turnkey option is Docker — it bundles everything:\n";
    echo "  docker compose -f installer/docker-compose.yml up -d --build\n";
}

echo "\n$line\n";
if (!$phpOK || $missingReq) {
    echo $bad("NOT READY") . " — install the REQUIRED items above, then re-run.\n\n";
    exit(1);
}
echo $ok("READY TO INSTALL") . ($missingRec ? '  ' . $warn('(recommended extensions missing; features degrade gracefully)') : '') . "\n\n";
exit(0);
