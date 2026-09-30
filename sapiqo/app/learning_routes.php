<?php
declare(strict_types=1);
route('GET', '/admin/readiness', function () {
    require_admin(); $cfg = lms_config(); $checks = [];
    $checks[] = ['Database responds', (bool)db()->query('SELECT 1')->fetchColumn(), '/admin'];
    $checks[] = ['Persistent storage is writable', is_writable($cfg['data_dir']), '/admin/settings'];
    $checks[] = ['Course storage is writable', is_writable($cfg['courses_dir']), '/admin/courses'];
    $checks[] = ['Canonical HTTPS public URL configured', str_starts_with($cfg['public_url'] ?? '', 'https://'), '/admin/settings'];
    $checks[] = ['Organization branding configured', !empty($cfg['org_name']), '/admin/settings'];
    $checks[] = ['Email enabled (send a delivery test)', mail_enabled(), '/admin/mail'];
    $checks[] = ['Your two-step verification enabled', !empty(current_user()['mfa_secret']), '/profile/security'];
    $checks[] = ['At least one course is registered', count(all_courses(false)) > 0, '/admin/create'];
    view('admin/readiness', ['checks'=>$checks], 'Installation readiness');
});
route('GET', '/admin/courses/{slug}/checklist', function ($p) {
    require_content_access(); $course=course_by_slug($p['slug']); if (!$course) { http_response_code(404); exit('Course not found.'); }
    view('admin/checklist', ['course'=>$course, 'checks'=>course_publication_checks(course_load_json($p['slug']))], 'Publication checklist');
});
