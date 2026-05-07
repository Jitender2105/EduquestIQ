<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';
require_once __DIR__ . '/includes_articles.php';

$pdo = get_pdo();
$articleVisibility = article_active_clause($pdo, 'a');
$articles = article_table_exists($pdo, 'articles')
    ? $pdo->query(
        'SELECT a.id, a.title, a.slug, a.content_html, a.article_type, a.image_path, a.created_at,
                s.name AS school_name, u.name AS creator_name
         FROM articles a
         JOIN users u ON u.id = a.created_by
         LEFT JOIN schools s ON s.id = a.school_id
         WHERE ' . $articleVisibility . '
         ORDER BY a.created_at DESC'
    )->fetchAll()
    : [];

$legacyArticles = [];
if (article_table_exists($pdo, 'study_materials')) {
    $stmt = $pdo->query(
        "SELECT sm.id, sm.title, sm.file_path AS url, c.title AS course_title
         FROM study_materials sm
         LEFT JOIN courses c ON sm.course_id = c.id
         WHERE sm.material_type = 'link'
         ORDER BY sm.uploaded_at DESC"
    );
    $legacyArticles = $stmt->fetchAll();
}

$featured = $articles[0] ?? null;
$latest = array_slice($articles, 0, 4);
$similar = $featured
    ? array_values(array_filter($articles, static function (array $row) use ($featured): bool {
        return (int)$row['id'] !== (int)$featured['id']
            && ((string)$row['article_type'] === (string)$featured['article_type']
            || (string)($row['school_name'] ?? '') === (string)($featured['school_name'] ?? ''));
    }))
    : [];

function article_format_date(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format('d M Y');
    } catch (Throwable $e) {
        return $value;
    }
}

if (!$articles && !$legacyArticles):
?>
<div class="eq-page-head">
    <h2>Articles</h2>
    <p class="subtitle">Learning insights, study tips, and curated resources for students and educators.</p>
</div>
<?php
render_static_fallback([
    'eyebrow' => 'Knowledge Hub',
    'title' => 'Articles will appear here soon',
    'description' => 'This section will show curated learning articles, FAQ support, similar-reading blocks, and latest updates.',
    'points' => [
        'Clean article pages with slug URLs',
        'Similar article and latest article recommendations',
        'School-specific and contest/news categories',
    ],
    'cards' => [
        ['title' => 'How to build a study rhythm', 'meta' => 'Learning Habit', 'text' => 'A practical weekly system for consistency.'],
        ['title' => 'How to interpret SIRA scores', 'meta' => 'Dashboard Guide', 'text' => 'Read attribute and sub-attribute feedback with confidence.'],
        ['title' => 'Parent support checklist', 'meta' => 'Parent Guide', 'text' => 'Ways to help without over-directing a learner.'],
    ],
    'primary_label' => 'Open Dashboard',
    'primary_link' => url_for('dashboard.php'),
    'secondary_label' => 'Open Backend',
    'secondary_link' => url_for('backend/articles.php'),
]);
require_once __DIR__ . '/includes_footer.php';
return;
endif;
?>

<style>
    .eq-article-page {
        max-width: 1180px;
        margin: 0 auto;
    }
    .eq-article-list-shell {
        display: grid;
        gap: 22px;
    }
    .eq-article-list-hero {
        border-radius: 30px;
        background: linear-gradient(135deg, #2f65ff 0%, #7b37ff 52%, #be35ff 100%);
        color: #fff;
        padding: 28px;
        box-shadow: 0 24px 46px rgba(71, 58, 255, 0.22);
        overflow: hidden;
        position: relative;
    }
    .eq-article-list-hero::after {
        content: "";
        position: absolute;
        inset: auto -80px -90px auto;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.16);
        filter: blur(12px);
    }
    .eq-article-card {
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 16px 36px rgba(37, 49, 104, 0.08);
        height: 100%;
        background: #fff;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .eq-article-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(37, 49, 104, 0.12);
    }
    .eq-article-card img {
        width: 100%;
        height: 214px;
        object-fit: cover;
    }
    .eq-article-card .card-body {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 18px;
    }
    .eq-article-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: rgba(67, 116, 255, 0.08);
        color: #3b53c0;
        font-size: 0.76rem;
        font-weight: 700;
        padding: 6px 10px;
    }
    .eq-article-meta-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .eq-article-card h5 {
        font-size: 1.08rem;
        line-height: 1.35;
    }
    .eq-article-card p {
        line-height: 1.65;
        font-size: 0.92rem;
    }
    .eq-article-strip {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 18px;
        align-items: stretch;
    }
    .eq-article-side-panel {
        background: #fff;
        color: #18214a;
        border-radius: 24px;
        padding: 18px;
        box-shadow: 0 16px 36px rgba(37, 49, 104, 0.09);
    }
    .eq-article-side-panel h4 {
        margin-bottom: 8px;
    }
    .eq-article-side-panel .small {
        color: #6f789b;
    }
    .eq-article-quick-list {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }
    .eq-article-quick-item {
        padding: 12px 14px;
        border-radius: 16px;
        background: #f6f8ff;
        border: 1px solid rgba(47, 59, 120, 0.08);
    }
    .eq-article-quick-item strong {
        display: block;
        margin-bottom: 4px;
    }
    .eq-article-grid-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: end;
        flex-wrap: wrap;
    }
    @media (max-width: 991px) {
        .eq-article-strip {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="eq-page-head">
    <h2>Articles</h2>
    <p class="subtitle">Public learning articles with Vedantu-style reading blocks, FAQs, and related content.</p>
</div>

<div class="eq-article-page">
<div class="eq-article-list-shell">
    <section class="eq-article-list-hero">
        <div class="eq-article-strip">
            <div>
                <div class="eq-article-chip mb-3">Knowledge Hub</div>
                <h1 class="display-6 fw-bold">Learn with articles built for study rhythm, exam prep, and parent support.</h1>
                <p class="lead mb-0">Each article can open as a clean slug-based page with rich content, FAQ support, similar article suggestions, and latest updates.</p>
            </div>
            <div>
                <?php if ($featured): ?>
                    <div class="eq-article-side-panel">
                        <div class="small text-uppercase text-primary fw-semibold mb-2">Featured article</div>
                        <div class="d-flex gap-3 align-items-start">
                            <?php if (!empty($featured['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars(url_for((string)$featured['image_path'])); ?>" alt="" class="rounded-3 flex-shrink-0" style="width:90px;height:90px;object-fit:cover;">
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars((string)$featured['title']); ?></div>
                                <div class="small text-muted mb-2"><?php echo htmlspecialchars(article_excerpt((string)$featured['content_html'], 120)); ?></div>
                                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('articles/' . (string)$featured['slug'])); ?>">Read article</a>
                            </div>
                        </div>
                        <div class="eq-article-quick-list">
                            <div class="eq-article-quick-item">
                                <strong>Latest updates</strong>
                                <span class="small">Fresh student, parent, and exam-prep reading appears here automatically.</span>
                            </div>
                            <div class="eq-article-quick-item">
                                <strong>Related reading</strong>
                                <span class="small">Every article page surfaces matching posts by type and school context.</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($articles): ?>
        <section>
            <div class="eq-article-grid-header mb-3">
                <div class="eq-page-head text-start mb-0 flex-grow-1">
                    <h3>Latest Articles</h3>
                    <p class="subtitle">Fresh reading, curated for students, parents, and teachers.</p>
                </div>
                <div class="small text-muted">Showing <?php echo count($articles); ?> published article<?php echo count($articles) === 1 ? '' : 's'; ?></div>
            </div>
            <div class="row g-3">
                <?php foreach (array_slice($articles, 0, 6) as $article): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="eq-article-card h-100">
                            <?php if (!empty($article['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars(url_for((string)$article['image_path'])); ?>" alt="">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height:190px;">
                                    <span class="text-muted">EduquestIQ</span>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="eq-article-meta-row">
                                    <span class="eq-article-chip text-capitalize"><?php echo htmlspecialchars((string)$article['article_type']); ?></span>
                                    <span class="small text-muted"><?php echo htmlspecialchars(article_format_date((string)$article['created_at'])); ?></span>
                                </div>
                                <h5 class="mb-0"><?php echo htmlspecialchars((string)$article['title']); ?></h5>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars(article_excerpt((string)$article['content_html'], 150)); ?></p>
                                <div class="small text-muted">
                                    By <?php echo htmlspecialchars((string)$article['creator_name']); ?>
                                    <?php if (!empty($article['school_name'])): ?>
                                        · <?php echo htmlspecialchars((string)$article['school_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <a href="<?php echo htmlspecialchars(url_for('articles/' . (string)$article['slug'])); ?>" class="btn btn-outline-primary btn-sm">Read more</a>
                                  
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($similar): ?>
        <section>
            <div class="eq-page-head text-start mb-3">
                <h3>Similar Articles</h3>
                <p class="subtitle">Articles related by type or school context.</p>
            </div>
            <div class="row g-3">
                <?php foreach (array_slice($similar, 0, 3) as $article): ?>
                    <div class="col-md-4">
                        <a class="text-decoration-none text-dark" href="<?php echo htmlspecialchars(url_for('articles/' . (string)$article['slug'])); ?>">
                            <div class="card h-100 eq-article-card">
                                <?php if (!empty($article['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars(url_for((string)$article['image_path'])); ?>" alt="">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:190px;">
                                        <span class="text-muted">Related</span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <h6 class="mb-1"><?php echo htmlspecialchars((string)$article['title']); ?></h6>
                                    <div class="small text-muted"><?php echo htmlspecialchars(article_excerpt((string)$article['content_html'], 120)); ?></div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($legacyArticles): ?>
        <section>
            <div class="eq-page-head text-start mb-3">
                <h3>Legacy Article Links</h3>
                <p class="subtitle">Older knowledge links managed as study materials.</p>
            </div>
            <div class="list-group">
                <?php foreach ($legacyArticles as $a): ?>
                    <a href="<?php echo htmlspecialchars((string)$a['url']); ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$a['title']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars((string)$a['course_title']); ?></div>
                        </div>
                        <span class="badge text-bg-light border">Open</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
</div>

<?php
require_once __DIR__ . '/includes_footer.php';
