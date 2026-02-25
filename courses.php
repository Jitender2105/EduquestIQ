<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';

$pdo = get_pdo();

$stmt = $pdo->query(
    'SELECT c.id, c.title, c.description, c.created_at,
            u.name AS teacher_name,
            a.name AS attribute_name
     FROM courses c
     LEFT JOIN users u ON c.teacher_id = u.id
     LEFT JOIN attributes a ON c.attribute_id = a.id
     ORDER BY c.created_at DESC'
);
$courses = $stmt->fetchAll();

$enrolledCourseIds = [];
if ($authUser && $authUser['role'] === 'student') {
    $stmt = $pdo->prepare(
        'SELECT course_id FROM course_enrollments WHERE student_id = ?'
    );
    $stmt->execute([(int)$authUser['sub']]);
    foreach ($stmt->fetchAll() as $row) {
        $enrolledCourseIds[(int)$row['course_id']] = true;
    }
}
?>

<div class="eq-page-head d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <h2>Courses</h2>
        <p class="subtitle">Browse skill-mapped courses, enroll as a student, and continue learning at your own pace.</p>
    </div>
    <?php if ($authUser && in_array($authUser['role'], ['teacher', 'school_admin'], true)): ?>
        <a href="<?php echo htmlspecialchars(url_for('manage_lms.php')); ?>" class="btn btn-primary btn-sm">
            Manage Courses
        </a>
    <?php endif; ?>
</div>

<?php if (!$courses): ?>
    <div class="alert alert-info">
        No courses have been created yet. Create rows in the <code>courses</code> table to see them here.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($courses as $course): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                        <p class="card-text small text-muted flex-grow-1">
                            <?php echo htmlspecialchars(mb_strimwidth((string)$course['description'], 0, 140, '...')); ?>
                        </p>
                        <p class="small mb-2">
                            <?php if ($course['teacher_name']): ?>
                                Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?><br>
                            <?php endif; ?>
                            <?php if ($course['attribute_name']): ?>
                                Attribute: <?php echo htmlspecialchars($course['attribute_name']); ?>
                            <?php endif; ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo htmlspecialchars(url_for('course.php?id=' . (int)$course['id'])); ?>"
                               class="btn btn-sm btn-outline-primary">
                                View
                            </a>
                            <?php if ($authUser && $authUser['role'] === 'student'): ?>
                                <?php if (isset($enrolledCourseIds[(int)$course['id']])): ?>
                                    <span class="badge text-bg-success">Enrolled</span>
                                <?php else: ?>
                                    <form method="post" action="<?php echo htmlspecialchars(url_for('enroll_course.php')); ?>" class="m-0">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Enroll</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
