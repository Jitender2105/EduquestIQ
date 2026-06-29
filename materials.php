<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_csrf.php';
require_once __DIR__ . '/includes_payments.php';

$pdo = get_pdo();
ensure_study_material_tables($pdo);
$hasDescriptionColumn = table_has_column($pdo, 'study_materials', 'description');
$hasAccessColumn = table_has_column($pdo, 'study_materials', 'access_type');
$hasAmountColumn = table_has_column($pdo, 'study_materials', 'amount_inr');
$hasGradeColumn = table_has_column($pdo, 'study_materials', 'grade');
$hasAttributeColumn = table_has_column($pdo, 'study_materials', 'attribute_id');
$hasSubAttributeColumn = table_has_column($pdo, 'study_materials', 'sub_attribute_id');
$hasChapterColumn = table_has_column($pdo, 'study_materials', 'chapter');
$hasStatusColumn = table_has_column($pdo, 'study_materials', 'status');
$hasActiveColumn = table_has_column($pdo, 'study_materials', 'is_active');

$GLOBALS['metaTitleOverride'] = 'Study Material by Class & Skill | EduquestIQ';
$GLOBALS['metaDescriptionOverride'] = 'Browse free and paid study material by class, attribute, sub-attribute, and chapter for STEM, Olympiad, and competitive exam readiness.';
$GLOBALS['canonicalUrlOverride'] = 'https://eduquestiq.com/study-material';

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_fallback.php';

$selectedGrade = trim((string)($_GET['grade'] ?? ''));
$selectedAttribute = max(0, (int)($_GET['attribute_id'] ?? 0));
$selectedSubAttribute = max(0, (int)($_GET['sub_attribute_id'] ?? 0));
$selectedAccess = trim((string)($_GET['access_type'] ?? ''));
$selectedSearch = trim((string)($_GET['q'] ?? ''));
if (!in_array($selectedAccess, ['free', 'paid'], true)) {
    $selectedAccess = '';
}

$where = [];
$params = [];
if ($hasActiveColumn) {
    $where[] = '(sm.is_active = 1)';
}
if ($hasStatusColumn) {
    $where[] = "(sm.status = 'published')";
}
if ($selectedGrade !== '' && $hasGradeColumn) {
    $where[] = 'sm.grade = ?';
    $params[] = $selectedGrade;
}
if ($selectedAttribute > 0 && $hasAttributeColumn) {
    $where[] = 'sm.attribute_id = ?';
    $params[] = $selectedAttribute;
}
if ($selectedSubAttribute > 0 && $hasSubAttributeColumn) {
    $where[] = 'sm.sub_attribute_id = ?';
    $params[] = $selectedSubAttribute;
}
if ($selectedAccess !== '' && $hasAccessColumn) {
    $where[] = 'sm.access_type = ?';
    $params[] = $selectedAccess;
}
if ($selectedSearch !== '') {
    $searchParts = ['sm.title LIKE ?'];
    $like = '%' . $selectedSearch . '%';
    $params[] = $like;
    if ($hasChapterColumn) {
        $searchParts[] = 'sm.chapter LIKE ?';
        $params[] = $like;
    }
    if ($hasDescriptionColumn) {
        $searchParts[] = 'sm.description LIKE ?';
        $params[] = $like;
    }
    $where[] = '(' . implode(' OR ', $searchParts) . ')';
}

$select = [
    'sm.id',
    'sm.title',
    'sm.file_path',
    'sm.material_type',
    'sm.uploaded_at',
    'c.title AS course_title',
];
$select[] = $hasDescriptionColumn ? 'sm.description' : 'NULL AS description';
$select[] = $hasAccessColumn ? 'sm.access_type' : "'free' AS access_type";
$select[] = $hasAmountColumn ? 'sm.amount_inr' : '0.00 AS amount_inr';
$select[] = $hasGradeColumn ? 'sm.grade' : 'NULL AS grade';
$select[] = $hasAttributeColumn ? 'sm.attribute_id' : 'NULL AS attribute_id';
$select[] = $hasSubAttributeColumn ? 'sm.sub_attribute_id' : 'NULL AS sub_attribute_id';
$select[] = $hasChapterColumn ? 'sm.chapter' : 'NULL AS chapter';
$select[] = $hasActiveColumn ? 'sm.is_active' : '1 AS is_active';
$select[] = $hasStatusColumn ? 'sm.status' : "'published' AS status";
$select[] = $hasAttributeColumn ? 'a.name AS attribute_name' : 'NULL AS attribute_name';
$select[] = $hasSubAttributeColumn ? 'sa.name AS sub_attribute_name' : 'NULL AS sub_attribute_name';

$join = 'LEFT JOIN courses c ON c.id = sm.course_id ';
if ($hasAttributeColumn) {
    $join .= 'LEFT JOIN attributes a ON a.id = sm.attribute_id ';
}
if ($hasSubAttributeColumn) {
    $join .= 'LEFT JOIN sub_attributes sa ON sa.id = sm.sub_attribute_id ';
}

$stmt = $pdo->prepare(
    'SELECT ' . implode(', ', $select) . '
     FROM study_materials sm
     ' . $join .
     ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
     ORDER BY ' . ($hasGradeColumn ? 'sm.grade ASC, ' : '') . ($hasAttributeColumn ? 'COALESCE(a.name, ""), ' : '') . ($hasSubAttributeColumn ? 'COALESCE(sa.name, ""), ' : '') . 'sm.uploaded_at DESC, sm.id DESC'
);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$grades = $hasGradeColumn ? $pdo->query("SELECT DISTINCT grade FROM study_materials WHERE grade IS NOT NULL AND grade <> '' ORDER BY grade ASC")->fetchAll(PDO::FETCH_COLUMN) : [];
$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name ASC')->fetchAll();
$subAttributes = $pdo->query('SELECT id, attribute_id, name FROM sub_attributes ORDER BY attribute_id ASC, name ASC')->fetchAll();

$studentId = ($authUser && ($authUser['role'] ?? '') === 'student') ? (int)$authUser['sub'] : 0;
$paidMaterialIds = [];
if ($studentId > 0 && study_material_purchase_table_exists($pdo)) {
    $paidRows = $pdo->prepare("SELECT study_material_id FROM study_material_purchases WHERE student_id = ? AND payment_status = 'paid'");
    $paidRows->execute([$studentId]);
    foreach ($paidRows->fetchAll(PDO::FETCH_COLUMN) as $paidId) {
        $paidMaterialIds[(int)$paidId] = true;
    }
}

$totalCount = count($materials);
$freeCount = 0;
$paidCount = 0;
foreach ($materials as $material) {
    if ((string)($material['access_type'] ?? 'free') === 'paid') {
        $paidCount++;
    } else {
        $freeCount++;
    }
}
?>

<style>
.eq-material-hero {
    border-radius: 28px;
    background: linear-gradient(135deg, #115e59 0%, #2563eb 58%, #9333ea 100%);
    color: #fff;
    padding: 30px;
    box-shadow: 0 24px 56px rgba(35, 52, 118, 0.2);
}
.eq-material-hero h1 {
    font-size: clamp(1.8rem, 4vw, 3.1rem);
    line-height: 1.05;
    margin-bottom: 12px;
}
.eq-material-hero p {
    color: rgba(255,255,255,0.84);
    max-width: 760px;
    margin-bottom: 0;
}
.eq-material-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 20px;
}
.eq-material-metric {
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 16px;
    background: rgba(255,255,255,0.12);
    padding: 12px;
}
.eq-material-metric strong {
    display: block;
    font-size: 1.35rem;
}
.eq-material-filter {
    border: 1px solid rgba(47, 59, 120, 0.08);
    border-radius: 20px;
    background: #fff;
    padding: 16px;
    box-shadow: 0 14px 30px rgba(37, 49, 104, 0.08);
}
.eq-material-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.eq-material-card {
    border: 1px solid rgba(47, 59, 120, 0.08);
    border-radius: 20px;
    background: #fff;
    padding: 18px;
    box-shadow: 0 14px 30px rgba(37, 49, 104, 0.07);
    min-height: 100%;
    display: flex;
    flex-direction: column;
}
.eq-material-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #0f766e, #4f46e5);
    margin-bottom: 12px;
}
.eq-material-preview {
    position: relative;
    aspect-ratio: 4 / 3;
    border-radius: 16px;
    overflow: hidden;
    background: linear-gradient(135deg, #e0f2fe, #eef2ff);
    border: 1px solid rgba(47, 59, 120, 0.08);
    margin-bottom: 14px;
    user-select: none;
    -webkit-user-select: none;
    pointer-events: none;
}
.eq-material-preview canvas {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
    background: #fff;
}
.eq-material-preview::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0) 45%, rgba(15, 23, 42, 0.64) 100%),
        repeating-linear-gradient(-35deg, rgba(255,255,255,0.07) 0 2px, transparent 2px 12px);
}
.eq-material-preview-label {
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: 10px;
    z-index: 2;
    color: #fff;
    font-size: 0.76rem;
    font-weight: 800;
    text-shadow: 0 1px 8px rgba(15, 23, 42, 0.5);
}
.eq-material-preview-fallback {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    color: #475569;
    font-weight: 800;
    text-align: center;
}
.eq-material-card h2 {
    font-size: 1.08rem;
    margin-bottom: 8px;
}
.eq-material-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}
.eq-material-meta span {
    border-radius: 999px;
    background: #eef2ff;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 5px 9px;
}
.eq-material-desc {
    color: #64748b;
    font-size: 0.9rem;
    flex: 1;
}
.eq-material-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 12px;
}
.eq-payment-message {
    display: none;
}
@media (max-width: 991px) {
    .eq-material-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .eq-material-hero,
    .eq-material-filter,
    .eq-material-card {
        border-radius: 16px;
    }
    .eq-material-metrics,
    .eq-material-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="eq-material-hero mb-4">
    <h1>Study Material Library</h1>
    <p>Class-wise and skill-wise worksheets, notes, guides, and practice resources for curious students and involved parents.</p>
    <div class="eq-material-metrics">
        <div class="eq-material-metric"><strong><?php echo $totalCount; ?></strong><span>Visible resources</span></div>
        <div class="eq-material-metric"><strong><?php echo $freeCount; ?></strong><span>Free materials</span></div>
        <div class="eq-material-metric"><strong><?php echo $paidCount; ?></strong><span>Premium materials</span></div>
    </div>
</section>

<?php if (isset($_GET['purchase']) && $_GET['purchase'] === 'success'): ?>
    <div class="alert alert-success">Payment successful. Your study material is unlocked.</div>
<?php elseif (isset($_GET['purchase']) && $_GET['purchase'] === 'required'): ?>
    <div class="alert alert-warning">Please complete payment to access that study material.</div>
<?php endif; ?>

<form method="get" action="<?php echo htmlspecialchars(url_for('study-material')); ?>" class="eq-material-filter mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Find the right material</h2>
            <div class="small text-muted">Filter by class, attribute, sub-attribute, free or paid access, and chapter keywords.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(url_for('study-material')); ?>">Clear filters</a>
    </div>
    <div class="row g-2">
        <div class="col-md-2">
            <select class="form-select" name="grade" aria-label="Filter by class">
                <option value="">All Classes</option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?php echo htmlspecialchars((string)$grade); ?>"<?php echo $selectedGrade === (string)$grade ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$grade); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="attribute_id" id="filter-attribute">
                <option value="">All Attributes</option>
                <?php foreach ($attributes as $attribute): ?>
                    <option value="<?php echo (int)$attribute['id']; ?>"<?php echo $selectedAttribute === (int)$attribute['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$attribute['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="sub_attribute_id" id="filter-sub-attribute">
                <option value="">All Sub-attributes</option>
                <?php foreach ($subAttributes as $subAttribute): ?>
                    <option value="<?php echo (int)$subAttribute['id']; ?>" data-attribute-id="<?php echo (int)$subAttribute['attribute_id']; ?>"<?php echo $selectedSubAttribute === (int)$subAttribute['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$subAttribute['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="access_type">
                <option value="">Free & Paid</option>
                <option value="free"<?php echo $selectedAccess === 'free' ? ' selected' : ''; ?>>Free</option>
                <option value="paid"<?php echo $selectedAccess === 'paid' ? ' selected' : ''; ?>>Paid</option>
            </select>
        </div>
        <div class="col-md-2">
            <input class="form-control" name="q" value="<?php echo htmlspecialchars($selectedSearch); ?>" placeholder="Chapter search">
        </div>
    </div>
    <div class="mt-3">
        <button class="btn btn-primary">Apply filters</button>
    </div>
</form>

<div id="material-payment-message" class="alert eq-payment-message" role="alert"></div>
<?php if ($studentId > 0): ?>
    <input type="hidden" id="material-payment-csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
<?php endif; ?>

<?php if (!$materials): ?>
    <?php
    render_static_fallback([
        'eyebrow' => 'Study Library',
        'title' => 'No study material matched your filters',
        'description' => 'Try clearing the filters or add fresh class-wise resources from the backend.',
        'points' => [
            'Materials can be class-wise and attribute-wise.',
            'Premium materials unlock after Razorpay payment.',
            'Free resources remain open for quick revision.',
        ],
        'cards' => [
            ['title' => 'Grade 2 Worksheet', 'meta' => 'Free', 'text' => 'A quick concept sheet for warm-up practice.'],
            ['title' => 'Olympiad Drill Pack', 'meta' => 'Paid', 'text' => 'A structured premium pack for focused preparation.'],
            ['title' => 'Parent Revision Guide', 'meta' => 'Class-wise', 'text' => 'Clear chapter guidance for home support.'],
        ],
        'primary_label' => 'Clear Filters',
        'primary_link' => url_for('study-material'),
        'secondary_label' => 'Open Backend',
        'secondary_link' => url_for('backend/materials.php'),
    ]);
    ?>
<?php else: ?>
    <section class="eq-material-grid">
        <?php foreach ($materials as $material): ?>
            <?php
                $materialId = (int)$material['id'];
                $isPaid = (string)($material['access_type'] ?? 'free') === 'paid' && (float)($material['amount_inr'] ?? 0) > 0;
                $hasAccess = !$isPaid || isset($paidMaterialIds[$materialId]);
                $downloadUrl = url_for('study_material_download.php?id=' . $materialId);
                $previewUrl = url_for('study_material_preview.php?id=' . $materialId);
                $description = trim(strip_tags((string)($material['description'] ?? '')));
                $typeLabel = strtoupper((string)($material['material_type'] ?? 'PDF'));
                $canPreviewPdf = strtolower(pathinfo((string)($material['file_path'] ?? ''), PATHINFO_EXTENSION)) === 'pdf';
            ?>
            <article class="eq-material-card">
                <?php if ($canPreviewPdf): ?>
                    <div class="eq-material-preview js-material-pdf-preview" data-pdf-url="<?php echo htmlspecialchars($previewUrl); ?>" aria-hidden="true">
                        <canvas></canvas>
                        <div class="eq-material-preview-label">PDF preview · sample pages</div>
                    </div>
                <?php else: ?>
                    <div class="eq-material-preview" aria-hidden="true">
                        <div class="eq-material-preview-fallback"><?php echo htmlspecialchars($typeLabel); ?> material preview</div>
                        <div class="eq-material-preview-label">Preview available after opening</div>
                    </div>
                <?php endif; ?>
                <div class="eq-material-icon"><?php echo htmlspecialchars(substr($typeLabel, 0, 3)); ?></div>
                <h2><?php echo htmlspecialchars((string)$material['title']); ?></h2>
                <div class="eq-material-meta">
                    <?php if (!empty($material['grade'])): ?><span><?php echo htmlspecialchars((string)$material['grade']); ?></span><?php endif; ?>
                    <?php if (!empty($material['attribute_name'])): ?><span><?php echo htmlspecialchars((string)$material['attribute_name']); ?></span><?php endif; ?>
                    <?php if (!empty($material['sub_attribute_name'])): ?><span><?php echo htmlspecialchars((string)$material['sub_attribute_name']); ?></span><?php endif; ?>
                    <?php if (!empty($material['chapter'])): ?><span><?php echo htmlspecialchars((string)$material['chapter']); ?></span><?php endif; ?>
                    <span><?php echo htmlspecialchars($typeLabel); ?></span>
                </div>
                <div class="eq-material-desc">
                    <?php echo $description !== '' ? htmlspecialchars(text_preview($description, 150, '...')) : 'A focused learning resource for revision, practice, and parent-guided study time.'; ?>
                </div>
                <div class="eq-material-actions">
                    <?php if ($isPaid): ?>
                        <span class="badge text-bg-warning text-dark">Premium <?php echo htmlspecialchars(test_price_label((float)$material['amount_inr'])); ?></span>
                    <?php else: ?>
                        <span class="badge text-bg-success">Free</span>
                    <?php endif; ?>

                    <?php if ($hasAccess): ?>
                        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($downloadUrl); ?>">Open Material</a>
                    <?php elseif (!$authUser): ?>
                        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('login.php')); ?>">Login to Buy</a>
                    <?php elseif (($authUser['role'] ?? '') !== 'student'): ?>
                        <span class="small text-muted">Login as a student to buy.</span>
                    <?php elseif (!payment_gateway_ready()): ?>
                        <span class="small text-muted">Payment is not configured.</span>
                    <?php else: ?>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm js-buy-material"
                            data-id="<?php echo $materialId; ?>"
                            data-title="<?php echo htmlspecialchars((string)$material['title']); ?>"
                            data-price="<?php echo (int)amount_in_paise((float)$material['amount_inr']); ?>"
                        >
                            Buy Now
                        </button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<script>
(function () {
    const attributeSelect = document.getElementById('filter-attribute');
    const subAttributeSelect = document.getElementById('filter-sub-attribute');
    if (attributeSelect && subAttributeSelect) {
        const options = Array.from(subAttributeSelect.querySelectorAll('option[data-attribute-id]'));
        function syncSubAttributes() {
            const attributeId = attributeSelect.value;
            options.forEach(function (option) {
                option.hidden = attributeId !== '' && option.getAttribute('data-attribute-id') !== attributeId;
            });
            const selected = subAttributeSelect.selectedOptions[0];
            if (selected && selected.hidden) {
                subAttributeSelect.value = '';
            }
        }
        attributeSelect.addEventListener('change', syncSubAttributes);
        syncSubAttributes();
    }
})();
</script>

<?php if ($materials): ?>
    <script type="module">
    import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';
    const previews = Array.from(document.querySelectorAll('.js-material-pdf-preview'));
    if (previews.length) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

        async function renderPreview(preview) {
            const canvas = preview.querySelector('canvas');
            const url = preview.getAttribute('data-pdf-url');
            if (!canvas || !url) {
                return;
            }

            try {
                const pdf = await pdfjsLib.getDocument({
                    url,
                    disableRange: true,
                    disableStream: true
                }).promise;
                const maxPages = Math.min(pdf.numPages, 3);
                let pageNumber = 1;

                async function drawPage() {
                    const page = await pdf.getPage(pageNumber);
                    const containerWidth = Math.max(240, preview.clientWidth);
                    const viewport = page.getViewport({ scale: 1 });
                    const scale = Math.min(1.4, containerWidth / viewport.width);
                    const scaled = page.getViewport({ scale });
                    const context = canvas.getContext('2d', { alpha: false });
                    canvas.width = Math.floor(scaled.width);
                    canvas.height = Math.floor(scaled.height);
                    await page.render({ canvasContext: context, viewport: scaled }).promise;
                    pageNumber = pageNumber >= maxPages ? 1 : pageNumber + 1;
                }

                await drawPage();
                if (maxPages > 1) {
                    window.setInterval(drawPage, 2600 + Math.floor(Math.random() * 600));
                }
            } catch (error) {
                preview.innerHTML = '<div class="eq-material-preview-fallback">PDF preview</div><div class="eq-material-preview-label">Sample preview</div>';
            }
        }

        previews.slice(0, 12).forEach(renderPreview);
    }
    </script>
<?php endif; ?>

<?php if ($studentId > 0 && payment_gateway_ready()): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (function () {
        const buttons = Array.from(document.querySelectorAll('.js-buy-material'));
        const csrfToken = document.getElementById('material-payment-csrf')?.value || '';
        const message = document.getElementById('material-payment-message');
        if (!buttons.length || !csrfToken || !message) {
            return;
        }

        function showMessage(type, text) {
            message.className = 'alert alert-' + type;
            message.style.display = 'block';
            message.textContent = text;
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(function () {
                return {};
            });
            if (!response.ok || data.success === false) {
                throw new Error(data.error || 'Payment request failed.');
            }
            return data;
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', async function () {
                const materialId = Number(button.getAttribute('data-id') || 0);
                const title = button.getAttribute('data-title') || 'Study Material';
                button.disabled = true;
                showMessage('info', 'Preparing secure payment...');

                let order;
                try {
                    order = await postJson(<?php echo json_encode(url_for('api/create-order.php')); ?>, {
                        items: [{ type: 'study_material', id: materialId }]
                    });
                } catch (error) {
                    showMessage('danger', error.message);
                    button.disabled = false;
                    return;
                }

                if (order.already_paid && order.redirect_url) {
                    window.location.href = order.redirect_url;
                    return;
                }

                const checkout = new Razorpay({
                    key: order.key_id,
                    amount: order.amount,
                    currency: order.currency,
                    name: 'EduquestIQ',
                    description: title,
                    order_id: order.order_id,
                    callback_url: <?php echo json_encode(url_for('razorpay_return.php?source=study_material')); ?>,
                    redirect: true,
                    prefill: {
                        name: <?php echo json_encode((string)($authUser['name'] ?? '')); ?>,
                        email: <?php echo json_encode((string)($authUser['email'] ?? '')); ?>
                    },
                    handler: async function (response) {
                        try {
                            const verify = await postJson(<?php echo json_encode(url_for('api/verify-payment.php')); ?>, {
                                razorpay_order_id: response.razorpay_order_id || '',
                                razorpay_payment_id: response.razorpay_payment_id || '',
                                razorpay_signature: response.razorpay_signature || ''
                            });
                            showMessage('success', 'Payment verified. Unlocking material...');
                            window.location.href = verify.redirect_url || <?php echo json_encode(url_for('study-material?purchase=success')); ?>;
                        } catch (error) {
                            showMessage('danger', error.message);
                            button.disabled = false;
                        }
                    },
                    theme: { color: '#2563eb' },
                    modal: {
                        ondismiss: function () {
                            showMessage('warning', 'Payment was cancelled. You can try again when ready.');
                            button.disabled = false;
                        }
                    }
                });

                checkout.on('payment.failed', function (response) {
                    const reason = response && response.error && response.error.description
                        ? response.error.description
                        : 'Payment failed. Please try again.';
                    showMessage('danger', reason);
                    button.disabled = false;
                });

                checkout.open();
            });
        });
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes_footer.php';
