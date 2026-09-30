<?php
declare(strict_types=1);
function assignment_course_guard(string $slug,bool $author=false): array {
    require_login();
    $course=course_by_slug($slug); if (!$course) { http_response_code(404); exit('Course not found.'); }
    if ($author && !can_edit_content() && !assignment_can_grade((int)$course['id'])) { http_response_code(403); exit('Course teaching access required.'); }
    if (!$author && ($error=course_access_error((int)current_user()['id'],$course))!==null) { http_response_code(403); exit(e($error)); }
    return $course;
}
route('GET','/assignments/{slug}',function($p){
    $course=assignment_course_guard($p['slug']); $uid=(int)current_user()['id'];
    view('assignments',['course'=>$course,'assignments'=>assignment_list((int)$course['id']),'uid'=>$uid],'Course assignments');
});
route('POST','/assignments/{slug}/{id}',function($p){
    csrf_check(); $course=assignment_course_guard($p['slug']); $a=gb_assessment((int)$p['id']); $uid=(int)current_user()['id'];
    if (!$a || (int)$a['course_id']!==(int)$course['id'] || empty($a['submission_enabled'])) { http_response_code(404); exit('Assignment not found.'); }
    $upload = [null,null];
    try {
        enroll($uid,(int)$course['id']);
        $upload = assignment_upload($_FILES['file']??[]);
        assignment_save($a,$uid,(string)($_POST['body']??''),input('action')==='submit',(int)input('revision'),$upload);
        audit('assignment.saved',['target_type'=>'assessment','target_id'=>(string)$a['id']]); flash(input('action')==='submit'?'Assignment submitted.':'Draft saved.');
    } catch (Throwable $e) { if ($upload[0]) unlink(lms_config()['data_dir'].'/assignment-files/'.$upload[0]); if (!$e instanceof InvalidArgumentException) throw $e; flash($e->getMessage(),'error'); }
    redirect('/assignments/'.$course['slug']);
});
route('GET','/assignment-file/{id}',function($p){
    require_login(); $sub=db_one('SELECT * FROM assignment_submissions WHERE id=?',[(int)$p['id']]);
    $a=$sub?gb_assessment((int)$sub['assessment_id']):null; $course=$a?course_by_id((int)$a['course_id']):null;
    if (!$sub || !$a || !$course || (!$sub['file_path']) || ((int)$sub['user_id']!==(int)current_user()['id'] && !assignment_can_grade((int)$course['id']))) { http_response_code(404); exit; }
    if (!assignment_can_grade((int)$course['id']) && course_access_error((int)current_user()['id'],$course)!==null) { http_response_code(403); exit; }
    if (!preg_match('/^[a-f0-9]{40}\.(pdf|txt|png|jpe?g)$/D',$sub['file_path'])) { http_response_code(404); exit; }
    $file=lms_config()['data_dir'].'/assignment-files/'.$sub['file_path']; if (!is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="submission.'.pathinfo($file,PATHINFO_EXTENSION).'"'); header('Cache-Control: private, no-store'); header("Content-Security-Policy: sandbox; default-src 'none'"); readfile($file);
});
route('GET','/admin/assignments/{slug}',function($p){
    $course=assignment_course_guard($p['slug'],true);
    view('admin/assignments',['course'=>$course,'assignments'=>assignment_list((int)$course['id']),'rubrics'=>db_all('SELECT * FROM rubrics ORDER BY id DESC'),'groups'=>assignment_can_grade((int)$course['id'])?assignment_groups((int)$course['id']):[],
        'schedules'=>db_all('SELECT s.*,g.name FROM cohort_schedules s JOIN user_groups g ON g.id=s.group_id WHERE course_id=?',[$course['id']])],'Assignments and cohorts');
});
route('POST','/admin/assignments/{slug}',function($p){
    csrf_check(); $course=assignment_course_guard($p['slug'],true);
    try {
        $title=input('title'); $instructions=(string)($_POST['instructions']??''); $max=filter_var(input('max_points'),FILTER_VALIDATE_FLOAT);
        $pass=filter_var(input('pass_percent','70'),FILTER_VALIDATE_FLOAT);
        if ($title==='' || strlen($title)>191 || strlen($instructions)>50000 || $max===false || $max<=0 || $max>10000 || $pass===false || $pass<0 || $pass>100) throw new InvalidArgumentException('Enter a title, valid points, and a passing percentage between 0 and 100.');
        $rubric=db_one('SELECT * FROM rubrics WHERE id=?',[(int)input('rubric_id')]); $criteria=$rubric?json_decode($rubric['criteria'],true):[];
        if ($criteria) $max=array_sum(array_column($criteria,'max'));
        $due=learning_utc(input('due')); $available=learning_utc(input('available')); if ($due && $available && $due<$available) throw new InvalidArgumentException('The deadline must follow the release date.');
        $id=gb_add_assessment((int)$course['id'],$title,'Assignment',(float)$max,$due);
        db_run('UPDATE assessments SET submission_enabled=1,instructions=?,rubric=?,required_completion=?,pass_percent=?,late_policy=?,available_at=?,allow_resubmit=? WHERE id=?',[$instructions,json_encode($criteria),isset($_POST['required'])?1:0,$pass,input('late_policy')==='close'?'close':'accept',$available,isset($_POST['resubmit'])?1:0,$id]);
        audit('assignment.created',['target_type'=>'assessment','target_id'=>(string)$id]); flash('Assignment created.');
    } catch (InvalidArgumentException $e) { flash($e->getMessage(),'error'); }
    redirect('/admin/assignments/'.$course['slug']);
});
route('POST','/admin/assignments/{slug}/rubric',function($p){
    csrf_check(); $course=assignment_course_guard($p['slug'],true);
    try {
        $criteria=rubric_parse((string)($_POST['criteria']??'')); $title=input('title'); if ($title==='' || strlen($title)>191) throw new InvalidArgumentException('Enter a rubric title.');
        db_insert('INSERT INTO rubrics (title,criteria,created_at) VALUES (?,?,?)',[$title,json_encode($criteria),now_utc()]); flash('Reusable rubric saved.');
    } catch (InvalidArgumentException $e) { flash($e->getMessage(),'error'); }
    redirect('/admin/assignments/'.$course['slug']);
});
route('POST','/admin/assignments/{slug}/schedule',function($p){
    csrf_check(); $course=assignment_course_guard($p['slug'],true);
    if (!assignment_can_grade((int)$course['id'])) { http_response_code(403); exit('Course teaching access required.'); }
    try {
        $gid=(int)input('group_id'); if (!in_array($gid,array_map(fn($g)=>(int)$g['id'],assignment_groups((int)$course['id'])),true)) throw new InvalidArgumentException('Choose a group.');
        $opens=learning_utc(input('opens')); $due=learning_utc(input('due')); if ($due && $opens && $due<$opens) throw new InvalidArgumentException('The deadline must follow release.');
        $pdo=db(); $pdo->beginTransaction();
        db_run('DELETE FROM cohort_schedules WHERE group_id=? AND course_id=?',[$gid,$course['id']]);
        if ($opens || $due) db_run('INSERT INTO cohort_schedules (group_id,course_id,opens_at,due_at,late_policy) VALUES (?,?,?,?,?)',[$gid,$course['id'],$opens,$due,input('late_policy')==='close'?'close':'accept']);
        $pdo->commit(); flash('Cohort schedule saved. Blank dates remove its schedule.');
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); if (!$e instanceof InvalidArgumentException) throw $e; flash($e->getMessage(),'error'); }
    redirect('/admin/assignments/'.$course['slug']);
});
route('GET','/admin/assignment/{id}',function($p){
    require_login(); $a=gb_assessment((int)$p['id']); if (!$a || empty($a['submission_enabled'])) { http_response_code(404); exit('Assignment not found.'); }
    if (!assignment_can_grade((int)$a['course_id'])) { http_response_code(403); exit('Course teaching access required.'); }
    $subs=db_all("SELECT s.*,u.first_name,u.last_name,u.email FROM assignment_submissions s JOIN users u ON u.id=s.user_id WHERE s.assessment_id=? AND s.status<>'draft' ORDER BY s.updated_at DESC",[$a['id']]);
    view('admin/submissions',['assignment'=>$a,'submissions'=>$subs],'Review submissions');
});
route('POST','/admin/assignment/{id}/{user}',function($p){
    csrf_check(); require_login(); $a=gb_assessment((int)$p['id']); if (!$a || empty($a['submission_enabled'])) { http_response_code(404); exit; }
    if (!assignment_can_grade((int)$a['course_id'])) { http_response_code(403); exit('Course teaching access required.'); }
    try {
        $raw=input('points'); $points=filter_var($raw,FILTER_VALIDATE_FLOAT); if ($points===false) $points=null;
        assignment_grade($a,(int)$p['user'],(int)input('revision'),(array)($_POST['rubric_scores']??[]),$points,(string)($_POST['feedback']??''));
        audit('assignment.graded',['target_type'=>'assessment','target_id'=>(string)$a['id']]); flash('Feedback and grade saved.');
    } catch (InvalidArgumentException $e) { flash($e->getMessage(),'error'); }
    redirect('/admin/assignment/'.$a['id']);
});
route('GET','/admin/learning-report',function(){
    require_admin(); $rows=db_all("SELECT e.*,u.email,u.first_name,u.last_name,c.slug,c.title FROM enrollments e JOIN users u ON u.id=e.user_id JOIN courses c ON c.id=e.course_id WHERE e.status<>'completed' ORDER BY e.enrolled_at LIMIT 1000");
    $items=[];
    foreach ($rows as $row) {
        $course=course_by_id((int)$row['course_id']); $percent=course_percent((int)$row['user_id'],$course);
        $state=$percent===0?'Not started':(($row['last_seen_at'] && $row['last_seen_at']<gmdate('Y-m-d H:i:s',time()-7*86400))?'Stalled':'In progress');
        foreach (assignment_list((int)$course['id']) as $a) {
            $sub=assignment_submission((int)$a['id'],(int)$row['user_id']); $due=assignment_due($a,(int)$row['user_id']);
            if ($due && $due<now_utc() && (!$sub || $sub['status']==='draft')) { $state='Overdue'; break; }
            $score=db_one('SELECT points FROM assessment_scores WHERE assessment_id=? AND user_id=?',[$a['id'],$row['user_id']]);
            if ($sub && $sub['status']==='graded' && $score && (float)$score['points']<(float)$a['max_points']*(float)$a['pass_percent']/100) $state='Needs revision';
        }
        $row['attention']=$state; $row['percent']=$percent; $items[]=$row;
    }
    view('admin/learning_report',['items'=>$items],'Learners needing attention');
});

route('POST','/admin/assignments/{slug}/instructor',function($p){
    csrf_check(); require_admin(); $course=assignment_course_guard($p['slug'],true);
    $user=db_one('SELECT id FROM users WHERE email=?',[strtolower(input('email'))]);
    if (!$user) { flash('User not found.','error'); redirect('/admin/assignments/'.$course['slug']); }
    db_run('DELETE FROM course_instructors WHERE course_id=? AND user_id=?',[$course['id'],$user['id']]);
    if (input('action')!=='remove') db_run('INSERT INTO course_instructors (course_id,user_id) VALUES (?,?)',[$course['id'],$user['id']]);
    audit('course.instructor',['target_type'=>'course','target_id'=>$course['slug'],'detail'=>input('action')==='remove'?'removed':'assigned']);
    flash('Course instructor access updated.'); redirect('/admin/assignments/'.$course['slug']);
});
