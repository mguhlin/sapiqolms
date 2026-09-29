<?php
// Build a distributable LMS update package (.tar.gz) from the current code.
// Hand the resulting file to any install; an admin uploads it at Admin → Updates
// to bring that install's LMS code up to date without touching data or content.
//
//   php bin/build-update.php [output_dir]     (defaults to the current directory)

declare(strict_types=1);

require_once __DIR__ . '/../app/updater.php';

$out = $argv[1] ?? getcwd();
try {
    $path = build_update_package($out);
    printf("Built update package: %s\n  version %s, schema %d\n",
        $path, update_current_version(), defined('DB_SCHEMA_VERSION') ? DB_SCHEMA_VERSION : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Build failed: ' . $e->getMessage() . "\n");
    exit(1);
}
