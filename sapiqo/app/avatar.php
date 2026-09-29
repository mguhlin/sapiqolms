<?php
// Profile photos. File-based (no schema change): data/avatars/<id>.jpg.
// Users can upload one; SSO logins auto-pull the provider picture.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function avatar_dir(): string {
    $d = lms_config()['data_dir'] . '/avatars';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

function avatar_path(int $userId): string {
    return avatar_dir() . '/' . $userId . '.jpg';
}

function has_avatar(int $userId): bool {
    return is_file(avatar_path($userId));
}

function avatar_initials(array $user): string {
    $a = strtoupper(substr(trim($user['first_name'] ?? ''), 0, 1));
    $b = strtoupper(substr(trim($user['last_name'] ?? ''), 0, 1));
    $s = $a . $b;
    return $s !== '' ? $s : strtoupper(substr($user['email'] ?? '?', 0, 1));
}

// Save an uploaded image ($_FILES entry) as a centered 256px square JPEG.
function save_avatar_upload(int $userId, array $file): array {
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return [false, 'Upload failed.'];
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) return [false, 'Image too large (8 MB max).'];
    $tmp = $file['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) return [false, 'That file is not a valid image.'];
    return _avatar_write_from_file($userId, $tmp, $info[2])
        ? [true, 'Photo updated.']
        : [false, 'Could not process that image (JPEG, PNG, or WebP only).'];
}

// Download a provider picture URL and store it as the avatar (best effort).
function save_avatar_from_url(int $userId, string $url): bool {
    if ($url === '' || !preg_match('#^https?://#', $url)) return false;
    try {
        // SSRF-safe fetch: blocks internal/metadata hosts + redirects into them.
        $data = function_exists('safe_fetch') ? safe_fetch($url, 15, 5000000) : null;
        if (!$data) return false;
        $tmp = tempnam(sys_get_temp_dir(), 'av');
        file_put_contents($tmp, $data);
        $info = @getimagesize($tmp);
        $ok = $info ? _avatar_write_from_file($userId, $tmp, $info[2]) : false;
        @unlink($tmp);
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

function _avatar_write_from_file(int $userId, string $path, int $type): bool {
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($path),
        default        => false,
    };
    if (!$src) return false;
    $w = imagesx($src); $h = imagesy($src);
    $side = min($w, $h);
    $sx = (int) (($w - $side) / 2); $sy = (int) (($h - $side) / 2);
    $out = imagecreatetruecolor(256, 256);
    imagecopyresampled($out, $src, 0, 0, $sx, $sy, 256, 256, $side, $side);
    $ok = imagejpeg($out, avatar_path($userId), 88);
    imagedestroy($src); imagedestroy($out);
    return $ok;
}

function delete_avatar(int $userId): void {
    if (has_avatar($userId)) @unlink(avatar_path($userId));
}

// Render an avatar: the photo if present, else an initials circle.
function avatar_html(array $user, int $px = 32): string {
    $id = (int) $user['id'];
    $style = "width:{$px}px;height:{$px}px;border-radius:50%;object-fit:cover;flex:none;";
    if (has_avatar($id)) {
        return '<img class="avatar" alt="" src="' . e(url('/avatar/' . $id)) . '" style="' . $style . '">';
    }
    $fs = max(11, (int) round($px * 0.42));
    return '<span class="avatar avatar--initials" style="' . $style
        . "display:inline-flex;align-items:center;justify-content:center;background:var(--navy-600);color:#fff;"
        . "font-weight:700;font-size:{$fs}px;\">" . e(avatar_initials($user)) . '</span>';
}
