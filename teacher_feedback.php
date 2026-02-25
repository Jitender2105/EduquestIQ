<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_auth.php';
require_once __DIR__ . '/includes_csrf.php';

$user = require_auth(['teacher', 'school_admin', 'parent']);
$pdo = get_pdo();

$errors = [];
$success = null;
$tableReady = false;
$parentLinksReady = false;

try {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute(['teacher_feedback']);
    $tableReady = (bool)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute(['parent_student_links']);
    $parentLinksReady = (bool)$stmt->fetchColumn();
} catch (Throwable $e) {
    $tableReady = false;
    $parentLinksReady = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    if ($user['role'] !== 'teacher') {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token.';
    }

    $studentId = (int)($_POST['student_id'] ?? 0);
    $feedbackText = trim((string)($_POST['feedback_text'] ?? ''));
    if ($studentId <= 0) {
        $errors[] = 'Student is required.';
    }
    if ($feedbackText === '') {
        $errors[] = 'Feedback text is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "student"');
        $stmt->execute([$studentId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Invalid student selected.';
        } else {
            $teacherId = (int)$user['sub'];
            $stmt = $pdo->prepare(
                'INSERT INTO teacher_feedback (teacher_id, student_id, feedback_text, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$teacherId, $studentId, $feedbackText]);
            $success = 'Feedback added.';
        }
    }
}

$students = [];
if ($tableReady && in_array($user['role'], ['teacher', 'school_admin'], true)) {
    if ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             JOIN course_enrollments ce ON ce.student_id = u.id
             JOIN courses c ON c.id = ce.course_id
             WHERE u.role = "student" AND c.teacher_id = ?
             ORDER BY u.name ASC'
        );
        $stmt->execute([(int)$user['sub']]);
    } else {
        $stmt = $pdo->query('SELECT id, name, email FROM users WHERE role = "student" ORDER BY name ASC LIMIT 200');
    }
    $students = $stmt->fetchAll();
}

if (!$tableReady) {
    $feedbackRows = [];
    $errors[] = 'The teacher_feedback table is missing. Re-import schema.sql or add the table from the latest schema.';
} elseif ($user['role'] === 'parent' && !$parentLinksReady) {
    $feedbackRows = [];
    $errors[] = 'The parent_student_links table is missing. Re-import schema.sql or add the table from the latest schema.';
} elseif ($user['role'] === 'parent') {
    $stmt = $pdo->prepare(
        'SELECT tf.feedback_text, tf.created_at, t.name AS teacher_name, s.name AS student_name
         FROM teacher_feedback tf
         JOIN users t ON t.id = tf.teacher_id
         JOIN users s ON s.id = tf.student_id
         JOIN parent_student_links psl ON psl.student_id = s.id
         WHERE psl.parent_id = ?
         ORDER BY tf.created_at DESC
         LIMIT 20'
    );
    $stmt->execute([(int)$user['sub']]);
    $feedbackRows = $stmt->fetchAll();
} elseif ($user['role'] === 'teacher') {
    $stmt = $pdo->prepare(
        'SELECT tf.feedback_text, tf.created_at, s.name AS student_name
         FROM teacher_feedback tf
         JOIN users s ON s.id = tf.student_id
         WHERE tf.teacher_id = ?
         ORDER BY tf.created_at DESC
         LIMIT 20'
    );
    $stmt->execute([(int)$user['sub']]);
    $feedbackRows = $stmt->fetchAll();
} else {
    $stmt = $pdo->query(
        'SELECT tf.feedback_text, tf.created_at, t.name AS teacher_name, s.name AS student_name
         FROM teacher_feedback tf
         JOIN users t ON t.id = tf.teacher_id
         JOIN users s ON s.id = tf.student_id
         ORDER BY tf.created_at DESC
         LIMIT 50'
    );
    $feedbackRows = $stmt->fetchAll();
}

require_once __DIR__ . '/includes_header.php';
?>

<div class="eq-page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-0">Teacher Feedback</h2>
        <p class="subtitle">Teachers can publish qualitative student feedback; parents and admins see it in real time.</p>
    </div>
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

<div class="row g-3">
    <?php if ($user['role'] === 'teacher'): ?>
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Add Feedback</h5>
                    <?php if (!$students): ?>
                        <p class="text-muted small mb-0">No students available to select yet.</p>
                    <?php else: ?>
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Student</label>
                                <select name="student_id" class="form-select" required>
                                    <option value="">Select student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo (int)$student['id']; ?>">
                                            <?php echo htmlspecialchars($student['name'] . ' (' . $student['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Feedback</label>
                                <textarea name="feedback_text" rows="4" class="form-control" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" <?php echo $tableReady ? '' : 'disabled'; ?>>Save Feedback</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?php echo $user['role'] === 'teacher' ? 'col-md-7' : 'col-md-12'; ?>">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Recent Feedback</h5>
                <?php if (!$feedbackRows): ?>
                    <p class="text-muted small mb-0">No feedback entries yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($feedbackRows as $row): ?>
                            <div class="list-group-item px-0">
                                <div class="small text-muted mb-1">
                                    <?php if (isset($row['teacher_name'])): ?>
                                        Teacher: <?php echo htmlspecialchars($row['teacher_name']); ?> ·
                                    <?php endif; ?>
                                    Student: <?php echo htmlspecialchars($row['student_name']); ?> ·
                                    <?php echo htmlspecialchars($row['created_at']); ?>
                                </div>
                                <div><?php echo nl2br(htmlspecialchars((string)$row['feedback_text'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
