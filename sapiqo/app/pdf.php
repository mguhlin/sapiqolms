<?php
// Minimal, dependency-free PDF generation for printable certificates. Renders
// GD images to JPEG and embeds them (DCTDecode), one per Letter-landscape page —
// no Composer, FPDF, or external binaries required.

declare(strict_types=1);

// Read an image path into [jpegBytes, width, height] (flattened onto white).
function _pdf_jpeg(string $path): ?array {
    if (!is_file($path)) return null;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $img = match ($ext) {
        'png'         => @imagecreatefrompng($path),
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        'gif'         => @imagecreatefromgif($path),
        default       => null,
    };
    if (!$img) return null;
    $w = imagesx($img); $h = imagesy($img);
    $canvas = imagecreatetruecolor($w, $h);
    imagefilledrectangle($canvas, 0, 0, $w, $h, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
    ob_start(); imagejpeg($canvas, null, 88); $jpeg = ob_get_clean();
    imagedestroy($img); imagedestroy($canvas);
    return ($jpeg === false || $jpeg === '') ? null : [$jpeg, $w, $h];
}

// Build a multi-page PDF: each image path becomes one centered Letter-landscape
// page. Returns the PDF bytes, or null if nothing could be read.
function pdf_from_images(array $paths): ?string {
    $pageW = 792.0; $pageH = 612.0; $margin = 30.0;
    $imgs = [];
    foreach ($paths as $p) { $j = _pdf_jpeg($p); if ($j) $imgs[] = $j; }
    if (!$imgs) return null;

    // Object numbering: 1=Catalog, 2=Pages, then per page: page, content, image.
    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $kids = [];
    $n = 2;
    foreach ($imgs as [$jpeg, $iw, $ih]) {
        $pageObj = ++$n; $contentObj = ++$n; $imgObj = ++$n;
        $kids[] = "$pageObj 0 R";
        $scale = min(($pageW - 2 * $margin) / $iw, ($pageH - 2 * $margin) / $ih);
        $dw = $iw * $scale; $dh = $ih * $scale;
        $tx = ($pageW - $dw) / 2; $ty = ($pageH - $dh) / 2;
        $content = sprintf("q\n%.2f 0 0 %.2f %.2f %.2f cm\n/Im0 Do\nQ\n", $dw, $dh, $tx, $ty);
        $objects[$pageObj] = sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0f %.0f] "
            . "/Resources << /XObject << /Im0 %d 0 R >> >> /Contents %d 0 R >>",
            $pageW, $pageH, $imgObj, $contentObj);
        $objects[$contentObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        $objects[$imgObj] = "<< /Type /XObject /Subtype /Image /Width $iw /Height $ih "
            . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
            . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
    }
    $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($imgs) . " >>";

    $total = $n;
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    for ($i = 1; $i <= $total; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= "$i 0 obj\n" . $objects[$i] . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . ($total + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $total; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $pdf .= "trailer\n<< /Size " . ($total + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
    return $pdf;
}

// Backwards-compatible single-image helper.
function pdf_from_image(string $imagePath): ?string {
    return pdf_from_images([$imagePath]);
}
