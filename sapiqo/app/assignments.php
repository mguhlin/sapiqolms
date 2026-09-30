<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gradebook.php';
function learning_utc(string $value): ?string {
    if (trim($value) === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(Z|[+-]\d{2}:\d{2})$/D', $value)) throw new InvalidArgumentException('Use an ISO date with timezone, such as 2026-10-01T17:00:00-05:00.');
    $date = new DateTimeImmutable($value); if (DateTimeImmutable::getLastErrors() !== false) throw new InvalidArgumentException('Invalid date.');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function rubric_parse(string $text): array {
    $criteria = [];
    foreach (preg_split('/\R/', trim($text)) as $line) {
        if (trim($line) === '') continue;
        $parts = explode('|', $line, 2); $name = trim($parts[0]); $max = isset($parts[1]) ? filter_var(trim($parts[1]), FILTER_VALIDATE_FLOAT) : false;
        if ($name === '' || strlen($name) > 200 || $max === false || $max <= 0 || $max > 10000) throw new InvalidArgumentException('Rubric format: Criterion | maximum points, one per line.');
        $criteria[] = ['title'=>$name, 'max'=>(float)$max];
    }
    if (!$criteria || count($criteria)>30) throw new InvalidArgumentException('Use between one and thirty rubric criteria.');
    return $criteria;
}
function assignment_list(int $cid): array { return db_all('SELECT * FROM assessments WHERE course_id=? AND submission_enabled=1 ORDER BY position,id', [$cid]); }
function assignment_submission(int $aid, int $uid): ?array { return db_one('SELECT * FROM assignment_submissions WHERE assessment_id=? AND user_id=?',[$aid,$uid]); }
function cohort_schedule(int $cid, int $uid): array {
    $rows = db_all('SELECT s.* FROM cohort_schedules s JOIN user_group_members m ON m.group_id=s.group_id WHERE s.course_id=? AND m.user_id=?',[$cid,$uid]);
    $out = ['opens_at'=>null,'due_at'=>null,'late_policy'=>'accept'];
    foreach ($rows as $row) {
        if (!empty($row['opens_at']) && (!$out['opens_at'] || $row['opens_at']>$out['opens_at'])) $out['opens_at']=$row['opens_at'];
        if (!empty($row['due_at']) && (!$out['due_at'] || $row['due_at']<$out['due_at'])) $out['due_at']=$row['due_at'];
        if ($row['late_policy']==='close') $out['late_policy']='close';
    }
    return $out;
}
function assignment_due(array $a,int $uid): ?string {
    $cohort = cohort_schedule((int)$a['course_id'],$uid);
    // Cohort deadlines override the course-wide assignment due date.
    $due = $cohort['due_at'] ?: ($a['due_date'] ?: null);
    if ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due)) $due .= ' 23:59:59';
    return $due;
}
function assignment_is_available(array $a,int $uid): bool {
    $cohort = cohort_schedule((int)$a['course_id'],$uid);
    $opens = max($a['available_at'] ?: '',$cohort['opens_at'] ?: ''); return $opens === '' || $opens<=now_utc();
}
function assignment_required_state(int $uid,int $cid): array {
    $required=0; $passed=0;
    foreach (assignment_list($cid) as $a) if (!empty($a['required_completion'])) {
        $required++; $sub=assignment_submission((int)$a['id'],$uid);
        $score=db_one('SELECT points FROM assessment_scores WHERE assessment_id=? AND user_id=?',[(int)$a['id'],$uid]);
        if ($sub && $sub['status']==='graded' && $score && (float)$score['points'] >= (float)$a['max_points'] * (float)$a['pass_percent']/100) $passed++;
    }
    return [$required,$passed];
}
function assignment_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return [null,null];
    if (($file['error'] ?? 1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || (int)$file['size']>10*1024*1024) throw new InvalidArgumentException('Upload a file no larger than 10 MB.');
    $name = basename(str_replace('\\','/',(string)$file['name'])); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['pdf'=>['application/pdf'],'txt'=>['text/plain'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg']];
    if (!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) throw new InvalidArgumentException('Allowed files: PDF, plain text, PNG and JPEG.');
    $dir=lms_config()['data_dir'].'/assignment-files'; if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create file storage.');
    $path=bin2hex(random_bytes(20)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$path) || !chmod($dir.'/'.$path,0600)) throw new RuntimeException('Cannot store submission.');
    return [$path,mb_substr($name,0,180)];
}
function assignment_save(array $a,int $uid,string $body,bool $submit,int $revision,?array $upload=null): void {
    if (strlen($body)>100000) throw new InvalidArgumentException('Submission text is too long.');
    if (!assignment_is_available($a,$uid)) throw new InvalidArgumentException('This assignment is not yet available.');
    $cohort=cohort_schedule((int)$a['course_id'],$uid); $due=assignment_due($a,$uid);
    if ($due && $due<now_utc() && ($a['late_policy']==='close' || $cohort['late_policy']==='close')) throw new InvalidArgumentException('The submission deadline has passed.');
    $pdo=db(); $pdo->beginTransaction();
    try {
        // Serialize submissions for this user before reading a revision (also prevents SQLite read-to-write lock upgrades).
        db_run('UPDATE users SET updated_at=updated_at WHERE id=?',[$uid]);
        $suffix=lms_config()['db_driver']==='mysql'?' FOR UPDATE':'';
        $old=db_one('SELECT * FROM assignment_submissions WHERE assessment_id=? AND user_id=?'.$suffix,[$a['id'],$uid]);
        if ((int)($old['revision']??0)!==$revision) throw new InvalidArgumentException('This submission changed. Reload before saving.');
        if ($old && $old['status']!=='draft' && empty($a['allow_resubmit'])) throw new InvalidArgumentException('Resubmission is disabled.');
        [$path,$name]=$upload ?: [null,null]; $path=$path ?: ($old['file_path']??null); $name=$name ?: ($old['file_name']??null);
        if ($submit && trim($body)==='' && !$path) throw new InvalidArgumentException('Enter text or attach a file before submitting.');
        $status=$submit?'submitted':'draft'; $now=now_utc();
        if ($old) db_run('UPDATE assignment_submissions SET body=?,file_path=?,file_name=?,status=?,feedback=NULL,rubric_scores=NULL,submitted_at=?,updated_at=?,revision=revision+1 WHERE id=?',[$body,$path,$name,$status,$submit?$now:null,$now,$old['id']]);
        else db_run('INSERT INTO assignment_submissions (assessment_id,user_id,body,file_path,file_name,status,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',[$a['id'],$uid,$body,$path,$name,$status,$submit?$now:null,$now]);
        gb_set_score((int)$a['id'],$uid,null); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function assignment_grade(array $a,int $uid,int $revision,array $rubricScores,?float $points,string $feedback): void {
    $criteria=json_decode($a['rubric']?:'[]',true); $clean=[];
    if ($criteria) {
        $points=0;
        foreach ($criteria as $i=>$criterion) {
            $score=filter_var($rubricScores[$i]??null,FILTER_VALIDATE_FLOAT);
            if ($score===false || $score<0 || $score>$criterion['max']) throw new InvalidArgumentException('Each rubric score must be within its stated range.');
            $clean[]=['title'=>$criterion['title'],'max'=>$criterion['max'],'points'=>$score]; $points+=$score;
        }
    }
    if ($points===null || !is_finite($points) || $points<0 || $points>(float)$a['max_points'] || strlen($feedback)>50000) throw new InvalidArgumentException('Enter a valid score and feedback.');
    $pdo=db(); $pdo->beginTransaction();
    try {
        $changed=db_run("UPDATE assignment_submissions SET status='graded',feedback=?,rubric_scores=?,updated_at=?,revision=revision+1 WHERE assessment_id=? AND user_id=? AND revision=? AND status<>'draft'",[$feedback,json_encode($clean),now_utc(),$a['id'],$uid,$revision]);
        if ($changed->rowCount()!==1) throw new InvalidArgumentException('The submission changed. Reload before grading.');
        gb_set_score((int)$a['id'],$uid,$points); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    $course=course_by_id((int)$a['course_id']);
    if ($course && course_percent($uid,$course)>=100) { issue_badge($uid,(int)$course['id']); mark_enrollment_complete($uid,(int)$course['id']); }
    notify($uid,'Feedback is available for '.$a['title'],url('/assignments/'.$course['slug']));
}

function assignment_can_grade(int $cid, ?int $uid = null): bool {
    $uid = $uid ?? (int)(current_user()['id'] ?? 0);
    $user = db_one('SELECT role FROM users WHERE id=?',[$uid]);
    return $user && ($user['role']==='admin' || (bool)db_one('SELECT user_id FROM course_instructors WHERE course_id=? AND user_id=?',[$cid,$uid]));
}
function assignment_groups(int $cid): array {
    if (is_admin()) return all_groups();
    return db_all('SELECT DISTINCT g.* FROM user_groups g JOIN user_group_members m ON m.group_id=g.id JOIN enrollments e ON e.user_id=m.user_id WHERE e.course_id=? ORDER BY g.name',[$cid]);
}
