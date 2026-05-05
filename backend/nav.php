<?php
declare(strict_types=1);
?>
<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/index.php')); ?>">Overview</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/schools.php')); ?>">Schools</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/taxonomy.php')); ?>">Taxonomy</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/attributes.php')); ?>">Attributes</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/questions.php')); ?>">Questions</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/tests.php')); ?>">Tests</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/practice_papers.php')); ?>">Practice Papers</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/courses.php')); ?>">Courses</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/videos.php')); ?>">Videos</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/materials.php')); ?>">Materials</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/articles.php')); ?>">Articles</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/content.php')); ?>">Content Meta</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/paths.php')); ?>">Learning Paths</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/achievements.php')); ?>">Achievements</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/community.php')); ?>">Community</a>
        <a class="btn btn-outline-primary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('backend/users.php')); ?>">Users</a>
        <a class="btn btn-outline-secondary btn-sm eq-allow-nav" href="<?php echo htmlspecialchars(url_for('dashboard.php')); ?>">Dashboard</a>
    </div>
</div>
