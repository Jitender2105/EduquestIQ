<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';

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
    <div class="alert alert-info">
        No articles found. Add rows into <code>study_materials</code> with <code>material_type = 'link'</code>.
    </div>
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
