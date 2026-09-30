<?php
// Shared backup routine used by bin/backup.php (CLI) and the admin download route.
// Complete recovery: persistent data, course content, private configuration and DB.
// Archives contain secrets; keep them outside the document root with restricted access.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Build a backup archive in $outDir. Returns the file path. Throws on failure.
// Uses .zip when the Zip extension is present, else a portable .tar.gz.
function create_backup(string $outDir): string {
    $cfg = lms_config();
    $dataRoot = dirname($cfg['data_dir']);
    if (!is_dir($outDir) && !@mkdir($outDir, 0700, true)) throw new RuntimeException("Cannot create $outDir");
    $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));

    // Snapshot SQLite independently of live WAL writes; require a real MySQL dump.
    $dump = null; $snapshot = null; $credentials = null; $outFile = null;
    try {
    if ($cfg['db_driver'] === 'sqlite') {
        $snapshot = tempnam(sys_get_temp_dir(), 'sapiqo-snapshot-');
        if ($snapshot === false) throw new RuntimeException('Cannot allocate database snapshot.');
        unlink($snapshot);
        db()->exec('VACUUM INTO ' . db()->quote($snapshot));
        chmod($snapshot, 0600);
    } elseif (function_exists('system')) {
        $m = $cfg['mysql'];
        $credentials = backup_mysql_options($m);
        $dump = tempnam(sys_get_temp_dir(), 'lmsdump');
        $cmd = sprintf('mysqldump --defaults-extra-file=%s --single-transaction --skip-lock-tables %s > %s 2>/dev/null', escapeshellarg($credentials), escapeshellarg($m['dbname']), escapeshellarg($dump));
        system($cmd, $rc);
        if ($rc !== 0 || !is_file($dump) || filesize($dump) === 0) {
            @unlink($dump); throw new RuntimeException('MySQL dump failed; no incomplete backup was created.');
        }
    } else { throw new RuntimeException('MySQL backup requires mysqldump and system() access.'); }

    $info = "Sapiqo backup\nCreated: " . gmdate('c') . "\nDriver: {$cfg['db_driver']}\n";
    $files = _backup_file_list($dataRoot);
    if ($snapshot) {
        unset($files[$cfg['sqlite_path']], $files[$cfg['sqlite_path'].'-wal'], $files[$cfg['sqlite_path'].'-shm']);
        $files[$snapshot] = 'db/snapshot.sqlite';
    }

    foreach (_backup_file_list($cfg['courses_dir'], 'courses/') as $full => $local) $files[$full] = $local;
    $legacyConfig = __DIR__ . '/config.local.php';
    if (!is_file($dataRoot . '/config.local.php') && is_file($legacyConfig)) $files[$legacyConfig] = 'data-root/config.local.php';
    if ($dump) $files[$dump] = 'db/dump.sql';
    $manifest = ['format' => 2, 'created_at' => gmdate('c'), 'driver' => $cfg['db_driver'], 'files' => []];
    foreach ($files as $full => $local) {
        $hash = hash_file('sha256', $full);
        if ($hash === false) throw new RuntimeException('Cannot read backup source.');
        $manifest['files'][$local] = $hash;
    }
    $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    if (class_exists('ZipArchive')) {
        $outFile = rtrim($outDir, '/') . "/sapiqo-backup-$stamp.zip";
        $zip = new ZipArchive();
        if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create archive: $outFile");
        }
        foreach ($files as $full => $local) if (!$zip->addFile($full, $local)) throw new RuntimeException('Cannot archive backup source.');
        $zip->addFromString('manifest.json', $manifestJson);
        $zip->addFromString('BACKUP-INFO.txt', $info . 'Files: ' . count($files) . "\n");
        if (!$zip->close()) throw new RuntimeException('Cannot finish backup archive.');
    } else {
        // Portable tar.gz via PharData (no zip extension required).
        $tarPath = rtrim($outDir, '/') . "/sapiqo-backup-$stamp.tar";
        @unlink($tarPath); @unlink($tarPath . '.gz');
        $tar = new PharData($tarPath);
        foreach ($files as $full => $local) $tar->addFile($full, $local);
        $tar->addFromString('manifest.json', $manifestJson);
        $tar->addFromString('BACKUP-INFO.txt', $info . 'Files: ' . count($files) . "\n");
        $tar->compress(Phar::GZ);
        unset($tar);
        @unlink($tarPath);          // keep only the compressed .tar.gz
        $outFile = $tarPath . '.gz';
    }
    chmod($outFile, 0600);
    foreach ($manifest['files'] as $local => $hash) {
        $archived = hash_file('sha256', 'phar://' . $outFile . '/' . $local);
        if ($archived === false || !hash_equals($hash, $archived)) throw new RuntimeException('Backup source changed during capture; retry during a maintenance window.');
    }
    return $outFile;
    } catch (Throwable $e) { if ($outFile && is_file($outFile)) @unlink($outFile); throw $e; }
    finally { foreach ([$dump,$snapshot,$credentials] as $temp) if ($temp && is_file($temp)) @unlink($temp); }

}

// Map of absolute path => archive-local path for the data root (minus transient
// SQLite side files and any prior backups).
function _backup_file_list(string $dataRoot, string $prefix = 'data-root/'): array {
    $out = [];
    if (!is_dir($dataRoot)) throw new RuntimeException('Backup source directory missing.');
    $dataRoot = rtrim($dataRoot, '/\\');
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        $rel = ltrim(substr($file->getPathname(), strlen($dataRoot)), '/\\');
        if ($file->isLink()) throw new RuntimeException('Backup sources must not contain symbolic links.');
        if (!$file->isFile()) continue;
        if (preg_match('/(?:^|\/)\.schema-v[0-9]+$/', $rel)) continue;
        if ($prefix === 'data-root/' && preg_match('#^(?:backups/|data/(?:tmp|exports|backups|updates)/)#', str_replace('\\', '/', $rel))) continue;
        if (preg_match('/\.sqlite-(wal|shm)$/', $rel)) continue;
        $out[$file->getPathname()] = $prefix . str_replace('\\', '/', $rel);
    }
    return $out;
}

// Keep MySQL credentials off the process command line.
function backup_mysql_options(array $mysql): string {
    $file = tempnam(sys_get_temp_dir(), 'sapiqo-db-options-');
    if ($file === false) throw new RuntimeException('Cannot allocate database options.');
    chmod($file, 0600);
    $text = "[client]\n";
    foreach (['host'=>'host','port'=>'port','user'=>'user','password'=>'pass'] as $option=>$key) {
        $value = str_replace(["\\", "\n", "\r", '"'], ["\\\\", "\\n", "\\r", '\\"'], (string)$mysql[$key]);
        $text .= $option . '="' . $value . "\"\n";
    }
    if (file_put_contents($file,$text) === false) { unlink($file); throw new RuntimeException('Cannot save database options.'); }
    return $file;
}
