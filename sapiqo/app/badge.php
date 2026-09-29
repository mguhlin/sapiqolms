<?php
// Personalized badge image generation using PHP GD. Overlays the learner name,
// course title, and date onto a placeholder badge, so the result is a single
// downloadable PNG. Replace the placeholder art in public/assets/img later.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Default palette (navy/gold). Overridden by brand settings when configured.
const BADGE_NAVY = [11, 46, 91];
const BADGE_GOLD = [244, 180, 26];
const BADGE_INK  = [22, 32, 46];

// Resolve badge colors from brand settings when available, else the defaults.
function _badge_rgb(string $which): array {
    if (function_exists('brand_colors') && function_exists('hex_to_rgb')) {
        $c = brand_colors();
        if ($which === 'navy') return hex_to_rgb($c['primary']);
        if ($which === 'gold') return hex_to_rgb($c['accent']);
    }
    return $which === 'navy' ? BADGE_NAVY : ($which === 'gold' ? BADGE_GOLD : BADGE_INK);
}

// Text for the placeholder medallion: an explicit brand mark if the admin set
// one, otherwise the platform name (kept short so it fits the medallion).
function _badge_mark_text(): string {
    $cfg = lms_config();
    $explicit = function_exists('setting') ? trim(setting('brand_mark', '')) : '';
    $text = $explicit !== '' ? $explicit : ($cfg['app_name'] ?? 'Sapiqo');
    return strtoupper(mb_substr($text, 0, 10));
}

function ensure_placeholder_badge(): string {
    $cfg = lms_config();
    $path = $cfg['badge_placeholder'];
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);

    // Prefer the bundled, professionally designed medallion when present: copy it
    // into the writable data dir (refreshing if the bundled art is newer). Only
    // fall back to the generated GD medallion below when no bundled art ships
    // (e.g. a white-label install that removed it).
    $bundled = dirname(__DIR__) . '/public/assets/img/badge-placeholder.png';
    if (is_file($bundled)) {
        if (!is_file($path) || filemtime($bundled) > filemtime($path)) @copy($bundled, $path);
        if (is_file($path)) return $path;
    }

    // Generate a simple navy/gold placeholder medallion once.
    $size = 600;
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    $navy = imagecolorallocate($im, ..._badge_rgb('navy'));
    $gold = imagecolorallocate($im, ..._badge_rgb('gold'));
    $white = imagecolorallocate($im, 255, 255, 255);

    $cx = $cy = $size / 2;
    imagefilledellipse($im, (int)$cx, (int)$cy, 540, 540, $gold);
    imagefilledellipse($im, (int)$cx, (int)$cy, 500, 500, $navy);
    imagefilledellipse($im, (int)$cx, (int)$cy, 430, 430, $navy);
    // Accent ring
    imagesetthickness($im, 10);
    imageellipse($im, (int)$cx, (int)$cy, 470, 470, $gold);

    _badge_text_centered($im, _badge_mark_text(), 46, 250, $gold, true);
    _badge_text_centered($im, 'CERTIFIED', 22, 320, $white, false);

    imagepng($im, $path);
    imagedestroy($im);
    return $path;
}

// Resolve a course's badge art path: absolute, relative to public/, or root.
function resolve_badge_art(string $path): ?string {
    if ($path === '') return null;
    if (is_file($path)) return $path;
    $root = dirname(__DIR__);
    foreach ([$root . '/public/' . $path, $root . '/' . $path] as $p) {
        if (is_file($p)) return $p;
    }
    return null;
}

// Issue a badge PNG for a completed course. Returns the image path (relative to data_dir).
function generate_badge_image(array $user, array $course, string $code, string $issuedAt): string {
    $cfg = lms_config();
    $base = resolve_badge_art((string) ($course['badge_image'] ?? '')) ?: ensure_placeholder_badge();

    // Load the course badge in any format (PNG, JPG, GIF, WebP); fall back to
    // the placeholder medallion if it can't be read.
    $badge = @imagecreatefromstring((string) @file_get_contents($base));
    if (!$badge) $badge = @imagecreatefromstring(file_get_contents(ensure_placeholder_badge()));
    imagealphablending($badge, true);

    // Compose onto a 1000x1200 card with name + course + date below the medallion.
    $W = 1000; $H = 1220;
    $card = imagecreatetruecolor($W, $H);
    $bg = imagecolorallocate($card, 245, 247, 251);
    imagefilledrectangle($card, 0, 0, $W, $H, $bg);

    $navy = imagecolorallocate($card, ..._badge_rgb('navy'));
    $gold = imagecolorallocate($card, ..._badge_rgb('gold'));
    $ink  = imagecolorallocate($card, ...BADGE_INK);
    $soft = imagecolorallocate($card, 64, 80, 102);

    // Top navy band
    imagefilledrectangle($card, 0, 0, $W, 120, $navy);
    imagefilledrectangle($card, 0, 120, $W, 130, $gold);
    _badge_text_centered($card, strtoupper(lms_config()['org_name']), 20, 55, $gold, false, $W);
    _badge_text_centered($card, 'CERTIFICATE OF COMPLETION', 26, 90, imagecolorallocate($card,255,255,255), true, $W);

    // Medallion (scaled, aspect-preserved, alpha-composited onto the card).
    $bw = imagesx($badge); $bh = imagesy($badge);
    $target = 460;
    $th = (int) round($target * $bh / max(1, $bw));
    $dstX = (int)(($W - $target) / 2);
    imagealphablending($card, true);
    imagecopyresampled($card, $badge, $dstX, 175, 0, 0, $target, $th, $bw, $bh);

    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
    _badge_text_centered($card, 'This certifies that', 22, 690, $soft, false, $W);
    _badge_text_centered($card, $name, 46, 760, $navy, true, $W);
    _badge_text_centered($card, 'has successfully completed', 22, 820, $soft, false, $W);
    _badge_text_centered($card, $course['title'], 30, 875, $ink, true, $W);

    $date = date('F j, Y', strtotime($issuedAt) ?: time());
    _badge_text_centered($card, 'Awarded ' . $date, 20, 955, $soft, false, $W);

    // Optional signatory line.
    $signatory = trim((string) ($cfg['cert_signatory'] ?? ''));
    if ($signatory !== '') {
        imagesetthickness($card, 2);
        imageline($card, (int)($W/2 - 190), 1055, (int)($W/2 + 190), 1055, $soft);
        _badge_text_centered($card, $signatory, 20, 1085, $ink, false, $W);
    }

    _badge_text_centered($card, 'Verification code: ' . $code, 16, 1150, $soft, false, $W);
    imagefilledrectangle($card, 0, $H - 14, $W, $H, $gold);

    if (!is_dir($cfg['badge_dir'])) @mkdir($cfg['badge_dir'], 0775, true);
    $file = $cfg['badge_dir'] . '/' . $code . '.png';
    imagepng($card, $file);
    imagedestroy($card);
    imagedestroy($badge);

    return 'badges/' . $code . '.png';
}

// Render certificate page 1 (the text certificate) as a landscape PNG, matching a
// classic completion certificate: name, course, date, CPE hours, and a signatory
// block (name / title / CPE provider #). Returns the temp file path.
function render_certificate_page(array $user, array $course, string $code, string $issuedAt): string {
    $cfg = lms_config();
    $W = 1100; $H = 850;
    $card = imagecreatetruecolor($W, $H);
    imagefilledrectangle($card, 0, 0, $W, $H, imagecolorallocate($card, 255, 255, 255));
    $navy = imagecolorallocate($card, ..._badge_rgb('navy'));
    $gold = imagecolorallocate($card, ..._badge_rgb('gold'));
    $ink  = imagecolorallocate($card, 22, 32, 46);
    $soft = imagecolorallocate($card, 90, 100, 116);

    // Top + bottom accent bands.
    imagefilledrectangle($card, 0, 0, $W, 46, $gold);
    imagefilledrectangle($card, 0, $H - 46, $W, $H, $gold);

    $cx = intdiv($W, 2);
    // Decorative certificate art (seal + divider) when bundled.
    $certArt = dirname(__DIR__) . '/public/assets/img/cert';
    _cert_paste_center($card, "$certArt/cert-divider.png", $cx, 405, 300, 20);
    _cert_paste_center($card, "$certArt/cert-seal.png", $cx, 610, 96, 96);

    _cert_text($card, 'This certifies that', 22, $cx, 150, $soft, false, 'center');
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: (string) ($user['email'] ?? '');
    _cert_text($card, $name, 52, $cx, 235, $navy, true, 'center', $W - 120);
    _cert_text($card, 'has completed', 22, $cx, 300, $soft, false, 'center');
    _cert_text($card, $course['title'] ?? 'Course', 34, $cx, 365, $ink, true, 'center', $W - 140);

    $date = date('F j, Y', strtotime($issuedAt) ?: time());
    _cert_text($card, 'on ' . $date, 20, $cx, 435, $soft, false, 'center');

    $cpe = (float) ($course['cpe_hours'] ?? 0);
    $gt  = (float) ($course['gt_hours'] ?? 0);
    if ($cpe > 0 || $gt > 0) {
        $parts = [];
        if ($cpe > 0) { $h = rtrim(rtrim(number_format($cpe, 2), '0'), '.'); $parts[] = "$h " . ($cpe == 1 ? 'CPE credit hour' : 'CPE credit hours'); }
        if ($gt  > 0) { $h = rtrim(rtrim(number_format($gt, 2), '0'), '.');  $parts[] = "$h " . ($gt  == 1 ? 'GT credit hour'  : 'GT credit hours'); }
        _cert_text($card, 'and earned', 20, $cx, 480, $soft, false, 'center');
        _cert_text($card, implode(' + ', $parts) . ' of professional development', 24, $cx, 520, $navy, true, 'center', $W - 140);
    }

    // Signatory block (bottom-right).
    $sig = trim((string) ($cfg['cert_signatory'] ?? ''));
    $title = trim((string) ($cfg['cert_signatory_title'] ?? ''));
    $provider = trim((string) ($cfg['cert_provider'] ?? ''));
    $rx = $W - 90; $sy = 690;
    if ($sig !== '' || $title !== '' || $provider !== '') {
        imagesetthickness($card, 2);
        imageline($card, $W - 380, $sy, $W - 90, $sy, $soft);
        $yy = $sy + 30;
        if ($sig !== '')      { _cert_text($card, $sig, 22, $rx, $yy, $ink, true, 'right'); $yy += 34; }
        if ($title !== '')    { _cert_text($card, $title, 18, $rx, $yy, $soft, false, 'right'); $yy += 30; }
        if ($provider !== '') { _cert_text($card, $provider, 18, $rx, $yy, $soft, false, 'right'); }
    }

    // Org (bottom-left) + logo if available.
    _cert_text($card, strtoupper((string) ($cfg['org_name'] ?? '')), 18, 90, 720, $navy, true, 'left', (int)($W / 2));
    $logoName = function_exists('setting') ? setting('logo', '') : '';
    if ($logoName !== '' && is_file($cfg['data_dir'] . '/branding/' . $logoName)) {
        _cert_paste_logo($card, $cfg['data_dir'] . '/branding/' . $logoName, 90, 630, 120, 60);
    }

    // Footer website + verification code.
    $site = trim((string) ($cfg['cert_website'] ?? ''));
    if ($site !== '') _cert_text($card, $site, 16, $cx, $H - 16, imagecolorallocate($card, 255, 255, 255), true, 'center');
    _cert_text($card, 'Verification code: ' . $code, 13, $cx, 800, $soft, false, 'center');

    $dir = ($cfg['data_dir'] ?? sys_get_temp_dir()) . '/tmp';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/cert-' . $code . '.png';
    imagepng($card, $file);
    imagedestroy($card);
    return $file;
}

// Aligned text (left|center|right at $x), TTF if available with auto-shrink to $maxW.
function _cert_text($im, string $text, int $size, int $x, int $y, $color, bool $bold, string $align = 'left', int $maxW = 0): void {
    $font = _badge_font($bold);
    if ($font && function_exists('imagettftext')) {
        if ($maxW > 0) {
            $box = imagettfbbox($size, 0, $font, $text); $tw = abs($box[2] - $box[0]);
            while ($tw > $maxW && $size > 10) { $size -= 2; $box = imagettfbbox($size, 0, $font, $text); $tw = abs($box[2] - $box[0]); }
        } else {
            $box = imagettfbbox($size, 0, $font, $text); $tw = abs($box[2] - $box[0]);
        }
        $dx = $align === 'center' ? (int) ($x - $tw / 2) : ($align === 'right' ? (int) ($x - $tw) : $x);
        imagettftext($im, $size, 0, $dx, $y, $color, $font, $text);
    } else {
        $gd = 5; $tw = imagefontwidth($gd) * strlen($text);
        $dx = $align === 'center' ? (int) ($x - $tw / 2) : ($align === 'right' ? (int) ($x - $tw) : $x);
        imagestring($im, $gd, $dx, $y - 12, $text, $color);
    }
}

// Paste a PNG/WebP centered on ($cx,$cy), scaled to fit within $maxW×$maxH
// (preserving aspect + alpha). No-op if the file is missing. Used for the
// decorative certificate seal and divider.
function _cert_paste_center($card, string $path, int $cx, int $cy, int $maxW, int $maxH): void {
    if (!is_file($path)) return;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $src = match ($ext) {
        'png' => @imagecreatefrompng($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        'jpg', 'jpeg' => @imagecreatefromjpeg($path), default => null,
    };
    if (!$src) return;
    $sw = imagesx($src); $sh = imagesy($src);
    $sc = min($maxW / $sw, $maxH / $sh); $dw = (int) ($sw * $sc); $dh = (int) ($sh * $sc);
    imagealphablending($card, true);
    imagecopyresampled($card, $src, (int) ($cx - $dw / 2), (int) ($cy - $dh / 2), 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
}

function _cert_paste_logo($card, string $path, int $x, int $y, int $maxW, int $maxH): void {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $src = match ($ext) {
        'png' => @imagecreatefrompng($path), 'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null, default => null,
    };
    if (!$src) return;
    $sw = imagesx($src); $sh = imagesy($src);
    $sc = min($maxW / $sw, $maxH / $sh); $dw = (int) ($sw * $sc); $dh = (int) ($sh * $sc);
    imagealphablending($card, true);
    imagecopyresampled($card, $src, $x, $y, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
}

// Draws centered text. Uses a bundled TrueType font if available, else the
// built-in GD bitmap font (still legible, just less refined).
function _badge_text_centered($im, string $text, int $size, int $y, $color, bool $bold, int $width = 600): void {
    $font = _badge_font($bold);
    if ($font && function_exists('imagettftext')) {
        // Shrink to fit within the card width (keeps ~48px side margins) so long
        // course titles and names never overflow the certificate.
        $maxW = $width - 96;
        $box = imagettfbbox($size, 0, $font, $text);
        $textW = abs($box[2] - $box[0]);
        while ($textW > $maxW && $size > 12) {
            $size -= 2;
            $box = imagettfbbox($size, 0, $font, $text);
            $textW = abs($box[2] - $box[0]);
        }
        $x = (int)(($width - $textW) / 2);
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    } else {
        $gdFont = 5;
        $textW = imagefontwidth($gdFont) * strlen($text);
        $x = (int)(($width - $textW) / 2);
        imagestring($im, $gdFont, $x, $y - 12, $text, $color);
    }
}

function _badge_font(bool $bold): ?string {
    // Look for common DejaVu fonts installed on most Linux servers.
    $candidates = $bold
        ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
           '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf']
        : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
           '/usr/share/fonts/dejavu/DejaVuSans.ttf'];
    $bundled = dirname(__DIR__) . '/public/assets/fonts/' . ($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf');
    array_unshift($candidates, $bundled);
    foreach ($candidates as $f) {
        if (is_file($f)) return $f;
    }
    return null;
}
