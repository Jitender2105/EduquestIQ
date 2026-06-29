<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes_payments.php';

$user = backend_user();
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
$schemaReady = $hasDescriptionColumn && $hasAccessColumn && $hasAmountColumn && $hasGradeColumn
    && $hasAttributeColumn && $hasSubAttributeColumn && $hasChapterColumn && $hasStatusColumn && $hasActiveColumn;

function backend_material_default_form(): array
{
    return [
        'edit_id' => '',
        'title' => '',
        'description' => '',
        'file_path' => '',
        'material_type' => 'pdf',
        'access_type' => 'free',
        'amount_inr' => '0.00',
        'grade' => '',
        'attribute_id' => '',
        'sub_attribute_id' => '',
        'chapter' => '',
        'course_id' => '',
        'is_active' => '1',
        'status' => 'published',
    ];
}

function backend_material_form_from_source(array $source): array
{
    $form = backend_material_default_form();
    foreach ($form as $key => $value) {
        if (array_key_exists($key, $source)) {
            $form[$key] = trim((string)$source[$key]);
        }
    }
    $form['is_active'] = (string)($source['is_active'] ?? '1') === '0' ? '0' : '1';
    $form['access_type'] = $form['access_type'] === 'paid' ? 'paid' : 'free';
    $form['status'] = in_array($form['status'], ['draft', 'published', 'archived'], true) ? $form['status'] : 'published';
    $form['material_type'] = in_array($form['material_type'], ['pdf', 'doc', 'ppt', 'link'], true) ? $form['material_type'] : 'pdf';
    return $form;
}

function backend_material_safe_filename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
    $name = trim((string)$name, '.-');
    return $name !== '' ? $name : 'study-material';
}

function backend_material_type_from_extension(string $extension): string
{
    return match (strtolower($extension)) {
        'doc', 'docx' => 'doc',
        'ppt', 'pptx' => 'ppt',
        default => 'pdf',
    };
}

function backend_material_store_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['', ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Upload only PDF, DOC, DOCX, PPT, or PPTX files.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 30 * 1024 * 1024) {
        throw new RuntimeException('File size must be between 1 byte and 30 MB.');
    }

    $relativeDir = 'uploads/study_materials/' . date('Y/m');
    $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('Could not create upload folder.');
    }

    $baseName = backend_material_safe_filename(pathinfo($originalName, PATHINFO_FILENAME));
    $filename = $baseName . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
    $absolutePath = $absoluteDir . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    return [$relativeDir . '/' . $filename, backend_material_type_from_extension($extension)];
}

$errors = [];
$success = null;
$form = backend_material_default_form();

if (isset($_GET['edit'])) {
    $editId = max(0, (int)$_GET['edit']);
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM study_materials WHERE id = ? LIMIT 1');
        $stmt->execute([$editId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $form = backend_material_form_from_source([
                'edit_id' => (string)$existing['id'],
                'title' => (string)($existing['title'] ?? ''),
                'description' => (string)($existing['description'] ?? ''),
                'file_path' => (string)($existing['file_path'] ?? ''),
                'material_type' => (string)($existing['material_type'] ?? 'pdf'),
                'access_type' => (string)($existing['access_type'] ?? 'free'),
                'amount_inr' => (string)($existing['amount_inr'] ?? '0.00'),
                'grade' => (string)($existing['grade'] ?? ''),
                'attribute_id' => (string)($existing['attribute_id'] ?? ''),
                'sub_attribute_id' => (string)($existing['sub_attribute_id'] ?? ''),
                'chapter' => (string)($existing['chapter'] ?? ''),
                'course_id' => (string)($existing['course_id'] ?? ''),
                'is_active' => !isset($existing['is_active']) || (int)$existing['is_active'] === 1 ? '1' : '0',
                'status' => (string)($existing['status'] ?? 'published'),
            ]);
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (!$schemaReady) {
        $errors[] = 'Study material database fields are still being prepared. Please reload once before saving.';
    } else {
        try {
            backend_require_admin($user);
            $form = backend_material_form_from_source($_POST);
            $editId = max(0, (int)$form['edit_id']);
            $title = trim($form['title']);
            $filePath = trim($form['file_path']);
            $uploadedType = '';
            $price = max(0.0, (float)$form['amount_inr']);
            $accessType = $form['access_type'];
            $courseId = max(0, (int)$form['course_id']);
            try {
                [$uploadedPath, $uploadedType] = backend_material_store_upload($_FILES['material_file'] ?? []);
                if ($uploadedPath !== '') {
                    $filePath = $uploadedPath;
                }
            } catch (Throwable $uploadError) {
                $errors[] = $uploadError->getMessage();
            }

            if ($title === '') {
                $errors[] = 'Enter the study material name.';
            }
            if ($filePath === '') {
                $errors[] = 'Upload the study material file.';
            }
            if ($accessType === 'paid' && $price < 1) {
                $errors[] = 'Paid study material must have a price of at least ₹1.00.';
            }
            if ($accessType === 'free') {
                $price = 0.0;
            }

            if ($courseId <= 0) {
                $fallbackCourse = $pdo->query('SELECT id FROM courses ORDER BY id ASC LIMIT 1')->fetchColumn();
                $courseId = $fallbackCourse !== false ? (int)$fallbackCourse : 0;
            }

            if ($errors === []) {
                $values = [
                    $courseId > 0 ? $courseId : null,
                    $title,
                    trim($form['description']) ?: null,
                    $filePath,
                    $uploadedType !== '' ? $uploadedType : $form['material_type'],
                    $accessType,
                    $price,
                    trim($form['grade']) ?: null,
                    (int)$form['attribute_id'] > 0 ? (int)$form['attribute_id'] : null,
                    (int)$form['sub_attribute_id'] > 0 ? (int)$form['sub_attribute_id'] : null,
                    trim($form['chapter']) ?: null,
                    (int)$form['is_active'],
                    $form['status'],
                ];

                if ($editId > 0) {
                    $values[] = $editId;
                    $stmt = $pdo->prepare(
                        'UPDATE study_materials
                         SET course_id = ?, title = ?, description = ?, file_path = ?, material_type = ?, access_type = ?, amount_inr = ?,
                             grade = ?, attribute_id = ?, sub_attribute_id = ?, chapter = ?, is_active = ?, status = ?
                         WHERE id = ?'
                    );
                    $stmt->execute($values);
                    $success = 'Study material updated.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO study_materials
                         (course_id, title, description, file_path, material_type, access_type, amount_inr, grade, attribute_id, sub_attribute_id, chapter, is_active, status, uploaded_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute($values);
                    $success = 'Study material saved.';
                }

                $form = backend_material_default_form();
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title ASC LIMIT 300')->fetchAll();
$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name ASC')->fetchAll();
$subAttributes = $pdo->query('SELECT id, attribute_id, name FROM sub_attributes ORDER BY attribute_id ASC, name ASC')->fetchAll();
$rowSelect = [
    'sm.id',
    'sm.title',
    'sm.material_type',
    $hasAccessColumn ? 'sm.access_type' : "'free' AS access_type",
    $hasAmountColumn ? 'sm.amount_inr' : '0.00 AS amount_inr',
    $hasGradeColumn ? 'sm.grade' : 'NULL AS grade',
    $hasChapterColumn ? 'sm.chapter' : 'NULL AS chapter',
    $hasActiveColumn ? 'sm.is_active' : '1 AS is_active',
    $hasStatusColumn ? 'sm.status' : "'published' AS status",
    $hasAttributeColumn ? 'a.name AS attribute_name' : 'NULL AS attribute_name',
    $hasSubAttributeColumn ? 'sa.name AS sub_attribute_name' : 'NULL AS sub_attribute_name',
];
$rowJoin = '';
if ($hasAttributeColumn) {
    $rowJoin .= ' LEFT JOIN attributes a ON a.id = sm.attribute_id';
}
if ($hasSubAttributeColumn) {
    $rowJoin .= ' LEFT JOIN sub_attributes sa ON sa.id = sm.sub_attribute_id';
}
$rows = $pdo->query(
    'SELECT ' . implode(', ', $rowSelect) . '
     FROM study_materials sm' . $rowJoin . '
     ORDER BY sm.id DESC
     LIMIT 150'
)->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head">
    <h2>Study Materials Backend</h2>
    <p class="subtitle">Create class-wise and attribute-wise material, set free or paid access, and publish it to the student library.</p>
</div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if (!$schemaReady): ?>
    <div class="alert alert-warning">Study material fields are being prepared on the live database. The page is safe to view; saving will enable after the required columns are available.</div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" class="card p-3 shadow-sm" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($form['edit_id']); ?>">
            <h5 class="mb-3"><?php echo $form['edit_id'] !== '' ? 'Edit Study Material' : 'Add Study Material'; ?></h5>

            <label class="form-label">Name of Study Material</label>
            <input class="form-control mb-3" name="title" value="<?php echo htmlspecialchars($form['title']); ?>" required>

            <label class="form-label">Description</label>
            <textarea class="form-control mb-3 eq-richtext" data-richtext name="description" rows="4"><?php echo htmlspecialchars($form['description']); ?></textarea>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Free / Paid</label>
                    <select class="form-select mb-3" name="access_type" id="material-access-type">
                        <option value="free"<?php echo $form['access_type'] === 'free' ? ' selected' : ''; ?>>Free</option>
                        <option value="paid"<?php echo $form['access_type'] === 'paid' ? ' selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price (INR)</label>
                    <input class="form-control mb-3" type="number" step="0.01" min="0" name="amount_inr" id="material-price" value="<?php echo htmlspecialchars($form['amount_inr']); ?>">
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Grade / Class</label>
                    <input class="form-control mb-3" name="grade" placeholder="Grade 2" value="<?php echo htmlspecialchars($form['grade']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Chapter</label>
                    <input class="form-control mb-3" name="chapter" placeholder="Fractions" value="<?php echo htmlspecialchars($form['chapter']); ?>">
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Attribute</label>
                    <select class="form-select mb-3" name="attribute_id" id="material-attribute-select">
                        <option value="">Select attribute</option>
                        <?php foreach ($attributes as $attribute): ?>
                            <option value="<?php echo (int)$attribute['id']; ?>"<?php echo $form['attribute_id'] === (string)$attribute['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$attribute['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-attribute</label>
                    <select class="form-select mb-3" name="sub_attribute_id" id="material-sub-attribute-select">
                        <option value="">Select sub-attribute</option>
                        <?php foreach ($subAttributes as $subAttribute): ?>
                            <option value="<?php echo (int)$subAttribute['id']; ?>" data-attribute-id="<?php echo (int)$subAttribute['attribute_id']; ?>"<?php echo $form['sub_attribute_id'] === (string)$subAttribute['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$subAttribute['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label class="form-label">Optional Course Mapping</label>
            <select class="form-select mb-3" name="course_id">
                <option value="">No course mapping</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo (int)$course['id']; ?>"<?php echo $form['course_id'] === (string)$course['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$course['title']); ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label">Upload Study Material File</label>
            <input class="form-control mb-2" type="file" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx" <?php echo $form['edit_id'] === '' ? 'required' : ''; ?>>
            <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($form['file_path']); ?>">
            <?php if ($form['file_path'] !== ''): ?>
                <div class="small text-muted mb-3">Current file: <?php echo htmlspecialchars($form['file_path']); ?></div>
            <?php else: ?>
                <div class="small text-muted mb-3">Files are stored on the server under uploads/study_materials.</div>
            <?php endif; ?>

            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select class="form-select mb-3" name="material_type">
                        <?php foreach (['pdf', 'doc', 'ppt', 'link'] as $type): ?>
                            <option value="<?php echo $type; ?>"<?php echo $form['material_type'] === $type ? ' selected' : ''; ?>><?php echo strtoupper($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select mb-3" name="status">
                        <?php foreach (['published', 'draft', 'archived'] as $status): ?>
                            <option value="<?php echo $status; ?>"<?php echo $form['status'] === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Visibility</label>
                    <select class="form-select mb-3" name="is_active">
                        <option value="1"<?php echo $form['is_active'] === '1' ? ' selected' : ''; ?>>Active</option>
                        <option value="0"<?php echo $form['is_active'] === '0' ? ' selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" <?php echo !$schemaReady ? 'disabled' : ''; ?>><?php echo $form['edit_id'] !== '' ? 'Update Material' : 'Save Material'; ?></button>
                <?php if ($form['edit_id'] !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url_for('backend/materials.php')); ?>">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card p-3 shadow-sm">
            <h5 class="mb-3">Recent Study Materials</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Material</th><th>Class & Skill</th><th>Access</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string)$row['title']); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars(strtoupper((string)$row['material_type'])); ?> · <?php echo htmlspecialchars((string)$row['status']); ?> · <?php echo !empty($row['is_active']) ? 'Active' : 'Inactive'; ?></div>
                                </td>
                                <td class="small text-muted">
                                    <?php echo htmlspecialchars(trim(implode(' · ', array_filter([(string)($row['grade'] ?? ''), (string)($row['attribute_name'] ?? ''), (string)($row['sub_attribute_name'] ?? ''), (string)($row['chapter'] ?? '')]))) ?: 'Unmapped'); ?>
                                </td>
                                <td>
                                    <?php if ((string)$row['access_type'] === 'paid'): ?>
                                        <span class="badge text-bg-warning text-dark">Paid ₹<?php echo htmlspecialchars(number_format((float)$row['amount_inr'], 2)); ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">Free</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('backend/materials.php?edit=' . (int)$row['id'])); ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="4" class="text-muted">No study materials added yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const accessType = document.getElementById('material-access-type');
    const price = document.getElementById('material-price');
    function syncPrice() {
        if (!accessType || !price) {
            return;
        }
        const isPaid = accessType.value === 'paid';
        price.disabled = !isPaid;
        if (!isPaid) {
            price.value = '0.00';
        }
    }
    if (accessType) {
        accessType.addEventListener('change', syncPrice);
        syncPrice();
    }

    const attributeSelect = document.getElementById('material-attribute-select');
    const subAttributeSelect = document.getElementById('material-sub-attribute-select');
    if (!attributeSelect || !subAttributeSelect) {
        return;
    }
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
})();
</script>

<?php require_once dirname(__DIR__) . '/includes_footer.php';
