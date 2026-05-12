<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$pdo = get_pdo();
$hasVideoTestColumn = table_has_column($pdo, 'video_lectures', 'test_id');
$hasVideoAttributeColumn = table_has_column($pdo, 'video_lectures', 'attribute_id');
$hasVideoSubAttributeColumn = table_has_column($pdo, 'video_lectures', 'sub_attribute_id');
$hasVideoDescriptionColumn = table_has_column($pdo, 'video_lectures', 'description');
$hasVideoFeaturedColumn = table_has_column($pdo, 'video_lectures', 'is_featured');

function frontend_video_extract_youtube_id(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (preg_match('~(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $matches)) {
        return $matches[1];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    if (!empty($parts['host']) && str_contains((string)$parts['host'], 'youtube.com') && !empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        if (!empty($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$query['v'])) {
            return (string)$query['v'];
        }
    }

    return null;
}

$select = [
    'vl.id',
    'vl.title',
    'vl.video_url',
    'vl.duration',
    'vl.sequence_order',
    'c.title AS course_title',
];

if ($hasVideoDescriptionColumn) {
    $select[] = 'vl.description';
} else {
    $select[] = 'NULL AS description';
}
if ($hasVideoTestColumn) {
    $select[] = 't.title AS test_title';
} else {
    $select[] = 'NULL AS test_title';
}
if ($hasVideoAttributeColumn) {
    $select[] = 'a.name AS attribute_name';
} else {
    $select[] = 'NULL AS attribute_name';
}
if ($hasVideoSubAttributeColumn) {
    $select[] = 'sa.name AS sub_attribute_name';
} else {
    $select[] = 'NULL AS sub_attribute_name';
}
if ($hasVideoFeaturedColumn) {
    $select[] = 'vl.is_featured';
} else {
    $select[] = '0 AS is_featured';
}

$query = 'SELECT ' . implode(', ', $select) . '
    FROM video_lectures vl
    LEFT JOIN courses c ON c.id = vl.course_id ';
if ($hasVideoTestColumn) {
    $query .= 'LEFT JOIN tests t ON t.id = vl.test_id ';
}
if ($hasVideoAttributeColumn) {
    $query .= 'LEFT JOIN attributes a ON a.id = vl.attribute_id ';
}
if ($hasVideoSubAttributeColumn) {
    $query .= 'LEFT JOIN sub_attributes sa ON sa.id = vl.sub_attribute_id ';
}
$query .= table_has_column($pdo, 'video_lectures', 'is_active') ? 'WHERE vl.is_active = 1 ' : '';

$orderBy = [];
if ($hasVideoFeaturedColumn) {
    $orderBy[] = 'vl.is_featured DESC';
}
if ($hasVideoTestColumn) {
    $orderBy[] = 'COALESCE(t.title, "")';
}
if ($hasVideoAttributeColumn) {
    $orderBy[] = 'COALESCE(a.name, "")';
}
if ($hasVideoSubAttributeColumn) {
    $orderBy[] = 'COALESCE(sa.name, "")';
}
$orderBy[] = 'vl.sequence_order ASC';
$orderBy[] = 'vl.id DESC';
$query .= 'ORDER BY ' . implode(', ', $orderBy);

$rows = $pdo->query($query)->fetchAll();

$testSections = [];
$skillSections = [];
$generalVideos = [];
$activePlayableCount = 0;

foreach ($rows as $row) {
    $youtubeId = frontend_video_extract_youtube_id((string)($row['video_url'] ?? ''));
    if ($youtubeId === null) {
        continue;
    }
    $activePlayableCount++;

    $video = [
        'id' => (int)$row['id'],
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'duration' => (int)($row['duration'] ?? 0),
        'course_title' => (string)($row['course_title'] ?? ''),
        'test_title' => (string)($row['test_title'] ?? ''),
        'attribute_name' => (string)($row['attribute_name'] ?? ''),
        'sub_attribute_name' => (string)($row['sub_attribute_name'] ?? ''),
        'is_featured' => !empty($row['is_featured']),
        'youtube_id' => $youtubeId,
        'youtube_url' => 'https://www.youtube.com/watch?v=' . $youtubeId,
        'embed_url' => 'https://www.youtube.com/embed/' . $youtubeId,
        'thumbnail_url' => 'https://img.youtube.com/vi/' . $youtubeId . '/hqdefault.jpg',
    ];

    if ($video['test_title'] !== '') {
        $testSections[$video['test_title']][] = $video;
        continue;
    }

    if ($video['sub_attribute_name'] !== '' || $video['attribute_name'] !== '') {
        $skillLabel = $video['sub_attribute_name'] !== ''
            ? $video['sub_attribute_name'] . ($video['attribute_name'] !== '' ? ' · ' . $video['attribute_name'] : '')
            : $video['attribute_name'];
        $skillSections[$skillLabel][] = $video;
        continue;
    }

    $generalVideos[] = $video;
}
?>

<style>
.eq-video-shell {
    display: grid;
    gap: 2rem;
}
.eq-video-hero {
    background: linear-gradient(135deg, #101937, #4c1d95 62%, #2563eb);
    color: #fff;
    border-radius: 32px;
    padding: 2rem;
    box-shadow: 0 28px 60px rgba(33, 24, 91, 0.24);
}
.eq-video-hero p {
    color: rgba(255,255,255,0.8);
    max-width: 780px;
    margin-bottom: 0;
}
.eq-video-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-top: 1.4rem;
}
.eq-video-summary-card {
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 20px;
    padding: 1rem 1.1rem;
}
.eq-video-summary-card strong {
    display: block;
    font-size: 1.35rem;
}
.eq-video-section {
    display: grid;
    gap: 1rem;
}
.eq-video-section-head h3 {
    margin-bottom: 0.25rem;
}
.eq-video-section-head p {
    margin: 0;
    color: #64748b;
}
.eq-video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}
.eq-video-card {
    background: #fff;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
    border: 1px solid rgba(148, 163, 184, 0.15);
}
.eq-video-card.is-featured {
    border-color: rgba(245, 158, 11, 0.42);
    box-shadow: 0 22px 52px rgba(245, 158, 11, 0.14);
}
.eq-video-frame {
    position: relative;
    padding-top: 56.25%;
    background: #0f172a;
}
.eq-video-frame iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
}
.eq-video-body {
    padding: 1rem 1rem 1.1rem;
}
.eq-video-body h4 {
    font-size: 1rem;
    margin-bottom: 0.45rem;
}
.eq-video-featured-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: #92400e;
    background: #fef3c7;
    border-radius: 999px;
    padding: 0.28rem 0.62rem;
    margin-bottom: 0.6rem;
}
.eq-video-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.eq-video-meta span {
    font-size: 0.76rem;
    color: #334155;
    background: #eef2ff;
    border-radius: 999px;
    padding: 0.32rem 0.68rem;
}
.eq-video-body p {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}
</style>

<div class="eq-video-shell">
    <section class="eq-video-hero">
        <h2>Video Lectures</h2>
        <p>Watch mapped YouTube lessons inside EduquestIQ. Videos are grouped by the test they support and by the attribute or sub-attribute they develop, so students can move from assessment to concept revision without leaving the platform.</p>
        <div class="eq-video-summary">
            <div class="eq-video-summary-card"><strong><?php echo $activePlayableCount; ?></strong><span>Total playable lectures</span></div>
            <div class="eq-video-summary-card"><strong><?php echo count($testSections); ?></strong><span>Test-wise playlists</span></div>
            <div class="eq-video-summary-card"><strong><?php echo count($skillSections); ?></strong><span>Skill-focused sections</span></div>
        </div>
    </section>

    <?php if ($activePlayableCount === 0): ?>
        <?php
        render_static_fallback([
            'eyebrow' => 'Video Academy',
            'title' => 'Video lectures are not published yet',
            'description' => 'Once videos are added from the backend, this page will show playable YouTube lectures grouped by tests and skill domains.',
            'points' => [
                'Map revision videos directly to a test.',
                'Group concept videos by attribute and sub-attribute.',
                'Keep a general learning library for broader exploration.',
            ],
            'cards' => [
                ['title' => 'Test Revision', 'meta' => 'Grouped by exam', 'text' => 'Students move from the assessment page to targeted concept lectures.'],
                ['title' => 'Skill Playlists', 'meta' => 'Attribute-driven', 'text' => 'Creative, technical, and academic lectures remain organized by learning domain.'],
                ['title' => 'Playable YouTube Hub', 'meta' => 'Embedded viewing', 'text' => 'Videos play directly on the page without opening a separate destination.'],
            ],
            'primary_label' => 'Open Backend Videos',
            'primary_link' => url_for('backend/videos.php'),
            'secondary_label' => 'Go to Tests',
            'secondary_link' => url_for('tests.php'),
        ]);
        ?>
    <?php else: ?>
        <?php foreach ($testSections as $sectionTitle => $videos): ?>
            <section class="eq-video-section">
                <div class="eq-video-section-head">
                    <h3><?php echo htmlspecialchars($sectionTitle); ?></h3>
                    <p>Videos mapped directly to this test for pre-attempt prep and post-attempt revision.</p>
                </div>
                <div class="eq-video-grid">
                    <?php foreach ($videos as $video): ?>
                        <article class="eq-video-card<?php echo $video['is_featured'] ? ' is-featured' : ''; ?>">
                            <div class="eq-video-frame">
                                <iframe src="<?php echo htmlspecialchars($video['embed_url']); ?>" title="<?php echo htmlspecialchars($video['title']); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            </div>
                            <div class="eq-video-body">
                                <?php if ($video['is_featured']): ?><div class="eq-video-featured-badge">Featured</div><?php endif; ?>
                                <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                                <div class="eq-video-meta">
                                    <?php if ($video['course_title'] !== ''): ?><span><?php echo htmlspecialchars($video['course_title']); ?></span><?php endif; ?>
                                    <?php if ($video['duration'] > 0): ?><span><?php echo (int)$video['duration']; ?> min</span><?php endif; ?>
                                </div>
                                <?php if ($video['description'] !== ''): ?><p><?php echo htmlspecialchars(text_preview(strip_tags($video['description']), 160, '...')); ?></p><?php endif; ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($video['youtube_url']); ?>" target="_blank" rel="noopener">Open on YouTube</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php foreach ($skillSections as $sectionTitle => $videos): ?>
            <section class="eq-video-section">
                <div class="eq-video-section-head">
                    <h3><?php echo htmlspecialchars($sectionTitle); ?></h3>
                    <p>Concept videos organized by attribute and sub-attribute for focused skill-building.</p>
                </div>
                <div class="eq-video-grid">
                    <?php foreach ($videos as $video): ?>
                        <article class="eq-video-card<?php echo $video['is_featured'] ? ' is-featured' : ''; ?>">
                            <div class="eq-video-frame">
                                <iframe src="<?php echo htmlspecialchars($video['embed_url']); ?>" title="<?php echo htmlspecialchars($video['title']); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            </div>
                            <div class="eq-video-body">
                                <?php if ($video['is_featured']): ?><div class="eq-video-featured-badge">Featured</div><?php endif; ?>
                                <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                                <div class="eq-video-meta">
                                    <?php if ($video['course_title'] !== ''): ?><span><?php echo htmlspecialchars($video['course_title']); ?></span><?php endif; ?>
                                    <?php if ($video['duration'] > 0): ?><span><?php echo (int)$video['duration']; ?> min</span><?php endif; ?>
                                    <?php if ($video['test_title'] !== ''): ?><span><?php echo htmlspecialchars($video['test_title']); ?></span><?php endif; ?>
                                </div>
                                <?php if ($video['description'] !== ''): ?><p><?php echo htmlspecialchars(text_preview(strip_tags($video['description']), 160, '...')); ?></p><?php endif; ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($video['youtube_url']); ?>" target="_blank" rel="noopener">Open on YouTube</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($generalVideos): ?>
            <section class="eq-video-section">
                <div class="eq-video-section-head">
                    <h3>General Video Library</h3>
                    <p>Videos that are active but not mapped to a specific test or skill bucket yet.</p>
                </div>
                <div class="eq-video-grid">
                    <?php foreach ($generalVideos as $video): ?>
                        <article class="eq-video-card<?php echo $video['is_featured'] ? ' is-featured' : ''; ?>">
                            <div class="eq-video-frame">
                                <iframe src="<?php echo htmlspecialchars($video['embed_url']); ?>" title="<?php echo htmlspecialchars($video['title']); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            </div>
                            <div class="eq-video-body">
                                <?php if ($video['is_featured']): ?><div class="eq-video-featured-badge">Featured</div><?php endif; ?>
                                <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                                <div class="eq-video-meta">
                                    <?php if ($video['course_title'] !== ''): ?><span><?php echo htmlspecialchars($video['course_title']); ?></span><?php endif; ?>
                                    <?php if ($video['duration'] > 0): ?><span><?php echo (int)$video['duration']; ?> min</span><?php endif; ?>
                                </div>
                                <?php if ($video['description'] !== ''): ?><p><?php echo htmlspecialchars(text_preview(strip_tags($video['description']), 160, '...')); ?></p><?php endif; ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($video['youtube_url']); ?>" target="_blank" rel="noopener">Open on YouTube</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
