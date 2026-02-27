<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

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
    <?php
    render_static_fallback([
        'eyebrow' => 'Assessment Center',
        'title' => 'No tests published yet',
        'description' => 'This section will display active MCQ and subjective tests as soon as your assessment bank is added.',
        'points' => [
            'Questions can map to multiple attributes and sub-attributes.',
            'Weighted scoring updates skill progress automatically.',
            'Students can attempt tests and view attempt status in real time.',
        ],
        'cards' => [
            ['title' => 'Math Mastery Check', 'meta' => '40 marks · 30 min', 'text' => 'Tracks mathematics sub-skills with weighted performance mapping.'],
            ['title' => 'Creative Thinking Sprint', 'meta' => '25 marks · 20 min', 'text' => 'Blends MCQ + subjective responses for innovation profiling.'],
            ['title' => 'Leadership Readiness', 'meta' => '30 marks · 25 min', 'text' => 'Measures communication, teamwork, and initiative indicators.'],
        ],
        'primary_label' => 'Go to Dashboard',
        'primary_link' => url_for('dashboard.php'),
        'secondary_label' => 'Add Tests in Backend',
        'secondary_link' => url_for('manage_lms.php'),
    ]);
    ?>
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
