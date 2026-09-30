<?php
// Restore complete format-2 backups or legacy data-only archives. Stop web/cron
// writers first. Restore into an empty installation when removing stale files matters.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/course_io.php';
require_once __DIR__ . '/../app/backup.php';
$archive = $argv[1] ?? '';
if (!is_file($archive) || !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Usage: php bin/restore.php <backup.zip|backup.tar.gz> --force\nStop application writers first. This overwrites matching data and course files.\n"); exit(1);
}
$tmp = sys_get_temp_dir() . '/sapiqo-restore-' . bin2hex(random_bytes(8));
try {
    if (!archive_entries_safe($archive)) throw new RuntimeException('Unsafe or unsupported archive.');
    if (!mkdir($tmp, 0700)) throw new RuntimeException('Cannot create restore staging directory.');
    if (preg_match('/\.zip$/i', $archive) && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true || !$zip->extractTo($tmp)) throw new RuntimeException('Cannot extract backup.');
        $zip->close();
    } else { (new PharData($archive))->extractTo($tmp); }
    $cfg = lms_config();
    $manifest = is_file($tmp . '/manifest.json') ? json_decode(file_get_contents($tmp . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR) : null;
    if ($manifest !== null) {
        if (($manifest['format'] ?? 0) !== 2 || !is_array($manifest['files'] ?? null) || ($manifest['driver'] ?? '') !== $cfg['db_driver']) throw new RuntimeException('Backup format or database driver mismatch.');
        foreach ($manifest['files'] as $rel => $hash) {
            if (!is_string($rel) || !preg_match('#^(data-root/|courses/|db/)#', $rel) || str_contains($rel, '..') || str_contains($rel, '\\') || !is_string($hash)
                || !is_file($tmp . '/' . $rel) || !hash_equals($hash, hash_file('sha256', $tmp . '/' . $rel))) throw new RuntimeException('Backup integrity verification failed.');
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($tmp) + 1);
            if (!in_array($rel, ['manifest.json', 'BACKUP-INFO.txt'], true) && !isset($manifest['files'][$rel])) throw new RuntimeException('Unlisted backup file.');
        }
        if ($cfg['db_driver'] === 'sqlite') {
            $check = new PDO('sqlite:' . $tmp . '/db/snapshot.sqlite');
            if ($check->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Invalid SQLite snapshot.');
            unset($check);
        }
    } elseif (!is_dir($tmp . '/data-root')) { throw new RuntimeException('Not a Sapiqo backup.'); }
    // Resolve every destination before the first write, rejecting existing symlink ancestors.
    $copies = [];
    foreach (['data-root' => dirname($cfg['data_dir']), 'courses' => $cfg['courses_dir']] as $prefix => $destRoot) {
        if (!is_dir($tmp . '/' . $prefix)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp . '/' . $prefix, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->isLink()) throw new RuntimeException('Unexpected backup entry.');
            $rel = substr($file->getPathname(), strlen($tmp . '/' . $prefix) + 1);
            if ($prefix === 'data-root' && $rel === 'config.local.php' && in_array('--keep-config', $argv, true)) continue;
            $copies[$file->getPathname()] = rtrim($destRoot, '/') . '/' . $rel;
        }
    }
    if ($manifest && $cfg['db_driver'] === 'sqlite') $copies[$tmp . '/db/snapshot.sqlite'] = $cfg['sqlite_path'];
    foreach ($copies as $src => $dest) {
        for ($p = $dest; $p !== dirname($p); $p = dirname($p)) if (is_link($p)) throw new RuntimeException('Restore destination contains a symbolic link.');
    }
    foreach ($copies as $src => $dest) {
        if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0700, true)) throw new RuntimeException('Cannot create restore destination.');
        $stage = $dest . '.restore-' . bin2hex(random_bytes(4));
        if (!copy($src, $stage) || !chmod($stage, 0600) || !rename($stage, $dest)) throw new RuntimeException('Cannot restore file.');
    }
    if ($cfg['db_driver'] === 'sqlite') foreach (['-wal', '-shm'] as $suffix) if (is_file($cfg['sqlite_path'] . $suffix) && !unlink($cfg['sqlite_path'] . $suffix)) throw new RuntimeException('Cannot remove stale SQLite journal.');
    $dump = $tmp . '/db/dump.sql';
    if (is_file($dump) && $cfg['db_driver'] === 'mysql') {
        $m = $cfg['mysql'];
        $options = backup_mysql_options($m);
        try { $cmd = sprintf('mysql --defaults-extra-file=%s %s < %s', escapeshellarg($options), escapeshellarg($m['dbname']), escapeshellarg($dump)); system($cmd, $rc); }
        finally { unlink($options); }
        if ($rc !== 0) throw new RuntimeException('MySQL import failed.');
    }
    foreach (glob($cfg['data_dir'] . '/.schema-v*') ?: [] as $marker) if (!unlink($marker)) throw new RuntimeException('Cannot invalidate schema marker.');
    echo "Restore complete: database, persistent data" . ($manifest ? ', and courses' : ' (legacy data-only backup)') . ".\nReview restored configuration paths before restarting.\n";
} catch (Throwable $e) { fwrite(STDERR, 'Restore failed: ' . $e->getMessage() . "\n"); exit(1); }
finally { if (is_dir($tmp)) _rmtree($tmp); }
