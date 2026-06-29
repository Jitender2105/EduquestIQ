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

function frontend_video_is_generated_title(string $title): bool
{
    return (bool)preg_match('/^YouTube Lecture [A-Za-z0-9_-]{11}$/', trim($title));
}

function frontend_video_is_generated_description(string $description): bool
{
    return str_starts_with(trim($description), 'Embedded lecture from YouTube:');
}

function frontend_video_display_title(array $row, string $youtubeId): string
{
    $title = trim((string)($row['title'] ?? ''));
    if ($title !== '' && !frontend_video_is_generated_title($title)) {
        return $title;
    }

    foreach (['test_title', 'sub_attribute_name', 'attribute_name', 'course_title'] as $key) {
        $fallback = trim((string)($row[$key] ?? ''));
        if ($fallback !== '') {
            return $fallback . ' Video Lecture';
        }
    }

    return 'Video Lecture';
}

function frontend_video_display_description(array $row): string
{
    $description = trim((string)($row['description'] ?? ''));
    if ($description === '' || frontend_video_is_generated_description($description)) {
        return '';
    }

    return trim(strip_tags($description));
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
$allVideos = [];
$activePlayableCount = 0;

foreach ($rows as $row) {
    $youtubeId = frontend_video_extract_youtube_id((string)($row['video_url'] ?? ''));
    if ($youtubeId === null) {
        continue;
    }
    $activePlayableCount++;

    $video = [
        'id' => (int)$row['id'],
        'title' => frontend_video_display_title($row, $youtubeId),
        'description' => frontend_video_display_description($row),
        'duration' => (int)($row['duration'] ?? 0),
        'course_title' => (string)($row['course_title'] ?? ''),
        'test_title' => (string)($row['test_title'] ?? ''),
        'attribute_name' => (string)($row['attribute_name'] ?? ''),
        'sub_attribute_name' => (string)($row['sub_attribute_name'] ?? ''),
        'is_featured' => !empty($row['is_featured']),
        'youtube_id' => $youtubeId,
        'embed_url' => 'https://www.youtube.com/embed/' . $youtubeId,
        'thumbnail_url' => 'https://img.youtube.com/vi/' . $youtubeId . '/hqdefault.jpg',
    ];
    $allVideos[] = $video;

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

$videosPerPage = 9;
$totalPages = max(1, (int)ceil(count($allVideos) / $videosPerPage));
$currentPage = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$pageOffset = ($currentPage - 1) * $videosPerPage;
$visibleVideos = array_slice($allVideos, $pageOffset, $videosPerPage);
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.eq-video-card {
    background: #fff;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
    border: 1px solid rgba(148, 163, 184, 0.15);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    height: 100%;
    text-align: left;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.eq-video-card:hover,
.eq-video-card:focus {
    transform: translateY(-2px);
    box-shadow: 0 24px 54px rgba(15, 23, 42, 0.12);
    outline: none;
}
.eq-video-card.is-featured {
    border-color: rgba(245, 158, 11, 0.42);
    box-shadow: 0 22px 52px rgba(245, 158, 11, 0.14);
}
.eq-video-thumb {
    position: relative;
    background: #0f172a;
    aspect-ratio: 16 / 9;
}
.eq-video-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.eq-video-play {
    position: absolute;
    inset: 50% auto auto 50%;
    transform: translate(-50%, -50%);
    width: 54px;
    height: 54px;
    border-radius: 999px;
    background: rgba(255,255,255,0.94);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.22);
}
.eq-video-play::before {
    content: "";
    position: absolute;
    left: 22px;
    top: 17px;
    border-left: 16px solid #4f46e5;
    border-top: 10px solid transparent;
    border-bottom: 10px solid transparent;
}
.eq-video-body {
    padding: 1rem 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    flex: 1;
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
.eq-video-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.eq-video-modal-frame {
    aspect-ratio: 16 / 9;
    background: #0f172a;
}
.eq-video-modal-frame iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}
@media (max-width: 991px) {
    .eq-video-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .eq-video-grid {
        grid-template-columns: 1fr;
    }
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
        <section class="eq-video-section">
            <div class="eq-video-section-head">
                <h3>All Video Lectures</h3>
                <p>Showing <?php echo count($visibleVideos); ?> of <?php echo count($allVideos); ?> videos. Select any video to watch it here.</p>
            </div>
            <div class="eq-video-grid">
                <?php foreach ($visibleVideos as $video): ?>
                    <article
                        class="eq-video-card<?php echo $video['is_featured'] ? ' is-featured' : ''; ?> js-video-card"
                        role="button"
                        tabindex="0"
                        data-title="<?php echo htmlspecialchars($video['title']); ?>"
                        data-description="<?php echo htmlspecialchars($video['description']); ?>"
                        data-embed="<?php echo htmlspecialchars($video['embed_url']); ?>"
                    >
                        <div class="eq-video-thumb">
                            <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                            <span class="eq-video-play" aria-hidden="true"></span>
                        </div>
                        <div class="eq-video-body">
                            <?php if ($video['is_featured']): ?><div class="eq-video-featured-badge">Featured</div><?php endif; ?>
                            <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                            <div class="eq-video-meta">
                                <?php if ($video['test_title'] !== ''): ?><span><?php echo htmlspecialchars($video['test_title']); ?></span><?php endif; ?>
                                <?php if ($video['sub_attribute_name'] !== ''): ?><span><?php echo htmlspecialchars($video['sub_attribute_name']); ?></span><?php elseif ($video['attribute_name'] !== ''): ?><span><?php echo htmlspecialchars($video['attribute_name']); ?></span><?php endif; ?>
                                <?php if ($video['course_title'] !== ''): ?><span><?php echo htmlspecialchars($video['course_title']); ?></span><?php endif; ?>
                                <?php if ($video['duration'] > 0): ?><span><?php echo (int)$video['duration']; ?> min</span><?php endif; ?>
                            </div>
                            <?php if ($video['description'] !== ''): ?>
                                <p><?php echo htmlspecialchars(text_preview($video['description'], 170, '...')); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="eq-video-pagination" aria-label="Video lecture pages">
                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                        <a class="btn btn-sm <?php echo $page === $currentPage ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo htmlspecialchars(url_for('video_lectures.php?page=' . $page)); ?>"><?php echo $page; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<div class="modal fade" id="videoLectureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoLectureModalTitle">Video Lecture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="eq-video-modal-frame mb-3">
                    <iframe id="videoLectureModalFrame" src="" title="Video lecture player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                </div>
                <p class="mb-0 text-muted" id="videoLectureModalDescription"></p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    window.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('videoLectureModal');
        const frame = document.getElementById('videoLectureModalFrame');
        const title = document.getElementById('videoLectureModalTitle');
        const description = document.getElementById('videoLectureModalDescription');
        if (!modalEl || !frame || !title || !description || typeof bootstrap === 'undefined') {
            return;
        }

        const modal = new bootstrap.Modal(modalEl);
        function openVideo(card) {
            title.textContent = card.getAttribute('data-title') || 'Video Lecture';
            const descriptionText = card.getAttribute('data-description') || '';
            description.textContent = descriptionText;
            description.hidden = descriptionText === '';
            frame.src = (card.getAttribute('data-embed') || '') + '?autoplay=1&rel=0';
            modal.show();
        }

        document.querySelectorAll('.js-video-card').forEach(function (card) {
            card.addEventListener('click', function () {
                openVideo(card);
            });
            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openVideo(card);
                }
            });
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            frame.src = '';
        });
    });
})();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
