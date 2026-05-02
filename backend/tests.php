<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = backend_user();
$pdo = get_pdo();

function backend_tests_local_to_utc(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $tzIn = new DateTimeZone('Asia/Kolkata');
    $tzOut = new DateTimeZone('UTC');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $tzIn);
    if (!$dt) {
        $dt = new DateTimeImmutable($value, $tzIn);
    }

    return $dt->setTimezone($tzOut)->format('Y-m-d H:i:s');
}

function backend_tests_utc_to_local_input(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d\TH:i');
}

function backend_tests_utc_to_local_label(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Not set';
    }

    $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A');
}

function backend_tests_default_bundle(): array
{
    return [
        'title' => '',
        'description' => '',
        'instruction' => '',
        'duration_minutes' => '60',
        'price_inr' => '0.00',
        'start_at' => '',
        'end_at' => '',
        'test_year' => (string)date('Y'),
        'questions' => [
            [
                'title' => '',
                'question_type' => 'mcq',
                'marks' => '1',
                'attribute_id' => '',
                'sub_attribute_id' => '',
                'weight' => '1.00',
                'options' => [
                    ['text' => '', 'is_correct' => 1],
                    ['text' => '', 'is_correct' => 0],
                ],
            ],
        ],
    ];
}

function backend_tests_clean_questions(array $questions): array
{
    $clean = [];
    foreach ($questions as $question) {
        if (!is_array($question)) {
            continue;
        }

        $title = trim((string)($question['title'] ?? ''));
        $type = (string)($question['question_type'] ?? 'mcq');
        $marks = (string)($question['marks'] ?? '1');
        $attributeId = (string)($question['attribute_id'] ?? '');
        $subAttributeId = (string)($question['sub_attribute_id'] ?? '');
        $weight = (string)($question['weight'] ?? '1.00');
        $options = [];

        if (!empty($question['options']) && is_array($question['options'])) {
            foreach ($question['options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optionText = trim((string)($option['text'] ?? ''));
                $isCorrect = !empty($option['is_correct']) ? 1 : 0;
                if ($optionText === '' && $isCorrect === 0) {
                    continue;
                }
                $options[] = [
                    'text' => $optionText,
                    'is_correct' => $isCorrect,
                ];
            }
        }

        if ($title === '' && $marks === '' && $attributeId === '' && $subAttributeId === '' && $weight === '' && $options === []) {
            continue;
        }

        $clean[] = [
            'title' => $title,
            'question_type' => in_array($type, ['mcq', 'subjective'], true) ? $type : 'mcq',
            'marks' => $marks !== '' ? $marks : '1',
            'attribute_id' => $attributeId,
            'sub_attribute_id' => $subAttributeId,
            'weight' => $weight !== '' ? $weight : '1.00',
            'options' => $options,
        ];
    }

    return $clean;
}

$bundle = backend_tests_default_bundle();
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $bundle['title'] = trim((string)($_POST['title'] ?? ''));
        $bundle['description'] = (string)($_POST['description'] ?? '');
        $bundle['instruction'] = (string)($_POST['instruction'] ?? '');
        $bundle['duration_minutes'] = (string)($_POST['duration_minutes'] ?? '60');
        $bundle['price_inr'] = (string)($_POST['price_inr'] ?? '0.00');
        $bundle['start_at'] = (string)($_POST['start_at'] ?? '');
        $bundle['end_at'] = (string)($_POST['end_at'] ?? '');
        $bundle['test_year'] = trim((string)($_POST['test_year'] ?? ''));
        $bundle['questions'] = backend_tests_clean_questions($_POST['questions'] ?? []);

        if ($bundle['title'] === '') {
            $errors[] = 'Test name is required.';
        }
        if (trim(strip_tags($bundle['description'])) === '') {
            $errors[] = 'Test description is required.';
        }
        if (trim(strip_tags($bundle['instruction'])) === '') {
            $errors[] = 'Test instruction is required.';
        }
        $durationMinutes = (int)$bundle['duration_minutes'];
        if ($durationMinutes <= 0) {
            $errors[] = 'Duration must be at least 1 minute.';
        }
        $priceInr = (float)$bundle['price_inr'];
        if ($priceInr < 0) {
            $errors[] = 'Price cannot be negative.';
        }
        $startAtUtc = backend_tests_local_to_utc($bundle['start_at']);
        $endAtUtc = backend_tests_local_to_utc($bundle['end_at']);
        if (!$startAtUtc || !$endAtUtc) {
            $errors[] = 'Start and end date/time are required.';
        } elseif ($endAtUtc <= $startAtUtc) {
            $errors[] = 'Test end date/time must be after the start date/time.';
        }
        if ($bundle['test_year'] === '') {
            $errors[] = 'Test year is required.';
        }
        if ($bundle['questions'] === []) {
            $errors[] = 'Add at least one question.';
        }

        $questionPayload = [];
        $totalMarks = 0;
        $attributeIds = array_column($pdo->query('SELECT id FROM attributes ORDER BY id')->fetchAll(), 'id');
        $subAttrMap = [];
        foreach ($pdo->query('SELECT id, attribute_id FROM sub_attributes')->fetchAll() as $subRow) {
            $subAttrMap[(int)$subRow['id']] = (int)$subRow['attribute_id'];
        }

        foreach ($bundle['questions'] as $index => $question) {
            $title = trim((string)$question['title']);
            $questionType = (string)$question['question_type'];
            $marks = (int)$question['marks'];
            $attributeId = (int)$question['attribute_id'];
            $subAttributeId = (int)$question['sub_attribute_id'];
            $weight = (float)$question['weight'];

            if ($title === '') {
                $errors[] = 'Question ' . ($index + 1) . ' needs a title.';
                continue;
            }
            if ($marks <= 0) {
                $errors[] = 'Question ' . ($index + 1) . ' must have marks of at least 1.';
            }
            if ($questionType === 'mcq') {
                if (count($question['options']) < 2) {
                    $errors[] = 'Question ' . ($index + 1) . ' needs at least two options.';
                }
                $correctCount = 0;
                foreach ($question['options'] as $opt) {
                    if (trim((string)$opt['text']) !== '' && (int)$opt['is_correct'] === 1) {
                        $correctCount++;
                    }
                }
                if ($correctCount === 0) {
                    $errors[] = 'Question ' . ($index + 1) . ' needs one correct option.';
                }
            }

            if ($attributeId > 0 || $subAttributeId > 0) {
                if ($attributeId <= 0 || $subAttributeId <= 0) {
                    $errors[] = 'Question ' . ($index + 1) . ' needs both attribute and sub-attribute if mapping is used.';
                } elseif (!in_array($attributeId, array_map('intval', $attributeIds), true)) {
                    $errors[] = 'Question ' . ($index + 1) . ' has an invalid attribute selection.';
                } elseif (!isset($subAttrMap[$subAttributeId]) || $subAttrMap[$subAttributeId] !== $attributeId) {
                    $errors[] = 'Question ' . ($index + 1) . ' has an invalid sub-attribute selection.';
                }
            }

            $totalMarks += $marks;
            $questionPayload[] = [
                'title' => $title,
                'question_type' => $questionType,
                'marks' => $marks,
                'attribute_id' => $attributeId,
                'sub_attribute_id' => $subAttributeId,
                'weight' => $weight,
                'options' => $question['options'],
            ];
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO tests
                     (title, description, instruction, test_year, start_at, end_at, created_by, total_marks, duration_minutes, price_inr, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $bundle['title'],
                    $bundle['description'] !== '' ? $bundle['description'] : null,
                    $bundle['instruction'] !== '' ? $bundle['instruction'] : null,
                    $bundle['test_year'],
                    $startAtUtc,
                    $endAtUtc,
                    (int)$user['sub'],
                    $totalMarks,
                    $durationMinutes,
                    $priceInr,
                ]);
                $testId = (int)$pdo->lastInsertId();

                $questionStmt = $pdo->prepare(
                    'INSERT INTO questions (question_text, question_type, difficulty, created_by, created_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $testQuestionStmt = $pdo->prepare(
                    'INSERT INTO test_questions (test_id, question_id, marks) VALUES (?, ?, ?)'
                );
                $optionStmt = $pdo->prepare(
                    'INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)'
                );
                $mappingStmt = $pdo->prepare(
                    'INSERT INTO question_attribute_mapping (question_id, attribute_id, sub_attribute_id, weight) VALUES (?, ?, ?, ?)'
                );

                foreach ($questionPayload as $question) {
                    $questionStmt->execute([
                        $question['title'],
                        $question['question_type'],
                        'medium',
                        (int)$user['sub'],
                    ]);
                    $questionId = (int)$pdo->lastInsertId();
                    $testQuestionStmt->execute([$testId, $questionId, $question['marks']]);

                    if ($question['attribute_id'] > 0 && $question['sub_attribute_id'] > 0) {
                        $mappingStmt->execute([
                            $questionId,
                            $question['attribute_id'],
                            $question['sub_attribute_id'],
                            $question['weight'] > 0 ? $question['weight'] : 1.00,
                        ]);
                    }

                    foreach ($question['options'] as $option) {
                        $optionText = trim((string)$option['text']);
                        if ($optionText === '') {
                            continue;
                        }
                        $optionStmt->execute([$questionId, $optionText, (int)$option['is_correct']]);
                    }
                }

                $pdo->commit();
                header('Location: ' . url_for('backend/tests.php?created=1'));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Save failed: ' . $e->getMessage();
            }
        }
    }
}

$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name')->fetchAll();
$subAttributes = $pdo->query('SELECT id, attribute_id, name FROM sub_attributes ORDER BY attribute_id, name')->fetchAll();
$tests = $pdo->query(
    'SELECT t.id, t.title, t.test_year, t.start_at, t.end_at, t.duration_minutes, t.price_inr, t.total_marks, t.created_at,
            COALESCE(qc.question_count, 0) AS question_count
     FROM tests t
     LEFT JOIN (
         SELECT test_id, COUNT(*) AS question_count
         FROM test_questions
         GROUP BY test_id
     ) qc ON qc.test_id = t.id
     ORDER BY t.id DESC
     LIMIT 50'
)->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>

<style>
    .eq-backend-panel {
        background: #fff;
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 22px;
        box-shadow: 0 16px 34px rgba(37, 49, 104, 0.08);
    }
    .eq-backend-panel .card-body {
        padding: 20px;
    }
    .eq-builder-shell {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .eq-builder-head {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 10px;
    }
    .eq-builder-head h2 {
        margin-bottom: 4px;
    }
    .eq-muted {
        color: #6e7487;
    }
    .eq-richtext {
        min-height: 160px;
    }
    .eq-question-card {
        border: 1px solid rgba(47, 59, 120, 0.10);
        border-radius: 20px;
        background: linear-gradient(180deg, #fff 0%, #fbfcff 100%);
        box-shadow: 0 12px 24px rgba(37, 49, 104, 0.06);
        padding: 16px;
    }
    .eq-option-card {
        border: 1px solid rgba(47, 59, 120, 0.08);
        border-radius: 14px;
        background: #fff;
        padding: 12px;
    }
    .eq-question-toolbar {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }
    .eq-question-toolbar h5 {
        margin: 0;
    }
    .eq-option-row + .eq-option-row {
        margin-top: 10px;
    }
    .eq-option-row .form-check {
        min-height: 38px;
    }
    .eq-question-actions {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .eq-question-actions .btn {
        border-radius: 12px;
    }
    .eq-inline-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6e7487;
        font-weight: 800;
        margin-bottom: 6px;
    }
    .eq-questions-list {
        display: grid;
        gap: 16px;
    }
    .eq-load-more {
        width: 100%;
        border: 1px dashed rgba(47, 59, 120, 0.22);
        border-radius: 18px;
        padding: 14px;
        background: #f8faff;
        color: #304089;
        font-weight: 700;
    }
    @media (max-width: 767px) {
        .eq-question-card .row > [class*="col-"] {
            margin-bottom: 10px;
        }
    }
</style>

<div class="eq-page-head">
    <h2>Tests Backend</h2>
    <p class="subtitle">Create a full SIRA test on one screen, then add questions and rich-text options inside the same form.</p>
</div>

<?php require __DIR__ . '/nav.php'; ?>

<?php if (!empty($_GET['created'])): ?>
    <div class="alert alert-success">Test created successfully.</div>
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

<div class="eq-builder-shell">
    <form method="post" class="eq-backend-panel" id="test-builder-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_bundle">
        <div class="card-body">
            <div class="eq-builder-head">
                <div>
                    <h2 class="mb-0">Create Test</h2>
                    <div class="eq-muted">Name, description, instruction, availability, and questions all in one screen.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-question-top" onclick="window.eqTestsAddQuestion && window.eqTestsAddQuestion(); return false;">Add More Question</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Test</button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <label class="form-label">Name of test</label>
                    <input class="form-control" name="title" required value="<?php echo htmlspecialchars($bundle['title']); ?>">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Duration of test (min)</label>
                    <input class="form-control" type="number" min="1" name="duration_minutes" value="<?php echo htmlspecialchars($bundle['duration_minutes']); ?>" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Price (INR)</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="price_inr" value="<?php echo htmlspecialchars($bundle['price_inr'] ?? '0.00'); ?>">
                    <div class="form-text">Leave as 0 for a free test.</div>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Test year</label>
                    <input class="form-control" name="test_year" value="<?php echo htmlspecialchars($bundle['test_year']); ?>" required>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Test start date and time</label>
                    <input class="form-control" type="datetime-local" name="start_at" value="<?php echo htmlspecialchars($bundle['start_at']); ?>" required>
                    <div class="form-text">Students can only start the test after this time.</div>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Test end date and time</label>
                    <input class="form-control" type="datetime-local" name="end_at" value="<?php echo htmlspecialchars($bundle['end_at']); ?>" required>
                    <div class="form-text">Students will not be able to attempt the test after this time.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description of test</label>
                    <textarea class="form-control eq-richtext" name="description" data-richtext><?php echo htmlspecialchars($bundle['description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Instruction of test</label>
                    <textarea class="form-control eq-richtext" name="instruction" data-richtext><?php echo htmlspecialchars($bundle['instruction']); ?></textarea>
                    <div class="form-text">This instruction block will show before the test starts.</div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">Questions</h5>
                    <div class="eq-muted">Add questions, options, and correct answers directly in this form.</div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-question" onclick="window.eqTestsAddQuestion && window.eqTestsAddQuestion(); return false;">Add More Question</button>
            </div>

            <div id="questions-builder" class="eq-questions-list"></div>

            <div class="mt-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-primary" id="btn-add-question-bottom" onclick="window.eqTestsAddQuestion && window.eqTestsAddQuestion(); return false;">Add More Question</button>
                <button type="submit" class="btn btn-primary">Save Test</button>
            </div>
        </div>
    </form>

    <div class="eq-backend-panel">
        <div class="card-body">
            <h5 class="mb-3">Recent Tests</h5>
            <div class="table-responsive">
                <table class="table align-middle small">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Year</th>
                            <th>Window</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Questions</th>
                            <th>Total Marks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                            <tr>
                                <td><?php echo (int)$test['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)$test['title']); ?></td>
                                <td><?php echo htmlspecialchars((string)($test['test_year'] ?? '')); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars(backend_tests_utc_to_local_label((string)($test['start_at'] ?? ''))); ?></div>
                                    <div class="text-muted">to <?php echo htmlspecialchars(backend_tests_utc_to_local_label((string)($test['end_at'] ?? ''))); ?></div>
                                </td>
                                <td><?php echo (int)$test['duration_minutes']; ?> min</td>
                                <td><?php echo htmlspecialchars(test_price_label((float)($test['price_inr'] ?? 0))); ?></td>
                                <td><?php echo (int)$test['question_count']; ?></td>
                                <td><?php echo (int)$test['total_marks']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/richtext.php'; ?>
<script>
(function () {
    const pageData = {
        bundle: <?php echo json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        attributes: <?php echo json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        subAttributes: <?php echo json_encode($subAttributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };

    const state = {
        questionsBuilder: null,
        questionSeed: 0,
        initialized: false
    };
    let questionSeed = 0;

    const subAttributeMap = {};
    pageData.subAttributes.forEach(function (sub) {
        const attrId = String(sub.attribute_id);
        if (!subAttributeMap[attrId]) {
            subAttributeMap[attrId] = [];
        }
        subAttributeMap[attrId].push(sub);
    });

    function initEditors(root) {
        if (window.EQRichText) {
            try {
                window.EQRichText.init(root);
            } catch (error) {
                console.warn('EQRichText init skipped', error);
            }
        }
    }

    function getQuestionsBuilder() {
        if (state.questionsBuilder && document.contains(state.questionsBuilder)) {
            return state.questionsBuilder;
        }
        state.questionsBuilder = document.getElementById('questions-builder') || document.querySelector('.eq-questions-list');
        return state.questionsBuilder;
    }

    function optionRowHtml(questionIndex, optionIndex, option = {}) {
        const checked = option.is_correct ? 'checked' : '';
        const value = option.text || '';
        return `
            <div class="eq-option-row" data-option-row>
                <div class="eq-option-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <strong class="small text-uppercase text-muted">Option ${optionIndex + 1}</strong>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>Remove</button>
                    </div>
                    <textarea class="form-control eq-richtext" data-richtext name="questions[${questionIndex}][options][${optionIndex}][text]">${value}</textarea>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="q${questionIndex}_opt${optionIndex}_correct" name="questions[${questionIndex}][options][${optionIndex}][is_correct]" value="1" ${checked}>
                        <label class="form-check-label" for="q${questionIndex}_opt${optionIndex}_correct">is_correct option</label>
                    </div>
                </div>
            </div>`;
    }

    function createQuestionCard(question = {}) {
        const idx = questionSeed++;
        const title = question.title || '';
        const type = question.question_type || 'mcq';
        const marks = question.marks || '1';
        const attributeId = question.attribute_id || '';
        const subAttributeId = question.sub_attribute_id || '';
        const weight = question.weight || '1.00';
        const options = Array.isArray(question.options) && question.options.length ? question.options : [
            { text: '', is_correct: 1 },
            { text: '', is_correct: 0 }
        ];

        const card = document.createElement('div');
        card.className = 'eq-question-card';
        card.dataset.questionCard = '1';
        card.dataset.questionIndex = String(idx);
        card.innerHTML = `
            <div class="eq-question-toolbar">
                <h5>Question ${idx + 1}</h5>
                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-question>Remove question</button>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="eq-inline-label">Question title</div>
                    <textarea class="form-control eq-richtext" data-richtext name="questions[${idx}][title]">${title}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Question type</label>
                    <select class="form-select" name="questions[${idx}][question_type]" data-question-type>
                        <option value="mcq" ${type === 'mcq' ? 'selected' : ''}>MCQ</option>
                        <option value="subjective" ${type === 'subjective' ? 'selected' : ''}>Subjective</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marks</label>
                    <input class="form-control" type="number" min="1" name="questions[${idx}][marks]" value="${marks}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Weight</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="questions[${idx}][weight]" value="${weight}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Attribute</label>
                    <select class="form-select" name="questions[${idx}][attribute_id]" data-attribute-select>
                        <option value="">Select attribute</option>
                        ${pageData.attributes.map(function (attr) {
                            return `<option value="${attr.id}" ${String(attributeId) === String(attr.id) ? 'selected' : ''}>${attr.name}</option>`;
                        }).join('')}
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-attribute</label>
                    <select class="form-select" name="questions[${idx}][sub_attribute_id]" data-sub-attribute-select>
                        <option value="">Select sub-attribute</option>
                    </select>
                </div>
            </div>

            <div class="mt-3" data-options-wrap>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <strong>Options</strong>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-add-option>Add option</button>
                </div>
                <div data-options-list class="d-grid gap-2"></div>
            </div>
        `;

        const optionsList = card.querySelector('[data-options-list]');
        options.forEach(function (option, optionIndex) {
            optionsList.insertAdjacentHTML('beforeend', optionRowHtml(idx, optionIndex, option));
        });

        function populateSubAttributes() {
            const attrSelect = card.querySelector('[data-attribute-select]');
            const subSelect = card.querySelector('[data-sub-attribute-select]');
            const selectedAttr = String(attrSelect.value || '');
            const selectedSub = String(subAttributeId || '');
            const items = subAttributeMap[selectedAttr] || [];
            subSelect.innerHTML = '<option value="">Select sub-attribute</option>' + items.map(function (item) {
                return `<option value="${item.id}" ${String(item.id) === selectedSub ? 'selected' : ''}>${item.name}</option>`;
            }).join('');
        }

        function syncQuestionType() {
            const typeValue = card.querySelector('[data-question-type]').value;
            card.querySelector('[data-options-wrap]').style.display = typeValue === 'mcq' ? '' : 'none';
        }

        card.querySelector('[data-question-type]').addEventListener('change', syncQuestionType);
        card.querySelector('[data-attribute-select]').addEventListener('change', populateSubAttributes);

        populateSubAttributes();
        syncQuestionType();
        return card;
    }

    function addQuestion(question = {}) {
        const builder = getQuestionsBuilder();
        if (!builder) {
            return null;
        }
        const card = createQuestionCard(question);
        builder.appendChild(card);
        initEditors(card);
        return card;
    }

    function renderBundle() {
        const builder = getQuestionsBuilder();
        if (!builder) {
            return;
        }
        builder.innerHTML = '';
        questionSeed = 0;
        (pageData.bundle.questions || []).forEach(function (question) {
            addQuestion(question);
        });
        if (!builder.querySelector('[data-question-card]')) {
            addQuestion();
        }
    }

    function handleBuilderClick(event) {
        const builder = getQuestionsBuilder();
        if (!builder) {
            return;
        }
        const addOptionBtn = event.target.closest('[data-add-option]');
        if (addOptionBtn) {
            const card = addOptionBtn.closest('[data-question-card]');
            if (!card) {
                return;
            }
            const optionsList = card.querySelector('[data-options-list]');
            const nextIndex = card.querySelectorAll('[data-option-row]').length;
            const questionIndex = card.dataset.questionIndex || '0';
            optionsList.insertAdjacentHTML('beforeend', optionRowHtml(questionIndex, nextIndex, { text: '', is_correct: 0 }));
            initEditors(optionsList);
            return;
        }

        const removeOptionBtn = event.target.closest('[data-remove-option]');
        if (removeOptionBtn) {
            const row = removeOptionBtn.closest('[data-option-row]');
            if (row) {
                row.remove();
            }
            return;
        }

        const removeQuestionBtn = event.target.closest('[data-remove-question]');
        if (removeQuestionBtn) {
            const card = removeQuestionBtn.closest('[data-question-card]');
            if (card) {
                card.remove();
            }
            if (!builder.querySelector('[data-question-card]')) {
                addQuestion();
            }
        }
    }

    function bindBuilderEvents() {
        if (state.initialized) {
            return;
        }
        const builder = getQuestionsBuilder();
        const form = document.getElementById('test-builder-form');
        if (builder) {
            builder.addEventListener('click', handleBuilderClick);
        }
        if (form) {
            form.addEventListener('submit', function () {
                if (window.EQRichText) {
                    try {
                        window.EQRichText.init(document);
                    } catch (error) {
                        console.warn('EQRichText submit sync skipped', error);
                    }
                }
            });
        }
        state.initialized = true;
    }

    window.eqTestsAddQuestion = function () {
        const created = addQuestion();
        const builder = getQuestionsBuilder();
        if (created && builder) {
            builder.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
        return false;
    };

    window.eqTestsRenderBundle = renderBundle;

    bindBuilderEvents();
    renderBundle();
    initEditors(document);
})();
</script>

<?php require_once dirname(__DIR__) . '/includes_footer.php'; ?>
