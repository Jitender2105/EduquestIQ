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
            if ($action === 'test') {
                $stmt = $pdo->prepare('INSERT INTO tests (title, description, created_by, total_marks, duration_minutes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([trim((string)$_POST['title']), trim((string)$_POST['description']) ?: null, (int)$user['sub'], (int)$_POST['total_marks'], (int)$_POST['duration_minutes']]);
                $success = 'Test created.';
            } elseif ($action === 'test_question') {
                $stmt = $pdo->prepare('INSERT INTO test_questions (test_id, question_id, marks) VALUES (?, ?, ?)');
                $stmt->execute([(int)$_POST['test_id'], (int)$_POST['question_id'], (int)$_POST['marks']]);
                $success = 'Question added to test.';
            } elseif ($action === 'settings') {
                $stmt = $pdo->prepare('INSERT INTO test_settings (test_id, test_type, pass_marks, attempts_allowed, availability_start, availability_end, proctoring_mode) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE test_type=VALUES(test_type), pass_marks=VALUES(pass_marks), attempts_allowed=VALUES(attempts_allowed), availability_start=VALUES(availability_start), availability_end=VALUES(availability_end), proctoring_mode=VALUES(proctoring_mode)');
                $stmt->execute([(int)$_POST['test_id'], (string)$_POST['test_type'], (int)$_POST['pass_marks'], (int)$_POST['attempts_allowed'], $_POST['availability_start'] ?: null, $_POST['availability_end'] ?: null, (string)$_POST['proctoring_mode']]);
                $success = 'Test settings saved.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$tests = $pdo->query('SELECT id, title, total_marks, duration_minutes FROM tests ORDER BY id DESC LIMIT 200')->fetchAll();
$questions = $pdo->query('SELECT id, question_text FROM questions ORDER BY id DESC LIMIT 200')->fetchAll();
$settings = $pdo->query('SELECT test_id, test_type, pass_marks, attempts_allowed FROM test_settings ORDER BY updated_at DESC LIMIT 100')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Tests Backend</h2><p class="subtitle">Assessment orchestration and operational test settings.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="test"><h6>Create Test</h6><input class="form-control mb-2" name="title" required><textarea class="form-control mb-2" name="description" rows="2"></textarea><div class="row g-2 mb-2"><div class="col"><input class="form-control" name="total_marks" type="number" min="1" value="100"></div><div class="col"><input class="form-control" name="duration_minutes" type="number" min="1" value="60"></div></div><button class="btn btn-primary btn-sm">Create</button></form></div>
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="test_question"><h6>Add Question to Test</h6><select class="form-select mb-2" name="test_id" required><?php foreach ($tests as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="question_id" required><?php foreach ($questions as $q): ?><option value="<?php echo (int)$q['id']; ?>">#<?php echo (int)$q['id']; ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="marks" type="number" min="1" value="1"><button class="btn btn-primary btn-sm">Attach</button></form></div>
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="settings"><h6>Test Settings</h6><select class="form-select mb-2" name="test_id" required><?php foreach ($tests as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="test_type"><option value="practice">Practice</option><option value="graded">Graded</option><option value="diagnostic">Diagnostic</option></select><div class="row g-2 mb-2"><div class="col"><input class="form-control" type="number" name="pass_marks" min="0" value="40"></div><div class="col"><input class="form-control" type="number" name="attempts_allowed" min="1" value="1"></div></div><div class="row g-2 mb-2"><div class="col"><input class="form-control" type="datetime-local" name="availability_start"></div><div class="col"><input class="form-control" type="datetime-local" name="availability_end"></div></div><select class="form-select mb-2" name="proctoring_mode"><option value="none">None</option><option value="camera">Camera</option><option value="browser_lock">Browser Lock</option></select><button class="btn btn-primary btn-sm">Save</button></form></div>
</div>
<div class="card p-3 mt-3"><h6>Recent Test Settings</h6><ul class="small mb-0"><?php foreach ($settings as $s): ?><li>Test #<?php echo (int)$s['test_id']; ?> - <?php echo htmlspecialchars((string)$s['test_type']); ?>, pass <?php echo (int)$s['pass_marks']; ?>, attempts <?php echo (int)$s['attempts_allowed']; ?></li><?php endforeach; ?></ul></div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
