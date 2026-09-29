<?php
// Shared backup routine used by bin/backup.php (CLI) and the admin download route.
// Backs up the persistent data root (config, DB, badges, avatars, uploads) — not
// the course content, which is static files backed up separately (ZIP export or
// a file copy of the courses/ folder).

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Build a backup archive in $outDir. Returns the file path. Throws on failure.
// Uses .zip when the Zip extension is present, else a portable .tar.gz.
function create_backup(string $outDir): string {
    $cfg = lms_config();
    $dataRoot = dirname($cfg['data_dir']);
    if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) throw new RuntimeException("Cannot create $outDir");
    $stamp = date('Ymd-His');

    // Snapshot SQLite independently of live WAL writes; require a real MySQL dump.
    $dump = null; $snapshot = null;
    if ($cfg['db_driver'] === 'sqlite') {
        $snapshot = tempnam(sys_get_temp_dir(), 'sapiqo-snapshot-');
        if ($snapshot === false) throw new RuntimeException('Cannot allocate database snapshot.');
        unlink($snapshot);
        db()->exec('VACUUM INTO ' . db()->quote($snapshot));
    } elseif (function_exists('system')) {
        $m = $cfg['mysql'];
        $dump = tempnam(sys_get_temp_dir(), 'lmsdump');
        $cmd = sprintf('mysqldump --host=%s --port=%d --user=%s %s %s > %s 2>/dev/null',
            escapeshellarg($m['host']), (int) $m['port'], escapeshellarg($m['user']),
            $m['pass'] !== '' ? '--password=' . escapeshellarg($m['pass']) : '',
            escapeshellarg($m['dbname']), escapeshellarg($dump));
        system($cmd, $rc);
        if ($rc !== 0 || !is_file($dump) || filesize($dump) === 0) {
            @unlink($dump); throw new RuntimeException('MySQL dump failed; no incomplete backup was created.');
        }
    } else { throw new RuntimeException('MySQL backup requires mysqldump and system() access.'); }

    $info = "Sapiqo backup\nCreated: " . gmdate('c') . "\nDriver: {$cfg['db_driver']}\n";
    $files = _backup_file_list($dataRoot);
    if ($snapshot) {
        unset($files[$cfg['sqlite_path']]);
        $files[$snapshot] = 'data-root/data/' . basename($cfg['sqlite_path']);
    }

    if (class_exists('ZipArchive')) {
        $outFile = rtrim($outDir, '/') . "/sapiqo-backup-$stamp.zip";
        $zip = new ZipArchive();
        if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create archive: $outFile");
        }
        foreach ($files as $full => $local) $zip->addFile($full, $local);
        if ($dump) $zip->addFile($dump, 'db/dump.sql');
        $zip->addFromString('BACKUP-INFO.txt', $info . 'Files: ' . count($files) . "\n");
        $zip->close();
    } else {
        // Portable tar.gz via PharData (no zip extension required).
        $tarPath = rtrim($outDir, '/') . "/sapiqo-backup-$stamp.tar";
        @unlink($tarPath); @unlink($tarPath . '.gz');
        $tar = new PharData($tarPath);
        foreach ($files as $full => $local) $tar->addFile($full, $local);
        if ($dump) $tar->addFile($dump, 'db/dump.sql');
        $tar->addFromString('BACKUP-INFO.txt', $info . 'Files: ' . count($files) . "\n");
        $tar->compress(Phar::GZ);
        unset($tar);
        @unlink($tarPath);          // keep only the compressed .tar.gz
        $outFile = $tarPath . '.gz';
    }
    if ($dump) @unlink($dump);
    if ($snapshot) @unlink($snapshot);
    return $outFile;
}

// Map of absolute path => archive-local path for the data root (minus transient
// SQLite side files and any prior backups).
function _backup_file_list(string $dataRoot): array {
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        $rel = ltrim(substr($file->getPathname(), strlen($dataRoot)), '/\\');
        if ($file->isLink()) continue;
        if (preg_match('#^(?:backups/|data/(?:tmp|exports|backups|updates)/)#', str_replace('\\', '/', $rel))) continue;
        if (preg_match('/\.sqlite-(wal|shm)$/', $rel)) continue;
        $out[$file->getPathname()] = 'data-root/' . str_replace('\\', '/', $rel);
    }
    return $out;
}
