<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$pdo = get_pdo();

$stmt = $pdo->query(
    "SELECT sm.id, sm.title, sm.file_path, sm.material_type, sm.uploaded_at,
            c.title AS course_title
     FROM study_materials sm
     LEFT JOIN courses c ON sm.course_id = c.id
     WHERE sm.material_type IN ('pdf','doc','ppt')
     ORDER BY sm.uploaded_at DESC"
);
$materials = $stmt->fetchAll();
?>

<div class="eq-page-head">
    <h2>Study Materials</h2>
    <p class="subtitle">Browse uploaded PDFs, documents, presentations, and linked resources across all courses.</p>
</div>

<?php if (!$materials): ?>
    <?php
    render_static_fallback([
        'eyebrow' => 'Study Library',
        'title' => 'No study materials available yet',
        'description' => 'Add PDFs, DOCs, and PPTs to build a structured resource center for each course.',
        'points' => [
            'Resources support revision, assignments, and exam preparation.',
            'Material uploads are linked to courses for easier navigation.',
            'Students can access learning files from any device anytime.',
        ],
        'cards' => [
            ['title' => 'Math Formula Handbook', 'meta' => 'PDF', 'text' => 'Quick-reference formulas for core algebra and geometry concepts.'],
            ['title' => 'Science Lab Activity Sheet', 'meta' => 'DOC', 'text' => 'Experiment templates with observation and analysis sections.'],
            ['title' => 'Coding Basics Presentation', 'meta' => 'PPT', 'text' => 'Visual walkthrough of programming fundamentals and examples.'],
        ],
        'primary_label' => 'Open Dashboard',
        'primary_link' => url_for('dashboard.php'),
        'secondary_label' => 'Upload Materials',
        'secondary_link' => url_for('manage_lms.php'),
    ]);
    ?>
<?php else: ?>
    <ul class="list-group">
        <?php foreach ($materials as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                    <?php if ($m['course_title']): ?>
                        <span class="small text-muted"> · Course: <?php echo htmlspecialchars($m['course_title']); ?></span>
                    <?php endif; ?>
                    <div class="small text-muted">
                        Type: <?php echo htmlspecialchars($m['material_type']); ?> ·
                        Uploaded: <?php echo htmlspecialchars($m['uploaded_at']); ?>
                    </div>
                    <?php if ($m['file_path']): ?>
                        <div class="small">
                            <a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank">Open</a>
                        </div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
