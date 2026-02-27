<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';

$pdo = get_pdo();

$stmt = $pdo->query(
    'SELECT t.id, t.title, t.description, t.total_marks, t.duration_minutes, t.created_at,
            u.name AS teacher_name
     FROM tests t
     LEFT JOIN users u ON t.created_by = u.id
     ORDER BY t.created_at DESC'
);
$tests = $stmt->fetchAll();

$attempted = [];
if ($authUser && $authUser['role'] === 'student') {
    $stmt = $pdo->prepare(
        'SELECT test_id FROM test_attempts WHERE student_id = ?'
    );
    $stmt->execute([(int)$authUser['sub']]);
    foreach ($stmt->fetchAll() as $row) {
        $attempted[(int)$row['test_id']] = true;
    }
}
?>

<div class="eq-page-head">
    <h2>Tests</h2>
    <p class="subtitle">Attempt MCQ and subjective assessments mapped to attributes and sub-attributes for live skill tracking.</p>
</div>

<?php if (!$tests): ?>
    <div class="alert alert-info">
        No tests have been created yet. Add rows into the <code>tests</code>, <code>questions</code>,
        and related tables to make them available here.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($tests as $test): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($test['title']); ?></h5>
                        <p class="card-text small text-muted flex-grow-1">
                            <?php echo htmlspecialchars(text_preview((string)$test['description'], 140, '...')); ?>
                        </p>
                        <p class="small mb-2">
                            <?php if ($test['teacher_name']): ?>
                                Teacher: <?php echo htmlspecialchars($test['teacher_name']); ?><br>
                            <?php endif; ?>
                            Marks: <?php echo (int)$test['total_marks']; ?> |
                            Duration: <?php echo (int)$test['duration_minutes']; ?> min
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <?php if ($authUser && $authUser['role'] === 'student'): ?>
                                <?php if (isset($attempted[(int)$test['id']])): ?>
                                    <span class="badge text-bg-secondary">Attempted</span>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars(url_for('test_attempt.php?id=' . (int)$test['id'])); ?>"
                                       class="btn btn-sm btn-primary">
                                        Attempt
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">Login as a student to attempt.</span>
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
