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
            if ($action === 'subject') {
                $stmt = $pdo->prepare('INSERT INTO subjects (code, name, domain) VALUES (?, ?, ?)');
                $stmt->execute([trim((string)$_POST['code']), trim((string)$_POST['name']), trim((string)$_POST['domain']) ?: null]);
                $success = 'Subject added.';
            } elseif ($action === 'grade') {
                $stmt = $pdo->prepare('INSERT INTO grade_levels (code, label, sort_order, age_min, age_max) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([trim((string)$_POST['code']), trim((string)$_POST['label']), (int)$_POST['sort_order'], (int)$_POST['age_min'], (int)$_POST['age_max']]);
                $success = 'Grade level added.';
            } elseif ($action === 'session') {
                $stmt = $pdo->prepare('INSERT INTO academic_sessions (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)');
                $stmt->execute([trim((string)$_POST['name']), $_POST['start_date'] ?: null, $_POST['end_date'] ?: null, isset($_POST['is_active']) ? 1 : 0]);
                $success = 'Academic session added.';
            } elseif ($action === 'category') {
                $stmt = $pdo->prepare('INSERT INTO course_categories (name, parent_id) VALUES (?, ?)');
                $parent = (int)($_POST['parent_id'] ?? 0);
                $stmt->execute([trim((string)$_POST['name']), $parent > 0 ? $parent : null]);
                $success = 'Category added.';
            } elseif ($action === 'tag') {
                $name = trim((string)$_POST['name']);
                $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
                $stmt = $pdo->prepare('INSERT INTO tags (name, slug, tag_type) VALUES (?, ?, ?)');
                $stmt->execute([$name, $slug, (string)($_POST['tag_type'] ?? 'course')]);
                $success = 'Tag added.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$subjects = $pdo->query('SELECT id, code, name, domain FROM subjects ORDER BY id DESC LIMIT 100')->fetchAll();
$grades = $pdo->query('SELECT id, code, label FROM grade_levels ORDER BY sort_order ASC, id ASC LIMIT 100')->fetchAll();
$sessions = $pdo->query('SELECT id, name, start_date, end_date, is_active FROM academic_sessions ORDER BY id DESC LIMIT 50')->fetchAll();
$categories = $pdo->query('SELECT id, name, parent_id FROM course_categories ORDER BY id DESC LIMIT 100')->fetchAll();
$tags = $pdo->query('SELECT id, name, slug, tag_type FROM tags ORDER BY id DESC LIMIT 120')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Taxonomy</h2><p class="subtitle">Academic taxonomy model: subjects, grades, sessions, categories, and tags.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-3">
<div class="col-lg-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="subject"><h6>Subject</h6><input class="form-control mb-2" name="code" placeholder="MATH"><input class="form-control mb-2" name="name" placeholder="Mathematics" required><input class="form-control mb-2" name="domain" placeholder="STEM"><button class="btn btn-primary btn-sm">Add</button></form></div>
<div class="col-lg-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="grade"><h6>Grade</h6><input class="form-control mb-2" name="code" placeholder="G8" required><input class="form-control mb-2" name="label" placeholder="Grade 8" required><div class="row g-2 mb-2"><div class="col"><input class="form-control" name="sort_order" type="number" value="8"></div><div class="col"><input class="form-control" name="age_min" type="number" value="12"></div><div class="col"><input class="form-control" name="age_max" type="number" value="14"></div></div><button class="btn btn-primary btn-sm">Add</button></form></div>
<div class="col-lg-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="session"><h6>Academic Session</h6><input class="form-control mb-2" name="name" placeholder="2026-2027" required><div class="row g-2 mb-2"><div class="col"><input class="form-control" type="date" name="start_date"></div><div class="col"><input class="form-control" type="date" name="end_date"></div></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="session_active"><label class="form-check-label" for="session_active">Active</label></div><button class="btn btn-primary btn-sm">Add</button></form></div>
<div class="col-lg-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="category"><h6>Course Category</h6><input class="form-control mb-2" name="name" placeholder="STEM Foundations" required><select class="form-select mb-2" name="parent_id"><option value="">No parent</option><?php foreach ($categories as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm">Add</button></form></div>
<div class="col-lg-6"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="tag"><h6>Tag</h6><input class="form-control mb-2" name="name" placeholder="Coding" required><select class="form-select mb-2" name="tag_type"><option value="course">Course</option><option value="skill">Skill</option><option value="resource">Resource</option></select><button class="btn btn-primary btn-sm">Add</button></form></div>
</div>

<div class="row g-3 mt-1">
<div class="col-md-4"><div class="card p-3"><h6>Subjects</h6><ul class="small mb-0"><?php foreach (array_slice($subjects,0,20) as $r): ?><li><?php echo htmlspecialchars($r['code'].' - '.$r['name']); ?></li><?php endforeach; ?></ul></div></div>
<div class="col-md-4"><div class="card p-3"><h6>Grades</h6><ul class="small mb-0"><?php foreach (array_slice($grades,0,20) as $r): ?><li><?php echo htmlspecialchars($r['label']); ?></li><?php endforeach; ?></ul></div></div>
<div class="col-md-4"><div class="card p-3"><h6>Tags</h6><ul class="small mb-0"><?php foreach (array_slice($tags,0,20) as $r): ?><li><?php echo htmlspecialchars($r['name'].' ['.$r['tag_type'].']'); ?></li><?php endforeach; ?></ul></div></div>
</div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
