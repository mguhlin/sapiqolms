<?php
// Author readiness and learner navigation, separated from route handlers.
declare(strict_types=1);
function course_publication_checks(array $data): array {
    $errors = []; $warnings = []; $ids = []; $lessons = 0;
    if (trim((string)($data['title'] ?? '')) === '') $errors[] = 'Add a course title.';
    if (!preg_match('/^[a-z0-9-]+$/D', (string)($data['slug'] ?? ''))) $errors[] = 'Use a valid course slug.';
    if (($data['type'] ?? '') === 'scorm') {
        if (!package_file(lms_config()['courses_dir'] . '/' . $data['slug'], (string)($data['scorm']['entry'] ?? ''))) $errors[] = 'The SCORM launch file is missing.';
        $warnings[] = 'Test the package in the sandboxed player before enrolling learners.';
    } else {
        foreach ($data['modules'] ?? [] as $module) foreach ($module['lessons'] ?? [] as $lesson) {
            $lessons++;
            $id = (string)($lesson['id'] ?? '');
            if ($id === '' || isset($ids[$id])) $errors[] = 'Every lesson needs a unique, nonempty identifier.';
            $ids[$id] = true;
            if (trim(strip_tags((string)($lesson['content'] ?? ''))) === '' && empty($lesson['blocks']) && empty($lesson['videos']) && empty($lesson['topics']) && empty($lesson['quiz']) && !preg_match('/<(img|iframe|video)\b/i', (string)($lesson['content'] ?? ''))) $warnings[] = 'Review empty lesson: ' . ($lesson['title'] ?? $id);
            foreach ($lesson['topics'] ?? [] as $topic) {
                $tid = (string)($topic['id'] ?? ''); $composite = $id . '::' . $tid;
                if ($tid === '' || isset($ids[$composite])) $errors[] = 'Every topic needs a unique, nonempty identifier within its lesson.';
                $ids[$composite] = true;
            }
            if (!empty($lesson['quiz'])) {
                $q = $lesson['quiz']; $qid = (string)($q['id'] ?? '');
                if ($qid === '' || isset($ids[$qid])) $errors[] = 'Every quiz needs a unique identifier.';
                $ids[$qid] = true;
                if (empty($q['questions'])) $errors[] = 'Add questions to each quiz.';
                $qids = [];
                foreach ($q['questions'] ?? [] as $i=>$question) {
                    $questionId = (string)($question['qid'] ?? 'q'.$i);
                    if (isset($qids[$questionId])) $errors[] = 'Quiz question identifiers must be unique.';
                    $qids[$questionId] = true;
                    if (isset($question['options']) && !array_filter($question['options'], fn($o)=>!empty($o['correct']))) $errors[] = 'A choice question has no correct answer.';
                }
            }
            if (preg_match('/<img\b(?![^>]*\balt=)[^>]*>/i', (string)($lesson['content'] ?? ''))) $warnings[] = 'Add image alternatives in: ' . ($lesson['title'] ?? $id);
        }
        if (!$lessons) $errors[] = 'Add at least one lesson.';
    }
    if (((float)($data['cpe_hours'] ?? 0) > 0 || (float)($data['gt_hours'] ?? 0) > 0) && empty(lms_config()['cert_signatory'])) $warnings[] = 'Configure the certificate signatory in Settings.';
    $warnings[] = 'Preview as a learner and review keyboard access, captions, resource links, and completion requirements.';
    return ['errors'=>array_values(array_unique($errors)), 'warnings'=>array_values(array_unique($warnings))];
}
function learning_next_activity(int $uid, array $course): ?array {
    $done = array_column(db_all('SELECT step_id FROM progress WHERE user_id=? AND course_id=?', [$uid,$course['id']]), 'step_id');
    foreach (course_structure($course['slug']) as $module) foreach ($module['lessons'] as $lesson) foreach ($lesson['units'] as $unit) {
        if (!in_array($unit['id'], $done, true)) return ['title'=>$unit['label'], 'href'=>url('/courses/'.$course['slug'].'/').'#/'.rawurlencode($unit['id'])];
    }
    return null;
}
function learning_templates(): array {
    return ['workshop'=>['title'=>'Workshop', 'description'=>'Prepare, practice, reflect'], 'self-paced'=>['title'=>'Self-paced professional learning','description'=>'Learn, check understanding, apply'], 'cohort'=>['title'=>'Facilitated cohort','description'=>'Orientation, discussion, demonstration']];
}
function learning_template_source(string $key): string {
    $template = learning_templates()[$key] ?? null; if (!$template) return creator_sample();
    $title = $template['title']; $slug = cr_slugify($title);
    return "---\ntitle: $title\nslug: $slug\ntagline: {$template['description']}\nabout: Replace this introduction with your learning objectives and completion requirements.\n---\n\n# Module 1: Get ready\n\n## Welcome and objectives\n\nDescribe who this course is for, what learners will achieve, and how completion is assessed.\n\n# Module 2: Learn and practice\n\n## Explore the topic\n\nAdd your accessible learning materials, examples, and practice activity.\n\n## Check your understanding\n\n@quiz\npass: 70\nQ: Have you reviewed the learning objectives?\n- *Yes\n- Not yet\nfeedback: Review the objectives before continuing.\n@endquiz\n\n# Module 3: Apply and reflect\n\n## Apply your learning\n\nReplace this prompt with an authentic application activity. Add an assignment and rubric from the Assignments page if instructor review is required.\n";
}
