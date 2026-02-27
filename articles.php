<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$pdo = get_pdo();

// Treat study materials of type "link" as articles
$stmt = $pdo->query(
    "SELECT sm.id, sm.title, sm.file_path AS url, c.title AS course_title
     FROM study_materials sm
     LEFT JOIN courses c ON sm.course_id = c.id
     WHERE sm.material_type = 'link'
     ORDER BY sm.uploaded_at DESC"
);
$articles = $stmt->fetchAll();
?>

<div class="eq-page-head">
    <h2>Articles</h2>
    <p class="subtitle">Learning insights, platform guides, and study tips for students and educators.</p>
</div>
<p class="text-muted small">
    Articles are managed as study materials with type <code>link</code> in the database.
</p>

<?php if (!$articles): ?>
    <?php
    render_static_fallback([
        'eyebrow' => 'Knowledge Hub',
        'title' => 'Articles will appear here soon',
        'description' => 'This section lists curated learning articles stored as study materials with type link.',
        'points' => [
            'Articles can be tagged per course for focused reading.',
            'Perfect for concept summaries, exam tips, and revision notes.',
            'Each item opens directly to external or internal reading links.',
        ],
        'cards' => [
            ['title' => 'How to Build a Weekly Study Plan', 'meta' => 'Productivity', 'text' => 'A practical framework for consistency and reduced study stress.'],
            ['title' => 'Creative Problem Solving for Students', 'meta' => 'Creative Domain', 'text' => 'Structured methods to improve ideation and execution.'],
            ['title' => 'Parent Guide to Progress Dashboards', 'meta' => 'Parent Insights', 'text' => 'How to interpret skill trends and support learning at home.'],
        ],
        'primary_label' => 'Go to Resources',
        'primary_link' => url_for('materials.php'),
        'secondary_label' => 'Add Article Links',
        'secondary_link' => url_for('manage_lms.php'),
    ]);
    ?>
<?php else: ?>
    <ul class="list-group">
        <?php foreach ($articles as $a): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <a href="<?php echo htmlspecialchars($a['url']); ?>" target="_blank">
                        <?php echo htmlspecialchars($a['title']); ?>
                    </a>
                    <?php if ($a['course_title']): ?>
                        <div class="small text-muted">Course: <?php echo htmlspecialchars($a['course_title']); ?></div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
