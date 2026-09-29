<?php
// Create a single-file backup of the LMS data root (config, database, badges,
// avatars, uploads) plus a SQL dump for MySQL installs. Produces a .zip.
//
// Usage:  php bin/backup.php [/path/to/output-dir]   (default: <data-root>/backups/)

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/backup.php';

$cfg = lms_config();
$outDir = $argv[1] ?? (dirname($cfg['data_dir']) . '/backups');
try {
    $file = create_backup($outDir);
    printf("Backup written: %s (%.1f MB)\n", $file, filesize($file) / 1048576);
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
