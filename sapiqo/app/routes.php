<?php
// Route definitions + handlers. Included by public/index.php.

declare(strict_types=1);

// --- Home / auth -------------------------------------------------------------

route('GET', '/', function () {
    if (is_logged_in()) redirect('/dashboard');
    $courses = public_courses();   // full standalone page (its own chrome)
    require dirname(__DIR__) . '/app/views/splash.php';
});

route('GET', '/login', function () {
    if (is_logged_in()) redirect('/dashboard');
    view('login', ['providers' => sso_providers()], 'Sign in');
});

route('POST', '/login', function () {
    csrf_check();
    $email = input('email');

    [$blocked, $retry] = login_is_blocked($email);
    if ($blocked) {
        audit('login.blocked', ['actor_email' => strtolower($email), 'detail' => "retry_after={$retry}s"]);
        $mins = max(1, (int) ceil($retry / 60));
        flash("Too many sign-in attempts. Please wait about $mins minute(s) and try again.", 'error');
        redirect('/login');
    }

    $user = verify_login($email, (string) ($_POST['password'] ?? ''));
    if (!$user) {
        login_record_attempt($email, false);
        audit('login.fail', ['actor_email' => strtolower(trim($email))]);
        flash('That email and password did not match.', 'error');
        redirect('/login');
    }
    login_record_attempt($email, true);
    login_clear_failures($email);
    login_user($user);
    audit('login.success', ['actor_id' => (int) $user['id'], 'actor_email' => $user['email']]);
    if ($r = code_apply_pending((int) $user['id'])) {
        flash('Code applied — you now have access to: ' . implode(', ', $r['courses']) . '.');
    }
    $to = $_SESSION['after_login'] ?? '/dashboard';
    unset($_SESSION['after_login']);
    redirect($to);
});

route('GET', '/register', function () {
    if (is_logged_in()) redirect('/dashboard');
    if (!registration_allowed()) {
        flash('Public sign-up is disabled. Ask your administrator for an account, or redeem your enrollment code first.', 'error');
        redirect('/login');
    }
    $codePrefill = input('code') ?: (string) ($_SESSION['pending_code'] ?? '');
    view('register', ['courses' => all_courses(), 'code' => $codePrefill, 'providers' => sso_providers()], 'Create your account');
});

route('POST', '/register', function () {
    csrf_check();
    if (!registration_allowed()) redirect('/login');
    [$ok, $res] = register_local([
        'first_name'   => input('first_name'),
        'last_name'    => input('last_name'),
        'email'        => input('email'),
        'phone'        => input('phone'),
        'password'     => (string) ($_POST['password'] ?? ''),
        'user_type'    => input('user_type'),
        'campus'       => input('campus'),
        'organization' => input('organization'),
        'role'         => 'learner',
    ]);
    if (!$ok) {
        flash((string) $res, 'error');
        redirect('/register');
    }
    $userId = (int) $res;
    $courseRef = input('course');
    if ($courseRef !== '') {
        $c = resolve_course($courseRef);
        if ($c && course_access_error($userId, $c) === null) enroll($userId, (int) $c['id']);
    }
    // Optional enrollment code typed at sign-up (or carried from a /redeem link).
    $codeInput = input('code');
    if ($codeInput !== '') $_SESSION['pending_code'] = $codeInput;
    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    audit('user.register', ['actor_id' => $userId, 'actor_email' => $user['email']]);
    if (mail_enabled()) {
        $name = trim($user['first_name'] . ' ' . $user['last_name']) ?: 'there';
        send_mail($user['email'], 'Welcome to ' . lms_config()['app_name'],
            "Hi $name,\n\nYour account is ready. Sign in any time to take courses and earn badges:\n"
            . base_url_absolute() . "/login\n");
    }
    login_user($user);
    if ($r = code_apply_pending($userId)) {
        flash('Welcome! Your code enrolled you in: ' . implode(', ', $r['courses']) . '.');
    } else {
        flash('Welcome to ' . lms_config()['app_name'] . '! Your account is ready.');
    }
    redirect('/dashboard');
});

// --- Password reset (self-service) ------------------------------------------

route('GET', '/forgot', function () {
    if (is_logged_in()) redirect('/dashboard');
    view('forgot', ['sent' => false, 'mail_on' => mail_enabled()], 'Reset your password');
});

route('POST', '/forgot', function () {
    csrf_check();
    $email = strtolower(input('email'));
    // Throttle reset requests by IP (reuse login limiter's IP window).
    [$blocked] = login_is_blocked($email);
    if (!$blocked) login_record_attempt($email, false);
    $user = valid_email($email) ? db_one('SELECT * FROM users WHERE email = ?', [$email]) : null;
    if ($user && !$blocked) {
        $token = create_reset_token((int) $user['id']);
        $link = base_url_absolute() . '/reset?token=' . $token;
        audit('password.reset_requested', ['actor_id' => (int) $user['id'], 'actor_email' => $email]);
        if (mail_enabled()) {
            send_mail($email, lms_config()['app_name'] . ' — password reset',
                "Hello,\n\nWe received a request to reset your password.\n\n"
                . "Open this link to choose a new password (valid for 1 hour):\n$link\n\n"
                . "If you didn't request this, you can ignore this email.\n");
        } else {
            // No mailer configured: log the link so an on-prem admin can deliver it.
            error_log("[password reset] $email -> $link");
        }
    }
    // Always show the same message (no account enumeration).
    view('forgot', ['sent' => true, 'mail_on' => mail_enabled()], 'Reset your password');
});

route('GET', '/reset', function () {
    if (is_logged_in()) redirect('/dashboard');
    $user = user_for_reset_token(input('token'));
    view('reset', ['valid' => (bool) $user, 'token' => input('token')], 'Choose a new password');
});

route('POST', '/reset', function () {
    csrf_check();
    $token = input('token');
    $user = user_for_reset_token($token);
    if (!$user) {
        flash('That reset link is invalid or has expired. Please request a new one.', 'error');
        redirect('/forgot');
    }
    $pw = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password2'] ?? '');
    if (strlen($pw) < 8) { flash('Use at least 8 characters.', 'error'); redirect('/reset?token=' . urlencode($token)); }
    if ($pw !== $pw2) { flash('The two passwords did not match.', 'error'); redirect('/reset?token=' . urlencode($token)); }
    if (!reset_password_once($token, $pw)) {
        flash('This reset link has expired or was already used.', 'error'); redirect('/forgot');
    }
    login_clear_failures($user['email']);
    audit('password.reset_done', ['actor_id' => (int) $user['id'], 'actor_email' => $user['email']]);
    flash('Your password has been reset. Please sign in.');
    redirect('/login');
});

// Switch UI language (persists in a cookie), then return where the user was.
route('GET', '/lang/{code}', function ($p) {
    set_locale_cookie($p['code']);
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Only redirect back to same-host referers; else home.
    if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)
        && in_array(parse_url($ref, PHP_URL_SCHEME), ['http', 'https'], true)) {
        header('Location: ' . $ref);
    } else {
        redirect('/');
    }
    exit;
});

route('GET', '/logout', function () {
    if (is_logged_in()) { $u = current_user(); audit('logout', ['actor_id' => (int) $u['id'], 'actor_email' => $u['email']]); }
    logout_user();
    redirect('/login');
});

// --- SSO --------------------------------------------------------------------

route('GET', '/auth/{provider}', function ($p) {
    sso_begin($p['provider']);
});

route('GET', '/auth/{provider}/callback', function ($p) {
    $code = input('code');
    $state = input('state');
    [$user, $err] = $code ? sso_complete($p['provider'], $code, $state) : [null, ''];
    if (!$user) {
        flash($err !== '' ? $err : 'Single sign-on failed or was cancelled.', 'error');
        redirect('/login');
    }
    if (!empty($_SESSION['sso_linked'])) { unset($_SESSION['sso_linked']); flash('Additional sign-in method linked.'); redirect('/profile/security'); }
    login_user($user);
    if ($r = code_apply_pending((int) $user['id'])) {
        flash('Welcome! Your code enrolled you in: ' . implode(', ', $r['courses']) . '.');
    }
    redirect('/dashboard');
});

// --- Learner ----------------------------------------------------------------

route('GET', '/dashboard', function () {
    require_login();
    $u = current_user();
    $enrollments = enrollments_for((int) $u['id']);
    foreach ($enrollments as &$en) {
        $en['percent'] = course_percent((int) $u['id'], [
            'id' => $en['course_id'], 'total_units' => $en['total_units'],
        ]);
    }
    unset($en);
    view('dashboard', [
        'user'        => $u,
        'enrollments' => $enrollments,
        'badges'      => badges_for((int) $u['id']),
        'catalog'     => all_courses(),
    ], 'Your dashboard');
});

// Learner transcript: completed courses, CPE hours earned, and badges — a
// printable record for professional-development / CPE reporting.
route('GET', '/transcript', function () {
    require_login();
    $u = current_user();
    // Read the snapshot captured when the badge was earned (durable record), and
    // fall back to the live course row for badges issued before snapshots existed.
    // LEFT JOIN so a credential still shows even if its course was later removed.
    $rows = db_all(
        'SELECT b.code, b.issued_at,
                COALESCE(NULLIF(b.title, \'\'), c.title) AS title,
                COALESCE(NULLIF(b.course_slug, \'\'), c.slug) AS slug,
                COALESCE(b.cpe_hours, c.cpe_hours, 0) AS cpe_hours,
                COALESCE(b.gt_hours, c.gt_hours, 0) AS gt_hours
         FROM badges b LEFT JOIN courses c ON c.id = b.course_id
         WHERE b.user_id = ? ORDER BY b.issued_at DESC',
        [(int) $u['id']]
    );
    $totalCpe = array_sum(array_map(fn($r) => (float) $r['cpe_hours'], $rows));
    $totalGt  = array_sum(array_map(fn($r) => (float) $r['gt_hours'], $rows));
    view('transcript', ['user' => $u, 'rows' => $rows, 'total_cpe' => $totalCpe, 'total_gt' => $totalGt], 'Transcript');
});

route('GET', '/profile', function () {
    require_login();
    view('profile', ['user' => current_user()], 'Your profile');
});

// In-app notifications (viewing marks them all read).
route('GET', '/notifications', function () {
    require_login();
    $u = current_user();
    $items = notifications_for((int) $u['id']);
    mark_all_read((int) $u['id']);
    view('notifications', ['items' => $items], 'Notifications');
});

// Serve a user's avatar (any signed-in user may view; avatars are low-sensitivity).
route('GET', '/avatar/{id}', function ($p) {
    require_login();
    $id = (int) $p['id'];
    $file = avatar_path($id);
    if (!is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=300');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
});

route('POST', '/profile/avatar', function () {
    csrf_check();
    require_login();
    $u = current_user();
    if (!empty($_POST['remove'])) {
        delete_avatar((int) $u['id']);
        flash('Photo removed.');
        redirect('/profile');
    }
    if (empty($_FILES['photo']['tmp_name'])) { flash('Choose an image to upload.', 'error'); redirect('/profile'); }
    [$ok, $msg] = save_avatar_upload((int) $u['id'], $_FILES['photo']);
    flash($msg, $ok ? 'success' : 'error');
    redirect('/profile');
});

route('POST', '/profile', function () {
    csrf_check();
    require_login();
    $u = current_user();
    db_run('UPDATE users SET first_name=?, last_name=?, phone=?, campus=?, organization=?, user_type=?, updated_at=? WHERE id=?', [
        input('first_name'), input('last_name'), input('phone'),
        input('campus'), input('organization'), input('user_type'),
        now_utc(), $u['id'],
    ]);
    // Optional password change.
    $new = (string) ($_POST['password'] ?? '');
    if ($new !== '') {
        if (strlen($new) < 8) {
            flash('Password not changed: use at least 8 characters.', 'error');
            redirect('/profile');
        }
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $u['password_hash'])) { flash('Enter your current password to change it.', 'error'); redirect('/profile'); }
        set_user_password((int) $u['id'], $new);
    }
    flash('Your profile has been updated.');
    redirect('/profile');
});

// --- Discussion forum --------------------------------------------------------

// Forum hub: global announcements + the courses whose forums you can access.
route('GET', '/forum', function () {
    require_login();
    $u = current_user();
    // Courses with an accessible forum: enrolled ones for learners; all active
    // for staff. Skip courses whose forum is turned off.
    $courses = forum_is_staff() ? all_courses(true) : array_map(
        fn($e) => $e + ['id' => $e['course_id']], array_filter(enrollments_for((int) $u['id']), fn($e) => (int) $e['active'] === 1));
    $boards = [];
    foreach ($courses as $c) {
        $course = isset($c['slug']) && isset($c['forum_enabled']) ? $c : course_by_slug($c['slug']);
        if ($course && forum_enabled_for($course)) $boards[] = $course;
    }
    // De-dup + sort by title.
    $seen = []; $list = [];
    foreach ($boards as $c) { if (!isset($seen[$c['id']])) { $seen[$c['id']] = 1; $list[] = $c; } }
    usort($list, fn($a, $b) => strcmp($a['title'], $b['title']));
    view('forum/hub', [
        // Admins see the full history, including expired announcements.
        'announcements' => forum_announcements(is_admin()),
        'courses'       => $list,
        'can_announce'  => forum_can_post_announcement(),
        'can_moderate'  => forum_can_moderate(),
        'is_admin_view' => is_admin(),
    ], 'Forum');
});

// Post a global announcement (staff only).
route('POST', '/forum/announce', function () {
    csrf_check();
    require_login();
    if (!forum_can_post_announcement()) { http_response_code(403); exit('Forbidden: only staff can post announcements.'); }
    $u = current_user();
    $body = trim((string) input('body'));
    $title = trim((string) input('title'));
    if ($body === '') { flash('Write an announcement before posting.', 'error'); redirect('/forum'); }
    // Optional expiry: hides the announcement from regular users after this
    // date, but admins can still see it in the history. End-of-day UTC.
    $expRaw = input('expires_at');
    $expires = $expRaw !== '' ? (substr($expRaw, 0, 10) . ' 23:59:59') : null;
    forum_create(['scope' => 'announcement', 'user_id' => (int) $u['id'],
        'title' => $title, 'body' => $body, 'pinned' => !empty($_POST['pinned']), 'expires_at' => $expires]);
    audit('forum.announce');
    flash('Announcement posted.');
    redirect('/forum');
});

// A course's per-module discussion boards.
// Forum index for a course: the discussion boards (forums) grouped by module.
route('GET', '/forum/course/{slug}', function ($p) {
    require_login();
    $course = course_by_slug($p['slug']);
    if (!$course) { http_response_code(404); view('error', ['code'=>404,'message'=>'Course not found.'], 'Not found'); return; }
    if (!forum_can_view_course($course)) {
        view('error', ['code' => 403,
            'message' => forum_enabled_for($course)
                ? 'The discussion forum for this course is open to enrolled participants. Enroll to join the conversation.'
                : 'The discussion forum is turned off for this course.'], 'Forum unavailable');
        return;
    }
    $cid = (int) $course['id'];
    $byModule = [];
    foreach (forum_boards_for_course($p['slug']) as $b) {
        $b['stats'] = forum_board_stats($cid, $b['id']);
        $byModule[$b['module']][] = $b;
    }
    view('forum/course', ['course' => $course, 'byModule' => $byModule], 'Forum: ' . $course['title']);
});

// A single board: threaded discussion.
route('GET', '/forum/course/{slug}/{board}', function ($p) {
    require_login();
    $course = course_by_slug($p['slug']);
    if (!$course || !forum_can_view_course($course)) { http_response_code(403); view('error', ['code'=>403,'message'=>'Forum unavailable.'], 'Forum'); return; }
    $board = forum_board($p['slug'], $p['board']);
    if (!$board) { http_response_code(404); view('error', ['code'=>404,'message'=>'That forum does not exist.'], 'Not found'); return; }
    $cid = (int) $course['id']; $u = current_user(); $staff = forum_is_staff();
    $posted = forum_user_posted_in_module((int) $u['id'], $cid, $board['id']);
    $locked = forum_gated_for($course) && !$staff && !$posted;
    $tree = array_reverse(forum_build_tree(forum_board_posts($cid, $board['id'], (int) $u['id'])));
    if ($locked) $tree = array_values(array_filter($tree, fn($t) => (int) $t['user_id'] === (int) $u['id']));
    view('forum/board', [
        'course' => $course, 'board' => $board, 'tree' => $tree, 'locked' => $locked,
        'can_post' => forum_can_post_course($course), 'can_moderate' => forum_can_moderate($cid),
    ], $board['name'] . ' — ' . $course['title']);
});

// Create a thread or (nested) reply in a board.
route('POST', '/forum/course/{slug}/{board}', function ($p) {
    csrf_check();
    require_login();
    $course = course_by_slug($p['slug']);
    if (!$course || !forum_can_post_course($course)) { http_response_code(403); exit('Forbidden.'); }
    $board = forum_board($p['slug'], $p['board']);
    if (!$board) { flash('Unknown forum.', 'error'); redirect('/forum/course/' . $p['slug']); }
    $u = current_user();
    $body = trim((string) input('body'));
    $parentId = (int) input('parent_id') ?: null;
    $back = '/forum/course/' . $p['slug'] . '/' . rawurlencode($board['id']);
    if ($body === '') { flash('Write something before posting.', 'error'); redirect($back); }
    // A reply may target ANY post in this same board (nested threading).
    if ($parentId) {
        $parent = forum_post($parentId);
        if (!$parent || (int) $parent['course_id'] !== (int) $course['id'] || (string) $parent['module_key'] !== (string) $board['id']) {
            flash('That post no longer exists.', 'error'); redirect($back);
        }
    }
    forum_create([
        'scope' => 'course', 'course_id' => (int) $course['id'], 'module_key' => (string) $board['id'],
        'parent_id' => $parentId, 'user_id' => (int) $u['id'], 'body' => $body,
    ]);
    flash($parentId ? 'Reply posted.' : 'Posted to the discussion.');
    redirect($back . '#p' . (int) db()->lastInsertId());
});

// Like / unlike a post.
route('POST', '/forum/post/{id}/like', function ($p) {
    csrf_check();
    require_login();
    $post = forum_post((int) $p['id']);
    if (!$post) { if (want_json()) json_out(['ok'=>false], 404); http_response_code(404); exit; }
    $u = current_user();
    $liked = forum_toggle_like((int) $p['id'], (int) $u['id']);
    if (want_json()) json_out(['ok' => true, 'liked' => $liked, 'count' => forum_like_count((int) $p['id'])]);
    redirect($_SERVER['HTTP_REFERER'] ?? '/forum');
});

// Moderate: hide/unhide a post (admins + group managers).
route('POST', '/forum/post/{id}/hide', function ($p) {
    csrf_check();
    require_login();
    $post = forum_post((int) $p['id']);
    if (!$post) { http_response_code(404); exit('Post not found'); }
    $courseId = $post['course_id'] !== null ? (int) $post['course_id'] : null;
    if (!forum_can_moderate($courseId)) { http_response_code(403); exit('Forbidden.'); }
    forum_set_hidden((int) $p['id'], (int) ($post['hidden'] ?? 0) === 0);
    audit('forum.hide', ['target_type' => 'forum_post', 'target_id' => (string) $p['id']]);
    flash('Post updated.');
    redirect($_SERVER['HTTP_REFERER'] ?? '/forum');
});

// Delete a post: the author, or a moderator.
route('POST', '/forum/post/{id}/delete', function ($p) {
    csrf_check();
    require_login();
    $post = forum_post((int) $p['id']);
    if (!$post) { http_response_code(404); exit('Post not found'); }
    $u = current_user();
    $courseId = $post['course_id'] !== null ? (int) $post['course_id'] : null;
    if ((int) $post['user_id'] !== (int) $u['id'] && !forum_can_moderate($courseId)) { http_response_code(403); exit('Forbidden.'); }
    forum_delete((int) $p['id']);
    audit('forum.delete', ['target_type' => 'forum_post', 'target_id' => (string) $p['id']]);
    flash('Post deleted.');
    redirect($_SERVER['HTTP_REFERER'] ?? '/forum');
});

route('GET', '/catalog', function () {
    require_login();
    $u = current_user();
    // Draft courses are visible only to admins and course developers.
    $seeDrafts = is_admin() || can_edit_content();
    $courses = all_courses();
    if (!$seeDrafts) {
        $courses = array_values(array_filter($courses, fn($c) => ($c['status'] ?? 'published') !== 'draft'));
    }
    $courses = sort_courses_certs_first($courses);
    // Purchase mode: non-enrolled courses show a Purchase link
    // instead of self-enroll. Learners are enrolled by an admin after payment.
    $purchase = !empty(lms_config()['catalog_purchase']);
    $dir = rtrim(lms_config()['courses_dir'], '/');
    foreach ($courses as &$c) {
        $c['enrolled'] = is_enrolled((int) $u['id'], (int) $c['id']);
        $c['locked_by'] = unmet_prerequisite((int) $u['id'], $c);
        if ($purchase && !$c['enrolled']) {
            $jp = "$dir/{$c['slug']}/course.json";
            $data = is_file($jp) ? (json_decode((string) file_get_contents($jp), true) ?: []) : [];
            $c['purchase_url'] = $data['purchase_url'] ?? '';
        }
    }
    unset($c);
    view('catalog', ['courses' => $courses, 'purchase_mode' => $purchase], 'Course catalog');
});

route('GET', '/learn/{slug}', function ($p) {
    require_login();
    $u = current_user();
    $course = course_by_slug($p['slug']);
    if (!$course) { http_response_code(404); view('error', ['code'=>404,'message'=>'Course not found.'],'Not found'); return; }
    $error = course_access_error((int) $u['id'], $course, true);
    if ($error !== null) {
        http_response_code(403);
        view('error', ['code' => 403, 'message' => $error], 'Course access required');
        return;
    }
    enroll((int) $u['id'], (int) $course['id']);
    // Course sites are served statically at /courses/<slug>/ (same origin so the
    // reader can sync completion via /api). Fall back to the stored path.
    $target = $course['path'] ?: ('/courses/' . $course['slug'] . '/');
    redirect($target);
});

// Serve a badge image (owner or admin only).
route('GET', '/badge/{code}', function ($p) {
    require_login();
    $code = preg_replace('/\.png$/', '', $p['code']);
    $badge = db_one('SELECT * FROM badges WHERE code = ?', [$code]);
    if (!$badge) { http_response_code(404); exit('Badge not found'); }
    $u = current_user();
    if ((int) $badge['user_id'] !== (int) $u['id'] && !is_admin()) { http_response_code(403); exit('Forbidden'); }
    $file = lms_config()['data_dir'] . '/' . $badge['image_path'];
    if (!is_file($file)) { http_response_code(404); exit('Badge image missing'); }
    $download = isset($_GET['download']);
    header('Content-Type: image/png');
    if ($download) header('Content-Disposition: attachment; filename="badge-' . $code . '.png"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
});

// Printable PDF certificate (owner or admin). Embeds the badge/certificate image.
route('GET', '/certificate/{code}', function ($p) {
    require_login();
    $code = preg_replace('/\.pdf$/', '', $p['code']);
    $badge = db_one('SELECT * FROM badges WHERE code = ?', [$code]);
    if (!$badge) { http_response_code(404); exit('Certificate not found'); }
    $u = current_user();
    if ((int) $badge['user_id'] !== (int) $u['id'] && !is_admin()) { http_response_code(403); exit('Forbidden'); }
    // Page 1: the text certificate (name, course, date, CPE + GT hours, signatory).
    $course = course_by_id((int) $badge['course_id']) ?: ['title' => $badge['title']];
    // Prefer the badge's snapshot so the certificate stays accurate even if the
    // course's credit hours later change (or the course is removed).
    $course['cpe_hours'] = $badge['cpe_hours'] !== null ? (float) $badge['cpe_hours'] : (float) ($course['cpe_hours'] ?? 0);
    $course['gt_hours']  = $badge['gt_hours']  !== null ? (float) $badge['gt_hours']  : (float) ($course['gt_hours'] ?? 0);
    $user = db_one('SELECT * FROM users WHERE id = ?', [(int) $badge['user_id']]) ?: [];
    $page1 = render_certificate_page($user, $course, $code, $badge['issued_at']);

    // Page 2: the earned badge art (course badge.png), if present.
    $pages = [$page1];
    $art = resolve_badge_art((string) ($course['badge_image'] ?? ''));
    if (!$art) {
        $candidate = rtrim(lms_config()['courses_dir'], '/') . '/' . ($course['slug'] ?? '') . '/badge.png';
        if (is_file($candidate)) $art = $candidate;
    }
    if ($art) $pages[] = $art;

    $pdf = pdf_from_images($pages);
    @unlink($page1);
    if ($pdf === null) { redirect('/badge/' . $code . '?download=1'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="certificate-' . $code . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
});

// --- LTI 1.3 (tool provider) -------------------------------------------------

// OIDC third-party login initiation (platform calls this; GET or POST).
route('GET', '/lti/login', function () { lti_oidc_login(array_merge($_GET, $_POST)); });
route('POST', '/lti/login', function () { lti_oidc_login(array_merge($_GET, $_POST)); });

// Launch endpoint — the platform form-posts the signed id_token here.
route('POST', '/lti/launch', function () {
    [$ok, $res] = lti_launch($_POST);
    if (!$ok) { http_response_code(400); view('error', ['code' => 400, 'message' => 'LTI launch failed: ' . $res], 'LTI'); return; }
    redirect($res);
});

// Sapiqo's public JWKS (platform verifies our client assertions).
route('GET', '/lti/jwks', function () {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(lti_jwks(), JSON_UNESCAPED_SLASHES);
    exit;
});

// Tool configuration (handy when registering Sapiqo in a platform).
route('GET', '/lti/config', function () {
    $u = lti_tool_urls();
    json_out([
        'title' => lms_config()['app_name'], 'oidc_initiation_url' => $u['login_url'],
        'target_link_uri' => $u['launch_url'], 'redirect_uris' => $u['redirect_uris'],
        'public_jwks_url' => $u['jwks_url'],
    ]);
});

// --- Public REST API v1 (Bearer-token auth) ---------------------------------

route('GET', '/api/v1/courses', function () {
    require_api_key('courses:read');
    $out = array_map(fn($c) => [
        'id' => (int) $c['id'], 'slug' => $c['slug'], 'title' => $c['title'],
        'total_units' => (int) $c['total_units'], 'active' => (int) $c['active'] === 1,
    ], all_courses(false));
    json_out(['data' => $out]);
});

route('GET', '/api/v1/users', function () {
    require_api_key('users:read');
    $perPage = max(1, min(200, (int) input('per_page', '50')));
    $page = max(1, (int) input('page', '1'));
    $total = (int) (db_one('SELECT COUNT(*) n FROM users')['n'] ?? 0);
    $rows = db_all('SELECT id,first_name,last_name,email,role,user_type,campus,organization,created_at
                    FROM users ORDER BY id LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
    json_out(['data' => $rows, 'page' => $page, 'per_page' => $perPage, 'total' => $total]);
});

route('POST', '/api/v1/users', function () {
    $key = require_api_key('users:write');
    $b = api_body();
    if (($b['role'] ?? '') === 'admin' && !api_key_allows($key, 'users:admin')) json_out(['error' => 'Creating administrators requires users:admin scope'], 403);
    [$ok, $res] = register_local([
        'first_name' => $b['first_name'] ?? '', 'last_name' => $b['last_name'] ?? '',
        'email' => $b['email'] ?? '', 'user_type' => $b['user_type'] ?? '',
        'campus' => $b['campus'] ?? '', 'organization' => $b['organization'] ?? '',
        'password' => $b['password'] ?? (bin2hex(random_bytes(6)) . 'Aa1'),
        'role' => ($b['role'] ?? '') === 'admin' ? 'admin' : 'learner',
    ]);
    if (!$ok) json_out(['error' => $res], 422);
    audit('api.user.create', ['actor_email' => 'api:' . $key['name'], 'target_type' => 'user', 'target_id' => (string) $res]);
    json_out(['data' => db_one('SELECT id,first_name,last_name,email,role FROM users WHERE id=?', [$res])], 201);
});

route('GET', '/api/v1/users/{id}', function ($p) {
    require_api_key('users:read');
    $u = db_one('SELECT id,first_name,last_name,email,role,user_type,campus,organization,created_at FROM users WHERE id=?', [(int) $p['id']]);
    if (!$u) json_out(['error' => 'Not found'], 404);
    $u['enrollments'] = enrollments_for((int) $u['id']);
    $u['badges'] = badges_for((int) $u['id']);
    json_out(['data' => $u]);
});

route('GET', '/api/v1/enrollments', function () {
    require_api_key('enrollments:read');
    if (input('user') !== '') {
        json_out(['data' => enrollments_for((int) input('user'))]);
    }
    if (input('course') !== '') {
        $rows = db_all('SELECT e.*, u.email FROM enrollments e JOIN users u ON u.id=e.user_id WHERE e.course_id=? ORDER BY e.id', [(int) input('course')]);
        json_out(['data' => $rows]);
    }
    json_out(['error' => 'Provide ?user=<id> or ?course=<id>'], 422);
});

route('POST', '/api/v1/enrollments', function () {
    $key = require_api_key('enrollments:write');
    $b = api_body();
    $u = isset($b['user_id']) ? db_one('SELECT * FROM users WHERE id=?', [(int) $b['user_id']])
        : db_one('SELECT * FROM users WHERE email=?', [strtolower((string) ($b['email'] ?? ''))]);
    if (!$u) json_out(['error' => 'Unknown user'], 404);
    $c = isset($b['course_id']) ? course_by_id((int) $b['course_id']) : resolve_course((string) ($b['course'] ?? $b['slug'] ?? ''));
    if (!$c) json_out(['error' => 'Unknown course'], 404);
    enroll((int) $u['id'], (int) $c['id']);
    audit('api.enroll', ['actor_email' => 'api:' . $key['name'], 'target_type' => 'user', 'target_id' => (string) $u['id'], 'detail' => 'course=' . $c['slug']]);
    json_out(['data' => ['user_id' => (int) $u['id'], 'course_id' => (int) $c['id'], 'status' => 'enrolled']], 201);
});

route('GET', '/api/v1/completions', function () {
    require_api_key('completions:read');
    $rows = db_all(
        "SELECT u.email, u.first_name, u.last_name, c.slug AS course, e.status, e.completed_at,
                b.code AS badge_code
         FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id
         LEFT JOIN badges b ON b.user_id=e.user_id AND b.course_id=e.course_id
         WHERE e.status='completed' ORDER BY e.completed_at DESC LIMIT 1000"
    );
    json_out(['data' => $rows]);
});

route('GET', '/api/v1/badges', function () {
    require_api_key('badges:read');
    if (input('user') !== '') json_out(['data' => badges_for((int) input('user'))]);
    $rows = db_all('SELECT b.*, u.email, c.slug AS course FROM badges b JOIN users u ON u.id=b.user_id JOIN courses c ON c.id=b.course_id ORDER BY b.id DESC LIMIT 1000');
    json_out(['data' => $rows]);
});

// --- API (used by the course reader) ----------------------------------------

route('GET', '/api/whoami', function () {
    $cfg = lms_config();
    $brand = ['catalog_name' => $cfg['catalog_name'], 'mark' => $cfg['brand_mark'],
              'logo' => $cfg['logo'] !== '' ? url('/brand/logo') : ''];
    if (!is_logged_in()) json_out(['authenticated' => false, 'brand' => $brand]);
    $u = current_user();
    json_out([
        'authenticated' => true,
        'user' => ['id' => (int) $u['id'], 'name' => trim($u['first_name'].' '.$u['last_name']), 'email' => $u['email']],
        'csrf' => csrf_token(),
        'brand' => $brand,
    ]);
});

// Return the learner's completed step ids for a course (reader hydration).
route('GET', '/api/progress', function () {
    require_login();
    $u = current_user();
    $course = course_by_slug(input('course'));
    if (!$course) json_out(['error' => 'Unknown course'], 404);
    $error = course_access_error((int) $u['id'], $course);
    if ($error !== null) json_out(['error' => $error], 403);
    $rows = db_all('SELECT step_id FROM progress WHERE user_id = ? AND course_id = ?', [$u['id'], $course['id']]);
    json_out([
        'course'  => $course['slug'],
        'steps'   => array_column($rows, 'step_id'),
        'percent' => course_percent((int) $u['id'], $course),
    ]);
});

// Lightweight beacon: remember the lesson a learner is viewing (for "resume").
route('POST', '/api/seen', function () {
    require_login();
    csrf_check();
    $u = current_user();
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $course = course_by_slug((string) ($body['course'] ?? ''));
    $stepId = trim((string) ($body['step_id'] ?? ''));
    if (!$course || $stepId === '') json_out(['ok' => false], 200);
    if (is_enrolled((int) $u['id'], (int) $course['id'])) {
        set_last_seen((int) $u['id'], (int) $course['id'], $stepId);
    }
    json_out(['ok' => true]);
});

route('POST', '/api/progress', function () {
    require_login();
    csrf_check();
    $u = current_user();
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $course = course_by_slug((string) ($body['course'] ?? ''));
    if (!$course) json_out(['error' => 'Unknown course'], 404);
    $error = course_access_error((int) $u['id'], $course);
    if ($error !== null) json_out(['error' => $error], 403);
    $stepId = trim((string) ($body['step_id'] ?? ''));
    if ($stepId === '') json_out(['error' => 'Missing step_id'], 422);
    if (course_step_kind($course['slug'], $stepId) === 'quiz') json_out(['error' => 'Quiz completion requires server grading.'], 403);
    $done = !empty($body['done']);
    $result = record_progress((int) $u['id'], (int) $course['id'], $stepId, $done);
    if (!empty($result['badge'])) {
        $result['badge_url'] = url('/badge/' . $result['badge']['code'] . '?download=1');
    }
    json_out($result);
});

// SCORM run-time callback: record completion (marks the course's single unit)
// and store the score. Called by the SCORM player shim.
route('POST', '/api/scorm', function () {
    require_login();
    csrf_check();
    $u = current_user();
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $course = course_by_slug((string) ($body['course'] ?? ''));
    if (!$course) json_out(['error' => 'Unknown course'], 404);
    $error = course_access_error((int) $u['id'], $course);
    if ($error !== null) json_out(['error' => $error], 403);
    if ((course_load_json($course['slug'])['type'] ?? '') !== 'scorm') json_out(['error' => 'Not a SCORM course'], 422);
    $status = strtolower((string) ($body['status'] ?? ''));
    $score = (int) ($body['score'] ?? 0);
    $completed = in_array($status, ['completed', 'passed'], true);
    if (!$completed) json_out(['ok' => true, 'completed' => false]);

    // Record the score in the gradebook (reuse quiz_results with a 'scorm' id).
    quiz_record((int) $u['id'], (int) $course['id'], 'scorm',
        ['score' => $score, 'total' => 100, 'passed' => true]);
    $pr = record_progress((int) $u['id'], (int) $course['id'], 'scorm', true);
    $result = ['ok' => true, 'completed' => true, 'percent_course' => $pr['percent'] ?? null];
    if (!empty($pr['badge'])) {
        $result['badge'] = $pr['badge'];
        $result['badge_url'] = url('/badge/' . $pr['badge']['code'] . '?download=1');
    }
    json_out($result);
});

// Grade a quiz server-side; a pass records the quiz step (and may issue a badge).
route('POST', '/api/quiz', function () {
    require_login();
    csrf_check();
    $u = current_user();
    $raw = file_get_contents('php://input');
    // A real quiz submission (answers for one quiz) is at most a few KB; cap
    // well above that to stop a memory-exhaustion DoS from an oversized body.
    if (strlen($raw) > 2_000_000) json_out(['error' => 'Request too large.'], 413);
    $body = json_decode($raw, true) ?: $_POST;
    $course = course_by_slug((string) ($body['course'] ?? ''));
    if (!$course) json_out(['error' => 'Unknown course'], 404);
    $error = course_access_error((int) $u['id'], $course);
    if ($error !== null) json_out(['error' => $error], 403);
    $quizId = trim((string) ($body['quiz_id'] ?? ''));
    $quiz = $quizId !== '' ? quiz_find($course['slug'], $quizId) : null;
    if (!$quiz) json_out(['error' => 'Unknown quiz'], 404);
    if (!course_step_unlocked((int) $u['id'], $course, $quizId)) json_out(['error' => 'Complete earlier lessons first.'], 403);

    $limit = (int) ($quiz['attempts'] ?? 0);

    $answers = is_array($body['answers'] ?? null) ? $body['answers'] : [];
    $saved = $_SESSION['quiz_sets'][quiz_session_key((int) $course['id'], $quizId)] ?? null;
    if (!$saved || ($saved['fingerprint'] ?? '') !== hash('sha256', json_encode($quiz))) {
        json_out(['error' => 'Reload the course before submitting this quiz.'], 409);
    }
    $asked = $saved['ids'];
    $graded = quiz_grade($quiz, $answers, $asked);
    if (!$graded) json_out(['error' => 'Quiz has no questions'], 422);

    if (!quiz_record_limited((int) $u['id'], (int) $course['id'], $quizId, $graded, $limit)) {
        json_out(['ok' => false, 'error' => 'No attempts remaining.',
            'attempts_used' => quiz_attempts_used((int) $u['id'], (int) $course['id'], $quizId), 'attempts_limit' => $limit]);
    }
    if ($limit > 0) {
        $graded['attempts_used'] = quiz_attempts_used((int) $u['id'], (int) $course['id'], $quizId);
        $graded['attempts_limit'] = $limit;
    }

    $result = ['ok' => true] + $graded;
    if ($graded['passed']) {
        // Passing marks the quiz's progress step complete (counts toward the badge).
        $pr = record_progress((int) $u['id'], (int) $course['id'], $quizId, true);
        $result['percent_course'] = $pr['percent'] ?? null;
        if (!empty($pr['badge'])) {
            $result['badge'] = $pr['badge'];
            $result['badge_url'] = url('/badge/' . $pr['badge']['code'] . '?download=1');
        }
    } else {
        // A previously-passed quiz that's now failed shouldn't un-complete the
        // step; we only ever set it on pass. (No action on fail.)
    }
    json_out($result);
});

// --- Terms of Service & Privacy Policy (public, no login required) ----------

route('GET', '/privacy', function () {
    view('legal', [], 'Terms of Service & Privacy Policy');
});
route('GET', '/terms', function () {
    view('legal', [], 'Terms of Service & Privacy Policy');
});

// --- Admin ------------------------------------------------------------------

route('GET', '/admin', function () {
    require_admin();
    scan_courses();  // auto-pick up dropped-in / removed courses
    $stats = [
        'users'       => (int) (db_one('SELECT COUNT(*) n FROM users')['n'] ?? 0),
        'learners'    => (int) (db_one("SELECT COUNT(*) n FROM users WHERE role='learner'")['n'] ?? 0),
        'courses'     => (int) (db_one('SELECT COUNT(*) n FROM courses')['n'] ?? 0),
        'enrollments' => (int) (db_one('SELECT COUNT(*) n FROM enrollments')['n'] ?? 0),
        'completed'   => (int) (db_one("SELECT COUNT(*) n FROM enrollments WHERE status='completed'")['n'] ?? 0),
        'badges'      => (int) (db_one('SELECT COUNT(*) n FROM badges')['n'] ?? 0),
    ];
    $stats['rate'] = $stats['enrollments'] > 0
        ? (int) round($stats['completed'] / $stats['enrollments'] * 100) : 0;
    view('admin/dashboard', ['stats' => $stats, 'timeline' => report_timeline(8)], 'Admin');
});

// A single admin section (Course management, Account management, Imports &
// backups, Exports, Settings & integrations) rendered as its own page.
route('GET', '/admin/section/{key}', function ($p) {
    require_admin();
    $sections = admin_sections();
    $sec = $sections[$p['key']] ?? null;
    if (!$sec) { http_response_code(404); view('error', ['code'=>404,'message'=>'Unknown admin section.'], 'Not found'); return; }
    view('admin/section', ['key' => $p['key'], 'section' => $sec], strip_tags($sec['label']));
});

// --- Enrollment codes (admin) -----------------------------------------------
// Generate/enter codes that auto-enroll learners into one or many courses; see
// issued codes, who redeemed them, and which courses each grants.
route('GET', '/admin/codes', function () {
    require_admin();
    $q = input('q');
    $detail = null;
    if (($vid = (int) input('code_id')) && ($c = code_by_id($vid))) {
        $detail = ['code' => $c, 'courses' => code_courses($vid), 'redemptions' => code_redemptions($vid)];
    }
    view('admin/codes', [
        'codes'   => codes_all($q),
        'courses' => all_courses(false),
        'q'       => $q,
        'detail'  => $detail,
    ], 'Enrollment codes');
});

route('POST', '/admin/codes', function () {
    csrf_check();
    require_admin();
    $courseIds = array_map('intval', (array) ($_POST['course_ids'] ?? []));
    $maxUses   = input('max_uses') !== '' ? (int) input('max_uses') : null;
    $expRaw    = input('expires_at');
    $expires   = $expRaw !== '' ? (substr($expRaw, 0, 10) . ' 23:59:59') : null;
    [$ok, $res] = code_create(input('label'), $courseIds, $maxUses, $expires,
        (int) current_user()['id'], input('custom_code'));
    if (!$ok) { flash((string) $res, 'error'); redirect('/admin/codes'); }
    audit('code.create', ['target_type' => 'enroll_code', 'target_id' => (string) $res['id'], 'detail' => $res['code']]);
    flash('Code ' . $res['code'] . ' created.');
    redirect('/admin/codes');
});

route('POST', '/admin/codes/toggle', function () {
    csrf_check();
    require_admin();
    if (($id = (int) input('id')) && ($c = code_by_id($id))) {
        code_toggle($id);
        audit('code.toggle', ['target_type' => 'enroll_code', 'target_id' => (string) $id, 'detail' => $c['code']]);
        flash('Code ' . $c['code'] . ((int) $c['active'] === 1 ? ' deactivated.' : ' reactivated.'));
    }
    redirect('/admin/codes');
});

route('POST', '/admin/codes/delete', function () {
    csrf_check();
    require_admin();
    if (($id = (int) input('id')) && ($c = code_by_id($id))) {
        code_delete($id);
        audit('code.delete', ['target_type' => 'enroll_code', 'target_id' => (string) $id, 'detail' => $c['code']]);
        flash('Code ' . $c['code'] . ' deleted. (Enrolled learners keep their access.)');
    }
    redirect('/admin/codes');
});

// --- Redeem a code (learner / potential participant) ------------------------
// Works logged-in (redeem now) AND logged-out (enter code first, then create an
// account or sign in — the code is applied automatically afterward).
route('GET', '/redeem', function () {
    // A code carried in from a sign-in link redeems automatically.
    if (is_logged_in() && ($r = code_apply_pending((int) current_user()['id']))) {
        flash('Code applied — you now have access to: ' . implode(', ', $r['courses']) . '.');
        redirect('/dashboard');
    }
    view('redeem', ['code' => input('code')], 'Redeem a code');
});

route('POST', '/redeem', function () {
    csrf_check();
    $code = input('code');
    // Throttle guesses (this endpoint is reachable while signed out, so it's
    // otherwise an unauthenticated brute-force surface for any short/custom
    // codes). Two independent locks, both required to pass: per-IP (stops one
    // source hammering many codes) AND per-CODE (stops a distributed/rotating-IP
    // attacker spreading guesses of the same short custom code across many
    // source addresses — a per-IP-only lock doesn't catch that).
    $ident = 'redeem:' . client_ip();
    $codeIdent = 'redeemcode:' . code_normalize($code);
    [$blockedIp, $retryIp] = login_is_blocked($ident);
    [$blockedCode, $retryCode] = login_is_blocked($codeIdent);
    if ($blockedIp || $blockedCode) {
        $retryAfter = max($retryIp, $retryCode);
        flash('Too many code attempts. Try again in ' . ceil($retryAfter / 60) . ' minute(s).', 'error');
        redirect('/redeem?code=' . urlencode($code));
    }
    $record = function (bool $ok) use ($ident, $codeIdent) {
        login_record_attempt($ident, $ok);
        login_record_attempt($codeIdent, $ok);
    };
    if (!is_logged_in()) {
        // Validate the code up-front so newcomers get instant feedback, then send
        // them to create an account (or sign in); the code is applied on success.
        $c = code_by_string($code);
        if (!$c) { $record(false); flash('That code was not recognized.', 'error'); redirect('/redeem?code=' . urlencode($code)); }
        [$rok, $why] = code_redeemable($c);
        if (!$rok) { $record(false); flash($why, 'error'); redirect('/redeem?code=' . urlencode($code)); }
        $record(true);
        $_SESSION['pending_code'] = $c['code'];
        flash('Code accepted — create your account or sign in, and we’ll unlock your course(s) automatically.');
        redirect(registration_allowed() ? '/register' : '/login');
    }
    [$ok, $res] = code_redeem($code, (int) current_user()['id']);
    $record($ok);
    if (!$ok) { flash((string) $res, 'error'); redirect('/redeem?code=' . urlencode($code)); }
    audit('code.redeem', ['target_type' => 'enroll_code', 'target_id' => (string) $res['code']['id'], 'detail' => $res['code']['code']]);
    flash($res['already']
        ? 'You already redeemed that code — access re-confirmed for: ' . implode(', ', $res['courses']) . '.'
        : 'Success! You now have access to: ' . implode(', ', $res['courses']) . '.');
    redirect('/dashboard');
});

route('GET', '/admin/users', function () {
    require_admin();
    $q = input('q');
    $where = ''; $args = [];
    if ($q !== '') {
        $where = ' WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR organization LIKE ? OR campus LIKE ?';
        $like = '%' . $q . '%'; $args = [$like, $like, $like, $like, $like];
    }
    $total = (int) (db_one('SELECT COUNT(*) n FROM users' . $where, $args)['n'] ?? 0);
    $perPage = 50;
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, (int) input('page', '1')));
    $offset = ($page - 1) * $perPage;
    $users = db_all('SELECT * FROM users' . $where . ' ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $args);
    view('admin/users', [
        'users' => $users, 'q' => $q, 'page' => $page, 'pages' => $pages, 'total' => $total,
        'courses' => all_courses(false), 'groups' => all_groups(),
    ], 'Users');
});

// User roster export (all fields). Respects the same search filter as the
// Users page, so "search then export" gives you exactly the rows you see.
route('GET', '/admin/users.csv', function () {
    require_admin();
    $q = input('q');
    $where = ''; $args = [];
    if ($q !== '') {
        $where = ' WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR organization LIKE ? OR campus LIKE ?';
        $like = '%' . $q . '%'; $args = [$like, $like, $like, $like, $like];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sapiqo-users.csv"');
    $fh = fopen('php://output', 'w');
    csv_out($fh, ['ID','First name','Last name','Email','Phone','Role','User type','Campus','Organization','Auth provider','Created','Updated']);
    foreach (db_all('SELECT * FROM users' . $where . ' ORDER BY last_name, first_name', $args) as $u) {
        csv_out($fh, [$u['id'],$u['first_name'],$u['last_name'],$u['email'],$u['phone'],$u['role'],
            $u['user_type'],$u['campus'],$u['organization'],$u['auth_provider'],$u['created_at'],$u['updated_at']]);
    }
    audit('users.export', $q !== '' ? ['detail' => 'q=' . $q] : []);
    exit;
});

// Bulk actions on selected users.
route('POST', '/admin/users/bulk', function () {
    csrf_check();
    require_admin();
    $ids = array_values(array_unique(array_map('intval', $_POST['ids'] ?? [])));
    $action = input('action');
    if (!$ids) { flash('Select at least one user.', 'error'); redirect('/admin/users'); }
    $me = (int) current_user()['id'];
    $n = 0;
    switch ($action) {
        case 'enroll':
        case 'unenroll':
            $cid = (int) input('course_id');
            if ($cid && course_by_id($cid)) {
                foreach ($ids as $id) { $action === 'enroll' ? enroll($id, $cid) : unenroll($id, $cid); $n++; }
            }
            break;
        case 'group_add':
        case 'group_remove':
            $gid = (int) input('group_id');
            if ($gid && group_by_id($gid)) {
                foreach ($ids as $id) { $action === 'group_add' ? add_member($gid, $id) : remove_member($gid, $id); $n++; }
            }
            break;
        case 'role_admin':
        case 'role_learner':
            $role = $action === 'role_admin' ? 'admin' : 'learner';
            foreach ($ids as $id) {
                if ($id === $me && $role !== 'admin') continue;  // don't self-demote
                db_run('UPDATE users SET role=?, updated_at=? WHERE id=?', [$role, now_utc(), $id]); $n++;
            }
            break;
        case 'delete':
            foreach ($ids as $id) {
                if ($id === $me) continue;  // never delete yourself
                db_run('DELETE FROM users WHERE id=?', [$id]); $n++;
            }
            break;
        default:
            flash('Unknown bulk action.', 'error'); redirect('/admin/users');
    }
    audit('users.bulk', ['detail' => "$action count=$n"]);
    flash("Applied “$action” to $n user(s).");
    redirect('/admin/users' . ($action ? '' : ''));
});

// Quick role toggle from the list.
route('POST', '/admin/users/role', function () {
    csrf_check();
    require_admin();
    $id = (int) input('id');
    $role = input('role') === 'admin' ? 'admin' : 'learner';
    db_run('UPDATE users SET role = ?, updated_at = ? WHERE id = ?', [$role, now_utc(), $id]);
    audit('user.role', ['target_type' => 'user', 'target_id' => (string) $id, 'detail' => "role=$role"]);
    flash('Role updated.');
    redirect('/admin/users');
});

// Start impersonating a user ("view as") for troubleshooting.
route('POST', '/admin/users/{id}/impersonate', function ($p) {
    csrf_check();
    require_admin();
    $id = (int) $p['id'];
    $me = current_user();
    if (is_impersonating()) { flash('You are already viewing as another user. Return to admin first.', 'error'); redirect('/admin/users/' . $id); }
    $target = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$target) { http_response_code(404); view('error', ['code'=>404,'message'=>'User not found.'], 'Not found'); return; }
    if ($id === (int) $me['id']) { flash('That is your own account.', 'error'); redirect('/admin/users/' . $id); }
    // Guard: don't let one admin act as another (avoids privilege confusion).
    if (($target['role'] ?? '') === 'admin') { flash('For safety, you cannot view as another administrator.', 'error'); redirect('/admin/users/' . $id); }
    audit('user.impersonate.start', ['target_type' => 'user', 'target_id' => (string) $id, 'detail' => 'as ' . $target['email']]);
    begin_impersonation($id);
    flash('You are now viewing as ' . (trim($target['first_name'] . ' ' . $target['last_name']) ?: $target['email']) . '. Use “Return to admin” when done.');
    redirect('/dashboard');
});

// Stop impersonating and return to the original admin account.
route('POST', '/impersonate/stop', function () {
    csrf_check();
    require_login();
    if (!is_impersonating()) redirect('/dashboard');
    $targetId = (int) ($_SESSION['uid'] ?? 0);
    $adminId = end_impersonation();
    $admin = $adminId ? db_one('SELECT * FROM users WHERE id = ?', [$adminId]) : null;
    // If the original admin vanished or lost admin rights, fail closed.
    if (!$admin || ($admin['role'] ?? '') !== 'admin') { logout_user(); flash('Session ended.', 'error'); redirect('/login'); }
    audit('user.impersonate.stop', ['actor_id' => $adminId, 'target_type' => 'user', 'target_id' => (string) $targetId]);
    flash('Back to your administrator account.');
    redirect('/admin/users/' . $targetId);
});

// Rescan the drop-in courses directory on demand.
route('POST', '/admin/courses/rescan', function () {
    csrf_check();
    require_content_access();
    $s = scan_courses();
    audit('course.rescan', ['detail' => sprintf('added=%d reactivated=%d deactivated=%d',
        count($s['added']), count($s['reactivated']), count($s['deactivated']))]);
    $msg = sprintf('Course scan: %d added, %d reactivated, %d deactivated.',
        count($s['added']), count($s['reactivated']), count($s['deactivated']));
    flash($msg . ($s['deactivated'] ? ' Removed: ' . implode(', ', $s['deactivated']) : ''));
    redirect('/admin');
});

// --- Create a course (in-browser Markdown editor) ----------------------------

route('GET', '/admin/create', function () {
    require_content_access();
    $slug = input('slug'); $clone = input('clone'); $draftId = (int) input('draft');
    $me = current_user();
    $editing = null; $start = learning_template_source(input('template'));
    if ($draftId > 0) {
        $d = draft_get((int) $me['id'], $draftId);
        if ($d) $start = $d['markdown'];
        else flash('Draft not found.', 'error');
    } elseif ($clone !== '') {
        $src = creator_clone_source($clone);
        if ($src !== null) { $start = $src; flash('Loaded a copy — review the slug/title, then publish as a new course.'); }
        else flash("No editable source found for \"$clone\".", 'error');
    } elseif ($slug !== '') {
        $src = creator_load_source($slug);
        if ($src !== null) { $editing = $slug; $start = $src; }
        else flash("No editable source found for \"$slug\". You can still paste Markdown to rebuild it.", 'error');
    }
    view('admin/create', ['sample' => creator_sample(), 'start' => $start, 'editing' => $editing],
        $editing ? 'Edit course' : 'Create a course');
});

// Save / list / delete editor drafts.
route('POST', '/admin/drafts', function () {
    csrf_check();
    require_content_access();
    $md = (string) ($_POST['markdown'] ?? '');
    if (trim($md) === '') json_out(['ok' => false, 'error' => 'Nothing to save.'], 422);
    $id = draft_save((int) current_user()['id'], $md);
    json_out(['ok' => true, 'id' => $id]);
});

route('GET', '/admin/drafts', function () {
    require_content_access();
    view('admin/drafts', ['drafts' => drafts_for((int) current_user()['id'])], 'Drafts');
});

route('POST', '/admin/drafts/{id}/delete', function ($p) {
    csrf_check();
    require_content_access();
    draft_delete((int) current_user()['id'], (int) $p['id']);
    flash('Draft deleted.');
    redirect('/admin/drafts');
});

// Editor uploads: stage an image/video, return an `upload:<name>` reference the
// builder copies into the course's media/ on publish (keeps courses self-contained).
route('POST', '/admin/uploads', function () {
    csrf_check();
    require_admin();
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        json_out(['ok' => false, 'error' => 'No file received.'], 400);
    }
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) json_out(['ok' => false, 'error' => 'Upload failed (code ' . $f['error'] . ').'], 400);
    $maxBytes = (int) lms_config()['max_upload_mb'] * 1024 * 1024;
    if ($f['size'] > $maxBytes) json_out(['ok' => false, 'error' => 'File too large (max ' . lms_config()['max_upload_mb'] . ' MB).'], 413);

    $images = ['png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'gif' => 'image', 'webp' => 'image', 'svg' => 'image'];
    $videos = ['mp4' => 'video', 'webm' => 'video', 'm4v' => 'video'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $kind = $images[$ext] ?? $videos[$ext] ?? null;
    if ($kind === null) json_out(['ok' => false, 'error' => 'Unsupported type. Use PNG/JPG/GIF/WEBP/SVG or MP4/WEBM.'], 415);

    // Validate real content type for raster images (defense in depth).
    if ($kind === 'image' && $ext !== 'svg' && function_exists('getimagesize')) {
        if (@getimagesize($f['tmp_name']) === false) json_out(['ok' => false, 'error' => 'That file is not a valid image.'], 415);
    }

    $dir = lms_config()['upload_dir'];
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $safe = preg_replace('/[^a-z0-9._-]/i', '_', pathinfo($f['name'], PATHINFO_FILENAME));
    $name = substr(bin2hex(random_bytes(4)), 0, 8) . '-' . substr($safe, 0, 40) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        json_out(['ok' => false, 'error' => 'Could not save the upload.'], 500);
    }
    audit('editor.upload', ['detail' => "$kind $name"]);
    $md = $kind === 'image' ? '![' . $safe . '](upload:' . $name . ')' : '@video upload:' . $name;
    json_out(['ok' => true, 'kind' => $kind, 'name' => $name,
        'url' => url('/uploads/' . $name), 'markdown' => $md]);
});

// Serve a staged upload (admins only — this is unpublished authoring content).
route('GET', '/uploads/{name}', function ($p) {
    require_admin();
    $name = $p['name'];
    if (!preg_match('/^[a-z0-9._-]+$/i', $name) || str_contains($name, '..')) { http_response_code(400); exit; }
    $file = lms_config()['upload_dir'] . '/' . $name;
    if (!is_file($file)) { http_response_code(404); exit; }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
              'webp'=>'image/webp','svg'=>'image/svg+xml','mp4'=>'video/mp4','webm'=>'video/webm','m4v'=>'video/mp4'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
});

// Decorate a course list (source flag + enrollment count) for the manage views.
function _admin_decorate_courses(array $courses): array {
    foreach ($courses as &$c) {
        $c['has_source'] = creator_load_source($c['slug']) !== null;
        $c['enrolled_count'] = (int) (db_one('SELECT COUNT(*) n FROM enrollments WHERE course_id=?', [$c['id']])['n'] ?? 0);
    }
    unset($c);
    return $courses;
}

// Course management: non-certification courses only (certs have their own page).
route('GET', '/admin/courses', function () {
    require_content_access();
    scan_courses();
    $courses = array_values(array_filter(all_courses(false), fn($c) => empty($c['is_certification'])));
    view('admin/courses', ['courses' => _admin_decorate_courses($courses), 'mode' => 'courses'], 'Courses');
});

// Share this demo over the local Wi-Fi network: show the host's LAN URL(s) so
// staff on the same network can connect and try it out.
route('GET', '/admin/share', function () {
    require_admin();
    $ips  = local_network_ips();
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    $suffix = ($port && $port !== 80 && $port !== 443) ? ':' . $port : '';
    $urls = array_map(fn($ip) => 'http://' . $ip . $suffix . url('/'), $ips);
    // If THIS request already arrived on a LAN IP, the server is reachable on the
    // network; if it came in on 127.0.0.1, it may be bound to localhost only.
    $onLan = in_array($_SERVER['SERVER_ADDR'] ?? '', $ips, true);
    view('admin/share', ['ips' => $ips, 'port' => $port, 'urls' => $urls, 'on_lan' => $onLan],
        'Share on your network');
});

// Certification management: certification courses only.
route('GET', '/admin/certifications', function () {
    require_content_access();
    scan_courses();
    $courses = array_values(array_filter(all_courses(false), fn($c) => !empty($c['is_certification'])));
    view('admin/courses', ['courses' => _admin_decorate_courses($courses), 'mode' => 'certifications'], 'Certifications');
});

// Export a course as a portable .tar.gz (also serves as a per-course backup).
route('GET', '/admin/courses/{slug}/export', function ($p) {
    require_content_access();
    @set_time_limit(0);   // large media courses can take a while to package
    try {
        $tmpDir = io_tmp_dir('export-');
        $file = export_course_archive($p['slug'], $tmpDir);
    } catch (Throwable $e) {
        flash('Export failed: ' . $e->getMessage(), 'error');
        redirect('/admin/courses');
    }
    audit('course.export', ['target_type' => 'course', 'target_id' => $p['slug']]);
    header('Content-Type: application/x-tar');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    @unlink($file); @rmdir(dirname($file));
    exit;
});

// Export a course's raw JSON (the native course.json — structure, lessons, quizzes).
route('GET', '/admin/courses/{slug}/export-json', function ($p) {
    require_content_access();
    if (!preg_match('/^[a-z0-9-]+$/', $p['slug'])) { http_response_code(400); exit; }
    $file = rtrim(lms_config()['courses_dir'], '/') . '/' . $p['slug'] . '/course.json';
    if (!is_file($file)) { flash('No course.json for that course.', 'error'); redirect('/admin/courses'); }
    audit('course.export_json', ['target_type' => 'course', 'target_id' => $p['slug']]);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $p['slug'] . '.json"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
});

// Guided "Export to another LMS": the Common Cartridge download plus concise,
// accurate per-platform import steps for Canvas, Moodle, Blackboard, and Sakai.
route('GET', '/admin/courses/{slug}/lms-export', function ($p) {
    require_content_access();
    $course = course_by_slug($p['slug']);
    if (!$course) { http_response_code(404); view('error', ['code'=>404,'message'=>'Course not found.'], 'Not found'); return; }
    view('admin/lms_export', ['course' => $course], 'Export to another LMS');
});

// Export a course as an IMS Common Cartridge (.imscc) for other LMSs.
route('GET', '/admin/courses/{slug}/export-cc', function ($p) {
    require_content_access();
    @set_time_limit(0);
    try {
        $tmpDir = io_tmp_dir('cc-');
        $file = export_common_cartridge($p['slug'], $tmpDir);
    } catch (Throwable $e) {
        flash('Common Cartridge export failed: ' . $e->getMessage(), 'error');
        redirect('/admin/courses');
    }
    audit('course.export_cc', ['target_type' => 'course', 'target_id' => $p['slug']]);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    @unlink($file); @rmdir(dirname($file));
    exit;
});

// Split-export a course into small parts + show a download page (for re-import
// on servers with tight upload limits).
route('GET', '/admin/courses/{slug}/export-split', function ($p) {
    require_content_access();
    @set_time_limit(0);
    $token = bin2hex(random_bytes(6));
    $stageDir = rtrim(lms_config()['export_dir'], '/') . '/' . $token;
    try {
        $chunk = upload_chunk_bytes();
        $res = export_course_split($p['slug'], $stageDir, $chunk);
    } catch (Throwable $e) {
        flash('Split export failed: ' . $e->getMessage(), 'error');
        redirect('/admin/courses');
    }
    audit('course.export_split', ['target_type' => 'course', 'target_id' => $p['slug'],
        'detail' => count($res['parts']) . ' parts']);
    view('admin/export_split', [
        'slug' => $p['slug'], 'token' => $token, 'archive' => $res['archive'],
        'parts' => $res['parts'], 'chunkMb' => round($chunk / 1048576, 1),
    ], 'Split export');
});

// Serve one exported part (admin only).
route('GET', '/admin/exports/{token}/{file}', function ($p) {
    require_content_access();
    if (!preg_match('/^[a-f0-9]{6,}$/', $p['token']) || !preg_match('/^[A-Za-z0-9._-]+$/', $p['file']) || str_contains($p['file'], '..')) {
        http_response_code(400); exit;
    }
    $file = rtrim(lms_config()['export_dir'], '/') . '/' . $p['token'] . '/' . $p['file'];
    if (!is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $p['file'] . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
});

// Receive one uploaded part (its own request, so it stays under the upload cap).
route('POST', '/admin/import/part', function () {
    csrf_check();
    require_admin();
    $token = input('token'); $seq = (int) input('seq');
    if (!preg_match('/^[a-f0-9]{6,}$/', $token)) json_out(['ok' => false, 'error' => 'bad token'], 400);
    if (empty($_FILES['chunk']['tmp_name']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
        json_out(['ok' => false, 'error' => 'no chunk'], 400);
    }
    $dir = rtrim(lms_config()['data_dir'], '/') . '/tmp/upload-' . $token;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    move_uploaded_file($_FILES['chunk']['tmp_name'], sprintf('%s/chunk.part%05d', $dir, $seq));
    json_out(['ok' => true, 'seq' => $seq]);
});

// Assemble uploaded parts and import the resulting archive.
route('POST', '/admin/import/assemble', function () {
    csrf_check();
    require_admin();
    @set_time_limit(0);
    $token = input('token'); $name = input('name');
    if (!preg_match('/^[a-f0-9]{6,}$/', $token)) { flash('Bad upload token.', 'error'); redirect('/admin/courses'); }
    $dir = rtrim(lms_config()['data_dir'], '/') . '/tmp/upload-' . $token;
    $assembled = $dir . '/assembled-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name ?: 'course.tar');
    if (!assemble_parts($dir, $assembled)) { flash('No parts were received to assemble.', 'error'); redirect('/admin/courses'); }

    if (preg_match('/\.imscc$/i', $name)) {
        [$ok, $res, $report] = import_common_cartridge($assembled, $name);
        $extra = $ok ? " ({$report['lessons']} lessons, {$report['quizzes']} quizzes)" : '';
    } else {
        [$ok, $res] = import_course_archive($assembled, $name ?: 'course.tar');
        $extra = '';
    }
    // Clean the staging dir.
    foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
    @rmdir($dir);

    if ($ok) { scan_courses(); audit('course.import_split', ['target_type' => 'course', 'target_id' => $res]); flash("Imported “$res”.$extra"); }
    else flash($res, 'error');
    redirect('/admin/courses');
});

// Import a SCORM 1.2 / 2004 package (.zip).
route('POST', '/admin/courses/import-scorm', function () {
    csrf_check();
    require_admin();
    @set_time_limit(0);
    if (empty($_FILES['scorm']['tmp_name']) || !is_uploaded_file($_FILES['scorm']['tmp_name'])) {
        flash('Choose a SCORM .zip to upload.', 'error'); redirect('/admin/courses');
    }
    [$ok, $res, $report] = import_scorm($_FILES['scorm']['tmp_name'], $_FILES['scorm']['name']);
    if ($ok) {
        scan_courses();
        audit('course.import_scorm', ['target_type' => 'course', 'target_id' => $res, 'detail' => 'SCORM ' . $report['version']]);
        flash("Imported SCORM course “$res” (SCORM {$report['version']}).");
    } else {
        flash($res, 'error');
    }
    redirect('/admin/courses');
});

// Import a course archive (native .tar.gz/.zip export).
route('POST', '/admin/courses/import', function () {
    csrf_check();
    require_admin();
    @set_time_limit(0);
    if (empty($_FILES['archive']['tmp_name']) || !is_uploaded_file($_FILES['archive']['tmp_name'])) {
        flash('Choose a course archive to upload.', 'error'); redirect('/admin/courses');
    }
    [$ok, $res] = import_course_archive($_FILES['archive']['tmp_name'], $_FILES['archive']['name']);
    if ($ok) {
        scan_courses();
        audit('course.import', ['target_type' => 'course', 'target_id' => $res]);
        flash("Imported course “$res”. It is now in the catalog.");
    } else {
        flash($res, 'error');
    }
    redirect('/admin/courses');
});

// Set a course's CPE credit hours (shown on the certificate).
route('POST', '/admin/courses/{slug}/cpe', function ($p) {
    csrf_check();
    require_content_access();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $hours = (float) str_replace(',', '.', input('cpe_hours'));
    if ($hours < 0) $hours = 0;
    db_run('UPDATE courses SET cpe_hours = ? WHERE id = ?', [$hours, $c['id']]);
    audit('course.cpe', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => "cpe=$hours"]);
    flash('CPE hours updated.');
    redirect('/admin/courses');
});

// Permanently delete a course: removes its content folder and course-scoped
// rows. Admin-only. Learner BADGES are kept (they carry a durable title/CPE/GT
// snapshot, so transcripts and certificates still work). Prefer Hide/Draft.
route('POST', '/admin/courses/{slug}/delete', function ($p) {
    csrf_check();
    require_admin();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $cid = (int) $c['id'];
    // Remove the drop-in content folder (course.json, media, badge, index.html).
    $dir = rtrim(lms_config()['courses_dir'], '/') . '/' . $c['slug'];
    if (is_dir($dir) && function_exists('_rmtree')) _rmtree($dir);
    // Remove course-scoped rows (keep badges — the permanent learner record).
    foreach (['enrollments', 'progress', 'quiz_results', 'forum_posts',
              'group_courses', 'org_courses', 'enroll_code_courses', 'assessments'] as $t) {
        try { db_run("DELETE FROM $t WHERE course_id = ?", [$cid]); } catch (Throwable $e) {}
    }
    db_run('DELETE FROM courses WHERE id = ?', [$cid]);
    audit('course.delete', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $c['title']]);
    flash('Course "' . $c['title'] . '" was permanently deleted. Any earned badges/certificates stay on learner transcripts.');
    redirect('/admin/courses');
});

// Toggle a course between draft (hidden from learners) and published.
route('POST', '/admin/courses/{slug}/status', function ($p) {
    csrf_check();
    require_content_access();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $new = (($c['status'] ?? 'published') === 'draft') ? 'published' : 'draft';
    if ($new === 'published') { $checks = course_publication_checks(course_load_json($c['slug'])); if ($checks['errors']) { flash(implode(' ', $checks['errors']), 'error'); redirect('/admin/courses/' . $c['slug'] . '/checklist'); } }
    db_run('UPDATE courses SET status = ? WHERE id = ?', [$new, $c['id']]);
    // Persist to course.json so it survives a rescan and travels with the course.
    $jsonPath = rtrim(lms_config()['courses_dir'], '/') . '/' . $c['slug'] . '/course.json';
    if (is_file($jsonPath)) {
        $data = json_decode((string) file_get_contents($jsonPath), true) ?: [];
        $data['status'] = $new;
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    audit('course.status', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $new]);
    flash($new === 'draft' ? 'Course moved to Draft — hidden from learners.' : 'Course published.');
    redirect('/admin/courses');
});

// Set a course's prerequisite (another course, or none).
route('POST', '/admin/courses/{slug}/prereq', function ($p) {
    csrf_check();
    require_content_access();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $pid = (int) input('prereq_id');
    // Guard against self- and direct circular references.
    if ($pid === (int) $c['id']) { flash('A course cannot be its own prerequisite.', 'error'); redirect('/admin/courses'); }
    if ($pid > 0) {
        $other = course_by_id($pid);
        if ($other && (int) ($other['prereq_id'] ?? 0) === (int) $c['id']) {
            flash('That would create a circular prerequisite.', 'error'); redirect('/admin/courses');
        }
    }
    db_run('UPDATE courses SET prereq_id = ? WHERE id = ?', [$pid > 0 ? $pid : null, $c['id']]);
    audit('course.prereq', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => 'prereq_id=' . $pid]);
    flash('Prerequisite updated.');
    redirect('/admin/courses');
});

// Set a course's enrollment lifetime (days; 0 = no expiry).
route('POST', '/admin/courses/{slug}/expiry', function ($p) {
    csrf_check();
    require_content_access();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $days = max(0, (int) input('enroll_days'));
    set_course_enroll_days((int) $c['id'], $days);
    audit('course.expiry', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => "days=$days"]);
    flash($days > 0 ? "Enrollments in “{$c['slug']}” now expire after $days day(s)."
                    : "Enrollment expiry disabled for “{$c['slug']}”.");
    redirect('/admin/courses');
});

// Toggle sequential (locked-order) mode for a course.
route('POST', '/admin/courses/{slug}/sequential', function ($p) {
    csrf_check();
    require_content_access();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $val = input('sequential'); // 'inherit' | 'on' | 'off'
    $set = $val === 'inherit' ? null : ($val === 'on' || $val === '1');
    set_course_sequential((int) $c['id'], $set);
    audit('course.sequential', ['target_type' => 'course', 'target_id' => $p['slug'],
        'detail' => $set === null ? 'inherit' : ($set ? 'on' : 'off')]);
    $label = $set === null ? 'follows the global default'
           : ($set ? 'requires lessons to be completed in order' : 'allows lessons in any order');
    flash("“{$c['slug']}” now $label.");
    redirect('/admin/courses');
});

// Set a course's discussion-forum mode: off / on / on + "post before you see".
route('POST', '/admin/courses/{slug}/forum', function ($p) {
    csrf_check();
    require_content_access();
    $c = course_by_slug($p['slug']);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $val = input('forum'); // 'off' | 'on' | 'gated'
    $enabled = $val !== 'off';
    $gated = $val === 'gated';
    db_run('UPDATE courses SET forum_enabled = ?, forum_gated = ? WHERE id = ?',
        [$enabled ? 1 : 0, $gated ? 1 : 0, (int) $c['id']]);
    audit('course.forum', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $val]);
    $label = !$enabled ? 'has its forum turned off'
           : ($gated ? 'has a forum where participants post before seeing others' : 'has an open discussion forum');
    flash("“{$c['slug']}” now $label.");
    redirect('/admin/courses');
});

// Run the enrollment-expiry sweep on demand.
route('POST', '/admin/expire', function () {
    csrf_check();
    require_admin();
    $r = expire_enrollments();
    audit('enrollments.expire', ['detail' => "removed={$r['removed']} warned={$r['warned']}"]);
    flash("Expiry sweep: {$r['removed']} enrollment(s) ended" . ($r['warned'] ? ", {$r['warned']} warned" : '') . '.');
    redirect('/admin/courses');
});

// Import an IMS Common Cartridge (.imscc) from Canvas/Sakai/Moodle/Blackboard.
route('POST', '/admin/courses/import-cc', function () {
    csrf_check();
    require_admin();
    @set_time_limit(0);
    if (empty($_FILES['cartridge']['tmp_name']) || !is_uploaded_file($_FILES['cartridge']['tmp_name'])) {
        flash('Choose an .imscc file to upload.', 'error'); redirect('/admin/courses');
    }
    [$ok, $res, $report] = import_common_cartridge($_FILES['cartridge']['tmp_name'], $_FILES['cartridge']['name']);
    if ($ok) {
        scan_courses();
        audit('course.import_cc', ['target_type' => 'course', 'target_id' => $res,
            'detail' => "lessons={$report['lessons']} quizzes={$report['quizzes']} media={$report['media']} skipped=" . count($report['skipped'])]);
        $msg = "Imported “$res”: {$report['lessons']} lesson(s), {$report['quizzes']} quiz(zes), {$report['media']} media file(s).";
        if (!empty($report['missing_media'])) $msg .= ' ' . (int) $report['missing_media']
            . ' referenced file(s) were not in the export (commonly Canvas-hosted videos) and were left as links.';
        if ($report['skipped']) $msg .= ' Skipped ' . count($report['skipped']) . ' unsupported item(s): '
            . implode('; ', array_slice($report['skipped'], 0, 6)) . (count($report['skipped']) > 6 ? '…' : '');
        flash($msg);
    } else {
        flash($res, 'error');
    }
    redirect('/admin/courses');
});

// Import a LearnDash (WordPress) course export (.json) — native, no Python.
route('POST', '/admin/courses/import-learndash', function () {
    csrf_check();
    require_admin();
    @set_time_limit(0);
    if (empty($_FILES['learndash']['tmp_name']) || !is_uploaded_file($_FILES['learndash']['tmp_name'])) {
        flash('Choose a LearnDash export .json file to upload.', 'error'); redirect('/admin/import');
    }
    $mirror = !empty($_POST['mirror']);
    [$ok, $res, $report] = import_learndash($_FILES['learndash']['tmp_name'], $_FILES['learndash']['name'], $mirror);
    if ($ok) {
        scan_courses();
        audit('course.import_learndash', ['target_type' => 'course', 'target_id' => $res,
            'detail' => "modules={$report['modules']} lessons={$report['lessons']} videos={$report['videos']} images={$report['images_mirrored']}"]);
        $msg = "Imported “{$report['title']}” ({$res}): {$report['modules']} module(s), {$report['lessons']} lesson(s), {$report['videos']} video(s)";
        if ($mirror) $msg .= ", {$report['images_mirrored']} image(s) mirrored" . ($report['images_failed'] ? " ({$report['images_failed']} failed)" : '');
        $msg .= '. Videos embed from Vimeo — add MP4s + videos.json to self-host.';
        flash($msg);
    } else {
        flash($res, 'error');
    }
    redirect('/admin/courses');
});

route('POST', '/admin/courses/{slug}/toggle', function ($p) {
    csrf_check();
    require_content_access();
    $slug = $p['slug'];
    $c = course_by_slug($slug);
    if (!$c) { flash('Course not found.', 'error'); redirect('/admin/courses'); }
    $disable = (int) $c['active'] === 1;   // currently active → hide it
    creator_set_disabled($slug, $disable);
    scan_courses();
    audit($disable ? 'course.hide' : 'course.restore', ['target_type' => 'course', 'target_id' => $slug]);
    flash($disable ? "\"$slug\" is now hidden from the catalog (learner data kept)."
                   : "\"$slug\" is available again.");
    redirect('/admin/courses');
});

route('POST', '/admin/courses/create', function () {
    csrf_check();
    require_content_access();
    $md = (string) ($_POST['markdown'] ?? '');
    if (trim($md) === '') {
        if (want_json()) json_out(['ok' => false, 'error' => 'Write some Markdown first.'], 422);
        flash('Write some Markdown first.', 'error'); redirect('/admin/create');
    }
    [$ok, $res] = create_course_from_markdown($md);
    if ($ok) { scan_courses(); audit('course.publish', ['target_type' => 'course', 'target_id' => (string) $res]); }
    if (want_json()) {
        json_out($ok
            ? ['ok' => true, 'slug' => $res, 'url' => url('/courses/' . $res . '/')]
            : ['ok' => false, 'error' => $res], $ok ? 200 : 422);
    }
    if (!$ok) { flash((string) $res, 'error'); redirect('/admin/create'); }
    flash('Course "' . $res . '" published. It is now in the catalog.');
    redirect('/courses/' . $res . '/');
});

// --- GUI (block-based) course editor -----------------------------------------

// Create a new blank course and open it in the visual editor.
route('POST', '/admin/editor-new', function () {
    require_content_access();
    csrf_check();
    [$ok, $res] = editor_create_blank((string) input('title'));
    if (!$ok) { flash($res, 'error'); redirect('/admin/courses'); }
    audit('course.editor_new', ['target_type' => 'course', 'target_id' => $res]);
    redirect('/admin/editor/' . $res);
});

// The visual editor page (loads the course structure via the API below).
route('GET', '/admin/editor/{slug}', function ($p) {
    require_content_access();
    $course = editor_load($p['slug']);
    if (!$course) { http_response_code(404); view('error', ['code'=>404,'message'=>'Course not found.'], 'Not found'); return; }
    view('admin/editor', ['slug' => $p['slug'], 'title' => $course['title'] ?? $p['slug']], 'Edit: ' . ($course['title'] ?? $p['slug']));
});

// Editor data: full course structure (blocks) + resources + course settings.
route('GET', '/api/editor/{slug}', function ($p) {
    require_content_access();
    $course = editor_load($p['slug']);
    if (!$course) json_out(['error' => 'Course not found'], 404);
    $row = course_by_slug($p['slug']);
    $others = [];
    foreach (all_courses(false) as $oc) {
        if ((int) $oc['id'] !== (int) ($row['id'] ?? 0)) $others[] = ['id' => (int) $oc['id'], 'title' => $oc['title']];
    }
    json_out([
        'course' => $course,
        'resources' => editor_resources($p['slug']),
        'settings' => [
            'tagline'       => $course['tagline'] ?? '',
            'cpe_hours'     => (float) ($row['cpe_hours'] ?? 0),
            'gt_hours'      => (float) ($row['gt_hours'] ?? 0),
            'prereq_id'     => (int) ($row['prereq_id'] ?? 0),
            'enroll_days'   => (int) ($row['enroll_days'] ?? 0),
            'sequential'    => $row['sequential'] === null ? 'inherit' : ((int) $row['sequential'] === 1 ? 'on' : 'off'),
            'forum'         => (($row['forum_enabled'] ?? 1) === '0' || (int) ($row['forum_enabled'] ?? 1) === 0) ? 'off' : ((int) ($row['forum_gated'] ?? 0) === 1 ? 'gated' : 'on'),
            'active'        => (int) ($row['active'] ?? 1),
        ],
        'other_courses' => $others,
        'rev' => (int) ($course['rev'] ?? 0),
        'lock' => editor_lock_read($p['slug']),   // who (if anyone) holds a live edit lease
    ]);
});

// Save course settings (CPE, prerequisite, expiry, sequential, forum, tagline).
route('POST', '/api/editor/{slug}/settings', function ($p) {
    require_content_access();
    csrf_check();
    $course = course_by_slug($p['slug']);
    if (!$course) json_out(['ok' => false, 'error' => 'Course not found'], 404);
    $cid = (int) $course['id'];
    // CPE + GT hours.
    db_run('UPDATE courses SET cpe_hours = ?, gt_hours = ? WHERE id = ?',
        [max(0.0, (float) input('cpe_hours')), max(0.0, (float) input('gt_hours')), $cid]);
    // Prerequisite (guard self + simple cycle).
    $pre = (int) input('prereq_id');
    if ($pre === $cid) $pre = 0;
    if ($pre > 0) { $pc = course_by_id($pre); if (!$pc || (int) ($pc['prereq_id'] ?? 0) === $cid) $pre = 0; }
    db_run('UPDATE courses SET prereq_id = ? WHERE id = ?', [$pre ?: null, $cid]);
    // Enrollment expiry (recomputes existing enrollments).
    set_course_enroll_days($cid, max(0, (int) input('enroll_days')));
    // Sequential.
    $sv = input('sequential');
    set_course_sequential($cid, $sv === 'inherit' ? null : ($sv === 'on'));
    // Forum.
    $fv = input('forum');
    db_run('UPDATE courses SET forum_enabled = ?, forum_gated = ? WHERE id = ?',
        [$fv === 'off' ? 0 : 1, $fv === 'gated' ? 1 : 0, $cid]);
    // Tagline + CPE/GT hours are persisted to course.json so they survive a
    // rescan and travel with the course.
    $dir = rtrim(lms_config()['courses_dir'], '/') . '/' . $p['slug'];
    if (is_file("$dir/course.json")) {
        $j = json_decode((string) file_get_contents("$dir/course.json"), true) ?: [];
        $j['tagline']   = trim((string) input('tagline'));
        $j['cpe_hours'] = max(0.0, (float) input('cpe_hours'));
        $j['gt_hours']  = max(0.0, (float) input('gt_hours'));
        @file_put_contents("$dir/course.json", json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    audit('course.settings', ['target_type' => 'course', 'target_id' => $p['slug']]);
    json_out(['ok' => true]);
});

// Save the edited course structure (JSON body).
route('POST', '/api/editor/{slug}', function ($p) {
    require_content_access();
    csrf_check();
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) json_out(['ok' => false, 'error' => 'Malformed request.'], 400);
    $baseRev = array_key_exists('base_rev', $data) ? (int) $data['base_rev'] : null;
    [$ok, $res] = editor_save($p['slug'], $data, $baseRev, current_user());
    if ($ok) {
        audit('course.editor_save', ['target_type' => 'course', 'target_id' => $p['slug']]);
        json_out(['ok' => true, 'slug' => $res['slug'], 'rev' => $res['rev']]);
    }
    $err = is_array($res) ? $res : ['error' => $res, 'code' => 422];
    json_out(['ok' => false, 'error' => $err['error'] ?? 'Save failed',
              'conflict' => $err['conflict'] ?? false, 'lockedBy' => $err['lockedBy'] ?? null], $err['code'] ?? 422);
});

// --- Editor lock/lease (concurrent-edit protection) ------------------------
// Acquire or take over (force=1) the edit lease for a course.
route('POST', '/api/editor/{slug}/lock', function ($p) {
    require_content_access();
    csrf_check();
    if (!course_by_slug($p['slug'])) json_out(['ok' => false, 'error' => 'Course not found'], 404);
    [$ok, $info] = editor_lock_acquire($p['slug'], current_user(), input('force') === '1');
    if ($ok) json_out(['ok' => true, 'holder' => $info['name']]);
    json_out(['ok' => false, 'lockedBy' => $info['name'], 'since' => (int) $info['acquired_at']], 200);
});
// Keep the lease alive (client pings every ~30s). ok=false means we lost it.
route('POST', '/api/editor/{slug}/lock/heartbeat', function ($p) {
    require_content_access();
    csrf_check();
    json_out(['ok' => editor_lock_heartbeat($p['slug'], (int) current_user()['id'])]);
});
// Release the lease (sent on page close via sendBeacon).
route('POST', '/api/editor/{slug}/lock/release', function ($p) {
    require_content_access();
    csrf_check();
    editor_lock_release($p['slug'], (int) current_user()['id']);
    json_out(['ok' => true]);
});

// Upload a resource (image/video/pdf/doc/caption) into the course.
route('POST', '/api/editor/{slug}/upload', function ($p) {
    require_content_access();
    csrf_check();
    @set_time_limit(0);
    if (empty($_FILES['file'])) json_out(['ok' => false, 'error' => 'No file.'], 400);
    [$ok, $res] = editor_store_upload($p['slug'], $_FILES['file'], (string) input('label'));
    if ($ok) audit('course.resource_upload', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $res]);
    json_out($ok ? ['ok' => true, 'path' => $res, 'url' => url('/courses/' . $p['slug'] . '/' . $res)]
                 : ['ok' => false, 'error' => $res], $ok ? 200 : 422);
});

// Delete a resource.
route('POST', '/api/editor/{slug}/resource/delete', function ($p) {
    require_content_access();
    csrf_check();
    $rel = (string) input('path');
    $ok = editor_delete_resource($p['slug'], $rel);
    if ($ok) audit('course.resource_delete', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $rel]);
    json_out(['ok' => $ok]);
});

// Per-course Resource Center (files/media library).
route('GET', '/admin/resources/{slug}', function ($p) {
    require_content_access();
    $course = editor_load($p['slug']);
    if (!$course) { http_response_code(404); view('error', ['code'=>404,'message'=>'Course not found.'], 'Not found'); return; }
    view('admin/resources', [
        'slug' => $p['slug'], 'title' => $course['title'] ?? $p['slug'],
        'resources' => editor_resources($p['slug']),
    ], 'Resources: ' . ($course['title'] ?? $p['slug']));
});

// Download a fresh backup of the data root (config, DB, badges, avatars, uploads).
route('GET', '/admin/backup', function () {
    require_admin();
    try {
        $tmpDir = sys_get_temp_dir() . '/lmsbak-' . bin2hex(random_bytes(4));
        $file = create_backup($tmpDir);
    } catch (Throwable $e) {
        flash('Backup failed: ' . $e->getMessage(), 'error');
        redirect('/admin');
    }
    audit('backup.download', ['detail' => basename($file)]);
    $ctype = str_ends_with($file, '.zip') ? 'application/zip' : 'application/gzip';
    header('Content-Type: ' . $ctype);
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    @unlink($file);
    @rmdir(dirname($file));
    exit;
});

// --- Gradebook (quiz results) -----------------------------------------------

route('GET', '/admin/gradebook', function () {
    require_admin();
    $courseId = input('course') !== '' ? (int) input('course') : null;
    $where = ''; $args = [];
    if ($courseId) { $where = ' WHERE qr.course_id = ?'; $args[] = $courseId; }
    $rows = db_all(
        "SELECT qr.quiz_id, qr.score, qr.total, qr.passed, qr.attempts, qr.updated_at,
                u.first_name, u.last_name, u.email, c.title AS course_title, c.slug
         FROM quiz_results qr
         JOIN users u ON u.id = qr.user_id
         JOIN courses c ON c.id = qr.course_id" . $where . "
         ORDER BY c.title, u.last_name, u.first_name, qr.quiz_id LIMIT 5000",
        $args
    );
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sapiqo-gradebook.csv"');
        $fh = fopen('php://output', 'w');
        csv_out($fh, ['Course', 'First name', 'Last name', 'Email', 'Quiz', 'Score', 'Total', 'Percent', 'Passed', 'Attempts', 'Updated (UTC)']);
        foreach ($rows as $r) {
            $pct = (int) $r['total'] > 0 ? round($r['score'] / $r['total'] * 100) : 0;
            csv_out($fh, [$r['course_title'], $r['first_name'], $r['last_name'], $r['email'], $r['quiz_id'],
                $r['score'], $r['total'], $pct, ((int)$r['passed'] ? 'yes' : 'no'), $r['attempts'], $r['updated_at']]);
        }
        exit;
    }
    view('admin/gradebook', ['rows' => $rows, 'courses' => all_courses(false), 'course_filter' => $courseId], 'Gradebook');
});

// --- Custom gradebook: scores grid per course --------------------------------

// The scores grid (learners x assessments) for one course.
route('GET', '/admin/gradebook/{slug}', function ($p) {
    require_admin();
    $course = course_by_slug($p['slug']);
    if (!$course) { http_response_code(404); view('error', ['code'=>404,'message'=>'Course not found.'], 'Not found'); return; }
    $cid = (int) $course['id'];
    $assessments = gb_assessments($cid);
    $learners = gb_learners($cid);
    $scores = gb_scores_for_course($cid);
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gradebook-' . $course['slug'] . '.csv"');
        $fh = fopen('php://output', 'w');
        $head = ['Last name', 'First name', 'Email'];
        foreach ($assessments as $a) $head[] = $a['title'] . ' (/' . gb_num($a['max_points']) . ')';
        $head[] = 'Overall %'; $head[] = 'Grade';
        csv_out($fh, $head);
        foreach ($learners as $l) {
            $row = [$l['last_name'], $l['first_name'], $l['email']];
            foreach ($assessments as $a) $row[] = gb_num($scores[(int) $a['id']][(int) $l['id']] ?? '');
            $pct = gb_overall((int) $l['id'], $assessments, $scores);
            $row[] = $pct === null ? '' : $pct; $row[] = gb_letter($pct);
            csv_out($fh, $row);
        }
        exit;
    }
    view('admin/gradebook_grid', [
        'course' => $course, 'assessments' => $assessments, 'learners' => $learners,
        'scores' => $scores, 'scale' => gb_grade_scale(),
    ], 'Gradebook: ' . $course['title']);
});

// Add an assessment (column) to a course.
route('POST', '/admin/gradebook/{slug}/assessment', function ($p) {
    csrf_check();
    require_admin();
    $course = course_by_slug($p['slug']);
    if (!$course) { http_response_code(404); exit('Course not found'); }
    $id = (int) input('id');
    $title = (string) input('title');
    $category = (string) input('category');
    $max = (float) input('max_points');
    $due = trim((string) input('due_date')) ?: null;
    if ($id > 0) gb_update_assessment($id, $title, $category, $max, $due);
    else gb_add_assessment((int) $course['id'], $title, $category, $max, $due);
    audit('gradebook.assessment', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $title]);
    flash('Assessment saved.');
    redirect('/admin/gradebook/' . $p['slug']);
});

// Delete an assessment (column) + its scores.
route('POST', '/admin/gradebook/{slug}/assessment/{id}/delete', function ($p) {
    csrf_check();
    require_admin();
    gb_delete_assessment((int) $p['id']);
    audit('gradebook.assessment_delete', ['target_type' => 'course', 'target_id' => $p['slug'], 'detail' => $p['id']]);
    flash('Assessment removed.');
    redirect('/admin/gradebook/' . $p['slug']);
});

// Save a single cell (AJAX). Body: assessment_id, user_id, points ('' clears).
route('POST', '/api/gradebook/score', function () {
    require_admin();
    csrf_check();
    $aid = (int) input('assessment_id');
    $uid = (int) input('user_id');
    $raw = trim((string) input('points'));
    $a = gb_assessment($aid);
    if (!$a) json_out(['ok' => false, 'error' => 'Unknown assessment'], 404);
    $points = $raw === '' ? null : (float) $raw;
    if ($points !== null && $points < 0) $points = 0.0;
    gb_set_score($aid, $uid, $points);
    // Recompute the learner's overall for live display.
    $cid = (int) $a['course_id'];
    $assessments = gb_assessments($cid);
    $scores = gb_scores_for_course($cid);
    $pct = gb_overall($uid, $assessments, $scores);
    json_out(['ok' => true, 'overall' => $pct, 'letter' => gb_letter($pct)]);
});

// --- LTI 1.3 platform registration (admin) ----------------------------------

route('GET', '/admin/lti', function () {
    require_admin();
    view('admin/lti', ['platforms' => lti_platforms_all(), 'tool' => lti_tool_urls(), 'keys' => lti_keys()], 'LTI 1.3');
});

route('POST', '/admin/lti', function () {
    csrf_check();
    require_admin();
    $id = lti_platform_create([
        'name' => input('name'), 'issuer' => input('issuer'), 'client_id' => input('client_id'),
        'deployment_id' => input('deployment_id'), 'auth_login_url' => input('auth_login_url'),
        'auth_token_url' => input('auth_token_url'), 'jwks_url' => input('jwks_url'),
        'public_key' => trim((string) ($_POST['public_key'] ?? '')),
    ]);
    audit('lti.platform.create', ['target_type' => 'lti_platform', 'target_id' => (string) $id, 'detail' => input('issuer')]);
    flash('LTI platform registered.');
    redirect('/admin/lti');
});

route('POST', '/admin/lti/{id}/delete', function ($p) {
    csrf_check();
    require_admin();
    lti_platform_delete((int) $p['id']);
    audit('lti.platform.delete', ['target_type' => 'lti_platform', 'target_id' => (string) $p['id']]);
    flash('LTI platform removed.');
    redirect('/admin/lti');
});

// --- API key management ------------------------------------------------------

route('GET', '/admin/api-keys', function () {
    require_admin();
    $new = $_SESSION['new_api_key'] ?? null;
    unset($_SESSION['new_api_key']);
    view('admin/api_keys', ['keys' => api_keys_all(), 'new' => $new], 'API keys');
});

route('POST', '/admin/api-keys', function () {
    csrf_check();
    require_admin();
    $scopes = array_values(array_intersect(array_filter((array)($_POST['scopes'] ?? []), 'is_string'), api_key_scopes()));
    if (!$scopes) { flash('Select at least one API scope.', 'error'); redirect('/admin/api-keys'); }
    [$id, $token] = api_key_create(input('name'), (int) current_user()['id'], $scopes);
    $_SESSION['new_api_key'] = $token;
    audit('api_key.create', ['target_type' => 'api_key', 'target_id' => (string) $id, 'detail' => input('name')]);
    flash('API key created — copy it now; it won\'t be shown again.');
    redirect('/admin/api-keys');
});

route('POST', '/admin/api-keys/{id}/revoke', function ($p) {
    csrf_check();
    require_admin();
    api_key_revoke((int) $p['id']);
    audit('api_key.revoke', ['target_type' => 'api_key', 'target_id' => (string) $p['id']]);
    flash('API key revoked.');
    redirect('/admin/api-keys');
});

// --- Branding / white-label settings ----------------------------------------

route('GET', '/admin/settings', function () {
    require_admin();
    view('admin/settings', ['cfg' => lms_config(), 's' => all_settings()], 'Branding & settings');
});

route('POST', '/admin/settings', function () {
    csrf_check();
    require_admin();
    foreach (['app_name', 'app_tagline', 'org_name', 'catalog_name', 'brand_mark', 'cert_signatory',
              'cert_signatory_title', 'cert_provider', 'cert_website', 'default_locale'] as $k) {
        set_setting($k, trim((string) ($_POST[$k] ?? '')));
    }
    foreach (['theme_primary', 'theme_accent'] as $k) {
        $v = trim((string) ($_POST[$k] ?? ''));
        if ($v !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) $v = '';
        set_setting($k, $v);
    }
    // Global default for new/unset courses (checkbox: absent = off).
    set_setting('sequential_default', !empty($_POST['sequential_default']) ? '1' : '0');
    // Open self-registration on/off (checkbox: absent = off). People with a
    // valid enrollment code can always create an account regardless of this.
    set_setting('allow_self_registration', !empty($_POST['allow_self_registration']) ? '1' : '0');
    // Idle-session timeout in minutes (0 = never). Clamp to a sane range so a
    // typo can't lock everyone out (or effectively disable it) by accident.
    $idleMin = max(0, min(1440, (int) input('session_idle_timeout_minutes')));
    set_setting('session_idle_timeout_minutes', (string) $idleMin);
    // Pre-expiry reminder milestones (days before access ends). Sanitize to a
    // sorted, de-duplicated list of positive integers; empty falls back to default.
    $days = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) ($_POST['expiry_reminder_days'] ?? ''))),
        fn($d) => $d > 0)));
    rsort($days);
    set_setting('expiry_reminder_days', $days ? implode(',', $days) : '30,7,1');
    if (!empty($_POST['remove_logo'])) {
        $old = setting('logo');
        if ($old !== '') @unlink(lms_config()['data_dir'] . '/branding/' . $old);
        set_setting('logo', '');
    } elseif (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)
            && ($ext === 'svg' || @getimagesize($_FILES['logo']['tmp_name']) !== false)) {
            $dir = lms_config()['data_dir'] . '/branding';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $name = 'logo-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $name)) {
                $old = setting('logo');
                if ($old !== '' && $old !== $name) @unlink($dir . '/' . $old);
                set_setting('logo', $name);
            }
        } else {
            flash('Logo must be PNG, JPG, WEBP, or SVG.', 'error');
        }
    }
    audit('settings.update');
    flash('Branding updated.');
    redirect('/admin/settings');
});

// Apply a theme preset (colors + font + corner radius).
route('POST', '/admin/settings/preset', function () {
    csrf_check();
    require_admin();
    $key = input('preset');
    $presets = theme_presets();
    if (!isset($presets[$key])) { flash('Unknown theme.', 'error'); redirect('/admin/settings'); }
    $p = $presets[$key];
    set_setting('theme_primary', $p['primary']);
    set_setting('theme_accent', $p['accent']);
    set_setting('theme_font', $p['font']);
    set_setting('theme_radius', (string) $p['radius']);
    audit('settings.theme_preset', ['detail' => $key]);
    flash('Applied the "' . $p['label'] . '" theme.');
    redirect('/admin/settings');
});

// --- Mail (SMTP) status + test send -------------------------------------------
// The actual host/user/app-password live in config.local.php (a secret, so —
// like the MySQL password and LTI keys — it's never exposed through the DB-backed
// Settings page). This page is read-only status + a live test send so an admin
// can verify Gmail/SMTP setup without shell access or triggering a real
// password-reset email.
route('GET', '/admin/mail', function () {
    require_admin();
    $cfg = mail_config();
    view('admin/mail', ['cfg' => $cfg], 'Mail (SMTP)');
});

route('POST', '/admin/mail/test', function () {
    csrf_check();
    require_admin();
    $to = trim((string) input('to'));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('Enter a valid email address to send the test to.', 'error');
        redirect('/admin/mail');
    }
    $cfg = mail_config();
    [$ok, $detail] = mail_send_diagnostic($to,
        'Test email from ' . ($cfg['from_name'] ?: lms_config()['app_name']),
        "This is a test message from " . lms_config()['app_name'] . ".\n\n"
        . "If you received this, outgoing mail is configured correctly.\n\n"
        . 'Sent by ' . (current_user()['email'] ?? 'an administrator') . ' via Admin -> Mail.');
    audit('mail.test', ['detail' => ($ok ? 'ok: ' : 'failed: ') . $detail]);
    flash(($ok ? 'Test email sent to ' . $to . '. ' : 'Test email failed. ') . $detail, $ok ? 'success' : 'error');
    redirect('/admin/mail');
});

// --- Terms of Service / Privacy Policy editor ---------------------------------
// A rich-text (contenteditable) editor for the public /terms + /privacy page.
// Saving writes a sanitized HTML override to <data_dir>/legal/terms-privacy.html,
// which legal_content_html() prefers over the shipped Markdown once present —
// so an admin can revise the policy from the web, no file access required.
route('GET', '/admin/legal-editor', function () {
    require_admin();
    $cfg = lms_config();
    $dir = rtrim($cfg['data_dir'] ?? '', '/') . '/legal';
    $htmlFile = $dir . '/terms-privacy.html';
    $isOverride = is_file($htmlFile);
    $bodyHtml = $isOverride ? (string) file_get_contents($htmlFile) : legal_content_html();
    view('admin/richdoc_editor', [
        'title' => 'Terms of Service & Privacy Policy',
        'subtitle' => 'Edited here, this becomes the live content at /terms and /privacy — no file access needed.',
        'saveUrl' => '/admin/legal-editor',
        'resetUrl' => '/admin/legal-editor/reset',
        'backUrl' => '/admin',
        'backLabel' => '← Back to Admin',
        'bodyHtml' => $bodyHtml,
        'isOverride' => $isOverride,
    ], 'Terms & Privacy');
});

route('POST', '/admin/legal-editor', function () {
    require_admin();
    csrf_check();
    $raw = (string) input('body_html');
    // Bounds a disk-fill DoS from a compromised/malicious admin session — this
    // is a single rich-text page, not a file upload; 2MB is generous headroom.
    if (strlen($raw) > 2_000_000) json_out(['ok' => false, 'error' => 'Content is too large (max 2MB).'], 413);
    $cfg = lms_config();
    $dir = rtrim($cfg['data_dir'] ?? '', '/') . '/legal';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $html = editor_sanitize_html($raw);
    file_put_contents($dir . '/terms-privacy.html', $html);
    audit('legal.edit', ['target_type' => 'legal', 'target_id' => 'terms-privacy']);
    json_out(['ok' => true]);
});

route('POST', '/admin/legal-editor/reset', function () {
    require_admin();
    csrf_check();
    $cfg = lms_config();
    $file = rtrim($cfg['data_dir'] ?? '', '/') . '/legal/terms-privacy.html';
    if (is_file($file)) unlink($file);
    audit('legal.reset', ['target_type' => 'legal', 'target_id' => 'terms-privacy']);
    flash('Reverted to the shipped default. Your previous edits were discarded.');
    redirect('/admin/legal-editor');
});

// --- Help pages editor ---------------------------------------------------------
// Same rich-text editor, reused for the shipped Help manual. Saving writes a
// sanitized HTML override to <data_dir>/help-overrides/{slug}.html, which
// help_render() prefers over the shipped Markdown once present.
route('GET', '/admin/help-editor', function () {
    require_admin();
    $overrideDir = help_override_dir();
    $pages = array_map(function ($p) use ($overrideDir) {
        $p['edited'] = is_file($overrideDir . '/' . $p['slug'] . '.html');
        return $p;
    }, help_pages());
    view('admin/help_editor_index', ['pages' => $pages, 'sections' => help_sections()], 'Help pages');
});

route('GET', '/admin/help-editor/{slug}', function ($p) {
    require_admin();
    $page = help_find($p['slug']);
    if (!$page) { http_response_code(404); view('error', ['code' => 404, 'message' => 'Help topic not found.'], 'Not found'); return; }
    $override = help_override_dir() . '/' . $p['slug'] . '.html';
    $isOverride = is_file($override);
    $bodyHtml = $isOverride ? (string) file_get_contents($override) : help_render($p['slug']);
    view('admin/richdoc_editor', [
        'title' => $page['title'],
        'subtitle' => 'Edited here, this becomes the live Help page at /help/' . $p['slug'] . ' — no file access needed.',
        'saveUrl' => '/admin/help-editor/' . $p['slug'],
        'resetUrl' => '/admin/help-editor/' . $p['slug'] . '/reset',
        'backUrl' => '/admin/help-editor',
        'backLabel' => '← Back to Help pages',
        'bodyHtml' => $bodyHtml,
        'isOverride' => $isOverride,
    ], $page['title']);
});

route('POST', '/admin/help-editor/{slug}', function ($p) {
    require_admin();
    csrf_check();
    if (!help_find($p['slug'])) json_out(['ok' => false, 'error' => 'Help topic not found.'], 404);
    $raw = (string) input('body_html');
    if (strlen($raw) > 2_000_000) json_out(['ok' => false, 'error' => 'Content is too large (max 2MB).'], 413);
    $dir = help_override_dir();
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $html = editor_sanitize_html($raw);
    file_put_contents($dir . '/' . $p['slug'] . '.html', $html);
    audit('help.edit', ['target_type' => 'help', 'target_id' => $p['slug']]);
    json_out(['ok' => true]);
});

route('POST', '/admin/help-editor/{slug}/reset', function ($p) {
    require_admin();
    csrf_check();
    $file = help_override_dir() . '/' . $p['slug'] . '.html';
    if (is_file($file)) unlink($file);
    audit('help.reset', ['target_type' => 'help', 'target_id' => $p['slug']]);
    flash('Reverted to the shipped default. Your previous edits were discarded.');
    redirect('/admin/help-editor/' . $p['slug']);
});

// --- Software updates (WordPress-style code update via the web) ---------------
route('GET', '/admin/update', function () {
    require_admin();
    require_once dirname(__DIR__) . '/app/updater.php';
    view('admin/update', [
        'version'       => update_current_version(),
        'schema'        => DB_SCHEMA_VERSION,
        'backups'       => list_backups(),
        'writable'      => is_writable(update_code_root()),
        'code_root'     => update_code_root(),
        'edition'       => (string) (lms_config()['edition_label'] ?? ''),
    ], 'Software updates');
});

// Apply an uploaded update package.
route('POST', '/admin/update', function () {
    require_admin();
    csrf_check();
    require_once dirname(__DIR__) . '/app/updater.php';
    @set_time_limit(0);
    if (empty($_FILES['package']['tmp_name']) || !is_uploaded_file($_FILES['package']['tmp_name'])) {
        flash('Choose an update package (.tar.gz) to upload.', 'error'); redirect('/admin/update');
    }
    $force = !empty($_POST['force']);
    [$ok, $msg, $meta] = apply_update_package($_FILES['package']['tmp_name'], $_FILES['package']['name'], $force);
    audit('app.update', ['detail' => ($ok ? 'ok ' : 'fail ') . ($meta['from'] ?? '?') . '->' . ($meta['to'] ?? ($meta['version'] ?? '?'))]);
    flash($msg, $ok ? 'success' : 'error');
    redirect('/admin/update');
});

// Build + download a distributable update package from THIS install's code.
route('GET', '/admin/update/download', function () {
    require_admin();
    require_once dirname(__DIR__) . '/app/updater.php';
    @set_time_limit(0);
    try {
        $tmp = io_tmp_dir('build-');
        $file = build_update_package($tmp);
    } catch (Throwable $e) {
        flash('Build failed: ' . $e->getMessage(), 'error'); redirect('/admin/update');
    }
    audit('app.update.build', ['detail' => 'v' . update_current_version()]);
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    @unlink($file); @rmdir(dirname($file));
    exit;
});

// Roll back to a previous code backup.
route('POST', '/admin/update/rollback', function () {
    require_admin();
    csrf_check();
    require_once dirname(__DIR__) . '/app/updater.php';
    @set_time_limit(0);
    [$ok, $msg] = rollback_update(input('file') !== '' ? input('file') : null);
    audit('app.update.rollback', ['detail' => $ok ? 'ok' : 'fail']);
    flash($msg, $ok ? 'success' : 'error');
    redirect('/admin/update');
});

// Serve the uploaded brand logo (public — it appears in the nav/splash).
route('GET', '/brand/logo', function () {
    $name = lms_config()['logo'];   // config.local.php default OR admin-uploaded (DB)
    if ($name === '') { http_response_code(404); exit; }
    $dir = rtrim(lms_config()['data_dir'], '/') . '/branding';
    $file = realpath($dir . '/' . $name);
    // realpath() containment: defense-in-depth in case the stored logo value
    // is ever attacker-influenced (e.g. via a future DB-write bug) rather than
    // only the current admin-upload path — a traversal value like
    // "../../../etc/passwd" resolves outside $dir and is rejected here.
    if ($file === false || !is_file($file) || !str_starts_with($file, realpath($dir) . DIRECTORY_SEPARATOR)) { http_response_code(404); exit; }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','svg'=>'image/svg+xml'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    if ($ext === 'svg') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
});

// --- Audit log ---------------------------------------------------------------

route('GET', '/admin/audit', function () {
    require_admin();
    $q = input('q'); $action = input('action');
    $rows = audit_recent(500, $q, $action);
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sapiqo-audit.csv"');
        $fh = fopen('php://output', 'w');
        csv_out($fh, ['When (UTC)', 'Actor', 'Action', 'Target type', 'Target', 'Detail', 'IP']);
        foreach ($rows as $r) {
            csv_out($fh, [$r['created_at'], $r['actor_email'], $r['action'], $r['target_type'], $r['target_id'], $r['detail'], $r['ip']]);
        }
        exit;
    }
    view('admin/audit', ['rows' => $rows, 'q' => $q, 'action' => $action, 'actions' => audit_actions()], 'Audit log');
});

// --- Groups ------------------------------------------------------------------

route('GET', '/admin/groups', function () {
    require_admin();
    view('admin/groups', ['groups' => groups_with_stats()], 'Groups');
});

route('POST', '/admin/groups', function () {
    csrf_check();
    require_admin();
    $name = input('name');
    if ($name === '') { flash('Enter a group name.', 'error'); redirect('/admin/groups'); }
    $id = create_group($name, input('description'));
    audit('group.create', ['target_type' => 'group', 'target_id' => (string) $id, 'detail' => $name]);
    flash('Group created.');
    redirect('/admin/groups/' . $id);
});

// Auto-create a group per distinct organization and add its users (districts).
route('POST', '/admin/groups/from-organizations', function () {
    csrf_check();
    require_admin();
    $orgs = db_all("SELECT DISTINCT organization FROM users WHERE organization <> ''");
    $created = 0;
    foreach ($orgs as $o) {
        $gid = create_group($o['organization']);
        $created++;
        foreach (db_all('SELECT id FROM users WHERE organization = ?', [$o['organization']]) as $u) {
            add_member($gid, (int) $u['id']);
        }
    }
    flash("Synced $created group(s) from organizations.");
    redirect('/admin/groups');
});

// Auto-create a group per distinct campus (named "Organization · Campus").
route('POST', '/admin/groups/from-campuses', function () {
    csrf_check();
    require_admin();
    $rows = db_all("SELECT DISTINCT campus, organization FROM users WHERE campus <> ''");
    $created = 0;
    foreach ($rows as $r) {
        $name = trim(($r['organization'] !== '' ? $r['organization'] . ' · ' : '') . $r['campus']);
        $gid = create_group($name);
        $created++;
        foreach (db_all('SELECT id FROM users WHERE campus = ? AND organization = ?',
                        [$r['campus'], $r['organization']]) as $u) {
            add_member($gid, (int) $u['id']);
        }
    }
    flash("Synced $created group(s) from campuses.");
    redirect('/admin/groups');
});

route('POST', '/admin/groups/{id}/delete', function ($p) {
    csrf_check();
    require_admin();
    delete_group((int) $p['id']);
    audit('group.delete', ['target_type' => 'group', 'target_id' => (string) $p['id']]);
    flash('Group deleted.');
    redirect('/admin/groups');
});

route('POST', '/admin/groups/{id}/add', function ($p) {
    csrf_check();
    require_admin();
    $gid = (int) $p['id'];
    // Add by comma/space/newline-separated emails.
    $added = 0; $missing = [];
    foreach (preg_split('/[\s,;]+/', input('emails')) as $em) {
        $em = strtolower(trim($em));
        if ($em === '') continue;
        $u = db_one('SELECT id FROM users WHERE email = ?', [$em]);
        if ($u) { add_member($gid, (int) $u['id']); $added++; }
        else $missing[] = $em;
    }
    flash("Added $added member(s)." . ($missing ? ' Not found: ' . implode(', ', $missing) : ''),
        $missing ? 'error' : 'success');
    redirect('/admin/groups/' . $gid);
});

route('POST', '/admin/groups/{id}/remove', function ($p) {
    csrf_check();
    require_admin();
    remove_member((int) $p['id'], (int) input('user_id'));
    flash('Member removed.');
    redirect('/admin/groups/' . $p['id']);
});

// Assign a sub-admin (manager) to a group by email.
route('POST', '/admin/groups/{id}/add-manager', function ($p) {
    csrf_check();
    require_admin();
    $gid = (int) $p['id'];
    $em = strtolower(input('email'));
    $u = db_one('SELECT id FROM users WHERE email = ?', [$em]);
    if (!$u) { flash("No user found with email '$em'.", 'error'); redirect('/admin/groups/' . $gid); }
    add_manager($gid, (int) $u['id']);
    add_member($gid, (int) $u['id']);   // managers are also members of their group
    audit('group.add_manager', ['target_type' => 'group', 'target_id' => (string) $gid, 'detail' => $em]);
    flash('Manager assigned.');
    redirect('/admin/groups/' . $gid);
});

route('POST', '/admin/groups/{id}/remove-manager', function ($p) {
    csrf_check();
    require_admin();
    remove_manager((int) $p['id'], (int) input('user_id'));
    flash('Manager removed.');
    redirect('/admin/groups/' . $p['id']);
});

route('GET', '/admin/groups/{id}', function ($p) {
    require_admin();
    $g = group_by_id((int) $p['id']);
    if (!$g) { http_response_code(404); view('error', ['code'=>404,'message'=>'Group not found.'],'Not found'); return; }
    $courseId = input('course') !== '' ? (int) input('course') : null;
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="group-' . $g['id'] . '-completion.csv"');
        $fh = fopen('php://output', 'w');
        csv_out($fh, ['First name','Last name','Email','Organization','Enrollments','Completions','Steps completed','Badges']);
        foreach (group_members((int) $g['id']) as $m) {
            csv_out($fh, [$m['first_name'],$m['last_name'],$m['email'],$m['organization'],$m['enrollments'],$m['completions'],$m['steps'],$m['badges']]);
        }
        exit;
    }
    view('admin/group_detail', [
        'g' => $g,
        'members' => group_members((int) $g['id']),
        'managers' => managers_of((int) $g['id']),
        'stats' => group_stats((int) $g['id'], $courseId),
        'courses' => all_courses(false),
        'course_filter' => $courseId,
        'subs' => group_courses_list((int) $g['id']),
        'org' => org_of_group((int) $g['id']),
        'orgs' => all_orgs(),
    ], 'Group: ' . $g['name']);
});

// --- Central account management ----------------------------------------------
// One place to see and assign every privileged account: administrators, course
// developers (content authors), and group managers (with per-grant permissions).
route('GET', '/admin/accounts', function () {
    require_admin();
    $admins = db_all("SELECT * FROM users WHERE role = 'admin' ORDER BY last_name, first_name");
    $devs   = course_developers();
    $managers = db_all(
        "SELECT gm.*, u.first_name, u.last_name, u.email, u.can_edit_content, g.name AS group_name
         FROM group_managers gm
         JOIN users u ON u.id = gm.user_id
         JOIN user_groups g ON g.id = gm.group_id
         ORDER BY g.name, u.last_name, u.first_name");
    view('admin/accounts', [
        'admins' => $admins, 'devs' => $devs, 'managers' => $managers, 'groups' => all_groups(),
    ], 'Account management');
});

// Grant or revoke the course-developer capability by email.
route('POST', '/admin/accounts/course-dev', function () {
    csrf_check();
    require_admin();
    $email = strtolower(input('email'));
    $u = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u) { flash("No user found with email '$email'.", 'error'); redirect('/admin/accounts'); }
    if (($u['role'] ?? '') === 'admin') { flash('Administrators can already edit content.', 'error'); redirect('/admin/accounts'); }
    if (input('action') === 'revoke') { revoke_course_dev((int) $u['id']); $msg = 'Course-developer access removed.'; $d = 'revoke'; }
    else { grant_course_dev((int) $u['id']); $msg = 'Course-developer access granted.'; $d = 'grant'; }
    audit('account.course_dev', ['target_type' => 'user', 'target_id' => (string) $u['id'], 'detail' => $d]);
    flash($msg);
    redirect('/admin/accounts');
});

// Assign a group manager (sub-admin) with per-grant permissions.
route('POST', '/admin/accounts/manager', function () {
    csrf_check();
    require_admin();
    $email = strtolower(input('email'));
    $gid = (int) input('group_id');
    $u = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u) { flash("No user found with email '$email'.", 'error'); redirect('/admin/accounts'); }
    if (!group_by_id($gid)) { flash('Choose a group to manage.', 'error'); redirect('/admin/accounts'); }
    $pe = !empty($_POST['perm_enroll']);
    $pm = !empty($_POST['perm_members']);
    add_manager($gid, (int) $u['id'], $pe, $pm);
    add_member($gid, (int) $u['id']);                 // managers are members of their group
    if (!empty($_POST['allow_content'])) grant_course_dev((int) $u['id']);
    audit('account.manager.assign', ['target_type' => 'user', 'target_id' => (string) $u['id'],
        'detail' => "group=$gid enroll=" . (int) $pe . " members=" . (int) $pm]);
    flash('Group manager assigned.');
    redirect('/admin/accounts');
});

// Update an existing manager grant's permissions.
route('POST', '/admin/accounts/manager/perms', function () {
    csrf_check();
    require_admin();
    $uid = (int) input('user_id');
    $gid = (int) input('group_id');
    set_manager_perms($gid, $uid, !empty($_POST['perm_enroll']), !empty($_POST['perm_members']));
    audit('account.manager.perms', ['target_type' => 'user', 'target_id' => (string) $uid, 'detail' => "group=$gid"]);
    flash('Permissions updated.');
    redirect('/admin/accounts');
});

// Remove a group-manager grant (keeps the user and their group membership).
route('POST', '/admin/accounts/manager/remove', function () {
    csrf_check();
    require_admin();
    $uid = (int) input('user_id');
    $gid = (int) input('group_id');
    remove_manager($gid, $uid);
    audit('account.manager.remove', ['target_type' => 'user', 'target_id' => (string) $uid, 'detail' => "group=$gid"]);
    flash('Group manager removed.');
    redirect('/admin/accounts');
});

// --- Help center (in-app manual) --------------------------------------------

route('GET', '/help', function () {
    require_login();
    view('help', ['page' => null, 'html' => null, 'pages' => help_pages()], 'Help');
});

route('GET', '/help/{page}', function ($p) {
    require_login();
    $page = help_find($p['page']);
    if (!$page || !help_can_see($page)) {
        http_response_code(404);
        view('error', ['code' => 404, 'message' => 'Help topic not found.'], 'Not found');
        return;
    }
    $html = help_render($page['slug']);
    view('help', ['page' => $page, 'html' => $html, 'pages' => help_pages()], $page['title'] . ' · Help');
});

// --- Organizations (admin) ---------------------------------------------------

route('GET', '/admin/orgs', function () {
    require_admin();
    view('admin/orgs', ['orgs' => all_orgs()], 'Organizations');
});

route('POST', '/admin/orgs', function () {
    csrf_check();
    require_admin();
    $name = input('name');
    if ($name === '') { flash('Enter an organization name.', 'error'); redirect('/admin/orgs'); }
    $id = create_org($name, input('description'));
    audit('org.create', ['target_type' => 'org', 'target_id' => (string) $id, 'detail' => $name]);
    flash('Organization created.');
    redirect('/admin/orgs/' . $id);
});

route('POST', '/admin/orgs/{id}/delete', function ($p) {
    csrf_check();
    require_admin();
    delete_org((int) $p['id']);
    audit('org.delete', ['target_type' => 'org', 'target_id' => (string) $p['id']]);
    flash('Organization deleted (its groups and members were kept).');
    redirect('/admin/orgs');
});

route('POST', '/admin/orgs/{id}/rename', function ($p) {
    csrf_check();
    require_admin();
    rename_org((int) $p['id'], input('name'), input('description'));
    flash('Organization updated.');
    redirect('/admin/orgs/' . $p['id']);
});

route('GET', '/admin/orgs/{id}', function ($p) {
    require_admin();
    $o = org_by_id((int) $p['id']);
    if (!$o) { http_response_code(404); view('error', ['code'=>404,'message'=>'Organization not found.'],'Not found'); return; }
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="org-' . $o['id'] . '-members.csv"');
        $fh = fopen('php://output', 'w');
        csv_out($fh, ['First name','Last name','Email','Enrollments','Completions','Badges']);
        foreach (org_members((int) $o['id']) as $m) {
            csv_out($fh, [$m['first_name'],$m['last_name'],$m['email'],$m['enrollments'],$m['completions'],$m['badges']]);
        }
        exit;
    }
    view('admin/org_detail', [
        'o' => $o,
        'stats' => org_stats((int) $o['id']),
        'members' => org_members((int) $o['id']),
        'groups' => groups_in_org((int) $o['id']),
        'subs' => org_courses_list((int) $o['id']),
        'managers' => org_managers_of((int) $o['id']),
        'courses' => all_courses(false),
        'all_group_list' => all_groups(),
    ], 'Organization: ' . $o['name']);
});

// Add existing members by email (unknown emails are reported; use the CSV import
// to create new accounts en masse).
route('POST', '/admin/orgs/{id}/members/add', function ($p) {
    csrf_check();
    require_admin();
    $oid = (int) $p['id'];
    $added = 0; $missing = [];
    foreach (preg_split('/[\s,;]+/', input('emails')) as $em) {
        $em = strtolower(trim($em));
        if ($em === '') continue;
        $u = db_one('SELECT id FROM users WHERE email = ?', [$em]);
        if ($u) { org_add_member($oid, (int) $u['id']); $added++; }
        else $missing[] = $em;
    }
    flash("Added $added member(s)." . ($missing ? ' Not found: ' . implode(', ', $missing) : ''),
        $missing ? 'error' : 'success');
    redirect('/admin/orgs/' . $oid);
});

route('POST', '/admin/orgs/{id}/members/remove', function ($p) {
    csrf_check();
    require_admin();
    org_remove_member((int) $p['id'], (int) input('user_id'));
    flash('Member removed from organization.');
    redirect('/admin/orgs/' . $p['id']);
});

// Subscribe the whole org to one or more courses (auto-enrolls all members).
route('POST', '/admin/orgs/{id}/subscribe', function ($p) {
    csrf_check();
    require_admin();
    $oid = (int) $p['id'];
    $ids = array_map('intval', $_POST['course_ids'] ?? []);
    foreach ($ids as $cid) if ($cid > 0) org_subscribe($oid, $cid);
    audit('org.subscribe', ['target_type' => 'org', 'target_id' => (string) $oid, 'detail' => 'courses=' . implode(',', $ids)]);
    flash(count($ids) ? 'Subscribed the organization to ' . count($ids) . ' course(s); members enrolled.' : 'No course selected.',
        count($ids) ? 'success' : 'error');
    redirect('/admin/orgs/' . $oid);
});

route('POST', '/admin/orgs/{id}/unsubscribe', function ($p) {
    csrf_check();
    require_admin();
    org_unsubscribe((int) $p['id'], (int) input('course_id'));
    flash('Subscription removed. Existing learners keep their access and progress.');
    redirect('/admin/orgs/' . $p['id']);
});

// Create a new group inside this org, or attach an existing standalone group.
route('POST', '/admin/orgs/{id}/groups/create', function ($p) {
    csrf_check();
    require_admin();
    $oid = (int) $p['id'];
    $name = input('name');
    if ($name === '') { flash('Enter a group name.', 'error'); redirect('/admin/orgs/' . $oid); }
    $gid = create_group($name, input('description'));
    set_group_org($gid, $oid);
    flash('Group created in this organization.');
    redirect('/admin/groups/' . $gid);
});

route('POST', '/admin/orgs/{id}/groups/attach', function ($p) {
    csrf_check();
    require_admin();
    $oid = (int) $p['id'];
    $gid = (int) input('group_id');
    if ($gid > 0) { set_group_org($gid, $oid); flash('Group added to the organization.'); }
    redirect('/admin/orgs/' . $oid);
});

// Assign / update / remove an organization manager (per-grant permissions).
route('POST', '/admin/orgs/{id}/manager/add', function ($p) {
    csrf_check();
    require_admin();
    $oid = (int) $p['id'];
    $em = strtolower(input('email'));
    $u = db_one('SELECT id FROM users WHERE email = ?', [$em]);
    if (!$u) { flash("No user found with email '$em'.", 'error'); redirect('/admin/orgs/' . $oid); }
    add_org_manager($oid, (int) $u['id'], !empty($_POST['perm_enroll']), !empty($_POST['perm_members']), !empty($_POST['perm_courses']));
    org_add_member($oid, (int) $u['id']);   // a manager is also a member of their org
    audit('org.manager.assign', ['target_type' => 'user', 'target_id' => (string) $u['id'], 'detail' => "org=$oid"]);
    flash('Organization manager assigned.');
    redirect('/admin/orgs/' . $oid);
});

route('POST', '/admin/orgs/{id}/manager/remove', function ($p) {
    csrf_check();
    require_admin();
    remove_org_manager((int) $p['id'], (int) input('user_id'));
    flash('Organization manager removed.');
    redirect('/admin/orgs/' . $p['id']);
});

// --- Group course subscriptions (admin) -------------------------------------

route('POST', '/admin/groups/{id}/subscribe', function ($p) {
    csrf_check();
    require_admin();
    $gid = (int) $p['id'];
    $ids = array_map('intval', $_POST['course_ids'] ?? []);
    foreach ($ids as $cid) if ($cid > 0) group_subscribe($gid, $cid);
    audit('group.subscribe', ['target_type' => 'group', 'target_id' => (string) $gid, 'detail' => 'courses=' . implode(',', $ids)]);
    flash(count($ids) ? 'Subscribed the group to ' . count($ids) . ' course(s); members enrolled.' : 'No course selected.',
        count($ids) ? 'success' : 'error');
    redirect('/admin/groups/' . $gid);
});

route('POST', '/admin/groups/{id}/unsubscribe', function ($p) {
    csrf_check();
    require_admin();
    group_unsubscribe((int) $p['id'], (int) input('course_id'));
    flash('Subscription removed. Existing learners keep their access and progress.');
    redirect('/admin/groups/' . $p['id']);
});

route('POST', '/admin/groups/{id}/set-org', function ($p) {
    csrf_check();
    require_admin();
    $oid = (int) input('org_id');
    set_group_org((int) $p['id'], $oid ?: null);
    flash($oid ? 'Group linked to organization.' : 'Group detached from organization.');
    redirect('/admin/groups/' . $p['id']);
});

// --- Group manager (sub-admin) area -----------------------------------------

route('GET', '/manage', function () {
    require_login();
    $u = current_user();
    if (is_admin() && !manages_any_groups() && !manages_any_orgs()) redirect('/admin/groups');
    view('manage/home', [
        'groups' => managed_groups((int) $u['id']),
        'orgs' => managed_orgs((int) $u['id']),
        'is_admin' => is_admin(),
    ], 'Manage');
});

// Org manager view: members, groups, and course subscriptions for one org.
route('GET', '/manage/orgs/{id}', function ($p) {
    $oid = (int) $p['id'];
    require_org_access($oid);
    $o = org_by_id($oid);
    if (!$o) { http_response_code(404); view('error', ['code'=>404,'message'=>'Organization not found.'],'Not found'); return; }
    view('manage/org', [
        'o' => $o,
        'stats' => org_stats($oid),
        'members' => org_members($oid),
        'groups' => groups_in_org($oid),
        'subs' => org_courses_list($oid),
        'courses' => all_courses(false),
        'can_members' => manager_allows_org($oid, 'members'),
        'can_courses' => manager_allows_org($oid, 'courses'),
    ], 'Manage: ' . $o['name']);
});

// Org manager: add members (creates accounts for unknown emails) — perm_members.
route('POST', '/manage/orgs/{id}/members/add', function ($p) {
    csrf_check();
    $oid = (int) $p['id'];
    require_org_access($oid);
    if (!manager_allows_org($oid, 'members')) { http_response_code(403); exit('Forbidden: you cannot add members to this organization.'); }
    $added = 0; $created = [];
    foreach (preg_split('/[\s,;]+/', input('emails')) as $em) {
        $em = strtolower(trim($em));
        if ($em === '' || !valid_email($em)) continue;
        $user = db_one('SELECT * FROM users WHERE email = ?', [$em]);
        if (!$user) {
            $tmp = bin2hex(random_bytes(4));
            [$ok, $res] = register_local(['email' => $em, 'password' => $tmp . 'Aa1', 'role' => 'learner']);
            if (!$ok) continue;
            $created[] = ['email' => $em, 'temp_password' => $tmp . 'Aa1'];
            $user = db_one('SELECT * FROM users WHERE id = ?', [$res]);
        }
        org_add_member($oid, (int) $user['id']);
        $added++;
    }
    if ($created) $_SESSION['manage_created'] = $created;
    flash("Added $added member(s)." . ($created ? ' ' . count($created) . ' new account(s) created — see temporary passwords.' : ''));
    redirect('/manage/orgs/' . $oid);
});

route('POST', '/manage/orgs/{id}/members/remove', function ($p) {
    csrf_check();
    $oid = (int) $p['id'];
    require_org_access($oid);
    if (!manager_allows_org($oid, 'members')) { http_response_code(403); exit('Forbidden.'); }
    org_remove_member($oid, (int) input('user_id'));
    flash('Member removed from organization.');
    redirect('/manage/orgs/' . $oid);
});

// Org manager: manage course subscriptions — perm_courses.
route('POST', '/manage/orgs/{id}/subscribe', function ($p) {
    csrf_check();
    $oid = (int) $p['id'];
    require_org_access($oid);
    if (!manager_allows_org($oid, 'courses')) { http_response_code(403); exit('Forbidden: you cannot manage this organization\'s courses.'); }
    $ids = array_map('intval', $_POST['course_ids'] ?? []);
    foreach ($ids as $cid) if ($cid > 0) org_subscribe($oid, $cid);
    flash(count($ids) ? 'Subscribed to ' . count($ids) . ' course(s); members enrolled.' : 'No course selected.',
        count($ids) ? 'success' : 'error');
    redirect('/manage/orgs/' . $oid);
});

route('POST', '/manage/orgs/{id}/unsubscribe', function ($p) {
    csrf_check();
    $oid = (int) $p['id'];
    require_org_access($oid);
    if (!manager_allows_org($oid, 'courses')) { http_response_code(403); exit('Forbidden.'); }
    org_unsubscribe($oid, (int) input('course_id'));
    flash('Subscription removed. Existing learners keep their access.');
    redirect('/manage/orgs/' . $oid);
});

route('GET', '/manage/groups/{id}', function ($p) {
    $gid = (int) $p['id'];
    require_group_access($gid);
    $g = group_by_id($gid);
    if (!$g) { http_response_code(404); view('error', ['code'=>404,'message'=>'Group not found.'],'Not found'); return; }
    $courseId = input('course') !== '' ? (int) input('course') : null;
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="group-' . $gid . '-completion.csv"');
        $fh = fopen('php://output', 'w');
        csv_out($fh, ['First name','Last name','Email','Campus','Organization','Enrollments','Completions','Steps completed','Badges']);
        foreach (group_members($gid) as $m) {
            csv_out($fh, [$m['first_name'],$m['last_name'],$m['email'],$m['campus'],$m['organization'],$m['enrollments'],$m['completions'],$m['steps'],$m['badges']]);
        }
        exit;
    }
    view('manage/group', [
        'g' => $g, 'members' => group_members($gid), 'stats' => group_stats($gid, $courseId),
        'courses' => all_courses(false), 'course_filter' => $courseId,
        'can_members' => manager_allows_group($gid, 'members'),
        'can_courses' => manager_allows_group($gid, 'courses'),
        'subs' => group_courses_list($gid),
    ], 'Manage: ' . $g['name']);
});

// Add members to a managed group — creates accounts for unknown emails.
route('POST', '/manage/groups/{id}/add', function ($p) {
    csrf_check();
    $gid = (int) $p['id'];
    require_group_access($gid);
    if (!manager_allows_group($gid, 'members')) { http_response_code(403); exit('Forbidden: you cannot add members to this group.'); }
    $added = 0; $created = [];
    foreach (preg_split('/[\s,;]+/', input('emails')) as $em) {
        $em = strtolower(trim($em));
        if ($em === '' || !valid_email($em)) continue;
        $user = db_one('SELECT * FROM users WHERE email = ?', [$em]);
        if (!$user) {
            $tmp = bin2hex(random_bytes(4));
            [$ok, $res] = register_local(['email' => $em, 'password' => $tmp . 'Aa1', 'role' => 'learner']);
            if (!$ok) continue;
            $created[] = ['email' => $em, 'temp_password' => $tmp . 'Aa1'];
            $user = db_one('SELECT * FROM users WHERE id = ?', [$res]);
        }
        add_member($gid, (int) $user['id']);
        $added++;
    }
    if ($created) $_SESSION['manage_created'] = $created;
    flash("Added $added member(s)." . ($created ? ' ' . count($created) . ' new account(s) created — see temporary passwords.' : ''));
    redirect('/manage/groups/' . $gid);
});

route('POST', '/manage/groups/{id}/remove', function ($p) {
    csrf_check();
    $gid = (int) $p['id'];
    require_group_access($gid);
    if (!manager_allows_group($gid, 'members')) { http_response_code(403); exit('Forbidden: you cannot change members of this group.'); }
    remove_member($gid, (int) input('user_id'));
    flash('Member removed from group.');
    redirect('/manage/groups/' . $gid);
});

// Group manager: manage this group's course subscriptions — perm_courses.
route('POST', '/manage/groups/{id}/subscribe', function ($p) {
    csrf_check();
    $gid = (int) $p['id'];
    require_group_access($gid);
    if (!manager_allows_group($gid, 'courses')) { http_response_code(403); exit('Forbidden: you cannot manage this group\'s courses.'); }
    $ids = array_map('intval', $_POST['course_ids'] ?? []);
    foreach ($ids as $cid) if ($cid > 0) group_subscribe($gid, $cid);
    flash(count($ids) ? 'Subscribed to ' . count($ids) . ' course(s); members enrolled.' : 'No course selected.',
        count($ids) ? 'success' : 'error');
    redirect('/manage/groups/' . $gid);
});

route('POST', '/manage/groups/{id}/unsubscribe', function ($p) {
    csrf_check();
    $gid = (int) $p['id'];
    require_group_access($gid);
    if (!manager_allows_group($gid, 'courses')) { http_response_code(403); exit('Forbidden.'); }
    group_unsubscribe($gid, (int) input('course_id'));
    flash('Subscription removed. Existing learners keep their access.');
    redirect('/manage/groups/' . $gid);
});

route('GET', '/manage/users/{id}', function ($p) {
    $id = (int) $p['id'];
    require_user_access($id);
    $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) { http_response_code(404); exit('User not found'); }
    $courses = all_courses(false);
    foreach ($courses as &$c) {
        $c['enrolled'] = is_enrolled($id, (int) $c['id']);
        $c['percent']  = course_percent($id, $c);
    }
    unset($c);
    view('manage/user_edit', [
        'u' => $u, 'courses' => $courses,
        'can_members' => manager_allows_user($id, 'members'),
        'can_enroll'  => manager_allows_user($id, 'enroll'),
    ], 'Edit member');
});

route('POST', '/manage/users/{id}', function ($p) {
    csrf_check();
    $id = (int) $p['id'];
    require_user_access($id);
    $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) { http_response_code(404); exit('User not found'); }

    $canMembers = manager_allows_user($id, 'members');
    $canEnroll  = manager_allows_user($id, 'enroll');
    if (!$canMembers && !$canEnroll) { http_response_code(403); exit('Forbidden: you do not have permission to edit this member.'); }

    // Profile fields + password (requires the "members" permission).
    if ($canMembers) {
        db_run('UPDATE users SET first_name=?, last_name=?, phone=?, user_type=?, campus=?, organization=?, updated_at=? WHERE id=?', [
            input('first_name'), input('last_name'), input('phone'),
            input('user_type'), input('campus'), input('organization'), now_utc(), $id,
        ]);
        $pw = (string) ($_POST['password'] ?? '');
        if ($pw !== '' && strlen($pw) >= 8) {
            set_user_password($id, $pw);
        }
    }
    // Enrollments (requires the "enroll" permission).
    if ($canEnroll) {
        $checked = array_map('intval', $_POST['enroll'] ?? []);
        foreach (all_courses(false) as $c) {
            $cid = (int) $c['id']; $want = in_array($cid, $checked, true); $has = is_enrolled($id, $cid);
            if ($want && !$has) enroll($id, $cid);
            elseif (!$want && $has) unenroll($id, $cid);
        }
    }
    flash('Member updated.');
    redirect('/manage/users/' . $id);
});

// Full user editor: all fields + enrollment management.
route('GET', '/admin/users/{id}', function ($p) {
    require_admin();
    $u = db_one('SELECT * FROM users WHERE id = ?', [(int) $p['id']]);
    if (!$u) { http_response_code(404); view('error', ['code'=>404,'message'=>'User not found.'],'Not found'); return; }
    $courses = all_courses(false);
    foreach ($courses as &$c) {
        $c['enrolled'] = is_enrolled((int) $u['id'], (int) $c['id']);
        $c['percent']  = course_percent((int) $u['id'], $c);
        $qr = quiz_results_for((int) $u['id'], (int) $c['id']);
        $c['quiz_attempted'] = count($qr);
        $c['quiz_passed'] = count(array_filter($qr, fn($r) => (int) $r['passed'] === 1));
    }
    unset($c);
    $groups = all_groups();
    $userGroupIds = array_map('intval', array_column(groups_for_user((int) $u['id']), 'id'));
    view('admin/user_edit', ['u' => $u, 'courses' => $courses,
        'groups' => $groups, 'userGroupIds' => $userGroupIds], 'Edit user');
});

route('POST', '/admin/users/{id}', function ($p) {
    csrf_check();
    require_admin();
    $id = (int) $p['id'];
    $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) { http_response_code(404); exit('User not found'); }

    // Email (unique) — only change if provided and different.
    $email = strtolower(input('email'));
    if ($email === '' || !valid_email($email)) {
        flash('Enter a valid email address.', 'error');
        redirect('/admin/users/' . $id);
    }
    if ($email !== $u['email'] && db_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
        flash('That email is already used by another account.', 'error');
        redirect('/admin/users/' . $id);
    }

    // Don't let an admin strip their own admin role (avoid lockout).
    $role = input('role') === 'admin' ? 'admin' : 'learner';
    $me = current_user();
    if ((int) $me['id'] === $id && $role !== 'admin') {
        $role = 'admin';
        flash('You cannot remove your own administrator role.', 'error');
    }

    db_run('UPDATE users SET first_name=?, last_name=?, email=?, phone=?, user_type=?, campus=?, organization=?, role=?, updated_at=? WHERE id=?', [
        input('first_name'), input('last_name'), $email, input('phone'),
        input('user_type'), input('campus'), input('organization'), $role, now_utc(), $id,
    ]);

    // Optional password reset.
    $pw = (string) ($_POST['password'] ?? '');
    if ($pw !== '') {
        if (strlen($pw) < 8) {
            flash('Password not changed: use at least 8 characters.', 'error');
            redirect('/admin/users/' . $id);
        }
        set_user_password($id, $pw);
    }

    // Sync enrollments to the checked set.
    $checked = array_map('intval', $_POST['enroll'] ?? []);
    $purge = !empty($_POST['purge_on_unenroll']);
    foreach (all_courses(false) as $c) {
        $cid = (int) $c['id'];
        $want = in_array($cid, $checked, true);
        $has  = is_enrolled($id, $cid);
        if ($want && !$has) enroll($id, $cid);
        elseif (!$want && $has) unenroll($id, $cid, $purge);
    }

    // Sync group membership to the checked set.
    set_user_groups($id, array_map('intval', $_POST['groups'] ?? []));

    audit('user.edit', ['target_type' => 'user', 'target_id' => (string) $id,
        'detail' => "email=$email role=$role enrollments=" . count($checked)]);
    flash('User updated.');
    redirect('/admin/users/' . $id);
});

// Per-user, per-course progress management: review where a learner is, mark
// individual lessons/topics (or the whole course) complete, enroll/disenroll.
route('GET', '/admin/users/{id}/course/{slug}', function ($p) {
    require_admin();
    $u = db_one('SELECT * FROM users WHERE id = ?', [(int) $p['id']]);
    if (!$u) { http_response_code(404); view('error', ['code'=>404,'message'=>'User not found.'], 'Not found'); return; }
    $course = course_by_slug($p['slug']);
    if (!$course) { flash('Course not found.', 'error'); redirect('/admin/users/' . (int) $p['id']); }
    $doneIds = array_column(db_all('SELECT step_id FROM progress WHERE user_id=? AND course_id=?',
        [(int) $u['id'], (int) $course['id']]), 'step_id');
    $enr = db_one('SELECT * FROM enrollments WHERE user_id=? AND course_id=?', [(int) $u['id'], (int) $course['id']]);
    $badge = db_one('SELECT * FROM badges WHERE user_id=? AND course_id=?', [(int) $u['id'], (int) $course['id']]);
    view('admin/user_course', [
        'u' => $u, 'course' => $course,
        'structure' => course_structure($course['slug']),
        'done' => array_fill_keys($doneIds, true),
        'enrolled' => (bool) $enr, 'enrollment' => $enr,
        'lastStep' => $enr['last_step_id'] ?? '',
        'percent' => course_percent((int) $u['id'], $course),
        'badge' => $badge,
    ], 'Manage progress');
});

// Save the learner's completed steps (exact checked set), or mark all complete.
route('POST', '/admin/users/{id}/course/{slug}', function ($p) {
    csrf_check();
    require_admin();
    $id = (int) $p['id'];
    $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u) { http_response_code(404); exit('User not found'); }
    $course = course_by_slug($p['slug']);
    if (!$course) { flash('Course not found.', 'error'); redirect('/admin/users/' . $id); }
    $desired = !empty($_POST['complete_all'])
        ? course_all_step_ids($course['slug'])
        : array_map('strval', $_POST['steps'] ?? []);
    $r = admin_sync_progress($id, (int) $course['id'], $desired);
    audit('user.progress_set', ['target_type' => 'user', 'target_id' => (string) $id,
        'detail' => $course['slug'] . " percent={$r['percent']} +{$r['added']}/-{$r['removed']}"]);
    if (!empty($_POST['complete_all'])) {
        flash("Marked “{$course['title']}” complete for {$u['first_name']} {$u['last_name']}."
            . ($r['badge'] ? ' Badge issued.' : ''));
    } else {
        flash("Progress saved ({$r['percent']}% complete)." . ($r['badge'] ? ' Badge issued.' : ''));
    }
    redirect('/admin/users/' . $id . '/course/' . urlencode($course['slug']));
});

// Enroll a user in a course from the progress page.
route('POST', '/admin/users/{id}/course/{slug}/enroll', function ($p) {
    csrf_check();
    require_admin();
    $id = (int) $p['id'];
    $course = course_by_slug($p['slug']);
    if (!$course) { flash('Course not found.', 'error'); redirect('/admin/users/' . $id); }
    enroll($id, (int) $course['id']);
    audit('user.enroll', ['target_type' => 'user', 'target_id' => (string) $id, 'detail' => $course['slug']]);
    flash("Enrolled in “{$course['title']}”.");
    redirect('/admin/users/' . $id . '/course/' . urlencode($course['slug']));
});

// Disenroll a user (optionally purging progress + badge).
route('POST', '/admin/users/{id}/course/{slug}/disenroll', function ($p) {
    csrf_check();
    require_admin();
    $id = (int) $p['id'];
    $course = course_by_slug($p['slug']);
    if (!$course) { flash('Course not found.', 'error'); redirect('/admin/users/' . $id); }
    $purge = !empty($_POST['purge']);
    unenroll($id, (int) $course['id'], $purge);
    audit('user.disenroll', ['target_type' => 'user', 'target_id' => (string) $id,
        'detail' => $course['slug'] . ($purge ? ' (purged)' : '')]);
    flash("Removed from “{$course['title']}”." . ($purge ? ' Progress and badge deleted.' : ' Progress kept.'));
    redirect('/admin/users/' . $id);
});

// Admin-generated password reset link (for districts without email configured).
route('POST', '/admin/users/{id}/reset-link', function ($p) {
    csrf_check();
    require_admin();
    $u = db_one('SELECT * FROM users WHERE id = ?', [(int) $p['id']]);
    if (!$u) { flash('User not found.', 'error'); redirect('/admin/users'); }
    $token = create_reset_token((int) $u['id']);
    $link = base_url_absolute() . '/reset?token=' . $token;
    audit('password.admin_reset_link', ['target_type' => 'user', 'target_id' => (string) $u['id']]);
    if (mail_enabled()) {
        send_mail($u['email'], lms_config()['app_name'] . ' — password reset',
            "An administrator started a password reset for your account.\n\n"
            . "Open this link to choose a new password (valid for 1 hour):\n$link\n");
        flash('A reset link was emailed to ' . $u['email'] . '.');
    } else {
        flash('One-time reset link (valid 1 hour) — share it securely with the user: ' . $link);
    }
    redirect('/admin/users/' . $u['id']);
});

route('GET', '/admin/import', function () {
    require_admin();
    view('admin/import', ['report' => $_SESSION['import_report'] ?? null], 'Bulk import');
    unset($_SESSION['import_report']);
});

route('POST', '/admin/import', function () {
    csrf_check();
    require_admin();
    if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        flash('Please choose a CSV file to upload.', 'error');
        redirect('/admin/import');
    }
    $report = import_users_csv($_FILES['csv']['tmp_name']);
    $_SESSION['import_report'] = $report;
    $n = count($report['created']); $en = count($report['enrolled']); $err = count($report['errors']);
    audit('users.import', ['detail' => "created=$n enrolled=$en errors=$err"]);
    flash("Import finished: $n created, $en enrolled" . ($err ? ", $err error(s)" : '') . '.', $err ? 'error' : 'success');
    redirect('/admin/import');
});

route('GET', '/admin/template.csv', function () {
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sapiqo-import-template.csv"');
    echo csv_template_string();
    exit;
});

// OneRoster (SIS) roster import — ZIP (orgs/users/classes/enrollments) or users.csv.
route('POST', '/admin/import/oneroster', function () {
    csrf_check();
    require_admin();
    @set_time_limit(0);
    if (empty($_FILES['roster']['tmp_name']) || !is_uploaded_file($_FILES['roster']['tmp_name'])) {
        flash('Choose a OneRoster .zip or users.csv file.', 'error'); redirect('/admin/import');
    }
    $r = import_oneroster($_FILES['roster']['tmp_name'], $_FILES['roster']['name']);
    audit('users.import_oneroster', ['detail' => "created={$r['users_created']} updated={$r['users_updated']} enrolled={$r['enrolled']}"]);
    $msg = "OneRoster: {$r['users_created']} created, {$r['users_updated']} updated, {$r['groups']} group(s), {$r['enrolled']} enrolled.";
    if ($r['unmatched_classes']) $msg .= ' Unmatched classes: ' . implode(', ', array_slice($r['unmatched_classes'], 0, 8));
    if ($r['errors']) $msg .= ' Errors: ' . implode('; ', array_slice($r['errors'], 0, 5));
    flash($msg, $r['errors'] ? 'error' : 'success');
    redirect('/admin/import');
});

route('GET', '/admin/reports', function () {
    require_admin();
    view('admin/reports', [
        'kpis'     => report_kpis(),
        'courses'  => report_course_performance(),
        'orgs'     => report_by_organization(),
        'campuses' => report_by_campus(),
        'types'    => report_by_user_type(),
        'timeline' => report_timeline(8),
        'activity' => report_recent_activity(12),
        'groups'   => groups_with_stats(),
    ], 'Training reports');
});

route('GET', '/admin/reports.csv', function () {
    require_admin();
    $k = report_kpis();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sapiqo-report-summary.csv"');
    $fh = fopen('php://output', 'w');
    csv_out($fh, ['Metric', 'Value']);
    foreach ([
        'Total users' => $k['users'], 'Learners' => $k['learners'], 'Admins' => $k['admins'],
        'Enrollments' => $k['enrollments'], 'Completions' => $k['completions'],
        'In progress' => $k['in_progress'], 'Active learners' => $k['active_learners'],
        'Badges issued' => $k['badges'], 'Completion rate (%)' => $k['completion_rate'],
    ] as $label => $val) {
        csv_out($fh, [$label, $val]);
    }
    csv_out($fh, []);
    csv_out($fh, ['Course', 'Enrolled', 'Completed', 'Completion rate (%)', 'Avg progress (%)']);
    foreach (report_course_performance() as $c) {
        csv_out($fh, [$c['title'], $c['enrolled'], $c['completed'], $c['rate'], $c['avg_progress']]);
    }
    csv_out($fh, []);
    csv_out($fh, ['Organization', 'Users', 'Enrolled', 'Completions', 'Completion rate (%)']);
    foreach (report_by_organization() as $o) {
        csv_out($fh, [$o['organization'], $o['users'], $o['enrolled'], $o['completions'], $o['rate']]);
    }
    exit;
});

route('GET', '/admin/completions', function () {
    require_admin();
    // Filters: search + organization + campus + group + status.
    $q      = trim((string) input('q'));
    $org    = (string) input('org');
    $campus = (string) input('campus');
    $status = (string) input('status');
    $group  = (int) input('group');
    $where = []; $args = [];
    if ($q !== '') {
        $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
        $like = '%' . $q . '%'; array_push($args, $like, $like, $like);
    }
    if ($org !== '')    { $where[] = 'u.organization = ?'; $args[] = $org; }
    if ($campus !== '') { $where[] = 'u.campus = ?'; $args[] = $campus; }
    if (in_array($status, ['enrolled', 'completed'], true)) { $where[] = 'e.status = ?'; $args[] = $status; }
    if ($group > 0) { $where[] = 'u.id IN (SELECT user_id FROM user_group_members WHERE group_id = ?)'; $args[] = $group; }
    $wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $rows = db_all(
        "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.campus, u.organization, u.user_type,
                c.title AS course, e.status, e.enrolled_at, e.completed_at, b.code AS badge_code
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         LEFT JOIN badges b ON b.user_id = e.user_id AND b.course_id = e.course_id"
        . $wsql . " ORDER BY e.status DESC, u.last_name, u.first_name LIMIT 5000",
        $args
    );
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sapiqo-completions.csv"');
        $fh = fopen('php://output', 'w');
        csv_out($fh, ['First name','Last name','Email','Campus','Organization','User type','Course','Status','Enrolled','Completed','Badge code']);
        foreach ($rows as $r) {
            csv_out($fh, [$r['first_name'],$r['last_name'],$r['email'],$r['campus'],$r['organization'],$r['user_type'],$r['course'],$r['status'],$r['enrolled_at'],$r['completed_at'],$r['badge_code']]);
        }
        exit;
    }
    view('admin/completions', [
        'rows'    => $rows,
        'filters' => ['q' => $q, 'org' => $org, 'campus' => $campus, 'status' => $status, 'group' => $group],
        'orgs'    => array_column(db_all("SELECT DISTINCT organization FROM users WHERE organization <> '' ORDER BY organization"), 'organization'),
        'campuses'=> array_column(db_all("SELECT DISTINCT campus FROM users WHERE campus <> '' ORDER BY campus"), 'campus'),
        'groups'  => function_exists('all_groups') ? all_groups() : [],
    ], 'Completions');
});

// A specific learner's transcript (admin view) — badges, CPE, downloadable
// badge + certificate for support/reissue.
route('GET', '/admin/users/{id}/transcript', function ($p) {
    require_admin();
    $u = db_one('SELECT * FROM users WHERE id = ?', [(int) $p['id']]);
    if (!$u) { http_response_code(404); view('error', ['code'=>404,'message'=>'User not found.'], 'Not found'); return; }
    $rows = db_all(
        'SELECT b.code, b.issued_at,
                COALESCE(NULLIF(b.title, \'\'), c.title) AS title,
                COALESCE(NULLIF(b.course_slug, \'\'), c.slug) AS slug,
                COALESCE(b.cpe_hours, c.cpe_hours, 0) AS cpe_hours,
                COALESCE(b.gt_hours, c.gt_hours, 0) AS gt_hours
         FROM badges b LEFT JOIN courses c ON c.id = b.course_id
         WHERE b.user_id = ? ORDER BY b.issued_at DESC', [(int) $u['id']]);
    $totalCpe = array_sum(array_map(fn($r) => (float) $r['cpe_hours'], $rows));
    $totalGt  = array_sum(array_map(fn($r) => (float) $r['gt_hours'], $rows));
    view('transcript', ['user' => $u, 'rows' => $rows, 'total_cpe' => $totalCpe, 'total_gt' => $totalGt, 'admin_view' => true],
        'Transcript — ' . (trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['email']));
});
