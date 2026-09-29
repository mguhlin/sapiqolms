<?php
// Restore an LMS backup created by bin/backup.php.
//
// Usage:
//   php bin/restore.php /path/to/sapiqo-backup-YYYYmmdd-HHMMSS.zip --force
//
// Extracts the data tree back into the data root (overwriting files) and, for
// MySQL installs, imports db/dump.sql. Requires --force to proceed.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

$archive = $argv[1] ?? '';
$force = in_array('--force', $argv, true);
if ($archive === '' || !is_file($archive)) {
    fwrite(STDERR, "Usage: php bin/restore.php <backup.zip> --force\n"); exit(1);
}
if (!$force) {
    fwrite(STDERR, "This will OVERWRITE current data. Re-run with --force to proceed.\n"); exit(1);
}

if (!archive_entries_safe($archive)) { fwrite(STDERR, "Archive contains unsafe or unsupported entries.\n"); exit(1); }

$cfg = lms_config();
$dataRoot = dirname($cfg['data_dir']);

$tmp = sys_get_temp_dir() . '/lmsrestore-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0775, true);

if (preg_match('/\.zip$/i', $archive) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($archive) !== true) { fwrite(STDERR, "Cannot open archive.\n"); exit(1); }
    $zip->extractTo($tmp);
    $zip->close();
} elseif (preg_match('/\.tar\.gz$|\.tgz$/i', $archive)) {
    try { $p = new PharData($archive); $p->extractTo($tmp, null, true); }
    catch (Throwable $e) { fwrite(STDERR, 'Cannot open archive: ' . $e->getMessage() . "\n"); exit(1); }
} else {
    fwrite(STDERR, "Unsupported archive type (expected .zip or .tar.gz).\n"); exit(1);
}

// Restore files.
$srcRoot = $tmp . '/data-root';
if (is_dir($srcRoot)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = ltrim(substr($item->getPathname(), strlen($srcRoot)), '/\\');
        $dest = $dataRoot . '/' . $rel;
        if ($item->isDir()) { if (!is_dir($dest)) @mkdir($dest, 0775, true); }
        else { @mkdir(dirname($dest), 0775, true); @copy($item->getPathname(), $dest); }
    }
    echo "Restored data files into $dataRoot\n";
}

// Restore MySQL dump if present.
$dump = $tmp . '/db/dump.sql';
if (is_file($dump) && $cfg['db_driver'] === 'mysql') {
    $m = $cfg['mysql'];
    $cmd = sprintf(
        'mysql --host=%s --port=%d --user=%s %s %s < %s',
        escapeshellarg($m['host']), (int) $m['port'], escapeshellarg($m['user']),
        $m['pass'] !== '' ? '--password=' . escapeshellarg($m['pass']) : '',
        escapeshellarg($m['dbname']), escapeshellarg($dump)
    );
    system($cmd, $rc);
    echo $rc === 0 ? "Imported MySQL dump.\n" : "Warning: MySQL import returned code $rc.\n";
}

// Clean temp.
$ri = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($ri as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmp);

echo "Restore complete.\n";
