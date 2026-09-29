<?php
// Export a course as an IMS Common Cartridge 1.1 (.imscc) package so it can
// be imported into Canvas, Blackboard, Moodle, Sakai, etc. Lessons become HTML
// web-content resources; quizzes become QTI 1.2 assessments; media is bundled.
//
// This is generation only (no XML parser needed). Uses PharData to write the zip.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/course_io.php';   // io_tmp_dir()

function xmle(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

// Build the .imscc for $slug in $outDir. Returns the file path.
function export_common_cartridge(string $slug, string $outDir): string {
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) throw new RuntimeException('Invalid slug.');
    $courseDir = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
    $jsonPath = "$courseDir/course.json";
    if (!is_file($jsonPath)) throw new RuntimeException("No course found for “$slug”.");
    $course = json_decode((string) file_get_contents($jsonPath), true) ?: [];
    if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) throw new RuntimeException("Cannot create $outDir");

    $files = [];        // archive-path => file contents (string) OR ['src'=>absolutePath]
    $orgItems = [];     // organization item XML
    $resources = [];    // resource XML

    $ri = 0;
    foreach ($course['modules'] ?? [] as $m) {
        $modTitle = $m['title'] ?? ($m['label'] ?? 'Module');
        $childXml = '';
        foreach ($m['lessons'] ?? [] as $l) {
            $ri++;
            $resId = 'R_' . $ri; $itemId = 'I_' . $ri;
            $htmlName = 'content/lesson_' . $ri . '.html';
            $files[$htmlName] = cc_lesson_html($l, $course['title'] ?? $slug);
            $childXml .= '      <item identifier="' . $itemId . '" identifierref="' . $resId . '">'
                       . '<title>' . xmle($l['title'] ?? 'Lesson') . "</title></item>\n";
            $resources[] = '  <resource identifier="' . $resId . '" type="webcontent" href="' . $htmlName . '">'
                         . '<file href="' . $htmlName . '"/></resource>';

            // Quiz -> QTI assessment resource.
            if (!empty($l['quiz']['questions'])) {
                $ri++;
                $qResId = 'R_' . $ri; $qItemId = 'I_' . $ri;
                $qtiName = 'assessments/quiz_' . $ri . '.xml';
                $files[$qtiName] = cc_qti_xml($l['quiz'], ($l['title'] ?? 'Lesson') . ' — Knowledge Check');
                $childXml .= '      <item identifier="' . $qItemId . '" identifierref="' . $qResId . '">'
                           . '<title>' . xmle(($l['title'] ?? 'Lesson') . ' — Quiz') . "</title></item>\n";
                $resources[] = '  <resource identifier="' . $qResId
                             . '" type="imsqti_xmlv1p2/imscc_xmlv1p1/assessment" href="' . $qtiName . '">'
                             . '<file href="' . $qtiName . '"/></resource>';
            }
        }
        $orgItems[] = '    <item identifier="M_' . md5($modTitle) . '"><title>' . xmle($modTitle) . "</title>\n"
                    . $childXml . '    </item>';
    }

    // Bundle media (images, videos, captions) referenced by lessons.
    if (is_dir("$courseDir/media")) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator("$courseDir/media", FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            $rel = 'media/' . str_replace('\\', '/', ltrim(substr($f->getPathname(), strlen("$courseDir/media")), '/\\'));
            $rid = 'RM_' . substr(md5($rel), 0, 10);
            $files[$rel] = ['src' => $f->getPathname()];
            $resources[] = '  <resource identifier="' . $rid . '" type="webcontent" href="' . $rel . '">'
                         . '<file href="' . $rel . '"/></resource>';
        }
    }

    // Short course description for LOM metadata (helps the target LMS show a blurb).
    $desc = trim((string) ($course['tagline'] ?? ''));
    if ($desc === '' && !empty($course['overview_html'])) {
        $desc = trim(mb_substr(strip_tags((string) $course['overview_html']), 0, 400));
    }
    $manifest = cc_manifest_xml($slug, $course['title'] ?? $slug, $orgItems, $resources, $desc);
    $files['imsmanifest.xml'] = $manifest;

    // Write the .imscc (zip). Neutral, unbranded filename so the target LMS names
    // the import by the course, not the source platform.
    $out = rtrim($outDir, '/') . "/$slug-cc-" . date('Ymd-His') . '.imscc';
    @unlink($out);
    $zip = new PharData($out, 0, null, Phar::ZIP);
    foreach ($files as $path => $content) {
        if (is_array($content)) $zip->addFile($content['src'], $path);
        else $zip->addFromString($path, $content);
    }
    unset($zip);
    return $out;
}

function cc_manifest_xml(string $slug, string $title, array $orgItems, array $resources, string $description = ''): string {
    $org = implode("\n", $orgItems);
    $res = implode("\n", $resources);
    $descXml = $description !== ''
        ? "<lom:description><lom:string>" . xmle($description) . "</lom:string></lom:description>"
        : '';
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<manifest identifier="course-' . xmle($slug) . '" '
        . 'xmlns="http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1" '
        . 'xmlns:lom="http://ltsc.ieee.org/xsd/imsccv1p1/LOM/resource" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
        . 'xsi:schemaLocation="http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1 '
        . 'http://www.imsglobal.org/profile/cc/ccv1p1/ccv1p1_imscp_v1p2_v1p0.xsd">' . "\n"
        . "  <metadata><schema>IMS Common Cartridge</schema><schemaversion>1.1.0</schemaversion>"
        . "<lom:lom><lom:general><lom:title><lom:string>" . xmle($title)
        . "</lom:string></lom:title>" . $descXml . "</lom:general></lom:lom></metadata>\n"
        . '  <organizations><organization identifier="org1" structure="rooted-hierarchy">' . "\n"
        . '    <item identifier="root">' . "\n" . $org . "\n    </item>\n"
        . "  </organization></organizations>\n"
        . "  <resources>\n" . $res . "\n  </resources>\n"
        . '</manifest>';
}

// Self-contained lesson HTML (content already sanitized at build time).
function cc_lesson_html(array $lesson, string $courseTitle): string {
    $title = xmle($lesson['title'] ?? 'Lesson');
    $body = $lesson['content'] ?? '';
    return "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"UTF-8\">"
        . "<title>$title</title></head><body>\n<h1>$title</h1>\n" . $body . "\n</body></html>";
}

// QTI 1.2 assessment for a quiz (multiple choice + multiple response).
function cc_qti_xml(array $quiz, string $title): string {
    $items = '';
    foreach ($quiz['questions'] ?? [] as $qi => $q) {
        $ident = 'q' . $qi;
        $multiple = !empty($q['multiple']);
        $card = $multiple ? 'Multiple' : 'Single';
        $responses = ''; $correct = [];
        foreach ($q['options'] ?? [] as $oi => $o) {
            $rid = 'q' . $qi . '_o' . $oi;
            $responses .= '<response_label ident="' . $rid . '"><material><mattext texttype="text/plain">'
                . xmle($o['text'] ?? '') . '</mattext></material></response_label>';
            if (!empty($o['correct'])) $correct[] = $rid;
        }
        $condvars = '';
        foreach ($correct as $rid) $condvars .= '<varequal respident="response_' . $ident . '">' . $rid . '</varequal>';
        if ($multiple && count($correct) > 1) $condvars = '<and>' . $condvars . '</and>';
        $items .= '<item ident="' . $ident . '" title="' . xmle(mb_substr($q['text'] ?? 'Question', 0, 80)) . '">'
            . '<presentation><material><mattext texttype="text/plain">' . xmle($q['text'] ?? '') . '</mattext></material>'
            . '<response_lid ident="response_' . $ident . '" rcardinality="' . $card . '">'
            . '<render_choice>' . $responses . '</render_choice></response_lid></presentation>'
            . '<resprocessing><outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>'
            . '<respcondition continue="No"><conditionvar>' . $condvars . '</conditionvar>'
            . '<setvar action="Set" varname="SCORE">100</setvar></respcondition></resprocessing></item>';
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<questestinterop xmlns="http://www.imsglobal.org/xsd/ims_qtiasiv1p2">'
        . '<assessment ident="assessment1" title="' . xmle($title) . '">'
        . '<section ident="root_section">' . $items . '</section></assessment></questestinterop>';
}
