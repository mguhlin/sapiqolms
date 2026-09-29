<?php
// Native course export/import: a course folder (course.json + media + source.md)
// packaged as a portable .tar.gz. Doubles as a per-course backup and lets courses
// move between installs. Uses PharData (no zip extension required).

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// A disk-backed scratch dir under the data root. sys_get_temp_dir() is often a
// small RAM tmpfs that can't hold course media (videos), so we avoid it here.
function io_tmp_dir(string $prefix): string {
    $base = rtrim(lms_config()['data_dir'], '/') . '/tmp';
    if (!is_dir($base)) @mkdir($base, 0775, true);
    $dir = $base . '/' . $prefix . bin2hex(random_bytes(4));
    @mkdir($dir, 0775, true);
    return $dir;
}

// Export a course folder to $outDir as course-<slug>-<ts>.tar.
// Uses an uncompressed tar because course media (mp4/png/jpg) is already
// compressed — gzip would be slow with no size benefit.
function export_course_archive(string $slug, string $outDir): string {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) throw new RuntimeException('Invalid slug.');
    $courseDir = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
    if (!is_file("$courseDir/course.json")) throw new RuntimeException("No course found for “$slug”.");
    if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) throw new RuntimeException("Cannot create $outDir");

    $tarPath = rtrim($outDir, '/') . "/course-$slug-" . date('Ymd-His') . '.tar';
    @unlink($tarPath);
    $tar = new PharData($tarPath);
    // Place everything under a top-level folder named for the slug.
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($courseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        $rel = ltrim(substr($file->getPathname(), strlen($courseDir)), '/\\');
        if ($rel === '.disabled') continue;   // don't carry the hidden marker
        $tar->addFile($file->getPathname(), $slug . '/' . str_replace('\\', '/', $rel));
    }
    unset($tar);
    return $tarPath;
}

// Import a course archive (.tar.gz/.tgz, or .zip if the extension is present).
// Returns [ok(bool), slugOrError(string)].
function import_course_archive(string $tmpFile, string $origName): array {
    $tmpDir = io_tmp_dir('import-');
    try {
        if (preg_match('/\.zip$/i', $origName) && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpFile) !== true) return [false, 'Could not open the .zip archive.'];
            if (!archive_entries_safe($tmpFile)) { $zip->close(); return [false, 'The archive contains unsafe entries.']; }
            if (!$zip->extractTo($tmpDir)) { $zip->close(); return [false, 'Could not extract the archive.']; }
            $zip->close();
        } elseif (preg_match('/\.(tar\.gz|tgz|tar)$/i', $origName, $em)) {
            // PharData needs a real extension; copy to a temp path with one.
            $ext = strtolower($em[1]) === 'tar' ? 'tar' : 'tar.gz';
            $staged = $tmpDir . '/upload.' . $ext;
            copy($tmpFile, $staged);
            if (function_exists('archive_entries_safe') && !archive_entries_safe($staged)) {
                return [false, 'The archive contains unsafe file paths.'];
            }
            $p = new PharData($staged);
            $p->extractTo($tmpDir, null, true);
            @unlink($staged);
        } else {
            return [false, 'Unsupported archive. Upload a .tar, .tar.gz, or .zip course export.'];
        }

        // Locate course.json (root or one folder deep).
        $jsonPath = _find_course_json($tmpDir);
        if ($jsonPath === null) return [false, 'No course.json found in the archive.'];
        $data = json_decode((string) file_get_contents($jsonPath), true);
        if (!$data || empty($data['slug'])) return [false, 'course.json is missing a slug.'];
        $slug = $data['slug'];
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) return [false, 'course.json has an invalid slug.'];
        if (in_array($slug, ['sapiqo', 'sapiqo-data', 'assets', 'creator'], true)) {
            return [false, 'That slug is reserved.'];
        }

        $srcDir = dirname($jsonPath);
        $destDir = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
        _copy_tree($srcDir, $destDir);
        @unlink($destDir . '/.disabled');   // imported courses are active
        return [true, $slug];
    } catch (Throwable $e) {
        return [false, 'Import failed: ' . $e->getMessage()];
    } finally {
        _rmtree($tmpDir);
    }
}

// Parse a PHP ini size string like "8M", "2G", "512K" into bytes.
function ini_bytes(string $val): int {
    $val = trim($val);
    if ($val === '') return 0;
    $unit = strtolower($val[strlen($val) - 1]);
    $num = (int) $val;
    return match ($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => (int) $val,
    };
}

// The largest safe per-request upload chunk: 90% of the smaller of PHP's
// upload_max_filesize / post_max_size, optionally capped by config export_chunk_mb.
function upload_chunk_bytes(): int {
    $up = ini_bytes((string) ini_get('upload_max_filesize')) ?: 2 * 1024 * 1024;
    $post = ini_bytes((string) ini_get('post_max_size')) ?: 8 * 1024 * 1024;
    $cap = (int) floor(min($up, $post) * 0.9);
    $cfgMb = (int) (lms_config()['export_chunk_mb'] ?? 0);
    if ($cfgMb > 0) $cap = min($cap, $cfgMb * 1024 * 1024);
    return max(256 * 1024, $cap);   // never below 256 KB
}

// Split a file into <name>.partNNN pieces of $chunkBytes each. Returns part paths.
function split_file(string $file, string $outDir, int $chunkBytes): array {
    if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) throw new RuntimeException("Cannot create $outDir");
    $in = fopen($file, 'rb');
    if (!$in) throw new RuntimeException('Cannot read the archive to split.');
    $base = basename($file);
    $parts = []; $n = 0;
    while (!feof($in)) {
        $chunk = fread($in, $chunkBytes);
        if ($chunk === '' || $chunk === false) break;
        $partPath = sprintf('%s/%s.part%03d', rtrim($outDir, '/'), $base, ++$n);
        file_put_contents($partPath, $chunk);
        $parts[] = $partPath;
    }
    fclose($in);
    return $parts;
}

// Build a course .tar and split it into parts under $stageDir. Returns
// ['archive' => tarBaseName, 'parts' => [names...]].
function export_course_split(string $slug, string $stageDir, int $chunkBytes): array {
    $tmp = io_tmp_dir('split-');
    $tar = export_course_archive($slug, $tmp);          // uncompressed .tar
    $parts = split_file($tar, $stageDir, $chunkBytes);
    $archiveName = basename($tar);
    @unlink($tar); @rmdir(dirname($tar));
    return ['archive' => $archiveName, 'parts' => array_map('basename', $parts)];
}

// Concatenate <dir>/*.partNNN (sorted) into $destFile.
function assemble_parts(string $dir, string $destFile): bool {
    $parts = glob($dir . '/*.part*') ?: [];
    if (!$parts) return false;
    natsort($parts);
    $out = fopen($destFile, 'wb');
    if (!$out) return false;
    foreach ($parts as $p) {
        $in = fopen($p, 'rb');
        if (!$in) { fclose($out); return false; }
        stream_copy_to_stream($in, $out);
        fclose($in);
    }
    fclose($out);
    return true;
}

function _find_course_json(string $dir): ?string {
    if (is_file("$dir/course.json")) return "$dir/course.json";
    foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $sub) {
        if (is_file("$sub/course.json")) return "$sub/course.json";
    }
    return null;
}

function _copy_tree(string $src, string $dest): void {
    if (!is_dir($dest)) @mkdir($dest, 0775, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = ltrim(substr($item->getPathname(), strlen($src)), '/\\');
        $target = $dest . '/' . $rel;
        if ($item->isLink() || is_link($target)) throw new RuntimeException('Symbolic links are not allowed in copied trees.');
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0775, true)) throw new RuntimeException('Cannot create destination directory.');
        } else {
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) throw new RuntimeException('Cannot create destination directory.');
            if (!copy($item->getPathname(), $target)) throw new RuntimeException('Cannot copy destination file.');
        }
    }
}

function _rmtree(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}
