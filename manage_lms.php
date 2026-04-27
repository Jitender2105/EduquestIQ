<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$user = require_auth(['teacher', 'school_admin']);

$modules = [
    ['title' => 'Backend Overview', 'desc' => 'Open the modular LMS backend home.', 'link' => url_for('backend/index.php')],
    ['title' => 'Schools', 'desc' => 'School master records and onboarding scope.', 'link' => url_for('backend/schools.php')],
    ['title' => 'Taxonomy', 'desc' => 'Subjects, grades, sessions, categories, and tags.', 'link' => url_for('backend/taxonomy.php')],
    ['title' => 'Attributes', 'desc' => 'Attributes and sub-attributes.', 'link' => url_for('backend/attributes.php')],
    ['title' => 'Questions', 'desc' => 'Question bank, options, and skill mapping.', 'link' => url_for('backend/questions.php')],
    ['title' => 'Tests', 'desc' => 'Single-screen test builder and assessment settings.', 'link' => url_for('backend/tests.php')],
    ['title' => 'Courses', 'desc' => 'Course catalog and mappings.', 'link' => url_for('backend/courses.php')],
    ['title' => 'Videos', 'desc' => 'Video tutorial management.', 'link' => url_for('backend/videos.php')],
    ['title' => 'Materials', 'desc' => 'Study materials and resource metadata.', 'link' => url_for('backend/materials.php')],
    ['title' => 'Articles', 'desc' => 'Article links and knowledge resources.', 'link' => url_for('backend/articles.php')],
    ['title' => 'Content Meta', 'desc' => 'Cross-entity metadata and publishing controls.', 'link' => url_for('backend/content.php')],
    ['title' => 'Learning Paths', 'desc' => 'Path orchestration and sequencing.', 'link' => url_for('backend/paths.php')],
    ['title' => 'Achievements', 'desc' => 'Badge criteria and award setup.', 'link' => url_for('backend/achievements.php')],
    ['title' => 'Community', 'desc' => 'Moderation and community governance.', 'link' => url_for('backend/community.php')],
    ['title' => 'Users', 'desc' => 'Status, school assignment, and role profile review.', 'link' => url_for('backend/users.php')],
];
?>

<div class="eq-page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-0">LMS Management Console</h2>
        <div class="subtitle">Each backend module now lives on its own URL under the backend subdomain.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('backend/index.php')); ?>">Open Backend Overview</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(url_for('logout.php')); ?>">Logout</a>
    </div>
</div>

<?php if ($user['role'] === 'school_admin'): ?>
    <div class="alert alert-info">
        You are signed in as a school admin. Use the separate backend URLs below to manage each module independently.
    </div>
<?php else: ?>
    <div class="alert alert-secondary">
        Teacher access is limited to modules you are allowed to manage.
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($modules as $module): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?php echo htmlspecialchars($module['title']); ?></h5>
                    <p class="card-text small text-muted flex-grow-1"><?php echo htmlspecialchars($module['desc']); ?></p>
                    <a class="btn btn-sm btn-outline-primary mt-auto" href="<?php echo htmlspecialchars($module['link']); ?>">Open</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
