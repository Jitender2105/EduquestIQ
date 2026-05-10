<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_articles.php';
require_once __DIR__ . '/includes_fallback.php';

$pdo = get_pdo();
$slug = article_slugify((string)($_GET['slug'] ?? ''));

$article = null;
$faqRows = [];
$similarArticles = [];
$latestArticles = [];

if ($slug !== '' && article_table_exists($pdo, 'articles')) {
    $articleVisibility = article_active_clause($pdo, 'a');
    $stmt = $pdo->prepare(
        'SELECT a.*, s.name AS school_name, s.city AS school_city, s.state AS school_state, u.name AS creator_name
         FROM articles a
         JOIN users u ON u.id = a.created_by
         LEFT JOIN schools s ON s.id = a.school_id
         WHERE a.slug = ?
           AND ' . $articleVisibility . '
         LIMIT 1'
    );
    $stmt->execute([$slug]);
    $article = $stmt->fetch() ?: null;

    if ($article) {
        $faqStmt = $pdo->prepare(
            'SELECT question, answer
             FROM article_faqs
             WHERE article_id = ?
             ORDER BY sequence_order ASC, id ASC'
        );
        $faqStmt->execute([(int)$article['id']]);
        $faqRows = $faqStmt->fetchAll();

        $similarStmt = $pdo->prepare(
            'SELECT id, title, slug, image_path, article_type, created_at
             FROM articles
             WHERE id <> ?
               AND ' . article_active_clause($pdo, 'articles') . '
               AND (
                    article_type = ?
                    OR school_id <=> ?
               )
             ORDER BY created_at DESC
             LIMIT 3'
        );
        $similarStmt->execute([(int)$article['id'], (string)$article['article_type'], $article['school_id']]);
        $similarArticles = $similarStmt->fetchAll();

        $latestStmt = $pdo->query(
            'SELECT id, title, slug, image_path, article_type, created_at
             FROM articles
             WHERE ' . article_active_clause($pdo, 'articles') . '
             ORDER BY created_at DESC
             LIMIT 4'
        );
        $latestArticles = $latestStmt->fetchAll();
    }
}

$pageTitle = $article ? $article['title'] . ' | EduquestIQ Articles' : 'Article | EduquestIQ';
$pageDescription = $article
    ? article_excerpt((string)$article['content_html'], 180)
    : 'Read EduquestIQ learning articles, study insights, and FAQs.';

$GLOBALS['metaTitleOverride'] = $pageTitle;
$GLOBALS['metaDescriptionOverride'] = $pageDescription;

require_once __DIR__ . '/includes_header.php';

if (!$article):
?>
<div class="eq-page-head">
    <h2>Article not found</h2>
    <p class="subtitle">The article you requested is not available yet.</p>
</div>
<?php
render_static_fallback([
    'eyebrow' => 'Articles',
    'title' => 'Public articles are coming soon',
    'description' => 'Once content admins publish articles, they will appear here with FAQ, similar article, and latest article sections.',
    'points' => [
        'Clean slug URLs like /articles/my-article-title',
        'Rich article content with FAQ accordion and image cover',
        'Suggested and latest reading blocks on every article page',
    ],
    'cards' => [
        ['title' => 'Study planning tips', 'meta' => 'Reading', 'text' => 'Weekly planning ideas to keep momentum strong.'],
        ['title' => 'Test preparation guide', 'meta' => 'Exam Prep', 'text' => 'How to turn assessments into a growth routine.'],
        ['title' => 'Parent dashboard guide', 'meta' => 'Support', 'text' => 'Reading the right signals from skill and progress data.'],
    ],
    'primary_label' => 'Browse Articles',
    'primary_link' => url_for('articles.php'),
    'secondary_label' => 'Go to Dashboard',
    'secondary_link' => url_for('dashboard.php'),
]);

require_once __DIR__ . '/includes_footer.php';
return;
endif;

$schoolLabel = trim((string)($article['school_name'] ?? ''));
if (!empty($article['school_city']) || !empty($article['school_state'])) {
    $schoolLabel .= ($schoolLabel !== '' ? ' · ' : '') . trim((string)$article['school_city'] . (!empty($article['school_city']) && !empty($article['school_state']) ? ', ' : '') . (string)$article['school_state']);
}
$schoolLabel = trim($schoolLabel);

function article_detail_date(?string $value): string
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
?>

<style>
    .eq-article-page {
        max-width: 1180px;
        margin: 0 auto;
    }
    .eq-article-shell {
        display: grid;
        gap: 20px;
    }
    .eq-article-hero {
        border-radius: 28px;
        overflow: hidden;
        background: linear-gradient(135deg, #2e6cff 0%, #7d38ff 52%, #c334ff 100%);
        color: #fff;
        box-shadow: 0 24px 46px rgba(71, 58, 255, 0.24);
    }
    .eq-article-hero-media {
        min-height: 280px;
        background-size: cover;
        background-position: center;
        position: relative;
    }
    .eq-article-hero-media::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(12, 16, 44, 0.02), rgba(12, 16, 44, 0.35));
    }
    .eq-article-hero-body {
        padding: 28px;
    }
    .eq-article-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 14px;
    }
    .eq-article-content {
        background: #fff;
        border-radius: 24px;
        border: 1px solid rgba(47, 59, 120, 0.08);
        box-shadow: 0 18px 40px rgba(37, 49, 104, 0.08);
        padding: 28px;
        line-height: 1.8;
        font-size: 1rem;
    }
    .eq-article-content h2,
    .eq-article-content h3,
    .eq-article-content h4 {
        margin-top: 1.2rem;
        margin-bottom: 0.8rem;
    }
    .eq-article-sidebar-card {
        background: #fff;
        border-radius: 22px;
        border: 1px solid rgba(47, 59, 120, 0.08);
        box-shadow: 0 16px 36px rgba(37, 49, 104, 0.07);
    }
    .eq-article-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: rgba(255,255,255,0.16);
        border: 1px solid rgba(255,255,255,0.2);
        padding: 7px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .eq-article-lead {
        font-size: 1.04rem;
        color: rgba(255,255,255,0.88);
        line-height: 1.8;
    }
    .eq-article-content img {
        max-width: 100%;
        height: auto;
        border-radius: 16px;
    }
    .eq-article-content blockquote {
        margin: 1rem 0;
        padding: 1rem 1.2rem;
        border-left: 4px solid #5d47ff;
        background: #f7f8ff;
        border-radius: 0 14px 14px 0;
    }
    .eq-article-content ul,
    .eq-article-content ol {
        padding-left: 1.25rem;
    }
    .eq-article-side-list a {
        text-decoration: none;
    }
</style>

<div class="eq-page-head">
    <h2><?php echo htmlspecialchars($article['title']); ?></h2>
    <p class="subtitle">Public article view with FAQ, related reading, and latest updates.</p>
</div>

<div class="eq-article-page">
<div class="eq-article-shell">
    <section class="eq-article-hero">
        <div class="row g-0">
            <div class="col-lg-5">
                <div class="eq-article-hero-media" style="background-image:url('<?php echo htmlspecialchars(!empty($article['image_path']) ? url_for((string)$article['image_path']) : url_for('assets/img/favicon.png')); ?>');"></div>
            </div>
            <div class="col-lg-7">
                <div class="eq-article-hero-body">
                   
                    <div class="eq-article-meta">
                        <?php if ($schoolLabel !== ''): ?>
                            <span class="badge text-bg-light text-dark"><?php echo htmlspecialchars($schoolLabel); ?></span>
                        <?php endif; ?>
                        <span class="badge text-bg-light text-dark">By <?php echo htmlspecialchars((string)$article['creator_name']); ?></span>
                        <span class="badge text-bg-light text-dark"><?php echo htmlspecialchars(article_detail_date((string)$article['created_at'])); ?></span>
                    </div>
                    <h1 class="display-6 fw-bold"><?php echo htmlspecialchars($article['title']); ?></h1>
                    <p class="eq-article-lead mb-0"><?php echo htmlspecialchars(article_excerpt((string)$article['content_html'], 220)); ?></p>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <article class="eq-article-content">
                <?php echo $article['content_html']; ?>
            </article>

            <?php if ($faqRows): ?>
                <section class="mt-4">
                    <div class="eq-page-head text-start mb-3">
                        <h3>FAQ</h3>
                        <p class="subtitle">Quick answers for common article questions.</p>
                    </div>
                    <div class="accordion" id="articleFaqAccordion">
                        <?php foreach ($faqRows as $index => $faq): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq-heading-<?php echo (int)$index; ?>">
                                    <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse-<?php echo (int)$index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="faq-collapse-<?php echo (int)$index; ?>">
                                        <?php echo htmlspecialchars((string)$faq['question']); ?>
                                    </button>
                                </h2>
                                <div id="faq-collapse-<?php echo (int)$index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="faq-heading-<?php echo (int)$index; ?>" data-bs-parent="#articleFaqAccordion">
                                    <div class="accordion-body">
                                        <?php echo nl2br(htmlspecialchars((string)$faq['answer'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="eq-article-sidebar-card p-3 mb-4">
                <h5 class="mb-3">Article Info</h5>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><strong>Created by:</strong> <?php echo htmlspecialchars((string)$article['creator_name']); ?></li>
                    <li class="mb-2"><strong>Created:</strong> <?php echo htmlspecialchars(article_detail_date((string)$article['created_at'])); ?></li>
                    <?php if ($schoolLabel !== ''): ?>
                        <li class="mb-2"><strong>School:</strong> <?php echo htmlspecialchars($schoolLabel); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="eq-article-sidebar-card p-3 mb-4 eq-article-side-list">
                <h5 class="mb-3">Similar Articles</h5>
                <?php if (!$similarArticles): ?>
                    <p class="text-muted mb-0">No similar articles yet.</p>
                <?php else: ?>
                    <div class="vstack gap-3">
                        <?php foreach ($similarArticles as $item): ?>
                            <a class="text-decoration-none text-dark" href="<?php echo htmlspecialchars(url_for('articles/' . (string)$item['slug'])); ?>">
                                <div class="d-flex gap-3">
                                    <?php if (!empty($item['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars(url_for((string)$item['image_path'])); ?>" alt="" class="rounded-3 flex-shrink-0" style="width:68px;height:68px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded-3 bg-light flex-shrink-0 d-flex align-items-center justify-content-center" style="width:68px;height:68px;">
                                            <span class="text-muted small">Read</span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars((string)$item['title']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars(ucfirst((string)$item['article_type']) . ' · ' . (string)$item['created_at']); ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="eq-article-sidebar-card p-3 eq-article-side-list">
                <h5 class="mb-3">Latest Articles</h5>
                <?php if (!$latestArticles): ?>
                    <p class="text-muted mb-0">No latest articles yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($latestArticles as $item): ?>
                            <a class="list-group-item list-group-item-action px-0" href="<?php echo htmlspecialchars(url_for('articles/' . (string)$item['slug'])); ?>">
                                <div class="fw-semibold"><?php echo htmlspecialchars((string)$item['title']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars(ucfirst((string)$item['article_type'])); ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
