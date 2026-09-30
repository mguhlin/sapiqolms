<?php
// SCORM 1.2 / 2004 support: import a .zip package, extract it into a course
// folder, and generate a player page whose window.API / API_1484_11 shim maps
// the package's completion + score back to Sapiqo progress. No XML/Zip extension
// needed — PharData reads the zip and the manifest is parsed with regex.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/creator.php';     // cr_slugify()
require_once __DIR__ . '/course_io.php';   // io_tmp_dir(), _copy_tree(), _rmtree()
require_once __DIR__ . '/cc_import.php';   // _cc_attr()

// Import a SCORM package. Returns [ok(bool), slugOrError(string), report(array)].
function import_scorm(string $tmpFile, string $origName): array {
    $report = ['version' => '', 'entry' => ''];
    $work = io_tmp_dir('scorm-');
    try {
        $staged = $work . '/pkg.zip';
        copy($tmpFile, $staged);
        if (function_exists('archive_entries_safe') && !archive_entries_safe($staged)) return [false, 'The archive contains unsafe file paths.', $report];
        try { $p = new PharData($staged); $p->extractTo($work, null, true); }
        catch (Throwable $e) { return [false, 'Could not open the SCORM .zip: ' . $e->getMessage(), $report]; }
        @unlink($staged);

        // Manifest may sit at root or one level deep.
        $manifest = is_file("$work/imsmanifest.xml") ? "$work/imsmanifest.xml" : null;
        if (!$manifest) {
            foreach (glob("$work/*", GLOB_ONLYDIR) ?: [] as $sub) {
                if (is_file("$sub/imsmanifest.xml")) { $manifest = "$sub/imsmanifest.xml"; break; }
            }
        }
        if (!$manifest) return [false, 'No imsmanifest.xml found — is this a SCORM package?', $report];
        $pkgRoot = dirname($manifest);
        $xml = (string) file_get_contents($manifest);

        // Version: adlcp namespace / schemaversion.
        $version = (stripos($xml, 'adlcp_rootv1p2') !== false || preg_match('/<schemaversion>\s*1\.2/i', $xml))
            ? '1.2' : '2004';

        // Title.
        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $xml, $tm)) {
            $title = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8'));
        }
        if ($title === '') $title = pathinfo($origName, PATHINFO_FILENAME);

        // Launch href: prefer the SCO resource, else the first resource with href,
        // honoring the default organization's first item -> resource mapping.
        $entry = _scorm_launch_href($xml);
        if ($entry === '') return [false, 'Could not find a launchable SCO in the manifest.', $report];

        $slug = _scorm_unique_slug(cr_slugify($title));
        $courseDir = rtrim(lms_config()['courses_dir'], '/') . '/' . $slug;
        // Copy the package payload under scorm/.
        _copy_tree($pkgRoot, $courseDir . '/scorm');

        $course = [
            'slug' => $slug, 'title' => $title, 'provider' => lms_config()['org_name'] ?? '',
            'type' => 'scorm', 'tagline' => '', 'tags' => [], 'overview_html' => '',
            'scorm' => ['version' => $version, 'entry' => 'scorm/' . ltrim($entry, '/')],
            'stats' => ['modules' => 1, 'core_modules' => 1, 'lessons' => 1, 'topics' => 0, 'videos' => 0, 'quizzes' => 0, 'units' => 1],
            'modules' => [],
        ];
        file_put_contents($courseDir . '/course.json',
            json_encode($course, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        scorm_write_player($courseDir, $title, $version, 'scorm/' . ltrim($entry, '/'));

        $report['version'] = $version; $report['entry'] = $entry;
        return [true, $slug, $report];
    } catch (Throwable $e) {
        return [false, 'SCORM import failed: ' . $e->getMessage(), $report];
    } finally {
        _rmtree($work);
    }
}

function _scorm_launch_href(string $xml): string {
    // Map resource identifier -> href.
    $res = [];
    if (preg_match_all('#<resource\b([^>]*)>#is', $xml, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $r) {
            $id = _cc_attr($r[1], 'identifier');
            $href = _cc_attr($r[1], 'href');
            $type = _cc_attr($r[1], 'adlcp:scormtype') ?: _cc_attr($r[1], 'scormtype');
            if ($id !== '' && $href !== '') $res[$id] = ['href' => $href, 'sco' => strtolower($type) === 'sco'];
        }
    }
    // First item's identifierref in the default organization.
    if (preg_match('#<organization\b[^>]*>(.*?)</organization>#is', $xml, $om)) {
        if (preg_match('#<item\b[^>]*\bidentifierref="([^"]+)"#is', $om[1], $im)) {
            $ref = $im[1];
            if (isset($res[$ref])) return $res[$ref]['href'];
        }
    }
    // Else first SCO resource, else first resource with an href.
    foreach ($res as $r) if ($r['sco']) return $r['href'];
    foreach ($res as $r) return $r['href'];
    return '';
}

function _scorm_unique_slug(string $slug): string {
    $slug = $slug ?: 'scorm-course';
    $root = rtrim(lms_config()['courses_dir'], '/');
    $s = $slug; $n = 2;
    while (is_dir("$root/$s")) { $s = $slug . '-' . $n; $n++; }
    return $s;
}

// Write the player index.html: a SCORM run-time shim + iframe launcher that
// reports completion/score to the LMS (or no-ops when served standalone).
function scorm_write_player(string $outDir, string $title, string $version, string $entry): void {
    $t = htmlspecialchars($title, ENT_QUOTES);
    $entryJs = json_encode($entry, JSON_UNESCAPED_SLASHES);
    $verJs = json_encode($version);
    file_put_contents($outDir . '/index.html', <<<HTML
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>$t</title>
<style>
  html,body{margin:0;height:100%;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;background:#0b2e5b}
  .bar{display:flex;align-items:center;gap:12px;padding:10px 16px;background:#0b2e5b;color:#fff}
  .bar a{color:#ffdb7d;text-decoration:none;font-weight:600}
  .bar .status{margin-left:auto;font-size:.9rem;color:#cfe0f6}
  iframe{border:0;width:100%;height:calc(100vh - 44px);background:#fff;display:block}
  .toast{position:fixed;right:18px;bottom:18px;background:#fff;border-left:5px solid #f4b41a;border-radius:10px;
    padding:14px 18px;box-shadow:0 8px 30px rgba(0,0,0,.25);max-width:280px}
  .toast strong{display:block;color:#0b2e5b}
</style></head><body>
<div class="bar"><a href="../index.html">← All courses</a><span>$t</span>
  <span class="status" id="scormStatus">SCORM $version</span></div>
<iframe id="scoFrame" title="$t"></iframe>
<script>
(function(){
  var ENTRY=$entryJs, VERSION=$verJs;
  var slug=(location.pathname.match(/\\/courses\\/([^\\/]+)\\//)||[])[1]||"";
  var cmi={}, done=false;
  var LMS={enabled:false,base:null,csrf:null};
  function apiBase(){ try{return new URL("../../api/",location.href).href;}catch(e){return null;} }
  function init(){
    LMS.base=apiBase();
    if(!LMS.base) return;
    fetch(LMS.base+"whoami",{credentials:"same-origin"}).then(function(r){return r.ok?r.json():null;})
      .then(function(d){ if(d&&d.authenticated){LMS.enabled=true;LMS.csrf=d.csrf;} }).catch(function(){});
  }
  function status(s){ document.getElementById("scormStatus").textContent=s; }
  function report(){
    if(done) return;
    var st=(cmi["cmi.core.lesson_status"]||cmi["cmi.completion_status"]||cmi["cmi.success_status"]||"").toLowerCase();
    var passed = st==="completed"||st==="passed";
    if(!passed) return;
    done=true; status("Completed ✓");
    var raw=parseFloat(cmi["cmi.core.score.raw"]);
    var scaled=parseFloat(cmi["cmi.score.scaled"]);
    var score = !isNaN(raw)?Math.round(raw) : (!isNaN(scaled)?Math.round(scaled*100):100);
    if(!LMS.enabled){ try{localStorage.setItem("scorm::"+slug,"done");}catch(e){} celebrate(null); return; }
    fetch(LMS.base+"scorm",{method:"POST",credentials:"same-origin",
      headers:{"Content-Type":"application/json","X-CSRF-Token":LMS.csrf||""},
      body:JSON.stringify({course:slug,status:"completed",score:score})})
      .then(function(r){return r.ok?r.json():null;})
      .then(function(res){ if(res&&res.badge_url) celebrate(res.badge_url); }).catch(function(){});
  }
  function celebrate(url){
    var t=document.createElement("div"); t.className="toast";
    t.innerHTML="<strong>🎉 Course complete!</strong>"+(url?'<a href="'+url+'">Download your badge</a>':"Your progress was saved.");
    document.body.appendChild(t);
  }
  // --- SCORM 1.2 API ---
  var API={
    LMSInitialize:function(){return "true";},
    LMSFinish:function(){report();return "true";},
    LMSGetValue:function(k){return cmi[k]!=null?String(cmi[k]):"";},
    LMSSetValue:function(k,v){cmi[k]=v; return "true";},
    LMSCommit:function(){report();return "true";},
    LMSGetLastError:function(){return "0";},
    LMSGetErrorString:function(){return "";},
    LMSGetDiagnostic:function(){return "";}
  };
  // --- SCORM 2004 API ---
  var API_1484_11={
    Initialize:function(){return "true";},
    Terminate:function(){report();return "true";},
    GetValue:function(k){return cmi[k]!=null?String(cmi[k]):"";},
    SetValue:function(k,v){cmi[k]=v; return "true";},
    Commit:function(){report();return "true";},
    GetLastError:function(){return "0";},
    GetErrorString:function(){return "";},
    GetDiagnostic:function(){return "";}
  };
  window.API=API; window.API_1484_11=API_1484_11;   // discoverable by the SCO
  init();
  document.getElementById("scoFrame").src=ENTRY;
})();
</script>
</body></html>
HTML);
}

// Trusted shell generated at request time, independent of package HTML.
function scorm_player_html(array $data): string {
    $entry = (string)($data['scorm']['entry'] ?? '');
    if (!preg_match('#^scorm/[A-Za-z0-9_./%?=&+~\-]+$#D', $entry) || str_contains(rawurldecode($entry), '..')) throw new RuntimeException('Invalid SCORM launch path.');
    $config = htmlspecialchars(json_encode(['entry'=>$entry, 'course'=>$data['slug']], JSON_THROW_ON_ERROR), ENT_QUOTES);
    $title = htmlspecialchars((string)$data['title'], ENT_QUOTES);
    $script = htmlspecialchars(url('/assets/js/scorm-player.js'), ENT_QUOTES);
    $dashboard = htmlspecialchars(url('/dashboard'), ENT_QUOTES);
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title></head><body><header><a href="'.$dashboard.'">Dashboard</a><h1>'.$title.'</h1><p id="scorm-status" role="status">Preparing course…</p></header><main id="scorm-player" data-config="'.$config.'"><iframe id="scorm-frame" title="'.$title.'" sandbox="allow-scripts" style="width:100%;height:80vh;border:0"></iframe></main><script src="'.$script.'"></script></body></html>';
}
