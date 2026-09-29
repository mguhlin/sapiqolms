<?php
// Router for the PHP built-in server (development only):
//   php -S localhost:8000 -t public public/router.php
// Serves existing static files (course pages, assets, videos with range) directly
// and sends everything else to the front controller.

declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (preg_match('#(^|/)\.#', $path)) { http_response_code(404); exit('Not found'); }
$file = realpath(__DIR__ . $path);
$assets = realpath(__DIR__ . '/assets');
if ($file !== false && $assets !== false && str_starts_with($path, '/assets/')
    && str_starts_with($file, $assets . DIRECTORY_SEPARATOR) && is_file($file)) return false;
// A missing *static asset* (recognized front-end extension, not a course path)
// → real 404, so it isn't mis-routed to the front controller under the built-in
// server. App download routes with dots (e.g. /admin/users.csv) are NOT in this
// list, so they fall through to index.php below.
if (!str_starts_with($path, '/courses/')
    && preg_match('#\.(css|js|mjs|map|png|jpe?g|gif|svg|webp|avif|ico|bmp|woff2?|ttf|otf|eot|mp4|webm|ogg|ogv|mp3|wav|m4a|vtt|srt)$#i', $path)) {
    http_response_code(404);
    exit('Not found');
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
