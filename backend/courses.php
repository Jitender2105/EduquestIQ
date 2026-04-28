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
            if ($action === 'course') {
                backend_require_admin($user);
                $teacherId = backend_is_admin($user) ? (int)($_POST['teacher_id'] ?? 0) : (int)$user['sub'];
                $stmt = $pdo->prepare('INSERT INTO courses (title, description, teacher_id, attribute_id, created_at) VALUES (?, ?, ?, ?, NOW())');
                $attr = (int)($_POST['attribute_id'] ?? 0);
                $stmt->execute([trim((string)$_POST['title']), trim((string)$_POST['description']) ?: null, $teacherId, $attr > 0 ? $attr : null]);
                $success = 'Course created.';
            } elseif ($action === 'taxonomy_map') {
                backend_require_admin($user);
                $stmt = $pdo->prepare('INSERT INTO course_taxonomy_map (course_id, subject_id, grade_level_id, academic_session_id, category_id, level, language, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE subject_id=VALUES(subject_id), grade_level_id=VALUES(grade_level_id), academic_session_id=VALUES(academic_session_id), category_id=VALUES(category_id), level=VALUES(level), language=VALUES(language), status=VALUES(status)');
                $stmt->execute([(int)$_POST['course_id'], (int)$_POST['subject_id'], (int)$_POST['grade_level_id'], (int)$_POST['academic_session_id'], (int)$_POST['category_id'], (string)$_POST['level'], trim((string)$_POST['language']) ?: 'en', (string)$_POST['status']]);
                $success = 'Course taxonomy mapping saved.';
            } elseif ($action === 'tag_map') {
                backend_require_admin($user);
                $stmt = $pdo->prepare('INSERT IGNORE INTO course_tag_map (course_id, tag_id) VALUES (?, ?)');
                $stmt->execute([(int)$_POST['course_id'], (int)$_POST['tag_id']]);
                $success = 'Tag mapped.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY id DESC LIMIT 200')->fetchAll();
$teachers = $pdo->query('SELECT id, name FROM users WHERE role="teacher" AND status="active" ORDER BY name')->fetchAll();
$attributes = $pdo->query('SELECT id, name FROM attributes ORDER BY name')->fetchAll();
$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
$grades = $pdo->query('SELECT id, label FROM grade_levels ORDER BY sort_order, id')->fetchAll();
$sessions = $pdo->query('SELECT id, name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM course_categories ORDER BY name')->fetchAll();
$tags = $pdo->query('SELECT id, name FROM tags WHERE tag_type IN ("course","skill") ORDER BY name LIMIT 300')->fetchAll();
$maps = $pdo->query('SELECT c.title, s.name AS subject_name, g.label AS grade_label, a.name AS session_name, cc.name AS category_name, m.level, m.status FROM course_taxonomy_map m JOIN courses c ON c.id=m.course_id LEFT JOIN subjects s ON s.id=m.subject_id LEFT JOIN grade_levels g ON g.id=m.grade_level_id LEFT JOIN academic_sessions a ON a.id=m.academic_session_id LEFT JOIN course_categories cc ON cc.id=m.category_id ORDER BY m.updated_at DESC LIMIT 80')->fetchAll();

require_once dirname(__DIR__) . '/includes_header.php';
?>
<div class="eq-page-head"><h2>Courses Backend</h2><p class="subtitle">Course records with taxonomy mapping for discoverability and governance.</p></div>
<?php require __DIR__ . '/nav.php'; ?>
<?php require __DIR__ . '/richtext.php'; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row g-3">
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="course"><h6>Create Course</h6><input class="form-control mb-2" name="title" required><textarea class="form-control mb-2 eq-richtext" data-richtext name="description" rows="2"></textarea><?php if (backend_is_admin($user)): ?><select class="form-select mb-2" name="teacher_id" required><?php foreach ($teachers as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select><?php endif; ?><select class="form-select mb-2" name="attribute_id"><option value="">Attribute</option><?php foreach ($attributes as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm">Create</button></form></div>
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="taxonomy_map"><h6>Course Taxonomy</h6><select class="form-select mb-2" name="course_id" required><?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="subject_id" required><?php foreach ($subjects as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="grade_level_id" required><?php foreach ($grades as $g): ?><option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['label']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="academic_session_id" required><?php foreach ($sessions as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select><div class="row g-2 mb-2"><div class="col"><select class="form-select" name="level"><option>beginner</option><option>intermediate</option><option>advanced</option></select></div><div class="col"><input class="form-control" name="language" value="en"></div></div><select class="form-select mb-2" name="status"><option value="draft">draft</option><option value="published">published</option><option value="archived">archived</option></select><button class="btn btn-primary btn-sm">Save</button></form></div>
<div class="col-md-4"><form method="post" class="card p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="tag_map"><h6>Map Tag</h6><select class="form-select mb-2" name="course_id" required><?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select><select class="form-select mb-2" name="tag_id" required><?php foreach ($tags as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm">Add Tag</button></form></div>
</div>
<div class="card p-3 mt-3"><h6>Recent Taxonomy Mappings</h6><ul class="small mb-0"><?php foreach ($maps as $m): ?><li><?php echo htmlspecialchars($m['title'].' | '.$m['subject_name'].' | '.$m['grade_label'].' | '.$m['session_name'].' | '.$m['category_name'].' | '.$m['level'].' | '.$m['status']); ?></li><?php endforeach; ?></ul></div>
<?php require_once dirname(__DIR__) . '/includes_footer.php';
