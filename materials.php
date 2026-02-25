<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';

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
    <div class="alert alert-info">
        No study materials found. Add rows into <code>study_materials</code> with type pdf/doc/ppt.
    </div>
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
