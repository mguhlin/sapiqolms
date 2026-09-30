<?php
// Run daily by cron. In-app reminders are always available; email is optional.
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
foreach (['helpers','auth','security','courses','notifications','mailer'] as $service) require_once __DIR__.'/../app/'.$service.'.php';
ensure_schema(); $sent=0;
foreach (db_all("SELECT e.user_id,e.course_id,u.email FROM enrollments e JOIN users u ON u.id=e.user_id WHERE e.status<>'completed'") as $enrollment) {
    $course=course_by_id((int)$enrollment['course_id']); if (!$course || course_access_error((int)$enrollment['user_id'],$course)!==null) continue;
    foreach (assignment_list((int)$course['id']) as $assignment) {
        $uid=(int)$enrollment['user_id']; $due=assignment_due($assignment,$uid); $sub=assignment_submission((int)$assignment['id'],$uid);
        if (!$due || $due<now_utc() || $due>gmdate('Y-m-d H:i:s',time()+3*86400) || ($sub && $sub['status']!=='draft')) continue;
        $pdo=db(); $pdo->beginTransaction();
        try {
            db_run('INSERT INTO deadline_reminders (user_id,assessment_id,due_at) VALUES (?,?,?)',[$uid,$assignment['id'],$due]);
            $message=$assignment['title'].' is due '.$due.' UTC.'; $url=url('/assignments/'.$course['slug']);
            db_run('INSERT INTO notifications (user_id,message,url,created_at) VALUES (?,?,?,?)',[$uid,$message,$url,now_utc()]); $pdo->commit(); $sent++;
        } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); if (str_starts_with((string)$e->getCode(),'23')) continue; throw $e; }
        if (mail_enabled()) send_mail($enrollment['email'],'Assignment reminder: '.$assignment['title'],$message."\n".base_url_absolute().'/assignments/'.$course['slug']);
    }
}
echo "Created $sent deadline reminder(s).\n";
