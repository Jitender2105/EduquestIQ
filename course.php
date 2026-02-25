<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_csrf.php';

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($courseId <= 0) {
    header('Location: ' . url_for('courses.php'));
    exit;
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT c.*, u.name AS teacher_name, a.name AS attribute_name
     FROM courses c
     LEFT JOIN users u ON c.teacher_id = u.id
     LEFT JOIN attributes a ON c.attribute_id = a.id
     WHERE c.id = ?'
);
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: ' . url_for('courses.php'));
    exit;
}

// Load lectures and materials
$stmt = $pdo->prepare(
    'SELECT * FROM video_lectures WHERE course_id = ? ORDER BY sequence_order ASC, id ASC'
);
$stmt->execute([$courseId]);
$videos = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT * FROM study_materials WHERE course_id = ? ORDER BY uploaded_at DESC'
);
$stmt->execute([$courseId]);
$materials = $stmt->fetchAll();

// Load per-item progress for student
$progressByVideo = [];
$progressByMaterial = [];

if ($authUser && $authUser['role'] === 'student') {
    $stmt = $pdo->prepare(
        'SELECT video_id, material_id, completion_percentage
         FROM progress
         WHERE student_id = ? AND course_id = ?'
    );
    $stmt->execute([(int)$authUser['sub'], $courseId]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['video_id']) {
            $progressByVideo[(int)$row['video_id']] = (float)$row['completion_percentage'];
        } elseif ($row['material_id']) {
            $progressByMaterial[(int)$row['material_id']] = (float)$row['completion_percentage'];
        }
    }
}
?>

<div class="mb-3">
    <a href="<?php echo htmlspecialchars(url_for('courses.php')); ?>" class="btn btn-link">&larr; Back to courses</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="eq-page-head">
            <h2><?php echo htmlspecialchars($course['title']); ?></h2>
            <p class="subtitle">
            <?php if ($course['teacher_name']): ?>
                Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?> |
            <?php endif; ?>
            <?php if ($course['attribute_name']): ?>
                Attribute: <?php echo htmlspecialchars($course['attribute_name']); ?>
            <?php endif; ?>
            </p>
        </div>
        <p><?php echo nl2br(htmlspecialchars((string)$course['description'])); ?></p>

        <h4 class="mt-4 mb-3">Video Lectures</h4>
        <?php if (!$videos): ?>
            <p class="text-muted small">No video lectures have been added yet.</p>
        <?php else: ?>
            <ul class="list-group mb-3">
                <?php foreach ($videos as $v): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($v['title']); ?></div>
                            <?php if ($v['video_url']): ?>
                                <a href="<?php echo htmlspecialchars($v['video_url']); ?>" target="_blank" class="small">
                                    Open video
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($authUser && $authUser['role'] === 'student'): ?>
                            <form method="post" action="<?php echo htmlspecialchars(url_for('progress_mark.php')); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="course_id" value="<?php echo (int)$courseId; ?>">
                                <input type="hidden" name="video_id" value="<?php echo (int)$v['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo isset($progressByVideo[(int)$v['id']]) ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                    <?php echo isset($progressByVideo[(int)$v['id']]) ? 'Completed' : 'Mark complete'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h4 class="mb-3">Study Materials</h4>
        <?php if (!$materials): ?>
            <p class="text-muted small">No study materials have been added yet.</p>
        <?php else: ?>
            <ul class="list-group mb-3">
                <?php foreach ($materials as $m): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($m['title']); ?></div>
                            <?php if ($m['file_path']): ?>
                                <a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank" class="small">
                                    Open <?php echo htmlspecialchars($m['material_type']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($authUser && $authUser['role'] === 'student'): ?>
                            <form method="post" action="<?php echo htmlspecialchars(url_for('progress_mark.php')); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="course_id" value="<?php echo (int)$courseId; ?>">
                                <input type="hidden" name="material_id" value="<?php echo (int)$m['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo isset($progressByMaterial[(int)$m['id']]) ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                    <?php echo isset($progressByMaterial[(int)$m['id']]) ? 'Completed' : 'Mark complete'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
