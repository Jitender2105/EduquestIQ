<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes_payments.php';

$user = backend_user();
$pdo = get_pdo();
$testsHasActiveColumn = table_has_column($pdo, 'tests', 'is_active');
$testsHasGradeColumn = table_has_column($pdo, 'tests', 'target_grade');

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
        'edit_id' => '',
        'access_type' => 'free',
        'is_active' => '1',
        'title' => '',
        'target_grade' => '',
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

function backend_tests_bundle_from_post(array $source): array
{
    $bundle = backend_tests_default_bundle();
    $bundle['edit_id'] = trim((string)($source['edit_id'] ?? ''));
    $bundle['access_type'] = (string)($source['access_type'] ?? 'free');
    $bundle['is_active'] = (string)($source['is_active'] ?? '1');
    $bundle['title'] = trim((string)($source['title'] ?? ''));
    $bundle['target_grade'] = trim((string)($source['target_grade'] ?? ''));
    $bundle['description'] = (string)($source['description'] ?? '');
    $bundle['instruction'] = (string)($source['instruction'] ?? '');
    $bundle['duration_minutes'] = (string)($source['duration_minutes'] ?? '60');
    $bundle['price_inr'] = (string)($source['price_inr'] ?? '0.00');
    $bundle['start_at'] = (string)($source['start_at'] ?? '');
    $bundle['end_at'] = (string)($source['end_at'] ?? '');
    $bundle['test_year'] = trim((string)($source['test_year'] ?? ''));
    $bundle['questions'] = backend_tests_clean_questions($source['questions'] ?? []);
    return $bundle;
}

function backend_tests_load_bundle(PDO $pdo, int $testId, bool $hasGradeColumn, bool $hasActiveColumn): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, description, instruction, duration_minutes, price_inr, start_at, end_at, test_year'
        . ($hasGradeColumn ? ', target_grade' : '')
        . ($hasActiveColumn ? ', is_active' : '')
        . '
         FROM tests
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$testId]);
    $test = $stmt->fetch();
    if (!$test) {
        return null;
    }

    $questionStmt = $pdo->prepare(
        'SELECT q.id AS question_id, q.question_text, q.question_type, tq.marks,
                qam.attribute_id, qam.sub_attribute_id, qam.weight
         FROM test_questions tq
         JOIN questions q ON q.id = tq.question_id
         LEFT JOIN question_attribute_mapping qam ON qam.question_id = q.id
         WHERE tq.test_id = ?
         ORDER BY tq.id ASC'
    );
    $questionStmt->execute([$testId]);
    $questions = [];
    foreach ($questionStmt->fetchAll() as $row) {
        $optionStmt = $pdo->prepare('SELECT option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id ASC');
        $optionStmt->execute([(int)$row['question_id']]);
        $options = [];
        foreach ($optionStmt->fetchAll() as $optionRow) {
            $options[] = [
                'text' => (string)$optionRow['option_text'],
                'is_correct' => (int)$optionRow['is_correct'],
            ];
        }
        if ($options === []) {
            $options = backend_tests_blank_question()['options'];
        }
        $questions[] = [
            'title' => (string)$row['question_text'],
            'question_type' => (string)$row['question_type'],
            'marks' => (string)$row['marks'],
            'attribute_id' => (string)($row['attribute_id'] ?? ''),
            'sub_attribute_id' => (string)($row['sub_attribute_id'] ?? ''),
            'weight' => (string)($row['weight'] ?? '1.00'),
            'options' => $options,
        ];
    }

    return [
        'edit_id' => (string)$test['id'],
        'access_type' => ((float)($test['price_inr'] ?? 0) > 0) ? 'paid' : 'free',
        'is_active' => $hasActiveColumn && empty($test['is_active']) ? '0' : '1',
        'title' => (string)$test['title'],
        'target_grade' => $hasGradeColumn ? (string)($test['target_grade'] ?? '') : '',
        'description' => (string)($test['description'] ?? ''),
        'instruction' => (string)($test['instruction'] ?? ''),
        'duration_minutes' => (string)$test['duration_minutes'],
        'price_inr' => number_format((float)($test['price_inr'] ?? 0), 2, '.', ''),
        'start_at' => backend_tests_utc_to_local_input((string)($test['start_at'] ?? '')),
        'end_at' => backend_tests_utc_to_local_input((string)($test['end_at'] ?? '')),
        'test_year' => (string)($test['test_year'] ?? ''),
        'questions' => $questions !== [] ? $questions : backend_tests_default_bundle()['questions'],
    ];
}

function backend_tests_blank_question(): array
{
    return [
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
    ];
}

function backend_tests_render_question_card(int $idx, array $question, array $attributes, array $subAttributes): string
{
    $title = (string)($question['title'] ?? '');
    $type = (string)($question['question_type'] ?? 'mcq');
    $marks = (string)($question['marks'] ?? '1');
    $attributeId = (string)($question['attribute_id'] ?? '');
    $subAttributeId = (string)($question['sub_attribute_id'] ?? '');
    $weight = (string)($question['weight'] ?? '1.00');
    $options = (!empty($question['options']) && is_array($question['options']))
        ? $question['options']
        : [
            ['text' => '', 'is_correct' => 1],
            ['text' => '', 'is_correct' => 0],
        ];

    $availableSubAttributes = [];
    foreach ($subAttributes as $subAttribute) {
        if ((string)($subAttribute['attribute_id'] ?? '') === $attributeId) {
            $availableSubAttributes[] = $subAttribute;
        }
    }

    ob_start();
    ?>
    <div class="eq-question-card" data-question-card="1" data-question-index="<?php echo (int)$idx; ?>">
        <div class="eq-question-toolbar">
            <h5>Question <?php echo (int)($idx + 1); ?></h5>
            <button type="submit" class="btn btn-outline-danger btn-sm" name="action" value="remove_question:<?php echo (int)$idx; ?>" formnovalidate>Remove question</button>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="eq-inline-label">Question title</div>
                <textarea class="form-control eq-richtext" data-richtext name="questions[<?php echo (int)$idx; ?>][title]"><?php echo htmlspecialchars($title); ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Question type</label>
                <select class="form-select" name="questions[<?php echo (int)$idx; ?>][question_type]" data-question-type>
                    <option value="mcq"<?php echo $type === 'mcq' ? ' selected' : ''; ?>>MCQ</option>
                    <option value="subjective"<?php echo $type === 'subjective' ? ' selected' : ''; ?>>Subjective</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Marks</label>
                <input class="form-control" type="number" min="1" name="questions[<?php echo (int)$idx; ?>][marks]" value="<?php echo htmlspecialchars($marks); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Weight</label>
                <input class="form-control" type="number" step="0.01" min="0" name="questions[<?php echo (int)$idx; ?>][weight]" value="<?php echo htmlspecialchars($weight); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Attribute</label>
                <select class="form-select" name="questions[<?php echo (int)$idx; ?>][attribute_id]" data-attribute-select>
                    <option value="">Select attribute</option>
                    <?php foreach ($attributes as $attribute): ?>
                        <option value="<?php echo (int)$attribute['id']; ?>"<?php echo (string)$attributeId === (string)$attribute['id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$attribute['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sub-attribute</label>
                <select class="form-select" name="questions[<?php echo (int)$idx; ?>][sub_attribute_id]" data-sub-attribute-select>
                    <option value="">Select sub-attribute</option>
                    <?php foreach ($availableSubAttributes as $subAttribute): ?>
                        <option value="<?php echo (int)$subAttribute['id']; ?>"<?php echo (string)$subAttributeId === (string)$subAttribute['id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$subAttribute['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mt-3" data-options-wrap<?php echo $type === 'mcq' ? '' : ' style="display:none"'; ?>>
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <strong>Options</strong>
                <button type="submit" class="btn btn-outline-primary btn-sm" name="action" value="add_option:<?php echo (int)$idx; ?>" formnovalidate>Add option</button>
            </div>
            <div data-options-list class="d-grid gap-2">
                <?php foreach ($options as $optionIndex => $option): ?>
                    <?php $optionText = (string)($option['text'] ?? ''); ?>
                    <div class="eq-option-row" data-option-row>
                        <div class="eq-option-card">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <strong class="small text-uppercase text-muted">Option <?php echo (int)($optionIndex + 1); ?></strong>
                                <button type="submit" class="btn btn-outline-danger btn-sm" name="action" value="remove_option:<?php echo (int)$idx; ?>:<?php echo (int)$optionIndex; ?>" formnovalidate>Remove</button>
                            </div>
                            <textarea class="form-control eq-richtext" data-richtext name="questions[<?php echo (int)$idx; ?>][options][<?php echo (int)$optionIndex; ?>][text]"><?php echo htmlspecialchars($optionText); ?></textarea>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="q<?php echo (int)$idx; ?>_opt<?php echo (int)$optionIndex; ?>_correct" name="questions[<?php echo (int)$idx; ?>][options][<?php echo (int)$optionIndex; ?>][is_correct]" value="1"<?php echo !empty($option['is_correct']) ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="q<?php echo (int)$idx; ?>_opt<?php echo (int)$optionIndex; ?>_correct">is_correct option</label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
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
$editId = backend_is_super_admin($user) ? max(0, (int)($_GET['edit'] ?? 0)) : 0;

if ($editId > 0) {
    $loadedBundle = backend_tests_load_bundle($pdo, $editId, $testsHasGradeColumn, $testsHasActiveColumn);
    if ($loadedBundle) {
        $bundle = $loadedBundle;
    } else {
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save_bundle');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $bundle = backend_tests_bundle_from_post($_POST);
        $postedEditId = max(0, (int)($bundle['edit_id'] ?? 0));
        backend_require_admin($user);
        if ($postedEditId > 0) {
            backend_require_super_admin($user);
        }
        if ($action === 'add_question') {
            $bundle['questions'][] = backend_tests_blank_question();
        } elseif (str_starts_with($action, 'add_option:')) {
            $questionIndex = (int)substr($action, strlen('add_option:'));
            if (isset($bundle['questions'][$questionIndex])) {
                $bundle['questions'][$questionIndex]['options'][] = ['text' => '', 'is_correct' => 0];
            }
        } elseif (str_starts_with($action, 'remove_question:')) {
            $questionIndex = (int)substr($action, strlen('remove_question:'));
            if (isset($bundle['questions'][$questionIndex])) {
                unset($bundle['questions'][$questionIndex]);
                $bundle['questions'] = array_values($bundle['questions']);
            }
            if ($bundle['questions'] === []) {
                $bundle['questions'][] = backend_tests_blank_question();
            }
        } elseif (str_starts_with($action, 'remove_option:')) {
            $parts = explode(':', $action);
            $questionIndex = isset($parts[1]) ? (int)$parts[1] : -1;
            $optionIndex = isset($parts[2]) ? (int)$parts[2] : -1;
            if (isset($bundle['questions'][$questionIndex]['options'][$optionIndex])) {
                unset($bundle['questions'][$questionIndex]['options'][$optionIndex]);
                $bundle['questions'][$questionIndex]['options'] = array_values($bundle['questions'][$questionIndex]['options']);
            }
            if (isset($bundle['questions'][$questionIndex]) && count($bundle['questions'][$questionIndex]['options']) < 2) {
                while (count($bundle['questions'][$questionIndex]['options']) < 2) {
                    $bundle['questions'][$questionIndex]['options'][] = ['text' => '', 'is_correct' => 0];
                }
                $bundle['questions'][$questionIndex]['options'][0]['is_correct'] = 1;
            }
        } else {
            if ($bundle['title'] === '') {
                $errors[] = 'Test name is required.';
            }
            if ($testsHasGradeColumn && $bundle['target_grade'] === '') {
                $errors[] = 'Select the class for this test.';
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
            if (!in_array($bundle['access_type'], ['free', 'paid'], true)) {
                $errors[] = 'Select a valid access type.';
            }
            $isActive = ($bundle['is_active'] ?? '1') === '1' ? 1 : 0;
            $priceInr = $bundle['access_type'] === 'free' ? 0.0 : (float)$bundle['price_inr'];
            if ($priceInr < 0) {
                $errors[] = 'Price cannot be negative.';
            }
            if ($bundle['access_type'] === 'paid' && $priceInr <= 0) {
                $errors[] = 'Paid tests must have a price greater than 0.';
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
                    if ($postedEditId > 0) {
                        $attemptCountStmt = $pdo->prepare('SELECT COUNT(*) FROM test_attempts WHERE test_id = ?');
                        $attemptCountStmt->execute([$postedEditId]);
                        if ((int)$attemptCountStmt->fetchColumn() > 0) {
                            throw new RuntimeException('This test already has student attempts. Create a new version instead of changing existing questions.');
                        }
                        $stmt = $pdo->prepare(
                            'UPDATE tests
                             SET title = ?, description = ?, instruction = ?, test_year = ?'
                             . ($testsHasGradeColumn ? ', target_grade = ?' : '')
                             . ($testsHasActiveColumn ? ', is_active = ?' : '')
                             . ', start_at = ?, end_at = ?, total_marks = ?, duration_minutes = ?, price_inr = ?
                             WHERE id = ?'
                        );
                        $updateParams = [
                            $bundle['title'],
                            $bundle['description'] !== '' ? $bundle['description'] : null,
                            $bundle['instruction'] !== '' ? $bundle['instruction'] : null,
                            $bundle['test_year'],
                            $startAtUtc,
                            $endAtUtc,
                            $totalMarks,
                            $durationMinutes,
                            $priceInr,
                            $postedEditId,
                        ];
                        if ($testsHasGradeColumn) {
                            array_splice($updateParams, 4, 0, [$bundle['target_grade']]);
                        }
                        if ($testsHasActiveColumn) {
                            array_splice($updateParams, $testsHasGradeColumn ? 5 : 4, 0, [$isActive]);
                        }
                        $stmt->execute($updateParams);
                        $existingQuestionStmt = $pdo->prepare('SELECT question_id FROM test_questions WHERE test_id = ?');
                        $existingQuestionStmt->execute([$postedEditId]);
                        $existingQuestionIds = array_map('intval', array_column($existingQuestionStmt->fetchAll(), 'question_id'));
                        if ($existingQuestionIds !== []) {
                            $placeholders = implode(',', array_fill(0, count($existingQuestionIds), '?'));
                            $pdo->prepare("DELETE FROM question_attribute_mapping WHERE question_id IN ($placeholders)")->execute($existingQuestionIds);
                            $pdo->prepare("DELETE FROM question_options WHERE question_id IN ($placeholders)")->execute($existingQuestionIds);
                            $pdo->prepare('DELETE FROM test_questions WHERE test_id = ?')->execute([$postedEditId]);
                            $pdo->prepare("DELETE FROM questions WHERE id IN ($placeholders)")->execute($existingQuestionIds);
                        } else {
                            $pdo->prepare('DELETE FROM test_questions WHERE test_id = ?')->execute([$postedEditId]);
                        }
                        $testId = $postedEditId;
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO tests
                             (title, description, instruction, test_year'
                             . ($testsHasGradeColumn ? ', target_grade' : '')
                             . ($testsHasActiveColumn ? ', is_active' : '')
                             . ', start_at, end_at, created_by, total_marks, duration_minutes, price_inr, created_at)
                             VALUES (?, ?, ?, ?'
                             . ($testsHasGradeColumn ? ', ?' : '')
                             . ($testsHasActiveColumn ? ', ?' : '')
                             . ', ?, ?, ?, ?, ?, ?, NOW())'
                        );
                        $insertParams = [
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
                        ];
                        if ($testsHasGradeColumn) {
                            array_splice($insertParams, 4, 0, [$bundle['target_grade']]);
                        }
                        if ($testsHasActiveColumn) {
                            array_splice($insertParams, $testsHasGradeColumn ? 5 : 4, 0, [$isActive]);
                        }
                        $stmt->execute($insertParams);
                        $testId = (int)$pdo->lastInsertId();
                    }

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
                    header('Location: ' . url_for('backend/tests.php?' . ($postedEditId > 0 ? 'updated=1' : 'created=1')));
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
}

$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name')->fetchAll();
$subAttributes = $pdo->query('SELECT id, attribute_id, name FROM sub_attributes ORDER BY attribute_id, name')->fetchAll();
$tests = $pdo->query(
    'SELECT t.id, t.title, t.test_year'
        . ($testsHasGradeColumn ? ', t.target_grade' : ', "" AS target_grade')
        . ($testsHasActiveColumn ? ', t.is_active' : ', 1 AS is_active')
        . ', t.start_at, t.end_at, t.duration_minutes, t.price_inr, t.total_marks, t.created_at,
            COALESCE(qc.question_count, 0) AS question_count,
            COALESCE(ac.attempt_count, 0) AS attempt_count
     FROM tests t
     LEFT JOIN (
         SELECT test_id, COUNT(*) AS question_count
         FROM test_questions
         GROUP BY test_id
     ) qc ON qc.test_id = t.id
     LEFT JOIN (
         SELECT test_id, COUNT(*) AS attempt_count
         FROM test_attempts
         GROUP BY test_id
     ) ac ON ac.test_id = t.id
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
<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success">Test updated successfully.</div>
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

<div class="eq-builder-shell"
     id="backend-tests-page"
     data-bundle="<?php echo htmlspecialchars(json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
     data-attributes="<?php echo htmlspecialchars(json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
     data-sub-attributes="<?php echo htmlspecialchars(json_encode($subAttributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
    <form method="post" class="eq-backend-panel" id="test-builder-form">
        <?php echo csrf_field(); ?>
        <div class="card-body">
            <div class="eq-builder-head">
                <div>
                    <h2 class="mb-0"><?php echo $editId > 0 ? 'Edit Test' : 'Create Test'; ?></h2>
                    <div class="eq-muted"><?php echo $editId > 0 ? 'Super admin edit mode for an existing test.' : 'Name, description, instruction, availability, and questions all in one screen.'; ?></div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-primary btn-sm" id="btn-add-question-top" name="action" value="add_question" formnovalidate>Add More Question</button>
                    <button type="submit" class="btn btn-primary btn-sm" name="action" value="save_bundle"><?php echo $editId > 0 ? 'Update Test' : 'Save Test'; ?></button>
                </div>
            </div>
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($bundle['edit_id'] ?? ''); ?>">

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <label class="form-label">Name of test</label>
                    <input class="form-control" name="title" required value="<?php echo htmlspecialchars($bundle['title']); ?>">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" name="target_grade" required>
                        <option value="">Select class</option>
                        <?php foreach (['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'] as $gradeOption): ?>
                            <option value="<?php echo htmlspecialchars($gradeOption); ?>"<?php echo ($bundle['target_grade'] ?? '') === $gradeOption ? ' selected' : ''; ?>><?php echo htmlspecialchars($gradeOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Visibility</label>
                    <select class="form-select" name="is_active">
                        <option value="1"<?php echo ($bundle['is_active'] ?? '1') === '1' ? ' selected' : ''; ?>>Active</option>
                        <option value="0"<?php echo ($bundle['is_active'] ?? '1') === '0' ? ' selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Test access</label>
                    <select class="form-select" name="access_type" id="test-access-type">
                        <option value="free"<?php echo ($bundle['access_type'] ?? 'free') === 'free' ? ' selected' : ''; ?>>Free</option>
                        <option value="paid"<?php echo ($bundle['access_type'] ?? 'free') === 'paid' ? ' selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Duration of test (min)</label>
                    <input class="form-control" type="number" min="1" name="duration_minutes" value="<?php echo htmlspecialchars($bundle['duration_minutes']); ?>" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Price (INR)</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="price_inr" id="test-price-input" value="<?php echo htmlspecialchars($bundle['price_inr'] ?? '0.00'); ?>">
                    <div class="form-text">Choose Free or Paid. Paid tests require a price.</div>
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
                <button type="submit" class="btn btn-outline-primary btn-sm" id="btn-add-question" name="action" value="add_question" formnovalidate>Add More Question</button>
            </div>

            <div id="questions-builder" class="eq-questions-list">
                <?php
                $renderQuestions = $bundle['questions'];
                if ($renderQuestions === []) {
                    $renderQuestions = backend_tests_default_bundle()['questions'];
                }
                foreach ($renderQuestions as $questionIndex => $question) {
                    echo backend_tests_render_question_card((int)$questionIndex, (array)$question, $attributes, $subAttributes);
                }
                ?>
            </div>

            <div class="mt-3 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-outline-primary" id="btn-add-question-bottom" name="action" value="add_question" formnovalidate>Add More Question</button>
                <button type="submit" class="btn btn-primary" name="action" value="save_bundle"><?php echo $editId > 0 ? 'Update Test' : 'Save Test'; ?></button>
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
                            <th>Class</th>
                            <th>Status</th>
                            <th>Year</th>
                            <th>Window</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Total Marks</th>
                            <?php if (backend_is_super_admin($user)): ?><th>Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                            <tr>
                                <td><?php echo (int)$test['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)$test['title']); ?></td>
                                <td><?php echo htmlspecialchars((string)($test['target_grade'] ?? '')); ?></td>
                                <td><span class="badge <?php echo !empty($test['is_active']) ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo !empty($test['is_active']) ? 'Active' : 'Inactive'; ?></span></td>
                                <td><?php echo htmlspecialchars((string)($test['test_year'] ?? '')); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars(backend_tests_utc_to_local_label((string)($test['start_at'] ?? ''))); ?></div>
                                    <div class="text-muted">to <?php echo htmlspecialchars(backend_tests_utc_to_local_label((string)($test['end_at'] ?? ''))); ?></div>
                                </td>
                                <td><?php echo (int)$test['duration_minutes']; ?> min</td>
                                <td><?php echo (float)($test['price_inr'] ?? 0) > 0 ? htmlspecialchars(test_price_label((float)$test['price_inr'])) : 'Free'; ?></td>
                                <td><?php echo (int)$test['question_count']; ?></td>
                                <td><?php echo (int)$test['attempt_count']; ?></td>
                                <td><?php echo (int)$test['total_marks']; ?></td>
                                <?php if (backend_is_super_admin($user)): ?>
                                    <td><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(url_for('backend/tests.php?edit=' . (int)$test['id'])); ?>">Edit</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function parseJsonAttribute(value, fallback) {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function bootstrapFallback() {
        const root = document.getElementById('backend-tests-page');
        const builder = document.getElementById('questions-builder');
        if (!root || !builder || typeof window.backendTestsAddQuestion === 'function') {
            return;
        }

        const pageData = {
            bundle: parseJsonAttribute(root.dataset.bundle, { questions: [] }),
            attributes: parseJsonAttribute(root.dataset.attributes, []),
            subAttributes: parseJsonAttribute(root.dataset.subAttributes, [])
        };

        const subAttributeMap = {};
        pageData.subAttributes.forEach(function (sub) {
            const attrId = String(sub.attribute_id);
            if (!subAttributeMap[attrId]) {
                subAttributeMap[attrId] = [];
            }
            subAttributeMap[attrId].push(sub);
        });

        let questionSeed = 0;

        function richTextHtml(fieldName, value, placeholder) {
            return '<textarea class="form-control eq-richtext" data-richtext name="' + fieldName + '">' +
                (value || '') +
                '</textarea>';
        }

        function optionRowHtml(questionIndex, optionIndex, option) {
            const checked = option && option.is_correct ? 'checked' : '';
            const value = option && option.text ? option.text : '';
            return (
                '<div class="eq-option-row" data-option-row>' +
                    '<div class="eq-option-card">' +
                        '<div class="d-flex justify-content-between align-items-center gap-2 mb-2">' +
                            '<strong class="small text-uppercase text-muted">Option ' + (optionIndex + 1) + '</strong>' +
                            '<button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>Remove</button>' +
                        '</div>' +
                        richTextHtml('questions[' + questionIndex + '][options][' + optionIndex + '][text]', value, '') +
                        '<div class="form-check mt-2">' +
                            '<input class="form-check-input" type="checkbox" id="q' + questionIndex + '_opt' + optionIndex + '_correct" name="questions[' + questionIndex + '][options][' + optionIndex + '][is_correct]" value="1" ' + checked + '>' +
                            '<label class="form-check-label" for="q' + questionIndex + '_opt' + optionIndex + '_correct">is_correct option</label>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }

        function createQuestionCard(question) {
            const idx = questionSeed++;
            const item = question || {};
            const title = item.title || '';
            const type = item.question_type || 'mcq';
            const marks = item.marks || '1';
            const attributeId = item.attribute_id || '';
            const subAttributeId = item.sub_attribute_id || '';
            const weight = item.weight || '1.00';
            const options = Array.isArray(item.options) && item.options.length ? item.options : [
                { text: '', is_correct: 1 },
                { text: '', is_correct: 0 }
            ];

            const card = document.createElement('div');
            card.className = 'eq-question-card';
            card.dataset.questionCard = '1';
            card.dataset.questionIndex = String(idx);
            card.innerHTML = [
                '<div class="eq-question-toolbar">',
                    '<h5>Question ' + (idx + 1) + '</h5>',
                    '<button type="button" class="btn btn-outline-danger btn-sm" data-remove-question>Remove question</button>',
                '</div>',
                '<div class="row g-3">',
                    '<div class="col-12">',
                        '<div class="eq-inline-label">Question title</div>',
                        richTextHtml('questions[' + idx + '][title]', title, ''),
                    '</div>',
                    '<div class="col-md-4">',
                        '<label class="form-label">Question type</label>',
                        '<select class="form-select" name="questions[' + idx + '][question_type]" data-question-type>',
                            '<option value="mcq"' + (type === 'mcq' ? ' selected' : '') + '>MCQ</option>',
                            '<option value="subjective"' + (type === 'subjective' ? ' selected' : '') + '>Subjective</option>',
                        '</select>',
                    '</div>',
                    '<div class="col-md-4">',
                        '<label class="form-label">Marks</label>',
                        '<input class="form-control" type="number" min="1" name="questions[' + idx + '][marks]" value="' + marks + '">',
                    '</div>',
                    '<div class="col-md-4">',
                        '<label class="form-label">Weight</label>',
                        '<input class="form-control" type="number" step="0.01" min="0" name="questions[' + idx + '][weight]" value="' + weight + '">',
                    '</div>',
                    '<div class="col-md-6">',
                        '<label class="form-label">Attribute</label>',
                        '<select class="form-select" name="questions[' + idx + '][attribute_id]" data-attribute-select>',
                            '<option value="">Select attribute</option>',
                            pageData.attributes.map(function (attr) {
                                return '<option value="' + attr.id + '"' + (String(attributeId) === String(attr.id) ? ' selected' : '') + '>' + attr.name + '</option>';
                            }).join(''),
                        '</select>',
                    '</div>',
                    '<div class="col-md-6">',
                        '<label class="form-label">Sub-attribute</label>',
                        '<select class="form-select" name="questions[' + idx + '][sub_attribute_id]" data-sub-attribute-select>',
                            '<option value="">Select sub-attribute</option>',
                        '</select>',
                    '</div>',
                '</div>',
                '<div class="mt-3" data-options-wrap>',
                    '<div class="d-flex justify-content-between align-items-center gap-2 mb-2">',
                        '<strong>Options</strong>',
                        '<button type="button" class="btn btn-outline-primary btn-sm" data-add-option>Add option</button>',
                    '</div>',
                    '<div data-options-list class="d-grid gap-2"></div>',
                '</div>'
            ].join('');

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
                    return '<option value="' + item.id + '"' + (String(item.id) === selectedSub ? ' selected' : '') + '>' + item.name + '</option>';
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

        function initEditors(rootNode) {
            if (window.EQRichText && typeof window.EQRichText.init === 'function') {
                try {
                    window.EQRichText.init(rootNode);
                } catch (error) {
                    console.warn('backend-tests fallback: rich text init skipped', error);
                }
            }
        }

        function addQuestion(question) {
            const card = createQuestionCard(question || {});
            builder.appendChild(card);
            initEditors(card);
            return card;
        }

        function renderBundle() {
            builder.innerHTML = '';
            questionSeed = 0;
            (pageData.bundle.questions || []).forEach(function (question) {
                addQuestion(question);
            });
            if (!builder.querySelector('[data-question-card]')) {
                addQuestion();
            }
        }

        function bindEvents() {
            builder.addEventListener('click', function (event) {
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
                    const questionCard = removeQuestionBtn.closest('[data-question-card]');
                    if (questionCard) {
                        questionCard.remove();
                    }
                    if (!builder.querySelector('[data-question-card]')) {
                        addQuestion();
                    }
                }
            });

            ['btn-add-question-top', 'btn-add-question', 'btn-add-question-bottom'].forEach(function (id) {
                const button = document.getElementById(id);
                if (!button) {
                    return;
                }
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    addQuestion();
                    builder.scrollIntoView({ behavior: 'smooth', block: 'end' });
                });
            });

            const form = document.getElementById('test-builder-form');
            if (form) {
                form.addEventListener('submit', function () {
                    initEditors(document);
                });
            }
        }

        window.backendTestsAddQuestion = function () {
            addQuestion();
            builder.scrollIntoView({ behavior: 'smooth', block: 'end' });
            return false;
        };
        window.eqTestsAddQuestion = window.backendTestsAddQuestion;

        bindEvents();
        renderBundle();
        initEditors(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapFallback);
    } else {
        bootstrapFallback();
    }

    function syncPriceField() {
        const accessSelect = document.getElementById('test-access-type');
        const priceInput = document.getElementById('test-price-input');
        if (!accessSelect || !priceInput) {
            return;
        }
        const isPaid = accessSelect.value === 'paid';
        priceInput.disabled = !isPaid;
        if (!isPaid) {
            priceInput.value = '0.00';
        } else if (!priceInput.value || Number(priceInput.value) <= 0) {
            priceInput.value = '99.00';
        }
    }

    document.getElementById('test-access-type')?.addEventListener('change', syncPriceField);
    syncPriceField();
})();
</script>

<?php require __DIR__ . '/richtext.php'; ?>
<script>
if (window.EQRichText && typeof window.EQRichText.init === 'function') {
    window.EQRichText.init(document);
    window.setTimeout(function () {
        window.EQRichText.init(document);
    }, 250);
}

(function () {
    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        ['link', 'image', 'video'],
        [{ header: 1 }, { header: 2 }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ script: 'sub' }, { script: 'super' }],
        [{ indent: '-1' }, { indent: '+1' }],
        [{ direction: 'rtl' }],
        [{ size: ['small', false, 'large', 'huge'] }],
        [{ header: [1, 2, 3, 4, 5, 6, false] }],
        [{ color: [] }, { background: [] }],
        [{ font: [] }],
        [{ align: [] }],
        ['clean']
    ];

    function initTextarea(textarea) {
        if (!textarea || textarea.dataset.quillReady === '1' || typeof window.Quill !== 'function') {
            return;
        }

        textarea.dataset.quillReady = '1';
        const editor = document.createElement('div');
        editor.className = 'eq-quill-editor';
        editor.innerHTML = String(textarea.value || textarea.getAttribute('value') || '');

        const wrapper = document.createElement('div');
        wrapper.className = 'eq-quill-wrap mb-2';
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(editor);
        textarea.style.display = 'none';
        wrapper.insertAdjacentElement('afterend', textarea);

        const quill = new window.Quill(editor, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions }
        });

        function sync() {
            textarea.value = quill.root.innerHTML;
        }

        quill.on('text-change', sync);
        textarea._eqRichTextSync = sync;
        sync();
    }

    function initAll(rootNode) {
        if (typeof window.Quill !== 'function') {
            return;
        }
        (rootNode || document).querySelectorAll('textarea[data-richtext]').forEach(initTextarea);
    }

    function loadQuillThenInit() {
        if (typeof window.Quill === 'function') {
            initAll(document);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js';
        script.onload = function () {
            initAll(document);
        };
        document.head.appendChild(script);
    }

    if (typeof MutationObserver === 'function') {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node && node.nodeType === 1) {
                        initAll(node.matches && node.matches('textarea[data-richtext]') ? node.parentNode : node);
                    }
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    document.getElementById('test-builder-form')?.addEventListener('submit', function () {
        document.querySelectorAll('textarea[data-richtext]').forEach(function (textarea) {
            if (typeof textarea._eqRichTextSync === 'function') {
                textarea._eqRichTextSync();
            }
        });
    });

    loadQuillThenInit();
    window.setTimeout(loadQuillThenInit, 500);
})();
</script>

<?php require_once dirname(__DIR__) . '/includes_footer.php'; ?>
