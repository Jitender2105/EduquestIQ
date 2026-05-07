<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes_files.php';
require_once dirname(__DIR__) . '/includes_payments.php';

$user = backend_user();
$pdo = get_pdo();

$errors = [];
$success = null;
$practicePaperActiveColumn = table_has_column($pdo, 'practice_papers', 'is_active');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        try {
            backend_require_admin($user);

            $testId = (int)($_POST['test_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $className = trim((string)($_POST['class_name'] ?? ''));
            $paperYear = trim((string)($_POST['paper_year'] ?? ''));
            $accessType = (string)($_POST['access_type'] ?? 'free');
            $amountInr = $accessType === 'paid' ? max(0.0, (float)($_POST['amount_inr'] ?? 0)) : 0.0;
            $isActive = (int)(($_POST['is_active'] ?? '1') === '1');

            if ($testId <= 0) {
                $errors[] = 'Select a test.';
            }
            if ($name === '') {
                $errors[] = 'Practice paper name is required.';
            }
            if ($className === '') {
                $errors[] = 'Class is required.';
            }
            if ($paperYear === '') {
                $errors[] = 'Year is required.';
            }
            if (!in_array($accessType, ['free', 'paid'], true)) {
                $errors[] = 'Select free or paid access.';
            }
            if ($accessType === 'paid' && $amountInr < 1) {
                $errors[] = 'Paid practice paper amount must be at least ₹1.';
            }
            if ($errors === []) {
                $validated = validate_pdf_upload($_FILES['pdf_file'] ?? []);
                $filePath = store_practice_paper_upload($validated);

                if ($practicePaperActiveColumn) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO practice_papers
                         (test_id, name, description, class_name, paper_year, access_type, amount_inr, pdf_file_path, is_active, created_by, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                    );
                    $stmt->execute([
                        $testId,
                        $name,
                        $description !== '' ? $description : null,
                        $className,
                        $paperYear,
                        $accessType,
                        $amountInr,
                        $filePath,
                        $isActive,
                        (int)$user['sub'],
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO practice_papers
                         (test_id, name, description, class_name, paper_year, access_type, amount_inr, pdf_file_path, status, created_by, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                    );
                    $stmt->execute([
                        $testId,
                        $name,
                        $description !== '' ? $description : null,
                        $className,
                        $paperYear,
                        $accessType,
                        $amountInr,
                        $filePath,
                        $isActive ? 'published' : 'archived',
                        (int)$user['sub'],
                    ]);
                }
                $success = 'Practice paper created.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$tests = $pdo->query('SELECT id, title FROM tests ORDER BY created_at DESC, id DESC LIMIT 300')->fetchAll();
$papers = [];
if (practice_paper_table_exists($pdo)) {
    $paperStatusSelect = $practicePaperActiveColumn ? 'pp.is_active' : 'CASE WHEN pp.status = "published" THEN 1 ELSE 0 END AS is_active';
    $papers = $pdo->query(
        'SELECT pp.*, ' . $paperStatusSelect . ', t.title AS test_title, u.name AS creator_name
         FROM practice_papers pp
         JOIN tests t ON t.id = pp.test_id
         LEFT JOIN users u ON u.id = pp.created_by
         ORDER BY pp.id DESC
         LIMIT 200'
    )->fetchAll();
}

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head">
    <h2>Practice Papers Backend</h2>
    <p class="subtitle">Create downloadable practice papers mapped to tests, classes, and years.</p>
</div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" enctype="multipart/form-data" class="card p-3">
            <?php echo csrf_field(); ?>
            <h5 class="mb-3">Add Practice Paper</h5>
            <div class="mb-3">
                <label class="form-label">Test name</label>
                <select class="form-select" name="test_id" required>
                    <option value="">Select test</option>
                    <?php foreach ($tests as $test): ?>
                        <option value="<?php echo (int)$test['id']; ?>"><?php echo htmlspecialchars($test['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Name of practice paper</label>
                <input class="form-control" name="name" maxlength="180" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control eq-richtext" data-richtext name="description" rows="4"></textarea>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Class</label>
                    <input class="form-control" name="class_name" placeholder="Class 8" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year</label>
                    <input class="form-control" name="paper_year" placeholder="2026" required>
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Free / Paid</label>
                    <select class="form-select" name="access_type" id="paper-access-type">
                        <option value="free">Free</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount</label>
                    <input class="form-control" type="number" name="amount_inr" id="paper-amount" min="0" step="0.01" value="0.00">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">PDF upload file</label>
                <input class="form-control" type="file" name="pdf_file" accept="application/pdf,.pdf" required>
                <div class="form-text">PDF only. Maximum 10 MB.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Visibility</label>
                <select class="form-select" name="is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <button class="btn btn-primary">Create Practice Paper</button>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card p-3 mb-3">
            <h5 class="mb-3">Sample Practice Papers</h5>
            <div class="row g-2 small">
                <div class="col-md-6"><div class="border rounded p-2 h-100"><strong>Olympiad Warm-up Set</strong><br>Class 6 · 2026 · Free PDF for concept revision.</div></div>
                <div class="col-md-6"><div class="border rounded p-2 h-100"><strong>Reasoning Booster Pack</strong><br>Class 8 · 2025 · Paid diagnostic worksheet bundle.</div></div>
                <div class="col-md-6"><div class="border rounded p-2 h-100"><strong>STEM Mock Revision Pack</strong><br>Class 9 · 2026 · Final practice before test attempt.</div></div>
                <div class="col-md-6"><div class="border rounded p-2 h-100"><strong>Language Accuracy Drill</strong><br>Class 7 · 2024 · Grammar and reading PDF set.</div></div>
            </div>
        </div>
        <div class="card p-3">
            <h5 class="mb-3">Recent Practice Papers</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Paper</th><th>Test</th><th>Class</th><th>Year</th><th>Price</th><th>Status</th><th>PDF</th></tr></thead>
                    <tbody>
                    <?php foreach ($papers as $paper): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($paper['name']); ?></td>
                            <td><?php echo htmlspecialchars($paper['test_title']); ?></td>
                            <td><?php echo htmlspecialchars($paper['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($paper['paper_year']); ?></td>
                            <td><?php echo $paper['access_type'] === 'paid' ? htmlspecialchars(test_price_label((float)$paper['amount_inr'])) : 'Free'; ?></td>
                            <td><span class="badge <?php echo !empty($paper['is_active']) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo !empty($paper['is_active']) ? 'Active' : 'Inactive'; ?></span></td>
                            <td><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for((string)$paper['pdf_file_path'])); ?>" target="_blank">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$papers): ?>
                        <tr><td colspan="7" class="text-muted">No practice papers created yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const access = document.getElementById('paper-access-type');
    const amount = document.getElementById('paper-amount');
    if (!access || !amount) return;
    function syncAmount() {
        const paid = access.value === 'paid';
        amount.disabled = !paid;
        if (!paid) amount.value = '0.00';
    }
    access.addEventListener('change', syncAmount);
    syncAmount();
})();
</script>
<?php require_once dirname(__DIR__) . '/includes_footer.php'; ?>
