<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes_articles.php';

$user = backend_user();
$pdo = get_pdo();

$schools = article_table_exists($pdo, 'schools')
    ? $pdo->query('SELECT id, name, city, state FROM schools ORDER BY name ASC')->fetchAll()
    : [];
$contentUsers = $pdo->query(
    "SELECT id, name, email, role
     FROM users
     WHERE role IN ('content_admin', 'super_admin')
     ORDER BY role DESC, name ASC"
)->fetchAll();

$errors = [];
$success = null;
$form = [
    'title' => '',
    'content_html' => '',
    'school_id' => '',
    'article_type' => 'generic',
    'created_by' => (string)$user['sub'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $form['title'] = trim((string)($_POST['title'] ?? ''));
        $form['content_html'] = (string)($_POST['content_html'] ?? '');
        $form['school_id'] = trim((string)($_POST['school_id'] ?? ''));
        $form['article_type'] = (string)($_POST['article_type'] ?? 'generic');
        $form['created_by'] = (string)($_POST['created_by'] ?? $user['sub']);
        $slug = '';

        try {
            backend_require_admin($user);

            if ($form['title'] === '') {
                throw new RuntimeException('Article title is required.');
            }
            if (trim(strip_tags($form['content_html'])) === '') {
                throw new RuntimeException('Article content is required.');
            }
            if (!in_array($form['article_type'], ['generic', 'school', 'contest', 'news'], true)) {
                throw new RuntimeException('Invalid article type.');
            }

            $createdBy = (int)$form['created_by'];
            if ($createdBy <= 0) {
                throw new RuntimeException('Creator is required.');
            }
            $creatorStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('content_admin', 'super_admin') LIMIT 1");
            $creatorStmt->execute([$createdBy]);
            if (!$creatorStmt->fetchColumn()) {
                throw new RuntimeException('Selected creator must be a content admin or super admin.');
            }

            $schoolId = null;
            if ($form['school_id'] !== '') {
                $schoolId = (int)$form['school_id'];
                $schoolStmt = $pdo->prepare('SELECT id FROM schools WHERE id = ? LIMIT 1');
                $schoolStmt->execute([$schoolId]);
                if (!$schoolStmt->fetchColumn()) {
                    throw new RuntimeException('Selected school is invalid.');
                }
            }

            $imagePath = null;
            if (!empty($_FILES['image_file']) && (int)($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $imagePath = article_upload_image($_FILES['image_file']);
            }

            $slug = article_unique_slug($pdo, $form['title']);

            $stmt = $pdo->prepare(
                'INSERT INTO articles (title, slug, content_html, school_id, article_type, image_path, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $form['title'],
                $slug,
                $form['content_html'],
                $schoolId,
                $form['article_type'],
                $imagePath,
                $createdBy,
            ]);
            $articleId = (int)$pdo->lastInsertId();

            $faqQuestions = $_POST['faq_question'] ?? [];
            $faqAnswers = $_POST['faq_answer'] ?? [];
            if (is_array($faqQuestions) && is_array($faqAnswers)) {
                $faqStmt = $pdo->prepare(
                    'INSERT INTO article_faqs (article_id, question, answer, sequence_order) VALUES (?, ?, ?, ?)'
                );
                $sequence = 1;
                foreach ($faqQuestions as $index => $questionText) {
                    $questionText = trim((string)$questionText);
                    $answerText = trim((string)($faqAnswers[$index] ?? ''));
                    if ($questionText === '' && $answerText === '') {
                        continue;
                    }
                    if ($questionText === '' || $answerText === '') {
                        throw new RuntimeException('Each FAQ row needs both a question and an answer.');
                    }
                    $faqStmt->execute([$articleId, $questionText, $answerText, $sequence++]);
                }
            }

            $success = 'Article saved and public slug created: /articles/' . $slug;
            $form = [
                'title' => '',
                'content_html' => '',
                'school_id' => '',
                'article_type' => 'generic',
                'created_by' => (string)$user['sub'],
            ];
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$recentArticles = article_table_exists($pdo, 'articles')
    ? $pdo->query(
        'SELECT a.id, a.title, a.slug, a.article_type, a.created_at, a.image_path,
                s.name AS school_name, u.name AS creator_name
         FROM articles a
         JOIN users u ON u.id = a.created_by
         LEFT JOIN schools s ON s.id = a.school_id
         ORDER BY a.created_at DESC
         LIMIT 24'
    )->fetchAll()
    : [];

require_once dirname(__DIR__) . '/includes_header.php';
?>

<div class="eq-page-head">
    <h2>Articles Backend</h2>
    <p class="subtitle">Create rich learning articles with slug URLs, FAQ sections, image uploads, and content-admin ownership.</p>
</div>

<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" enctype="multipart/form-data" class="card h-100 shadow-sm">
            <div class="card-body">
                <?php echo csrf_field(); ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="card-title mb-1">Create Article</h5>
                        <div class="text-muted small">Bootstrap-form layout with Quill content and FAQ repeater.</div>
                    </div>
                    <span class="badge text-bg-primary"><?php echo backend_can_edit($user) ? 'super_admin editing' : 'read-only for content_admin'; ?></span>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Article Title</label>
                    <input type="text" class="form-control" name="title" id="article-title" value="<?php echo htmlspecialchars($form['title']); ?>" required>
                    <small class="form-text text-muted">Slug will be generated automatically from this title.</small>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Article School (optional)</label>
                            <select class="form-select" name="school_id">
                                <option value="">All schools / generic</option>
                                <?php foreach ($schools as $school): ?>
                                    <?php
                                        $schoolLabel = $school['name'];
                                        if (!empty($school['city']) || !empty($school['state'])) {
                                            $schoolLabel .= ' — ' . trim((string)$school['city'] . (!empty($school['city']) && !empty($school['state']) ? ', ' : '') . (string)$school['state']);
                                        }
                                    ?>
                                    <option value="<?php echo (int)$school['id']; ?>" <?php echo ((string)$form['school_id'] === (string)$school['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($schoolLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Article Type</label>
                            <select class="form-select" name="article_type" required>
                                <?php foreach (['generic' => 'Generic', 'school' => 'School', 'contest' => 'Contest', 'news' => 'News'] as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $form['article_type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Created By</label>
                            <select class="form-select" name="created_by" required>
                                <?php foreach ($contentUsers as $creator): ?>
                                    <option value="<?php echo (int)$creator['id']; ?>" <?php echo ((string)$form['created_by'] === (string)$creator['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($creator['name'] . ' (' . $creator['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Article Image</label>
                            <input type="file" class="form-control" name="image_file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                            <small class="form-text text-muted">Optional cover image for the public article page.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Article Content</label>
                    <textarea class="form-control eq-richtext" data-richtext name="content_html" rows="10" required><?php echo htmlspecialchars($form['content_html']); ?></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="mb-0">FAQ</h6>
                        <div class="small text-muted">Use add-more to create question and answer rows.</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="add-faq-row">Add More</button>
                </div>

                <div id="faq-rows" class="vstack gap-3">
                    <div class="border rounded-3 p-3 bg-light faq-row">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small">Question</label>
                                <input type="text" name="faq_question[]" class="form-control" placeholder="FAQ question">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Answer</label>
                                <textarea name="faq_answer[]" class="form-control" rows="2" placeholder="FAQ answer"></textarea>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-faq-row">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="small text-muted">
                        Public URL preview:
                        <code id="slug-preview"><?php echo htmlspecialchars(url_for('articles/' . article_slugify($form['title'] ?: 'your-title-here'))); ?></code>
                    </div>
                    <button class="btn btn-primary px-4" type="submit">Publish Article</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Recent Articles</h5>
                <?php if (!$recentArticles): ?>
                    <p class="text-muted mb-0">No articles created yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentArticles as $article): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex gap-3 align-items-start">
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($article['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars(url_for((string)$article['image_path'])); ?>" alt="" class="rounded-3" style="width:72px;height:72px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded-3 bg-light d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                                                <span class="text-muted small">No image</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between gap-2">
                                            <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                            <span class="badge text-bg-light border text-capitalize"><?php echo htmlspecialchars((string)$article['article_type']); ?></span>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars((string)$article['creator_name']); ?>
                                            <?php if (!empty($article['school_name'])): ?>
                                                · <?php echo htmlspecialchars((string)$article['school_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small mt-1 text-break">
                                            <code><?php echo htmlspecialchars(url_for('articles/' . (string)$article['slug'])); ?></code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const titleInput = document.getElementById('article-title');
    const slugPreview = document.getElementById('slug-preview');
    const faqRows = document.getElementById('faq-rows');
    const addBtn = document.getElementById('add-faq-row');

    function slugify(value) {
        return String(value || '')
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'article';
    }

    function updateSlugPreview() {
        slugPreview.textContent = <?php echo json_encode(url_for('articles/')); ?> + slugify(titleInput.value);
    }

    function bindRemove(button) {
        button.addEventListener('click', function () {
            const row = button.closest('.faq-row');
            if (row && faqRows.querySelectorAll('.faq-row').length > 1) {
                row.remove();
            }
        });
    }

    addBtn.addEventListener('click', function () {
        const template = document.createElement('div');
        template.className = 'border rounded-3 p-3 bg-light faq-row';
        template.innerHTML = `
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label small">Question</label>
                    <input type="text" name="faq_question[]" class="form-control" placeholder="FAQ question">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Answer</label>
                    <textarea name="faq_answer[]" class="form-control" rows="2" placeholder="FAQ answer"></textarea>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-faq-row">Remove</button>
                </div>
            </div>
        `;
        faqRows.appendChild(template);
        bindRemove(template.querySelector('.remove-faq-row'));
    });

    faqRows.querySelectorAll('.remove-faq-row').forEach(bindRemove);
    titleInput.addEventListener('input', updateSlugPreview);
    updateSlugPreview();
})();
</script>

<?php require_once dirname(__DIR__) . '/includes_footer.php'; ?>
