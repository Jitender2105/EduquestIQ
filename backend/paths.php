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
        try {
            if (($_POST['action'] ?? '') === 'path') {
                backend_require_admin($user);
                $stmt = $pdo->prepare('INSERT INTO learning_paths (title, description) VALUES (?, ?)');
                $stmt->execute([trim((string)$_POST['title']), trim((string)$_POST['description']) ?: null]);
                $success = 'Learning path created.';
            } elseif (($_POST['action'] ?? '') === 'path_course') {
                backend_require_admin($user);
                $stmt = $pdo->prepare('INSERT INTO path_courses (path_id, course_id, sequence_order) VALUES (?, ?, ?)');
                $stmt->execute([(int)$_POST['path_id'], (int)$_POST['course_id'], (int)$_POST['sequence_order']]);
                $success = 'Course added to path.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$paths = $pdo->query('SELECT id, title FROM learning_paths ORDER BY id DESC LIMIT 150')->fetchAll();
$courses = $pdo->query('SELECT id, title FROM courses ORDER BY id DESC LIMIT 250')->fetchAll();
$maps = $pdo->query('SELECT lp.title AS path_title, c.title AS course_title, pc.sequence_order FROM path_courses pc JOIN learning_paths lp ON lp.id=pc.path_id JOIN courses c ON c.id=pc.course_id ORDER BY pc.id DESC LIMIT 120')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Learning Paths Backend</h2><p class="subtitle">Define guided paths and sequence courses for progression models.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="path"><h6>Create Learning Path</h6><input class="form-control mb-2" name="title" required><textarea class="form-control mb-2 eq-richtext" data-richtext name="description" rows="2"></textarea><button class="btn btn-primary btn-sm">Create</button></form></div>
<div class="col-md-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="path_course"><h6>Add Course to Path</h6><select class="form-select mb-2" name="path_id" required><?php foreach ($paths as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="course_id" required><?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select><input class="form-control mb-2" name="sequence_order" type="number" min="1" value="1"><button class="btn btn-primary btn-sm">Add</button></form></div>
</div>
<div class="card p-3 mt-3"><h6>Recent Path Mappings</h6><ul class="small mb-0"><?php foreach ($maps as $m): ?><li><?php echo htmlspecialchars($m['path_title'].' -> '.$m['course_title'].' ('.$m['sequence_order'].')'); ?></li><?php endforeach; ?></ul></div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
