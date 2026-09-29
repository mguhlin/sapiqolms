<?php
// WordPress-style self-update for the LMS code.
//
// The install is three separated trees (see config.php): the replaceable code
// (sapiqo/), the persistent data root (sapiqo-data/ — DB, badges, uploads,
// config.local.php), and course content (content/). An "update" replaces the
// CODE only; data, content, and local config are never touched. Packages are
// .tar.gz (PharData — no zip extension needed) with an update.json manifest.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/course_io.php';   // io_tmp_dir(), _copy_tree(), _rmtree()

function update_current_version(): string { return defined('APP_VERSION') ? APP_VERSION : '0.0.0'; }

// The replaceable code root (the sapiqo/ folder).
function update_code_root(): string { return dirname(__DIR__); }

// Where code backups are kept (inside the persistent data root, so they survive).
function update_backups_dir(): string {
    $d = rtrim(lms_config()['data_dir'], '/') . '/updates';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

// Top-level names inside sapiqo/ that are NOT part of the shippable code and must
// never be packaged, backed up, or overwritten.
function update_excluded_top(): array {
    return ['data', '.git', 'node_modules'];
}

// Iterate the code tree, yielding [absolutePath, relativePath] for shippable files.
function _update_iter_code(string $root): Generator {
    $excl = update_excluded_top();
    foreach (scandir($root) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (in_array($entry, $excl, true)) continue;
        $abs = $root . '/' . $entry;
        if (is_dir($abs)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $f) {
                $rel = ltrim(substr($f->getPathname(), strlen($root)), '/\\');
                $rel = str_replace('\\', '/', $rel);
                // Never carry per-install local config into a distributable package.
                if ($rel === 'app/config.local.php') continue;
                yield [$f->getPathname(), $rel];
            }
        } else {
            yield [$abs, $entry];
        }
    }
}

// Build a distributable update package from the current code. Returns its path.
// $meta lets the caller stamp created_at (scripts can't call time() in some envs).
function build_update_package(string $outDir, array $meta = []): string {
    if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) throw new RuntimeException("Cannot create $outDir");
    $root = update_code_root();
    $ver  = update_current_version();

    $manifest = [
        'name'           => 'sapiqo-update',
        'version'        => $ver,
        'schema_version' => defined('DB_SCHEMA_VERSION') ? DB_SCHEMA_VERSION : null,
        'created_at'     => $meta['created_at'] ?? gmdate('c'),
        'app_name'       => lms_config()['app_name'] ?? 'Sapiqo',
        // Which specific edition/flavor of the code this was built from (see
        // config.php 'edition_label'). Lets the apply step warn before mixing
        // code between installs whose code genuinely differs (e.g. a Vimeo
        // video edition vs. a self-hosted-video edition) even when their
        // branding is otherwise identical. '' if the source install didn't set one.
        'edition'        => lms_config()['edition_label'] ?? '',
    ];

    $tar = rtrim($outDir, '/') . "/sapiqo-update-$ver.tar";
    @unlink($tar); @unlink($tar . '.gz');
    $p = new PharData($tar);
    $count = 0;
    foreach (_update_iter_code($root) as [$abs, $rel]) {
        $p->addFile($abs, 'code/' . $rel);
        $count++;
    }
    $manifest['files'] = $count;
    $p->addFromString('update.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $p->compress(Phar::GZ);          // -> $tar.gz
    unset($p);
    @unlink($tar);                   // keep only the compressed package
    return $tar . '.gz';
}

// Back up the current code tree to updates/. Returns the backup path.
function backup_code(array $meta = []): string {
    $root = update_code_root();
    $ver  = update_current_version();
    $ts   = $meta['ts'] ?? gmdate('Ymd-His');
    $tar  = update_backups_dir() . "/backup-$ver-$ts.tar";
    @unlink($tar); @unlink($tar . '.gz');
    $p = new PharData($tar);
    foreach (_update_iter_code($root) as [$abs, $rel]) {
        $p->addFile($abs, $rel);     // backups mirror the code root directly
    }
    $p->compress(Phar::GZ);
    unset($p);
    @unlink($tar);
    return $tar . '.gz';
}

function list_backups(): array {
    $out = [];
    foreach (glob(update_backups_dir() . '/backup-*.tar.gz') ?: [] as $f) {
        $out[] = ['file' => basename($f), 'path' => $f, 'size' => filesize($f), 'mtime' => filemtime($f)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

// Extract a .tar.gz (or .tar) to a fresh temp dir. Returns the dir path.
function _update_extract(string $tmpFile, string $origName): string {
    $dir = io_tmp_dir('update-');
    $ext = preg_match('/\.tar\.gz$|\.tgz$/i', $origName) ? 'tar.gz' : 'tar';
    $staged = $dir . '/pkg.' . $ext;
    if (!copy($tmpFile, $staged)) throw new RuntimeException('Could not stage the uploaded package.');
    if (function_exists('archive_entries_safe') && !archive_entries_safe($staged)) {
        throw new RuntimeException('The package contains unsafe file paths.');
    }
    $phar = new PharData($staged);
    $phar->extractTo($dir, null, true);
    @unlink($staged);
    return $dir;
}

// Validate + apply an uploaded update package. Returns [ok(bool), message, meta].
function apply_update_package(string $tmpFile, string $origName, bool $force = false): array {
    if (!preg_match('/\.(tar\.gz|tgz|tar)$/i', $origName)) {
        return [false, 'Upload a .tar.gz update package (built with bin/build-update.php).', []];
    }
    $root = update_code_root();
    if (!is_writable($root)) {
        return [false, "The code folder isn't writable by the web server ($root). Fix permissions and retry.", []];
    }
    $stage = null;
    try {
        $stage = _update_extract($tmpFile, $origName);

        // Manifest is required and identifies this as a real update package.
        $manifestPath = "$stage/update.json";
        if (!is_file($manifestPath)) return [false, 'Not a valid update package (no update.json).', []];
        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        if (($manifest['name'] ?? '') !== 'sapiqo-update') {
            return [false, 'This package is not a Sapiqo update.', []];
        }
        $newVer = (string) ($manifest['version'] ?? '');
        $curVer = update_current_version();
        if ($newVer === '') return [false, 'The package manifest has no version.', []];

        // A real code tree must be present.
        $srcCode = "$stage/code";
        if (!is_file("$srcCode/app/config.php") || !is_dir("$srcCode/public")) {
            return [false, 'The package is missing the expected code/ tree.', $manifest];
        }

        if (!$force && version_compare($newVer, $curVer, '<=')) {
            return [false, "Uploaded version ($newVer) is not newer than the installed version ($curVer). "
                . "Tick “apply anyway” to reinstall or downgrade.", $manifest + ['current' => $curVer]];
        }

        // Edition mismatch guard: two installs can share branding but run
        // genuinely different code (e.g. a Vimeo-video edition vs. a
        // self-hosted-video edition). If BOTH the package and this install
        // declare an edition_label and they differ, refuse unless forced —
        // this is the same "apply anyway" checkbox as the version check above.
        $pkgEdition = (string) ($manifest['edition'] ?? '');
        $curEdition = (string) (lms_config()['edition_label'] ?? '');
        if (!$force && $pkgEdition !== '' && $curEdition !== '' && $pkgEdition !== $curEdition) {
            return [false, "This package was built from a different edition (\"$pkgEdition\") than this "
                . "install (\"$curEdition\"). Applying it may overwrite code this install genuinely needs "
                . "to be different. Tick “apply anyway” only if you're sure.", $manifest + ['current_edition' => $curEdition]];
        }

        if (is_file($srcCode . '/app/config.local.php') || is_dir($srcCode . '/data')) {
            return [false, 'Update packages must not contain local configuration or runtime data.', $manifest];
        }

        // Back up current code first (rollback safety net).
        $backup = backup_code();

        // Apply: copy the package's code over the live tree. _copy_tree merges
        // (adds/overwrites) and never deletes, so removed files linger but nothing
        // in-use is lost; data/, content/, and config.local.php are untouched
        // because they live outside code/ (and config.local is excluded from packages).
        _copy_tree($srcCode, $root);

        return [true, "Updated from $curVer to $newVer. A backup of the previous code was saved. "
            . "Database migrations (if any) run automatically on the next page load.",
            $manifest + ['from' => $curVer, 'to' => $newVer, 'backup' => basename($backup)]];
    } catch (Throwable $e) {
        return [false, 'Update failed: ' . $e->getMessage(), []];
    } finally {
        if ($stage) _rmtree($stage);
    }
}

// Roll back to a previous code backup (latest by default). Returns [ok, message].
function rollback_update(?string $file = null): array {
    $backups = list_backups();
    if (!$backups) return [false, 'No code backups are available to roll back to.'];
    $target = null;
    if ($file === null) {
        $target = $backups[0]['path'];
    } else {
        if (!preg_match('/^backup-[A-Za-z0-9.\-]+\.tar\.gz$/', $file)) return [false, 'Invalid backup name.'];
        $cand = update_backups_dir() . '/' . $file;
        if (!is_file($cand)) return [false, 'That backup no longer exists.'];
        $target = $cand;
    }
    $root = update_code_root();
    if (!is_writable($root)) return [false, "The code folder isn't writable ($root)."];
    $stage = null;
    try {
        $stage = io_tmp_dir('rollback-');
        $staged = $stage . '/backup.tar.gz';
        copy($target, $staged);
        if (!archive_entries_safe($staged)) throw new RuntimeException('Unsafe backup archive.');
        $phar = new PharData($staged);
        $phar->extractTo($stage, null, true);
        @unlink($staged);
        // The backup mirrors the code root directly (no code/ prefix).
        _copy_tree($stage, $root);
        return [true, 'Rolled back to ' . basename($target) . '. Reload to run on the restored code.'];
    } catch (Throwable $e) {
        return [false, 'Rollback failed: ' . $e->getMessage()];
    } finally {
        if ($stage) _rmtree($stage);
    }
}
