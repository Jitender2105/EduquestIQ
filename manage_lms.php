<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';

$user = require_auth(['teacher', 'school_admin']);
$pdo = get_pdo();

$errors = [];
$success = null;

function fetch_pairs(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function require_admin_or_add_error(array $user, array &$errors): bool
{
    if ($user['role'] !== 'school_admin') {
        $errors[] = 'This action is restricted to school admins.';
        return false;
    }
    return true;
}

function can_teacher_access_course(PDO $pdo, int $teacherId, int $courseId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND teacher_id = ? LIMIT 1');
    $stmt->execute([$courseId, $teacherId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        try {
            switch ($action) {
                case 'create_attribute':
                    if (!require_admin_or_add_error($user, $errors)) {
                        break;
                    }
                    $name = trim((string)($_POST['name'] ?? ''));
                    $description = trim((string)($_POST['description'] ?? ''));
                    if ($name === '') {
                        $errors[] = 'Attribute name is required.';
                        break;
                    }
                    $stmt = $pdo->prepare('INSERT INTO attributes (name, description) VALUES (?, ?)');
                    $stmt->execute([$name, $description !== '' ? $description : null]);
                    $success = 'Attribute created.';
                    break;

                case 'create_sub_attribute':
                    if (!require_admin_or_add_error($user, $errors)) {
                        break;
                    }
                    $attributeId = (int)($_POST['attribute_id'] ?? 0);
                    $name = trim((string)($_POST['name'] ?? ''));
                    $description = trim((string)($_POST['description'] ?? ''));
                    if ($attributeId <= 0 || $name === '') {
                        $errors[] = 'Sub-attribute requires attribute and name.';
                        break;
                    }
                    $stmt = $pdo->prepare('INSERT INTO sub_attributes (attribute_id, name, description) VALUES (?, ?, ?)');
                    $stmt->execute([$attributeId, $name, $description !== '' ? $description : null]);
                    $success = 'Sub-attribute created.';
                    break;

                case 'create_course':
                    $title = trim((string)($_POST['title'] ?? ''));
                    $description = trim((string)($_POST['description'] ?? ''));
                    $attributeId = (int)($_POST['attribute_id'] ?? 0);
                    $teacherId = $user['role'] === 'teacher' ? (int)$user['sub'] : (int)($_POST['teacher_id'] ?? 0);
                    if ($title === '' || $teacherId <= 0) {
                        $errors[] = 'Course title and teacher are required.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO courses (title, description, teacher_id, attribute_id, created_at)
                         VALUES (?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([$title, $description !== '' ? $description : null, $teacherId, $attributeId > 0 ? $attributeId : null]);
                    $success = 'Course created.';
                    break;

                case 'update_user':
                    if (!require_admin_or_add_error($user, $errors)) {
                        break;
                    }
                    $targetUserId = (int)($_POST['user_id'] ?? 0);
                    $status = (string)($_POST['status'] ?? 'active');
                    $schoolIdRaw = trim((string)($_POST['school_id'] ?? ''));
                    $schoolId = $schoolIdRaw === '' ? null : (int)$schoolIdRaw;
                    if ($targetUserId <= 0 || !in_array($status, ['active', 'inactive'], true)) {
                        $errors[] = 'Valid user and status are required.';
                        break;
                    }
                    if ($schoolId !== null && $schoolId <= 0) {
                        $errors[] = 'School ID must be positive or blank.';
                        break;
                    }
                    $stmt = $pdo->prepare('UPDATE users SET status = ?, school_id = ? WHERE id = ?');
                    $stmt->execute([$status, $schoolId, $targetUserId]);
                    $success = 'User updated.';
                    break;

                case 'create_video':
                    $courseId = (int)($_POST['course_id'] ?? 0);
                    $title = trim((string)($_POST['title'] ?? ''));
                    $videoUrl = trim((string)($_POST['video_url'] ?? ''));
                    $duration = (int)($_POST['duration'] ?? 0);
                    $sequence = (int)($_POST['sequence_order'] ?? 0);
                    if ($courseId <= 0 || $title === '') {
                        $errors[] = 'Video requires course and title.';
                        break;
                    }
                    if ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                        $errors[] = 'Video URL must be valid.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
                        $stmt->execute([$courseId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only add videos to your courses.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO video_lectures (course_id, title, video_url, duration, sequence_order)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$courseId, $title, $videoUrl !== '' ? $videoUrl : null, $duration > 0 ? $duration : null, $sequence > 0 ? $sequence : 1]);
                    $success = 'Video lecture created.';
                    break;

                case 'delete_video':
                    $videoId = (int)($_POST['video_id'] ?? 0);
                    if ($videoId <= 0) {
                        $errors[] = 'Video ID is required.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare(
                            'SELECT c.id
                             FROM video_lectures vl
                             JOIN courses c ON c.id = vl.course_id
                             WHERE vl.id = ? AND c.teacher_id = ?'
                        );
                        $stmt->execute([$videoId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only delete videos from your courses.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare('DELETE FROM video_lectures WHERE id = ?');
                    $stmt->execute([$videoId]);
                    $success = 'Video deleted.';
                    break;

                case 'create_material_link':
                    $courseId = (int)($_POST['course_id'] ?? 0);
                    $title = trim((string)($_POST['title'] ?? ''));
                    $materialType = (string)($_POST['material_type'] ?? 'link');
                    $filePath = trim((string)($_POST['file_path'] ?? ''));
                    if ($courseId <= 0 || $title === '' || !in_array($materialType, ['pdf', 'doc', 'ppt', 'link'], true)) {
                        $errors[] = 'Material requires course, title, and valid type.';
                        break;
                    }
                    if ($filePath === '') {
                        $errors[] = 'Material file path / URL is required.';
                        break;
                    }
                    if ($materialType === 'link' && !filter_var($filePath, FILTER_VALIDATE_URL)) {
                        $errors[] = 'Material link must be a valid URL.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
                        $stmt->execute([$courseId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only add materials to your courses.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO study_materials (course_id, title, file_path, material_type, uploaded_at)
                         VALUES (?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([$courseId, $title, $filePath, $materialType]);
                    $success = 'Study material created.';
                    break;

                case 'create_article':
                    $courseId = (int)($_POST['course_id'] ?? 0);
                    $title = trim((string)($_POST['title'] ?? ''));
                    $articleUrl = trim((string)($_POST['article_url'] ?? ''));
                    if ($courseId <= 0 || $title === '' || !filter_var($articleUrl, FILTER_VALIDATE_URL)) {
                        $errors[] = 'Article requires course, title, and a valid URL.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ?');
                        $stmt->execute([$courseId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only add articles to your courses.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO study_materials (course_id, title, file_path, material_type, uploaded_at)
                         VALUES (?, ?, ?, "link", NOW())'
                    );
                    $stmt->execute([$courseId, $title, $articleUrl]);
                    $success = 'Article created.';
                    break;

                case 'delete_material':
                    $materialId = (int)($_POST['material_id'] ?? 0);
                    if ($materialId <= 0) {
                        $errors[] = 'Material ID is required.';
                        break;
                    }
                    $filePathToDelete = null;
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare(
                            'SELECT sm.file_path
                             FROM study_materials sm
                             JOIN courses c ON c.id = sm.course_id
                             WHERE sm.id = ? AND c.teacher_id = ?'
                        );
                        $stmt->execute([$materialId, (int)$user['sub']]);
                        $row = $stmt->fetch();
                        if (!$row) {
                            $errors[] = 'You can only delete materials from your courses.';
                            break;
                        }
                        $filePathToDelete = $row['file_path'];
                    } else {
                        $stmt = $pdo->prepare('SELECT file_path FROM study_materials WHERE id = ?');
                        $stmt->execute([$materialId]);
                        $filePathToDelete = $stmt->fetchColumn() ?: null;
                    }
                    $stmt = $pdo->prepare('DELETE FROM study_materials WHERE id = ?');
                    $stmt->execute([$materialId]);
                    if (is_string($filePathToDelete) && str_starts_with($filePathToDelete, 'uploads/materials/')) {
                        $abs = __DIR__ . '/' . $filePathToDelete;
                        if (is_file($abs)) {
                            @unlink($abs);
                        }
                    }
                    $success = 'Study material deleted.';
                    break;

                case 'create_question':
                    $questionText = trim((string)($_POST['question_text'] ?? ''));
                    $questionType = (string)($_POST['question_type'] ?? 'mcq');
                    $difficulty = (string)($_POST['difficulty'] ?? 'easy');
                    if ($questionText === '' || !in_array($questionType, ['mcq', 'subjective'], true) || !in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                        $errors[] = 'Question text, type, and difficulty are required.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO questions (question_text, question_type, difficulty, created_by, created_at)
                         VALUES (?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([$questionText, $questionType, $difficulty, (int)$user['sub']]);
                    $success = 'Question created.';
                    break;

                case 'delete_question':
                    $questionId = (int)($_POST['question_id'] ?? 0);
                    if ($questionId <= 0) {
                        $errors[] = 'Question ID is required.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare('SELECT id FROM questions WHERE id = ? AND created_by = ?');
                        $stmt->execute([$questionId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only delete your own questions.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare('DELETE FROM questions WHERE id = ?');
                    $stmt->execute([$questionId]);
                    $success = 'Question deleted.';
                    break;

                case 'create_option':
                    $questionId = (int)($_POST['question_id'] ?? 0);
                    $optionText = trim((string)($_POST['option_text'] ?? ''));
                    $isCorrect = isset($_POST['is_correct']) ? 1 : 0;
                    if ($questionId <= 0 || $optionText === '') {
                        $errors[] = 'Question option requires question and option text.';
                        break;
                    }
                    $stmt = $pdo->prepare('INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
                    $stmt->execute([$questionId, $optionText, $isCorrect]);
                    $success = 'Question option added.';
                    break;

                case 'map_question_skill':
                    $questionId = (int)($_POST['question_id'] ?? 0);
                    $attributeId = (int)($_POST['attribute_id'] ?? 0);
                    $subAttributeId = (int)($_POST['sub_attribute_id'] ?? 0);
                    $weight = (float)($_POST['weight'] ?? 0);
                    if ($questionId <= 0 || $attributeId <= 0 || $subAttributeId <= 0 || $weight <= 0) {
                        $errors[] = 'Question mapping requires question, attribute, sub-attribute, and weight.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO question_attribute_mapping (question_id, attribute_id, sub_attribute_id, weight)
                         VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$questionId, $attributeId, $subAttributeId, $weight]);
                    $success = 'Question mapped to skill.';
                    break;

                case 'create_test':
                    $title = trim((string)($_POST['title'] ?? ''));
                    $description = trim((string)($_POST['description'] ?? ''));
                    $totalMarks = (int)($_POST['total_marks'] ?? 0);
                    $duration = (int)($_POST['duration_minutes'] ?? 0);
                    if ($title === '' || $totalMarks <= 0 || $duration <= 0) {
                        $errors[] = 'Test title, total marks, and duration are required.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO tests (title, description, created_by, total_marks, duration_minutes, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([$title, $description !== '' ? $description : null, (int)$user['sub'], $totalMarks, $duration]);
                    $success = 'Test created.';
                    break;

                case 'delete_test':
                    $testId = (int)($_POST['test_id'] ?? 0);
                    if ($testId <= 0) {
                        $errors[] = 'Test ID is required.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare('SELECT id FROM tests WHERE id = ? AND created_by = ?');
                        $stmt->execute([$testId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only delete your own tests.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare('DELETE FROM tests WHERE id = ?');
                    $stmt->execute([$testId]);
                    $success = 'Test deleted.';
                    break;

                case 'add_test_question':
                    $testId = (int)($_POST['test_id'] ?? 0);
                    $questionId = (int)($_POST['question_id'] ?? 0);
                    $marks = (int)($_POST['marks'] ?? 0);
                    if ($testId <= 0 || $questionId <= 0 || $marks <= 0) {
                        $errors[] = 'Test question mapping requires test, question, and marks.';
                        break;
                    }
                    if ($user['role'] === 'teacher') {
                        $stmt = $pdo->prepare('SELECT id FROM tests WHERE id = ? AND created_by = ?');
                        $stmt->execute([$testId, (int)$user['sub']]);
                        if (!$stmt->fetch()) {
                            $errors[] = 'You can only edit your own tests.';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare('INSERT INTO test_questions (test_id, question_id, marks) VALUES (?, ?, ?)');
                    $stmt->execute([$testId, $questionId, $marks]);
                    $success = 'Question added to test.';
                    break;

                case 'create_achievement':
                    if (!require_admin_or_add_error($user, $errors)) {
                        break;
                    }
                    $title = trim((string)($_POST['title'] ?? ''));
                    $description = trim((string)($_POST['description'] ?? ''));
                    $icon = trim((string)($_POST['icon'] ?? ''));
                    $criteriaType = (string)($_POST['criteria_type'] ?? 'score');
                    $criteriaValue = (int)($_POST['criteria_value'] ?? 0);
                    if ($title === '' || $criteriaValue <= 0 || !in_array($criteriaType, ['score', 'course_completion', 'activity'], true)) {
                        $errors[] = 'Achievement requires title, valid criteria type, and value.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO achievements (title, description, icon, criteria_type, criteria_value)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $description !== '' ? $description : null, $icon !== '' ? $icon : null, $criteriaType, $criteriaValue]);
                    $success = 'Achievement created.';
                    break;

                case 'create_learning_path':
                    $title = trim((string)($_POST['title'] ?? ''));
                    $description = trim((string)($_POST['description'] ?? ''));
                    if ($title === '') {
                        $errors[] = 'Learning path title is required.';
                        break;
                    }
                    $stmt = $pdo->prepare('INSERT INTO learning_paths (title, description) VALUES (?, ?)');
                    $stmt->execute([$title, $description !== '' ? $description : null]);
                    $success = 'Learning path created.';
                    break;

                case 'add_path_course':
                    $pathId = (int)($_POST['path_id'] ?? 0);
                    $courseId = (int)($_POST['course_id'] ?? 0);
                    $sequence = (int)($_POST['sequence_order'] ?? 0);
                    if ($pathId <= 0 || $courseId <= 0) {
                        $errors[] = 'Path course mapping requires path and course.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO path_courses (path_id, course_id, sequence_order) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$pathId, $courseId, $sequence > 0 ? $sequence : 1]);
                    $success = 'Course added to learning path.';
                    break;

                case 'mark_attendance':
                    if (!require_admin_or_add_error($user, $errors)) {
                        break;
                    }
                    $studentId = (int)($_POST['student_id'] ?? 0);
                    $date = (string)($_POST['attendance_date'] ?? '');
                    $status = (string)($_POST['attendance_status'] ?? 'present');
                    if ($studentId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($status, ['present', 'absent', 'late'], true)) {
                        $errors[] = 'Attendance requires valid student, date, and status.';
                        break;
                    }
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "student"');
                    $stmt->execute([$studentId]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid student selected.';
                        break;
                    }
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance (student_id, date, status, created_at)
                         VALUES (?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE status = VALUES(status)'
                    );
                    $stmt->execute([$studentId, $date, $status]);
                    $success = 'Attendance saved.';
                    break;
            }
        } catch (Throwable $e) {
            $errors[] = 'Action failed. Please verify data and try again.';
        }
    }
}

$messageMap = [
    'material_uploaded' => 'Study material uploaded successfully.',
    'material_upload_failed' => 'Material upload failed. Check file type/size and try again.',
    'material_invalid' => 'Material upload request was invalid.',
    'material_course_forbidden' => 'You can only upload materials to allowed courses.',
];
$msgKey = (string)($_GET['msg'] ?? '');
if (isset($messageMap[$msgKey])) {
    $success = $messageMap[$msgKey];
}

$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name')->fetchAll();
$subAttributes = $pdo->query(
    'SELECT sa.id, sa.name, sa.attribute_id, a.name AS attribute_name
     FROM sub_attributes sa
     JOIN attributes a ON a.id = sa.attribute_id
     ORDER BY a.name, sa.name'
)->fetchAll();

if ($user['role'] === 'teacher') {
    $courses = fetch_pairs(
        $pdo,
        'SELECT id, title, teacher_id, attribute_id, created_at FROM courses WHERE teacher_id = ? ORDER BY created_at DESC',
        [(int)$user['sub']]
    );
    $tests = fetch_pairs(
        $pdo,
        'SELECT id, title, created_by, total_marks, duration_minutes, created_at FROM tests WHERE created_by = ? ORDER BY created_at DESC',
        [(int)$user['sub']]
    );
    $questions = fetch_pairs(
        $pdo,
        'SELECT id, question_text, question_type, difficulty, created_at FROM questions WHERE created_by = ? ORDER BY created_at DESC LIMIT 100',
        [(int)$user['sub']]
    );
} else {
    $courses = $pdo->query('SELECT id, title, teacher_id, attribute_id, created_at FROM courses ORDER BY created_at DESC LIMIT 200')->fetchAll();
    $tests = $pdo->query('SELECT id, title, created_by, total_marks, duration_minutes, created_at FROM tests ORDER BY created_at DESC LIMIT 200')->fetchAll();
    $questions = $pdo->query('SELECT id, question_text, question_type, difficulty, created_at FROM questions ORDER BY created_at DESC LIMIT 200')->fetchAll();
}

$teachers = $pdo->query('SELECT id, name, email FROM users WHERE role = "teacher" AND status = "active" ORDER BY name')->fetchAll();
$studentsForAttendance = $pdo->query('SELECT id, name, email, school_id, status FROM users WHERE role = "student" ORDER BY id DESC LIMIT 200')->fetchAll();
$usersForAdmin = $pdo->query('SELECT id, name, email, role, school_id, status FROM users ORDER BY id DESC LIMIT 200')->fetchAll();
$videos = $pdo->query(
    'SELECT vl.id, vl.title, vl.course_id, c.title AS course_title, vl.sequence_order
     FROM video_lectures vl JOIN courses c ON c.id = vl.course_id
     ORDER BY vl.id DESC LIMIT 100'
)->fetchAll();
$materials = $pdo->query(
    'SELECT sm.id, sm.title, sm.material_type, sm.course_id, c.title AS course_title, sm.uploaded_at
     FROM study_materials sm JOIN courses c ON c.id = sm.course_id
     ORDER BY sm.id DESC LIMIT 100'
)->fetchAll();
$articlesList = $pdo->query(
    "SELECT sm.id, sm.title, sm.file_path, sm.course_id, c.title AS course_title, sm.uploaded_at
     FROM study_materials sm
     JOIN courses c ON c.id = sm.course_id
     WHERE sm.material_type = 'link'
     ORDER BY sm.id DESC LIMIT 100"
)->fetchAll();
$questionOptions = $pdo->query(
    'SELECT qo.id, qo.question_id, qo.option_text, qo.is_correct
     FROM question_options qo ORDER BY qo.id DESC LIMIT 150'
)->fetchAll();
$questionMappings = $pdo->query(
    'SELECT qam.id, qam.question_id, a.name AS attribute_name, sa.name AS sub_attribute_name, qam.weight
     FROM question_attribute_mapping qam
     JOIN attributes a ON a.id = qam.attribute_id
     JOIN sub_attributes sa ON sa.id = qam.sub_attribute_id
     ORDER BY qam.id DESC LIMIT 150'
)->fetchAll();
$testQuestions = $pdo->query(
    'SELECT tq.id, tq.test_id, t.title AS test_title, tq.question_id, tq.marks
     FROM test_questions tq
     JOIN tests t ON t.id = tq.test_id
     ORDER BY tq.id DESC LIMIT 150'
)->fetchAll();
$achievements = $pdo->query('SELECT id, title, criteria_type, criteria_value FROM achievements ORDER BY id DESC LIMIT 100')->fetchAll();
$paths = $pdo->query('SELECT id, title, description FROM learning_paths ORDER BY id DESC LIMIT 100')->fetchAll();
$pathCourses = $pdo->query(
    'SELECT pc.id, pc.path_id, lp.title AS path_title, pc.course_id, c.title AS course_title, pc.sequence_order
     FROM path_courses pc
     JOIN learning_paths lp ON lp.id = pc.path_id
     JOIN courses c ON c.id = pc.course_id
     ORDER BY pc.id DESC LIMIT 150'
)->fetchAll();
$attendanceRows = $pdo->query(
    'SELECT a.student_id, u.name AS student_name, a.date, a.status
     FROM attendance a
     JOIN users u ON u.id = a.student_id
     ORDER BY a.date DESC, a.id DESC
     LIMIT 100'
)->fetchAll();

require_once __DIR__ . '/includes_header.php';
?>

<div class="eq-page-head d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="mb-0">LMS Management Console</h2>
        <div class="subtitle">
            Teacher/Admin data setup for courses, tests, mappings, content, achievements, and learning paths.
        </div>
    </div>
    <span class="badge text-bg-secondary text-uppercase"><?php echo htmlspecialchars($user['role']); ?></span>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small fw-semibold eq-muted">Backend modules:</span>
        <a class="btn btn-outline-primary btn-sm" href="#headingSkills">Attributes & Sub-Attributes</a>
        <a class="btn btn-outline-primary btn-sm" href="#headingCourses">Courses & Video Tutorials</a>
        <a class="btn btn-outline-primary btn-sm" href="#headingQuestions">Questions & Mapping</a>
        <a class="btn btn-outline-primary btn-sm" href="#headingTests">Tests</a>
        <a class="btn btn-outline-primary btn-sm" href="#headingArticles">Articles</a>
        <a class="btn btn-outline-primary btn-sm" href="#headingPaths">Paths & Achievements</a>
        <?php if ($user['role'] === 'school_admin'): ?>
            <a class="btn btn-outline-primary btn-sm" href="#headingAdminOps">Users & Attendance</a>
        <?php endif; ?>
    </div>
</div>

<div class="accordion" id="manageAccordion">
    <?php if ($user['role'] === 'school_admin'): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSkills">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSkills">
                    Attributes & Sub-Attributes
                </button>
            </h2>
            <div id="collapseSkills" class="accordion-collapse collapse show" data-bs-parent="#manageAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="post" class="card p-3">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="create_attribute">
                                <h6>Create Attribute</h6>
                                <input class="form-control mb-2" name="name" placeholder="Attribute name" required>
                                <textarea class="form-control mb-2" name="description" rows="2" placeholder="Description"></textarea>
                                <button class="btn btn-primary btn-sm">Add Attribute</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post" class="card p-3">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="create_sub_attribute">
                                <h6>Create Sub-Attribute</h6>
                                <select class="form-select mb-2" name="attribute_id" required>
                                    <option value="">Select attribute</option>
                                    <?php foreach ($attributes as $attribute): ?>
                                        <option value="<?php echo (int)$attribute['id']; ?>"><?php echo htmlspecialchars($attribute['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control mb-2" name="name" placeholder="Sub-attribute name" required>
                                <textarea class="form-control mb-2" name="description" rows="2" placeholder="Description"></textarea>
                                <button class="btn btn-primary btn-sm">Add Sub-Attribute</button>
                            </form>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="card p-3"><h6>Attributes</h6><ul class="small mb-0"><?php foreach ($attributes as $attribute): ?><li><?php echo (int)$attribute['id']; ?> - <?php echo htmlspecialchars($attribute['name']); ?></li><?php endforeach; ?></ul></div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3"><h6>Sub-Attributes</h6><ul class="small mb-0"><?php foreach (array_slice($subAttributes, 0, 40) as $sub): ?><li><?php echo htmlspecialchars($sub['attribute_name'] . ' -> ' . $sub['name']); ?></li><?php endforeach; ?></ul></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="accordion-item">
        <h2 class="accordion-header" id="headingCourses">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCourses">
                Courses, Video Tutorials & Materials
            </button>
        </h2>
        <div id="collapseCourses" class="accordion-collapse collapse" data-bs-parent="#manageAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_course">
                            <h6>Create Course</h6>
                            <input class="form-control mb-2" name="title" placeholder="Course title" required>
                            <textarea class="form-control mb-2" name="description" rows="2" placeholder="Description"></textarea>
                            <?php if ($user['role'] === 'school_admin'): ?>
                                <select class="form-select mb-2" name="teacher_id" required>
                                    <option value="">Select teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo (int)$teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name'] . ' (' . $teacher['email'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <select class="form-select mb-2" name="attribute_id">
                                <option value="">Attribute (optional)</option>
                                <?php foreach ($attributes as $attribute): ?>
                                    <option value="<?php echo (int)$attribute['id']; ?>"><?php echo htmlspecialchars($attribute['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm">Create Course</button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_video">
                            <h6>Add Video Lecture</h6>
                            <select class="form-select mb-2" name="course_id" required>
                                <option value="">Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" name="title" placeholder="Video title" required>
                            <input class="form-control mb-2" name="video_url" placeholder="https://...">
                            <div class="row g-2 mb-2">
                                <div class="col"><input class="form-control" type="number" name="duration" min="0" placeholder="Duration min"></div>
                                <div class="col"><input class="form-control" type="number" name="sequence_order" min="1" placeholder="Order"></div>
                            </div>
                            <button class="btn btn-primary btn-sm">Add Video</button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" action="<?php echo htmlspecialchars(url_for('material_upload.php')); ?>" enctype="multipart/form-data" class="card p-3 mb-3">
                            <?php echo csrf_field(); ?>
                            <h6>Upload Study Material (Validated)</h6>
                            <select class="form-select mb-2" name="course_id" required>
                                <option value="">Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" name="title" placeholder="Material title" required>
                            <select class="form-select mb-2" name="material_type" required>
                                <option value="pdf">PDF</option>
                                <option value="doc">DOC/DOCX</option>
                                <option value="ppt">PPT/PPTX</option>
                                <option value="link">Link</option>
                            </select>
                            <input class="form-control mb-2" type="file" name="material_file">
                            <input class="form-control mb-2" name="link_url" placeholder="Link URL (if type=link)">
                            <button class="btn btn-primary btn-sm">Upload / Add Link</button>
                        </form>

                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_material_link">
                            <h6>Add Material by Path/URL</h6>
                            <select class="form-select mb-2" name="course_id" required>
                                <option value="">Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" name="title" placeholder="Material title" required>
                            <select class="form-select mb-2" name="material_type" required>
                                <option value="link">Link</option>
                                <option value="pdf">PDF (path)</option>
                                <option value="doc">DOC (path)</option>
                                <option value="ppt">PPT (path)</option>
                            </select>
                            <input class="form-control mb-2" name="file_path" placeholder="URL or file path" required>
                            <button class="btn btn-outline-primary btn-sm">Add Material</button>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4"><div class="card p-3"><h6>Courses</h6><ul class="small mb-0"><?php foreach (array_slice($courses, 0, 20) as $course): ?><li>#<?php echo (int)$course['id']; ?> <?php echo htmlspecialchars($course['title']); ?></li><?php endforeach; ?></ul></div></div>
                    <div class="col-md-4">
                        <div class="card p-3"><h6>Recent Videos</h6>
                            <ul class="small mb-2"><?php foreach (array_slice($videos, 0, 10) as $video): ?><li><?php echo htmlspecialchars($video['course_title'] . ' -> ' . $video['title']); ?></li><?php endforeach; ?></ul>
                            <form method="post" class="input-group input-group-sm">
                                <?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_video">
                                <input class="form-control" type="number" min="1" name="video_id" placeholder="Video ID to delete">
                                <button class="btn btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3"><h6>Recent Materials</h6>
                            <ul class="small mb-2"><?php foreach (array_slice($materials, 0, 10) as $material): ?><li>#<?php echo (int)$material['id']; ?> <?php echo htmlspecialchars($material['course_title'] . ' -> ' . $material['title'] . ' [' . $material['material_type'] . ']'); ?></li><?php endforeach; ?></ul>
                            <form method="post" class="input-group input-group-sm">
                                <?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_material">
                                <input class="form-control" type="number" min="1" name="material_id" placeholder="Material ID to delete">
                                <button class="btn btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="headingArticles">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseArticles">
                Articles Backend (Study Links)
            </button>
        </h2>
        <div id="collapseArticles" class="accordion-collapse collapse" data-bs-parent="#manageAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_article">
                            <h6>Add Article</h6>
                            <p class="small eq-muted mb-2">Articles are stored as `study_materials` with type `link`.</p>
                            <select class="form-select mb-2" name="course_id" required>
                                <option value="">Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" name="title" placeholder="Article title" required>
                            <input class="form-control mb-2" name="article_url" placeholder="https://example.com/article" required>
                            <button class="btn btn-primary btn-sm">Create Article</button>
                        </form>
                    </div>
                    <div class="col-md-7">
                        <div class="card p-3">
                            <h6>Recent Articles</h6>
                            <?php if (!$articlesList): ?>
                                <p class="small eq-muted mb-0">No articles added yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Title</th>
                                                <th>Course</th>
                                                <th>URL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($articlesList, 0, 20) as $article): ?>
                                                <tr>
                                                    <td><?php echo (int)$article['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($article['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($article['course_title']); ?></td>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars((string)$article['file_path']); ?>" target="_blank" class="small text-decoration-none">Open</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <form method="post" class="input-group input-group-sm mt-3">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_material">
                                    <input class="form-control" type="number" min="1" name="material_id" placeholder="Article ID to delete (uses material delete)">
                                    <button class="btn btn-outline-danger">Delete Article</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="headingQuestions">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseQuestions">
                Questions, Options & Skill Mapping
            </button>
        </h2>
        <div id="collapseQuestions" class="accordion-collapse collapse" data-bs-parent="#manageAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_question">
                            <h6>Create Question</h6>
                            <textarea class="form-control mb-2" name="question_text" rows="3" placeholder="Question text" required></textarea>
                            <select class="form-select mb-2" name="question_type">
                                <option value="mcq">MCQ</option>
                                <option value="subjective">Subjective</option>
                            </select>
                            <select class="form-select mb-2" name="difficulty">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                            <button class="btn btn-primary btn-sm">Create Question</button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_option">
                            <h6>Add Question Option</h6>
                            <select class="form-select mb-2" name="question_id" required>
                                <option value="">Question</option>
                                <?php foreach (array_slice($questions, 0, 100) as $question): ?>
                                    <option value="<?php echo (int)$question['id']; ?>">
                                        #<?php echo (int)$question['id']; ?> <?php echo htmlspecialchars(mb_strimwidth((string)$question['question_text'], 0, 55, '...')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" name="option_text" placeholder="Option text" required>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_correct" id="is_correct_opt">
                                <label class="form-check-label" for="is_correct_opt">Correct option</label>
                            </div>
                            <button class="btn btn-primary btn-sm">Add Option</button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="map_question_skill">
                            <h6>Map Question to Attribute/Sub-Attribute</h6>
                            <select class="form-select mb-2" name="question_id" required>
                                <option value="">Question</option>
                                <?php foreach (array_slice($questions, 0, 100) as $question): ?>
                                    <option value="<?php echo (int)$question['id']; ?>">#<?php echo (int)$question['id']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select mb-2" name="attribute_id" required>
                                <option value="">Attribute</option>
                                <?php foreach ($attributes as $attribute): ?>
                                    <option value="<?php echo (int)$attribute['id']; ?>"><?php echo htmlspecialchars($attribute['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select mb-2" name="sub_attribute_id" required>
                                <option value="">Sub-attribute</option>
                                <?php foreach ($subAttributes as $sub): ?>
                                    <option value="<?php echo (int)$sub['id']; ?>">
                                        <?php echo htmlspecialchars($sub['attribute_name'] . ' -> ' . $sub['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" type="number" step="0.01" min="0.01" name="weight" placeholder="Weight (e.g. 1.00)" required>
                            <button class="btn btn-primary btn-sm">Add Mapping</button>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4"><div class="card p-3"><h6>Recent Questions</h6><ul class="small mb-2"><?php foreach (array_slice($questions, 0, 10) as $question): ?><li>#<?php echo (int)$question['id']; ?> [<?php echo htmlspecialchars($question['question_type']); ?>] <?php echo htmlspecialchars(mb_strimwidth((string)$question['question_text'], 0, 55, '...')); ?></li><?php endforeach; ?></ul><form method="post" class="input-group input-group-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_question"><input class="form-control" type="number" min="1" name="question_id" placeholder="Question ID to delete"><button class="btn btn-outline-danger">Delete</button></form></div></div>
                    <div class="col-md-4"><div class="card p-3"><h6>Recent Options</h6><ul class="small mb-0"><?php foreach (array_slice($questionOptions, 0, 20) as $option): ?><li>Q#<?php echo (int)$option['question_id']; ?> <?php echo (int)$option['is_correct'] ? '[Correct] ' : ''; ?><?php echo htmlspecialchars(mb_strimwidth((string)$option['option_text'], 0, 50, '...')); ?></li><?php endforeach; ?></ul></div></div>
                    <div class="col-md-4"><div class="card p-3"><h6>Recent Mappings</h6><ul class="small mb-0"><?php foreach (array_slice($questionMappings, 0, 20) as $map): ?><li>Q#<?php echo (int)$map['question_id']; ?> -> <?php echo htmlspecialchars($map['attribute_name'] . '/' . $map['sub_attribute_name']); ?> (<?php echo htmlspecialchars((string)$map['weight']); ?>)</li><?php endforeach; ?></ul></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="headingTests">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTests">
                Tests & Question Mapping
            </button>
        </h2>
        <div id="collapseTests" class="accordion-collapse collapse" data-bs-parent="#manageAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_test">
                            <h6>Create Test</h6>
                            <input class="form-control mb-2" name="title" placeholder="Test title" required>
                            <textarea class="form-control mb-2" name="description" rows="2" placeholder="Description"></textarea>
                            <div class="row g-2 mb-2">
                                <div class="col"><input class="form-control" type="number" min="1" name="total_marks" placeholder="Total marks" required></div>
                                <div class="col"><input class="form-control" type="number" min="1" name="duration_minutes" placeholder="Duration min" required></div>
                            </div>
                            <button class="btn btn-primary btn-sm">Create Test</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_test_question">
                            <h6>Add Question to Test</h6>
                            <select class="form-select mb-2" name="test_id" required>
                                <option value="">Test</option>
                                <?php foreach ($tests as $test): ?>
                                    <option value="<?php echo (int)$test['id']; ?>">#<?php echo (int)$test['id']; ?> <?php echo htmlspecialchars($test['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select mb-2" name="question_id" required>
                                <option value="">Question</option>
                                <?php foreach (array_slice($questions, 0, 100) as $question): ?>
                                    <option value="<?php echo (int)$question['id']; ?>">
                                        #<?php echo (int)$question['id']; ?> <?php echo htmlspecialchars(mb_strimwidth((string)$question['question_text'], 0, 55, '...')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" type="number" min="1" name="marks" placeholder="Marks" required>
                            <button class="btn btn-primary btn-sm">Add to Test</button>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6"><div class="card p-3"><h6>Recent Tests</h6><ul class="small mb-2"><?php foreach (array_slice($tests, 0, 10) as $test): ?><li>#<?php echo (int)$test['id']; ?> <?php echo htmlspecialchars($test['title']); ?> (<?php echo (int)$test['total_marks']; ?> marks, <?php echo (int)$test['duration_minutes']; ?> min)</li><?php endforeach; ?></ul><form method="post" class="input-group input-group-sm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_test"><input class="form-control" type="number" min="1" name="test_id" placeholder="Test ID to delete"><button class="btn btn-outline-danger">Delete</button></form></div></div>
                    <div class="col-md-6"><div class="card p-3"><h6>Recent Test Questions</h6><ul class="small mb-0"><?php foreach (array_slice($testQuestions, 0, 20) as $tq): ?><li><?php echo htmlspecialchars($tq['test_title']); ?> <- Q#<?php echo (int)$tq['question_id']; ?> (<?php echo (int)$tq['marks']; ?>)</li><?php endforeach; ?></ul></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="headingPaths">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePaths">
                Learning Paths & Achievements
            </button>
        </h2>
        <div id="collapsePaths" class="accordion-collapse collapse" data-bs-parent="#manageAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_learning_path">
                            <h6>Create Learning Path</h6>
                            <input class="form-control mb-2" name="title" placeholder="Path title" required>
                            <textarea class="form-control mb-2" name="description" rows="2" placeholder="Description"></textarea>
                            <button class="btn btn-primary btn-sm">Create Path</button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_path_course">
                            <h6>Add Course to Path</h6>
                            <select class="form-select mb-2" name="path_id" required>
                                <option value="">Learning path</option>
                                <?php foreach ($paths as $path): ?>
                                    <option value="<?php echo (int)$path['id']; ?>"><?php echo htmlspecialchars($path['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select mb-2" name="course_id" required>
                                <option value="">Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-control mb-2" type="number" min="1" name="sequence_order" placeholder="Sequence order">
                            <button class="btn btn-primary btn-sm">Add Course</button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" class="card p-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_achievement">
                            <h6>Create Achievement <?php echo $user['role'] !== 'school_admin' ? '(Admin only)' : ''; ?></h6>
                            <input class="form-control mb-2" name="title" placeholder="Achievement title" <?php echo $user['role'] === 'school_admin' ? 'required' : 'disabled'; ?>>
                            <input class="form-control mb-2" name="icon" placeholder="Icon key (optional)" <?php echo $user['role'] === 'school_admin' ? '' : 'disabled'; ?>>
                            <textarea class="form-control mb-2" name="description" rows="2" placeholder="Description" <?php echo $user['role'] === 'school_admin' ? '' : 'disabled'; ?>></textarea>
                            <select class="form-select mb-2" name="criteria_type" <?php echo $user['role'] === 'school_admin' ? '' : 'disabled'; ?>>
                                <option value="score">Score</option>
                                <option value="course_completion">Course Completion</option>
                                <option value="activity">Activity</option>
                            </select>
                            <input class="form-control mb-2" type="number" min="1" name="criteria_value" placeholder="Criteria value" <?php echo $user['role'] === 'school_admin' ? 'required' : 'disabled'; ?>>
                            <button class="btn btn-primary btn-sm" <?php echo $user['role'] === 'school_admin' ? '' : 'disabled'; ?>>Create Achievement</button>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4"><div class="card p-3"><h6>Learning Paths</h6><ul class="small mb-0"><?php foreach (array_slice($paths, 0, 20) as $path): ?><li>#<?php echo (int)$path['id']; ?> <?php echo htmlspecialchars($path['title']); ?></li><?php endforeach; ?></ul></div></div>
                    <div class="col-md-4"><div class="card p-3"><h6>Path Course Mappings</h6><ul class="small mb-0"><?php foreach (array_slice($pathCourses, 0, 20) as $pc): ?><li><?php echo htmlspecialchars($pc['path_title'] . ' -> ' . $pc['course_title']); ?> (<?php echo (int)$pc['sequence_order']; ?>)</li><?php endforeach; ?></ul></div></div>
                    <div class="col-md-4"><div class="card p-3"><h6>Achievements</h6><ul class="small mb-0"><?php foreach (array_slice($achievements, 0, 20) as $achievement): ?><li><?php echo htmlspecialchars($achievement['title']); ?> [<?php echo htmlspecialchars($achievement['criteria_type']); ?> >= <?php echo (int)$achievement['criteria_value']; ?>]</li><?php endforeach; ?></ul></div></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user['role'] === 'school_admin'): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingAdminOps">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdminOps">
                    Users & Attendance (Admin)
                </button>
            </h2>
            <div id="collapseAdminOps" class="accordion-collapse collapse" data-bs-parent="#manageAccordion">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="post" class="card p-3">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_user">
                                <h6>Update User Status / School</h6>
                                <select class="form-select mb-2" name="user_id" required>
                                    <option value="">Select user</option>
                                    <?php foreach ($usersForAdmin as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>">
                                            #<?php echo (int)$u['id']; ?> <?php echo htmlspecialchars($u['name'] . ' (' . $u['role'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-select mb-2" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <input class="form-control mb-2" type="number" min="1" name="school_id" placeholder="School ID (blank to clear)">
                                <button class="btn btn-primary btn-sm">Update User</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post" class="card p-3">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="mark_attendance">
                                <h6>Mark Attendance</h6>
                                <select class="form-select mb-2" name="student_id" required>
                                    <option value="">Select student</option>
                                    <?php foreach ($studentsForAttendance as $student): ?>
                                        <option value="<?php echo (int)$student['id']; ?>">
                                            #<?php echo (int)$student['id']; ?> <?php echo htmlspecialchars($student['name'] . ' (' . $student['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control mb-2" type="date" name="attendance_date" required value="<?php echo date('Y-m-d'); ?>">
                                <select class="form-select mb-2" name="attendance_status" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                                <button class="btn btn-primary btn-sm">Save Attendance</button>
                            </form>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="card p-3">
                                <h6>Recent Users</h6>
                                <ul class="small mb-0">
                                    <?php foreach (array_slice($usersForAdmin, 0, 20) as $u): ?>
                                        <li>
                                            #<?php echo (int)$u['id']; ?> <?php echo htmlspecialchars($u['name']); ?>
                                            [<?php echo htmlspecialchars($u['role']); ?>]
                                            - <?php echo htmlspecialchars($u['status']); ?>
                                            - school: <?php echo $u['school_id'] !== null ? (int)$u['school_id'] : 'null'; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <h6>Recent Attendance</h6>
                                <ul class="small mb-0">
                                    <?php foreach (array_slice($attendanceRows, 0, 20) as $row): ?>
                                        <li><?php echo htmlspecialchars($row['date'] . ' - ' . $row['student_name'] . ' - ' . $row['status']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
