<?php
// Sapiqo front controller. Pure PHP, no framework. Works with pretty URLs
// (via .htaccess rewrite) and with the PHP built-in server (router.php).

declare(strict_types=1);

$APP = dirname(__DIR__) . '/app';
require_once $APP . '/config.php';
require_once $APP . '/helpers.php';
require_once $APP . '/db.php';
require_once $APP . '/settings.php';
require_once $APP . '/i18n.php';
require_once $APP . '/notifications.php';
require_once $APP . '/security.php';
require_once $APP . '/mailer.php';
require_once $APP . '/auth.php';
require_once $APP . '/account_security.php';
require_once $APP . '/avatar.php';
require_once $APP . '/courses.php';
require_once $APP . '/codes.php';
require_once $APP . '/quiz.php';
require_once $APP . '/discovery.php';
require_once $APP . '/groups.php';
require_once $APP . '/orgs.php';
require_once $APP . '/csv.php';
require_once $APP . '/oneroster.php';
require_once $APP . '/reports.php';
require_once $APP . '/api.php';
require_once $APP . '/lti.php';
require_once $APP . '/pdf.php';
require_once $APP . '/backup.php';
require_once $APP . '/course_io.php';
require_once $APP . '/cc_export.php';
require_once $APP . '/cc_import.php';
require_once $APP . '/scorm.php';
require_once $APP . '/learndash_import.php';
require_once $APP . '/creator.php';
require_once $APP . '/learning.php';
require_once $APP . '/forum.php';
require_once $APP . '/editor.php';
require_once $APP . '/gradebook.php';
require_once $APP . '/assignments.php';
require_once $APP . '/help.php';

// Production error posture: log, don't display, unless config 'debug' is on.
if (empty(lms_config()['debug'])) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

send_security_headers();
auth_boot();
ensure_schema();   // idempotent; runs new migrations once after an upgrade

// --- Resolve the route path -------------------------------------------------
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$bp = base_path();
if ($bp && str_starts_with($uri, $bp)) {
    $uri = substr($uri, strlen($bp));
}
$path = '/' . trim(rawurldecode($uri), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// A POST whose body exceeded post_max_size arrives with $_POST/$_FILES empty
// (PHP silently drops it), which otherwise surfaces as a confusing CSRF error.
// Detect it and explain the real cause + fix.
if ($method === 'POST' && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $cl = (int) $_SERVER['CONTENT_LENGTH'];
    $max = ini_bytes((string) ini_get('post_max_size'));
    if ($max > 0 && $cl > $max) {
        http_response_code(413);
        $mb = round($cl / 1048576);
        $msg = "That upload (~{$mb} MB) is larger than this server currently allows "
            . "(upload_max_filesize " . ini_get('upload_max_filesize') . ", post_max_size " . ini_get('post_max_size') . "). "
            . "Raise those PHP limits (see installer/README), or import a very large course by copying its folder into "
            . "content/ and clicking Rescan, or via Export → Split parts.";
        view('error', ['code' => 413, 'message' => $msg], 'Upload too large');
        exit;
    }
}

// Serve course static files (reader, media, captions, video) via a controlled
// passthrough so the private sapiqo/ and sapiqo-data/ folders (in the same
// courses/ project) are never web-reachable.
if (str_starts_with($path, '/courses/')) {
    require_once $APP . '/serve.php';
    serve_course(substr($path, strlen('/courses/')));
}

// --- Tiny router ------------------------------------------------------------
$routes = [];
function route(string $method, string $pattern, callable $handler): void {
    global $routes;
    $routes[] = [$method, $pattern, $handler];
}
function dispatch(string $method, string $path): void {
    global $routes;
    foreach ($routes as [$m, $pattern, $handler]) {
        if ($m !== $method) continue;
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (preg_match($regex, $path, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler($params);
            return;
        }
    }
    http_response_code(404);
    if (want_json()) json_out(['error' => 'Not found'], 404);
    view('error', ['code' => 404, 'message' => 'Page not found.'], 'Not found');
}

require $APP . '/routes.php';
require $APP . '/account_routes.php';
require $APP . '/learning_routes.php';
require $APP . '/assignment_routes.php';

dispatch($method, $path);
