<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$user = backend_user();
require_once dirname(__DIR__) . '/includes_header.php';

$pdo = get_pdo();
$schema = backend_schema_ready($pdo);
?>
<div class="eq-page-head">
    <h2>Backend Console</h2>
    <p class="subtitle">Entity-specific administration pages for content admins and super admins.</p>
</div>

<?php require __DIR__ . '/nav.php'; ?>

<?php if (!$schema['ready']): ?>
<div class="alert alert-warning">
    Missing backend taxonomy tables: <code><?php echo htmlspecialchars(implode(', ', $schema['missing'])); ?></code><br>
    Run migration: <code>migrations/2026-02-28_backend_taxonomy_upgrade.sql</code>
</div>
<?php endif; ?>

<div class="row g-3">
<?php
$cards = [
    ['Schools', 'Master list used by role-based registration and school mapping.', 'backend/schools.php'],
    ['Taxonomy', 'Subjects, grades, sessions, categories, and tags.', 'backend/taxonomy.php'],
    ['Attributes', 'Learning dimensions and sub-attributes.', 'backend/attributes.php'],
    ['Questions', 'Question bank, options, mapping, blueprint.', 'backend/questions.php'],
    ['Tests', 'Assessments, question mapping, and test settings.', 'backend/tests.php'],
    ['Courses', 'Course catalog and taxonomy mappings.', 'backend/courses.php'],
    ['Video Tutorials', 'Dedicated video backend with metadata controls.', 'backend/videos.php'],
    ['Study Materials', 'Dedicated material backend with metadata controls.', 'backend/materials.php'],
    ['Articles', 'Dedicated article backend for resource links.', 'backend/articles.php'],
    ['Content Meta', 'Cross-entity metadata management.', 'backend/content.php'],
    ['Learning Paths', 'Path orchestration and sequence mapping.', 'backend/paths.php'],
    ['Achievements', 'Badge/criteria setup and award logic inputs.', 'backend/achievements.php'],
    ['Community', 'Community moderation and governance.', 'backend/community.php'],
    ['Users', 'Status, school assignment, and role profile review.', 'backend/users.php'],
];
foreach ($cards as [$title, $desc, $link]):
?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <h5><?php echo htmlspecialchars($title); ?></h5>
                <p class="small text-muted"><?php echo htmlspecialchars($desc); ?></p>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url_for($link)); ?>">Open</a>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes_footer.php';
