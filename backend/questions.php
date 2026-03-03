<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$user = backend_user();
$pdo = get_pdo();

$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'question') {
                $stmt = $pdo->prepare('INSERT INTO questions (question_text, question_type, difficulty, created_by, created_at) VALUES (?, ?, ?, ?, NOW())');
                $stmt->execute([trim((string)$_POST['question_text']), (string)$_POST['question_type'], (string)$_POST['difficulty'], (int)$user['sub']]);
                $success = 'Question created.';
            } elseif ($action === 'option') {
                $stmt = $pdo->prepare('INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
                $stmt->execute([(int)$_POST['question_id'], trim((string)$_POST['option_text']), isset($_POST['is_correct']) ? 1 : 0]);
                $success = 'Option added.';
            } elseif ($action === 'mapping') {
                $stmt = $pdo->prepare('INSERT INTO question_attribute_mapping (question_id, attribute_id, sub_attribute_id, weight) VALUES (?, ?, ?, ?)');
                $stmt->execute([(int)$_POST['question_id'], (int)$_POST['attribute_id'], (int)$_POST['sub_attribute_id'], (float)$_POST['weight']]);
                $success = 'Mapping added.';
            } elseif ($action === 'blueprint') {
                $stmt = $pdo->prepare('INSERT INTO question_blueprint (question_id, bloom_level, competency_code, learning_objective, estimated_time_seconds, hint_text) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE bloom_level=VALUES(bloom_level), competency_code=VALUES(competency_code), learning_objective=VALUES(learning_objective), estimated_time_seconds=VALUES(estimated_time_seconds), hint_text=VALUES(hint_text)');
                $stmt->execute([(int)$_POST['question_id'], (string)$_POST['bloom_level'], trim((string)$_POST['competency_code']) ?: null, trim((string)$_POST['learning_objective']) ?: null, (int)$_POST['estimated_time_seconds'], trim((string)$_POST['hint_text']) ?: null]);
                $success = 'Question blueprint saved.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$questions = $pdo->query('SELECT id, question_text, question_type, difficulty FROM questions ORDER BY id DESC LIMIT 200')->fetchAll();
$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name')->fetchAll();
$subs = $pdo->query('SELECT id, name, attribute_id FROM sub_attributes ORDER BY name')->fetchAll();
$blueprints = $pdo->query('SELECT qb.question_id, qb.bloom_level, qb.competency_code FROM question_blueprint qb ORDER BY qb.question_id DESC LIMIT 80')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Questions Backend</h2><p class="subtitle">Question bank, options, attribute mappings, and pedagogical blueprint.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="question"><h6>Create Question</h6><textarea class="form-control mb-2" name="question_text" rows="3" required></textarea><div class="row g-2 mb-2"><div class="col"><select class="form-select" name="question_type"><option value="mcq">MCQ</option><option value="subjective">Subjective</option></select></div><div class="col"><select class="form-select" name="difficulty"><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select></div></div><button class="btn btn-primary btn-sm">Save</button></form></div>
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="option"><h6>Add Option</h6><select class="form-select mb-2" name="question_id" required><?php foreach ($questions as $q): ?><option value="<?php echo (int)$q['id']; ?>">#<?php echo (int)$q['id']; ?> <?php echo htmlspecialchars(text_preview((string)$q['question_text'], 45, '...')); ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="option_text" required><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_correct" id="is_correct"><label class="form-check-label" for="is_correct">Correct option</label></div><button class="btn btn-primary btn-sm">Add</button></form></div>
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="mapping"><h6>Question → Skill Mapping</h6><select class="form-select mb-2" name="question_id" required><?php foreach ($questions as $q): ?><option value="<?php echo (int)$q['id']; ?>">#<?php echo (int)$q['id']; ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="attribute_id" required><?php foreach ($attributes as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="sub_attribute_id" required><?php foreach ($subs as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select><input class="form-control mb-2" type="number" step="0.01" min="0.01" name="weight" value="1.00" required><button class="btn btn-primary btn-sm">Map</button></form></div>
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="blueprint"><h6>Question Blueprint</h6><select class="form-select mb-2" name="question_id" required><?php foreach ($questions as $q): ?><option value="<?php echo (int)$q['id']; ?>">#<?php echo (int)$q['id']; ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="bloom_level"><option>remember</option><option>understand</option><option>apply</option><option>analyze</option><option>evaluate</option><option>create</option></select><input class="form-control mb-2" name="competency_code" placeholder="COMP-MATH-01"><textarea class="form-control mb-2" name="learning_objective" rows="2" placeholder="Learning objective"></textarea><input class="form-control mb-2" type="number" min="10" name="estimated_time_seconds" value="60"><input class="form-control mb-2" name="hint_text" placeholder="Hint (optional)"><button class="btn btn-primary btn-sm">Save Blueprint</button></form></div>
</div>
<div class="card p-3 mt-3"><h6>Recent Question Blueprints</h6><ul class="small mb-0"><?php foreach ($blueprints as $b): ?><li>Q#<?php echo (int)$b['question_id']; ?> - <?php echo htmlspecialchars((string)$b['bloom_level']); ?> <?php echo !empty($b['competency_code']) ? '(' . htmlspecialchars((string)$b['competency_code']) . ')' : ''; ?></li><?php endforeach; ?></ul></div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
