<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$pdo = get_pdo();

$stmt = $pdo->query(
    'SELECT vl.id, vl.title, vl.video_url, vl.duration, c.title AS course_title
     FROM video_lectures vl
     LEFT JOIN courses c ON vl.course_id = c.id
     ORDER BY c.title ASC, vl.sequence_order ASC'
);
$videos = $stmt->fetchAll();
?>

<div class="eq-page-head">
    <h2>Video Lectures</h2>
    <p class="subtitle">Central catalog of course video lectures with sequence and duration metadata.</p>
</div>

<?php if (!$videos): ?>
    <?php
    render_static_fallback([
        'eyebrow' => 'Video Academy',
        'title' => 'Video lectures are not published yet',
        'description' => 'Once videos are added to courses, this catalog will show all lecture titles, durations, and watch links.',
        'points' => [
            'Videos can be organized by sequence order per course.',
            'Students can track completion percentage from each lecture.',
            'Course-level analytics can use watch progress for insights.',
        ],
        'cards' => [
            ['title' => 'Algebra Fundamentals', 'meta' => '18 min · Academic', 'text' => 'Step-by-step concept teaching with examples and checkpoints.'],
            ['title' => 'Creative Writing Basics', 'meta' => '12 min · Creative', 'text' => 'Narrative structure, style, and practical writing prompts.'],
            ['title' => 'Intro to Python Logic', 'meta' => '22 min · Technical', 'text' => 'Beginner-friendly programming concepts and coding mindset.'],
        ],
        'primary_label' => 'Browse Courses',
        'primary_link' => url_for('courses.php'),
        'secondary_label' => 'Upload Videos',
        'secondary_link' => url_for('manage_lms.php'),
    ]);
    ?>
<?php else: ?>
    <ul class="list-group">
        <?php foreach ($videos as $v): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo htmlspecialchars($v['title']); ?></strong>
                    <?php if ($v['course_title']): ?>
                        <span class="small text-muted"> · Course: <?php echo htmlspecialchars($v['course_title']); ?></span>
                    <?php endif; ?>
                    <?php if ($v['duration']): ?>
                        <span class="small text-muted"> · <?php echo (int)$v['duration']; ?> min</span>
                    <?php endif; ?>
                    <?php if ($v['video_url']): ?>
                        <div class="small">
                            <a href="<?php echo htmlspecialchars($v['video_url']); ?>" target="_blank">Watch</a>
                        </div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes_footer.php';
