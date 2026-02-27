<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$pdo = get_pdo();

// Load all learning paths with their courses
$pathsStmt = $pdo->query(
    'SELECT lp.id, lp.title, lp.description
     FROM learning_paths lp
     ORDER BY lp.id ASC'
);
$paths = $pathsStmt->fetchAll();

$coursesByPath = [];
if ($paths) {
    $pathIds = array_column($paths, 'id');
    $in = implode(',', array_fill(0, count($pathIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT pc.path_id, c.id AS course_id, c.title, pc.sequence_order
         FROM path_courses pc
         JOIN courses c ON pc.course_id = c.id
         WHERE pc.path_id IN ($in)
         ORDER BY pc.path_id ASC, pc.sequence_order ASC"
    );
    $stmt->execute($pathIds);
    foreach ($stmt->fetchAll() as $row) {
        $pid = (int)$row['path_id'];
        if (!isset($coursesByPath[$pid])) {
            $coursesByPath[$pid] = [];
        }
        $coursesByPath[$pid][] = $row;
    }
}
?>

<div class="eq-page-head">
    <h2>Learning Paths</h2>
    <p class="subtitle">
        Follow a structured sequence of courses or learn self-paced. Progress is saved so students can resume anytime.
    </p>
</div>

<?php if (!$paths): ?>
    <?php
    render_static_fallback([
        'eyebrow' => 'Guided Journeys',
        'title' => 'Learning paths are not configured yet',
        'description' => 'Once paths and sequence mapping are added, students will be able to follow structured journeys or learn self-paced.',
        'points' => [
            'Each path can contain multiple courses in a defined sequence.',
            'Students can pause and resume from their last progress point.',
            'Paths support both school-led and flexible self-paced journeys.',
        ],
        'cards' => [
            ['title' => 'Future Scholars Path', 'meta' => 'Academic Track', 'text' => 'Foundational academic sequence across core subjects.'],
            ['title' => 'Young Innovators Path', 'meta' => 'Creative + Technical', 'text' => 'Balanced route for design thinking and coding fundamentals.'],
            ['title' => 'Leadership Builder Path', 'meta' => 'Communication + Teamwork', 'text' => 'Builds confidence, collaboration, and decision-making skills.'],
        ],
        'primary_label' => 'Explore Courses',
        'primary_link' => url_for('courses.php'),
        'secondary_label' => 'Configure Paths',
        'secondary_link' => url_for('manage_lms.php'),
    ]);
    ?>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($paths as $path): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($path['title']); ?></h5>
                        <p class="card-text small text-muted flex-grow-1">
                            <?php echo nl2br(htmlspecialchars((string)$path['description'])); ?>
                        </p>
                        <h6 class="small fw-semibold mt-2">Courses in this path</h6>
                        <?php if (empty($coursesByPath[(int)$path['id']] ?? [])): ?>
                            <p class="small text-muted">No courses linked yet.</p>
                        <?php else: ?>
                            <ol class="small">
                                <?php foreach ($coursesByPath[(int)$path['id']] as $c): ?>
                                    <li>
                                        <a href="<?php echo htmlspecialchars(url_for('course.php?id=' . (int)$c['course_id'])); ?>">
                                            <?php echo htmlspecialchars($c['title']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
